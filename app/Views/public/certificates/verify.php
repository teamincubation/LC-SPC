<?php

declare(strict_types=1);

/**
 * Public Certificate Verification View (V3)
 */
$isRevoked = !empty($verification['is_revoked']);
$statusColor = $isRevoked ? '#DC2626' : '#059669';
$statusBadgeText = $verification['status_label'];
$canonicalUrl = "https://teami.in/LC/certificates/verify/" . htmlspecialchars($token ?? '');

// LinkedIn Share URL construction
$linkedInUrl = "https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME"
  . "&name=" . urlencode($verification['event_title'])
  . "&organizationName=" . urlencode("Listening Community")
  . "&issueYear=" . date('Y', strtotime($verification['issue_date']))
  . "&issueMonth=" . date('n', strtotime($verification['issue_date']))
  . "&certUrl=" . urlencode($canonicalUrl)
  . "&certId=" . urlencode($verification['certificate_id']);
?>

<div style="max-width: 860px; margin: 2rem auto; width: 100%;">
  <div style="text-align: center; margin-bottom: 1.5rem;">
    <a href="<?= e(url('/certificates')) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 1rem;">
      <?= icon('arrow-left') ?>
      <span>Search Another Certificate</span>
    </a>
  </div>

  <div class="card" style="box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color);">
    <!-- Header with Verification Status -->
    <div style="background: <?= $isRevoked ? '#FEF2F2' : '#ECFDF5' ?>; border-bottom: 2px solid <?= $statusColor ?>; padding: 1.5rem; text-align: center;">
      <div style="color: <?= $statusColor ?>; font-size: 2.25rem; margin-bottom: 0.25rem;">
        <?= $isRevoked ? icon('slash') : icon('check-circle') ?>
      </div>
      <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: var(--font-weight-bold); letter-spacing: 0.1em; color: <?= $statusColor ?>; margin-bottom: 0.25rem;">
        Official Verification Result
      </div>
      <h1 style="font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); margin: 0; color: <?= $statusColor ?>;">
        <?= e($statusBadgeText) ?>
      </h1>
      <?php if ($isRevoked): ?>
        <p class="text-secondary" style="font-size: var(--font-size-xs); margin-top: 0.5rem; color: #991B1B;">
          Notice: This credential has been officially marked as invalid or revoked by the issuing authority and is no longer certified.
        </p>
      <?php endif; ?>
    </div>

    <!-- Certificate 1:1 Visual Preview -->
    <div style="padding: 1.5rem; background: #0F172A; text-align: center;">
      <img 
        src="<?= e(url('/certificates/' . $token . '/image')) ?>" 
        alt="Certificate <?= e($verification['certificate_id']) ?>" 
        style="max-width: 100%; height: auto; border-radius: 4px; box-shadow: 0 6px 25px rgba(0,0,0,0.5); display: block; margin: 0 auto;"
      >
    </div>

    <!-- Credential Details Block -->
    <div style="padding: 1.75rem; background: #FFFFFF;">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: var(--font-weight-semibold); display: block; margin-bottom: 0.25rem;">Recipient Name</span>
          <strong style="font-size: var(--font-size-base); color: var(--text-primary);"><?= e($verification['recipient_name']) ?></strong>
        </div>

        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: var(--font-weight-semibold); display: block; margin-bottom: 0.25rem;">Certificate ID</span>
          <span style="font-family: var(--font-mono); font-size: var(--font-size-base); font-weight: var(--font-weight-bold); color: var(--color-primary);"><?= e($verification['certificate_id']) ?></span>
        </div>

        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: var(--font-weight-semibold); display: block; margin-bottom: 0.25rem;">Certification / Program</span>
          <span style="font-size: var(--font-size-sm); color: var(--text-primary);"><?= e($verification['event_title']) ?></span>
        </div>

        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: var(--font-weight-semibold); display: block; margin-bottom: 0.25rem;">Date of Issue</span>
          <span style="font-size: var(--font-size-sm); color: var(--text-primary);"><?= e($verification['issue_date']) ?></span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
        <a href="<?= e(url('/certificates/' . $token . '/pdf')) ?>" target="_blank" class="btn btn-primary">
          <?= icon('download') ?>
          <span>Download Official PDF</span>
        </a>
        <a href="<?= e(url('/certificates/' . $token . '/image')) ?>" target="_blank" class="btn btn-outline">
          <?= icon('image') ?>
          <span>Download High-Res JPG</span>
        </a>
        <?php if (!$isRevoked): ?>
          <a href="<?= e($linkedInUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="background: #0A66C2; color: #FFFFFF; border-color: #0A66C2;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 0.25rem;"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.45c-.9 0-1.63.73-1.63 1.63 0 .9.73 1.63 1.63 1.63.9 0 1.63-.73 1.63-1.63 0-.9-.73-1.63-1.63-1.63Z"/></svg>
            <span>Add to LinkedIn Profile</span>
          </a>
        <?php endif; ?>
        <button type="button" id="btnCopyLink" class="btn btn-outline">
          <?= icon('copy') ?>
          <span id="btnCopyLabel">Copy Link</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const btnCopyLink = document.getElementById('btnCopyLink');
  const btnCopyLabel = document.getElementById('btnCopyLabel');

  btnCopyLink.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(window.location.href);
      btnCopyLabel.textContent = 'Copied!';
      setTimeout(() => {
        btnCopyLabel.textContent = 'Copy Link';
      }, 2500);
    } catch (e) {
      alert('Link: ' + window.location.href);
    }
  });
});
</script>
