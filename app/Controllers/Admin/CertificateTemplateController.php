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

        // Variables: ensure name is strictly required
        $rawVars = (array) $request->input('required_variables', ['name', 'certificate_number']);
        if (!in_array('name', $rawVars, true)) {
            $rawVars[] = 'name';
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
            'title'     => "Edit Template - {$template['name']}",
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

        $rawVars = (array) $request->input('required_variables', ['name', 'certificate_number']);
        if (!in_array('name', $rawVars, true)) {
            $rawVars[] = 'name';
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
            'title'     => "Certificate Designer - {$template['name']}",
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

        // Verify mandatory recipient name placeholder is present in the designer elements
        $foundName = false;
        foreach ($layout['elements'] as $el) {
            $text = $el['text'] ?? '';
            if (str_contains($text, '{{name}}')) {
                $foundName = true;
                break;
            }
        }

        if (!$foundName) {
            return Response::json([
                'error' => 'Designer validation failed: Template layout MUST contain the mandatory recipient placeholder: {{name}}.'
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

        // Handle Background image upload if provided in multipart
        if (!empty($_FILES['background_image']['tmp_name']) && $_FILES['background_image']['error'] === UPLOAD_ERR_OK) {
            $bg = $_FILES['background_image'];
            $ext = strtolower(pathinfo($bg['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $bgFilename = "bg_{$id}_" . bin2hex(random_bytes(4)) . ".{$ext}";
                $moved = is_uploaded_file($bg['tmp_name']) ? move_uploaded_file($bg['tmp_name'], "{$uploadDir}/{$bgFilename}") : @copy($bg['tmp_name'], "{$uploadDir}/{$bgFilename}");
                if ($moved) {
                    $updateData['background_image_path'] = "storage/templates/{$id}/{$bgFilename}";
                }
            }
        }

        // Handle Seal upload if provided in multipart
        if (!empty($_FILES['seal_image']['tmp_name']) && $_FILES['seal_image']['error'] === UPLOAD_ERR_OK) {
            $seal = $_FILES['seal_image'];
            $ext = strtolower(pathinfo($seal['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'webp', 'jpg', 'jpeg'], true)) {
                $sealFilename = "seal_{$id}_" . bin2hex(random_bytes(4)) . ".{$ext}";
                $moved = is_uploaded_file($seal['tmp_name']) ? move_uploaded_file($seal['tmp_name'], "{$uploadDir}/{$sealFilename}") : @copy($seal['tmp_name'], "{$uploadDir}/{$sealFilename}");
                if ($moved) {
                    $updateData['seal_image_path'] = "storage/templates/{$id}/{$sealFilename}";
                }
            }
        }

        // Handle Signature 1 upload & metadata
        if (!empty($_FILES['signature1_image']['tmp_name']) && $_FILES['signature1_image']['error'] === UPLOAD_ERR_OK) {
            $sig1 = $_FILES['signature1_image'];
            $ext = strtolower(pathinfo($sig1['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'webp', 'jpg', 'jpeg'], true)) {
                $sig1Filename = "sig1_{$id}_" . bin2hex(random_bytes(4)) . ".{$ext}";
                $moved = is_uploaded_file($sig1['tmp_name']) ? move_uploaded_file($sig1['tmp_name'], "{$uploadDir}/{$sig1Filename}") : @copy($sig1['tmp_name'], "{$uploadDir}/{$sig1Filename}");
                if ($moved) {
                    $updateData['signature1_image_path'] = "storage/templates/{$id}/{$sig1Filename}";
                }
            }
        }
        if ($request->input('signature1_name') !== null) {
            $updateData['signature1_name'] = trim((string) $request->input('signature1_name'));
        }
        if ($request->input('signature1_designation') !== null) {
            $updateData['signature1_designation'] = trim((string) $request->input('signature1_designation'));
        }

        // Handle Signature 2 upload & metadata
        if (!empty($_FILES['signature2_image']['tmp_name']) && $_FILES['signature2_image']['error'] === UPLOAD_ERR_OK) {
            $sig2 = $_FILES['signature2_image'];
            $ext = strtolower(pathinfo($sig2['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'webp', 'jpg', 'jpeg'], true)) {
                $sig2Filename = "sig2_{$id}_" . bin2hex(random_bytes(4)) . ".{$ext}";
                $moved = is_uploaded_file($sig2['tmp_name']) ? move_uploaded_file($sig2['tmp_name'], "{$uploadDir}/{$sig2Filename}") : @copy($sig2['tmp_name'], "{$uploadDir}/{$sig2Filename}");
                if ($moved) {
                    $updateData['signature2_image_path'] = "storage/templates/{$id}/{$sig2Filename}";
                }
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
     * Upload single asset asynchronously (background, seal, signature1, signature2, logo).
     * POST /admin/certificate-templates/{id}/assets
     */
    public function uploadAsset(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $template = $this->templateRepo->findById($id);
        if (!$template) {
            return Response::json(['error' => 'Certificate template not found.'], 404);
        }

        $assetType = trim((string) $request->input('asset_type', ''));
        $validTypes = ['background', 'seal', 'signature1', 'signature2', 'logo'];
        if (!in_array($assetType, $validTypes, true)) {
            return Response::json(['error' => 'Invalid asset type specified.'], 400);
        }

        // Find file in $_FILES
        $file = $_FILES['asset_file'] ?? ($_FILES['file'] ?? ($_FILES[$assetType . '_image'] ?? null));
        if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'No valid file was uploaded or an upload error occurred.'], 400);
        }

        // Check size limit: background 5MB, others 3MB
        $maxBytes = ($assetType === 'background') ? 5 * 1024 * 1024 : 3 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $maxMb = round($maxBytes / (1024 * 1024));
            return Response::json(['error' => "File size exceeds the allowable limit of {$maxMb}MB."], 422);
        }

        // Extension check (strictly NO SVG per Option A)
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ($assetType === 'background') ? ['jpg', 'jpeg', 'png', 'webp'] : ['png', 'webp', 'jpg', 'jpeg'];
        if (!in_array($ext, $allowedExts, true)) {
            return Response::json(['error' => 'Invalid file extension. Only PNG, WebP, and JPG/JPEG images are permitted. SVG is not supported for security.'], 422);
        }

        // MIME type verification with finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            return Response::json(['error' => "Invalid file MIME type ({$mime}). Only JPEG, PNG, and WebP images are permitted."], 422);
        }

        // Verify image integrity with getimagesize
        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
            return Response::json(['error' => 'Uploaded file is not a valid or readable image.'], 422);
        }

        $width = (int) $imgInfo[0];
        $height = (int) $imgInfo[1];

        // Ensure storage directory exists
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $uploadDir = $appRoot . '/storage/templates/' . $id;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $filename = "{$assetType}_{$id}_" . bin2hex(random_bytes(4)) . ".{$ext}";
        $targetPath = "{$uploadDir}/{$filename}";
        $relPath = "storage/templates/{$id}/{$filename}";

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $targetPath)
            : @copy($file['tmp_name'], $targetPath);

        if (!$moved) {
            return Response::json(['error' => 'Failed to persist uploaded asset to storage.'], 500);
        }

        // Update corresponding column if template-level asset
        $columnMap = [
            'background' => 'background_image_path',
            'seal'       => 'seal_image_path',
            'signature1' => 'signature1_image_path',
            'signature2' => 'signature2_image_path',
        ];

        if (isset($columnMap[$assetType])) {
            $col = $columnMap[$assetType];
            $oldPath = $template[$col] ?? null;
            if ($oldPath && file_exists("{$appRoot}/{$oldPath}") && $oldPath !== $relPath) {
                @unlink("{$appRoot}/{$oldPath}");
            }
            $this->templateRepo->update($id, [$col => $relPath]);
        }

        $this->auditService->log(
            'certificate_template.upload_asset',
            'v3_certificate_templates',
            $id,
            [
                'asset_type' => $assetType,
                'filename'   => $filename,
                'width'      => $width,
                'height'     => $height,
                'actor_role' => $currentUserRole,
            ],
            $currentUserId,
            'admin'
        );

        return Response::json([
            'status'     => 'success',
            'asset_type' => $assetType,
            'path'       => $relPath,
            'url'        => url('/' . $relPath),
            'width'      => $width,
            'height'     => $height,
            'message'    => ucfirst($assetType) . ' asset uploaded successfully.',
        ]);
    }

    /**
     * Delete/Remove an asset from a certificate template.
     * POST /admin/certificate-templates/{id}/assets/delete
     */
    public function deleteAsset(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $template = $this->templateRepo->findById($id);
        if (!$template) {
            return Response::json(['error' => 'Certificate template not found.'], 404);
        }

        $assetType = trim((string) $request->input('asset_type', ''));
        $columnMap = [
            'background' => 'background_image_path',
            'seal'       => 'seal_image_path',
            'signature1' => 'signature1_image_path',
            'signature2' => 'signature2_image_path',
        ];

        if (!isset($columnMap[$assetType])) {
            return Response::json(['error' => 'Invalid asset type for deletion.'], 400);
        }

        $col = $columnMap[$assetType];
        $oldPath = $template[$col] ?? null;
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

        if ($oldPath && file_exists("{$appRoot}/{$oldPath}")) {
            @unlink("{$appRoot}/{$oldPath}");
        }

        $this->templateRepo->update($id, [$col => null]);

        $this->auditService->log(
            'certificate_template.delete_asset',
            'v3_certificate_templates',
            $id,
            [
                'asset_type' => $assetType,
                'actor_role' => $currentUserRole,
            ],
            $currentUserId,
            'admin'
        );

        return Response::json([
            'status'     => 'success',
            'asset_type' => $assetType,
            'message'    => ucfirst($assetType) . ' asset removed successfully.',
        ]);
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
