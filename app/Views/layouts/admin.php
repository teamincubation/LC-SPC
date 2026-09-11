<?php

declare(strict_types=1);

/**
 * Institutional Admin Layout Template for LC-SPC
 * Responsive SaaS Administration Shell with SVG Icon System and Unified Design Hierarchy.
 */

// Active navigation detection
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$activeNavSlug = $activeNav ?? null;
$isNavActive = function (string $slug) use ($currentUri, $activeNavSlug): bool {
    if (!empty($activeNavSlug) && $activeNavSlug === $slug) {
        return true;
    }
    $basePath = url('/admin' . ($slug === 'dashboard' ? '' : '/' . $slug));
    if ($slug === 'dashboard') {
        $exactPath = rtrim(url('/admin'), '/');
        $cleanUri = rtrim(explode('?', $currentUri)[0], '/');
        return $cleanUri === $exactPath || $cleanUri === $exactPath . '/';
    }
    return str_starts_with(explode('?', $currentUri)[0], $basePath);
};

// User identity extraction
$authUserId = session('_auth_user_id');
$authUser = null;
if (!empty($authUserId)) {
    try {
        $authUser = (new \App\Repositories\UserRepository())->findById((int) $authUserId);
    } catch (\Throwable $e) {
        // Fallback gracefully if database context differs
    }
}
$adminName = $authUser['name'] ?? ($user['name'] ?? 'Admin User');
$adminRoleSlug = $authUser['role'] ?? ($user['role'] ?? session('_auth_user_role', 'viewer'));
$adminRoleLabel = \App\Services\RoleService::getRoleLabel($adminRoleSlug);

