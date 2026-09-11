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
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
      <a href="<?= e(url('/admin/events/' . $event['id'])) ?>" class="btn btn-outline btn-sm">&larr; Event Details</a>
      <span class="badge badge-light" style="text-transform: capitalize; font-size: var(--font-size-xs);">
        <?= e($event['format'] ?? 'in_person') ?>
      </span>
    </div>
    <h1 style="margin: 0; font-size: var(--font-size-2xl);">
      Attendance &bull; <?= e($event['title']) ?>
    </h1>
    <div class="text-secondary" style="font-size: var(--font-size-sm); margin-top: 0.25rem;">
      <?= e(date('M d, Y', strtotime((string) $event['start_time']))) ?> &bull;
      <?= e(date('h:i A', strtotime((string) $event['start_time']))) ?> &ndash; <?= e(date('h:i A', strtotime((string) $event['end_time']))) ?>
      <?php if (!empty($event['venue_name'])): ?>
        &bull; <?= e($event['venue_name']) ?>
      <?php endif; ?>
    </div>
  </div>

  <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
    <a href="<?= e(url('/admin/checkin/event/' . $event['id'])) ?>" class="btn btn-primary btn-sm">
      <span>&#128247; Launch Scanner Console</span>
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance/export')) ?>" class="btn btn-outline btn-sm" title="Export Attendance CSV">
      <span>&#128196; Export CSV</span>
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/certificates')) ?>" class="btn btn-outline btn-sm" title="Manage Event Certificates">
      <span>&#127891; Certificates</span>
    </a>
  </div>
</div>

<!-- KPI Metrics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem;">
  <div class="card" style="padding: 1rem; text-align: center;">
    <div class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Confirmed</div>
    <div style="font-size: var(--font-size-2xl); font-weight: bold;"><?= e((string) $confirmed) ?></div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center; border-bottom: 3px solid var(--color-success);">
    <div class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Attended</div>
    <div style="font-size: var(--font-size-2xl); font-weight: bold; color: var(--color-success);"><?= e((string) $attended) ?></div>
    <div class="text-secondary" style="font-size: 0.7rem; margin-top: 0.2rem;">
      QR: <?= e((string) ($methodCounts['qr_scan'] ?? 0)) ?> &bull; Manual: <?= e((string) ($methodCounts['admin_manual'] ?? 0)) ?>
    </div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center;">
    <div class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Unmarked</div>
    <div style="font-size: var(--font-size-2xl); font-weight: bold; color: var(--text-secondary);"><?= e((string) $unmarked) ?></div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center; border-bottom: 3px solid var(--color-danger);">
    <div class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Absent</div>
    <div style="font-size: var(--font-size-2xl); font-weight: bold; color: var(--color-danger);"><?= e((string) $absent) ?></div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center; border-bottom: 3px solid var(--color-warning);">
    <div class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Excused</div>
    <div style="font-size: var(--font-size-2xl); font-weight: bold; color: var(--color-warning);"><?= e((string) $excused) ?></div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center; border-bottom: 3px solid var(--color-primary);">
    <div class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Turnout</div>
    <div style="font-size: var(--font-size-2xl); font-weight: bold; color: var(--color-primary);"><?= e((string) $turnout) ?>%</div>
  </div>
</div>

