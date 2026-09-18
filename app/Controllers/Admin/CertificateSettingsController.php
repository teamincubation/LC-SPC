<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditService;
use App\Services\CertificateIdGenerator;
use App\Services\FontManagementService;
use App\Services\RoleService;
use Throwable;

/**
 * Certificate Settings Controller
 * Manages global Certificate ID pattern, entropy, output format, fonts, and verification parameters.
 */
class CertificateSettingsController
{
    private AuditService $auditService;

    public function __construct(?AuditService $auditService = null)
    {
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Show Certificate Settings page.
     * GET /admin/certificate-settings
     */
    public function index(Request $request): Response
    {
        $settings = CertificateIdGenerator::loadSettings();
        $fonts = FontManagementService::getAllFonts();

        $sampleId = '';
        try {
            $sampleId = CertificateIdGenerator::generateCertificateId(null, $settings);
        } catch (Throwable) {
            $sampleId = 'LC26-X7Q9-M4KP';
        }

        $totalChars = (int) ($settings['cert_id_segment_count'] ?? 2) * (int) ($settings['cert_id_segment_length'] ?? 4);
        $uniqueSymbols = count(array_unique(str_split((string) ($settings['cert_id_charset'] ?? ''))));
        $entropyBits = CertificateIdGenerator::calculateEntropyBits($totalChars, $uniqueSymbols);

        return Response::html(View::render('admin/certificate_settings/index', [
            'title'       => 'Certificate Settings',
            'settings'    => $settings,
            'fonts'       => $fonts,
            'sampleId'    => $sampleId,
            'entropyBits' => $entropyBits,
            'activeNav'   => 'certificate-settings',
        ], 'layouts/admin'));
    }

    /**
     * Update global Certificate Settings.
     * POST /admin/certificate-settings
     */
    public function update(Request $request): Response
    {
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $data = [
            'cert_id_prefix'                    => strtoupper(trim((string) $request->input('cert_id_prefix', 'LC'))),
            'cert_id_include_year'              => $request->input('cert_id_include_year') ? '1' : '0',
            'cert_id_separator'                 => (string) $request->input('cert_id_separator', '-'),
            'cert_id_segment_count'             => (int) $request->input('cert_id_segment_count', 2),
            'cert_id_segment_length'            => (int) $request->input('cert_id_segment_length', 4),
            'cert_id_charset'                   => strtoupper(trim((string) $request->input('cert_id_charset', CertificateIdGenerator::DEFAULT_CHARSET))),
            'output_format'                     => (string) $request->input('output_format', 'pdf_image'),
            'canvas_width'                      => (string) $request->input('canvas_width', '2480'),
            'canvas_height'                     => (string) $request->input('canvas_height', '1754'),
            'dpi'                               => (string) $request->input('dpi', '300'),
            'default_font'                      => (string) $request->input('default_font', 'arial'),
            'public_search_phone_enabled'       => $request->input('public_search_phone_enabled') ? '1' : '0',
            'public_rate_limit_max_attempts'    => (string) $request->input('public_rate_limit_max_attempts', '10'),
            'public_rate_limit_lockout_seconds' => (string) $request->input('public_rate_limit_lockout_seconds', '900'),
        ];

        try {
            // Mandatory Entropy Check
            CertificateIdGenerator::validateConfiguration($data);

            foreach ($data as $key => $val) {
                Database::execute(
                    "INSERT INTO `certificate_settings` (`setting_key`, `setting_value`, `updated_at`)
                     VALUES (:k, :v, NOW())
                     ON DUPLICATE KEY UPDATE `setting_value` = :v2, `updated_at` = NOW()",
                    [':k' => $key, ':v' => $val, ':v2' => $val]
                );
            }

            $this->auditService->log(
                'certificate_settings.update',
                'certificate_settings',
                null,
                [
                    'updated_keys' => array_keys($data),
                    'actor_role'   => $currentUserRole,
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', 'Certificate settings updated successfully.');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update settings: ' . $e->getMessage());
        }

        return Response::redirect(url('/admin/certificate-settings'));
    }

    /**
     * Upload custom font.
     * POST /admin/certificate-settings/fonts
     */
    public function uploadFont(Request $request): Response
    {
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $fontName = (string) $request->input('font_name', '');
        $file = $_FILES['font_file'] ?? [];

        try {
            $uploaded = FontManagementService::uploadFont($file, $fontName, $currentUserId);
            Session::flash('success', "Font [{$uploaded['name']}] uploaded and activated successfully.");
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'Font upload failed: ' . $e->getMessage());
        }

        return Response::redirect(url('/admin/certificate-settings'));
    }

    /**
     * Delete custom font.
     * POST /admin/certificate-settings/fonts/{id}/delete
     */
    public function deleteFont(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        try {
            FontManagementService::deleteFont($id);
            Session::flash('success', 'Font deleted successfully.');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to delete font: ' . $e->getMessage());
        }

        return Response::redirect(url('/admin/certificate-settings'));
    }
}
