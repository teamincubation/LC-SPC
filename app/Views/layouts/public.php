<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? config('app.full_title', 'Listening Community – Suicide Prevention Campaign')) ?></title>
  <meta name="description" content="Official participant portal for Listening Community – Suicide Prevention Campaign (LC-SPC).">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

  <!-- Typography: Google Sans Flex -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <?php
    $appCssPath = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/public/assets/css/app.css';
    $appJsPath = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/public/assets/js/app.js';
    $cssVer = file_exists($appCssPath) ? (string) filemtime($appCssPath) : '2.0.2';
    $jsVer = file_exists($appJsPath) ? (string) filemtime($appJsPath) : '2.0.2';
  ?>

  <!-- LC-SPC Design System Stylesheet -->
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=<?= e($cssVer) ?>">
</head>
<body class="public-layout auth-layout">
  <!-- Accessibility: Skip Link -->
  <a href="#auth-main-content" class="skip-link">Skip to main content</a>

  <!-- Header -->
  <header class="public-header auth-header" role="banner">
    <div class="public-header-inner auth-header-inner">
      <div class="auth-header-brand-group">
        <a href="<?= e(url('/')) ?>" class="public-brand auth-brand" aria-label="<?= e(config('app.full_title')) ?> Home">
          <img 
            src="<?= e(asset('images/listening-community-logo.png')) ?>" 
            alt="<?= e(config('app.name')) ?>" 
            class="brand-logo auth-brand-logo"
            width="36"
            height="36"
            style="height: 36px; max-height: 36px; width: auto; max-width: 140px; object-fit: contain; display: block;"
          >
        </a>
        <div class="auth-header-divider" aria-hidden="true"></div>
        <div class="auth-header-title-block">
          <span class="auth-header-title">Suicide Prevention Campaign</span>
          <span class="auth-header-tagline">LISTEN &bull; SUPPORT &bull; EMPOWER</span>
        </div>
      </div>
      <div class="auth-header-actions">
        <span class="badge-pill auth-badge-pill">LC-SPC</span>
      </div>
    </div>
  </header>

  <!-- Content Shell -->
  <main id="auth-main-content" class="public-main auth-main" role="main">
    <div class="auth-portal-wrapper">
      <?php if ($flashSuccess = flash('success')): ?>
        <div class="alert alert-success auth-flash-alert" data-dismissible="true" role="status">
          <div class="alert-content"><?= e($flashSuccess) ?></div>
          <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close message">&times;</button>
        </div>
      <?php endif; ?>

      <?php if ($flashError = flash('error')): ?>
        <div class="alert alert-danger auth-flash-alert" data-dismissible="true" role="alert">
          <div class="alert-content"><?= e($flashError) ?></div>
          <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close alert">&times;</button>
        </div>
      <?php endif; ?>

      <?= $content ?? '' ?>
    </div>
  </main>

  <!-- Footer -->
  <footer class="public-footer auth-footer" role="contentinfo">
    <div class="container auth-footer-inner">
      <div class="auth-footer-copy">
        &copy; <?= date('Y') ?> <?= e(config('app.name')) ?> &mdash; <?= e(config('app.full_title')) ?>. All rights reserved.
      </div>
      <div class="auth-footer-links">
        <a href="<?= e(url('/')) ?>" class="auth-footer-link">Privacy Policy</a>
        <span class="auth-footer-sep">|</span>
        <a href="<?= e(url('/')) ?>" class="auth-footer-link">Terms of Use</a>
        <span class="auth-footer-sep">|</span>
        <a href="<?= e(url('/')) ?>" class="auth-footer-link">Contact</a>
      </div>
    </div>
  </footer>

  <script src="<?= e(asset('js/app.js')) ?>?v=<?= e($jsVer) ?>"></script>
</body>
</html>
