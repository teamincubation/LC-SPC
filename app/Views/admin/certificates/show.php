<?php
  $status = $certificate['status'] ?? 'active';
  $isRevoked = ($status === 'revoked');
  $statusBadgeClass = $isRevoked ? 'badge-danger' : 'badge-success';
  $typeBadgeClass = match ($certificate['type'] ?? '') {
    'volunteer'    => 'badge-info',
    'speaker'      => 'badge-primary',
    'appreciation' => 'badge-warning',
    default        => 'badge-light',
  };
?>

<!-- Certificate Detail Header -->
<div class="card mb-6">
  <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
        <h1 class="card-title" style="font-size: var(--font-size-xl); margin: 0;">
          Certificate Credential
        </h1>
        <code style="font-size: var(--font-size-md); font-weight: 700; background-color: var(--bg-surface-subtle); padding: 0.2rem 0.5rem; border-radius: var(--border-radius-sm); color: var(--color-primary-dark);">
          <?= e($certificate['certificate_number']) ?>
        </code>
        <span class="badge <?= e($statusBadgeClass) ?>" style="font-size: var(--font-size-sm); text-transform: uppercase;">
          <?= e($status) ?>
        </span>
        <span class="badge <?= e($typeBadgeClass) ?>" style="font-size: var(--font-size-sm); text-transform: capitalize;">
          <?= e($certificate['type']) ?>
        </span>
      </div>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Issued on <strong><?= e(date('M d, Y', strtotime((string) $certificate['issue_date']))) ?></strong>
        <?php if (!empty($certificate['issued_by_name'])): ?>
          &bull; Issued by <?= e($certificate['issued_by_name']) ?>
        <?php endif; ?>
      </p>
    </div>

    <!-- Quick Action Toolbar -->
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline btn-sm">&larr; Directory</a>
      <a href="<?= e(url('/admin/events/' . $certificate['event_id'] . '/certificates')) ?>" class="btn btn-outline btn-sm">&#128197; Event Hub</a>

      <?php if (!$isRevoked && $canDownload): ?>
        <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/print')) ?>" target="_blank" class="btn btn-primary btn-sm" title="Printable A4 Landscape PDF">
          <span>&#128424; Download PDF (Print)</span>
        </a>
        <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/jpg')) ?>" class="btn btn-outline btn-sm" title="High-Resolution Digital JPG">
          <span>&#128190; Download JPG (Share)</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Revocation Alert Banner if Revoked -->
