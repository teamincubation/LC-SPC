<?php
  $status = $certificate['status'] ?? 'active';
  $isRevoked = ($status === 'revoked');
  $statusBadgeClass = $isRevoked ? 'badge-pill badge-danger' : 'badge-pill badge-success';
  $typeBadgeClass = match ($certificate['type'] ?? '') {
    'volunteer'    => 'badge-pill badge-info',
    'speaker'      => 'badge-pill badge-primary',
    'appreciation' => 'badge-pill badge-warning',
    default        => 'badge-pill badge-neutral',
  };
?>

<!-- Certificate Detail Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <h1>
        <?= icon('award', ['width' => '24', 'height' => '24']) ?>
        <span>Certificate Credential</span>
      </h1>
      <code style="font-family: var(--font-mono); font-size: 0.9rem; font-weight: 700; background: var(--bg-surface-subtle); padding: 0.2rem 0.5rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: var(--primary);">
        <?= e($certificate['certificate_number']) ?>
      </code>
      <span class="<?= e($statusBadgeClass) ?>" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
        <?= e($status) ?>
      </span>
      <span class="<?= e($typeBadgeClass) ?>" style="font-size: 0.65rem; text-transform: capitalize; font-weight: 600;">
        <?= e($certificate['type']) ?>
      </span>
    </div>
    <p>
      Issued on <strong><?= e(date('M d, Y', strtotime((string) $certificate['issue_date']))) ?></strong>
      <?php if (!empty($certificate['issued_by_name'])): ?>
        &bull; Issued by <?= e($certificate['issued_by_name']) ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
      <?= icon('chevron-left', ['width' => '14', 'height' => '14']) ?>
      <span>Directory</span>
    </a>
    <a href="<?= e(url('/admin/events/' . $certificate['event_id'] . '/certificates')) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
      <?= icon('calendar', ['width' => '14', 'height' => '14']) ?>
      <span>Event Hub</span>
    </a>

    <?php if (!$isRevoked && $canDownload): ?>
      <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/print')) ?>" target="_blank" class="btn btn-primary btn-sm" title="Printable A4 Landscape PDF" style="display: inline-flex; align-items: center; gap: 0.35rem;">
        <?= icon('printer', ['width' => '14', 'height' => '14']) ?>
        <span>Download PDF</span>
      </a>
      <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/jpg')) ?>" class="btn btn-outline btn-sm" title="High-Resolution Digital JPG" style="display: inline-flex; align-items: center; gap: 0.35rem;">
        <?= icon('download', ['width' => '14', 'height' => '14']) ?>
        <span>Download JPG</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Revocation Alert Banner if Revoked -->
<?php if ($isRevoked): ?>
  <div style="background: rgba(239, 68, 68, 0.08); border-left: 4px solid var(--danger); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.75rem;">
    <span style="color: var(--danger); display: flex; margin-top: 0.1rem;">
      <?= icon('slash', ['width' => '22', 'height' => '22']) ?>
    </span>
    <div style="flex: 1;">
      <h3 style="margin: 0; font-size: var(--font-size-base); color: #991b1b; font-weight: 700;">
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
<?php endif; ?>

