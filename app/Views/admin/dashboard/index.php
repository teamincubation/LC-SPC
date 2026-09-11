<?php

declare(strict_types=1);

/**
 * Modernized LC-SPC Administrative Dashboard
 * Aligned to 12-Panel Design System Reference (Panel 2: Dashboard).
 */

$userName = $user['name'] ?? 'Administrator';
$userEmail = $user['email'] ?? '';
$userRole = $roleLabel ?? 'Administrator';
$currentDate = date('l, d M Y');

$totalCampaigns = (int) ($campCounts['total'] ?? array_sum($campCounts));
$activeCampaigns = (int) ($campCounts['active'] ?? 0);

$totalEvents = (int) ($eventCounts['total'] ?? array_sum($eventCounts));
$upcomingEvents = (int) (($eventCounts['published'] ?? 0) + ($eventCounts['ongoing'] ?? 0));

$totalParticipants = (int) ($partCounts['total'] ?? array_sum($partCounts));
$activeParticipants = (int) ($partCounts['active'] ?? 0);

$totalRegistrations = (int) ($regCounts['total'] ?? array_sum($regCounts));
$confirmedRegistrations = (int) ($regCounts['confirmed'] ?? 0);
?>

<!-- Welcome Banner -->
<div class="card mb-6" style="background: linear-gradient(135deg, #FFFFFF 0%, #FFF5F5 100%); border-left: 4px solid var(--color-primary);">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 1rem;">
      <div class="admin-avatar" style="width: 46px; height: 46px; font-size: var(--font-size-md);" aria-hidden="true">
        <?= e(strtoupper(substr(trim((string) $userName), 0, 2) ?: 'LC')) ?>
      </div>
      <div>
        <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.2rem; color: var(--text-primary);">
          Welcome, <?= e($userName) ?>
        </h1>
        <p class="text-secondary" style="font-size: var(--font-size-xs); margin: 0;">
          <span><?= e($userRole) ?></span>
          <?php if (!empty($userEmail)): ?>
            &bull; <span class="text-muted"><?= e($userEmail) ?></span>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <span class="badge badge-neutral" style="font-size: var(--font-size-xs);">
        <?= icon('clock', ['class' => 'svg-icon-xs text-muted']) ?>
        <span><?= e($currentDate) ?></span>
      </span>
      <a href="<?= e(url('/health')) ?>" class="badge badge-success" style="font-size: var(--font-size-xs); text-decoration: none;">
        <span class="badge-dot" aria-hidden="true"></span>
        <span>Live Health</span>
      </a>
    </div>
  </div>
</div>

<!-- KPI Summary Cards (4 Columns on Desktop -> 2 on Tablet -> 1 on Mobile) -->
<section class="metric-grid mb-6" aria-label="Program Key Performance Indicators">
  <!-- Campaigns KPI -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #ECFDF5; color: #059669;">
        <?= icon('target') ?>
      </div>
      <span class="badge-pill badge-pill-success"><?= e((string) $activeCampaigns) ?> Active</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) $totalCampaigns) ?></div>
      <div class="metric-card-label">Campaigns</div>
      <div class="metric-card-trend text-muted">
        <?= icon('trending-up', ['class' => 'svg-icon-xs text-success']) ?>
        <span>Multi-year initiatives</span>
      </div>
    </div>
  </div>

  <!-- Events KPI -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #EFF6FF; color: #2563EB;">
        <?= icon('calendar') ?>
      </div>
      <span class="badge-pill badge-pill-info"><?= e((string) $upcomingEvents) ?> Active</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) $totalEvents) ?></div>
      <div class="metric-card-label">Events &amp; Circles</div>
      <div class="metric-card-trend text-muted">
        <?= icon('clock', ['class' => 'svg-icon-xs text-info']) ?>
        <span>Workshops &amp; sessions</span>
      </div>
    </div>
  </div>

  <!-- Participants KPI -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #FEF3C7; color: #D97706;">
        <?= icon('users') ?>
      </div>
      <span class="badge-pill badge-pill-warning"><?= e((string) $activeParticipants) ?> Active</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) $totalParticipants) ?></div>
      <div class="metric-card-label">Participants</div>
      <div class="metric-card-trend text-muted">
        <?= icon('shield', ['class' => 'svg-icon-xs text-warning']) ?>
        <span>Privacy-aware directory</span>
      </div>
    </div>
  </div>

  <!-- Registrations KPI -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #FEE2E2; color: #BF1E2E;">
        <?= icon('clipboard-list') ?>
      </div>
      <span class="badge-pill badge-pill-danger"><?= e((string) $confirmedRegistrations) ?> Confirmed</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) $totalRegistrations) ?></div>
      <div class="metric-card-label">Registrations</div>
      <div class="metric-card-trend text-muted">
        <?= icon('scan-line', ['class' => 'svg-icon-xs text-danger']) ?>
        <span>Passes &amp; enrollments</span>
      </div>
    </div>
  </div>
