<?php
  $activeStatus = $filters['status'] ?? 'all';
  $activeType = $filters['type'] ?? 'all';
?>

<!-- Certificate Directory Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <h1>
      <?= icon('award', ['width' => '24', 'height' => '24']) ?>
      <span>Certificates Directory</span>
    </h1>
    <p>Official verifiable credentials issued for verified event participation and service.</p>
  </div>

  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/events')) ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
      <?= icon('calendar', ['width' => '14', 'height' => '14']) ?>
      <span>Select Event to Issue</span>
    </a>
  </div>
</div>

<!-- Metrics Overview Cards -->
<div class="metric-grid mb-6">
  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Total Issued</span>
      <div class="card-metric-icon" style="background: rgba(26, 86, 219, 0.1); color: var(--primary);">
        <?= icon('award', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value"><?= e((string) ($metrics['total'] ?? 0)) ?></div>
    <div class="card-metric-subtitle">All generated credentials</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Active Credentials</span>
      <div class="card-metric-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
        <?= icon('check-circle', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value" style="color: var(--success);"><?= e((string) ($metrics['active_count'] ?? 0)) ?></div>
    <div class="card-metric-subtitle">Valid &amp; publicly verifiable</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Participation</span>
      <div class="card-metric-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--primary-light);">
        <?= icon('users', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value"><?= e((string) ($metrics['participation_count'] ?? 0)) ?></div>
    <div class="card-metric-subtitle">Standard attendee awards</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Revoked / Void</span>
      <div class="card-metric-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
        <?= icon('x-circle', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value" style="color: var(--danger);"><?= e((string) ($metrics['revoked_count'] ?? 0)) ?></div>
    <div class="card-metric-subtitle">Superseded or invalidated</div>
  </div>
</div>

<!-- Filters & Search Toolbar -->
<div class="admin-filter-bar">
  <form action="<?= e(url('/admin/certificates')) ?>" method="GET" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; width: 100%;">
    <div style="flex: 1; min-width: 240px; position: relative; display: flex; align-items: center;">
      <span style="position: absolute; left: 0.75rem; color: var(--text-muted); pointer-events: none; display: flex;">
        <?= icon('search', ['width' => '14', 'height' => '14']) ?>
      </span>
      <input type="text" name="search" id="search" class="form-input" placeholder="Search Certificate No, Recipient, Pass Code..." value="<?= e($filters['search'] ?? '') ?>" style="padding-left: 2.25rem; font-size: var(--font-size-xs); width: 100%;">
    </div>

    <div style="min-width: 150px;">
      <select name="status" id="status" class="form-input" style="font-size: var(--font-size-xs);">
        <option value="">All Statuses</option>
        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="revoked" <?= ($filters['status'] ?? '') === 'revoked' ? 'selected' : '' ?>>Revoked</option>
      </select>
    </div>

    <div style="min-width: 160px;">
      <select name="type" id="type" class="form-input" style="font-size: var(--font-size-xs);">
        <option value="">All Types</option>
        <option value="participation" <?= ($filters['type'] ?? '') === 'participation' ? 'selected' : '' ?>>Participation</option>
        <option value="volunteer" <?= ($filters['type'] ?? '') === 'volunteer' ? 'selected' : '' ?>>Volunteer</option>
        <option value="speaker" <?= ($filters['type'] ?? '') === 'speaker' ? 'selected' : '' ?>>Speaker / Facilitation</option>
        <option value="appreciation" <?= ($filters['type'] ?? '') === 'appreciation' ? 'selected' : '' ?>>Appreciation</option>
      </select>
    </div>

    <div style="display: flex; gap: 0.5rem;">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['type'])): ?>
        <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline btn-sm">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Certificates Table -->
<div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
  <div class="table-responsive">
    <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
      <thead>
        <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-surface-subtle); text-align: left; font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">
          <th style="padding: 0.75rem 1rem;">Certificate No</th>
          <th style="padding: 0.75rem 1rem;">Recipient Legal Name</th>
          <th style="padding: 0.75rem 1rem;">Type</th>
          <th style="padding: 0.75rem 1rem;">Event Session</th>
          <th style="padding: 0.75rem 1rem;">Issue Date</th>
          <th style="padding: 0.75rem 1rem;">Status</th>
          <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($certificates)): ?>
          <tr>
            <td colspan="7" style="padding: 0;">
              <div class="empty-state" style="padding: 3rem 1.5rem;">
                <div class="empty-state-icon"><?= icon('award', ['width' => '40', 'height' => '40']) ?></div>
                <h4 style="margin: 0.5rem 0 0.25rem 0; font-size: var(--font-size-base); font-weight: 600;">No Certificates Found</h4>
                <p style="margin: 0; font-size: var(--font-size-sm); color: var(--text-muted);">No certificate records match your search or filter criteria.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($certificates as $cert): ?>
            <?php
              $isRevoked = ($cert['status'] ?? '') === 'revoked';
              $statusBadgeClass = $isRevoked ? 'badge-pill badge-danger' : 'badge-pill badge-success';
              $typeBadgeClass = match ($cert['type'] ?? '') {
                'volunteer'    => 'badge-pill badge-info',
                'speaker'      => 'badge-pill badge-primary',
                'appreciation' => 'badge-pill badge-warning',
                default        => 'badge-pill badge-neutral',
              };
            ?>
            <tr style="border-bottom: 1px solid var(--border-color); font-size: var(--font-size-sm);">
              <td style="padding: 0.75rem 1rem;">
                <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" style="font-weight: 600; font-family: var(--font-mono); color: var(--primary); font-size: 0.8rem;">
                  <?= e($cert['certificate_number']) ?>
                </a>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 600; color: var(--text-primary);"><?= e($cert['recipient_name_snapshot']) ?></div>
                <?php if (!empty($cert['registration_code'])): ?>
                  <div style="font-size: var(--font-size-xs); color: var(--text-muted); font-family: var(--font-mono);">
                    Pass: <?= e($cert['registration_code']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span class="<?= e($typeBadgeClass) ?>" style="font-size: 0.65rem; text-transform: capitalize; font-weight: 600;">
                  <?= e($cert['type']) ?>
                </span>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <a href="<?= e(url('/admin/events/' . $cert['event_id'])) ?>" style="font-size: var(--font-size-sm); color: var(--text-primary); font-weight: 500;">
                  <?= e($cert['event_title']) ?>
                </a>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs); color: var(--text-secondary);">
                <?= e(date('M d, Y', strtotime((string) $cert['issue_date']))) ?>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span class="<?= e($statusBadgeClass) ?>" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
                  <?= e($cert['status']) ?>
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                  <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" class="btn btn-outline btn-sm" title="Inspect Credential">
                    View
                  </a>
                  <?php if (!$isRevoked && in_array($userRole, ['staff', 'coordinator', 'super_admin'], true)): ?>
                    <a href="<?= e(url('/admin/certificates/' . $cert['id'] . '/print')) ?>" target="_blank" class="btn btn-outline btn-sm" title="Print PDF Layout" style="padding: 0.35rem 0.5rem; display: inline-flex; align-items: center;">
                      <?= icon('printer', ['width' => '13', 'height' => '13']) ?>
                    </a>
                    <a href="<?= e(url('/admin/certificates/' . $cert['id'] . '/jpg')) ?>" class="btn btn-outline btn-sm" title="Download High-Res JPG" style="padding: 0.35rem 0.5rem; display: inline-flex; align-items: center;">
                      <?= icon('download', ['width' => '13', 'height' => '13']) ?>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; border-top: 1px solid var(--border-color); font-size: var(--font-size-xs);">
      <span class="text-secondary">
        Showing <?= e((string) count($certificates)) ?> of <?= e((string) $pagination['total']) ?> certificates
      </span>
      <div style="display: flex; gap: 0.5rem; align-items: center;">
        <?php if ($pagination['has_previous']): ?>
          <a href="<?= e(url('/admin/certificates?' . http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])))) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
            <?= icon('chevron-left', ['width' => '12', 'height' => '12']) ?>
            <span>Previous</span>
          </a>
        <?php endif; ?>
        <span class="text-secondary" style="font-weight: 500;">
          Page <?= e((string) $pagination['page']) ?> of <?= e((string) $pagination['total_pages']) ?>
        </span>
        <?php if ($pagination['has_next']): ?>
          <a href="<?= e(url('/admin/certificates?' . http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])))) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
            <span>Next</span>
            <?= icon('chevron-right', ['width' => '12', 'height' => '12']) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
