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
<div class="card mb-6">
  <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
        <h1 class="card-title" style="font-size: var(--font-size-xl); margin: 0;">
          &#127891; Certificates &mdash; <?= e($event['title']) ?>
        </h1>
        <span class="badge badge-primary" style="text-transform: uppercase;">
          <?= e($event['status']) ?>
        </span>
      </div>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Event Start: <strong><?= e(date('M d, Y H:i', strtotime((string) $event['start_time']))) ?></strong>
        <?php if (!empty($event['venue_name'])): ?> &bull; <?= e($event['venue_name']) ?><?php endif; ?>
      </p>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/events/' . $event['id'])) ?>" class="btn btn-outline btn-sm">
        &larr; Back to Event
      </a>
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="btn btn-outline btn-sm">
        &#128101; Attendance Roster
      </a>
    </div>
  </div>
</div>

<!-- Event Metrics Summary -->
<div class="grid grid-cols-4 gap-4 mb-6">
  <div class="card" style="padding: 1.25rem; text-align: center;">
    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 600;">Event Total Issued</div>
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
    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 600;">Eligible Unissued</div>
    <div style="font-size: 1.8rem; font-weight: 700; color: #d97706; margin-top: 0.25rem;">
      <?= e((string) count(array_filter($candidates, fn($c) => empty($c['active_cert_id'])))) ?>
    </div>
  </div>

  <div class="card" style="padding: 1.25rem; text-align: center;">
    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 600;">Revoked / Superseded</div>
    <div style="font-size: 1.8rem; font-weight: 700; color: #b91c1c; margin-top: 0.25rem;">
      <?= e((string) ($metrics['revoked_count'] ?? 0)) ?>
    </div>
  </div>
</div>

<!-- Type Selector Navigation -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; overflow-x: auto;">
  <?php foreach ($typeLabels as $slug => $label): ?>
    <?php $isActive = ($activeType === $slug); ?>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/certificates?type=' . $slug)) ?>"
       class="btn <?= $isActive ? 'btn-primary' : 'btn-outline' ?>"
       style="font-size: 0.85rem;">
      <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Issuance Eligibility Warning if Event Not Started -->
<?php if (!$isEventStarted): ?>
  <div class="alert alert-warning mb-6" style="display: flex; align-items: center; gap: 0.75rem;">
    <span style="font-size: 1.5rem;">&#9888;</span>
    <div>
      <strong>Pre-Event Timeline Lock:</strong> Certificates cannot be issued before the event has started. Event starts on <strong><?= e(date('M d, Y H:i', strtotime((string) $event['start_time']))) ?></strong>.
    </div>
  </div>
<?php endif; ?>