<!-- Bulk Reconciliation Banner (Coordinator+) -->
<?php if ($isCoordinator && $unmarked > 0): ?>
  <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--bg-surface-subtle); border-left: 4px solid var(--color-warning);">
    <div>
      <h3 style="margin: 0 0 0.25rem 0; font-size: var(--font-size-md);">Post-Event Attendance Reconciliation</h3>
      <p class="text-secondary" style="margin: 0; font-size: var(--font-size-xs);">
        <?php if ($bulkAbsentUnlocked): ?>
          Operational window has closed. You may mark all <strong><?= e((string) $unmarked) ?></strong> remaining unverified confirmed attendees as absent.
        <?php else: ?>
          <span>&#128274; Bulk absent reconciliation is locked until <strong><?= e($bulkAbsentAvailableAt) ?></strong> (event end time + 4 hours).</span>
        <?php endif; ?>
      </p>
    </div>

    <div>
      <?php if ($bulkAbsentUnlocked): ?>
        <form action="<?= e(url('/admin/events/' . $event['id'] . '/attendance/bulk-absent')) ?>" method="POST" onsubmit="return confirm('Are you sure you want to mark all <?= e((string) $unmarked) ?> remaining unverified attendees as absent?');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-warning btn-sm">
            <span>&#9888; Mark All Unmarked as Absent (<?= e((string) $unmarked) ?>)</span>
          </button>
        </form>
      <?php else: ?>
        <button type="button" class="btn btn-outline btn-sm" disabled title="Locked until end time + 4 hours">
          <span>&#128274; Mark Absent (Locked)</span>
        </button>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Filter Tabs & Search -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <!-- Status Tabs -->
    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="btn <?= $currentFilter === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        All (<?= e((string) $confirmed) ?>)
      </a>
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=attended')) ?>" class="btn <?= $currentFilter === 'attended' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        Attended (<?= e((string) $attended) ?>)
      </a>
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=unmarked')) ?>" class="btn <?= $currentFilter === 'unmarked' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        Unmarked (<?= e((string) $unmarked) ?>)
      </a>
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=absent')) ?>" class="btn <?= $currentFilter === 'absent' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        Absent (<?= e((string) $absent) ?>)
      </a>
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?attendance_status=excused')) ?>" class="btn <?= $currentFilter === 'excused' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        Excused (<?= e((string) $excused) ?>)
      </a>
    </div>

    <!-- Search Form -->
    <form action="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" method="GET" style="display: flex; gap: 0.5rem;">
      <?php if ($currentFilter !== 'all'): ?>
        <input type="hidden" name="attendance_status" value="<?= e($currentFilter) ?>">
      <?php endif; ?>
      <input type="text" name="search" class="form-input" placeholder="Search by name, code, phone..." value="<?= e($filters['search'] ?? '') ?>" style="max-width: 240px; font-size: var(--font-size-xs);">
      <button type="submit" class="btn btn-outline btn-sm">Search</button>
      <?php if (!empty($filters['search'])): ?>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance' . ($currentFilter !== 'all' ? '?attendance_status=' . $currentFilter : ''))) ?>" class="btn btn-outline btn-sm">&times;</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Attendance Roster Table -->
