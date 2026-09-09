<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>419 - Page Expired | <?= e(config('app.name', 'LC-SPC')) ?></title>
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
      color: #d97706;
      line-height: 1;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="error-container">
    <div class="error-code">419</div>
    <h1 style="font-size: 1.5rem; margin-bottom: 0.75rem; color: #1e293b;">Security Token Expired</h1>
    <p style="color: #64748b; margin-bottom: 1.5rem; line-height: 1.6;">
      The page or form session expired. This happens if the form was left idle for too long.
    </p>
    <div>
      <a href="javascript:history.back()" class="btn btn-primary btn-sm">
        &larr; Return &amp; Try Again
      </a>
    </div>
  </div>
</body>
</html>
