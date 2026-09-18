<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\V3CertificateRepository;
use App\Repositories\V3CertificateTemplateRepository;
use App\Services\AuditService;
use App\Services\CertificateRenderer;
use App\Services\RoleService;
use Throwable;

/**
 * Administrative Certificate Repository Controller
 * Manage, filter, inspect, download, and invalidate generated V3 certificates.
 */
class CertificateRepositoryController
{
    private V3CertificateRepository $certRepo;
    private V3CertificateTemplateRepository $templateRepo;
    private AuditService $auditService;

    public function __construct(
        ?V3CertificateRepository $certRepo = null,
        ?V3CertificateTemplateRepository $templateRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->certRepo = $certRepo ?? new V3CertificateRepository();
        $this->templateRepo = $templateRepo ?? new V3CertificateTemplateRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * List all certificates with filtering and pagination.
     * GET /admin/certificates
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'search'      => trim((string) $request->input('search', '')),
            'status'      => trim((string) $request->input('status', '')),
            'template_id' => $request->input('template_id') ? (int) $request->input('template_id') : null,
            'date'        => trim((string) $request->input('date', '')),
        ];

        // Clean out empty filters
        $activeFilters = array_filter($filters, fn($val) => $val !== null && $val !== '');

        $certificates = $this->certRepo->search($activeFilters, $limit, $offset);
        $totalCount = $this->certRepo->countSearch($activeFilters);
        $totalPages = (int) ceil($totalCount / $limit);

        $templates = $this->templateRepo->getAll();

