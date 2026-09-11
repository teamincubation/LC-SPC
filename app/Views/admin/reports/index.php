<?php
  $totalReg = array_sum($regCounts);
  $confirmedReg = $regCounts['confirmed'] ?? 0;
  $attendedReg = $regCounts['attended'] ?? 0;
  $cancelledReg = $regCounts['cancelled'] ?? 0;

  $totalPart = array_sum($partCounts);
  $activePart = $partCounts['active'] ?? 0;
  $flaggedPart = $partCounts['flagged'] ?? 0;

  $totalEvents = array_sum($eventCounts);
  $publishedEvents = $eventCounts['published'] ?? 0;
  $completedEvents = $eventCounts['completed'] ?? 0;

  $totalCerts = $certMetrics['total'] ?? 0;
  $activeCerts = $certMetrics['active_count'] ?? 0;
?>

<!-- Reports Page Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <h1>
      <?= icon('chart', ['width' => '24', 'height' => '24']) ?>
      <span>Reports &amp; Analytics</span>
    </h1>
    <p>Institutional campaign performance, attendance verification ratios, and credential summaries.</p>
  </div>

  <div class="admin-page-header-actions">
    <button type="button" onclick="window.print()" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
      <?= icon('printer', ['width' => '14', 'height' => '14']) ?>
      <span>Print Summary</span>
    </button>
  </div>
</div>

