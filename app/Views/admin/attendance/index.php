<?php
  $confirmed = (int) ($metrics['confirmed'] ?? 0);
  $attended = (int) ($metrics['attended'] ?? 0);
  $absent = (int) ($metrics['absent'] ?? 0);
  $excused = (int) ($metrics['excused'] ?? 0);
  $unmarked = (int) ($metrics['unmarked'] ?? 0);
  $turnout = (float) ($metrics['turnout_percentage'] ?? 0.0);
  $currentFilter = $filters['attendance_status'] ?? 'all';
?>

<!-- Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/events/' . $event['id'])) ?>" class="btn btn-outline btn-sm btn-icon" title="Event Details" aria-label="Event Details">
        <?= icon('arrow-left', ['width' => '14', 'height' => '14']) ?>
      </a>
      <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
          <h1 style="margin: 0; font-size: var(--font-size-xl);">Attendance &bull; <?= e($event['title']) ?></h1>
          <span class="badge badge-pill badge-neutral" style="text-transform: capitalize; font-size: var(--font-size-xs);">
            <?= e(str_replace('_', ' ', $event['format'] ?? 'in_person')) ?>
          </span>
        </div>
        <p style="margin-top: 0.25rem;">
          <?= e(date('M d, Y', strtotime((string) $event['start_time']))) ?> &bull;
          <?= e(date('h:i A', strtotime((string) $event['start_time']))) ?> &ndash; <?= e(date('h:i A', strtotime((string) $event['end_time']))) ?>
          <?php if (!empty($event['venue_name'])): ?>
            &bull; <?= e($event['venue_name']) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>

  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/checkin/event/' . $event['id'])) ?>" class="btn btn-primary btn-sm">
      <?= icon('camera', ['width' => '14', 'height' => '14']) ?>
      <span>Launch Scanner Console</span>
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance/export')) ?>" class="btn btn-outline btn-sm" title="Export Attendance CSV">
      <?= icon('download', ['width' => '14', 'height' => '14']) ?>
      <span>Export CSV</span>
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/certificates')) ?>" class="btn btn-outline btn-sm" title="Manage Event Certificates">
      <?= icon('award', ['width' => '14', 'height' => '14']) ?>
      <span>Certificates</span>
    </a>
  </div>
</div>

<!-- KPI Metrics Grid -->
<section class="metric-grid mb-6" aria-label="Attendance KPIs">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      <?= icon('users', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) $confirmed) ?></div>
    <div class="card-metric-label">Confirmed</div>
  </div>
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      <?= icon('check-circle', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value" style="color: var(--color-success);"><?= e((string) $attended) ?></div>
    <div class="card-metric-label">Attended</div>
  </div>
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      <?= icon('clock', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) $unmarked) ?></div>
    <div class="card-metric-label">Unmarked</div>
  </div>
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-danger); color: var(--text-danger);">
      <?= icon('x-circle', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value" style="color: var(--color-danger);"><?= e((string) $absent) ?></div>
    <div class="card-metric-label">Absent</div>
  </div>
</section>

<!-- Bulk Reconciliation Banner (Coordinator+) -->
<?php if ($isCoordinator && $unmarked > 0): ?>
  <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--bg-surface-subtle); border-left: 4px solid var(--color-warning);">
    <div>
      <h3 style="margin: 0 0 0.25rem 0; font-size: var(--font-size-md); display: flex; align-items: center; gap: 0.5rem;">
        <?= icon('alert-triangle', ['width' => '16', 'height' => '16']) ?>
        <span>Post-Event Attendance Reconciliation</span>
      </h3>
      <p class="text-secondary" style="margin: 0; font-size: var(--font-size-xs);">
        <?php if ($bulkAbsentUnlocked): ?>
          Operational window has closed. You may mark all <strong><?= e((string) $unmarked) ?></strong> remaining unverified confirmed attendees as absent.
        <?php else: ?>
          <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
            <?= icon('lock', ['width' => '12', 'height' => '12']) ?>
            <span>Bulk absent reconciliation is locked until <strong><?= e($bulkAbsentAvailableAt) ?></strong> (event end time + 4 hours).</span>
          </span>
        <?php endif; ?>
      </p>
    </div>

    <div>
      <?php if ($bulkAbsentUnlocked): ?>
        <form action="<?= e(url('/admin/events/' . $event['id'] . '/attendance/bulk-absent')) ?>" method="POST" onsubmit="return confirm('Are you sure you want to mark all <?= e((string) $unmarked) ?> remaining unverified attendees as absent?');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-warning btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
            <?= icon('alert-triangle', ['width' => '14', 'height' => '14']) ?>
            <span>Mark All Unmarked as Absent (<?= e((string) $unmarked) ?>)</span>
          </button>
        </form>
      <?php else: ?>
        <button type="button" class="btn btn-outline btn-sm" disabled title="Locked until end time + 4 hours" style="display: inline-flex; align-items: center; gap: 0.35rem;">
          <?= icon('lock', ['width' => '14', 'height' => '14']) ?>
          <span>Mark Absent (Locked)</span>
        </button>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Filter Tabs & Search -->
