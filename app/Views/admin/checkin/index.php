<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h1 style="margin: 0; font-size: var(--font-size-2xl);">Check-In Console</h1>
    <p class="text-secondary" style="margin: 0.25rem 0 0 0; font-size: var(--font-size-sm);">
      Select an active session to open the mobile scanner or desk check-in console.
    </p>
  </div>
</div>

<?php if (empty($events)): ?>
  <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">&#128197;</div>
    <h2 style="font-size: var(--font-size-lg); margin-bottom: 0.5rem;">No Active Sessions Open for Check-In</h2>
    <p class="text-secondary" style="max-width: 480px; margin: 0 auto 1.5rem auto; font-size: var(--font-size-sm);">
      Check-in desks are available for published and ongoing events. When a workshop or circle is scheduled, it will appear here for volunteer check-in.
    </p>
    <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline">View All Events</a>
  </div>
<?php else: ?>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
    <?php foreach ($events as $event): ?>
      <?php
        $status = $event['status'] ?? 'published';
        $statusBadge = match ($status) {
          'published' => 'badge-primary',
          'ongoing'   => 'badge-success',
          'completed' => 'badge-secondary',
          default     => 'badge-light',
        };
        $metrics = $event['attendance_metrics'] ?? ['confirmed' => 0, 'attended' => 0, 'turnout_percentage' => 0.0];
        $confirmed = (int) ($metrics['confirmed'] ?? 0);
        $attended = (int) ($metrics['attended'] ?? 0);
        $turnout = (float) ($metrics['turnout_percentage'] ?? 0.0);
      ?>
      <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--color-primary);">
        <div>
          <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
            <span class="badge <?= e($statusBadge) ?>" style="text-transform: uppercase; font-size: var(--font-size-xs);">
              <?= e($status) ?>
            </span>
            <span class="badge badge-light" style="text-transform: capitalize; font-size: var(--font-size-xs);">
              <?= e($event['format'] ?? 'in_person') ?>
            </span>
          </div>

          <h2 style="font-size: var(--font-size-lg); margin: 0 0 0.5rem 0;">
            <a href="<?= e(url('/admin/checkin/event/' . $event['id'])) ?>" style="color: var(--text-primary); text-decoration: none;">
              <?= e($event['title']) ?>
            </a>
          </h2>
          <div class="text-secondary" style="font-size: var(--font-size-xs); margin-bottom: 1rem;">
            <?= e($event['campaign_title'] ?? '') ?>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: var(--font-size-xs); color: var(--text-secondary); margin-bottom: 1.25rem;">
            <div>
              <span>&#128197;</span> <strong><?= e(date('D, M d, Y', strtotime((string) $event['start_time']))) ?></strong>
            </div>
            <div>
              <span>&#9200;</span> <?= e(date('h:i A', strtotime((string) $event['start_time']))) ?> &ndash; <?= e(date('h:i A', strtotime((string) $event['end_time']))) ?>
            </div>
            <div>
              <span>&#128205;</span> <?= e(!empty($event['venue_name']) ? $event['venue_name'] : (!empty($event['online_meeting_url']) ? 'Online Session' : 'Main Venue')) ?>
            </div>
          </div>

          <!-- Quick Progress / Turnout Bar -->
          <div style="background-color: var(--bg-surface-subtle); border-radius: 6px; padding: 0.75rem; margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; font-size: var(--font-size-xs); margin-bottom: 0.35rem;">
              <span>Attended: <strong><?= e((string) $attended) ?> / <?= e((string) $confirmed) ?></strong></span>
              <span style="font-weight: bold; color: var(--color-primary);"><?= e((string) $turnout) ?>% Turnout</span>
            </div>
            <div style="background: var(--border-color); border-radius: 9999px; height: 6px; overflow: hidden;">
              <div style="background: var(--color-primary); height: 100%; width: <?= e((string) min(100, $turnout)) ?>%;"></div>
            </div>
          </div>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; pt-2;">
          <a href="<?= e(url('/admin/checkin/event/' . $event['id'])) ?>" class="btn btn-primary btn-sm" style="flex: 1; text-align: center;">
            <span>&#9989; Open Scanner</span>
          </a>
          <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="btn btn-outline btn-sm" title="View Full Attendance Roster">
            <span>&#128101; Roster</span>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
