<?php

declare(strict_types=1);

/**
 * Modernized LC-SPC V3 Certificate Platform Dashboard
 */

$userName = $user['name'] ?? 'Administrator';
$userEmail = $user['email'] ?? '';
$userRole = $roleLabel ?? 'Administrator';
$currentDate = date('l, d M Y');

$totalCerts = (int) ($metrics['total_certificates'] ?? 0);
$validCerts = (int) ($metrics['valid_certificates'] ?? 0);
$invalidCerts = (int) ($metrics['invalid_certificates'] ?? 0);
$templatesCount = (int) ($metrics['templates_count'] ?? 0);
$generatedToday = (int) ($metrics['generated_today'] ?? 0);
$recentBatches = (array) ($metrics['recent_batches'] ?? []);
?>

<!-- Welcome Banner -->
<div class="card mb-6" style="background: linear-gradient(135deg, #FFFFFF 0%, #FFF5F5 100%); border-left: 4px solid var(--color-primary);">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
      <div class="admin-avatar" style="width: 48px; height: 48px; font-size: var(--font-size-md);" aria-hidden="true">
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
        <span>System Operational</span>
      </a>
    </div>
  </div>
</div>

<!-- Primary KPI Metric Cards -->
<section class="metric-grid mb-6" aria-label="Certificate Platform KPIs">
  <!-- Total Certificates -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: rgba(191, 30, 46, 0.1); color: var(--color-primary);">
        <?= icon('award') ?>
      </div>
      <span class="badge-pill badge-pill-info"><?= e((string) $generatedToday) ?> Today</span>
    </div>
    <div class="metric-card-value"><?= number_format($totalCerts) ?></div>
    <div class="metric-card-label">Total Issued Certificates</div>
  </div>

  <!-- Valid Credentials -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #ECFDF5; color: #059669;">
        <?= icon('check-circle') ?>
      </div>
      <span class="badge-pill badge-pill-success">Active</span>
    </div>
    <div class="metric-card-value" style="color: #059669;"><?= number_format($validCerts) ?></div>
    <div class="metric-card-label">Publicly Verifiable Credentials</div>
  </div>

  <!-- Invalidated / Revoked -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #FEF2F2; color: #DC2626;">
        <?= icon('slash') ?>
      </div>
      <span class="badge-pill badge-pill-danger">Revoked</span>
    </div>
    <div class="metric-card-value" style="color: #DC2626;"><?= number_format($invalidCerts) ?></div>
    <div class="metric-card-label">Revoked Credentials</div>
  </div>

  <!-- Active Templates -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #EFF6FF; color: #2563EB;">
        <?= icon('file-text') ?>
      </div>
      <span class="badge-pill badge-pill-info">Templates</span>
    </div>
    <div class="metric-card-value"><?= number_format($templatesCount) ?></div>
    <div class="metric-card-label">Active Design Templates</div>
  </div>
</section>

<!-- Content Grid: Recent Batches & Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
  <!-- Recent Batches Table (2 cols) -->
  <div class="lg:col-span-2">
    <div class="card">
      <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin: 0;">
          Recent Generation Batches
        </h2>
        <a href="<?= e(url('/admin/certificates/generate')) ?>" class="text-secondary" style="font-size: var(--font-size-xs);">
          Generate New &rarr;
        </a>
      </div>
      <div class="card-body" style="padding: 0;">
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Batch Code</th>
                <th>Template</th>
                <th>Records</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentBatches)): ?>
                <tr>
                  <td colspan="5" class="text-muted" style="text-align: center; padding: 2.5rem 1rem;">
                    <?= icon('inbox') ?>
                    <p style="margin: 0.5rem 0 0 0; font-size: var(--font-size-xs);">No generation batches executed yet.</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentBatches as $batch): ?>
                  <tr>
                    <td>
                      <code style="font-size: 0.8rem;"><?= e($batch['batch_code']) ?></code>
                    </td>
                    <td>
                      <?= e($batch['template_name'] ?? 'Template #' . $batch['template_id']) ?>
                    </td>
                    <td>
                      <strong><?= e((string) $batch['generated_count']) ?></strong> / <?= e((string) $batch['valid_records']) ?>
                      <?php $invalidCount = (int) ($batch['invalid_records'] ?? $batch['failed_count'] ?? 0); ?>
                      <?php if ($invalidCount > 0): ?>
                        <span class="text-danger" style="font-size: 0.75rem;">(<?= e((string) $invalidCount) ?> err)</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($batch['status'] === 'completed'): ?>
                        <span class="badge badge-success">Completed</span>
                      <?php elseif ($batch['status'] === 'processing'): ?>
                        <span class="badge badge-info">Processing</span>
                      <?php else: ?>
                        <span class="badge badge-neutral"><?= e(ucfirst($batch['status'])) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted" style="font-size: 0.8rem;">
                      <?= e(date('M j, Y H:i', strtotime($batch['created_at']))) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Actions & Configuration (1 col) -->
  <div class="lg:col-span-1" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card">
      <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem;">
        <h3 style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); margin: 0;">
          Quick Actions
        </h3>
      </div>
      <div class="card-body" style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
        <a href="<?= e(url('/admin/certificates/generate')) ?>" class="btn btn-primary" style="justify-content: flex-start; padding: 0.75rem 1rem;">
          <?= icon('plus-circle') ?>
          <span>Generate Certificates</span>
        </a>
        <a href="<?= e(url('/admin/certificate-templates/create')) ?>" class="btn btn-outline" style="justify-content: flex-start; padding: 0.75rem 1rem;">
          <?= icon('file-text') ?>
          <span>New Certificate Template</span>
        </a>
        <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline" style="justify-content: flex-start; padding: 0.75rem 1rem;">
          <?= icon('list') ?>
          <span>Certificate Repository</span>
        </a>
        <a href="<?= e(url('/admin/certificate-settings')) ?>" class="btn btn-outline" style="justify-content: flex-start; padding: 0.75rem 1rem;">
          <?= icon('settings') ?>
          <span>Entropy &amp; Global Settings</span>
        </a>
      </div>
    </div>
  </div>
</div>

<?php 
$adminRoleSlug = (string) ($adminRoleSlug ?? ($user['role'] ?? ''));
if (\App\Services\RoleService::isSuperAdmin($adminRoleSlug)): 
?>
<!-- Recent System Activity Audit Trail -->
<div class="card mb-6">
  <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem;">
    <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin: 0;">
      Recent System Activity
    </h2>
  </div>
  <div class="card-body" style="padding: 0;">
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Entity</th>
            <th>Admin User</th>
            <th>Date &amp; Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentLogs)): ?>
            <tr>
              <td colspan="4" class="text-muted" style="text-align: center; padding: 2rem 1rem;">
                No recent activity logged.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentLogs as $log): ?>
              <tr>
                <td>
                  <code><?= e($log['action'] ?? '') ?></code>
                </td>
                <td>
                  <?= e($log['entity_type'] ?? '') ?> #<?= e((string) ($log['entity_id'] ?? '')) ?>
                </td>
                <td>
                  <?= e($log['user_name'] ?? ('User #' . ($log['user_id'] ?? ''))) ?>
                </td>
                <td class="text-muted" style="font-size: 0.8rem;">
                  <?= e(date('M j, Y H:i:s', strtotime($log['created_at'] ?? 'now'))) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
