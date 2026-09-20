<?php

declare(strict_types=1);

/**
 * LC-SPC Application Route Registry - V3 Standalone Automated Certificate Platform
 * Dedicated workflow:
 * Certificate Settings -> Certificate Templates -> CSV Validation -> Chunked Generation -> Certificate Repository -> Public Verification
 */

use App\Controllers\Admin\AdminManagementController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CertificateGenerateController;
use App\Controllers\Admin\CertificateRepositoryController;
use App\Controllers\Admin\CertificateSettingsController;
use App\Controllers\Admin\CertificateTemplateController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\Public\PublicCertificatePortalController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\GuestMiddleware;
use App\Core\Middleware\PermissionMiddleware;
use App\Core\Middleware\RoleMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\RoleService;

/** @var Router $router */

// -----------------------------------------------------------------------------
// Public Foundation & Certificate Portal Routes
// -----------------------------------------------------------------------------
// Root URL redirects to the public certificate portal
$router->get('/', function (Request $request): Response {
    return Response::redirect(url('/certificates'));
});

// System Health Endpoint
$router->get('/health', [HealthController::class, 'index']);

// Public Certificate Search & Verification
$router->get('/certificates', [PublicCertificatePortalController::class, 'index']);
$router->get('/certificates/verify/{token}', [PublicCertificatePortalController::class, 'verify']);
$router->get('/certificates/{token}/pdf', [PublicCertificatePortalController::class, 'downloadPdf']);
$router->get('/certificates/{token}/image', [PublicCertificatePortalController::class, 'downloadImage']);

// Backward compatibility redirect aliases for legacy QR codes
$router->get('/verify/{token}', function (Request $request, array $vars): Response {
    return Response::redirect(url('/certificates/verify/' . ($vars['token'] ?? '')));
});
$router->get('/certificate/verify/{token}', function (Request $request, array $vars): Response {
    return Response::redirect(url('/certificates/verify/' . ($vars['token'] ?? '')));
});

// -----------------------------------------------------------------------------
// Authentication Routes
// -----------------------------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class, CsrfMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

