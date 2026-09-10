<?php
  $status = $registration['status'] ?? 'confirmed';
  $badgeClass = match ($status) {
    'confirmed'  => 'badge-success',
    'pending'    => 'badge-warning',
    'waitlisted' => 'badge-secondary',
    'cancelled'  => 'badge-danger',
    default      => 'badge-light',
  };
  $canMutate = in_array($userRole, ['super_admin', 'coordinator'], true);
?>

<!-- Registration Detail Header -->
<div class="card mb-6">
  <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
        <h1 class="card-title" style="font-size: var(--font-size-xl); margin: 0;">
          Registration #<?= e((string) $registration['id']) ?>
        </h1>
        <code style="font-size: var(--font-size-md); font-weight: var(--font-weight-bold); background-color: var(--bg-surface-subtle); padding: 0.2rem 0.5rem; border-radius: var(--border-radius-sm); color: var(--color-primary-dark);">
          <?= e($registration['registration_code']) ?>
        </code>
        <span class="badge <?= e($badgeClass) ?>" style="font-size: var(--font-size-sm); text-transform: uppercase;">
          <?= e($status) ?>
        </span>
      </div>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Enrolled on <?= e(date('M d, Y H:i', strtotime((string) $registration['created_at']))) ?>
      </p>
    </div>

    <!-- Quick Actions Toolbar -->
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/registrations')) ?>" class="btn btn-outline btn-sm">&larr; Directory</a>
      <a href="<?= e(url('/admin/registrations/' . $registration['id'] . '/pass')) ?>" class="btn btn-primary btn-sm" title="View formatted digital pass">
        <span>&#127915; View Pass</span>
      </a>
      <a href="<?= e(url('/admin/registrations/' . $registration['id'] . '/print')) ?>" target="_blank" class="btn btn-outline btn-sm" title="Print physical attendance pass">
        <span>&#128424; Print Pass</span>
      </a>
    </div>
  </div>
</div>

<!-- Privacy Shield Notification for Masked Roles -->
<?php if (in_array($userRole, ['staff', 'viewer'], true)): ?>
  <div class="alert alert-info mb-6" style="display: flex; align-items: center; gap: 0.75rem; font-size: var(--font-size-sm);">
    <span style="font-size: 1.25rem;">&#128737;</span>
    <div>
      <strong>Privacy Shield Active:</strong> Personal email and phone contact details are masked for role <code><?= e($userRole) ?></code> in compliance with data minimization rules.
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-2 gap-6 mb-6">
  <!-- Card 1: Event & Schedule Information -->
  <div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
      <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: var(--font-weight-semibold); color: var(--color-primary-dark);">
        Event Session
      </h2>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
      <h3 style="font-size: var(--font-size-lg); margin-top: 0; margin-bottom: 0.25rem;">
        <?= e($registration['event_title']) ?>
      </h3>
      <div class="text-secondary mb-4" style="font-size: var(--font-size-sm);">
        Campaign: <strong><?= e($registration['campaign_title']) ?></strong>
      </div>

      <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: var(--font-size-sm);">
        <div>
          <span class="text-secondary">Schedule:</span><br>
          <strong><?= e(date('D, M d, Y &bull; H:i', strtotime((string) $registration['event_start_time']))) ?> &ndash; <?= e(date('H:i', strtotime((string) $registration['event_end_time']))) ?></strong>
        </div>

        <div>
          <span class="text-secondary">Format & Location:</span><br>
          <strong style="text-transform: capitalize;"><?= e($registration['event_format']) ?></strong>
          <?php if (!empty($registration['event_venue_name'])): ?>
            <div><?= e($registration['event_venue_name']) ?></div>
          <?php endif; ?>
          <?php if (!empty($registration['event_venue_address'])): ?>
            <div class="text-secondary"><?= e($registration['event_venue_address']) ?></div>
          <?php endif; ?>
          <?php if (!empty($registration['event_online_meeting_url'])): ?>
            <div><a href="<?= e($registration['event_online_meeting_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($registration['event_online_meeting_url']) ?></a></div>
          <?php endif; ?>
        </div>

        <div>
          <span class="text-secondary">Confirmed Attendance Capacity:</span><br>
          <strong><?= e((string) $confirmedCount) ?></strong> /
          <?php if ((int) $registration['event_capacity'] === 0): ?>
            <span>Unlimited Seats</span>
          <?php else: ?>
            <span><?= e((string) $registration['event_capacity']) ?> Seats</span>
            <?php if ($isEventFull): ?>
              <span class="badge badge-warning" style="margin-left: 0.35rem;">Full</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Attendee Profile -->
  <div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
      <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: var(--font-weight-semibold); color: var(--color-primary-dark);">
        Attendee Profile
      </h2>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
      <h3 style="font-size: var(--font-size-lg); margin-top: 0; margin-bottom: 0.25rem;">
        <?= e($registration['participant_name']) ?>
      </h3>
      <div class="mb-4">
        <span class="badge badge-light" style="text-transform: capitalize; font-size: var(--font-size-xs);">
          <?= e($registration['participant_category']) ?>
        </span>
        <?php if (!empty($registration['participant_organization'])): ?>
          <span class="text-secondary" style="font-size: var(--font-size-xs); margin-left: 0.5rem;">
            <?= e($registration['participant_organization']) ?>
          </span>
        <?php endif; ?>
      </div>

      <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: var(--font-size-sm);">
        <div>
          <span class="text-secondary">Email Address:</span><br>
          <strong><?= !empty($registration['participant_email']) ? e($registration['participant_email']) : 'None' ?></strong>
        </div>

        <div>
          <span class="text-secondary">Phone / WhatsApp:</span><br>
          <strong><?= !empty($registration['participant_phone']) ? e($registration['participant_phone']) : 'None' ?></strong>
        </div>

        <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
          <span class="text-secondary" style="font-size: var(--font-size-xs); font-weight: var(--font-weight-medium);">Compliance Records:</span>
          <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-top: 0.25rem;">
            &#10003; Guidelines Consent: <?= !empty($registration['participant_agreed_guidelines_at']) ? e(date('M d, Y H:i', strtotime((string) $registration['participant_agreed_guidelines_at']))) : 'Verified' ?><br>
            &#10003; Privacy Consent: <?= !empty($registration['participant_privacy_consent_at']) ? e(date('M d, Y H:i', strtotime((string) $registration['participant_privacy_consent_at']))) : 'Verified' ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Lifecycle Status & Operational Controls -->
