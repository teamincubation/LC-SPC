<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\CertificateException;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CertificateRepository;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Services\AuditService;
use App\Services\CertificateService;
use App\Services\RoleService;
use Throwable;

/**
 * Administrative Certificate Controller
 * Manages certificate lifecycle: global directory, event certificate hub,
 * candidate issuance, bulk batches, supersession re-issuance, and on-demand downloads.
 */
class CertificateController
{
    private CertificateService $certService;
    private CertificateRepository $certRepo;
    private EventRepository $eventRepo;
    private RegistrationRepository $regRepo;
    private \App\Repositories\CertificateTemplateRepository $templateRepo;
    private AuditService $auditService;

    public function __construct(
        ?CertificateService $certService = null,
        ?CertificateRepository $certRepo = null,
        ?EventRepository $eventRepo = null,
        ?RegistrationRepository $regRepo = null,
        ?\App\Repositories\CertificateTemplateRepository $templateRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->certService = $certService ?? new CertificateService();
        $this->certRepo = $certRepo ?? new CertificateRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->regRepo = $regRepo ?? new RegistrationRepository();
        $this->templateRepo = $templateRepo ?? new \App\Repositories\CertificateTemplateRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Global Certificate Directory.
     * GET /admin/certificates
     * Role: viewer+
     */
    public function index(Request $request): Response
    {
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);

        $filters = [
            'event_id'    => $request->query('event_id') ? (int) $request->query('event_id') : null,
            'campaign_id' => $request->query('campaign_id') ? (int) $request->query('campaign_id') : null,
            'status'      => $request->query('status') ? trim((string) $request->query('status')) : null,
            'type'        => $request->query('type') ? trim((string) $request->query('type')) : null,
            'search'      => $request->query('search') ? trim((string) $request->query('search')) : null,
        ];

        $page = max(1, (int) $request->query('page', 1));
        $pagination = $this->certRepo->paginate($filters, $page, 50);
        $pagination['items'] = $this->certService->maskCertificateList($pagination['items'], $userRole);

        $metrics = $this->certRepo->getMetrics();

        return Response::html(
            View::render('admin/certificates/index', [
                'title'         => 'Certificates Directory',
                'certificates'  => $pagination['items'],
                'pagination'    => $pagination,
                'metrics'       => $metrics,
                'filters'       => $filters,
                'userRole'      => $userRole,
                'isCoordinator' => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            ], 'layouts/admin')
        );
    }

    /**
     * Event Certificate Hub & Candidate Issuance.
     * GET /admin/events/{id}/certificates
     * Role: viewer+
     */
    public function eventCertificates(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);

        $event = $this->eventRepo->findById($eventId);
        if (!$event || !empty($event['deleted_at'])) {
            Session::flash('error', 'Event not found or has been deleted.');
            return Response::redirect(url('/admin/events'));
        }

        $type = $request->query('type', CertificateService::TYPE_PARTICIPATION);
        if (!in_array($type, CertificateService::VALID_TYPES, true)) {
            $type = CertificateService::TYPE_PARTICIPATION;
        }

        // Issued certificates for this event
        $filters = ['event_id' => $eventId, 'type' => $type];
        $page = max(1, (int) $request->query('page', 1));
        $pagination = $this->certRepo->paginate($filters, $page, 50);
        $pagination['items'] = $this->certService->maskCertificateList($pagination['items'], $userRole);

        // Eligible candidates who attended and don't have active certificate of this type
        $candidates = $this->certRepo->getEligibleCandidatesForEvent($eventId, $type);

        $metrics = $this->certRepo->getMetrics($eventId);
        $isEventStarted = time() >= strtotime((string) $event['start_time']);

