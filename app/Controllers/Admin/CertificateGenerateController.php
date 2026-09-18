<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\V3CertificateTemplateRepository;
use App\Services\AuditService;
use App\Services\CertificateBatchService;
use App\Services\CsvValidationService;
use App\Services\RoleService;
use Throwable;

/**
 * Automated Certificate Generation Controller
 * Multi-step workflow: Template selection, sample CSV download, multi-layer validation,
 * row error reporting, and chunk/batch oriented generation.
 */
class CertificateGenerateController
{
    private V3CertificateTemplateRepository $templateRepo;
    private AuditService $auditService;

    public function __construct(
        ?V3CertificateTemplateRepository $templateRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->templateRepo = $templateRepo ?? new V3CertificateTemplateRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Show Certificate Generation Page.
     * GET /admin/certificates/generate
     */
    public function index(Request $request): Response
    {
        $templates = $this->templateRepo->getAll(true); // Active templates only

        return Response::html(View::render('admin/certificates/generate', [
            'title'     => 'Generate Certificates',
            'templates' => $templates,
            'activeNav' => 'certificates/generate',
        ], 'layouts/admin'));
    }

    /**
     * Download Template-Specific Sample CSV.
     * GET /admin/certificates/sample-csv/{id}
     */
    public function sampleCsv(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $template = $this->templateRepo->findById($id);
        if (!$template) {
            Session::flash('error', 'Template not found.');
            return Response::redirect(url('/admin/certificates/generate'));
        }

        $csv = CsvValidationService::generateSampleCsv($template);
        $safeName = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['name']));
        $filename = "sample_certificate_data_{$safeName}.csv";

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Validate Uploaded CSV.
     * POST /admin/certificates/validate-csv
     */
    public function validateCsv(Request $request): Response
    {
        $templateId = (int) $request->input('template_id', 0);
        $template = $this->templateRepo->findById($templateId);
        if (!$template) {
            return Response::json(['error' => 'Please select a valid certificate template.'], 422);
        }

        if (empty($_FILES['csv_file']['tmp_name']) || !file_exists($_FILES['csv_file']['tmp_name'])) {
            return Response::json(['error' => 'Please select a CSV file to upload.'], 422);
        }

        $file = $_FILES['csv_file'];
        if ($file['size'] > CsvValidationService::MAX_FILE_SIZE_BYTES) {
            return Response::json(['error' => 'CSV file exceeds maximum allowed size of 10 MB.'], 422);
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            return Response::json(['error' => 'Failed to read uploaded file.'], 500);
        }

        try {
            $validation = CsvValidationService::validate($content, $template);

            // Store validated records in session for the generation step
            Session::set('pending_certificate_batch', [
                'template_id'   => $templateId,
                'filename'      => $file['name'],
                'valid_records' => $validation['valid_records'],
                'invalid_count' => $validation['invalid_count'],
                'invalid_rows'  => $validation['invalid_rows'],
            ]);

            return Response::json([
                'status'         => 'success',
                'total_records'  => $validation['total_records'],
                'valid_count'    => $validation['valid_count'],
                'invalid_count'  => $validation['invalid_count'],
                'invalid_rows'   => $validation['invalid_rows'],
            ]);
        } catch (ValidationException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Response::json(['error' => 'Validation error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download CSV Error Report for invalid rows.
     * POST /admin/certificates/download-error-report
     */
    public function downloadErrorReport(Request $request): Response
    {
        $batch = Session::get('pending_certificate_batch', []);
        $invalidRows = $batch['invalid_rows'] ?? [];

        if (empty($invalidRows)) {
            Session::flash('error', 'No error report available.');
            return Response::redirect(url('/admin/certificates/generate'));
        }

        $csv = CsvValidationService::generateErrorReportCsv($invalidRows);
        $filename = 'csv_validation_errors_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Start Batch (initializes batch record and staging).
     * POST /admin/certificates/start-batch
     */
    public function startBatch(Request $request): Response
    {
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $pending = Session::get('pending_certificate_batch', []);
        if (empty($pending['valid_records'])) {
            return Response::json(['error' => 'No valid records found in session. Please validate CSV first.'], 422);
        }

        $templateId = (int) $pending['template_id'];
        $validRecords = (array) $pending['valid_records'];
        $invalidCount = (int) ($pending['invalid_count'] ?? 0);
        $filename = (string) ($pending['filename'] ?? 'upload.csv');

        try {
            $batch = CertificateBatchService::createBatch($templateId, $validRecords, $invalidCount, $currentUserId, $filename);

            $this->auditService->log(
                'certificate_batch.start',
                'v3_certificate_batches',
                $batch['id'],
                [
                    'batch_code'  => $batch['batch_code'],
                    'valid_count' => count($validRecords),
                    'actor_role'  => $currentUserRole,
                ],
                $currentUserId,
                'admin'
            );

            return Response::json([
                'status'        => 'success',
                'batch_id'      => $batch['id'],
                'batch_code'    => $batch['batch_code'],
                'total_valid'   => count($validRecords),
            ]);
        } catch (Throwable $e) {
            return Response::json(['error' => 'Failed to initialize batch: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Process Next Chunk of Certificates (called iteratively by frontend).
     * POST /admin/certificates/process-chunk
     */
    public function processChunk(Request $request): Response
    {
        $batchId = (int) $request->input('batch_id', 0);
        $chunkSize = (int) $request->input('chunk_size', CertificateBatchService::CHUNK_SIZE);

        if ($batchId <= 0) {
            return Response::json(['error' => 'Invalid batch ID.'], 422);
        }

        try {
            $result = CertificateBatchService::processChunk($batchId, $chunkSize);
            return Response::json($result);
        } catch (Throwable $e) {
            return Response::json(['error' => 'Chunk processing failed: ' . $e->getMessage()], 500);
        }
    }
}