// Avatar initials
$nameParts = preg_split('/\s+/', trim((string) $adminName));
$initials = '';
if (!empty($nameParts[0])) {
    $initials .= mb_substr($nameParts[0], 0, 1);
}
if (count($nameParts) > 1 && !empty($nameParts[1])) {
    $initials .= mb_substr($nameParts[1], 0, 1);
}
$initials = strtoupper($initials ?: 'LC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Admin Portal') ?> &mdash; <?= e(config('app.name')) ?></title>
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

  <!-- Typography: Google Sans Flex -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- LC-SPC Design System Stylesheet -->
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
  <!-- Accessibility: Skip Link -->
  <a href="#admin-main-content" class="skip-link">Skip to main content</a>

  <div class="admin-layout">
    <!-- Admin Sidebar Navigation Drawer -->
    <aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="Admin Sidebar Navigation">
      <div class="admin-sidebar-header">
        <a href="<?= e(url('/admin')) ?>" aria-label="<?= e(config('app.name')) ?> Dashboard" style="display: flex; align-items: center;">
          <img src="<?= e(asset('images/listening-community-logo.png')) ?>" alt="<?= e(config('app.name')) ?>" class="brand-logo-sm">
        </a>
        <button type="button" class="admin-sidebar-close" aria-label="Close navigation menu">
          <?= icon('x') ?>
        </button>
      </div>

      <ul class="admin-nav" role="menubar">
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin')) ?>" class="admin-nav-link <?= $isNavActive('dashboard') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('dashboard') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('layout-dashboard') ?></span>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/campaigns')) ?>" class="admin-nav-link <?= $isNavActive('campaigns') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('campaigns') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('target') ?></span>
            <span>Campaigns</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/events')) ?>" class="admin-nav-link <?= $isNavActive('events') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('events') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('calendar') ?></span>
            <span>Events</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/participants')) ?>" class="admin-nav-link <?= $isNavActive('participants') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('participants') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('users') ?></span>
            <span>Participants</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/registrations')) ?>" class="admin-nav-link <?= $isNavActive('registrations') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('registrations') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('clipboard-list') ?></span>
            <span>Registrations</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/checkin')) ?>" class="admin-nav-link <?= $isNavActive('checkin') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('checkin') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('scan-line') ?></span>
            <span>Check-in</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/certificates')) ?>" class="admin-nav-link <?= $isNavActive('certificates') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('certificates') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('award') ?></span>
            <span>Certificates</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/reports')) ?>" class="admin-nav-link <?= $isNavActive('reports') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('reports') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('bar-chart') ?></span>
            <span>Reports</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/settings')) ?>" class="admin-nav-link <?= $isNavActive('settings') ? 'is-active' : '' ?>" role="menuitem" <?= $isNavActive('settings') ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon" aria-hidden="true"><?= icon('settings') ?></span>
            <span>Settings</span>
          </a>
        </li>
      </ul>

      <div class="admin-sidebar-footer">
        <div><strong><?= e(config('app.name')) ?></strong> Foundation</div>
        <div class="text-caption">Version <?= e(config('app.version')) ?></div>
      </div>
    </aside>

    <!-- Admin Main Content Shell -->
    <div class="admin-main">
      <!-- Top Bar -->
      <header class="admin-header" role="banner">
        <div class="admin-header-left">
          <button type="button" class="admin-sidebar-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="adminSidebar">
            <?= icon('menu') ?>
          </button>
          <nav class="admin-header-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('/admin')) ?>" class="admin-breadcrumb-root">
              <?= icon('home', ['width' => '14', 'height' => '14']) ?>
              <span>Admin</span>
            </a>
            <span class="admin-breadcrumb-sep" aria-hidden="true">&rsaquo;</span>
            <span class="admin-breadcrumb-current" aria-current="page"><?= e($breadcrumb ?? ($title ?? 'Overview')) ?></span>
          </nav>
        </div>

        <div class="admin-header-right">
          <a href="<?= e(url('/health')) ?>" class="badge badge-success" title="System Status: Healthy" style="text-decoration: none;">
            <span class="badge-dot" aria-hidden="true"></span>
            <span>Live Health</span>
          </a>

          <div class="admin-user-profile" aria-label="Current user: <?= e($adminName) ?> (<?= e($adminRoleLabel) ?>)">
            <div class="admin-avatar" aria-hidden="true"><?= e($initials) ?></div>
            <div class="admin-user-meta">
              <span class="admin-user-name"><?= e($adminName) ?></span>
              <span class="admin-user-role"><?= e($adminRoleLabel) ?></span>
            </div>
          </div>

          <form action="<?= e(url('/logout')) ?>" method="POST" style="margin: 0; display: inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn-logout" aria-label="Sign out of portal" title="Sign Out">
              <?= icon('log-out') ?>
              <span class="btn-logout-text">Logout</span>
            </button>
          </form>
        </div>
      </header>

      <!-- Content Area -->
      <main id="admin-main-content" class="admin-content" role="main">
        <?php if ($flashSuccess = flash('success')): ?>
          <div class="alert alert-success" data-dismissible="true" role="status" style="display: flex; align-items: center; gap: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <?= icon('check-circle', ['class' => 'text-success', 'style' => 'width: 20px; height: 20px;']) ?>
            <div class="alert-content" style="flex: 1;">
              <?= e($flashSuccess) ?>
            </div>
            <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close message" style="background: transparent; border: none; cursor: pointer; color: inherit; font-size: 1.25rem;">&times;</button>
          </div>
        <?php endif; ?>

        <?php if ($flashError = flash('error')): ?>
          <div class="alert alert-danger" data-dismissible="true" role="alert" style="display: flex; align-items: center; gap: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <?= icon('alert-triangle', ['class' => 'text-danger', 'style' => 'width: 20px; height: 20px;']) ?>
            <div class="alert-content" style="flex: 1;">
              <?= e($flashError) ?>
            </div>
            <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close alert" style="background: transparent; border: none; cursor: pointer; color: inherit; font-size: 1.25rem;">&times;</button>
          </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
      </main>
    </div>
  </div>

  <!-- Design System Interactions Script -->
  <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
