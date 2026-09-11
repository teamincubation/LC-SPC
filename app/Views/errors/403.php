<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 - Access Forbidden | <?= e(config('app.name', 'LC-SPC')) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <style>
    .error-container {
      max-width: 540px;
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
      color: #dc2626;
      line-height: 1;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="error-container">
    <div class="error-code">403</div>
    <h1 style="font-size: 1.5rem; margin-bottom: 0.75rem; color: #1e293b;"><?= e($title ?? 'Access Forbidden') ?></h1>
    <p style="color: #64748b; margin-bottom: 1.5rem; line-height: 1.6;">
      <?= e($message ?? 'You do not possess the administrative privileges required to access this resource or action.') ?>
      <?php if (!empty($requiredRole)): ?>
        <br><small style="color: #94a3b8;">Requires minimum rank: <strong><?= e($requiredRole) ?></strong></small>
      <?php endif; ?>
    </p>
    <div style="display: flex; justify-content: center; gap: 0.75rem;">
      <a href="<?= e(url('/admin')) ?>" class="btn btn-primary btn-sm">
        &larr; Back to Dashboard
      </a>
      <a href="<?= e(url('/')) ?>" class="btn btn-outline btn-sm">
        Public Home
      </a>
    </div>
  </div>
</body>
</html>
