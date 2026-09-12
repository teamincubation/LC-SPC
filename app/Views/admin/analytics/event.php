<?php
/**
 * Detailed Event Analytics View
 * View: app/Views/admin/analytics/event.php
 */
?>

<div style="margin-bottom: 1.5rem;">
  <a href="<?= e(url('/admin/analytics')) ?>" style="color: #64748b; text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.25rem;">
    &larr; Back to Analytics Overview
  </a>
</div>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
  <div>
    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
      <span class="badge badge-primary"><?= ucfirst(e($event['event_type'] ?? 'offline')) ?></span>
      <span class="badge badge-secondary"><?= ucfirst(e($event['status'])) ?></span>
    </div>
    <h1 style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin: 0;">
      <?= e($event['title']) ?>
    </h1>
    <p style="color: #64748b; margin: 0.25rem 0 0 0; font-size: 0.95rem;">
      <?= date('F j, Y', strtotime($event['start_time'])) ?> &bull; <?= date('g:i A', strtotime($event['start_time'])) ?> &ndash; <?= date('g:i A', strtotime($event['end_time'])) ?>
      <?php if (!empty($event['venue_name'])): ?>
        &bull; 📍 <?= e($event['venue_name']) ?>
      <?php endif; ?>
    </p>
  </div>

  <?php if ($canExport): ?>
    <div>
      <a href="<?= e(url('/admin/analytics/event/' . $event['id'] . '/export')) ?>" class="btn btn-secondary">
        📥 Export Analytics CSV
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- 4 Key Segmented Metric Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
  <div class="card card-elevated" style="padding: 1.25rem; border-radius: 12px; border-left: 4px solid #0284c7;">
    <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Total Registrations</span>
    <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a; margin: 0.25rem 0;">
      <?= number_format($stats['total_registrations']) ?>
    </div>
    <span style="font-size: 0.8rem; color: #64748b;">Enrolled participants</span>
  </div>

  <div class="card card-elevated" style="padding: 1.25rem; border-radius: 12px; border-left: 4px solid #059669;">
    <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Checked In (Present)</span>
    <div style="font-size: 1.8rem; font-weight: 700; color: #059669; margin: 0.25rem 0;">
      <?= number_format($stats['checked_in_count']) ?>
    </div>
    <span style="font-size: 0.8rem; color: #059669; font-weight: 600;">
      <?= $stats['attendance_rate'] ?>% Turnout
    </span>
  </div>

  <div class="card card-elevated" style="padding: 1.25rem; border-radius: 12px; border-left: 4px solid #e11d48;">
    <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Non-Checked In (Absent)</span>
    <div style="font-size: 1.8rem; font-weight: 700; color: #e11d48; margin: 0.25rem 0;">
      <?= number_format($stats['non_checked_in_count']) ?>
    </div>
    <span style="font-size: 0.8rem; color: #64748b;">Pending or absent</span>
  </div>

  <div class="card card-elevated" style="padding: 1.25rem; border-radius: 12px; border-left: 4px solid #d97706;">
    <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Waitlist / Cancelled</span>
    <div style="font-size: 1.8rem; font-weight: 700; color: #d97706; margin: 0.25rem 0;">
      <?= number_format($stats['waitlisted_count'] + $stats['cancelled_count']) ?>
    </div>
    <span style="font-size: 0.8rem; color: #64748b;">
      <?= $stats['waitlisted_count'] ?> waitlist &bull; <?= $stats['cancelled_count'] ?> cancelled
    </span>
  </div>
</div>