<?php if ($isRevoked): ?>
  <div class="alert alert-danger mb-6" style="border-left: 5px solid #dc2626; padding: 1.25rem;">
    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
      <span style="font-size: 1.5rem;">&#128721;</span>
      <div style="flex: 1;">
        <h3 style="margin: 0; font-size: 1rem; color: #991b1b; font-weight: 700;">
          CREDENTIAL OFFICIALLY REVOKED &bull; VOID
        </h3>
        <p style="font-size: var(--font-size-sm); color: #7f1d1d; margin: 0.35rem 0 0.5rem 0;">
          This certificate was revoked on <strong><?= e(date('M d, Y H:i', strtotime((string) ($certificate['revoked_at'] ?? '')))) ?></strong>
          <?php if (!empty($certificate['revoked_by_name'])): ?> by <strong><?= e($certificate['revoked_by_name']) ?></strong><?php endif; ?>.
          Any physical printout or electronic copy bearing this certificate number is void upon public verification.
        </p>
        <?php if ($isCoordinator && !empty($certificate['revocation_reason'])): ?>
          <div style="font-size: var(--font-size-xs); background: rgba(254, 226, 226, 0.7); padding: 0.5rem 0.75rem; border-radius: 4px; color: #991b1b;">
            <strong>Internal Operational Rationale:</strong> <?= e($certificate['revocation_reason']) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-2 gap-6 mb-6">
  <!-- Card 1: Recipient & Event Details -->
  <div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
      <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: 600; color: var(--color-primary-dark);">
        Credential Details
      </h2>
    </div>

    <div class="card-body" style="padding: 1.25rem;">
      <div class="detail-group" style="display: flex; flex-direction: column; gap: 1rem;">
        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: 600;">Recipient Legal Name Snapshot</span>
          <div style="font-size: 1.25rem; font-weight: 700; color: var(--color-primary-dark); margin-top: 0.15rem;">
            <?= e($certificate['recipient_name_snapshot']) ?>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Certificate Type</span>
            <div style="font-weight: 600; text-transform: capitalize; margin-top: 0.15rem;">
              <?= e($certificate['type']) ?>
            </div>
          </div>

          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Issue Date</span>
            <div style="font-weight: 600; margin-top: 0.15rem;">
              <?= e(date('F d, Y', strtotime((string) $certificate['issue_date']))) ?>
            </div>
          </div>
        </div>

        <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Event Session</span>
          <div style="font-weight: 600; margin-top: 0.15rem;">
            <a href="<?= e(url('/admin/events/' . $certificate['event_id'])) ?>" class="text-primary">
              <?= e($certificate['event_title']) ?>
            </a>
          </div>
          <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-top: 0.25rem;">
            Campaign: <strong><?= e($certificate['campaign_title']) ?></strong>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Pass Code</span>
            <div>
              <a href="<?= e(url('/admin/registrations/' . $certificate['registration_id'])) ?>" style="font-family: monospace; font-size: var(--font-size-sm); font-weight: 600;">
                <?= e($certificate['registration_code']) ?>
              </a>
            </div>
          </div>

          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Attendance Verification</span>
            <div style="margin-top: 0.15rem;">
              <span class="badge badge-success" style="font-size: 0.75rem; text-transform: uppercase;">
                <?= e($certificate['attendance_status']) ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Public QR Verification Preview -->
  <div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
      <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: 600; color: var(--color-primary-dark);">
        Public Verification & Cryptographic Security
      </h2>
    </div>

    <div class="card-body" style="padding: 1.25rem; text-align: center;">
      <?php if (!empty($qrDataUri)): ?>
        <div style="display: inline-block; padding: 0.75rem; background: #fff; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 1rem;">
          <img src="<?= $qrDataUri ?>" alt="Verification QR Code" width="160" height="160" style="display: block;">
        </div>
      <?php endif; ?>

      <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-bottom: 0.5rem;">
        Public Verification URL (Encoded in QR):
      </div>
      <div style="background: var(--bg-surface-subtle); padding: 0.5rem 0.75rem; border-radius: 6px; font-family: monospace; font-size: 0.75rem; word-break: break-all; margin-bottom: 1rem;">
        <a href="<?= e(url('/verify/' . ($certificate['verification_token'] ?? ''))) ?>" target="_blank" class="text-primary">
          <?= e($verifyUrl) ?>
        </a>
      </div>

      <div style="font-size: 0.7rem; color: var(--text-secondary); line-height: 1.4;">
        Secured by 256-bit cryptographic bearer token &bull; Never exposes phone, email, internal IDs, or admin revocation text upon public scan.
      </div>
    </div>
  </div>
</div>

<!-- Operational Controls (Coordinator / Super Admin) -->
<?php if ($isCoordinator && !$isRevoked): ?>
  <div class="card mb-6">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
      <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: 600; color: var(--color-primary-dark);">
        Operational Management Controls
      </h2>
    </div>

    <div class="card-body" style="padding: 1.25rem;">
      <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <!-- Button: Reissue with Name Correction -->
        <button type="button" class="btn btn-outline" onclick="openReissueModal()">
          &#9998; Correct Recipient Name & Re-issue
        </button>

        <!-- Button: Revoke Certificate -->
        <button type="button" class="btn btn-outline" style="color: #b91c1c; border-color: #fca5a5;" onclick="openRevokeModal()">
          &#128721; Revoke Certificate
        </button>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Historical Credentials / Superseded Timeline -->
<?php if (!empty($history) && count($history) > 1): ?>
  <div class="card mb-6">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
      <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: 600; color: var(--color-primary-dark);">
        Credential History & Superseded Records
      </h2>
    </div>

    <div class="card-body" style="padding: 0;">
      <div class="table-responsive">
        <table class="table" style="margin: 0;">
          <thead>
            <tr>
              <th>Certificate No</th>
              <th>Recipient Name</th>
              <th>Status</th>
              <th>Issue Date</th>
              <th>Revocation / Supersession Rationale</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <?php
                $isCurrent = ((int) $h['id'] === (int) $certificate['id']);
                $hStatusClass = ($h['status'] === 'revoked') ? 'badge-danger' : 'badge-success';
              ?>
              <tr style="<?= $isCurrent ? 'background-color: var(--bg-surface-subtle);' : '' ?>">
                <td>
                  <span style="font-family: monospace; font-weight: 600;"><?= e($h['certificate_number']) ?></span>
                  <?php if ($isCurrent): ?>
                    <span class="badge badge-primary" style="font-size: 0.65rem; margin-left: 0.25rem;">Current</span>
                  <?php endif; ?>
                </td>
                <td><?= e($h['recipient_name_snapshot']) ?></td>
                <td>
                  <span class="badge <?= e($hStatusClass) ?>" style="font-size: 0.75rem; text-transform: uppercase;">
                    <?= e($h['status']) ?>
                  </span>
                </td>
                <td><?= e(date('M d, Y', strtotime((string) $h['issue_date']))) ?></td>
                <td>
                  <span style="font-size: var(--font-size-xs); color: var(--text-secondary);">
                    <?= e($h['revocation_reason'] ?? 'Active Credential') ?>
                  </span>
                </td>
                <td style="text-align: right;">
                  <?php if (!$isCurrent): ?>
                    <a href="<?= e(url('/admin/certificates/' . $h['id'])) ?>" class="btn btn-outline btn-sm">Inspect</a>
                  <?php else: ?>
                    <span class="text-muted" style="font-size: var(--font-size-xs);">Viewing</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Modal: Clerical Name Correction & Supersession -->