</section>

<!-- Quick Actions Toolbar -->
<div class="card mb-6">
  <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1rem;">
    <h2 class="card-title" style="font-size: var(--font-size-sm); text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin: 0;">
      Quick Actions
    </h2>
  </div>
  <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
    <a href="<?= e(url('/admin/campaigns/create')) ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
      <?= icon('plus', ['class' => 'svg-icon-sm']) ?>
      <span>New Campaign</span>
    </a>
    <a href="<?= e(url('/admin/events/create')) ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
      <?= icon('calendar', ['class' => 'svg-icon-sm']) ?>
      <span>New Event</span>
    </a>
    <a href="<?= e(url('/admin/participants/create')) ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
      <?= icon('users', ['class' => 'svg-icon-sm']) ?>
      <span>Register Participant</span>
    </a>
    <a href="<?= e(url('/admin/reports')) ?>" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.4rem;">
      <?= icon('bar-chart', ['class' => 'svg-icon-sm']) ?>
      <span>View Reports</span>
    </a>
  </div>
</div>

<!-- Operational Split: Recent Activity & Campaign Reach -->
<div class="grid grid-cols-2 mb-6 gap-6">
  <!-- Recent Activity -->
  <div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
      <h2 class="card-title" style="font-size: var(--font-size-md); margin: 0;">
        Recent System Activity
      </h2>
      <span class="text-caption text-muted">Audit Log</span>
    </div>

    <?php if (empty($recentLogs)): ?>
      <div style="padding: 2rem 1rem; text-align: center; color: var(--text-muted); font-size: var(--font-size-sm);">
        <?= icon('info', ['class' => 'svg-icon-lg text-muted', 'style' => 'margin-bottom: 0.5rem; display: block; margin-left: auto; margin-right: auto;']) ?>
        No recent administrative activity recorded.
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($recentLogs as $log): ?>
          <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: flex-start; gap: 0.6rem;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background-color: var(--color-primary); margin-top: 6px; flex-shrink: 0;"></span>
              <div>
                <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold); color: var(--text-primary);">
                  <?= e(ucwords(str_replace(['.', '_'], ' ', (string) $log['action']))) ?>
                </div>
                <div class="text-caption text-secondary">
                  <?= e($log['actor_name'] ?? 'System') ?> &bull; <?= e(ucfirst((string) ($log['entity_type'] ?? 'entity'))) ?> #<?= e((string) ($log['entity_id'] ?? '')) ?>
                </div>
              </div>
            </div>
            <div class="text-caption text-muted" style="white-space: nowrap;">
              <?= !empty($log['created_at']) ? e(date('M d, H:i', strtotime((string) $log['created_at']))) : '' ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Campaign Reach & Security Hardening -->
  <div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
      <h2 class="card-title" style="font-size: var(--font-size-md); margin: 0;">
        Program Governance &amp; Security
      </h2>
      <span class="badge-pill badge-pill-success">Hardened</span>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: var(--bg-surface-subtle); border-radius: var(--radius-md);">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <?= icon('shield', ['class' => 'svg-icon text-success']) ?>
          <div>
            <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);">Authentication &amp; Session</div>
            <div class="text-caption text-muted">Argon2id &bull; HttpOnly &bull; SameSite=Lax</div>
          </div>
        </div>
        <span class="badge-pill badge-pill-success">Active</span>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: var(--bg-surface-subtle); border-radius: var(--radius-md);">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <?= icon('lock', ['class' => 'svg-icon text-info']) ?>
          <div>
            <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);">CSRF Protection</div>
            <div class="text-caption text-muted">Strict double-token header &amp; field verification</div>
          </div>
        </div>
        <span class="badge-pill badge-pill-info">Enforced</span>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: var(--bg-surface-subtle); border-radius: var(--radius-md);">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <?= icon('user', ['class' => 'svg-icon text-warning']) ?>
          <div>
            <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);">Role-Based Access Control</div>
            <div class="text-caption text-muted">Hierarchical RBAC: super_admin &rsaquo; coordinator &rsaquo; staff &rsaquo; viewer</div>
          </div>
        </div>
        <span class="badge-pill badge-pill-warning"><?= e($userRole) ?></span>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: var(--bg-surface-subtle); border-radius: var(--radius-md);">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <?= icon('bar-chart', ['class' => 'svg-icon text-danger']) ?>
          <div>
            <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);">Total Program Enrollees</div>
            <div class="text-caption text-muted">Active attendees across all campaigns</div>
          </div>
        </div>
        <span style="font-size: var(--font-size-sm); font-weight: var(--font-weight-bold); color: var(--color-primary);">
          <?= e((string) $totalParticipants) ?>
        </span>
      </div>
    </div>
  </div>
