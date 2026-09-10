<?php

declare(strict_types=1);

/**
 * LC-SPC Application Route Registry
 * All registered routes resolve consistently both locally (/) and under Hostinger (/LC/).
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CampaignController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\GuestMiddleware;
use App\Core\Middleware\RoleMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\RoleService;

/** @var Router $router */

// -----------------------------------------------------------------------------
// Public Foundation Routes
// -----------------------------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [HealthController::class, 'index']);

// -----------------------------------------------------------------------------
// Authentication Routes (Phase 1A)
// -----------------------------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class, CsrfMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

// -----------------------------------------------------------------------------
// Protected Administrative Foundation (/admin/*)
// -----------------------------------------------------------------------------
$router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]], function (Router $adminRouter): void {
    // Admin Dashboard Overview (Requires viewer role rank or above)
    $adminRouter->get('/', [DashboardController::class, 'index'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // -------------------------------------------------------------------------
    // Campaign Management Routes (Phase 1B)
    // -------------------------------------------------------------------------
    // List campaigns (viewer+)
    $adminRouter->get('/campaigns', [CampaignController::class, 'index'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Create campaign form (coordinator+)
    $adminRouter->get('/campaigns/create', [CampaignController::class, 'create'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    // Store new campaign (coordinator+, CSRF protected)
    $adminRouter->post('/campaigns', [CampaignController::class, 'store'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // View campaign details (viewer+)
    $adminRouter->get('/campaigns/{id}', [CampaignController::class, 'show'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Edit campaign form (coordinator+)
    $adminRouter->get('/campaigns/{id}/edit', [CampaignController::class, 'edit'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    // Update campaign (coordinator+, CSRF protected)
    $adminRouter->post('/campaigns/{id}', [CampaignController::class, 'update'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Status management (coordinator+, CSRF protected)
    $adminRouter->post('/campaigns/{id}/status', [CampaignController::class, 'updateStatus'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Soft-delete campaign (super_admin only, CSRF protected)
    $adminRouter->post('/campaigns/{id}/delete', [CampaignController::class, 'destroy'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);

    // Restore soft-deleted campaign (super_admin only, CSRF protected)
    $adminRouter->post('/campaigns/{id}/restore', [CampaignController::class, 'restore'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);

    // -------------------------------------------------------------------------
    // RBAC Capability Gate Routes (Phase 1A Foundation & Verification)
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