// -----------------------------------------------------------------------------
// Protected Administrative Foundation (/admin/*)
// -----------------------------------------------------------------------------
$router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]], function (Router $adminRouter): void {
    // Admin Dashboard Overview
    $adminRouter->get('/', [DashboardController::class, 'index'], [new PermissionMiddleware('dashboard.view')]);
    $adminRouter->get('/dashboard', [DashboardController::class, 'index'], [new PermissionMiddleware('dashboard.view')]);

    // -------------------------------------------------------------------------
    // Module 1: Certificate Settings & Fonts
    // -------------------------------------------------------------------------
    $adminRouter->get('/certificate-settings', [CertificateSettingsController::class, 'index'], [new PermissionMiddleware(['certificates.manage', 'settings.view'])]);
    $adminRouter->post('/certificate-settings', [CertificateSettingsController::class, 'update'], [new PermissionMiddleware(['certificates.manage', 'settings.manage']), CsrfMiddleware::class]);
    $adminRouter->post('/certificate-settings/fonts', [CertificateSettingsController::class, 'uploadFont'], [new PermissionMiddleware(['certificates.manage', 'settings.manage']), CsrfMiddleware::class]);
    $adminRouter->post('/certificate-settings/fonts/{id}/delete', [CertificateSettingsController::class, 'deleteFont'], [new PermissionMiddleware(['certificates.manage', 'settings.manage']), CsrfMiddleware::class]);

    // -------------------------------------------------------------------------
    // Module 2: Certificate Templates & Visual Designer
    // -------------------------------------------------------------------------
    $adminRouter->get('/certificate-templates', [CertificateTemplateController::class, 'index'], [new PermissionMiddleware(['certificates.view', 'certificates.manage'])]);
    $adminRouter->get('/certificate-templates/create', [CertificateTemplateController::class, 'create'], [new PermissionMiddleware(['certificates.create', 'certificates.manage'])]);
    $adminRouter->post('/certificate-templates', [CertificateTemplateController::class, 'store'], [new PermissionMiddleware(['certificates.create', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->get('/certificate-templates/{id}/edit', [CertificateTemplateController::class, 'edit'], [new PermissionMiddleware(['certificates.edit', 'certificates.manage'])]);
    $adminRouter->post('/certificate-templates/{id}', [CertificateTemplateController::class, 'update'], [new PermissionMiddleware(['certificates.edit', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->get('/certificate-templates/{id}/designer', [CertificateTemplateController::class, 'designer'], [new PermissionMiddleware(['certificates.edit', 'certificates.manage'])]);
    $adminRouter->post('/certificate-templates/{id}/designer', [CertificateTemplateController::class, 'saveDesigner'], [new PermissionMiddleware(['certificates.edit', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->post('/certificate-templates/{id}/assets', [CertificateTemplateController::class, 'uploadAsset'], [new PermissionMiddleware(['certificates.edit', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->post('/certificate-templates/{id}/assets/delete', [CertificateTemplateController::class, 'deleteAsset'], [new PermissionMiddleware(['certificates.edit', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->get('/certificate-templates/{id}/preview', [CertificateTemplateController::class, 'preview'], [new PermissionMiddleware(['certificates.view', 'certificates.manage'])]);
    $adminRouter->post('/certificate-templates/{id}/duplicate', [CertificateTemplateController::class, 'duplicate'], [new PermissionMiddleware(['certificates.create', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->post('/certificate-templates/{id}/delete', [CertificateTemplateController::class, 'destroy'], [new PermissionMiddleware(['certificates.delete', 'certificates.manage']), CsrfMiddleware::class]);

    // -------------------------------------------------------------------------
    // Module 3: Certificate Generation & Batch Processing
    // -------------------------------------------------------------------------
    $adminRouter->get('/certificates/generate', [CertificateGenerateController::class, 'index'], [new PermissionMiddleware(['certificates.create', 'certificates.manage'])]);
    $adminRouter->get('/certificates/sample-csv/{id}', [CertificateGenerateController::class, 'sampleCsv'], [new PermissionMiddleware(['certificates.create', 'certificates.manage'])]);
    $adminRouter->post('/certificates/validate-csv', [CertificateGenerateController::class, 'validateCsv'], [new PermissionMiddleware(['certificates.create', 'certificates.manage'])]);
    $adminRouter->post('/certificates/download-error-report', [CertificateGenerateController::class, 'downloadErrorReport'], [new PermissionMiddleware(['certificates.create', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->post('/certificates/start-batch', [CertificateGenerateController::class, 'startBatch'], [new PermissionMiddleware(['certificates.create', 'certificates.manage'])]);
    $adminRouter->post('/certificates/process-chunk', [CertificateGenerateController::class, 'processChunk'], [new PermissionMiddleware(['certificates.create', 'certificates.manage'])]);

    // -------------------------------------------------------------------------
    // Module 4: Certificate Repository
    // -------------------------------------------------------------------------
    $adminRouter->get('/certificates', [CertificateRepositoryController::class, 'index'], [new PermissionMiddleware(['certificates.view', 'certificates.manage'])]);
    $adminRouter->get('/certificates/{id}', [CertificateRepositoryController::class, 'show'], [new PermissionMiddleware(['certificates.view', 'certificates.manage'])]);
    $adminRouter->get('/certificates/{id}/pdf', [CertificateRepositoryController::class, 'downloadPdf'], [new PermissionMiddleware(['certificates.export', 'certificates.manage'])]);
    $adminRouter->get('/certificates/{id}/image', [CertificateRepositoryController::class, 'downloadImage'], [new PermissionMiddleware(['certificates.export', 'certificates.manage'])]);
    $adminRouter->post('/certificates/{id}/invalidate', [CertificateRepositoryController::class, 'invalidate'], [new PermissionMiddleware(['certificates.delete', 'certificates.manage']), CsrfMiddleware::class]);
    $adminRouter->post('/certificates/{id}/delete', [CertificateRepositoryController::class, 'destroy'], [new PermissionMiddleware(['certificates.delete', 'certificates.manage']), CsrfMiddleware::class]);

    // -------------------------------------------------------------------------
    // Module 5: Admin Management Routes (Super Administrator Only)
    // -------------------------------------------------------------------------
    $adminRouter->get('/admins', [AdminManagementController::class, 'index'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN)]);
    $adminRouter->get('/admins/create', [AdminManagementController::class, 'create'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN)]);
    $adminRouter->post('/admins', [AdminManagementController::class, 'store'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);
    $adminRouter->get('/admins/{id}/edit', [AdminManagementController::class, 'edit'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN)]);
    $adminRouter->post('/admins/{id}', [AdminManagementController::class, 'update'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);
    $adminRouter->get('/admins/{id}/password', [AdminManagementController::class, 'password'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN)]);
    $adminRouter->post('/admins/{id}/password', [AdminManagementController::class, 'updatePassword'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);
    $adminRouter->get('/admins/{id}/permissions', [AdminManagementController::class, 'permissions'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN)]);
    $adminRouter->post('/admins/{id}/permissions', [AdminManagementController::class, 'updatePermissions'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);
    $adminRouter->post('/admins/{id}/activate', [AdminManagementController::class, 'activate'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);
    $adminRouter->post('/admins/{id}/deactivate', [AdminManagementController::class, 'deactivate'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);

    // -------------------------------------------------------------------------
    // RBAC Capability Gate Routes
    // -------------------------------------------------------------------------
    $adminRouter->get('/viewer-area', function (Request $request): Response {
        return Response::json(['status' => 'authorized', 'role_required' => 'viewer', 'level' => 10]);
    }, [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    $adminRouter->get('/staff-area', function (Request $request): Response {
        return Response::json(['status' => 'authorized', 'role_required' => 'staff', 'level' => 20]);
    }, [new RoleMiddleware(RoleService::ROLE_STAFF)]);

    $adminRouter->get('/coordinator-area', function (Request $request): Response {
        return Response::json(['status' => 'authorized', 'role_required' => 'coordinator', 'level' => 30]);
    }, [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    $adminRouter->get('/super-admin-area', function (Request $request): Response {
        return Response::json(['status' => 'authorized', 'role_required' => 'super_admin', 'level' => 40]);
    }, [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN)]);
});