</div>

<!-- Module Navigation Overview Grid -->
<div class="card mb-6">
  <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1rem;">
    <h2 class="card-title" style="font-size: var(--font-size-md); margin: 0;">
      LC-SPC Program Modules
    </h2>
  </div>

  <div class="grid grid-cols-3 gap-4">
    <!-- Campaigns -->
    <a href="<?= e(url('/admin/campaigns')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), border-color var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <div style="width: 32px; height: 32px; border-radius: var(--radius-md); background-color: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center;">
          <?= icon('target') ?>
        </div>
        <span class="badge-pill badge-pill-neutral"><?= e((string) $totalCampaigns) ?> Total</span>
      </div>
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.25rem; color: var(--color-primary);">
        Campaign Management &rarr;
      </h3>
      <p class="text-caption text-secondary" style="margin: 0;">
        Multi-year initiatives, themes, timelines, and program governance.
      </p>
    </a>

    <!-- Events -->
    <a href="<?= e(url('/admin/events')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), border-color var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <div style="width: 32px; height: 32px; border-radius: var(--radius-md); background-color: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center;">
          <?= icon('calendar') ?>
        </div>
        <span class="badge-pill badge-pill-neutral"><?= e((string) $totalEvents) ?> Total</span>
      </div>
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.25rem; color: var(--color-primary);">
        Events &amp; Circles &rarr;
      </h3>
      <p class="text-caption text-secondary" style="margin: 0;">
        Workshops, listening circles, venues, capacity, and scheduling.
      </p>
    </a>

    <!-- Participants -->
    <a href="<?= e(url('/admin/participants')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), border-color var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <div style="width: 32px; height: 32px; border-radius: var(--radius-md); background-color: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center;">
          <?= icon('users') ?>
        </div>
        <span class="badge-pill badge-pill-neutral"><?= e((string) $totalParticipants) ?> Total</span>
      </div>
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.25rem; color: var(--color-primary);">
        Participant Directory &rarr;
      </h3>
      <p class="text-caption text-secondary" style="margin: 0;">
        Attendee identities, stakeholder categories, and privacy-safe data.
      </p>
    </a>

    <!-- Registrations -->
    <a href="<?= e(url('/admin/registrations')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), border-color var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <div style="width: 32px; height: 32px; border-radius: var(--radius-md); background-color: #FEE2E2; color: #BF1E2E; display: flex; align-items: center; justify-content: center;">
          <?= icon('clipboard-list') ?>
        </div>
        <span class="badge-pill badge-pill-neutral"><?= e((string) $totalRegistrations) ?> Total</span>
      </div>
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.25rem; color: var(--color-primary);">
        Registrations &amp; Passes &rarr;
      </h3>
      <p class="text-caption text-secondary" style="margin: 0;">
        Pass issuance, status workflow, waitlist promotions, and check-ins.
      </p>
    </a>

    <!-- Check-in -->
    <a href="<?= e(url('/admin/checkin')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), border-color var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <div style="width: 32px; height: 32px; border-radius: var(--radius-md); background-color: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center;">
          <?= icon('scan-line') ?>
        </div>
        <span class="badge-pill badge-pill-success">Live Check-in</span>
      </div>
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.25rem; color: var(--color-primary);">
        Event Check-In Hub &rarr;
      </h3>
      <p class="text-caption text-secondary" style="margin: 0;">
        Real-time QR barcode verification, manual override, and roster audits.
      </p>
    </a>

    <!-- Certificates -->
    <a href="<?= e(url('/admin/certificates')) ?>" style="text-decoration: none; color: inherit; display: block; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background-color: var(--bg-surface); transition: transform var(--transition-fast), border-color var(--transition-fast);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <div style="width: 32px; height: 32px; border-radius: var(--radius-md); background-color: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center;">
          <?= icon('award') ?>
        </div>
        <span class="badge-pill badge-pill-info">Verified</span>
      </div>
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.25rem; color: var(--color-primary);">
        Certificate Issuance &rarr;
      </h3>
      <p class="text-caption text-secondary" style="margin: 0;">
        Participant certificates, verification tokens, PDF/JPG downloads, and revocations.
      </p>
    </a>
  </div>
</div>
