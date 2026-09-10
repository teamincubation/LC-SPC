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
    <!-- Admin Sidebar Navigation -->
    <aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="Admin Sidebar Navigation">
      <div class="admin-sidebar-header">
        <a href="<?= e(url('/')) ?>" aria-label="<?= e(config('app.name')) ?> Dashboard">
          <img src="<?= e(asset('images/listening-community-logo.png')) ?>" alt="<?= e(config('app.name')) ?>" class="brand-logo-sm">
        </a>
      </div>

      <ul class="admin-nav" role="menubar">
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin')) ?>" class="admin-nav-link" role="menuitem">
            <span class="admin-nav-icon" aria-hidden="true">&#9638;</span>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="<?= e(url('/admin/campaigns')) ?>" class="admin-nav-link" role="menuitem">
            <span class="admin-nav-icon" aria-hidden="true">&#127919;</span>
            <span>Campaigns</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="#" class="admin-nav-link" role="menuitem" tabindex="-1" aria-disabled="true">
            <span class="admin-nav-icon" aria-hidden="true">&#128197;</span>
            <span>Events</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="#" class="admin-nav-link" role="menuitem" tabindex="-1" aria-disabled="true">
            <span class="admin-nav-icon" aria-hidden="true">&#128101;</span>
            <span>Registrations</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="#" class="admin-nav-link" role="menuitem" tabindex="-1" aria-disabled="true">
            <span class="admin-nav-icon" aria-hidden="true">&#9989;</span>
            <span>Check-in</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="#" class="admin-nav-link" role="menuitem" tabindex="-1" aria-disabled="true">
            <span class="admin-nav-icon" aria-hidden="true">&#127891;</span>
            <span>Certificates</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="#" class="admin-nav-link" role="menuitem" tabindex="-1" aria-disabled="true">
            <span class="admin-nav-icon" aria-hidden="true">&#128202;</span>
            <span>Reports</span>
          </a>
        </li>
        <li class="admin-nav-item" role="none">
          <a href="#" class="admin-nav-link" role="menuitem" tabindex="-1" aria-disabled="true">
            <span class="admin-nav-icon" aria-hidden="true">&#9881;</span>
            <span>Settings</span>
          </a>
        </li>
      </ul>

      <div class="admin-sidebar-footer">
        <div><strong><?= e(config('app.name')) ?></strong> Foundation</div>
        <div class="text-caption">Version <?= e(config('app.version')) ?></div>
      </div>
    </aside>

    <!-- Admin Main Shell -->
    <div class="admin-main">
      <!-- Top Bar -->
      <header class="admin-header" role="banner">
        <div class="admin-header-left">
          <button type="button" class="admin-sidebar-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="adminSidebar">
            &#9776;
          </button>
          <div class="text-caption text-secondary">
            <span>Admin</span> &rsaquo; <strong><?= e($breadcrumb ?? 'Overview') ?></strong>
          </div>
        </div>

        <div class="admin-header-right">
          <a href="<?= e(url('/health')) ?>" class="badge badge-success" title="System Status: Healthy">
            <span class="badge-dot" aria-hidden="true"></span>
            <span>Live Health</span>
          </a>
          <div class="admin-user-menu" tabindex="0" role="button" aria-label="Admin profile">
            <div class="admin-avatar" aria-hidden="true">LC</div>
            <div style="display: flex; flex-direction: column;">
              <span style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold); line-height: 1.2;">Admin User</span>
              <span class="text-caption text-muted" style="line-height: 1;">Administrator</span>
            </div>
          </div>
        </div>
      </header>

      <!-- Content Area -->
      <main id="admin-main-content" class="admin-content" role="main">
        <?php if ($flashSuccess = flash('success')): ?>
          <div class="alert alert-success" data-dismissible="true" role="status">
            <div class="alert-content">
              <?= e($flashSuccess) ?>
            </div>
            <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close message">&times;</button>
          </div>
        <?php endif; ?>

        <?php if ($flashError = flash('error')): ?>
          <div class="alert alert-danger" data-dismissible="true" role="alert">
            <div class="alert-content">
              <?= e($flashError) ?>
            </div>
            <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close alert">&times;</button>
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