<div class="card" style="padding: 0; overflow-x: auto;">
  <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
    <thead>
      <tr style="border-bottom: 2px solid var(--border-color); background: var(--bg-surface-subtle); text-align: left; font-size: var(--font-size-xs); text-transform: uppercase;">
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
          <td colspan="<?= $isCoordinator ? '6' : '5' ?>" style="text-align: center; padding: 2.5rem; color: var(--text-secondary);">
            No attendee records found matching the selected filter.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($roster as $att): ?>
          <?php
            $attStatus = $att['attendance_status'] ?? 'unmarked';
            $badgeClass = match ($attStatus) {
              'attended' => 'badge-success',
              'absent'   => 'badge-danger',
              'excused'  => 'badge-warning',
              default    => 'badge-secondary',
            };
          ?>
          <tr style="border-bottom: 1px solid var(--border-color); font-size: var(--font-size-sm);">
            <td style="padding: 0.75rem 1rem;">
              <strong><?= e($att['participant_name']) ?></strong><br>
              <span class="badge badge-light" style="font-size: 0.7rem; text-transform: capitalize;">
                <?= e($att['participant_category'] ?? 'general') ?>
              </span>
              <?php if (!empty($att['participant_organization'])): ?>
                <span class="text-secondary" style="font-size: 0.75rem;">&bull; <?= e($att['participant_organization']) ?></span>
              <?php endif; ?>
            </td>
            <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: var(--font-size-xs); font-weight: bold;">
              <?= e($att['registration_code']) ?>
            </td>
            <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs);">
              <?php if (!empty($att['participant_email'])): ?>
                <div><?= e($att['participant_email']) ?></div>
              <?php endif; ?>
              <?php if (!empty($att['participant_phone'])): ?>
                <div class="text-secondary"><?= e($att['participant_phone']) ?></div>
              <?php endif; ?>
            </td>
            <td style="padding: 0.75rem 1rem;">
              <span class="badge <?= e($badgeClass) ?>" style="text-transform: uppercase; font-size: 0.75rem;">
                <?= e($attStatus) ?>
              </span>
            </td>
            <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs);">
              <?php if ($attStatus === 'attended'): ?>
                <strong><?= e(date('M d, h:i A', strtotime((string) $att['checked_in_at']))) ?></strong><br>
                <span class="text-secondary">
                  By: <?= e($att['checked_in_by_name'] ?? 'Desk Staff') ?>
                  (<?= e($att['check_in_method'] === 'qr_scan' ? 'QR Scan' : 'Manual') ?>)
                </span>
              <?php elseif (!empty($att['admin_notes'])): ?>
                <span class="text-secondary">Note: <?= e($att['admin_notes']) ?></span>
              <?php else: ?>
                <span class="text-secondary">&mdash;</span>
              <?php endif; ?>
            </td>
            <?php if ($isCoordinator): ?>
              <td style="padding: 0.75rem 1rem; text-align: right;">
                <button type="button" class="btn btn-outline btn-sm" onclick="openStatusModal(<?= (int) $att['id'] ?>, '<?= e($att['participant_name']) ?>', '<?= e($attStatus) ?>')">
                  Update &hellip;
                </button>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination Controls -->
<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
  <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; font-size: var(--font-size-xs);">
    <span class="text-secondary">
      Showing page <?= e((string) $pagination['page']) ?> of <?= e((string) $pagination['total_pages']) ?> (<?= e((string) $pagination['total']) ?> records)
    </span>
    <div style="display: flex; gap: 0.5rem;">
      <?php if ($pagination['page'] > 1): ?>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?page=' . ($pagination['page'] - 1) . ($currentFilter !== 'all' ? '&attendance_status=' . $currentFilter : ''))) ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
      <?php endif; ?>
      <?php if ($pagination['has_more']): ?>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance?page=' . ($pagination['page'] + 1) . ($currentFilter !== 'all' ? '&attendance_status=' . $currentFilter : ''))) ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- ATTENDANCE STATUS MODAL (Coordinator+)                                    -->
<!-- ========================================================================= -->
<div id="status-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 480px; width: 100%; padding: 1.5rem; background: var(--bg-surface); box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
    <h3 style="margin-top: 0; font-size: var(--font-size-lg);" id="modal-attendee-name">Update Attendance Status</h3>
    <form action="<?= e(url('/admin/events/' . $event['id'] . '/attendance/update')) ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="registration_id" id="modal-reg-id" value="">

      <div style="margin-bottom: 1rem;">
        <label for="modal-status-select" class="form-label">New Attendance Status</label>
        <select name="attendance_status" id="modal-status-select" class="form-input" required>
          <option value="attended">&#9989; Attended (Manual Check-In)</option>
          <option value="absent">&#10060; Absent (No-Show)</option>
          <option value="excused">&#9888; Excused (Documented Conflict)</option>
          <option value="unmarked">&#8635; Unmarked (Reversal / Reset)</option>
        </select>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label for="modal-reason-input" class="form-label">Operational Reason (Mandatory)</label>
        <input type="text" name="reason" id="modal-reason-input" class="form-input" placeholder="e.g. In-person arrival verified by coordinator" maxlength="255" required>
        <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.25rem;">
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
    document.getElementById('modal-attendee-name').innerText = 'Update Status — ' + attendeeName;
    document.getElementById('modal-status-select').value = currentStatus;
    document.getElementById('modal-reason-input').value = '';
    document.getElementById('status-modal').style.display = 'flex';
  }

  function closeStatusModal() {
    document.getElementById('status-modal').style.display = 'none';
  }
</script>