<div class="grid grid-cols-2 gap-6 mb-6">
  <!-- Card 1: Recipient & Event Details -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface);">
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <?= icon('file-text', ['width' => '16', 'height' => '16']) ?>
        <span>Credential Details</span>
      </h2>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Recipient Legal Name Snapshot</span>
          <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-top: 0.2rem;">
            <?= e($certificate['recipient_name_snapshot']) ?>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em;">Certificate Type</span>
            <div style="font-weight: 600; text-transform: capitalize; margin-top: 0.15rem; font-size: var(--font-size-sm);">
              <?= e($certificate['type']) ?>
            </div>
          </div>

          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em;">Issue Date</span>
            <div style="font-weight: 600; margin-top: 0.15rem; font-size: var(--font-size-sm);">
              <?= e(date('F d, Y', strtotime((string) $certificate['issue_date']))) ?>
            </div>
          </div>
        </div>

        <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em;">Event Session</span>
          <div style="font-weight: 600; margin-top: 0.15rem; font-size: var(--font-size-sm);">
            <a href="<?= e(url('/admin/events/' . $certificate['event_id'])) ?>" style="color: var(--primary);">
              <?= e($certificate['event_title']) ?>
            </a>
          </div>
          <div style="font-size: var(--font-size-xs); color: var(--text-muted); margin-top: 0.25rem;">
            Campaign: <strong><?= e($certificate['campaign_title']) ?></strong>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em;">Pass Code</span>
            <div style="margin-top: 0.15rem;">
              <a href="<?= e(url('/admin/registrations/' . $certificate['registration_id'])) ?>" style="font-family: var(--font-mono); font-size: 0.8rem; font-weight: 600; color: var(--primary);">
                <?= e($certificate['registration_code']) ?>
              </a>
            </div>
          </div>

          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em;">Attendance Verification</span>
            <div style="margin-top: 0.15rem;">
              <span class="badge-pill badge-success" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
                <?= e($certificate['attendance_status']) ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Public QR Verification Preview -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface);">
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <?= icon('shield', ['width' => '16', 'height' => '16']) ?>
        <span>Public Verification &amp; Security</span>
      </h2>
    </div>

    <div style="padding: 1.5rem; text-align: center;">
      <?php if (!empty($qrDataUri)): ?>
        <div style="display: inline-block; padding: 0.75rem; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1rem;">
          <img src="<?= $qrDataUri ?>" alt="Verification QR Code" width="150" height="150" style="display: block;">
        </div>
      <?php endif; ?>

      <div style="font-size: var(--font-size-xs); color: var(--text-muted); margin-bottom: 0.35rem;">
        Public Verification URL (Encoded in QR):
      </div>
      <div style="background: var(--bg-surface-subtle); padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-family: var(--font-mono); font-size: 0.75rem; word-break: break-all; margin-bottom: 1rem;">
        <a href="<?= e(url('/verify/' . ($certificate['verification_token'] ?? ''))) ?>" target="_blank" style="color: var(--primary);">
          <?= e($verifyUrl) ?>
        </a>
      </div>

      <div style="font-size: 0.7rem; color: var(--text-muted); line-height: 1.4;">
        Secured by 256-bit cryptographic bearer token &bull; Never exposes phone, email, internal IDs, or admin revocation notes upon public scan.
      </div>
    </div>
  </div>
</div>

<!-- Operational Controls (Coordinator / Super Admin) -->
<?php if ($isCoordinator && !$isRevoked): ?>
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface);">
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <?= icon('settings', ['width' => '16', 'height' => '16']) ?>
        <span>Operational Management Controls</span>
      </h2>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <!-- Button: Reissue with Name Correction -->
        <button type="button" class="btn btn-outline btn-sm" onclick="openReissueModal()" style="display: inline-flex; align-items: center; gap: 0.35rem;">
          <?= icon('edit', ['width' => '14', 'height' => '14']) ?>
          <span>Correct Legal Name &amp; Re-issue</span>
        </button>

        <!-- Button: Revoke Certificate -->
        <button type="button" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.4); display: inline-flex; align-items: center; gap: 0.35rem;" onclick="openRevokeModal()">
          <?= icon('slash', ['width' => '14', 'height' => '14']) ?>
          <span>Revoke Certificate</span>
        </button>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Historical Credentials / Superseded Timeline -->
<?php if (!empty($history) && count($history) > 1): ?>
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface);">
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <?= icon('clock', ['width' => '16', 'height' => '16']) ?>
        <span>Credential History &amp; Superseded Records</span>
      </h2>
    </div>

    <div class="table-responsive">
      <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-surface-subtle); text-align: left; font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">
            <th style="padding: 0.75rem 1rem;">Certificate No</th>
            <th style="padding: 0.75rem 1rem;">Recipient Name</th>
            <th style="padding: 0.75rem 1rem;">Status</th>
            <th style="padding: 0.75rem 1rem;">Issue Date</th>
            <th style="padding: 0.75rem 1rem;">Revocation / Supersession Rationale</th>
            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <?php
              $isCurrent = ((int) $h['id'] === (int) $certificate['id']);
              $hStatusClass = ($h['status'] === 'revoked') ? 'badge-pill badge-danger' : 'badge-pill badge-success';
            ?>
            <tr style="border-bottom: 1px solid var(--border-color); font-size: var(--font-size-sm); <?= $isCurrent ? 'background-color: var(--bg-surface-subtle);' : '' ?>">
              <td style="padding: 0.75rem 1rem;">
                <span style="font-family: var(--font-mono); font-weight: 600; color: var(--primary);"><?= e($h['certificate_number']) ?></span>
                <?php if ($isCurrent): ?>
                  <span class="badge-pill badge-primary" style="font-size: 0.6rem; margin-left: 0.25rem;">Current</span>
                <?php endif; ?>
              </td>
              <td style="padding: 0.75rem 1rem; font-weight: 500;"><?= e($h['recipient_name_snapshot']) ?></td>
              <td style="padding: 0.75rem 1rem;">
                <span class="<?= e($hStatusClass) ?>" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
                  <?= e($h['status']) ?>
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs); color: var(--text-secondary);"><?= e(date('M d, Y', strtotime((string) $h['issue_date']))) ?></td>
              <td style="padding: 0.75rem 1rem;">
                <span style="font-size: var(--font-size-xs); color: var(--text-muted);">
                  <?= e($h['revocation_reason'] ?? 'Active Credential') ?>
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right;">
                <?php if (!$isCurrent): ?>
                  <a href="<?= e(url('/admin/certificates/' . $h['id'])) ?>" class="btn btn-outline btn-sm">Inspect</a>
                <?php else: ?>
                  <span class="text-secondary" style="font-size: var(--font-size-xs);">Viewing</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Modal: Clerical Name Correction & Supersession -->