        return Response::html(View::render('admin/certificates/index', [
            'title'        => 'Certificate Repository',
            'certificates' => $certificates,
            'templates'    => $templates,
            'filters'      => $filters,
            'pagination'   => [
                'current_page' => $page,
                'total_pages'  => max(1, $totalPages),
                'total_count'  => $totalCount,
                'limit'        => $limit,
            ],
            'activeNav'    => 'certificates',
        ], 'layouts/admin'));
    }

    /**
     * Show single certificate inspector.
     * GET /admin/certificates/{id}
     */
    public function show(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $cert = $this->certRepo->findById($id);

        if (!$cert) {
            Session::flash('error', 'Certificate not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        return Response::html(View::render('admin/certificates/show', [
            'title'        => 'Certificate ' . $cert['certificate_id'],
            'certificate'  => $cert,
            'activeNav'    => 'certificates',
        ], 'layouts/admin'));
    }

    /**
     * Stream or download high-resolution certificate PDF.
     * GET /admin/certificates/{id}/pdf
     */
    public function downloadPdf(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $cert = $this->certRepo->findById($id);

        if (!$cert) {
            Session::flash('error', 'Certificate not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        $pdfContent = '';
        $baseDir = dirname(__DIR__, 3);
        $fullPath = $cert['pdf_path'] ? $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cert['pdf_path']), DIRECTORY_SEPARATOR) : '';

        if (!empty($fullPath) && file_exists($fullPath)) {
            $pdfContent = (string) file_get_contents($fullPath);
        } else {
            // Dynamically render if file is absent on disk
            $template = $this->templateRepo->findById((int) $cert['template_id']);
            if ($template) {
                $snapshot = json_decode((string) ($cert['snapshot_data'] ?? '{}'), true) ?: [];
                $certData = array_merge($snapshot, [
                    'name'               => $cert['name'],
                    'phone'              => $cert['phone'],
                    'certificate_number' => $cert['certificate_id'],
                    'verification_token' => $cert['verification_token'],
                    'event_title'        => $cert['event_title'],
                    'date'               => $cert['date'],
                ]);
                $isRevoked = in_array($cert['status'], ['invalid', 'revoked'], true);
                $pdfContent = CertificateRenderer::renderPdf($template, $certData, $isRevoked);
            }
        }

        if (empty($pdfContent)) {
            Session::flash('error', 'Could not retrieve PDF data.');
            return Response::redirect(url('/admin/certificates/' . $id));
        }

        $filename = "Certificate_{$cert['certificate_id']}.pdf";
        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    /**
     * Stream or download high-resolution certificate Image (JPEG).
     * GET /admin/certificates/{id}/image
     */
    public function downloadImage(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $cert = $this->certRepo->findById($id);

        if (!$cert) {
            Session::flash('error', 'Certificate not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        $imgContent = '';
        $baseDir = dirname(__DIR__, 3);
        $fullPath = $cert['image_path'] ? $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cert['image_path']), DIRECTORY_SEPARATOR) : '';

        if (!empty($fullPath) && file_exists($fullPath)) {
            $imgContent = (string) file_get_contents($fullPath);
        } else {
            // Dynamically render if file is absent on disk
            $template = $this->templateRepo->findById((int) $cert['template_id']);
            if ($template) {
                $snapshot = json_decode((string) ($cert['snapshot_data'] ?? '{}'), true) ?: [];
                $certData = array_merge($snapshot, [
                    'name'               => $cert['name'],
                    'phone'              => $cert['phone'],
                    'certificate_number' => $cert['certificate_id'],
                    'verification_token' => $cert['verification_token'],
                    'event_title'        => $cert['event_title'],
                    'date'               => $cert['date'],
                ]);
                $isRevoked = in_array($cert['status'], ['invalid', 'revoked'], true);
                $imgContent = CertificateRenderer::renderJpg($template, $certData, $isRevoked);
            }
        }

        if (empty($imgContent)) {
            Session::flash('error', 'Could not retrieve certificate image.');
            return Response::redirect(url('/admin/certificates/' . $id));
        }

        $filename = "Certificate_{$cert['certificate_id']}.jpg";
        return new Response($imgContent, 200, [
            'Content-Type'        => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    /**
     * Invalidate / Revoke a Certificate.
     * POST /admin/certificates/{id}/invalidate
     */
    public function invalidate(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $reason = trim((string) $request->input('reason', ''));

        if (empty($reason)) {
            Session::flash('error', 'A mandatory reason is required to invalidate a certificate.');
            return Response::redirect(url('/admin/certificates/' . $id));
        }

        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $cert = $this->certRepo->findById($id);
        if (!$cert) {
            Session::flash('error', 'Certificate not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        $success = $this->certRepo->invalidate($id, $currentUserId, $reason);

        if ($success) {
            // Re-render and overwrite on-disk artifacts with revocation stamp
            $template = $this->templateRepo->findById((int) $cert['template_id']);
            if ($template) {
                $snapshot = json_decode((string) ($cert['snapshot_data'] ?? '{}'), true) ?: [];
                $certData = array_merge($snapshot, [
                    'name'               => $cert['name'],
                    'phone'              => $cert['phone'],
                    'certificate_number' => $cert['certificate_id'],
                    'verification_token' => $cert['verification_token'],
                    'event_title'        => $cert['event_title'],
                    'date'               => $cert['date'],
                ]);
                $baseDir = dirname(__DIR__, 3);
                $pdfPath = $cert['pdf_path'] ? $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cert['pdf_path']), DIRECTORY_SEPARATOR) : '';
                $imgPath = $cert['image_path'] ? $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cert['image_path']), DIRECTORY_SEPARATOR) : '';

                if ($pdfPath && file_exists(dirname($pdfPath))) {
                    @file_put_contents($pdfPath, CertificateRenderer::renderPdf($template, $certData, true));
                }
                if ($imgPath && file_exists(dirname($imgPath))) {
                    @file_put_contents($imgPath, CertificateRenderer::renderJpg($template, $certData, true));
                }
            }

            $this->auditService->log(
                'certificate.invalidate',
                'v3_certificates',
                $id,
                [
                    'certificate_id' => $cert['certificate_id'],
                    'reason'         => $reason,
                    'actor_role'     => $currentUserRole,
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Certificate {$cert['certificate_id']} has been invalidated.");
        } else {
            Session::flash('error', 'Failed to invalidate certificate.');
        }

        return Response::redirect(url('/admin/certificates/' . $id));
    }

    /**
     * Soft-delete / Archive certificate.
     * POST /admin/certificates/{id}/delete
     */
    public function destroy(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $cert = $this->certRepo->findById($id);
        if (!$cert) {
            Session::flash('error', 'Certificate not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        $success = $this->certRepo->softDelete($id, $currentUserId);

        if ($success) {
            $this->auditService->log(
                'certificate.delete',
                'v3_certificates',
                $id,
                [
                    'certificate_id' => $cert['certificate_id'],
                    'actor_role'     => $currentUserRole,
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Certificate {$cert['certificate_id']} archived.");
        } else {
            Session::flash('error', 'Failed to archive certificate.');
        }

        return Response::redirect(url('/admin/certificates'));
    }
}