<!-- Secondary Analytics Grids: Timeline & Geographic -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <!-- Hourly Timeline -->
  <div class="card card-elevated" style="border-radius: 12px; padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0 0 1rem 0; color: #1e293b;">
      ⏰ Hourly Check-in Distribution
    </h3>
    <?php if (empty($hourlyTimeline)): ?>
      <p style="color: #64748b; font-size: 0.9rem; margin: 0;">No check-in timestamps recorded yet.</p>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php 
          $maxCount = 1;
          foreach ($hourlyTimeline as $slot) {
              if ((int)$slot['checkin_count'] > $maxCount) $maxCount = (int)$slot['checkin_count'];
          }
        ?>
        <?php foreach ($hourlyTimeline as $slot): ?>
          <?php $pct = round(((int)$slot['checkin_count'] / $maxCount) * 100); ?>
          <div>
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.2rem;">
              <span><?= e($slot['hour_slot']) ?>:00</span>
              <strong><?= (int)$slot['checkin_count'] ?> attendee<?= (int)$slot['checkin_count'] === 1 ? '' : 's' ?></strong>
            </div>
            <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
              <div style="height: 100%; width: <?= $pct ?>%; background: #0284c7;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Geographic Place Breakdown -->
  <div class="card card-elevated" style="border-radius: 12px; padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0 0 1rem 0; color: #1e293b;">
      📍 Regional Location Breakdown
    </h3>
    <?php if (empty($geoBreakdown)): ?>
      <p style="color: #64748b; font-size: 0.9rem; margin: 0;">No regional data available.</p>
    <?php else: ?>
      <ul style="list-style: none; padding: 0; margin: 0;">
        <?php foreach ($geoBreakdown as $geo): ?>
          <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem;">
            <span style="color: #334155;"><?= e($geo['location']) ?></span>
            <strong><?= (int)$geo['count'] ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<!-- Device & Telemetry Breakdown -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <div class="card card-elevated" style="border-radius: 12px; padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0 0 1rem 0; color: #1e293b;">
      📱 Device Breakdown
    </h3>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
      <?php foreach ($deviceBreakdown as $dev): ?>
        <div style="flex: 1; min-width: 120px; background: #f8fafc; border-radius: 8px; padding: 0.75rem; text-align: center;">
          <span style="font-size: 0.8rem; color: #64748b;"><?= e($dev['device']) ?></span>
          <div style="font-size: 1.3rem; font-weight: 700; color: #0f172a;"><?= (int)$dev['count'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card card-elevated" style="border-radius: 12px; padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0 0 1rem 0; color: #1e293b;">
      🌐 Browser Breakdown
    </h3>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
      <?php foreach ($browserBreakdown as $br): ?>
        <div style="flex: 1; min-width: 100px; background: #f8fafc; border-radius: 8px; padding: 0.75rem; text-align: center;">
          <span style="font-size: 0.8rem; color: #64748b;"><?= e($br['browser']) ?></span>
          <div style="font-size: 1.3rem; font-weight: 700; color: #0f172a;"><?= (int)$br['count'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Participant Turnout Roster -->
<div class="card card-elevated" style="border-radius: 12px; overflow: hidden;">
  <div class="card-header" style="padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0; color: #1e293b;">
      Participant Attendance Roster
    </h2>

    <div style="display: flex; gap: 0.5rem;">
      <a href="?filter=all" class="btn btn-sm <?= $currentFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
      <a href="?filter=checked_in" class="btn btn-sm <?= $currentFilter === 'checked_in' ? 'btn-primary' : 'btn-secondary' ?>">Checked In</a>
      <a href="?filter=not_checked_in" class="btn btn-sm <?= $currentFilter === 'not_checked_in' ? 'btn-primary' : 'btn-secondary' ?>">Absent / Pending</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table" style="width: 100%; margin: 0; border-collapse: collapse;">
      <thead>
        <tr style="background: #f8fafc; color: #475569; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align: left;">
          <th style="padding: 1rem 1.5rem;">Participant</th>
          <th style="padding: 1rem;">Pass Code</th>
          <th style="padding: 1rem;">Place</th>
          <th style="padding: 1rem;">Attendance</th>
          <th style="padding: 1rem;">Check-in Method</th>
          <th style="padding: 1rem 1.5rem;">Network (Masked)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($roster)): ?>
          <tr>
            <td colspan="6" style="padding: 2.5rem; text-align: center; color: #64748b;">
              No participants found under this filter.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($roster as $row): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 1rem 1.5rem;">
                <strong><?= e($row['full_name']) ?></strong>
                <div style="font-size: 0.8rem; color: #64748b;">
                  <?= e($row['phone_normalized'] ?: $row['phone']) ?>
                </div>
              </td>
              <td style="padding: 1rem;">
                <span style="font-family: monospace; font-weight: 600; color: #0284c7;">
                  <?= e($row['registration_code']) ?>
                </span>
              </td>
              <td style="padding: 1rem; color: #475569;">
                <?= e($row['place'] ?: '—') ?>
              </td>
              <td style="padding: 1rem;">
                <?php if ($row['attendance_status'] === 'attended'): ?>
                  <span class="badge badge-success" style="background: #10b981; color: #fff;">
                    Present (<?= date('H:i', strtotime($row['attended_at'])) ?>)
                  </span>
                <?php else: ?>
                  <span class="badge" style="background: #f1f5f9; color: #64748b;">
                    Not Checked In
                  </span>
                <?php endif; ?>
              </td>
              <td style="padding: 1rem; font-size: 0.85rem; color: #64748b;">
                <?= e($row['check_in_method'] ?: '—') ?>
              </td>
              <td style="padding: 1rem 1.5rem; font-size: 0.8rem; color: #64748b;">
                <?= e(\App\Services\RegistrationMetadataService::maskIp($row['ip_address'])) ?> &bull; <?= e($row['device_type'] ?: 'Unknown') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
