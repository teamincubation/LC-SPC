<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\FormSettingsRepository;
use App\Services\AuditService;
use App\Services\RoleService;
use Throwable;

/**
 * Global Form Settings Controller
 * Controls global registration form rules, mandatory fields, and WhatsApp redirects.
 */
class FormSettingsController
{
    private FormSettingsRepository $formSettingsRepo;
    private AuditService $auditService;

    public function __construct(
        ?FormSettingsRepository $formSettingsRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->formSettingsRepo = $formSettingsRepo ?? new FormSettingsRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Show Form Settings.
     * GET /admin/form-settings
     */
    public function index(Request $request): Response
    {
        $settings = $this->formSettingsRepo->getSettings();

        return Response::html(View::render('admin/form_settings/index', [
            'title'     => 'Form Settings — Global Rules',
            'settings'  => $settings,
            'activeNav' => 'form-settings',
        ], 'layouts/admin'));
    }

    /**
     * Update Form Settings.
     * POST /admin/form-settings
     */
    public function update(Request $request): Response
    {
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $mandatoryLocation = (int) $request->input('mandatory_location_access', 0);
        $defaultCountryCode = trim((string) $request->input('default_country_code', '+91'));
        $defaultCountryIso = strtoupper(trim((string) $request->input('default_country_iso', 'IN')));
        $successMessage = trim((string) $request->input('registration_success_message', ''));
        $whatsappUrl = trim((string) $request->input('whatsapp_group_url', ''));
        $autoRedirect = (int) $request->input('whatsapp_auto_redirect', 0);
        $countdown = max(1, (int) $request->input('whatsapp_countdown_seconds', 5));

        // URL validation if provided
        if (!empty($whatsappUrl) && !filter_var($whatsappUrl, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Please enter a valid URL for the WhatsApp Group Link.');
            return Response::redirect(url('/admin/form-settings'));
        }

        try {
            $this->formSettingsRepo->updateSettings([
                'mandatory_location_access'    => $mandatoryLocation,
                'default_country_code'         => $defaultCountryCode,
                'default_country_iso'          => $defaultCountryIso,
                'registration_success_message' => $successMessage,
                'whatsapp_group_url'           => $whatsappUrl,
                'whatsapp_auto_redirect'       => $autoRedirect,
                'whatsapp_countdown_seconds'   => $countdown,
            ]);

            $this->auditService->log(
                'form_settings.update',
                'form_settings',
                1,
                [
                    'mandatory_location' => $mandatoryLocation,
                    'auto_redirect'      => $autoRedirect,
                    'countdown'          => $countdown,
                ],
                $currentUserId,
                $currentUserRole
            );

            Session::flash('success', 'Global form settings successfully updated.');
        } catch (Throwable $e) {
            Session::flash('error', 'An error occurred while saving form settings.');
        }

        return Response::redirect(url('/admin/form-settings'));
    }
}
