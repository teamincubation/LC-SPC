<?php
  $activeType = $type ?? 'participation';
  $typeLabels = [
    'participation' => 'Participation',
    'volunteer'     => 'Volunteer Service',
    'speaker'       => 'Speaker / Facilitation',
    'appreciation'  => 'Appreciation',
  ];
?>

<!-- Event Certificate Hub Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <h1>
        <?= icon('award', ['width' => '24', 'height' => '24']) ?>
        <span>Certificates &mdash; <?= e($event['title']) ?></span>
      </h1>
      <span class="badge-pill badge-primary" style="text-transform: uppercase;">
        <?= e($event['status']) ?>
      </span>
    </div>
    <p>
      Event Start: <strong><?= e(date('M d, Y H:i', strtotime((string) $event['start_time']))) ?></strong>
      <?php if (!empty($event['venue_name'])): ?> &bull; <?= e($event['venue_name']) ?><?php endif; ?>
    </p>
  </div>

  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/events/' . $event['id'])) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
      <?= icon('chevron-left', ['width' => '14', 'height' => '14']) ?>
      <span>Back to Event</span>
    </a>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
      <?= icon('users', ['width' => '14', 'height' => '14']) ?>
      <span>Attendance Roster</span>
    </a>
  </div>
</div>

<!-- Event Metrics Summary -->
<div class="metric-grid mb-6">
  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Event Total Issued</span>
      <div class="card-metric-icon" style="background: rgba(26, 86, 219, 0.1); color: var(--primary);">
        <?= icon('award', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value"><?= e((string) ($metrics['total'] ?? 0)) ?></div>
    <div class="card-metric-subtitle">Generated for this event</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Active Credentials</span>
      <div class="card-metric-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
        <?= icon('check-circle', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value" style="color: var(--success);"><?= e((string) ($metrics['active_count'] ?? 0)) ?></div>
    <div class="card-metric-subtitle">Active &amp; valid</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Eligible Unissued</span>
      <div class="card-metric-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
        <?= icon('clock', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value" style="color: var(--warning);"><?= e((string) count(array_filter($candidates, fn($c) => empty($c['active_cert_id'])))) ?></div>
    <div class="card-metric-subtitle">Awaiting issuance</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Revoked / Void</span>
      <div class="card-metric-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
        <?= icon('x-circle', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value" style="color: var(--danger);"><?= e((string) ($metrics['revoked_count'] ?? 0)) ?></div>
    <div class="card-metric-subtitle">Invalidated credentials</div>
  </div>
</div>

<!-- Type Selector Navigation -->
<div class="admin-filter-bar" style="margin-bottom: 1.5rem;">
  <div class="filter-tabs">
    <?php foreach ($typeLabels as $slug => $label): ?>
      <?php $isActive = ($activeType === $slug); ?>
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/certificates?type=' . $slug)) ?>"
         class="filter-tab <?= $isActive ? 'active' : '' ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Issuance Eligibility Warning if Event Not Started -->
<?php if (!$isEventStarted): ?>
  <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: #92400e;">
    <span style="display: flex; color: var(--warning);">
      <?= icon('alert-triangle', ['width' => '20', 'height' => '20']) ?>
    </span>
    <div style="font-size: var(--font-size-sm);">
      <strong>Pre-Event Timeline Lock:</strong> Certificates cannot be issued before the event has started. Event starts on <strong><?= e(date('M d, Y H:i', strtotime((string) $event['start_time']))) ?></strong>.
    </div>
  </div>
<?php endif; ?>

<!-- Section 1: Eligible Candidates for Issuance -->
<div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
  <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--bg-surface);">
    <div>
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <span>Eligible Candidates &mdash; <?= e($typeLabels[$activeType] ?? $activeType) ?></span>
      </h2>
      <p style="font-size: var(--font-size-xs); color: var(--text-muted); margin: 0.2rem 0 0 0;">
        Confirmed attendees with verified presence. Flagged attendees are automatically excluded from bulk issuance and require individual confirmation.
      </p>
    </div>

    <?php if ($isCoordinator && $isEventStarted): ?>
      <button type="button" id="btnSubmitBulk" class="btn btn-primary btn-sm" onclick="submitBulkIssuance()" disabled style="display: inline-flex; align-items: center; gap: 0.35rem;">
        <?= icon('award', ['width' => '14', 'height' => '14']) ?>
        <span>Bulk Issue Selected (<span id="selectedCount">0</span>)</span>
      </button>
    <?php endif; ?>
  </div>

  <form id="bulkForm" action="<?= e(url('/admin/events/' . $event['id'] . '/certificates/bulk')) ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($activeType) ?>">

    <div class="table-responsive">
      <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-surface-subtle); text-align: left; font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">
            <?php if ($isCoordinator && $isEventStarted): ?>
              <th style="width: 44px; text-align: center; padding: 0.75rem 0.5rem;">
                <input type="checkbox" id="selectAllCandidates" onclick="toggleSelectAllCandidates(this)">
              </th>
            <?php endif; ?>
            <th style="padding: 0.75rem 1rem;">Attendee Name</th>
            <th style="padding: 0.75rem 1rem;">Pass Code</th>
            <th style="padding: 0.75rem 1rem;">Attendance Presence</th>
            <th style="padding: 0.75rem 1rem;">Participant Status</th>
            <th style="padding: 0.75rem 1rem;">Certificate Status</th>
            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $unissuedCandidates = array_filter($candidates, fn($c) => empty($c['active_cert_id']));
          ?>
          <?php if (empty($candidates)): ?>
            <tr>
              <td colspan="<?= ($isCoordinator && $isEventStarted) ? '7' : '6' ?>" style="padding: 0;">
                <div class="empty-state" style="padding: 3rem 1.5rem;">
                  <div class="empty-state-icon"><?= icon('users', ['width' => '40', 'height' => '40']) ?></div>
                  <h4 style="margin: 0.5rem 0 0.25rem 0; font-size: var(--font-size-base); font-weight: 600;">No Attendees Found</h4>
                  <p style="margin: 0; font-size: var(--font-size-sm); color: var(--text-muted);">No confirmed attended attendees found for this event.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($candidates as $cand): ?>
              <?php
                $hasActive = !empty($cand['active_cert_id']);
                $isFlagged = ($cand['participant_status'] === 'flagged');
                $canBulkSelect = (!$hasActive && !$isFlagged && $isEventStarted && $isCoordinator);
              ?>
              <tr style="border-bottom: 1px solid var(--border-color); font-size: var(--font-size-sm); <?= $hasActive ? 'background-color: var(--bg-surface-subtle); opacity: 0.85;' : '' ?>">
                <?php if ($isCoordinator && $isEventStarted): ?>
                  <td style="text-align: center; padding: 0.75rem 0.5rem;">
                    <?php if ($canBulkSelect): ?>
                      <input type="checkbox" name="registration_ids[]" value="<?= e((string) $cand['registration_id']) ?>" class="candidate-checkbox" onclick="updateCandidateSelectionCount()">
                    <?php else: ?>
                      <span style="color: var(--text-muted);">&ndash;</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td style="padding: 0.75rem 1rem;">
                  <div style="font-weight: 600; color: var(--text-primary);"><?= e($cand['participant_name']) ?></div>
                  <div style="font-size: var(--font-size-xs); color: var(--text-muted); text-transform: capitalize;">
                    <?= e($cand['participant_category'] ?? 'community') ?>
                  </div>
                </td>
                <td style="padding: 0.75rem 1rem;">
                  <code style="font-family: var(--font-mono); font-size: 0.8rem; font-weight: 600; padding: 0.2rem 0.4rem; background: var(--bg-surface-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: var(--primary);">
                    <?= e($cand['registration_code']) ?>
                  </code>
                </td>
                <td style="padding: 0.75rem 1rem;">
                  <span class="badge-pill badge-success" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
                    <?= e($cand['attendance_status']) ?>
                  </span>
                  <?php if (!empty($cand['checked_in_at'])): ?>
                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem;">
                      <?= e(date('H:i', strtotime((string) $cand['checked_in_at']))) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="padding: 0.75rem 1rem;">
                  <?php if ($isFlagged): ?>
                    <span class="badge-pill badge-warning" style="font-size: 0.65rem; text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.25rem;" title="Flagged: Excluded from bulk; individual verification required">
                      <?= icon('alert-triangle', ['width' => '10', 'height' => '10']) ?>
                      <span>Flagged</span>
                    </span>
                  <?php else: ?>
                    <span class="badge-pill badge-neutral" style="font-size: 0.65rem; text-transform: uppercase;">
                      <?= e($cand['participant_status']) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td style="padding: 0.75rem 1rem;">
                  <?php if ($hasActive): ?>
                    <span class="badge-pill badge-success" style="font-size: 0.65rem; display: inline-flex; align-items: center; gap: 0.25rem;">
                      <?= icon('check', ['width' => '10', 'height' => '10']) ?>
                      <span><?= e($cand['active_cert_number']) ?></span>
                    </span>
                  <?php else: ?>
                    <span class="badge-pill badge-secondary" style="font-size: 0.65rem;">Not Issued</span>
                  <?php endif; ?>
                </td>
                <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                  <?php if ($hasActive): ?>
                    <a href="<?= e(url('/admin/certificates/' . $cand['active_cert_id'])) ?>" class="btn btn-outline btn-sm">
                      View
                    </a>
                  <?php elseif ($isCoordinator && $isEventStarted): ?>
                    <?php if ($isFlagged): ?>
                      <button type="button" class="btn btn-warning btn-sm" onclick="openFlaggedModal(<?= e((string) $cand['registration_id']) ?>, '<?= e(addslashes($cand['participant_name'])) ?>')" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <?= icon('alert-triangle', ['width' => '12', 'height' => '12']) ?>
                        <span>Issue Flagged</span>
                      </button>
                    <?php else: ?>
                      <form action="<?= e(url('/admin/events/' . $event['id'] . '/certificates/issue')) ?>" method="POST" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="registration_id" value="<?= e((string) $cand['registration_id']) ?>">
                        <input type="hidden" name="type" value="<?= e($activeType) ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                          Issue
                        </button>
                      </form>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-secondary" style="font-size: var(--font-size-xs);">&ndash;</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<!-- Section 2: Issued Certificates List for this Event -->
<div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
  <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
    <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary);">
      Issued Certificates &mdash; <?= e($typeLabels[$activeType] ?? $activeType) ?>
    </h2>
    <span class="badge-pill badge-neutral"><?= e((string) $pagination['total']) ?> Total</span>
  </div>

  <div class="table-responsive">
    <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
      <thead>
        <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-surface-subtle); text-align: left; font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">
          <th style="padding: 0.75rem 1rem;">Certificate No</th>
          <th style="padding: 0.75rem 1rem;">Recipient Legal Name</th>
          <th style="padding: 0.75rem 1rem;">Issue Date</th>
          <th style="padding: 0.75rem 1rem;">Issued By</th>
          <th style="padding: 0.75rem 1rem;">Status</th>
          <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($certificates)): ?>
          <tr>
            <td colspan="6" style="padding: 0;">
              <div class="empty-state" style="padding: 2.5rem 1.5rem;">
                <div class="empty-state-icon"><?= icon('award', ['width' => '36', 'height' => '36']) ?></div>
                <h4 style="margin: 0.5rem 0 0.25rem 0; font-size: var(--font-size-base); font-weight: 600;">No Certificates Issued Yet</h4>
                <p style="margin: 0; font-size: var(--font-size-sm); color: var(--text-muted);">No <?= e($activeType) ?> certificates have been issued for this event yet.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($certificates as $cert): ?>
            <?php
              $isRevoked = ($cert['status'] ?? '') === 'revoked';
              $statusBadge = $isRevoked ? 'badge-pill badge-danger' : 'badge-pill badge-success';
            ?>
            <tr style="border-bottom: 1px solid var(--border-color); font-size: var(--font-size-sm);">
              <td style="padding: 0.75rem 1rem;">
                <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" style="font-family: var(--font-mono); font-weight: 600; color: var(--primary); font-size: 0.8rem;">
                  <?= e($cert['certificate_number']) ?>
                </a>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 600; color: var(--text-primary);"><?= e($cert['recipient_name_snapshot']) ?></div>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs); color: var(--text-secondary);">
                <?= e(date('M d, Y', strtotime((string) $cert['issue_date']))) ?>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs); color: var(--text-secondary);">
                <?= e($cert['issued_by_name'] ?? 'System') ?>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span class="<?= e($statusBadge) ?>" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
                  <?= e($cert['status']) ?>
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                  <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" class="btn btn-outline btn-sm">
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
</div>

