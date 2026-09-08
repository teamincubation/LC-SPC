<!-- Hero & Foundation Status Banner -->
<div class="card" style="border-left: 4px solid var(--color-primary);">
  <div class="card-header">
    <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
      <img src="<?= e(asset('images/listening-community-logo.png')) ?>" alt="<?= e($appName) ?>" class="brand-logo" style="max-height: 56px;">
      <div>
        <h1 class="card-title" style="font-size: var(--font-size-2xl); margin-bottom: 0.25rem;">
          <?= e($fullTitle) ?>
        </h1>
        <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
          <strong>Creating Safe Spaces for Real Conversations</strong> &mdash; Words and Beyond.
        </p>
      </div>
    </div>
    <span class="badge badge-success">
      <span class="badge-dot" aria-hidden="true"></span>
      Phase 0: UX/UI Foundation Ready
    </span>
  </div>

  <p class="text-body-large" style="margin-top: 0.5rem;">
    Welcome to the standalone portal for the <strong>Listening Community – Suicide Prevention Campaign (LC-SPC)</strong>.
    The UX/UI design system has been established with official brand colors (<span class="code-inline">#BF1E2E</span> + <span class="code-inline">#FBFBFB</span>),
    <strong>Google Sans Flex</strong> typography, accessible components, responsive layouts, and zero external framework dependencies.
  </p>
</div>

<!-- Foundation Status KPI Cards -->
<section class="grid grid-cols-4 mb-6" aria-label="System Foundation Status">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--color-primary-tint); color: var(--color-primary);">
      &#9672;
    </div>
    <div class="card-metric-value"><?= e($appName) ?></div>
    <div class="card-metric-label">Application Core</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      &#9881;
    </div>
    <div class="card-metric-value"><?= $dbConnected ? 'Online' : 'Pending' ?></div>
    <div class="card-metric-label">Database u806388046_LC</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-info); color: var(--text-info);">
      &#128274;
    </div>
    <div class="card-metric-value">Hardened</div>
    <div class="card-metric-label">Session &amp; CSP Policy</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      &#9874;
    </div>
    <div class="card-metric-value">PHP <?= e($phpVersion) ?></div>
    <div class="card-metric-label">Runtime Engine</div>
  </div>
</section>

<!-- Design System Showcase: Component Foundation -->
<section class="grid grid-cols-2 mb-6" aria-label="Design System Showcase">
  <!-- Left Column: Buttons & Badges -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title" style="font-size: var(--font-size-md);">Buttons &amp; Interactive Tokens</h2>
      <span class="text-caption text-muted">Demonstration</span>
    </div>

    <p class="text-secondary mb-4">
      Standardized buttons built on the brand color palette, offering high contrast, active states, and a minimum 44px touch target.
    </p>

    <div class="flex flex-wrap gap-3 mb-4">
      <button type="button" class="btn btn-primary">Primary Action &rarr;</button>
      <button type="button" class="btn btn-secondary">Secondary Action</button>
      <button type="button" class="btn btn-outline">Neutral Outline</button>
      <button type="button" class="btn btn-ghost">Ghost</button>
      <button type="button" class="btn btn-danger">Destructive</button>
      <button type="button" class="btn btn-primary" disabled>Disabled</button>
    </div>

    <div class="card-header" style="margin-top: 1.5rem;">
      <h3 class="card-title" style="font-size: var(--font-size-sm);">Status Badges (No Color-Only Indication)</h3>
    </div>
    <div class="flex flex-wrap gap-2">
      <span class="badge badge-primary"><span class="badge-dot" aria-hidden="true"></span> Primary Brand</span>
      <span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Verified / Connected</span>
      <span class="badge badge-warning"><span class="badge-dot" aria-hidden="true"></span> Attention Required</span>
      <span class="badge badge-danger"><span class="badge-dot" aria-hidden="true"></span> Error Detected</span>
      <span class="badge badge-info"><span class="badge-dot" aria-hidden="true"></span> Information</span>
      <span class="badge badge-neutral"><span class="badge-dot" aria-hidden="true"></span> Neutral Default</span>
    </div>
  </div>

  <!-- Right Column: Form Elements -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title" style="font-size: var(--font-size-md);">Accessible Form Architecture</h2>
      <span class="text-caption text-muted">Demonstration</span>
    </div>

    <p class="text-secondary mb-4">
      Forms feature explicit labels, required indicators, helper text, accessible error feedback, and keyboard-navigable controls.
    </p>

    <form onsubmit="return false;" novalidate>
      <div class="form-group">
        <label for="demo_name" class="form-label form-label-required">Full Name</label>
        <input type="text" id="demo_name" class="form-control" placeholder="e.g. Alex Sharma" autocomplete="name" required>
        <span class="form-hint">Used for participant identification and verification.</span>
      </div>

      <div class="form-group">
        <label for="demo_category" class="form-label">Participation Category</label>
        <select id="demo_category" class="form-select">
          <option value="student">Student / Youth Volunteer</option>
          <option value="professional">Mental Health Professional</option>
          <option value="community">Community Supporter</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" class="form-check-input" checked>
          <span class="form-check-label">I agree to the Community Guidelines &amp; Code of Conduct</span>
        </label>
      </div>

      <div class="flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-modal-target="#demoModal">Preview Modal</button>
        <span class="text-caption text-secondary" style="align-self: center;">Click to test accessible dialog controller.</span>
      </div>
    </form>
  </div>