        return Response::html(
            View::render('admin/certificates/event', [
                'title'          => 'Certificates — ' . $event['title'],
                'event'          => $event,
                'type'           => $type,
                'certificates'   => $pagination['items'],
                'pagination'     => $pagination,
                'candidates'     => $candidates,
                'metrics'        => $metrics,
                'isEventStarted' => $isEventStarted,
                'userRole'       => $userRole,
                'isCoordinator'  => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            ], 'layouts/admin')
        );
    }

    /**
     * Issue individual certificate.
     * POST /admin/events/{id}/certificates/issue
     * Role: coordinator+
     */
    public function issueSingle(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_COORDINATOR);

        $regId = (int) $request->input('registration_id', 0);
        $type = trim((string) $request->input('type', CertificateService::TYPE_PARTICIPATION));

        $options = [
            'confirm_flagged'      => (bool) $request->input('confirm_flagged', false),
            'flag_override_reason' => trim((string) $request->input('flag_override_reason', '')),
        ];

        try {
            $cert = $this->certService->issueSingle($regId, $type, $userId, $userRole, $options);
            Session::flash('success', "Certificate {$cert['certificate_number']} issued successfully for {$cert['recipient_name_snapshot']}.");
        } catch (CertificateException|ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'An unexpected error occurred during certificate issuance.');
        }

        return Response::redirect(url("/admin/events/{$eventId}/certificates?type={$type}"));
    }

    /**
     * Controlled Bulk Certificate Issuance.
     * POST /admin/events/{id}/certificates/bulk
     * Role: coordinator+
     */
    public function bulkIssue(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_COORDINATOR);

        $type = trim((string) $request->input('type', CertificateService::TYPE_PARTICIPATION));
        $registrationIds = (array) $request->input('registration_ids', []);

        try {
            $result = $this->certService->bulkIssue($eventId, $type, $registrationIds, $userId, $userRole);
            $msg = "Bulk issuance completed: {$result['issued']} issued, {$result['already_issued']} already issued, {$result['skipped']} skipped.";
            if ($result['failed'] > 0) {
                $msg .= " ({$result['failed']} failed)";
            }
            Session::flash('success', $msg);
        } catch (CertificateException|ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'An error occurred during bulk certificate generation.');
        }

        return Response::redirect(url("/admin/events/{$eventId}/certificates?type={$type}"));
    }

    /**
     * Certificate Detail & Inspection View.
     * GET /admin/certificates/{id}
     * Role: viewer+
     */
    public function show(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);

        $cert = $this->certService->getCertificateById($id, $userRole);
        if (!$cert) {
            Session::flash('error', 'Certificate not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        // Certificate history for this registration (superseded/previous records)
        $history = $this->certRepo->getHistoryByRegistration((int) $cert['registration_id']);

        // Generate QR data URI for preview
        $token = $cert['verification_token'] ?? '';
        $verifyUrl = "https://teami.in/LC/verify/{$token}";
        $qrDataUri = !empty($token) ? QrCode::dataUri($verifyUrl, 180) : '';

        return Response::html(
            View::render('admin/certificates/show', [
                'title'         => 'Certificate ' . $cert['certificate_number'],
                'certificate'   => $cert,
                'history'       => $history,
                'qrDataUri'     => $qrDataUri,
                'verifyUrl'     => $verifyUrl,
                'userRole'      => $userRole,
                'isCoordinator' => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
                'canDownload'   => RoleService::hasRole($userRole, RoleService::ROLE_STAFF),
            ], 'layouts/admin')
        );
    }

    /**
     * Print-Ready Vector Certificate Layout (PDF printable).
     * GET /admin/certificates/{id}/print
     * Role: staff+
     */
    public function printCertificate(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_STAFF);

        $cert = $this->certService->getRawCertificateById($id);
        if (!$cert) {
            return Response::html(View::render('errors/404', ['title' => 'Certificate Not Found', 'message' => 'The requested certificate record does not exist.']), 404);
        }

        // Active check: only active certificates can be officially printed
        if (($cert['status'] ?? '') === 'revoked') {
            return Response::html(View::render('errors/403', ['title' => 'Certificate Revoked', 'message' => 'This certificate has been officially revoked and cannot be printed as a valid credential.']), 403);
        }

        // Generate SVG vector QR
        $verifyUrl = "https://teami.in/LC/verify/{$cert['verification_token']}";
        $qrSvg = QrCode::svg($verifyUrl, 200, 2);

        $signatories = $this->certService->getSignatories($cert);

        return Response::html(
            View::render('admin/certificates/print', [
                'title'       => 'Official Certificate — ' . $cert['certificate_number'],
                'certificate' => $cert,
                'qrSvg'       => $qrSvg,
                'verifyUrl'   => $verifyUrl,
                'signatories' => $signatories,
                'autoPrint'   => (bool) $request->query('auto_print', false),
            ])
        );
    }

    /**
     * On-Demand Download Gateway (PDF or JPG).
     * GET /admin/certificates/{id}/download?format=pdf|jpg
     * Role: staff+
     */
    public function download(Request $request, array $vars): Response
    {
        $format = strtolower(trim((string) $request->query('format', 'pdf')));
        if ($format === 'jpg' || $format === 'jpeg') {
            return $this->downloadJpg($request, $vars);
        }

        return $this->downloadPdf($request, $vars);
    }

    /**
     * Direct PDF Download / Stream.
     * GET /admin/certificates/{id}/pdf
     * Role: staff+
     */
    public function downloadPdf(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_STAFF);

        $cert = $this->certService->getRawCertificateById($id);
        if (!$cert) {
            return Response::html(View::render('errors/404', ['title' => 'Certificate Not Found', 'message' => 'The requested certificate does not exist.']), 404);
        }

        if (($cert['status'] ?? '') === 'revoked') {
            return Response::html(View::render('errors/403', ['title' => 'Certificate Revoked', 'message' => 'This certificate has been officially revoked and cannot be exported as an active credential.']), 403);
        }

        if (($cert['event_status'] ?? '') !== 'completed') {
            return Response::html(View::render('errors/403', [
                'title'   => 'Certificate Gated',
                'message' => 'Certificates can strictly only be downloaded once the event is marked as completed.',
            ]), 403);
        }

        if (($cert['attendance_status'] ?? '') !== 'attended') {
            return Response::html(View::render('errors/403', [
                'title'   => 'Ineligible Attendee',
                'message' => 'Certificates are only available for attendees who checked in and attended the event.',
            ]), 403);
        }

        // Render official PDF binary
        $pdfData = $this->certService->renderPdf($cert);

        // Audit download
        $this->auditService->log(
            'certificate.download',
            'certificates',
            $id,
            [
                'certificate_number' => $cert['certificate_number'],
                'format'             => 'pdf',
            ],
            $userId,
            $userRole
        );

        $safeNum = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $cert['certificate_number']);
        $filename = "LC-Certificate-{$safeNum}.pdf";

        return new Response($pdfData, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Length'      => (string) strlen($pdfData),
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Direct High-Resolution JPG Download.
     * GET /admin/certificates/{id}/jpg
     * Role: staff+
     */
    public function downloadJpg(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_STAFF);

        $cert = $this->certService->getRawCertificateById($id);
        if (!$cert) {
            return Response::html(View::render('errors/404', ['title' => 'Certificate Not Found', 'message' => 'The requested certificate does not exist.']), 404);
        }

        if (($cert['status'] ?? '') === 'revoked') {
            return Response::html(View::render('errors/403', ['title' => 'Certificate Revoked', 'message' => 'This certificate has been officially revoked and cannot be exported as an active credential.']), 403);
        }

        if (($cert['event_status'] ?? '') !== 'completed') {
            return Response::html(View::render('errors/403', [
                'title'   => 'Certificate Gated',
                'message' => 'Certificates can strictly only be downloaded once the event is marked as completed.',
            ]), 403);
        }

        if (($cert['attendance_status'] ?? '') !== 'attended') {
            return Response::html(View::render('errors/403', [
                'title'   => 'Ineligible Attendee',
                'message' => 'Certificates are only available for attendees who checked in and attended the event.',
            ]), 403);
        }

        // Render high-resolution JPG binary
        $jpegData = $this->certService->renderJpg($cert);

        // Audit download
        $this->auditService->log(
            'certificate.download',
            'certificates',
            $id,
            [
                'certificate_number' => $cert['certificate_number'],
                'format'             => 'jpg',
            ],
            $userId,
            $userRole
        );

        $safeNum = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $cert['certificate_number']);
        $filename = "LC-Certificate-{$safeNum}.jpg";

        return new Response($jpegData, 200, [
            'Content-Type'        => 'image/jpeg',
            'Content-Length'      => (string) strlen($jpegData),
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Clerical Name Correction & Supersession Re-issuance.
     * POST /admin/certificates/{id}/reissue
     * Role: coordinator+
     */
    public function reissue(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_COORDINATOR);

        $newName = trim((string) $request->input('recipient_name', ''));
        $reason = trim((string) $request->input('reason', ''));

        try {
            $result = $this->certService->reissueNameCorrection($id, $newName, $reason, $userId, $userRole);
            Session::flash('success', "Certificate superseded and replaced: new Certificate #{$result['new_certificate_number']} issued for {$result['new_recipient_name']}.");
            return Response::redirect(url("/admin/certificates/{$result['new_certificate_id']}"));
        } catch (CertificateException|ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'An error occurred during certificate name correction.');
        }

        return Response::redirect(url("/admin/certificates/{$id}"));
    }

    /**
     * Permanent Revocation.
     * POST /admin/certificates/{id}/revoke
     * Role: coordinator+
     */
    public function revoke(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_COORDINATOR);

        $reason = trim((string) $request->input('reason', ''));

        try {
            $this->certService->revokeCertificate($id, $reason, $userId, $userRole);
            Session::flash('success', 'Certificate has been permanently revoked.');
        } catch (CertificateException|ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'An error occurred during certificate revocation.');
        }

        return Response::redirect(url("/admin/certificates/{$id}"));
    }

    /**
     * Certificate Template Designer.
     * GET /admin/events/{id}/certificates/designer
     * Role: coordinator+
     */
    public function designer(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $event = $this->eventRepo->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        $template = $this->templateRepo->findByEventId($eventId);
        $defaultConfig = $this->templateRepo->getDefaultLayoutConfig();

        return Response::html(
            View::render('admin/certificates/designer', [
                'title'         => 'Certificate Template Designer — ' . ($event['title'] ?? ''),
                'event'         => $event,
                'template'      => $template,
                'defaultConfig' => $defaultConfig,
            ], 'layouts/admin')
        );
    }

    /**
     * Save Certificate Template Configuration & Assets.
     * POST /admin/events/{id}/certificates/designer
     * Role: coordinator+
     */
    public function saveDesigner(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $event = $this->eventRepo->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            return Response::redirect(url('/admin/certificates'));
        }

        $uploadBase = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/public/uploads/certificates';
        if (!is_dir($uploadBase)) {
            @mkdir($uploadBase, 0755, true);
        }

        $templateData = [
            'signature1_name'        => trim((string) $request->input('signature1_name', '')),
            'signature1_designation' => trim((string) $request->input('signature1_designation', '')),
            'signature2_name'        => trim((string) $request->input('signature2_name', '')),
            'signature2_designation' => trim((string) $request->input('signature2_designation', '')),
        ];

        // Handle File Uploads
        $uploadFields = [
            'background_image' => 'background_image_path',
            'seal_image'       => 'seal_image_path',
            'signature1_image' => 'signature1_image_path',
            'signature2_image' => 'signature2_image_path',
        ];

        $maxBytes = 5 * 1024 * 1024; // 5 MB per asset limit

        foreach ($uploadFields as $inputKey => $dbKey) {
            if (isset($_FILES[$inputKey]) && $_FILES[$inputKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$inputKey];

                // Enforce maximum 5MB per uploaded certificate asset BEFORE moving/processing
                if (($file['size'] ?? 0) > $maxBytes) {
                    $assetLabel = str_replace('_', ' ', $inputKey);
                    Session::flash('error', "The uploaded {$assetLabel} exceeds the maximum allowed file size of 5 MB.");
                    return Response::redirect(url("/admin/events/{$eventId}/certificates/designer"));
                }

                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if (isset($allowedMimes[$mime])) {
                    $ext = $allowedMimes[$mime];
                    $filename = "cert_{$eventId}_{$inputKey}_" . bin2hex(random_bytes(6)) . ".{$ext}";
                    $destPath = $uploadBase . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $templateData[$dbKey] = '/uploads/certificates/' . $filename;
                    }
                }
            }
        }

        // Layout Config
        $layoutConfig = $request->input('layout_config');
        if (!empty($layoutConfig) && is_array($layoutConfig)) {
            $templateData['layout_config'] = $layoutConfig;
        }

        $this->templateRepo->saveOrUpdate($eventId, $templateData);

        Session::flash('success', 'Certificate template and layout saved successfully.');
        return Response::redirect(url("/admin/events/{$eventId}/certificates/designer"));
    }
}
