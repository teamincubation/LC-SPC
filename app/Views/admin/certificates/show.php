<?php

declare(strict_types=1);

/**
 * Certificate Platform V3 - Certificate Inspector View
 */
$isRevoked = in_array($certificate['status'], ['invalid', 'revoked'], true);
$verifyUrl = "https://teami.in/LC/certificates/verify/" . $certificate['verification_token'];
?>

<div class="page-header mb-6" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
  <div>
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
      <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline btn-sm">
        <?= icon('arrow-left') ?>
        <span>Back to Repository</span>
      </a>
      <h1 class="page-title" style="margin: 0; font-family: var(--font-mono); font-size: var(--font-size-xl);">
        <?= e($certificate['certificate_id']) ?>
      </h1>
      <?php if ($isRevoked): ?>
        <span class="badge badge-danger">Invalid / Revoked</span>
      <?php else: ?>
        <span class="badge badge-success">Active Credential</span>
      <?php endif; ?>
    </div>
    <p class="text-secondary" style="margin: 0;">Recipient: <strong><?= e($certificate['name']) ?></strong> (Issued: <?= e(date('F j, Y', strtotime($certificate['created_at']))) ?>)</p>
  </div>

  <div style="display: flex; gap: 0.5rem;">
    <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/pdf')) ?>" target="_blank" class="btn btn-outline">
      <?= icon('file-text') ?>
      <span>Download PDF</span>
    </a>
    <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/image')) ?>" target="_blank" class="btn btn-outline">
      <?= icon('image') ?>
      <span>Download Image</span>
    </a>
    <a href="<?= e(url('/certificates/verify/' . $certificate['verification_token'])) ?>" target="_blank" class="btn btn-primary">
      <?= icon('external-link') ?>
      <span>Public Verification Page</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
  <!-- Certificate Image Preview (2 cols) -->
  <div class="lg:col-span-2">
    <div class="card">
      <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin: 0;">
          Certificate Live Preview (300 DPI Canvas)
        </h2>
        <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/image')) ?>" target="_blank" class="text-secondary" style="font-size: var(--font-size-xs);">
          View Full Resolution &rarr;
        </a>
      </div>
      <div class="card-body" style="padding: 1.25rem; text-align: center; background: #0b0f19;">
        <img src="<?= e(url('/admin/certificates/' . $certificate['id'] . '/image')) ?>" alt="Certificate <?= e($certificate['certificate_id']) ?>" style="max-width: 100%; height: auto; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); display: block; margin: 0 auto;">
      </div>
    </div>
  </div>

  <!-- Details & Actions Sidebar (1 col) -->
  <div class="lg:col-span-1" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Metadata Card -->
    <div class="card">
      <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
        <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin: 0;">
          Credential Details
        </h3>
      </div>
      <div class="card-body" style="padding: 1.25rem; font-size: var(--font-size-sm);">
        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-xs); display: block;">Recipient Full Name</span>
          <strong><?= e($certificate['name']) ?></strong>
        </div>

        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-xs); display: block;">Contact Phone</span>
          <span style="font-family: var(--font-mono);"><?= e($certificate['phone']) ?></span>
          <?php if (!empty($certificate['phone_normalized'])): ?>
            <span class="text-muted" style="font-size: var(--font-size-xs); display: block;">E.164: <?= e($certificate['phone_normalized']) ?></span>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-xs); display: block;">Template</span>
          <span><?= e($certificate['template_name'] ?? 'Template #' . $certificate['template_id']) ?></span>
        </div>

        <?php if (!empty($certificate['event_title'])): ?>
          <div class="mb-3">
            <span class="text-secondary" style="font-size: var(--font-size-xs); display: block;">Event / Program</span>
            <span><?= e($certificate['event_title']) ?></span>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-xs); display: block;">Issue Date</span>
          <span><?= e($certificate['date'] ?? date('Y-m-d', strtotime($certificate['created_at']))) ?></span>
        </div>

        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-xs); display: block;">Verification Token</span>
          <code style="font-size: 0.75rem; word-break: break-all;"><?= e($certificate['verification_token']) ?></code>
        </div>

        <!-- Invalidation Info -->
        <?php if ($isRevoked): ?>
          <div style="background: var(--bg-danger); border: 1px solid var(--border-danger); border-radius: 6px; padding: 0.75rem; margin-top: 1rem;">
            <div style="color: var(--text-danger); font-weight: var(--font-weight-semibold); font-size: var(--font-size-xs); text-transform: uppercase;">
              Invalidation Record
            </div>
            <div style="font-size: var(--font-size-xs); margin-top: 0.25rem;">
              <strong>Reason:</strong> <?= e($certificate['invalidation_reason'] ?? 'Revoked by administrator') ?>
            </div>
            <?php if (!empty($certificate['invalidated_at'])): ?>
              <div class="text-muted" style="font-size: 0.7rem; margin-top: 0.25rem;">
                Revoked on <?= e(date('Y-m-d H:i:s', strtotime($certificate['invalidated_at']))) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Administrative Actions Card -->
    <div class="card">
      <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
        <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin: 0;">
          Administrative Controls
        </h3>
      </div>
      <div class="card-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem;">
        <?php if (!$isRevoked): ?>
          <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('invalidateModal').style.display = 'flex';">
            <?= icon('slash') ?>
            <span>Invalidate / Revoke Credential</span>
          </button>
        <?php endif; ?>

        <form method="POST" action="<?= e(url('/admin/certificates/' . $certificate['id'] . '/delete')) ?>" onsubmit="return confirm('Archive this certificate? It will be hidden from the active repository.');">
          <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" class="btn btn-outline btn-sm text-secondary" style="width: 100%;">
            <?= icon('trash-2') ?>
            <span>Archive Certificate</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Invalidate Modal -->
<div id="invalidateModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 480px; width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
      <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin: 0; color: var(--color-danger);">
        Invalidate Certificate
      </h3>
      <button type="button" onclick="document.getElementById('invalidateModal').style.display = 'none';" style="background: none; border: none; font-size: 1.25rem; cursor: pointer;">&times;</button>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin-bottom: 1rem;">
        Are you sure you want to invalidate Certificate <strong><?= e($certificate['certificate_id']) ?></strong>?
        Once invalidated, it will display an official revocation watermark and will not appear in public phone searches.
      </p>
      <form method="POST" action="<?= e(url('/admin/certificates/' . $certificate['id'] . '/invalidate')) ?>">
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="form-group mb-4">
          <label for="reason" class="form-label font-semibold">Reason for Revocation <span class="text-danger">*</span></label>
          <textarea id="reason" name="reason" class="form-control" rows="3" placeholder="e.g. Ineligible attendance, clerical mistake, duplicate award..." required></textarea>
          <div class="form-text text-muted" style="font-size: var(--font-size-xs);">This reason is stored in the audit log for administrative record keeping.</div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
          <button type="button" class="btn btn-outline" onclick="document.getElementById('invalidateModal').style.display = 'none';">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Revocation</button>
        </div>
      </form>
    </div>
  </div>
</div>
