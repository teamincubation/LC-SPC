<?php
  $activeStatus = $filters['status'] ?? 'all';
  $activeType = $filters['type'] ?? 'all';
?>

<!-- Certificate Directory Header -->
<div class="card mb-6">
  <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin: 0;">
        &#127891; Certificates Directory
      </h1>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin-top: 0.25rem;">
        Official verifiable credentials issued for verified event participation and service.
      </p>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-sm">
        <span>&#128197; Select Event to Issue</span>
      </a>
    </div>
  </div>
</div>

<!-- Metrics Overview Cards -->
<div class="grid grid-cols-4 gap-4 mb-6">
  <div class="card" style="padding: 1.25rem; text-align: center;">
    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 600;">Total Issued</div>
    <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-primary-dark); margin-top: 0.25rem;">
      <?= e((string) ($metrics['total'] ?? 0)) ?>
    </div>
  </div>

  <div class="card" style="padding: 1.25rem; text-align: center;">
    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 600;">Active Credentials</div>
    <div style="font-size: 1.8rem; font-weight: 700; color: #15803d; margin-top: 0.25rem;">
      <?= e((string) ($metrics['active_count'] ?? 0)) ?>
    </div>
  </div>

  <div class="card" style="padding: 1.25rem; text-align: center;">
    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 600;">Participation</div>
    <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
      <?= e((string) ($metrics['participation_count'] ?? 0)) ?>
    </div>
  </div>

  <div class="card" style="padding: 1.25rem; text-align: center;">
    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 600;">Revoked / Superseded</div>
    <div style="font-size: 1.8rem; font-weight: 700; color: #b91c1c; margin-top: 0.25rem;">
      <?= e((string) ($metrics['revoked_count'] ?? 0)) ?>
    </div>
  </div>
</div>

<!-- Filters & Search Toolbar -->
<div class="card mb-6">
  <div class="card-body" style="padding: 1rem 1.25rem;">
    <form action="<?= e(url('/admin/certificates')) ?>" method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 220px;">
        <label for="search" class="form-label" style="font-size: var(--font-size-xs);">Search Credential</label>
        <input type="text" name="search" id="search" class="form-control" placeholder="Certificate No, Recipient Name, Pass Code..." value="<?= e($filters['search'] ?? '') ?>">
      </div>

      <div style="min-width: 160px;">
        <label for="status" class="form-label" style="font-size: var(--font-size-xs);">Status</label>
        <select name="status" id="status" class="form-control">
          <option value="">All Statuses</option>
          <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="revoked" <?= ($filters['status'] ?? '') === 'revoked' ? 'selected' : '' ?>>Revoked</option>
        </select>
      </div>

      <div style="min-width: 180px;">
        <label for="type" class="form-label" style="font-size: var(--font-size-xs);">Type</label>
        <select name="type" id="type" class="form-control">
          <option value="">All Types</option>
          <option value="participation" <?= ($filters['type'] ?? '') === 'participation' ? 'selected' : '' ?>>Participation</option>
          <option value="volunteer" <?= ($filters['type'] ?? '') === 'volunteer' ? 'selected' : '' ?>>Volunteer</option>
          <option value="speaker" <?= ($filters['type'] ?? '') === 'speaker' ? 'selected' : '' ?>>Speaker / Facilitation</option>
          <option value="appreciation" <?= ($filters['type'] ?? '') === 'appreciation' ? 'selected' : '' ?>>Appreciation</option>
        </select>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Certificates Table -->
<div class="card">
  <div class="card-body" style="padding: 0;">
    <div class="table-responsive">
      <table class="table" style="margin: 0;">
        <thead>
          <tr>
            <th>Certificate No</th>
            <th>Recipient Legal Name</th>
            <th>Type</th>
            <th>Event Session</th>
            <th>Issue Date</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($certificates)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-secondary);">
                No certificates found matching your criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
              <?php
                $isRevoked = ($cert['status'] ?? '') === 'revoked';
                $statusBadgeClass = $isRevoked ? 'badge-danger' : 'badge-success';
                $typeBadgeClass = match ($cert['type'] ?? '') {
                  'volunteer'    => 'badge-info',
                  'speaker'      => 'badge-primary',
                  'appreciation' => 'badge-warning',
                  default        => 'badge-light',
                };
              ?>
              <tr>
                <td>
                  <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" style="font-weight: 600; font-family: monospace; color: var(--color-primary-dark);">
                    <?= e($cert['certificate_number']) ?>
                  </a>
                </td>
                <td>
                  <strong style="font-size: 0.95rem;"><?= e($cert['recipient_name_snapshot']) ?></strong>
                  <?php if (!empty($cert['registration_code'])): ?>
                    <div style="font-size: var(--font-size-xs); color: var(--text-secondary);">
                      Pass: <?= e($cert['registration_code']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= e($typeBadgeClass) ?>" style="font-size: 0.75rem; text-transform: capitalize;">
                    <?= e($cert['type']) ?>
                  </span>
                </td>
                <td>
                  <a href="<?= e(url('/admin/events/' . $cert['event_id'])) ?>" style="font-size: var(--font-size-sm); color: var(--text-primary);">
                    <?= e($cert['event_title']) ?>
                  </a>
                </td>
                <td>
                  <span style="font-size: var(--font-size-sm);"><?= e(date('M d, Y', strtotime((string) $cert['issue_date']))) ?></span>
                </td>
                <td>
                  <span class="badge <?= e($statusBadgeClass) ?>" style="font-size: 0.75rem; text-transform: uppercase;">
                    <?= e($cert['status']) ?>
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" class="btn btn-outline btn-sm" title="Inspect Credential">
                    View
                  </a>
                  <?php if (!$isRevoked && in_array($userRole, ['staff', 'coordinator', 'super_admin'], true)): ?>
                    <a href="<?= e(url('/admin/certificates/' . $cert['id'] . '/print')) ?>" target="_blank" class="btn btn-outline btn-sm" title="Print PDF Layout">
                      &#128424;
                    </a>
                    <a href="<?= e(url('/admin/certificates/' . $cert['id'] . '/jpg')) ?>" class="btn btn-outline btn-sm" title="Download High-Res JPG">
                      &#128190;
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
        <span class="text-caption text-secondary">
          Showing <?= e((string) count($certificates)) ?> of <?= e((string) $pagination['total']) ?> certificates
        </span>
        <div style="display: flex; gap: 0.5rem;">
          <?php if ($pagination['has_previous']): ?>
            <a href="<?= e(url('/admin/certificates?' . http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])))) ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
          <?php endif; ?>
          <span class="btn btn-outline btn-sm" style="pointer-events: none;">
            Page <?= e((string) $pagination['page']) ?> of <?= e((string) $pagination['total_pages']) ?>
          </span>
          <?php if ($pagination['has_next']): ?>
            <a href="<?= e(url('/admin/certificates?' . http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])))) ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