<!-- 4 Key Institutional KPIs -->
<div class="metric-grid mb-6">
  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Total Registrations</span>
      <div class="card-metric-icon" style="background: rgba(26, 86, 219, 0.1); color: var(--primary);">
        <?= icon('clipboard', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value"><?= e((string) $totalReg) ?></div>
    <div class="card-metric-subtitle"><?= e((string) $confirmedReg) ?> confirmed &bull; <?= e((string) $attendedReg) ?> attended</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Community Participants</span>
      <div class="card-metric-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
        <?= icon('users', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value" style="color: var(--success);"><?= e((string) $totalPart) ?></div>
    <div class="card-metric-subtitle"><?= e((string) $activePart) ?> active directory records</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Events &amp; Sessions</span>
      <div class="card-metric-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--primary-light);">
        <?= icon('calendar', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value"><?= e((string) $totalEvents) ?></div>
    <div class="card-metric-subtitle"><?= e((string) $publishedEvents) ?> published &bull; <?= e((string) $completedEvents) ?> completed</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-header">
      <span class="card-metric-title">Credentials Issued</span>
      <div class="card-metric-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
        <?= icon('award', ['width' => '18', 'height' => '18']) ?>
      </div>
    </div>
    <div class="card-metric-value" style="color: var(--warning);"><?= e((string) $totalCerts) ?></div>
    <div class="card-metric-subtitle"><?= e((string) $activeCerts) ?> active verifiable certificates</div>
  </div>
</div>

<!-- Detailed Breakdown Cards -->
<div class="grid grid-cols-2 gap-6 mb-6">
  <!-- Card 1: Registration & Attendance Pipeline -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; justify-content: space-between;">
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <?= icon('check-circle', ['width' => '16', 'height' => '16']) ?>
        <span>Registration Status Breakdown</span>
      </h2>
      <a href="<?= e(url('/admin/registrations')) ?>" class="btn btn-outline btn-sm">View All</a>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php
          $statuses = [
            'confirmed' => ['label' => 'Confirmed Registrations', 'badge' => 'badge-pill badge-primary', 'count' => $regCounts['confirmed'] ?? 0],
            'attended'  => ['label' => 'Verified Attended', 'badge' => 'badge-pill badge-success', 'count' => $regCounts['attended'] ?? 0],
            'cancelled' => ['label' => 'Cancelled / Withdrawn', 'badge' => 'badge-pill badge-danger', 'count' => $regCounts['cancelled'] ?? 0],
            'waitlist'  => ['label' => 'Waitlisted', 'badge' => 'badge-pill badge-warning', 'count' => $regCounts['waitlist'] ?? 0],
          ];
        ?>
        <?php foreach ($statuses as $k => $item): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span class="<?= e($item['badge']) ?>" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
                <?= e($k) ?>
              </span>
              <span style="font-size: var(--font-size-sm); color: var(--text-primary); font-weight: 500;"><?= e($item['label']) ?></span>
            </div>
            <div style="font-weight: 700; font-size: var(--font-size-base); color: var(--text-primary);">
              <?= e((string) $item['count']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Card 2: Credential & Compliance Breakdown -->
  <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; justify-content: space-between;">
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <?= icon('shield', ['width' => '16', 'height' => '16']) ?>
        <span>Credential &amp; Compliance Integrity</span>
      </h2>
      <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline btn-sm">View All</a>
    </div>

    <div style="padding: 1.25rem;">
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
          <div>
            <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">Active Public Credentials</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-muted);">Verifiable via 256-bit cryptographic token</div>
          </div>
          <div style="font-weight: 700; font-size: var(--font-size-base); color: var(--success);">
            <?= e((string) ($certMetrics['active_count'] ?? 0)) ?>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
          <div>
            <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">Participation Certificates</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-muted);">Standard event attendee completion credentials</div>
          </div>
          <div style="font-weight: 700; font-size: var(--font-size-base); color: var(--primary);">
            <?= e((string) ($certMetrics['participation_count'] ?? 0)) ?>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
          <div>
            <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">Revoked / Void Records</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-muted);">Invalidated, superseded, or voided credentials</div>
          </div>
          <div style="font-weight: 700; font-size: var(--font-size-base); color: var(--danger);">
            <?= e((string) ($certMetrics['revoked_count'] ?? 0)) ?>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary);">Flagged Participant Records</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-muted);">Requires individual coordinator review</div>
          </div>
          <div style="font-weight: 700; font-size: var(--font-size-base); color: var(--warning);">
            <?= e((string) $flaggedPart) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Operational Attendance Rosters & Data Export Center -->
<div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
  <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h2 style="font-size: var(--font-size-base); margin: 0; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
        <?= icon('download', ['width' => '16', 'height' => '16']) ?>
        <span>Event Attendance Rosters &amp; CSV Export Center</span>
      </h2>
      <p style="font-size: var(--font-size-xs); color: var(--text-muted); margin: 0.2rem 0 0 0;">
        Download RFC 4180 compliant CSV attendance reports with role-enforced PII masking.
      </p>
    </div>

    <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-sm">
      All Events
    </a>
  </div>

  <div class="table-responsive">
    <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
      <thead>
        <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-surface-subtle); text-align: left; font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">
          <th style="padding: 0.75rem 1rem;">Event Session</th>
          <th style="padding: 0.75rem 1rem;">Campaign</th>
          <th style="padding: 0.75rem 1rem;">Start Date</th>
          <th style="padding: 0.75rem 1rem;">Status</th>
          <th style="padding: 0.75rem 1rem; text-align: right;">Export Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentEvents)): ?>
          <tr>
            <td colspan="5" style="padding: 0;">
              <div class="empty-state" style="padding: 2.5rem 1.5rem;">
                <div class="empty-state-icon"><?= icon('calendar', ['width' => '36', 'height' => '36']) ?></div>
                <h4 style="margin: 0.5rem 0 0.25rem 0; font-size: var(--font-size-base); font-weight: 600;">No Event Sessions Found</h4>
                <p style="margin: 0; font-size: var(--font-size-sm); color: var(--text-muted);">Create campaigns and events to generate attendance reports.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recentEvents as $evt): ?>
            <?php
              $statusBadge = match ($evt['status'] ?? '') {
                'published' => 'badge-pill badge-primary',
                'completed' => 'badge-pill badge-success',
                'cancelled' => 'badge-pill badge-danger',
                default     => 'badge-pill badge-neutral',
              };
            ?>
            <tr style="border-bottom: 1px solid var(--border-color); font-size: var(--font-size-sm);">
              <td style="padding: 0.75rem 1rem;">
                <a href="<?= e(url('/admin/events/' . $evt['id'])) ?>" style="font-weight: 600; color: var(--text-primary);">
                  <?= e($evt['title']) ?>
                </a>
                <div style="font-size: var(--font-size-xs); color: var(--text-muted); text-transform: capitalize;">
                  <?= e($evt['format']) ?> &bull; <?= e($evt['category']) ?>
                </div>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs); color: var(--text-secondary);">
                <?= e($evt['campaign_title'] ?? 'General') ?>
              </td>
              <td style="padding: 0.75rem 1rem; font-size: var(--font-size-xs); color: var(--text-secondary);">
                <?= e(date('M d, Y', strtotime((string) $evt['start_time']))) ?>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span class="<?= e($statusBadge) ?>" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">
                  <?= e($evt['status']) ?>
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                  <a href="<?= e(url('/admin/events/' . $evt['id'] . '/attendance')) ?>" class="btn btn-outline btn-sm" title="View Full Attendance Roster" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                    <?= icon('users', ['width' => '12', 'height' => '12']) ?>
                    <span>Roster</span>
                  </a>
                  <a href="<?= e(url('/admin/events/' . $evt['id'] . '/attendance/export')) ?>" class="btn btn-primary btn-sm" title="Download Privacy-Shielded CSV" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                    <?= icon('download', ['width' => '12', 'height' => '12']) ?>
                    <span>Export CSV</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
