<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(config('app.full_title', 'Listening Community – Suicide Prevention Campaign')) ?></title>
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
  <header class="site-header">
    <div class="header-inner">
      <a href="<?= e(url('/')) ?>" class="brand">
        <span class="brand-badge">LC-SPC</span>
        <div>
          <span class="brand-title"><?= e(config('app.name')) ?></span>
          <span class="brand-subtitle"><?= e(config('app.full_title')) ?></span>
        </div>
      </a>
      <nav>
        <ul class="nav-links">
          <li><a href="<?= e(url('/')) ?>" class="nav-link">Home</a></li>
          <li><a href="<?= e(url('/health')) ?>" class="nav-link">Health Check</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main class="main-content">
    <?php if ($flashSuccess = flash('success')): ?>
      <div class="alert alert-success" data-dismissible="true">
        <?= e($flashSuccess) ?>
      </div>
    <?php endif; ?>

    <?php if ($flashError = flash('error')): ?>
      <div class="alert alert-danger" data-dismissible="true">
        <?= e($flashError) ?>
      </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>

  <footer class="site-footer">
    <div class="footer-inner">
      <div>
        &copy; <?= date('Y') ?> Team Incubation. All rights reserved.
      </div>
      <div>
        <span>Version: <?= e(config('app.version')) ?></span> &bull;
        <span>Env: <?= e(config('app.env')) ?></span>
      </div>
    </div>
  </footer>

  <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
