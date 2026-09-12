<?php
/**
 * Admin Analytics Overview View
 * View: app/Views/admin/analytics/index.php
 */
?>

<div class="page-header" style="margin-bottom: 2rem;">
  <div>
    <h1 style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin: 0;">
      📊 Registration & Attendance Analytics
    </h1>
    <p style="color: #64748b; margin: 0.25rem 0 0 0; font-size: 0.95rem;">
      Real-time participant turnout, check-in segmentation, and regional telemetry.
    </p>
  </div>
</div>

<!-- High-Level KPI Summary Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
  <div class="card card-elevated" style="padding: 1.5rem; border-radius: 12px; border-left: 4px solid #0284c7;">
    <span style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Total Registrations</span>
    <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;"><?= number_format($totalAllRegistrations) ?></div>
    <span style="font-size: 0.85rem; color: #64748b;">Cumulative enrollments</span>
  </div>

  <div class="card card-elevated" style="padding: 1.5rem; border-radius: 12px; border-left: 4px solid #059669;">
    <span style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Total Checked In</span>
    <div style="font-size: 2rem; font-weight: 700; color: #059669; margin: 0.35rem 0;"><?= number_format($totalAllCheckedIn) ?></div>
    <span style="font-size: 0.85rem; color: #64748b;">Verified at venue</span>
  </div>

  <div class="card card-elevated" style="padding: 1.5rem; border-radius: 12px; border-left: 4px solid #7c3aed;">
    <span style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Overall Turnout Rate</span>
    <div style="font-size: 2rem; font-weight: 700; color: #7c3aed; margin: 0.35rem 0;"><?= $overallAttendanceRate ?>%</div>
    <span style="font-size: 0.85rem; color: #64748b;">Turnout ratio</span>
  </div>
</div>

<!-- Events Analytics Table -->
<div class="card card-elevated" style="border-radius: 12px; overflow: hidden;">
  <div class="card-header" style="padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
    <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0; color: #1e293b;">Event Turnout Breakdown</h2>
  </div>

  <div class="table-responsive">
    <table class="table" style="width: 100%; margin: 0; border-collapse: collapse;">
      <thead>
        <tr style="background: #f8fafc; color: #475569; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align: left;">
          <th style="padding: 1rem 1.5rem;">Event</th>
          <th style="padding: 1rem;">Status</th>
          <th style="padding: 1rem;">Registered</th>
          <th style="padding: 1rem;">Checked In</th>
          <th style="padding: 1rem;">Absent / Pending</th>
          <th style="padding: 1rem;">Turnout %</th>
          <th style="padding: 1rem 1.5rem; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($events)): ?>
          <tr>
            <td colspan="7" style="padding: 2.5rem; text-align: center; color: #64748b;">
              No events found.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($events as $evt): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 1rem 1.5rem;">
                <a href="<?= e(url('/admin/analytics/event/' . $evt['id'])) ?>" style="font-weight: 600; color: #0284c7; text-decoration: none;">
                  <?= e($evt['title']) ?>
                </a>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem;">
                  <?= date('M j, Y', strtotime($evt['start_time'])) ?> &bull; <?= ucfirst(e($evt['event_type'] ?? 'offline')) ?>
                </div>
              </td>
              <td style="padding: 1rem;">
                <span class="badge badge-<?= $evt['status'] === 'published' ? 'success' : ($evt['status'] === 'completed' ? 'primary' : 'secondary') ?>">
                  <?= ucfirst(e($evt['status'])) ?>
                </span>
              </td>
              <td style="padding: 1rem; font-weight: 600;">
                <?= number_format((int) ($evt['total_registrations'] ?? 0)) ?>
              </td>
              <td style="padding: 1rem; font-weight: 600; color: #059669;">
                <?= number_format((int) ($evt['checked_in_count'] ?? 0)) ?>
              </td>
              <td style="padding: 1rem; color: #64748b;">
                <?= number_format((int) ($evt['non_checked_in_count'] ?? 0)) ?>
              </td>
              <td style="padding: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <div style="flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; min-width: 60px;">
                    <div style="height: 100%; width: <?= (float) ($evt['attendance_rate'] ?? 0) ?>%; background: #059669;"></div>
                  </div>
                  <span style="font-weight: 600; font-size: 0.85rem;"><?= (float) ($evt['attendance_rate'] ?? 0) ?>%</span>
                </div>
              </td>
              <td style="padding: 1rem 1.5rem; text-align: right;">
                <a href="<?= e(url('/admin/analytics/event/' . $evt['id'])) ?>" class="btn btn-secondary btn-sm">
                  View Analytics &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
