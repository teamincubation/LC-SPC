<div class="admin-page-header">
  <div class="admin-page-header-title">
    <h1>Check-In Console</h1>
    <p>Select an active session to open the mobile scanner or desk check-in console.</p>
  </div>
  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-sm">
      <?= icon('list', ['width' => '14', 'height' => '14']) ?>
      <span>All Events</span>
    </a>
  </div>
</div>

<?php if (empty($events)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">
      <?= icon('calendar', ['width' => '32', 'height' => '32']) ?>
    </div>
    <div class="empty-state-title">No Active Sessions Open for Check-In</div>
    <div class="empty-state-description">
      Check-in desks are available for published and ongoing events. When a workshop or circle is scheduled, it will appear here for volunteer check-in.
    </div>
    <div style="margin-top: 1rem;">
      <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-sm">
        <?= icon('calendar', ['width' => '14', 'height' => '14']) ?>
        <span>View All Events</span>
      </a>
    </div>
  </div>
<?php else: ?>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
    <?php foreach ($events as $event): ?>
      <?php
        $status = $event['status'] ?? 'published';
        $statusBadge = match ($status) {
          'published' => 'badge-primary',
          'ongoing'   => 'badge-success',
          'completed' => 'badge-neutral',
          default     => 'badge-neutral',
        };
        $metrics = $event['attendance_metrics'] ?? ['confirmed' => 0, 'attended' => 0, 'turnout_percentage' => 0.0];
        $confirmed = (int) ($metrics['confirmed'] ?? 0);
        $attended = (int) ($metrics['attended'] ?? 0);
        $turnout = (float) ($metrics['turnout_percentage'] ?? 0.0);
      ?>
      <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 3px solid var(--color-primary);">
        <div>
          <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
            <span class="badge badge-pill <?= e($statusBadge) ?>" style="text-transform: uppercase; font-size: var(--font-size-xs);">
              <span class="badge-dot" aria-hidden="true"></span>
              <?= e($status) ?>
            </span>
            <span class="badge badge-pill badge-neutral" style="text-transform: capitalize; font-size: var(--font-size-xs);">
              <?= e(str_replace('_', ' ', $event['format'] ?? 'in_person')) ?>
            </span>
          </div>

          <h2 style="font-size: var(--font-size-md); margin: 0 0 0.35rem 0; font-weight: var(--font-weight-semibold);">
            <a href="<?= e(url('/admin/checkin/event/' . $event['id'])) ?>" style="color: var(--text-primary); text-decoration: none;">
              <?= e($event['title']) ?>
            </a>
          </h2>
          <div class="text-secondary" style="font-size: var(--font-size-xs); margin-bottom: 0.85rem;">
            <?= e($event['campaign_title'] ?? '') ?>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: var(--font-size-xs); color: var(--text-secondary); margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.4rem;">
              <?= icon('calendar', ['width' => '13', 'height' => '13']) ?>
              <strong><?= e(date('D, M d, Y', strtotime((string) $event['start_time']))) ?></strong>
            </div>
            <div style="display: flex; align-items: center; gap: 0.4rem;">
              <?= icon('clock', ['width' => '13', 'height' => '13']) ?>
              <span><?= e(date('h:i A', strtotime((string) $event['start_time']))) ?> &ndash; <?= e(date('h:i A', strtotime((string) $event['end_time']))) ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.4rem;">
              <?= icon('map-pin', ['width' => '13', 'height' => '13']) ?>
              <span><?= e(!empty($event['venue_name']) ? $event['venue_name'] : (!empty($event['online_meeting_url']) ? 'Online Session' : 'Main Venue')) ?></span>
            </div>
          </div>

          <!-- Quick Progress / Turnout Bar -->
          <div style="background-color: var(--bg-surface-subtle); border-radius: var(--radius-sm); padding: 0.75rem; margin-bottom: 1.25rem; border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; font-size: var(--font-size-xs); margin-bottom: 0.35rem;">
              <span>Attended: <strong><?= e((string) $attended) ?> / <?= e((string) $confirmed) ?></strong></span>
              <span style="font-weight: bold; color: var(--color-primary);"><?= e((string) $turnout) ?>% Turnout</span>
            </div>
            <div style="background: var(--border-color); border-radius: 9999px; height: 6px; overflow: hidden;">
              <div style="background: var(--color-primary); height: 100%; width: <?= e((string) min(100, $turnout)) ?>%;"></div>
            </div>
          </div>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; pt-2; border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
          <a href="<?= e(url('/admin/checkin/event/' . $event['id'])) ?>" class="btn btn-primary btn-sm" style="flex: 1; text-align: center;">
            <?= icon('camera', ['width' => '14', 'height' => '14']) ?>
            <span>Open Scanner</span>
          </a>
          <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="btn btn-outline btn-sm" title="View Full Attendance Roster">
            <?= icon('users', ['width' => '14', 'height' => '14']) ?>
            <span>Roster</span>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