<div class="card mb-6">
  <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
    <h2 style="font-size: var(--font-size-md); margin: 0; font-weight: var(--font-weight-semibold); color: var(--color-primary-dark);">
      Registration Status & Operational Controls
    </h2>
  </div>

  <div class="card-body" style="padding: 1.25rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 2rem; justify-content: space-between; align-items: flex-start;">
      <!-- Status & Notes Summary -->
      <div style="max-width: 450px;">
        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-sm);">Lifecycle Status:</span><br>
          <span class="badge <?= e($badgeClass) ?>" style="font-size: var(--font-size-md); text-transform: uppercase; margin-top: 0.25rem;">
            <?= e($status) ?>
          </span>
        </div>

        <?php
          $attStatus = $registration['attendance_status'] ?? 'unmarked';
          $attBadgeClass = match ($attStatus) {
            'attended' => 'badge-success',
            'absent'   => 'badge-danger',
            'excused'  => 'badge-warning',
            default    => 'badge-secondary',
          };
          $methodLabel = match ($registration['check_in_method'] ?? '') {
            'qr_scan'       => 'QR Scanner',
            'manual_lookup' => 'Manual Lookup',
            'override'      => 'Admin Override',
            default         => $registration['check_in_method'] ?? 'N/A',
          };
        ?>
        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-sm);">Attendance Presence:</span><br>
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; flex-wrap: wrap;">
            <span class="badge <?= e($attBadgeClass) ?>" style="font-size: var(--font-size-sm); text-transform: uppercase;">
              <?= e($attStatus) ?>
            </span>
            <a href="<?= e(url('/admin/events/' . $registration['event_id'] . '/attendance')) ?>" class="text-primary" style="font-size: var(--font-size-xs); text-decoration: underline;">
              View Event Attendance Roster &rarr;
            </a>
          </div>
          <?php if ($attStatus === 'attended' && !empty($registration['checked_in_at'])): ?>
            <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-top: 0.35rem; line-height: 1.4;">
              <div>&#128343; Checked in: <strong><?= e(date('M d, Y H:i:s', strtotime((string) $registration['checked_in_at']))) ?></strong></div>
              <?php if (!empty($registration['checked_in_by_name'])): ?>
                <div>&#128100; Verified by: <strong><?= e($registration['checked_in_by_name']) ?></strong></div>
              <?php endif; ?>
              <?php if (!empty($registration['check_in_method'])): ?>
                <div>&#128247; Intake Method: <strong><?= e($methodLabel) ?></strong></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($registration['admin_notes'])): ?>
          <div class="mb-3">
            <span class="text-secondary" style="font-size: var(--font-size-sm);">Operational Logistics Notes:</span>
            <div style="background-color: var(--bg-surface-subtle); padding: 0.5rem 0.75rem; border-radius: var(--border-radius-sm); font-size: var(--font-size-sm); margin-top: 0.25rem;">
              <?= e($registration['admin_notes']) ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Action Controls (Coordinator / Super Admin only) -->
      <?php if ($canMutate): ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; min-width: 250px;">
          <!-- Action: Approve Pending -->
          <?php if ($status === 'pending'): ?>
            <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/approve')) ?>" method="POST">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary" style="width: 100%; text-align: center;">
                &#10003; Approve Pass
              </button>
            </form>

            <?php if ($isEventFull): ?>
              <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/waitlist')) ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="width: 100%; text-align: center;" title="Event is full; move application to waitlist">
                  &#9873; Move to Waitlist
                </button>
              </form>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Action: Promote Waitlisted -->
          <?php if ($status === 'waitlisted'): ?>
            <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/promote')) ?>" method="POST">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary" style="width: 100%; text-align: center;">
                &#11014; Promote to Confirmed
              </button>
            </form>
          <?php endif; ?>

          <!-- Action: Cancel Registration -->
          <?php if (in_array($status, ['confirmed', 'pending', 'waitlisted'], true)): ?>
            <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/cancel')) ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this registration?');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-danger" style="width: 100%; text-align: center;">
                &#10005; Cancel Registration
              </button>
            </form>
          <?php endif; ?>

          <!-- Action: Reactivate Cancelled -->
          <?php if ($status === 'cancelled'): ?>
            <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/reactivate')) ?>" method="POST" onsubmit="return confirm('Reactivate this registration? A fresh pass code will be generated.');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary" style="width: 100%; text-align: center;">
                &#8634; Reactivate Registration
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
