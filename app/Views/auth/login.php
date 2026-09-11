<div class="auth-portal-grid">
  <!-- Left Column: Mission Inspiration -->
  <aside class="auth-side-col auth-side-left" aria-label="Campaign Mission">
    <div class="auth-mission-quote">
      <div class="auth-mission-accent-bar" aria-hidden="true"></div>
      <div class="auth-mission-text">
        <div class="auth-mission-heading">
          <span>Stronger</span>
          <span>Communities</span>
          <span>Brighter Tomorrows</span>
        </div>
        <div class="auth-mission-tagline">PEOPLE &bull; PURPOSE &bull; PREVENTION</div>
      </div>
    </div>
  </aside>

  <!-- Center Column: Main Login Card -->
  <div class="public-form-card auth-card">
    <div class="auth-card-header">
      <div class="auth-card-logo-wrap">
        <img src="<?= e(asset('images/listening-community-logo.png')) ?>" alt="<?= e(config('app.name')) ?>" class="auth-card-logo">
      </div>
      <h1 class="auth-title">Administrative Sign In</h1>
      <p class="auth-subtitle">Listening Community &mdash; Suicide Prevention Campaign</p>
    </div>

    <form id="adminLoginForm" action="<?= e(url('/login')) ?>" method="POST" autocomplete="on" novalidate>
      <?= csrf_field() ?>

      <div class="form-group mb-3">
        <label for="login-email" class="form-label form-label-required">Administrative Email</label>
        <div class="form-input-group">
          <span class="form-input-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect width="20" height="16" x="2" y="4" rx="2"/>
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
          </span>
          <input 
            type="email" 
            id="login-email" 
            name="email" 
            class="form-control" 
            placeholder="incubation.ngo@gmail.com" 
            autocomplete="username" 
            enterkeyhint="next"
            required
            autofocus
          >
        </div>
      </div>

      <div class="form-group mb-4">
        <label for="login-password" class="form-label form-label-required">Password</label>
        <div class="form-input-group">
          <span class="form-input-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input 
            type="password" 
            id="login-password" 
            name="password" 
            class="form-control has-action" 
            placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" 
            autocomplete="current-password" 
            enterkeyhint="done"
            required
          >
          <button 
            type="button" 
            id="togglePasswordBtn" 
            class="form-input-action" 
            aria-label="Show password" 
            aria-controls="login-password" 
            aria-pressed="false"
          >
            <!-- SVG Eye (visible when password is hidden) -->
            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <!-- SVG Eye Off (visible when password is text) -->
            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: none;">
              <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
              <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
              <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
              <line x1="2" x2="22" y1="2" y2="22"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="mb-4">
        <button type="submit" id="loginSubmitBtn" class="btn btn-primary btn-auth-submit">
          <span class="btn-text">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
              <polyline points="10 17 15 12 10 7"/>
              <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            <span>Sign In to Portal</span>
          </span>
          <span class="btn-spinner" aria-hidden="true" style="display: none;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin">
              <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
              <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
            </svg>
            <span>Signing In...</span>
          </span>
        </button>
      </div>

      <div class="auth-divider">
        <span>Or return to public site</span>
      </div>

      <div class="auth-back-home-wrap">
        <a href="<?= e(url('/')) ?>" class="btn-back-home">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          <span>Back to Home</span>
        </a>
      </div>
    </form>
  </div>

  <!-- Right Column: Institutional Trust Highlights -->
  <aside class="auth-side-col auth-side-right" aria-label="Portal Security Highlights">
    <div class="auth-feature-list">
      <div class="auth-feature-item">
        <div class="auth-feature-icon-wrapper auth-feature-icon-red" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <div class="auth-feature-content">
          <div class="auth-feature-title">Secure Access</div>
          <div class="auth-feature-desc">Authorized staff and coordinators only</div>
        </div>
      </div>

      <div class="auth-feature-item">
        <div class="auth-feature-icon-wrapper auth-feature-icon-blue" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="auth-feature-content">
          <div class="auth-feature-title">Manage with Confidence</div>
          <div class="auth-feature-desc">Events &bull; Participants &bull; Registrations</div>
        </div>
      </div>

      <div class="auth-feature-item">
        <div class="auth-feature-icon-wrapper auth-feature-icon-purple" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 20V10"/>
            <path d="M12 20V4"/>
            <path d="M6 20v-6"/>
          </svg>
        </div>
        <div class="auth-feature-content">
          <div class="auth-feature-title">Greater Impact</div>
          <div class="auth-feature-desc">Together for a safer, stronger community</div>
        </div>
      </div>
    </div>
  </aside>
</div>