<!-- Section 1: Eligible Candidates for Issuance -->
<div class="card mb-6">
  <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color);">
    <div>
      <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: 600; color: var(--color-primary-dark);">
        Eligible Candidates &mdash; <?= e($typeLabels[$activeType] ?? $activeType) ?>
      </h2>
      <p class="text-secondary" style="font-size: var(--font-size-xs); margin-top: 0.2rem;">
        Confirmed attendees with verified presence. Flagged attendees are automatically excluded from bulk issuance and require individual confirmation.
      </p>
    </div>

    <?php if ($isCoordinator && $isEventStarted): ?>
      <button type="button" id="btnSubmitBulk" class="btn btn-primary btn-sm" onclick="submitBulkIssuance()" disabled>
        <span>&#127891; Bulk Issue Selected (<span id="selectedCount">0</span>)</span>
      </button>
    <?php endif; ?>
  </div>

  <div class="card-body" style="padding: 0;">
    <form id="bulkForm" action="<?= e(url('/admin/events/' . $event['id'] . '/certificates/bulk')) ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="<?= e($activeType) ?>">

      <div class="table-responsive">
        <table class="table" style="margin: 0;">
          <thead>
            <tr>
              <?php if ($isCoordinator && $isEventStarted): ?>
                <th style="width: 40px; text-align: center;">
                  <input type="checkbox" id="selectAllCandidates" onclick="toggleSelectAllCandidates(this)">
                </th>
              <?php endif; ?>
              <th>Attendee Name</th>
              <th>Pass Code</th>
              <th>Attendance Presence</th>
              <th>Participant Status</th>
              <th>Certificate Status</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $unissuedCandidates = array_filter($candidates, fn($c) => empty($c['active_cert_id']));
            ?>
            <?php if (empty($candidates)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                  No confirmed attended attendees found for this event.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($candidates as $cand): ?>
                <?php
                  $hasActive = !empty($cand['active_cert_id']);
                  $isFlagged = ($cand['participant_status'] === 'flagged');
                  $canBulkSelect = (!$hasActive && !$isFlagged && $isEventStarted && $isCoordinator);
                ?>
                <tr style="<?= $hasActive ? 'background-color: var(--bg-surface-subtle); opacity: 0.85;' : '' ?>">
                  <?php if ($isCoordinator && $isEventStarted): ?>
                    <td style="text-align: center;">
                      <?php if ($canBulkSelect): ?>
                        <input type="checkbox" name="registration_ids[]" value="<?= e((string) $cand['registration_id']) ?>" class="candidate-checkbox" onclick="updateCandidateSelectionCount()">
                      <?php else: ?>
                        <span style="color: #94a3b8;">&ndash;</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td>
                    <strong><?= e($cand['participant_name']) ?></strong>
                    <div style="font-size: var(--font-size-xs); color: var(--text-secondary); text-transform: capitalize;">
                      <?= e($cand['participant_category'] ?? 'community') ?>
                    </div>
                  </td>
                  <td>
                    <code style="font-size: var(--font-size-xs); font-weight: 600;"><?= e($cand['registration_code']) ?></code>
                  </td>
                  <td>
                    <span class="badge badge-success" style="font-size: 0.75rem; text-transform: uppercase;">
                      <?= e($cand['attendance_status']) ?>
                    </span>
                    <?php if (!empty($cand['checked_in_at'])): ?>
                      <div style="font-size: 0.7rem; color: var(--text-secondary);">
                        <?= e(date('H:i', strtotime((string) $cand['checked_in_at']))) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($isFlagged): ?>
                      <span class="badge badge-warning" style="font-size: 0.75rem; text-transform: uppercase;" title="Flagged: Excluded from bulk; individual verification required">
                        &#9888; Flagged
                      </span>
                    <?php else: ?>
                      <span class="badge badge-light" style="font-size: 0.75rem; text-transform: uppercase;">
                        <?= e($cand['participant_status']) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($hasActive): ?>
                      <span class="badge badge-success" style="font-size: 0.75rem;">
                        &#10003; <?= e($cand['active_cert_number']) ?>
                      </span>
                    <?php else: ?>
                      <span class="badge badge-secondary" style="font-size: 0.75rem;">Not Issued</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right; white-space: nowrap;">
                    <?php if ($hasActive): ?>
                      <a href="<?= e(url('/admin/certificates/' . $cand['active_cert_id'])) ?>" class="btn btn-outline btn-sm">
                        View
                      </a>
                    <?php elseif ($isCoordinator && $isEventStarted): ?>
                      <?php if ($isFlagged): ?>
                        <button type="button" class="btn btn-warning btn-sm" onclick="openFlaggedModal(<?= e((string) $cand['registration_id']) ?>, '<?= e(addslashes($cand['participant_name'])) ?>')">
                          &#9888; Issue Flagged
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
</div>

<!-- Section 2: Issued Certificates List for this Event -->
<div class="card">
  <div class="card-header" style="border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
    <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: 600; color: var(--color-primary-dark);">
      Issued Certificates &mdash; <?= e($typeLabels[$activeType] ?? $activeType) ?>
    </h2>
    <span class="badge badge-light"><?= e((string) $pagination['total']) ?> Total</span>
  </div>

  <div class="card-body" style="padding: 0;">
    <div class="table-responsive">
      <table class="table" style="margin: 0;">
        <thead>
          <tr>
            <th>Certificate No</th>
            <th>Recipient Legal Name</th>
            <th>Issue Date</th>
            <th>Issued By</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($certificates)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                No <?= e($activeType) ?> certificates have been issued for this event yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
              <?php
                $isRevoked = ($cert['status'] ?? '') === 'revoked';
                $statusBadge = $isRevoked ? 'badge-danger' : 'badge-success';
              ?>
              <tr>
                <td>
                  <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" style="font-family: monospace; font-weight: 600; color: var(--color-primary-dark);">
                    <?= e($cert['certificate_number']) ?>
                  </a>
                </td>
                <td>
                  <strong><?= e($cert['recipient_name_snapshot']) ?></strong>
                </td>
                <td>
                  <?= e(date('M d, Y', strtotime((string) $cert['issue_date']))) ?>
                </td>
                <td>
                  <span style="font-size: var(--font-size-xs); color: var(--text-secondary);">
                    <?= e($cert['issued_by_name'] ?? 'System') ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= e($statusBadge) ?>" style="font-size: 0.75rem; text-transform: uppercase;">
                    <?= e($cert['status']) ?>
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" class="btn btn-outline btn-sm">
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
  </div>
</div>

<!-- Modal: Flagged Attendee Manual Issuance Confirmation -->
<div id="flaggedModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 540px; width: 100%; border: 2px solid #f59e0b; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
    <div class="card-header" style="background-color: #fef3c7; border-bottom: 1px solid #fde68a; padding: 1.25rem;">
      <h3 style="margin: 0; font-size: 1.1rem; color: #92400e; display: flex; align-items: center; gap: 0.5rem;">
        <span>&#9888;</span> FLAGGED ATTENDEE NOTICE
      </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
      <p style="font-size: var(--font-size-sm); color: #78350f; margin-bottom: 1rem;">
        Participant <strong id="modalFlaggedName">Attendee</strong> is currently <strong>FLAGGED</strong> in the system. Review attendee identity before authorizing credential generation.
      </p>

      <form id="flaggedIssueForm" action="<?= e(url('/admin/events/' . $event['id'] . '/certificates/issue')) ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="registration_id" id="modalFlaggedRegId" value="">
        <input type="hidden" name="type" value="<?= e($activeType) ?>">

        <div class="mb-4">
          <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: var(--font-size-sm); font-weight: 600; cursor: pointer;">
            <input type="checkbox" name="confirm_flagged" value="1" required style="margin-top: 0.2rem;">
            <span>I have verified the attendee's identity and attendance, and officially authorize certificate issuance for this flagged record.</span>
          </label>
        </div>

        <div class="mb-4">
          <label for="flag_override_reason" class="form-label" style="font-size: var(--font-size-xs);">
            Operational Rationale Note <span class="text-danger">*</span>
          </label>
          <textarea name="flag_override_reason" id="flag_override_reason" class="form-control" rows="3" required placeholder="State operational logistics justification (e.g. Verified attendee photo ID at desk)..." minlength="10" maxlength="255"></textarea>
          <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.25rem;">
            Strictly prohibited: Do NOT record clinical, medical, or counselling information.
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button type="button" class="btn btn-outline" onclick="closeFlaggedModal()">Cancel</button>
          <button type="submit" class="btn btn-warning">Confirm & Issue Certificate</button>
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
