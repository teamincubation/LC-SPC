<div style="text-align: center; margin-bottom: 2rem;">
  <img src="<?= e(asset('images/listening-community-logo.png')) ?>" alt="<?= e(config('app.name')) ?>" style="max-height: 52px; margin-bottom: 1rem;">
  <h1 style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin-bottom: 0.5rem; color: var(--text-primary);">
    Administrative Sign In
  </h1>
  <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
    Listening Community &mdash; Suicide Prevention Campaign
  </p>
</div>

<form action="<?= e(url('/login')) ?>" method="POST" autocomplete="on" novalidate>
  <?= csrf_field() ?>

  <div class="form-group mb-4">
    <label for="login-email" class="form-label form-label-required">Administrative Email</label>
    <input 
      type="email" 
      id="login-email" 
      name="email" 
      class="form-control" 
      placeholder="name@teami.in" 
      autocomplete="username" 
      required
      autofocus
    >
    <span class="form-hint">Enter your authorized staff or coordinator email address.</span>
  </div>

  <div class="form-group mb-5">
    <div style="display: flex; justify-content: space-between; align-items: baseline;">
      <label for="login-password" class="form-label form-label-required">Password</label>
    </div>
    <input 
      type="password" 
      id="login-password" 
      name="password" 
      class="form-control" 
      placeholder="Enter your account password" 
      autocomplete="current-password" 
      required
    >
  </div>

  <div class="mb-4">
    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; font-weight: var(--font-weight-semibold); padding: 0.75rem 1.5rem;">
      Sign In to Portal &rarr;
    </button>
  </div>

  <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem; margin-top: 1.5rem; text-align: center;">
    <p class="text-caption text-muted" style="margin: 0;">
      Authorized personnel only. All authentication attempts and security events are logged for forensic audit.
    </p>
    <p class="text-caption" style="margin-top: 0.5rem;">
      <a href="<?= e(url('/')) ?>" style="color: var(--color-primary); text-decoration: none;">&larr; Return to Public Campaign Page</a>
    </p>
  </div>
</form>
