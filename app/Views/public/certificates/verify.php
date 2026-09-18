<?php

declare(strict_types=1);

/**
 * Public Certificate Verification View (V3 - Mobile-First Redesign)
 * Strictly adheres to Mobile Priority Order:
 * 1. Certificate status (Verified vs Revoked)
 * 2. Recipient / certificate information
 * 3. Certificate 1:1 preview (Aspect ratio 2480:1754, never cropped)
 * 4. Primary PDF download CTA
 * 5. Secondary JPG download CTA
 * 6. LinkedIn / verification-link actions
 * 7. Supporting trust information & cryptographic assurance
 */
$isRevoked = !empty($verification['is_revoked']);
$statusBadgeText = $verification['status_label'];
$canonicalUrl = "https://teami.in/LC/certificates/verify/" . htmlspecialchars($token ?? '');

// LinkedIn Add Certification URL
$linkedInUrl = "https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME"
  . "&name=" . urlencode($verification['event_title'])
  . "&organizationName=" . urlencode("Listening Community")
  . "&issueYear=" . date('Y', strtotime($verification['issue_date']))
  . "&issueMonth=" . date('n', strtotime($verification['issue_date']))
  . "&certUrl=" . urlencode($canonicalUrl)
  . "&certId=" . urlencode($verification['certificate_id']);
?>