<!-- Modal: Flagged Attendee Manual Issuance Confirmation -->
<div id="flaggedModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 540px; width: 100%; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); border-radius: var(--radius-lg); background: var(--bg-surface);">
    <div style="background-color: #fef3c7; border-bottom: 1px solid #fde68a; padding: 1.25rem; border-top-left-radius: var(--radius-lg); border-top-right-radius: var(--radius-lg);">
      <h3 style="margin: 0; font-size: 1rem; color: #92400e; display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
        <?= icon('alert-triangle', ['width' => '18', 'height' => '18']) ?>
        <span>FLAGGED ATTENDEE NOTICE</span>
      </h3>
    </div>
    <div style="padding: 1.25rem;">
      <p style="font-size: var(--font-size-sm); color: #78350f; margin: 0 0 1rem 0;">
        Participant <strong id="modalFlaggedName">Attendee</strong> is currently <strong>FLAGGED</strong> in the system. Review attendee identity before authorizing credential generation.
      </p>

      <form id="flaggedIssueForm" action="<?= e(url('/admin/events/' . $event['id'] . '/certificates/issue')) ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="registration_id" id="modalFlaggedRegId" value="">
        <input type="hidden" name="type" value="<?= e($activeType) ?>">

        <div style="margin-bottom: 1rem;">
          <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: var(--font-size-xs); font-weight: 500; cursor: pointer; color: var(--text-primary);">
            <input type="checkbox" name="confirm_flagged" value="1" required style="margin-top: 0.15rem;">
            <span>I have verified the attendee's identity and attendance, and officially authorize certificate issuance for this flagged record.</span>
          </label>
        </div>

        <div style="margin-bottom: 1.25rem;">
          <label for="flag_override_reason" class="form-label" style="font-size: var(--font-size-xs); font-weight: 600; margin-bottom: 0.35rem;">
            Operational Rationale Note <span class="text-danger">*</span>
          </label>
          <textarea name="flag_override_reason" id="flag_override_reason" class="form-input" rows="3" required placeholder="State operational logistics justification (e.g. Verified attendee photo ID at desk)..." minlength="10" maxlength="255"></textarea>
          <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            Strictly prohibited: Do NOT record clinical, medical, or counselling information.
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
          <button type="button" class="btn btn-outline btn-sm" onclick="closeFlaggedModal()">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm">Confirm &amp; Issue Certificate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleSelectAllCandidates(master) {
  const checkboxes = document.querySelectorAll('.candidate-checkbox');
  checkboxes.forEach(cb => cb.checked = master.checked);
  updateCandidateSelectionCount();
}

function updateCandidateSelectionCount() {
  const checked = document.querySelectorAll('.candidate-checkbox:checked');
  const count = checked.length;
  const countSpan = document.getElementById('selectedCount');
  const bulkBtn = document.getElementById('btnSubmitBulk');
  if (countSpan) countSpan.textContent = count;
  if (bulkBtn) bulkBtn.disabled = (count === 0);
}

function submitBulkIssuance() {
  const checked = document.querySelectorAll('.candidate-checkbox:checked');
  if (checked.length === 0) {
    alert('Please select at least one candidate for bulk issuance.');
    return;
  }
  if (checked.length > 100) {
    alert('Maximum 100 certificates can be issued in a single bulk batch.');
    return;
  }
  if (confirm(`Authorize certificate issuance for ${checked.length} selected attendee(s)?`)) {
    document.getElementById('bulkForm').submit();
  }
}

function openFlaggedModal(regId, name) {
  document.getElementById('modalFlaggedRegId').value = regId;
  document.getElementById('modalFlaggedName').textContent = name;
  document.getElementById('flaggedModal').style.display = 'flex';
}

function closeFlaggedModal() {
  document.getElementById('flaggedModal').style.display = 'none';
}
</script>
