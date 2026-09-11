<!-- Settings Page Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <h1>
      <?= icon('settings', ['width' => '24', 'height' => '24']) ?>
      <span>System Settings</span>
    </h1>
    <p>Platform environment parameters, active security policies, and institutional configuration.</p>
  </div>

  <div class="admin-page-header-actions">
    <span class="badge-pill badge-primary" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; padding: 0.35rem 0.75rem;">
      <?= icon('shield', ['width' => '12', 'height' => '12']) ?>
      <span>Role: <?= e($roleLabel) ?></span>
    </span>
  </div>
</div>

<div class="grid grid-cols-2 gap-6 mb-6">
  <!-- Card 1: Platform & Environment -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 0.5rem;">
      <span style="color: var(--primary); display: flex;">
        <?= icon('server', ['width' => '18', 'height' => '18']) ?>
      </span>
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary);">
        Platform &amp; Environment
      </h2>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Application Name</span>
          <span style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);"><?= e($appName) ?></span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Environment</span>
          <span class="badge-pill <?= $appEnv === 'production' ? 'badge-success' : 'badge-warning' ?>" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
            <?= e($appEnv) ?>
          </span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Base URL</span>
          <code style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--primary); background: var(--bg-surface-subtle); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <?= e($appUrl) ?>
          </code>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Platform Version</span>
          <span style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">v<?= e($version) ?></span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Server Timezone</span>
          <span style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);"><?= e($timezone) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Security & Authentication Policy -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 0.5rem;">
      <span style="color: var(--success); display: flex;">
        <?= icon('shield', ['width' => '18', 'height' => '18']) ?>
      </span>
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary);">
        Security &amp; Auth Policy
      </h2>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Password Hashing</span>
          <span style="font-size: var(--font-size-xs); font-weight: 600; color: var(--text-primary);">Argon2id (bcrypt fallback)</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Lockout Protection</span>
          <span style="font-size: var(--font-size-xs); font-weight: 600; color: var(--text-primary);">5 attempts &bull; 15 min cooldown</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Session Protection</span>
          <span style="font-size: var(--font-size-xs); font-weight: 600; color: var(--text-primary);">HttpOnly, SameSite=Lax</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Session Lifetime</span>
          <span style="font-size: var(--font-size-xs); font-weight: 600; color: var(--text-primary);">7,200 seconds (2 hours)</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">CSRF Validation</span>
          <span class="badge-pill badge-success" style="font-size: 0.65rem; font-weight: 600;">ENFORCED</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-cols-2 gap-6 mb-6">
  <!-- Card 3: Privacy & Data Protection Compliance -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 0.5rem;">
      <span style="color: var(--primary-light); display: flex;">
        <?= icon('lock', ['width' => '18', 'height' => '18']) ?>
      </span>
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary);">
        Privacy &amp; Compliance Standards
      </h2>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <div>
            <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">PII Field Masking</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-muted);">Phone &amp; email masked for viewer/staff roles</div>
          </div>
          <span class="badge-pill badge-success" style="font-size: 0.65rem; font-weight: 600;">ACTIVE</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <div>
            <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">Cryptographic Bearer Tokens</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-muted);">256-bit random tokens for verification</div>
          </div>
          <span class="badge-pill badge-success" style="font-size: 0.65rem; font-weight: 600;">ACTIVE</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">Post-Event Reconciliation Lock</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-muted);">Event end time + 4 hours operational gate</div>
          </div>
          <span class="badge-pill badge-success" style="font-size: 0.65rem; font-weight: 600;">ENFORCED</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 4: System Runtime Diagnostics -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 0.5rem;">
      <span style="color: var(--warning); display: flex;">
        <?= icon('clock', ['width' => '18', 'height' => '18']) ?>
      </span>
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary);">
        System Runtime Diagnostics
      </h2>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">PHP Runtime</span>
          <span style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);"><?= PHP_VERSION ?></span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Database Engine</span>
          <span style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">MySQL 8.0 / MariaDB (PDO)</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.65rem; border-bottom: 1px solid var(--border-color);">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Web Server</span>
          <span style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);"><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in Server') ?></span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: var(--font-size-xs); color: var(--text-muted); font-weight: 500;">Host Platform</span>
          <span style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);"><?= PHP_OS ?></span>
        </div>
      </div>
    </div>
  </div>
</div>
