<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EventFormRepository;
use App\Repositories\EventRepository;
use App\Services\AuditService;
use App\Services\EventFormService;
use App\Services\RoleService;
use Throwable;

/**
 * Event Registration Form Controller
 * Manages form configuration, banner uploads, locked global fields, and custom field builders.
 */
class EventFormController
{
    private EventFormRepository $formRepo;
    private EventFormService $formService;
    private EventRepository $eventRepo;
    private AuditService $auditService;

    public function __construct(
        ?EventFormRepository $formRepo = null,
        ?EventFormService $formService = null,
        ?EventRepository $eventRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->formRepo = $formRepo ?? new EventFormRepository();
        $this->formService = $formService ?? new EventFormService();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * List all event registration forms.
     * GET /admin/forms
     */
    public function index(Request $request): Response
    {
        $forms = $this->formRepo->getAllForms();

        return Response::html(View::render('admin/forms/index', [
            'title'       => 'Event Registration Forms',
            'forms'       => $forms,
            'activeNav'   => 'forms',
            'formService' => $this->formService,
        ], 'layouts/admin'));
    }

    /**
     * Form Settings & Builder View.
     * GET /admin/forms/{id}
     */
    public function show(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $form = $this->formRepo->findById($id);

        if (!$form) {
            Session::flash('error', 'Event registration form not found.');
            return Response::redirect(url('/admin/forms'));
        }

        $fields = $this->formRepo->getFields($id);
        $regCount = $this->formRepo->countRegistrations($id);

        $regUrl = $this->formService->getPublicRegistrationUrl($form['slug']);
        $regQrSvg = $this->formService->getPublicRegistrationQrSvg($form['slug']);
        $checkInUrl = $this->formService->getPublicCheckInUrl($form['slug']);
        $checkInQrSvg = $this->formService->getPublicCheckInQrSvg($form['slug']);

        return Response::html(View::render('admin/forms/builder', [
            'title'        => "Event Reg. Form — {$form['event_title']}",
            'form'         => $form,
            'fields'       => $fields,
            'regCount'     => $regCount,
            'regUrl'       => $regUrl,
            'regQrSvg'     => $regQrSvg,
            'checkInUrl'   => $checkInUrl,
            'checkInQrSvg' => $checkInQrSvg,
            'activeNav'    => 'forms',
        ], 'layouts/admin'));
    }

    /**
     * Update Form Settings.
     * POST /admin/forms/{id}
     */
    public function update(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $form = $this->formRepo->findById($id);

        if (!$form) {
            Session::flash('error', 'Event registration form not found.');
            return Response::redirect(url('/admin/forms'));
        }

        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_COORDINATOR);

        $formTitle = trim((string) $request->input('form_title', $form['form_title']));
        $slug = trim((string) $request->input('slug', $form['slug']));
        $photoUpload = (int) $request->input('photo_upload_enabled', 0);
        $locationAccess = (int) $request->input('location_access_required', 0);
        $whatsappUrl = trim((string) $request->input('whatsapp_group_url', ''));
        $whatsappAuto = (int) $request->input('whatsapp_auto_redirect', 0);
        $whatsappCd = max(1, (int) $request->input('whatsapp_countdown_seconds', 5));
        $successMsg = trim((string) $request->input('custom_success_message', ''));
        $status = trim((string) $request->input('status', $form['status']));

        $updateData = [
            'form_title'                 => $formTitle,
            'slug'                       => $slug,
            'photo_upload_enabled'       => $photoUpload,
            'location_access_required'   => $locationAccess,
            'whatsapp_group_url'         => $whatsappUrl ?: null,
            'whatsapp_auto_redirect'     => $whatsappAuto,
            'whatsapp_countdown_seconds' => $whatsappCd,
            'custom_success_message'     => $successMsg ?: null,
            'status'                     => in_array($status, ['draft', 'published', 'closed'], true) ? $status : 'published',
        ];

        // Process banner upload if provided
        $files = $request->files();
        if (!empty($files['banner']) && is_array($files['banner']) && !empty($files['banner']['tmp_name'])) {
            $uploaded = $files['banner'];
            if ($uploaded['error'] === UPLOAD_ERR_OK) {
                $maxBytes = 5 * 1024 * 1024; // 5 MB
                if ($uploaded['size'] > $maxBytes) {
                    Session::flash('error', 'Banner file exceeds maximum size limit of 5 MB.');
                    return Response::redirect(url("/admin/forms/{$id}"));
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $uploaded['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($allowedMimes[$mime])) {
                    Session::flash('error', 'Invalid banner image type. Supported formats: JPEG, PNG, WebP.');
                    return Response::redirect(url("/admin/forms/{$id}"));
                }

                $ext = $allowedMimes[$mime];
                $storageDir = defined('APP_ROOT') ? APP_ROOT . '/storage/uploads/banners' : dirname(__DIR__, 3) . '/storage/uploads/banners';
                if (!is_dir($storageDir)) {
                    @mkdir($storageDir, 0755, true);
                }

                $filename = 'banner_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $destPath = $storageDir . '/' . $filename;

                if (move_uploaded_file($uploaded['tmp_name'], $destPath)) {
                    $updateData['banner_path'] = 'banners/' . $filename;
                }
            }
        }

        try {
            $this->formService->updateForm($id, $updateData);

            $this->auditService->log(
                'form.update',
                'event_forms',
                $id,
                ['updated_fields' => array_keys($updateData)],
                $currentUserId,
                $currentUserRole
            );

            Session::flash('success', 'Form settings saved successfully.');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update form settings.');
        }

        return Response::redirect(url("/admin/forms/{$id}"));
    }

    /**
     * Add Custom Field to Form.
     * POST /admin/forms/{id}/fields
     */
    public function addField(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $form = $this->formRepo->findById($id);

        if (!$form) {
            Session::flash('error', 'Form not found.');
            return Response::redirect(url('/admin/forms'));
        }

        $label = trim((string) $request->input('field_label', ''));
        $type = trim((string) $request->input('field_type', 'text'));
        $required = (int) $request->input('is_required', 0);
        $placeholder = trim((string) $request->input('placeholder', ''));
        $helpText = trim((string) $request->input('help_text', ''));
        $optionsRaw = trim((string) $request->input('options_json', ''));

        $options = null;
        if (!empty($optionsRaw)) {
            $lines = array_filter(array_map('trim', explode("\n", $optionsRaw)));
            if (!empty($lines)) {
                $options = array_values($lines);
            }
        }

        try {
            $this->formService->addCustomField($id, [
                'field_label'  => $label,
                'field_type'   => $type,
                'is_required'  => $required,
                'placeholder'  => $placeholder ?: null,
                'help_text'    => $helpText ?: null,
                'options_json' => $options,
            ]);

            Session::flash('success', "Custom field [{$label}] added.");
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to add custom field.');
        }

        return Response::redirect(url("/admin/forms/{$id}"));
    }

    /**
     * Delete Custom Field.
     * POST /admin/forms/{id}/fields/{field_id}/delete
     */
    public function deleteField(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $fieldId = (int) ($vars['field_id'] ?? 0);

        try {
            $this->formService->deleteCustomField($fieldId);
            Session::flash('success', 'Custom field removed.');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to remove field.');
        }

        return Response::redirect(url("/admin/forms/{$id}"));
    }
}
