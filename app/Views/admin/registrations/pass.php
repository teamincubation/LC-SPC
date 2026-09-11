<?php
  $status = $registration['status'] ?? 'confirmed';
  $badgeClass = match ($status) {
    'confirmed'  => 'badge-success',
    'pending'    => 'badge-warning',
    'waitlisted' => 'badge-secondary',
    'cancelled'  => 'badge-danger',
    default      => 'badge-light',
  };
?>

<div style="max-width: 650px; margin: 0 auto;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
    <a href="<?= e(url('/admin/registrations/' . $registration['id'])) ?>" class="btn btn-outline btn-sm">
      <?= icon('arrow-left', ['width' => '14', 'height' => '14']) ?>
      <span>Back to Registration</span>
    </a>
    <a href="<?= e(url('/admin/registrations/' . $registration['id'] . '/print')) ?>" target="_blank" class="btn btn-primary btn-sm">
      <?= icon('printer', ['width' => '14', 'height' => '14']) ?>
      <span>Print Pass</span>
    </a>
  </div>

  <!-- Attendance Pass Card -->
  <div class="card" style="border: 2px solid var(--color-primary); box-shadow: 0 10px 25px rgba(0,0,0,0.08); overflow: hidden;">
    <!-- Pass Header -->
    <div style="background: linear-gradient(135deg, var(--color-primary-dark), var(--color-primary)); color: #fff; padding: 1.5rem; text-align: center;">
      <div style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.85; margin-bottom: 0.25rem;">
        Listening Community &bull; Suicide Prevention Campaign
      </div>
      <h1 style="font-size: var(--font-size-xl); margin: 0; color: #fff; font-weight: var(--font-weight-bold);">
        Official Attendance Pass
      </h1>
      <div style="font-size: var(--font-size-sm); opacity: 0.9; margin-top: 0.25rem;">
        <?= e($registration['campaign_title']) ?>
      </div>
    </div>

    <!-- Pass Body -->
    <div style="padding: 2rem; background-color: var(--bg-surface);">
      <!-- Pass Code & Status -->
      <div style="text-align: center; border-bottom: 1px dashed var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
        <div style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 0.35rem;">
          Registration Pass Code
        </div>
        <div style="font-size: 2rem; font-family: monospace; font-weight: bold; color: var(--color-primary-dark); letter-spacing: 0.05em;">
          <?= e($registration['registration_code']) ?>
        </div>
        <div style="margin-top: 0.5rem;">
          <span class="badge badge-pill <?= e($badgeClass) ?>" style="font-size: var(--font-size-sm); text-transform: uppercase; padding: 0.3rem 0.8rem;">
            <?= e($status) ?> Pass
          </span>
        </div>
      </div>

      <!-- Attendee & Event Details -->
      <div style="display: flex; flex-direction: column; gap: 1rem; font-size: var(--font-size-sm);">
        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Attendee Name:</span><br>
          <strong style="font-size: var(--font-size-lg); color: var(--text-primary);"><?= e($registration['participant_name']) ?></strong>
          <span class="badge badge-light" style="text-transform: capitalize; margin-left: 0.5rem; vertical-align: middle;">
            <?= e($registration['participant_category']) ?>
          </span>
        </div>

        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Event Session:</span><br>
          <strong style="font-size: var(--font-size-md); color: var(--text-primary);"><?= e($registration['event_title']) ?></strong>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Schedule:</span><br>
            <strong><?= e(date('M d, Y', strtotime((string) $registration['event_start_time']))) ?></strong><br>
            <span class="text-secondary"><?= e(date('H:i', strtotime((string) $registration['event_start_time']))) ?> &ndash; <?= e(date('H:i', strtotime((string) $registration['event_end_time']))) ?></span>
          </div>

          <div>
            <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase;">Location / Format:</span><br>
            <strong style="text-transform: capitalize;"><?= e($registration['event_format']) ?></strong><br>
            <span class="text-secondary">
              <?php if (!empty($registration['event_venue_name'])): ?>
                <?= e($registration['event_venue_name']) ?>
              <?php elseif (!empty($registration['event_online_meeting_url'])): ?>
                Online Session
              <?php endif; ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Pass Footer -->
    <div style="background-color: var(--bg-surface-subtle); border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; text-align: center; font-size: var(--font-size-xs); color: var(--text-secondary);">
      Please present this pass code upon arrival at the venue for attendee verification.
    </div>
  </div>
</div>