<div style="width: 100%; max-width: 860px; margin: 1rem auto;">
  <!-- Navigation Header: Search Another Certificate -->
  <div class="cert-nav-bar">
    <a href="<?= e(url('/certificates')) ?>" class="cert-nav-back" aria-label="Search another certificate">
      <?= icon('arrow-left') ?>
      <span>Search Another Certificate</span>
    </a>
  </div>

  <div class="cert-verification-card">
    <!-- ======================================================================
         1. Certificate Status Banner (Mobile Priority #1)
         ====================================================================== -->
    <header class="cert-status-banner <?= $isRevoked ? 'is-revoked' : 'is-active' ?>" role="region" aria-label="Certificate Status">
      <div class="cert-status-icon-wrap" aria-hidden="true">
        <?= $isRevoked ? icon('slash') : icon('check-circle') ?>
      </div>
      <div class="cert-status-eyebrow">
        Official Verification Result
      </div>
      <h1 class="cert-status-heading">
        <?= e($statusBadgeText) ?>
      </h1>
      <?php if ($isRevoked): ?>
        <p class="cert-status-desc">
          <strong>Notice:</strong> This credential has been officially marked as invalid or revoked by the issuing authority and is no longer certified.
        </p>
      <?php else: ?>
        <p class="cert-status-desc">
          Official, authentic participation credential issued and cryptographically verified by Listening Community SPC.
        </p>
      <?php endif; ?>
    </header>

    <!-- ======================================================================
         2. Recipient & Certificate Information (Mobile Priority #2)
         ====================================================================== -->
    <section class="cert-info-block" aria-label="Recipient and Credential Details">
      <div class="cert-info-grid">
        <div class="cert-info-item">
          <span class="cert-info-label">Recipient Name</span>
          <span class="cert-info-value"><?= e($verification['recipient_name']) ?></span>
        </div>

        <div class="cert-info-item">
          <span class="cert-info-label">Official Certificate ID</span>
          <span class="cert-info-value cert-info-value-mono"><?= e($verification['certificate_id']) ?></span>
        </div>

        <div class="cert-info-item">
          <span class="cert-info-label">Program / Event Title</span>
          <span class="cert-info-value" style="font-size: 0.9375rem; font-weight: 600;"><?= e($verification['event_title']) ?></span>
        </div>

        <div class="cert-info-item">
          <span class="cert-info-label">Date of Issuance</span>
          <span class="cert-info-value" style="font-size: 0.9375rem; font-weight: 600;"><?= e($verification['issue_date']) ?></span>
        </div>
      </div>
    </section>

    <!-- ======================================================================
         3. Certificate 1:1 Canvas Preview (Mobile Priority #3)
         ====================================================================== -->
    <section class="cert-preview-section" aria-label="Certificate Visual Preview">
      <div class="cert-preview-aspect-box">
        <img 
          src="<?= e(url('/certificates/' . $token . '/image')) ?>" 
          alt="Certificate for <?= e($verification['recipient_name']) ?> (ID: <?= e($verification['certificate_id']) ?>)" 
          class="cert-preview-img"
          loading="eager"
          width="2480"
          height="1754"
        >
      </div>
      <div class="cert-preview-badge">
        <?= icon('shield', ['style' => 'width: 13px; height: 13px;']) ?>
        <span>Official High-Resolution 300 DPI Render &bull; Aspect Ratio 1.41:1</span>
      </div>
    </section>

    <!-- ======================================================================
         4, 5, 6. Action Buttons & Downloads (Mobile Priority #4, #5, #6)
         ====================================================================== -->
    <section class="cert-actions-card" aria-label="Certificate Actions and Downloads">
      <div class="cert-actions-grid">
        <!-- 4. PDF Download (Primary Action) -->
        <a 
          href="<?= e(url('/certificates/' . $token . '/pdf')) ?>" 
          id="btnDownloadPdf" 
          class="cert-btn-action cert-btn-pdf" 
          target="_blank"
          download="Certificate-<?= e($verification['certificate_id']) ?>.pdf"
          aria-label="Download official high-resolution PDF certificate"
        >
          <?= icon('download') ?>
          <span class="btn-action-text">Download Official PDF</span>
        </a>

        <!-- 5. JPG Download (Secondary Action) -->
        <a 
          href="<?= e(url('/certificates/' . $token . '/image')) ?>" 
          id="btnDownloadJpg" 
          class="cert-btn-action cert-btn-jpg" 
          target="_blank"
          download="Certificate-<?= e($verification['certificate_id']) ?>.jpg"
          aria-label="Download high-resolution JPG certificate"
        >
          <?= icon('image') ?>
          <span class="btn-action-text">Download High-Res JPG</span>
        </a>

        <!-- 6. LinkedIn Certification Share (if not revoked) -->
        <?php if (!$isRevoked): ?>
          <a 
            href="<?= e($linkedInUrl) ?>" 
            target="_blank" 
            rel="noopener noreferrer" 
            class="cert-btn-action cert-btn-linkedin"
            aria-label="Add this verified credential to your LinkedIn profile"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="display: inline-block; flex-shrink: 0;"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.45c-.9 0-1.63.73-1.63 1.63 0 .9.73 1.63 1.63 1.63.9 0 1.63-.73 1.63-1.63 0-.9-.73-1.63-1.63-1.63Z"/></svg>
            <span>Add to LinkedIn Profile</span>
          </a>
        <?php endif; ?>

        <!-- 6. Copy Verification Link -->
        <button 
          type="button" 
          id="btnCopyLink" 
          class="cert-btn-action cert-btn-copy"
          aria-label="Copy public verification link to clipboard"
        >
          <?= icon('copy') ?>
          <span id="btnCopyLabel">Copy Verification Link</span>
        </button>
      </div>

      <!-- Live Notification for Screen Readers -->
      <div id="copyAlertRegion" class="sr-only" aria-live="polite"></div>
    </section>

    <!-- ======================================================================
         7. Supporting Trust Information (Mobile Priority #7)
         ====================================================================== -->
    <footer class="cert-trust-footer">
      <div class="cert-trust-inner">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.35rem; font-weight: 700; color: #334155;">
          <?= icon('shield', ['style' => 'color: #059669; width: 16px; height: 16px;']) ?>
          <span>Tamper-Proof Verification Assurance</span>
        </div>
        <p style="margin: 0;">
          This official credential is tied to a unique 256-bit cryptographic verification token and registered in the Listening Community SPC database.
        </p>
        <div class="cert-verification-url-box" title="Permanent Verification URL">
          <span><?= e($canonicalUrl) ?></span>
        </div>
      </div>
    </footer>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Copy Verification Link with accessible feedback
  const btnCopyLink = document.getElementById('btnCopyLink');
  const btnCopyLabel = document.getElementById('btnCopyLabel');
  const copyAlertRegion = document.getElementById('copyAlertRegion');

  if (btnCopyLink && btnCopyLabel) {
    btnCopyLink.addEventListener('click', async () => {
      const shareUrl = window.location.href;
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(shareUrl);
        } else {
          // Fallback for non-https or older webviews
          const textArea = document.createElement('textarea');
          textArea.value = shareUrl;
          textArea.style.position = 'fixed';
          textArea.style.left = '-999999px';
          document.body.appendChild(textArea);
          textArea.focus();
          textArea.select();
          document.execCommand('copy');
          document.body.removeChild(textArea);
        }

        btnCopyLabel.textContent = 'Link Copied!';
        btnCopyLink.style.borderColor = '#059669';
        btnCopyLink.style.color = '#059669';
        if (copyAlertRegion) {
          copyAlertRegion.textContent = 'Verification link successfully copied to clipboard.';
        }

        setTimeout(() => {
          btnCopyLabel.textContent = 'Copy Verification Link';
          btnCopyLink.style.borderColor = '';
          btnCopyLink.style.color = '';
        }, 2500);
      } catch (err) {
        prompt('Copy verification link below:', shareUrl);
      }
    });
  }

  // Download CTAs instant feedback (avoids duplicate clicks)
  function setupDownloadFeedback(btnId, preparingText, defaultText) {
    const btn = document.getElementById(btnId);
    if (!btn) return;

    btn.addEventListener('click', () => {
      const textSpan = btn.querySelector('.btn-action-text');
      if (textSpan) {
        textSpan.textContent = preparingText;
        setTimeout(() => {
          textSpan.textContent = defaultText;
        }, 3000);
      }
    });
  }

  setupDownloadFeedback('btnDownloadPdf', 'Preparing PDF...', 'Download Official PDF');
  setupDownloadFeedback('btnDownloadJpg', 'Preparing JPG...', 'Download High-Res JPG');
});
</script>
