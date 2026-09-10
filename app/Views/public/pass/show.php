<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Official Attendance Pass') ?></title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
      color: #1e293b;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .pass-container {
      width: 100%;
      max-width: 560px;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      border: 1px solid #e2e8f0;
      overflow: hidden;
    }
    .pass-header {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #ffffff;
      padding: 1.75rem 1.5rem;
      text-align: center;
    }
    .pass-header .brand {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      opacity: 0.8;
      margin-bottom: 0.35rem;
    }
    .pass-header h1 {
      font-size: 1.4rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: 0.02em;
    }
    .pass-header .campaign {
      font-size: 0.85rem;
      opacity: 0.85;
      margin-top: 0.25rem;
    }
    .pass-body {
      padding: 1.75rem 1.5rem;
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
      font-size: 2rem;
      font-weight: 700;
      color: #0f172a;
      letter-spacing: 0.06em;
    }
    .code-section .badge {
      display: inline-block;
      margin-top: 0.5rem;
      padding: 0.25rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      background: #dcfce7;
      color: #15803d;
      border-radius: 9999px;
    }
    .detail-group {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      font-size: 0.95rem;
    }
    .detail-item .label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      margin-bottom: 0.15rem;
    }
    .detail-item .value {
      font-weight: 600;
      color: #0f172a;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .pass-footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 1rem 1.5rem;
      text-align: center;
      font-size: 0.75rem;
      color: #64748b;
      line-height: 1.4;
    }
    .action-bar {
      margin-top: 1.25rem;
      display: flex;
      justify-content: center;
      gap: 0.75rem;
    }
    .btn {
      padding: 0.5rem 1.25rem;
      font-size: 0.85rem;
      font-weight: 600;
      border-radius: 6px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }
    .btn-print {
      background: #0f172a;
      color: #ffffff;
      border: 1px solid #0f172a;
    }
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .action-bar {
        display: none;
      }
      .pass-container {
        border: 2px solid #000;
        box-shadow: none;
        max-width: 100%;
      }
    }
  </style>
</head>
<body>

  <div class="pass-container">
    <!-- Pass Header -->
    <div class="pass-header">
      <div class="brand">Listening Community &bull; Suicide Prevention Campaign</div>
      <h1>Official Attendance Pass</h1>
      <div class="campaign"><?= e($pass['campaign_title'] ?? '') ?></div>
    </div>

    <!-- Pass Content -->
    <div class="pass-body">
      <!-- Pass Code & Valid Badge -->
      <div class="code-section">
        <div class="label">Attendance Pass Code</div>
        <div class="code"><?= e($pass['registration_code'] ?? '') ?></div>
        <div class="badge">&#10003; Confirmed Attendance Pass</div>
      </div>

      <!-- Operational Information -->
      <div class="detail-group">
        <div class="detail-item">
          <div class="label">Attendee Name</div>
          <div class="value" style="font-size: 1.15rem;"><?= e($pass['attendee_name'] ?? '') ?></div>
          <div style="font-size: 0.8rem; color: #64748b; text-transform: capitalize; margin-top: 0.15rem;">
            <?= e($pass['category'] ?? 'Community') ?>
          </div>
        </div>

        <div class="detail-item">
          <div class="label">Event Session</div>
          <div class="value"><?= e($pass['event_title'] ?? '') ?></div>
        </div>

        <div class="detail-grid">
          <div class="detail-item">
            <div class="label">Date & Time</div>
            <div class="value">
              <?= e(date('M d, Y', strtotime((string) $pass['event_start_time']))) ?><br>
              <span style="font-weight: normal; font-size: 0.85rem; color: #64748b;">
                <?= e(date('H:i', strtotime((string) $pass['event_start_time']))) ?> &ndash; <?= e(date('H:i', strtotime((string) $pass['event_end_time']))) ?>
              </span>
            </div>
          </div>

          <div class="detail-item">
            <div class="label">Format & Venue</div>
            <div class="value" style="text-transform: capitalize;">
              <?= e($pass['event_format'] ?? 'in_person') ?><br>
              <span style="font-weight: normal; font-size: 0.85rem; color: #64748b;">
                <?php if (!empty($pass['venue_name'])): ?>
                  <?= e($pass['venue_name']) ?>
                <?php elseif (!empty($pass['online_meeting_url'])): ?>
                  Online Session
                <?php else: ?>
                  Main Hall
                <?php endif; ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pass Footer -->
    <div class="pass-footer">
      Please present this pass upon arrival at the venue for verification.<br>
      This pass contains no contact personal data &bull; Verified via teami.in/LC
    </div>
  </div>

  <div class="action-bar">
    <button onclick="window.print()" class="btn btn-print">
      <span>&#128424; Print Pass</span>
    </button>
  </div>

</body>
</html>
