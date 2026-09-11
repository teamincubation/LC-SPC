<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e($title ?? 'Official Certificate') ?></title>
  <style>
    @page {
      size: A4 landscape;
      margin: 0;
    }
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f1f5f9;
      color: #0f172a;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1rem;
    }
    .print-controls {
      margin-bottom: 1rem;
      display: flex;
      gap: 0.75rem;
    }
    .btn {
      padding: 0.5rem 1.25rem;
      font-size: 0.9rem;
      font-weight: 600;
      border-radius: 6px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border: 1px solid #0f172a;
      background: #0f172a;
      color: #fff;
    }
    .btn-outline {
      background: #fff;
      color: #0f172a;
    }
    /* Certificate A4 Landscape Canvas: 297mm x 210mm */
    .certificate-canvas {
      width: 297mm;
      height: 210mm;
      background: #ffffff;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      position: relative;
      padding: 14mm 16mm;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    /* Elegant Borders */
    .cert-outer-border {
      position: absolute;
      inset: 8mm;
      border: 2.5mm solid #0f172a;
      pointer-events: none;
    }
    .cert-inner-border {
      position: absolute;
      inset: 11mm;
      border: 0.8mm solid #b45309; /* Gold / Bronze */
      pointer-events: none;
    }
    .corner-ornament {
      position: absolute;
      width: 14mm;
      height: 14mm;
      border-color: #b45309;
      border-style: solid;
      pointer-events: none;
    }
    .co-tl { top: 13mm; left: 13mm; border-width: 1.2mm 0 0 1.2mm; }
    .co-tr { top: 13mm; right: 13mm; border-width: 1.2mm 1.2mm 0 0; }
    .co-bl { bottom: 13mm; left: 13mm; border-width: 0 0 1.2mm 1.2mm; }
    .co-br { bottom: 13mm; right: 13mm; border-width: 0 1.2mm 1.2mm 0; }

    /* Content Typography */
    .cert-content {
      position: relative;
      z-index: 10;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      text-align: center;
    }
    .cert-header .brand-title {
      font-size: 14pt;
      font-weight: 800;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: #0f172a;
    }
    .cert-header .brand-subtitle {
      font-size: 8.5pt;
      font-weight: 600;
      letter-spacing: 0.25em;
      text-transform: uppercase;
      color: #b45309;
      margin-top: 1mm;
    }
    .cert-title-section {
      margin-top: 3mm;
    }
    .cert-title-section h1 {
      font-size: 26pt;
      font-weight: 800;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #0f172a;
      line-height: 1.1;
    }
    .presentation-line {
      font-size: 11pt;
      color: #64748b;
      margin-top: 3mm;
      font-style: italic;
    }
    .recipient-name {
      font-size: 28pt;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: 0.02em;
      margin-top: 2mm;
      text-transform: uppercase;
      border-bottom: 0.8mm solid #b45309;
      display: inline-block;
      padding: 0 12mm 1mm 12mm;
    }
    .cert-body-text {
      font-size: 11pt;
      color: #475569;
      margin-top: 4mm;
      line-height: 1.5;
    }
    .event-title {
      font-size: 15pt;
      font-weight: 700;
      color: #0f172a;
      margin-top: 1mm;
    }
    .event-meta {
      font-size: 10pt;
      color: #64748b;
      margin-top: 1mm;
    }
    /* Bottom Grid: Signatures & QR */
    .cert-footer {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      margin-top: 4mm;
      padding: 0 10mm;
    }
    .signature-block {
      width: 65mm;
      text-align: center;
    }
    .signature-line {
      border-bottom: 0.4mm solid #94a3b8;
      margin-bottom: 2mm;
      height: 10mm;
    }
    .signatory-name {
      font-size: 10.5pt;
      font-weight: 700;
      color: #0f172a;
    }
    .signatory-title {
      font-size: 8.5pt;
      color: #64748b;
    }
    .verification-block {
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .qr-container {
      width: 24mm;
      height: 24mm;
      background: #fff;
      padding: 1mm;
      border: 0.3mm solid #cbd5e1;
    }
    .cert-number {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 8.5pt;
      font-weight: 700;
      color: #0f172a;
      margin-top: 1.5mm;
      letter-spacing: 0.05em;
    }
    .cert-date {
      font-size: 7.5pt;
      color: #64748b;
      margin-top: 0.5mm;
    }
    .security-notice {
      font-size: 6.5pt;
      color: #94a3b8;
      margin-top: 0.5mm;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    @media print {
      body {
        background: transparent;
        padding: 0;
      }
      .print-controls {
        display: none !important;
      }
      .certificate-canvas {
        box-shadow: none;
        width: 100vw;
        height: 100vh;
        max-width: 297mm;
        max-height: 210mm;
      }
    }
  </style>
</head>
<body>

  <!-- On-Screen Controls (hidden in print) -->
  <div class="print-controls">
    <button onclick="window.print()" class="btn">
      <?= icon('printer', ['width' => '16', 'height' => '16']) ?>
      <span>Print Certificate / Save as PDF</span>
    </button>
    <a href="<?= e(url('/admin/certificates/' . $certificate['id'] . '/jpg')) ?>" class="btn btn-outline">
      <?= icon('download', ['width' => '16', 'height' => '16']) ?>
      <span>Download JPG (Shareable)</span>
    </a>
    <a href="<?= e(url('/admin/certificates/' . $certificate['id'])) ?>" class="btn btn-outline">
      <?= icon('chevron-left', ['width' => '16', 'height' => '16']) ?>
      <span>Back to Detail</span>
    </a>
  </div>

  <!-- A4 Landscape Certificate Canvas -->
  <div class="certificate-canvas">
    <!-- Decorative Frame -->
    <div class="cert-outer-border"></div>
    <div class="cert-inner-border"></div>
    <div class="corner-ornament co-tl"></div>
    <div class="corner-ornament co-tr"></div>
    <div class="corner-ornament co-bl"></div>
    <div class="corner-ornament co-br"></div>

    <div class="cert-content">
      <!-- Header -->
      <div class="cert-header">
        <div class="brand-title">Listening Community</div>
        <div class="brand-subtitle">Suicide Prevention Campaign &bull; Official Credential</div>
      </div>

      <!-- Certificate Type -->
      <?php
        $typeHeading = match ($certificate['type'] ?? 'participation') {
          'volunteer'    => 'Certificate of Volunteer Service',
          'speaker'      => 'Certificate of Facilitation',
          'appreciation' => 'Certificate of Appreciation',
          default        => 'Certificate of Participation',
        };
      ?>
      <div class="cert-title-section">
        <h1><?= e($typeHeading) ?></h1>
        <div class="presentation-line">This is proudly presented to</div>
      </div>

      <!-- Recipient Legal Name -->
      <div>
        <div class="recipient-name">
          <?= e($certificate['recipient_name_snapshot']) ?>
        </div>
      </div>

      <!-- Event Citation & Context -->
      <div class="cert-body-text">
        in recognition of verified active participation and commitment during
        <div class="event-title">"<?= e($certificate['event_title']) ?>"</div>
        <div class="event-meta">
          <?= e(date('F d, Y', strtotime((string) ($certificate['event_start_time'] ?? $certificate['issue_date'])))) ?>
          <?php if (!empty($certificate['event_venue_name'])): ?>
            &bull; <?= e($certificate['event_venue_name']) ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Signatures & Verification Block -->
      <div class="cert-footer">
        <!-- Coordinator Signatory -->
        <div class="signature-block">
          <div class="signature-line"></div>
          <div class="signatory-name"><?= e($signatories['coordinator']['name']) ?></div>
          <div class="signatory-title"><?= e($signatories['coordinator']['title']) ?></div>
        </div>

        <!-- Center QR Verification -->
        <div class="verification-block">
          <div class="qr-container">
            <?= $qrSvg ?>
          </div>
          <div class="cert-number"><?= e($certificate['certificate_number']) ?></div>
          <div class="cert-date">Issued on <?= e(date('M d, Y', strtotime((string) $certificate['issue_date']))) ?></div>
          <div class="security-notice">Scan QR to verify authentic status at teami.in/LC</div>
        </div>

        <!-- Organization Signatory -->
        <div class="signature-block">
          <div class="signature-line"></div>
          <div class="signatory-name"><?= e($signatories['organization']['name']) ?></div>
          <div class="signatory-title"><?= e($signatories['organization']['title']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($autoPrint)): ?>
    <script>
      window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { window.print(); }, 400);
      });
    </script>
  <?php endif; ?>

</body>
</html>
