<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 - Page Not Found | <?= e(config('app.name', 'LC-SPC')) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <style>
    .error-container {
      max-width: 500px;
      margin: 5rem auto;
      text-align: center;
      background: #ffffff;
      padding: 3rem 2rem;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }
    .error-code {
      font-size: 4rem;
      font-weight: 800;
      color: #0284c7;
      line-height: 1;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="error-container">
    <div class="error-code">404</div>
    <h1 style="font-size:1.5rem; margin-bottom:0.75rem;"><?= e($title ?? 'Page Not Found') ?></h1>
    <p style="color:#64748b; margin-bottom:2rem;">
      <?= e($message ?? 'The page you are looking for does not exist or has been moved.') ?>
    </p>
    <a href="<?= e(url('/')) ?>" class="nav-link" style="display:inline-block; background:#1e3a8a; color:#fff; padding:0.6rem 1.25rem; border-radius:4px; text-decoration:none;">
      &larr; Return to Home
    </a>
  </div>
</body>
</html>
