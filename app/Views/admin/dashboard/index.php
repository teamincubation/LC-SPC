<!-- Authenticated Administrative Header -->
<div class="card mb-6" style="border-left: 4px solid var(--color-primary);">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
      <div style="width: 48px; height: 48px; border-radius: 50%; background-color: var(--color-primary-tint); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-weight: var(--font-weight-bold); font-size: var(--font-size-lg);">
        <?= e(strtoupper(substr($user['name'] ?? 'U', 0, 2))) ?>
      </div>
      <div>
        <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.25rem;">
          Welcome, <?= e($user['name'] ?? 'Administrator') ?>
        </h1>
        <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
          Authenticated as <strong><?= e($user['email'] ?? '') ?></strong>
        </p>
      </div>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <span class="badge <?= e($roleBadge ?? 'badge-primary') ?>" style="font-size: var(--font-size-xs); padding: 0.35rem 0.75rem;">
        <span class="badge-dot" aria-hidden="true"></span>
        <?= e($roleLabel ?? 'Staff') ?>
      </span>

      <form action="<?= e(url('/logout')) ?>" method="POST" style="margin: 0;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline btn-sm" style="display: flex; align-items: center; gap: 0.35rem;">
          <span>Sign Out</span> &rarr;
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Session & Security Metrics -->
<section class="grid grid-cols-4 mb-6" aria-label="Session and Authentication Metrics">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      &#10003;
    </div>
    <div class="card-metric-value">Active</div>
    <div class="card-metric-label">Authentication Status</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--color-primary-tint); color: var(--color-primary);">
      &#9638;
    </div>
    <div class="card-metric-value"><?= e($roleLabel ?? 'Viewer') ?></div>
    <div class="card-metric-label">Assigned Role Rank</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-info); color: var(--text-info);">
      &#128274;
    </div>
    <div class="card-metric-value">Hardened</div>
    <div class="card-metric-label">HttpOnly &bull; Lax &bull; Argon2id</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      &#9201;
    </div>
    <div class="card-metric-value">30 min</div>
    <div class="card-metric-label">Session Idle Timeout</div>
  </div>
</section>

<!-- Module Navigation & Foundation Status -->
<div class="card mb-6">
  <div class="card-header">
    <h2 class="card-title" style="font-size: var(--font-size-md);">
      Phase 1 Application Modules &amp; Navigation Baseline
    </h2>
    <span class="badge badge-success">Phase 1A Active</span>
  </div>

  <p class="text-secondary mb-4">
    The authentication and hierarchical role-based access control (RBAC) foundation is active. Subsequent business modules will be enabled in subsequent Phase 1 stages according to the approved architecture.
  </p>

  <div class="grid grid-cols-3 gap-4">
    <!-- Campaigns Module -->
    <a href="<?= e(url('/admin/campaigns')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), box-shadow var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span style="font-size: 1.5rem;" aria-hidden="true">&#127919;</span>
        <span class="badge badge-success">Phase 1B Active</span>
      </div>
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.35rem; color: var(--color-primary);">Campaign Management &rarr;</h3>
      <p class="text-caption text-secondary" style="margin-bottom: 0;">
        Annual campaign initiatives, themes, date ranges, and public catalog visibility.
      </p>
    </a>

    <!-- Events Module -->
    <a href="<?= e(url('/admin/events')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), box-shadow var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span style="font-size: 1.5rem;" aria-hidden="true">&#128197;</span>
        <span class="badge badge-success">Phase 1C Active</span>
      </div>
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.35rem; color: var(--color-primary);">Events &amp; Circles &rarr;</h3>
      <p class="text-caption text-secondary" style="margin-bottom: 0;">
        Workshops, listening circles, training sessions, venues, capacity controls, and approval gating.
      </p>
    </a>

    <!-- Participants Module -->
    <a href="<?= e(url('/admin/participants')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), box-shadow var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span style="font-size: 1.5rem;" aria-hidden="true">&#128100;</span>
        <span class="badge badge-success">Phase 1D Active</span>
      </div>
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.35rem; color: var(--color-primary);">Participant Directory &rarr;</h3>
      <p class="text-caption text-secondary" style="margin-bottom: 0;">
        Canonical attendee identities, stakeholder categories, server-side consent timestamps, and privacy masking.
      </p>
    </a>

    <!-- Registrations Module -->
    <a href="<?= e(url('/admin/registrations')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), box-shadow var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span style="font-size: 1.5rem;" aria-hidden="true">&#128101;</span>
        <span class="badge badge-success">Phase 1E Active</span>
      </div>
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.35rem; color: var(--color-primary);">Registrations &amp; Passes &rarr;</h3>
      <p class="text-caption text-secondary" style="margin-bottom: 0;">
        Participant deduplication, unique QR pass generation, waitlist management, and roster review.
      </p>
    </a>

    <!-- Check-In Module -->
    <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background-color: var(--bg-surface);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span style="font-size: 1.5rem;" aria-hidden="true">&#9989;</span>
        <span class="badge badge-neutral">Phase 1 Database Ready</span>
      </div>
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.35rem;">Desk &amp; QR Check-In</h3>
      <p class="text-caption text-secondary" style="margin-bottom: 0;">
        Rapid attendee check-in with camera QR scanning, manual lookup, and staff attribution.
      </p>
    </div>

    <!-- Certificates Module -->
    <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background-color: var(--bg-surface);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span style="font-size: 1.5rem;" aria-hidden="true">&#127891;</span>
        <span class="badge badge-neutral">Phase 1 Database Ready</span>
      </div>
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.35rem;">Digital Certificates</h3>
      <p class="text-caption text-secondary" style="margin-bottom: 0;">
        Single and batch certificate issuance for attended participants with 256-bit public verification.
      </p>
    </div>

    <!-- Security & Auditing Module -->
    <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background-color: var(--bg-surface);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span style="font-size: 1.5rem;" aria-hidden="true">&#128220;</span>
        <span class="badge badge-success">Active in Phase 1A</span>
      </div>
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.35rem;">Audit Trail &amp; Governance</h3>
      <p class="text-caption text-secondary" style="margin-bottom: 0;">
        Append-only forensic audit logging capturing all authentication events and system modifications.
      </p>
    </div>
  </div>
</div>
