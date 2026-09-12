<?php
  $isRevoked = !empty($verification['is_revoked']);
  $statusColor = $isRevoked ? '#dc2626' : '#16a34a';
  $statusBadgeText = $isRevoked ? 'REVOKED / INVALID' : 'AUTHENTIC & VERIFIED CREDENTIAL';
  $ogTitle = $isRevoked ? 'Invalidated Credential — LC-SPC' : 'Verified Certificate — LC-SPC';
  $ogDescription = 'Official credential verification portal for Listening Community – Suicide Prevention Campaign.';
  $canonicalUrl = "https://teami.in/LC/verify/" . htmlspecialchars($token ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Certificate Verification') ?> &mdash; Listening Community</title>

  <!-- OpenGraph Metadata (Minimal Disclosure) -->
  <meta property="og:title" content="<?= e($ogTitle) ?>">
  <meta property="og:description" content="<?= e($ogDescription) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= e($ogTitle) ?>">
  <meta name="twitter:description" content="<?= e($ogDescription) ?>">

  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      color: #1e293b;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .verify-container {
      width: 100%;
      max-width: 600px;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      border: 1px solid #cbd5e1;
      overflow: hidden;
    }
    .verify-header {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #ffffff;
      padding: 2rem 1.5rem;
      text-align: center;
    }
    .verify-header .brand {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      opacity: 0.8;
      margin-bottom: 0.35rem;
    }
    .verify-header h1 {
      font-size: 1.4rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: 0.02em;
    }
    .status-banner {
      padding: 1rem 1.5rem;
      text-align: center;
      font-weight: 700;
      font-size: 0.95rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      <?php if ($isRevoked): ?>
        background: #fee2e2;
        color: #991b1b;
        border-bottom: 2px solid #ef4444;
      <?php else: ?>
        background: #dcfce7;
        color: #15803d;
        border-bottom: 2px solid #22c55e;
      <?php endif; ?>
    }
    .verify-body {
      padding: 2rem 1.5rem;
    }
    .code-section {
      text-align: center;
      background: #f8fafc;
      border: 1.5px dashed #cbd5e1;
      border-radius: 8px;
      padding: 1.25rem;
      margin-bottom: 1.5rem;
    }
    .code-section .label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #64748b;
      margin-bottom: 0.35rem;
    }
    .code-section .code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 1.6rem;
      font-weight: 700;
      color: #0f172a;
      letter-spacing: 0.05em;
    }
    .detail-group {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }
    .detail-item .label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      margin-bottom: 0.2rem;
    }
    .detail-item .value {
      font-size: 1.05rem;
      font-weight: 600;
      color: #0f172a;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .revocation-notice {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 8px;
      padding: 1rem;
      margin-top: 1.5rem;
      font-size: 0.85rem;
      color: #991b1b;
      line-height: 1.5;
    }
    .verify-footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 1.25rem 1.5rem;
      text-align: center;
      font-size: 0.75rem;
      color: #64748b;
      line-height: 1.5;
    }
  </style>
</head>
<body>

  <div class="verify-container">
    <!-- Header -->
    <div class="verify-header">
      <div class="brand">Listening Community &bull; Suicide Prevention Campaign</div>
      <h1>Official Credential Verification</h1>
    </div>

    <!-- Status Banner -->
    <div class="status-banner">
      <?php if ($isRevoked): ?>
        <span>&#128721;</span> <?= e($statusBadgeText) ?>
      <?php else: ?>
        <span>&#10003;</span> <?= e($statusBadgeText) ?>
      <?php endif; ?>
    </div>

    <!-- Content -->
    <div class="verify-body">
      <!-- Certificate Identifier -->
      <div class="code-section">
        <div class="label">Official Certificate Number</div>
        <div class="code"><?= e($verification['certificate_number']) ?></div>
      </div>

      <!-- Credential Fields -->
      <div class="detail-group">
        <div class="detail-item">
          <div class="label">Recipient Legal Name</div>
          <div class="value" style="font-size: 1.35rem; color: #0f172a;">
            <?= e($verification['recipient_name_snapshot']) ?>
          </div>
        </div>

        <div class="detail-item">
          <div class="label">Credential Classification</div>
          <div class="value" style="color: #b45309;">
            <?= e($verification['type_label']) ?>
          </div>
        </div>

        <div class="detail-item">
          <div class="label">Event Session</div>
          <div class="value">
            <?= e($verification['event_title']) ?>
          </div>
          <?php if (!empty($verification['campaign_title'])): ?>
            <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.2rem;">
              Campaign: <?= e($verification['campaign_title']) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="detail-grid">
          <div class="detail-item">
            <div class="label">Issue Date</div>
            <div class="value">
              <?= e(date('F d, Y', strtotime((string) $verification['issue_date']))) ?>
            </div>
          </div>

          <div class="detail-item">
            <div class="label">Issuing Authority</div>
            <div class="value" style="font-size: 0.95rem;">
              Listening Community
            </div>
          </div>
        </div>

        <?php if (!$isRevoked): ?>
          <!-- Social Share & LinkedIn Credential Addition -->
          <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 0.75rem;">
            <a href="https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&name=<?= urlencode(($verification['type_label'] ?? 'Certificate') . ' - ' . ($verification['event_title'] ?? '')) ?>&organizationName=<?= urlencode('Listening Community') ?>&issueYear=<?= date('Y', strtotime((string) ($verification['issue_date'] ?? 'now'))) ?>&issueMonth=<?= date('n', strtotime((string) ($verification['issue_date'] ?? 'now'))) ?>&certUrl=<?= urlencode($canonicalUrl) ?>&certId=<?= urlencode($verification['certificate_number'] ?? '') ?>" 
               target="_blank" 
               rel="noopener noreferrer" 
               style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #0077b5; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 0.75rem 1.25rem; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: background 0.2s;">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854V1.146zm4.943 12.248V6.169H2.542v7.225h2.401zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248-.822 0-1.359.54-1.359 1.248 0 .694.521 1.248 1.327 1.248h.016zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016a5.54 5.54 0 0 1 .016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225h2.4z"/>
              </svg>
              Add Certificate to LinkedIn Profile
            </a>

            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($canonicalUrl) ?>" 
               target="_blank" 
               rel="noopener noreferrer" 
               style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #f1f5f9; color: #334155; text-decoration: none; font-weight: 500; font-size: 0.85rem; padding: 0.6rem 1.25rem; border-radius: 6px; border: 1px solid #cbd5e1; transition: background 0.2s;">
              Share Credential on LinkedIn Feed
            </a>
          </div>
        <?php endif; ?>

        <?php if ($isRevoked): ?>
          <!-- Generic Revocation Invalidation Statement -->
          <div class="revocation-notice">
            <strong>OFFICIAL INVALIDATION NOTICE:</strong><br>
            <?= e($verification['revocation_statement']) ?>
            <?php if (!empty($verification['revoked_at'])): ?>
              <div style="margin-top: 0.35rem; font-size: 0.8rem; color: #7f1d1d;">
                Invalidated on: <strong><?= e(date('F d, Y', strtotime((string) $verification['revoked_at']))) ?></strong>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer -->
    <div class="verify-footer">
      This verification portal confirms authentic institutional records maintained by the Listening Community.<br>
      Zero personal contact information is exposed &bull; Verified via teami.in/LC
    </div>
  </div>

</body>
</html>