<div class="admin-filter-bar">
  <!-- Status Tabs -->
  <div class="filter-tabs">
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="filter-tab <?= $currentFilter === 'all' ? 'active' : '' ?>">
      All (<?= e((string) $confirmed) ?>)
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=attended')) ?>" class="filter-tab <?= $currentFilter === 'attended' ? 'active' : '' ?>">
      Attended (<?= e((string) $attended) ?>)
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=unmarked')) ?>" class="filter-tab <?= $currentFilter === 'unmarked' ? 'active' : '' ?>">
      Unmarked (<?= e((string) $unmarked) ?>)
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=absent')) ?>" class="filter-tab <?= $currentFilter === 'absent' ? 'active' : '' ?>">
      Absent (<?= e((string) $absent) ?>)
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=excused')) ?>" class="filter-tab <?= $currentFilter === 'excused' ? 'active' : '' ?>">
      Excused (<?= e((string) $excused) ?>)
    </a>
  </div>

  <!-- Search Form -->
  <form action="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
    <?php if ($currentFilter !== 'all'): ?>
      <input type="hidden" name="attendance_status" value="<?= e($currentFilter) ?>">
    <?php endif; ?>
    <div style="position: relative; display: flex; align-items: center;">
      <span style="position: absolute; left: 0.75rem; color: var(--text-muted); pointer-events: none; display: flex;">
        <?= icon('search', ['width' => '14', 'height' => '14']) ?>
      </span>
      <input type="text" name="search" class="form-input" placeholder="Search attendee, code, phone..." value="<?= e($filters['search'] ?? '') ?>" style="padding-left: 2.25rem; max-width: 260px; font-size: var(--font-size-xs);">
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    <?php if (!empty($filters['search'])): ?>
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance' . ($currentFilter !== 'all' ? '?attendance_status=' . $currentFilter : ''))) ?>" class="btn btn-outline btn-sm" title="Clear search">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Attendance Roster Table -->
<div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
  <div class="table-responsive">
    <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
      <thead>
        <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-surface-subtle); text-align: left; font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">
          <th style="padding: 0.75rem 1rem;">Attendee</th>
          <th style="padding: 0.75rem 1rem;">Pass Code</th>
          <th style="padding: 0.75rem 1rem;">Contact Info</th>
          <th style="padding: 0.75rem 1rem;">Attendance</th>
          <th style="padding: 0.75rem 1rem;">Check-in Verification</th>
          <?php if ($isCoordinator): ?>
            <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($roster)): ?>
          <tr>
            <td colspan="<?= $isCoordinator ? '6' : '5' ?>" style="padding: 0;">
              <div class="empty-state" style="padding: 3rem 1.5rem;">
                <div class="empty-state-icon"><?= icon('users', ['width' => '40', 'height' => '40']) ?></div>
                <h4 style="margin: 0.5rem 0 0.25rem 0; font-size: var(--font-size-base); font-weight: 600;">No Attendee Records Found</h4>
                <p style="margin: 0; font-size: var(--font-size-sm); color: var(--text-muted);">No attendees match the selected filter criteria.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($roster as $att): ?>
            <?php
              $attStatus = $att['attendance_status'] ?? 'unmarked';
              $badgeClass = match ($attStatus) {
                'attended' => 'badge-pill badge-success',
                'absent'   => 'badge-pill badge-danger',
                'excused'  => 'badge-pill badge-warning',
                default    => 'badge-pill badge-secondary',
              };
            ?>
            <tr style="border-bottom: 1px solid var(--border-color); font-size: var(--font-size-sm);">
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 600; color: var(--text-primary);"><?= e($att['participant_name']) ?></div>
                <div style="display: flex; align-items: center; gap: 0.35rem; margin-top: 0.2rem; font-size: 0.75rem;">
                  <span class="badge-pill badge-neutral" style="text-transform: capitalize; padding: 0.1rem 0.4rem; font-size: 0.65rem;">
                    <?= e($att['participant_category'] ?? 'general') ?>
                  </span>
                  <?php if (!empty($att['participant_organization'])): ?>
                    <span class="text-secondary">&bull; <?= e($att['participant_organization']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <code style="font-family: var(--font-mono); font-size: 0.8rem; font-weight: 600; padding: 0.2rem 0.4rem; background: var(--bg-surface-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: var(--primary);">
                  <?= e($att['registration_code']) ?>
                </code>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs);">
                <?php if (!empty($att['participant_email'])): ?>
                  <div style="color: var(--text-secondary);"><?= e($att['participant_email']) ?></div>
                <?php endif; ?>
                <?php if (!empty($att['participant_phone'])): ?>
                  <div class="text-secondary"><?= e($att['participant_phone']) ?></div>
                <?php endif; ?>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span class="<?= e($badgeClass) ?>" style="text-transform: uppercase; font-size: 0.65rem; font-weight: 600;">
                  <?= e($attStatus) ?>
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs);">
                <?php if ($attStatus === 'attended'): ?>
                  <div style="font-weight: 600; color: var(--text-primary);"><?= e(date('M d, h:i A', strtotime((string) $att['checked_in_at']))) ?></div>
                  <div class="text-secondary" style="font-size: 0.7rem;">
                    By <?= e($att['checked_in_by_name'] ?? 'Desk Staff') ?>
                    (<?= e($att['check_in_method'] === 'qr_scan' ? 'QR Scan' : 'Manual') ?>)
                  </div>
                <?php elseif (!empty($att['admin_notes'])): ?>
                  <span class="text-secondary">Note: <?= e($att['admin_notes']) ?></span>
                <?php else: ?>
                  <span class="text-secondary">&mdash;</span>
                <?php endif; ?>
              </td>
              <?php if ($isCoordinator): ?>
                <td style="padding: 0.75rem 1rem; text-align: right;">
                  <button type="button" class="btn btn-outline btn-sm" onclick="openStatusModal(<?= (int) $att['id'] ?>, '<?= e($att['participant_name']) ?>', '<?= e($attStatus) ?>')" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                    <?= icon('edit', ['width' => '12', 'height' => '12']) ?>
                    <span>Update</span>
                  </button>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination Controls -->
