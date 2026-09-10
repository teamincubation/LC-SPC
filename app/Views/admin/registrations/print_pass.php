<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Attendance Pass') ?></title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      color: #111;
      background-color: #f7f7f7;
      padding: 2rem;
    }
    .print-container {
      max-width: 600px;
      margin: 0 auto;
      background: #fff;
      border: 2px solid #222;
      border-radius: 8px;
      padding: 2rem;
    }
    .header {
      text-align: center;
      border-bottom: 2px solid #222;
      padding-bottom: 1rem;
      margin-bottom: 1.5rem;
    }
    .header h1 {
      font-size: 1.5rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.25rem;
    }
    .header .subtitle {
      font-size: 0.85rem;
      color: #555;
    }
    .code-box {
      text-align: center;
      background: #f0f0f0;
      border: 1px dashed #444;
      padding: 1rem;
      margin-bottom: 1.5rem;
      border-radius: 4px;
    }
    .code-box .code {
      font-size: 2rem;
      font-family: "Courier New", Courier, monospace;
      font-weight: bold;
      letter-spacing: 0.08em;
    }
    .code-box .status {
      display: inline-block;
      margin-top: 0.35rem;
      padding: 0.2rem 0.6rem;
      font-size: 0.75rem;
      font-weight: bold;
      text-transform: uppercase;
      background: #222;
      color: #fff;
      border-radius: 3px;
    }
    .details-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
    }
    .details-table td {
      padding: 0.5rem 0;
      vertical-align: top;
    }
    .details-table .label {
      width: 35%;
      color: #555;
      font-size: 0.85rem;
      text-transform: uppercase;
    }
    .details-table .val {
      font-weight: 600;
    }
    .footer {
      text-align: center;
      border-top: 1px solid #ccc;
      padding-top: 1rem;
      font-size: 0.8rem;
      color: #666;
    }
    .no-print-bar {
      max-width: 600px;
      margin: 0 auto 1.5rem auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn {
      padding: 0.5rem 1rem;
      font-size: 0.9rem;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .btn-print {
      background: #000;
      color: #fff;
      border: 1px solid #000;
      font-weight: bold;
    }
    .btn-close {
      background: #fff;
      color: #333;
      border: 1px solid #ccc;
    }
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .no-print-bar {
        display: none;
      }
      .print-container {
        border: 2px solid #000;
        max-width: 100%;
        margin: 0;
        box-shadow: none;
      }
    }
  </style>
</head>
<body>

  <div class="no-print-bar">
    <button onclick="window.close()" class="btn btn-close">Close</button>
    <button onclick="window.print()" class="btn btn-print">&#128424; Print Pass</button>
  </div>

  <div class="print-container">
    <div class="header">
      <div style="font-size: 0.75rem; text-transform: uppercase; color: #555; margin-bottom: 0.2rem;">
        Listening Community &bull; Suicide Prevention Campaign
      </div>
      <h1>Attendance Pass</h1>
      <div class="subtitle"><?= e($registration['campaign_title'] ?? '') ?></div>
    </div>

    <div class="code-box">
      <div style="font-size: 0.75rem; text-transform: uppercase; color: #666; margin-bottom: 0.25rem;">Pass Code</div>
      <div class="code"><?= e($registration['registration_code'] ?? '') ?></div>
      <div class="status"><?= e(strtoupper((string) ($registration['status'] ?? 'confirmed'))) ?> PASS</div>
    </div>

    <table class="details-table">
      <tr>
        <td class="label">Attendee Name:</td>
        <td class="val"><?= e($registration['participant_name'] ?? '') ?></td>
      </tr>
      <tr>
        <td class="label">Category:</td>
        <td class="val" style="text-transform: capitalize;"><?= e($registration['participant_category'] ?? '') ?></td>
      </tr>
      <tr>
        <td class="label">Event Session:</td>
        <td class="val"><?= e($registration['event_title'] ?? '') ?></td>
      </tr>
      <tr>
        <td class="label">Schedule:</td>
        <td class="val">
          <?= e(date('l, F j, Y', strtotime((string) $registration['event_start_time']))) ?><br>
          <?= e(date('h:i A', strtotime((string) $registration['event_start_time']))) ?> &ndash; <?= e(date('h:i A', strtotime((string) $registration['event_end_time']))) ?>
        </td>
      </tr>
      <tr>
        <td class="label">Format & Venue:</td>
        <td class="val" style="text-transform: capitalize;">
          <?= e($registration['event_format'] ?? 'in_person') ?><br>
          <?php if (!empty($registration['event_venue_name'])): ?>
            <span style="font-weight: normal;"><?= e($registration['event_venue_name']) ?></span><br>
          <?php endif; ?>
          <?php if (!empty($registration['event_venue_address'])): ?>
            <span style="font-weight: normal; font-size: 0.85rem; color: #555;"><?= e($registration['event_venue_address']) ?></span>
          <?php endif; ?>
        </td>
      </tr>
    </table>

    <div class="footer">
      Please present this pass upon entry for attendee verification.<br>
      Issued by LC-SPC Administrative Portal &bull; teami.in/LC
    </div>
  </div>

</body>
</html>