<div id="reissueModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 540px; width: 100%; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color); border-radius: var(--radius-lg); background: var(--bg-surface);">
    <div style="border-bottom: 1px solid var(--border-color); padding: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
      <h3 style="margin: 0; font-size: 1rem; color: var(--text-primary); font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
        <?= icon('edit', ['width' => '16', 'height' => '16']) ?>
        <span>Clerical Name Correction &amp; Re-issuance</span>
      </h3>
      <button type="button" onclick="closeReissueModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted);">&times;</button>
    </div>
    <div style="padding: 1.25rem;">
      <p style="font-size: var(--font-size-sm); color: var(--text-secondary); margin: 0 0 1rem 0;">
        In compliance with the <strong>Superseded Credential Architecture</strong>, the current certificate (<code><?= e($certificate['certificate_number']) ?></code>) will be permanently marked <strong>REVOKED / SUPERSEDED</strong>. A fresh certificate with a new number and token will be generated for the corrected legal name.
      </p>

      <form action="<?= e(url('/admin/certificates/' . $certificate['id'] . '/reissue')) ?>" method="POST">
        <?= csrf_field() ?>

        <div style="margin-bottom: 1rem;">
          <label for="recipient_name" class="form-label" style="font-size: var(--font-size-xs); font-weight: 600; margin-bottom: 0.35rem;">
            Corrected Legal Recipient Name <span class="text-danger">*</span>
          </label>
          <input type="text" name="recipient_name" id="recipient_name" class="form-input" required minlength="2" maxlength="150" value="<?= e($certificate['recipient_name_snapshot']) ?>">
        </div>

        <div style="margin-bottom: 1.25rem;">
          <label for="reissue_reason" class="form-label" style="font-size: var(--font-size-xs); font-weight: 600; margin-bottom: 0.35rem;">
            Operational Justification <span class="text-danger">*</span>
          </label>
          <textarea name="reason" id="reissue_reason" class="form-input" rows="3" required placeholder="Clerical spelling correction requested by attendee..." minlength="5" maxlength="255"></textarea>
          <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            Clinical, medical, or counselling content is strictly prohibited.
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
          <button type="button" class="btn btn-outline btn-sm" onclick="closeReissueModal()">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Supersede &amp; Issue New Certificate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Revocation -->
<div id="revokeModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 520px; width: 100%; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); border-radius: var(--radius-lg); background: var(--bg-surface);">
    <div style="background: #fee2e2; border-bottom: 1px solid #fca5a5; padding: 1.25rem; border-top-left-radius: var(--radius-lg); border-top-right-radius: var(--radius-lg); display: flex; justify-content: space-between; align-items: center;">
      <h3 style="margin: 0; font-size: 1rem; color: #991b1b; display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
        <?= icon('slash', ['width' => '16', 'height' => '16']) ?>
        <span>Revoke Certificate</span>
      </h3>
      <button type="button" onclick="closeRevokeModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #991b1b;">&times;</button>
    </div>
    <div style="padding: 1.25rem;">
      <p style="font-size: var(--font-size-sm); color: #7f1d1d; margin: 0 0 1rem 0;">
        Are you sure you want to permanently revoke Certificate <strong><?= e($certificate['certificate_number']) ?></strong>?
        Once revoked, this credential cannot be un-revoked. Public verification scans will report the credential as VOID.
      </p>

      <form action="<?= e(url('/admin/certificates/' . $certificate['id'] . '/revoke')) ?>" method="POST">
        <?= csrf_field() ?>

        <div style="margin-bottom: 1.25rem;">
          <label for="revoke_reason" class="form-label" style="font-size: var(--font-size-xs); font-weight: 600; margin-bottom: 0.35rem;">
            Operational Revocation Reason <span class="text-danger">*</span>
          </label>
          <textarea name="reason" id="revoke_reason" class="form-input" rows="3" required placeholder="Administrative invalidation reason (e.g. Registered in error)..." minlength="5" maxlength="255"></textarea>
          <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            Clinical, medical, or counselling content is strictly prohibited.
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
          <button type="button" class="btn btn-outline btn-sm" onclick="closeRevokeModal()">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">Confirm Permanent Revocation</button>
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