</section>

<!-- Dismissible Alert Demonstration -->
<div class="alert alert-info" data-dismissible="true" role="status">
  <div class="alert-icon" aria-hidden="true">&#9432;</div>
  <div class="alert-content">
    <strong>Design System Active:</strong> Typography is powered exclusively by <strong>Google Sans Flex</strong> with clean system fallbacks. Security headers remain strictly enforced under CSP and Permissions-Policy.
  </div>
  <button type="button" class="alert-close" data-dismiss="alert" aria-label="Dismiss alert">&times;</button>
</div>

<!-- Architecture & Foundation Verification Table -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title" style="font-size: var(--font-size-md);">Architecture Specification &amp; System Configuration</h2>
    <span class="badge badge-neutral">Phase 0 Foundation</span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr>
          <th scope="col">Parameter</th>
          <th scope="col">Configuration Value</th>
          <th scope="col">Status</th>
          <th scope="col">Notes</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Brand Identity</strong></td>
          <td>Listening Community SPC (<span class="code-inline">LC-SPC</span>)</td>
          <td><span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Verified</span></td>
          <td>Official logo placed at <span class="code-inline">public/assets/images/listening-community-logo.png</span></td>
        </tr>
        <tr>
          <td><strong>Primary Brand Color</strong></td>
          <td><span class="code-inline">#BF1E2E</span> (Primary Red)</td>
          <td><span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Active</span></td>
          <td>Paired with <span class="code-inline">#FBFBFB</span> background &amp; high-contrast text</td>
        </tr>
        <tr>
          <td><strong>Typography</strong></td>
          <td><span class="code-inline">Google Sans Flex</span></td>
          <td><span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Loaded</span></td>
          <td>Linked via Google Fonts; robust system sans-serif fallback</td>
        </tr>
        <tr>
          <td><strong>Database Isolation</strong></td>
          <td>Dedicated DB: <span class="code-inline">u806388046_LC</span></td>
          <td><span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Isolated</span></td>
          <td>Dedicated user <span class="code-inline">u806388046_LC_SPC</span>; zero shared dependencies</td>
        </tr>
        <tr>
          <td><strong>Hostinger Path Resolution</strong></td>
          <td>Base Path: <span class="code-inline"><?= e($basePath ?: '/') ?></span></td>
          <td><span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Resolved</span></td>
          <td>Seamless execution across both local root and <span class="code-inline">/LC/</span> production</td>
        </tr>
        <tr>
          <td><strong>Permissions-Policy</strong></td>
          <td><span class="code-inline">geolocation=(self)</span></td>
          <td><span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Hardened</span></td>
          <td>Permitted for application origin; rejects arbitrary third parties</td>
        </tr>
        <tr>
          <td><strong>Session Security</strong></td>
          <td><span class="code-inline">LCSPC_SESSION</span> (7200s, HttpOnly, Lax)</td>
          <td><span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Secure</span></td>
          <td>Safe abort on headers sent; secure cookie deletion on destroy</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Demonstration Modal Dialog -->
<div class="modal" id="demoModal" role="dialog" aria-modal="true" aria-labelledby="demoModalTitle" aria-hidden="true">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title" id="demoModalTitle">LC-SPC Accessible Modal</h3>
      <button type="button" class="alert-close" data-modal-close aria-label="Close dialog">&times;</button>
    </div>
    <div class="modal-body">
      <p class="mb-3">
        This dialog demonstrates the accessible design-system modal architecture:
      </p>
      <ul style="padding-left: 1.5rem; color: var(--text-secondary); font-size: var(--font-size-sm); line-height: 1.6;">
        <li>Traps focus and focuses the first interactive control upon opening</li>
        <li>Closes gracefully when clicking the backdrop, pressing <kbd class="code-inline">Escape</kbd>, or clicking the close button</li>
        <li>Restores focus back to the triggering element on close</li>
        <li>Contains zero external libraries or jQuery dependencies</li>
      </ul>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" data-modal-close>Understood</button>
    </div>
  </div>
</div>
