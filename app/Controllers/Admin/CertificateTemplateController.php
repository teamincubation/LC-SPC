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
use App\Services\CertificateRenderer;
use App\Services\FontManagementService;
use App\Services\RoleService;
use App\Services\VariableRegistry;
use Throwable;

/**
 * Certificate Templates Controller
 * Handles template creation, listing, duplication, and visual designer workspace.
 */
class CertificateTemplateController
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
     * List all templates.
     * GET /admin/certificate-templates
     */
    public function index(Request $request): Response
    {
        $templates = $this->templateRepo->getAll();

        return Response::html(View::render('admin/certificate_templates/index', [
            'title'     => 'Certificate Templates',
            'templates' => $templates,
            'activeNav' => 'certificate-templates',
        ], 'layouts/admin'));
    }

    /**
     * Create Template Form.
     * GET /admin/certificate-templates/create
     */
    public function create(Request $request): Response
    {
        $allVars = VariableRegistry::getAll();

        return Response::html(View::render('admin/certificate_templates/create', [
            'title'     => 'Create Certificate Template',
            'allVars'   => $allVars,
            'activeNav' => 'certificate-templates',
        ], 'layouts/admin'));
    }

    /**
     * Store New Template.
     * POST /admin/certificate-templates
     */
    public function store(Request $request): Response
    {
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $certType = trim((string) $request->input('certificate_type', 'participation'));
        $status = trim((string) $request->input('status', 'active'));

        if ($name === '' || strlen($name) < 3) {
            Session::flash('error', 'Template name must be at least 3 characters.');
            return Response::redirect(url('/admin/certificate-templates/create'));
        }

        // Variables: ensure name and phone are strictly required
        $rawVars = (array) $request->input('required_variables', ['name', 'phone', 'certificate_number']);
        if (!in_array('name', $rawVars, true)) {
            $rawVars[] = 'name';
        }
        if (!in_array('phone', $rawVars, true)) {
            $rawVars[] = 'phone';
        }

        try {
            VariableRegistry::validateTemplateRequirements($rawVars);

            $data = [
                'name'               => $name,
                'description'        => $description,
                'certificate_type'   => $certType,
                'status'             => $status,
                'required_variables' => $rawVars,
                'layout_config'      => ['elements' => CertificateRenderer::getDefaultElements()],
                'created_by'         => $currentUserId,
            ];

            $id = $this->templateRepo->create($data);

            $this->auditService->log(
                'certificate_template.create',
                'v3_certificate_templates',
                $id,
                [
                    'name'       => $name,
                    'actor_role' => $currentUserRole,
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Template [{$name}] created successfully. You can now customize its layout in the Visual Designer.");
            return Response::redirect(url("/admin/certificate-templates/{$id}/designer"));
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect(url('/admin/certificate-templates/create'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to create template: ' . $e->getMessage());
            return Response::redirect(url('/admin/certificate-templates/create'));
        }
    }

    /**
     * Edit Template metadata.
     * GET /admin/certificate-templates/{id}/edit
     */
    public function edit(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $template = $this->templateRepo->findById($id);
        if (!$template) {
            Session::flash('error', 'Certificate template not found.');
            return Response::redirect(url('/admin/certificate-templates'));
        }

        $allVars = VariableRegistry::getAll();

        return Response::html(View::render('admin/certificate_templates/edit', [
            'title'     => "Edit Template — {$template['name']}",
            'template'  => $template,
            'allVars'   => $allVars,
            'activeNav' => 'certificate-templates',
        ], 'layouts/admin'));
    }

    /**
     * Update Template metadata.
     * POST /admin/certificate-templates/{id}
     */
    public function update(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $template = $this->templateRepo->findById($id);
        if (!$template) {
            Session::flash('error', 'Certificate template not found.');
            return Response::redirect(url('/admin/certificate-templates'));
        }

        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $certType = trim((string) $request->input('certificate_type', 'participation'));
        $status = trim((string) $request->input('status', 'active'));

        $rawVars = (array) $request->input('required_variables', ['name', 'phone', 'certificate_number']);
        if (!in_array('name', $rawVars, true)) {
            $rawVars[] = 'name';
        }
        if (!in_array('phone', $rawVars, true)) {
            $rawVars[] = 'phone';
        }

        try {
            VariableRegistry::validateTemplateRequirements($rawVars);

            $this->templateRepo->update($id, [
                'name'               => $name,
                'description'        => $description,
                'certificate_type'   => $certType,
                'status'             => $status,
                'required_variables' => $rawVars,
            ]);

            $this->auditService->log(
                'certificate_template.update',
                'v3_certificate_templates',
                $id,
                [
                    'name'       => $name,
                    'actor_role' => $currentUserRole,
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Template [{$name}] updated successfully.");
            return Response::redirect(url('/admin/certificate-templates'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update template: ' . $e->getMessage());
            return Response::redirect(url("/admin/certificate-templates/{$id}/edit"));
        }
    }

    /**
     * Visual Certificate Designer workspace.
     * GET /admin/certificate-templates/{id}/designer
     */
    public function designer(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $template = $this->templateRepo->findById($id);
        if (!$template) {
            Session::flash('error', 'Certificate template not found.');
            return Response::redirect(url('/admin/certificate-templates'));
        }

        $allVars = VariableRegistry::getAll();
        $fonts = FontManagementService::getAllFonts();

        return Response::html(View::render('admin/certificate_templates/designer', [
            'title'     => "Certificate Designer — {$template['name']}",
            'template'  => $template,
            'allVars'   => $allVars,
            'fonts'     => $fonts,
            'activeNav' => 'certificate-templates',
        ], 'layouts/admin'));
    }

    /**
     * Save Designer Layout and Assets.
     * POST /admin/certificate-templates/{id}/designer
     */
    public function saveDesigner(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $template = $this->templateRepo->findById($id);
        if (!$template) {
            return Response::json(['error' => 'Template not found'], 404);
        }

        $layoutJson = $request->input('layout_config');
        $layout = is_string($layoutJson) ? json_decode($layoutJson, true) : (array) $layoutJson;

        if (empty($layout['elements'])) {
            return Response::json(['error' => 'Layout elements cannot be empty.'], 422);
        }

        // Verify mandatory variables are present in the designer elements
        $foundName = false;
        $foundPhone = false;
        foreach ($layout['elements'] as $el) {
            $text = $el['text'] ?? '';
            if (str_contains($text, '{{name}}')) {
                $foundName = true;
            }
            if (str_contains($text, '{{phone}}')) {
                $foundPhone = true;
            }
        }

        if (!$foundName || !$foundPhone) {
            $missing = [];
            if (!$foundName) {
                $missing[] = '{{name}}';
            }
            if (!$foundPhone) {
                $missing[] = '{{phone}}';
            }
            return Response::json([
                'error' => 'Designer validation failed: Template layout MUST contain the mandatory placeholders: ' . implode(' and ', $missing) . '.'
            ], 422);
        }

        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $uploadDir = $appRoot . '/storage/templates/' . $id;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $updateData = [
            'layout_config' => $layout,
        ];

        // Handle Background image upload
        if (!empty($_FILES['background_image']['tmp_name'])) {
            $bg = $_FILES['background_image'];
            $ext = strtolower(pathinfo($bg['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $bgFilename = "bg_{$id}_" . bin2hex(random_bytes(3)) . ".{$ext}";
                move_uploaded_file($bg['tmp_name'], "{$uploadDir}/{$bgFilename}");
                $updateData['background_image_path'] = "storage/templates/{$id}/{$bgFilename}";
            }
        }

        // Handle Seal upload
        if (!empty($_FILES['seal_image']['tmp_name'])) {
            $seal = $_FILES['seal_image'];
            $ext = strtolower(pathinfo($seal['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'webp', 'jpg', 'jpeg'], true)) {
                $sealFilename = "seal_{$id}_" . bin2hex(random_bytes(3)) . ".{$ext}";
                move_uploaded_file($seal['tmp_name'], "{$uploadDir}/{$sealFilename}");
                $updateData['seal_image_path'] = "storage/templates/{$id}/{$sealFilename}";
            }
        }

        // Handle Signature 1 upload & metadata
        if (!empty($_FILES['signature1_image']['tmp_name'])) {
            $sig1 = $_FILES['signature1_image'];
            $ext = strtolower(pathinfo($sig1['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'webp', 'jpg', 'jpeg'], true)) {
                $sig1Filename = "sig1_{$id}_" . bin2hex(random_bytes(3)) . ".{$ext}";
                move_uploaded_file($sig1['tmp_name'], "{$uploadDir}/{$sig1Filename}");
                $updateData['signature1_image_path'] = "storage/templates/{$id}/{$sig1Filename}";
            }
        }
        if ($request->input('signature1_name') !== null) {
            $updateData['signature1_name'] = trim((string) $request->input('signature1_name'));
        }
        if ($request->input('signature1_designation') !== null) {
            $updateData['signature1_designation'] = trim((string) $request->input('signature1_designation'));
        }

        // Handle Signature 2 upload & metadata
        if (!empty($_FILES['signature2_image']['tmp_name'])) {
            $sig2 = $_FILES['signature2_image'];
            $ext = strtolower(pathinfo($sig2['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'webp', 'jpg', 'jpeg'], true)) {
                $sig2Filename = "sig2_{$id}_" . bin2hex(random_bytes(3)) . ".{$ext}";
                move_uploaded_file($sig2['tmp_name'], "{$uploadDir}/{$sig2Filename}");
                $updateData['signature2_image_path'] = "storage/templates/{$id}/{$sig2Filename}";
            }
        }
        if ($request->input('signature2_name') !== null) {
            $updateData['signature2_name'] = trim((string) $request->input('signature2_name'));
        }
        if ($request->input('signature2_designation') !== null) {
            $updateData['signature2_designation'] = trim((string) $request->input('signature2_designation'));
        }

        $this->templateRepo->update($id, $updateData);

        $this->auditService->log(
            'certificate_template.save_layout',
            'v3_certificate_templates',
            $id,
            [
                'name'       => $template['name'],
                'actor_role' => $currentUserRole,
            ],
            $currentUserId,
            'admin'
        );

        return Response::json(['status' => 'success', 'message' => 'Template layout saved successfully.']);
    }

    /**
     * Live Preview stream (JPEG image rendered via the canonical engine with dummy data).
     * GET /admin/certificate-templates/{id}/preview
     */
    public function preview(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $template = $this->templateRepo->findById($id);
        if (!$template) {
            return Response::json(['error' => 'Template not found'], 404);
        }

        $dummyData = VariableRegistry::getPreviewDummyData();
        $dummyData['template_name'] = $template['name'];

        $jpegBinary = CertificateRenderer::renderJpg($template, $dummyData, false);

        return new Response($jpegBinary, 200, [
            'Content-Type'        => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="template_preview.jpg"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Duplicate template.
     * POST /admin/certificate-templates/{id}/duplicate
     */
    public function duplicate(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $newId = $this->templateRepo->duplicate($id, $currentUserId);
        if ($newId) {
            $this->auditService->log(
                'certificate_template.duplicate',
                'v3_certificate_templates',
                $newId,
                [
                    'source_id'  => $id,
                    'actor_role' => $currentUserRole,
                ],
                $currentUserId,
                'admin'
            );
            Session::flash('success', 'Template duplicated successfully.');
        } else {
            Session::flash('error', 'Failed to duplicate template.');
        }

        return Response::redirect(url('/admin/certificate-templates'));
    }

    /**
     * Delete template.
     * POST /admin/certificate-templates/{id}/delete
     */
    public function destroy(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $this->templateRepo->delete($id);

        $this->auditService->log(
            'certificate_template.delete',
            'v3_certificate_templates',
            $id,
            ['actor_role' => $currentUserRole],
            $currentUserId,
            'admin'
        );

        Session::flash('success', 'Template deleted successfully.');
        return Response::redirect(url('/admin/certificate-templates'));
    }
}