<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
  <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; font-size: var(--font-size-xs);">
    <span class="text-secondary">
      Showing page <?= e((string) $pagination['page']) ?> of <?= e((string) $pagination['total_pages']) ?> (<?= e((string) $pagination['total']) ?> records)
    </span>
    <div style="display: flex; gap: 0.5rem;">
      <?php if ($pagination['page'] > 1): ?>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?page=' . ($pagination['page'] - 1) . ($currentFilter !== 'all' ? '&attendance_status=' . $currentFilter : ''))) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
          <?= icon('chevron-left', ['width' => '12', 'height' => '12']) ?>
          <span>Previous</span>
        </a>
      <?php endif; ?>
      <?php if ($pagination['has_more']): ?>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?page=' . ($pagination['page'] + 1) . ($currentFilter !== 'all' ? '&attendance_status=' . $currentFilter : ''))) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
          <span>Next</span>
          <?= icon('chevron-right', ['width' => '12', 'height' => '12']) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- ATTENDANCE STATUS MODAL (Coordinator+)                                    -->
<!-- ========================================================================= -->
<div id="status-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 480px; width: 100%; padding: 1.5rem; background: var(--bg-surface); box-shadow: 0 10px 25px rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
      <h3 style="margin: 0; font-size: var(--font-size-base); font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;" id="modal-attendee-name">
        <?= icon('check-circle', ['width' => '16', 'height' => '16']) ?>
        <span>Update Attendance Status</span>
      </h3>
      <button type="button" onclick="closeStatusModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted);">&times;</button>
    </div>
    <form action="<?= e(url('/admin/events/' . $event['id'] . '/attendance/update')) ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="registration_id" id="modal-reg-id" value="">

      <div style="margin-bottom: 1rem;">
        <label for="modal-status-select" class="form-label" style="font-weight: 600; font-size: var(--font-size-xs); margin-bottom: 0.35rem;">New Attendance Status</label>
        <select name="attendance_status" id="modal-status-select" class="form-input" required>
          <option value="attended">Attended (Manual Check-In)</option>
          <option value="absent">Absent (No-Show)</option>
          <option value="excused">Excused (Documented Conflict)</option>
          <option value="unmarked">Unmarked (Reversal / Reset)</option>
        </select>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label for="modal-reason-input" class="form-label" style="font-weight: 600; font-size: var(--font-size-xs); margin-bottom: 0.35rem;">Operational Reason (Mandatory)</label>
        <input type="text" name="reason" id="modal-reason-input" class="form-input" placeholder="e.g. In-person arrival verified by coordinator" maxlength="255" required>
        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
          Clinical, medical, or counselling content is strictly prohibited.
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeStatusModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Status Change</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openStatusModal(regId, attendeeName, currentStatus) {
    document.getElementById('modal-reg-id').value = regId;
    document.getElementById('modal-attendee-name').innerHTML = 'Update Status &mdash; ' + attendeeName;
    document.getElementById('modal-status-select').value = currentStatus;
    document.getElementById('modal-reason-input').value = '';
    document.getElementById('status-modal').style.display = 'flex';
  }

  function closeStatusModal() {
    document.getElementById('status-modal').style.display = 'none';
  }
</script>
