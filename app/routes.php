<?php

declare(strict_types=1);

/**
 * LC-SPC Application Route Registry
 * All registered routes resolve consistently both locally (/) and under Hostinger (/LC/).
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CampaignController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EventController;
use App\Controllers\Admin\ParticipantController;
use App\Controllers\Admin\RegistrationController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\Public\RegistrationPassController;
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
$router->get('/registration/pass/{code}', [RegistrationPassController::class, 'show']);

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
    // Event Management Routes (Phase 1C)
    // -------------------------------------------------------------------------
    // List events (viewer+)
    $adminRouter->get('/events', [EventController::class, 'index'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Create event form (coordinator+)
    $adminRouter->get('/events/create', [EventController::class, 'create'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    // Store new event (coordinator+, CSRF protected)
    $adminRouter->post('/events', [EventController::class, 'store'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // View event details (viewer+)
    $adminRouter->get('/events/{id}', [EventController::class, 'show'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Edit event form (coordinator+)
    $adminRouter->get('/events/{id}/edit', [EventController::class, 'edit'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    // Update event (coordinator+, CSRF protected)
    $adminRouter->post('/events/{id}', [EventController::class, 'update'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Status management (coordinator+, CSRF protected)
    $adminRouter->post('/events/{id}/status', [EventController::class, 'updateStatus'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Soft-delete event (super_admin only, CSRF protected)
    $adminRouter->post('/events/{id}/delete', [EventController::class, 'destroy'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);

    // Restore soft-deleted event (super_admin only, CSRF protected)
    $adminRouter->post('/events/{id}/restore', [EventController::class, 'restore'], [new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN), CsrfMiddleware::class]);

    // -------------------------------------------------------------------------
    // Participant Management Routes (Phase 1D)
    // -------------------------------------------------------------------------
    // List participants (viewer+)
    $adminRouter->get('/participants', [ParticipantController::class, 'index'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Create participant form (coordinator+)
    $adminRouter->get('/participants/create', [ParticipantController::class, 'create'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    // Store new participant (coordinator+, CSRF protected)
    $adminRouter->post('/participants', [ParticipantController::class, 'store'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // View participant profile details (viewer+)
    $adminRouter->get('/participants/{id}', [ParticipantController::class, 'show'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Edit participant form (coordinator+)
    $adminRouter->get('/participants/{id}/edit', [ParticipantController::class, 'edit'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    // Update participant (coordinator+, CSRF protected)
    $adminRouter->post('/participants/{id}', [ParticipantController::class, 'update'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Update participant lifecycle status (coordinator+, CSRF protected)
    $adminRouter->post('/participants/{id}/status', [ParticipantController::class, 'updateStatus'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // -------------------------------------------------------------------------
    // Event Registration Routes (Phase 1E)
    // -------------------------------------------------------------------------
    // List registrations (viewer+)
    $adminRouter->get('/registrations', [RegistrationController::class, 'index'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Create registration form (coordinator+)
    $adminRouter->get('/registrations/create', [RegistrationController::class, 'create'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR)]);

    // Store new registration (coordinator+, CSRF protected)
    $adminRouter->post('/registrations', [RegistrationController::class, 'store'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // View registration details (viewer+)
    $adminRouter->get('/registrations/{id}', [RegistrationController::class, 'show'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // View administrative attendance pass (viewer+)
    $adminRouter->get('/registrations/{id}/pass', [RegistrationController::class, 'pass'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Printable attendance pass layout (viewer+)
    $adminRouter->get('/registrations/{id}/print', [RegistrationController::class, 'printPass'], [new RoleMiddleware(RoleService::ROLE_VIEWER)]);

    // Coordinator approves pending registration (coordinator+, CSRF protected)
    $adminRouter->post('/registrations/{id}/approve', [RegistrationController::class, 'approve'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Coordinator moves pending registration to waitlist when full (coordinator+, CSRF protected)
    $adminRouter->post('/registrations/{id}/waitlist', [RegistrationController::class, 'moveToWaitlist'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Coordinator promotes waitlisted registration to confirmed (coordinator+, CSRF protected)
    $adminRouter->post('/registrations/{id}/promote', [RegistrationController::class, 'promote'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Cancel registration (coordinator+, CSRF protected)
    $adminRouter->post('/registrations/{id}/cancel', [RegistrationController::class, 'cancel'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

    // Reactivate cancelled registration (coordinator+, CSRF protected)
    $adminRouter->post('/registrations/{id}/reactivate', [RegistrationController::class, 'reactivate'], [new RoleMiddleware(RoleService::ROLE_COORDINATOR), CsrfMiddleware::class]);

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