<div id="reissueModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 540px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem;">
      <h3 style="margin: 0; font-size: 1.1rem; color: var(--color-primary-dark);">
        Clerical Name Correction & Re-issuance
      </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
      <p style="font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: 1rem;">
        In compliance with the <strong>Superseded Credential Architecture</strong>, the current certificate (<code><?= e($certificate['certificate_number']) ?></code>) will be permanently marked <strong>REVOKED / SUPERSEDED</strong>. A fresh certificate with a new number and token will be generated for the corrected legal name.
      </p>

      <form action="<?= e(url('/admin/certificates/' . $certificate['id'] . '/reissue')) ?>" method="POST">
        <?= csrf_field() ?>

        <div class="mb-4">
          <label for="recipient_name" class="form-label" style="font-size: var(--font-size-xs);">
            Corrected Legal Recipient Name <span class="text-danger">*</span>
          </label>
          <input type="text" name="recipient_name" id="recipient_name" class="form-control" required minlength="2" maxlength="150" value="<?= e($certificate['recipient_name_snapshot']) ?>">
        </div>

        <div class="mb-4">
          <label for="reissue_reason" class="form-label" style="font-size: var(--font-size-xs);">
            Operational Justification <span class="text-danger">*</span>
          </label>
          <textarea name="reason" id="reissue_reason" class="form-control" rows="3" required placeholder="Clerical spelling correction requested by attendee..." minlength="5" maxlength="255"></textarea>
          <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.25rem;">
            Clinical, medical, or counselling content is strictly prohibited.
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button type="button" class="btn btn-outline" onclick="closeReissueModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Supersede & Issue New Certificate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Revocation -->
<div id="revokeModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 520px; width: 100%; border: 2px solid #ef4444; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
    <div class="card-header" style="background: #fee2e2; border-bottom: 1px solid #fca5a5; padding: 1.25rem;">
      <h3 style="margin: 0; font-size: 1.1rem; color: #991b1b; display: flex; align-items: center; gap: 0.5rem;">
        <span>&#128721;</span> Revoke Certificate
      </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
      <p style="font-size: var(--font-size-sm); color: #7f1d1d; margin-bottom: 1rem;">
        Are you sure you want to permanently revoke Certificate <strong><?= e($certificate['certificate_number']) ?></strong>?
        Once revoked, this credential cannot be un-revoked. Public verification scans will report the credential as VOID.
      </p>

      <form action="<?= e(url('/admin/certificates/' . $certificate['id'] . '/revoke')) ?>" method="POST">
        <?= csrf_field() ?>

        <div class="mb-4">
          <label for="revoke_reason" class="form-label" style="font-size: var(--font-size-xs);">
            Operational Revocation Reason <span class="text-danger">*</span>
          </label>
          <textarea name="reason" id="revoke_reason" class="form-control" rows="3" required placeholder="Administrative invalidation reason (e.g. Registered in error)..." minlength="5" maxlength="255"></textarea>
          <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.25rem;">
            Clinical, medical, or counselling content is strictly prohibited.
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button type="button" class="btn btn-outline" onclick="closeRevokeModal()">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Permanent Revocation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReissueModal() {
  document.getElementById('reissueModal').style.display = 'flex';
}
function closeReissueModal() {
  document.getElementById('reissueModal').style.display = 'none';
}
function openRevokeModal() {
  document.getElementById('revokeModal').style.display = 'flex';
}
function closeRevokeModal() {
  document.getElementById('revokeModal').style.display = 'none';
}
</script>
