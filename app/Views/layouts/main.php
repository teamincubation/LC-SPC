<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(config('app.full_title', 'Listening Community – Suicide Prevention Campaign')) ?></title>
  <meta name="description" content="Official portal for the Listening Community – Suicide Prevention Campaign (LC-SPC). Compassion, Action, Hope.">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  
  <!-- Typography: Google Sans Flex loaded via Google Fonts (Compatible with CSP) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- LC-SPC Design System Stylesheet -->
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="public-layout">
  <!-- Accessibility: Skip Link -->
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <!-- Header -->
  <header class="public-header" role="banner">
    <div class="public-header-inner">
      <a href="<?= e(url('/')) ?>" class="public-brand" aria-label="<?= e(config('app.full_title')) ?> Home">
        <img src="<?= e(asset('images/listening-community-logo.png')) ?>" alt="<?= e(config('app.name')) ?>" class="brand-logo">
      </a>
      <nav aria-label="Main Navigation">
        <div class="flex gap-2">
          <a href="<?= e(url('/')) ?>" class="btn btn-ghost btn-sm">Home</a>
          <a href="<?= e(url('/health')) ?>" class="btn btn-outline btn-sm">Health Status</a>
        </div>
      </nav>
    </div>
  </header>

  <!-- Main Content Container -->
  <main id="main-content" class="container mt-6 mb-6" role="main">
    <?php if ($flashSuccess = flash('success')): ?>
      <div class="alert alert-success" data-dismissible="true" role="status">
        <div class="alert-content">
          <strong>Success:</strong> <?= e($flashSuccess) ?>
        </div>
        <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close message">&times;</button>
      </div>
    <?php endif; ?>

    <?php if ($flashError = flash('error')): ?>
      <div class="alert alert-danger" data-dismissible="true" role="alert">
        <div class="alert-content">
          <strong>Error:</strong> <?= e($flashError) ?>
        </div>
        <button type="button" class="alert-close" data-dismiss="alert" aria-label="Close alert">&times;</button>
      </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>

  <!-- Footer -->
  <footer class="public-footer" role="contentinfo">
    <div class="container">
      <p class="mb-2">
        &copy; <?= date('Y') ?> <strong><?= e(config('app.name')) ?></strong> &mdash; <?= e(config('app.full_title')) ?>. All rights reserved.
      </p>
      <p class="text-caption text-muted">
        Version <?= e(config('app.version', '1.0.0')) ?> &bull; Environment: <?= e(ucfirst((string) config('app.env', 'production'))) ?>
      </p>
    </div>
  </footer>

  <!-- Generic Interactions -->
  <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
