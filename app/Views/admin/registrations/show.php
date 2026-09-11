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
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/registrations')) ?>" class="btn btn-outline btn-sm btn-icon" title="All Registrations" aria-label="All Registrations">
        <?= icon('arrow-left', ['width' => '14', 'height' => '14']) ?>
      </a>
      <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
          <h1 style="margin: 0;">Registration #<?= e((string) $registration['id']) ?></h1>
          <code style="font-size: var(--font-size-sm); font-weight: var(--font-weight-bold); background-color: var(--bg-surface-subtle); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); color: var(--color-primary-dark);">
            <?= e($registration['registration_code']) ?>
          </code>
          <span class="badge badge-pill <?= e($badgeClass) ?>" style="font-size: var(--font-size-xs); text-transform: uppercase;">
            <span class="badge-dot" aria-hidden="true"></span>
            <?= e($status) ?>
          </span>
        </div>
        <p style="margin-top: 0.25rem;">
          Enrolled on <?= e(date('M d, Y H:i', strtotime((string) $registration['created_at']))) ?>
        </p>
      </div>
    </div>
  </div>

  <!-- Quick Actions Toolbar -->
  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/registrations')) ?>" class="btn btn-outline btn-sm">
      <?= icon('list', ['width' => '14', 'height' => '14']) ?>
      <span>Directory</span>
    </a>
    <a href="<?= e(url('/admin/registrations/' . $registration['id'] . '/pass')) ?>" class="btn btn-primary btn-sm" title="View formatted digital pass">
      <?= icon('credit-card', ['width' => '14', 'height' => '14']) ?>
      <span>View Pass</span>
    </a>
    <a href="<?= e(url('/admin/registrations/' . $registration['id'] . '/print')) ?>" target="_blank" class="btn btn-outline btn-sm" title="Print physical attendance pass">
      <?= icon('printer', ['width' => '14', 'height' => '14']) ?>
      <span>Print Pass</span>
    </a>
  </div>
</div>

<!-- Privacy Shield Notification for Masked Roles -->
<?php if (in_array($userRole, ['staff', 'viewer'], true)): ?>
  <div class="alert alert-info mb-6" style="display: flex; align-items: center; gap: 0.75rem; font-size: var(--font-size-sm); background-color: var(--bg-surface-subtle); border-left: 4px solid var(--color-primary);">
    <div style="color: var(--color-primary); display: flex; align-items: center;">
      <?= icon('shield', ['width' => '20', 'height' => '20']) ?>
    </div>
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
          <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-top: 0.25rem; display: flex; flex-direction: column; gap: 0.25rem;">
            <div style="display: flex; align-items: center; gap: 0.35rem;">
              <span style="color: var(--color-success); display: inline-flex;"><?= icon('check', ['width' => '13', 'height' => '13']) ?></span>
              <span>Guidelines Consent: <?= !empty($registration['participant_agreed_guidelines_at']) ? e(date('M d, Y H:i', strtotime((string) $registration['participant_agreed_guidelines_at']))) : 'Verified' ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.35rem;">
              <span style="color: var(--color-success); display: inline-flex;"><?= icon('check', ['width' => '13', 'height' => '13']) ?></span>
              <span>Privacy Consent: <?= !empty($registration['participant_privacy_consent_at']) ? e(date('M d, Y H:i', strtotime((string) $registration['participant_privacy_consent_at']))) : 'Verified' ?></span>
            </div>
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
      Registration Status &amp; Operational Controls
    </h2>
  </div>

  <div class="card-body" style="padding: 1.25rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 2rem; justify-content: space-between; align-items: flex-start;">
      <!-- Status & Notes Summary -->
      <div style="max-width: 450px;">
        <div class="mb-3">
          <span class="text-secondary" style="font-size: var(--font-size-sm);">Lifecycle Status:</span><br>
          <span class="badge badge-pill <?= e($badgeClass) ?>" style="font-size: var(--font-size-sm); text-transform: uppercase; margin-top: 0.25rem;">
            <span class="badge-dot" aria-hidden="true"></span>
            <?= e($status) ?>
          </span>
        </div>

        <?php
          $attStatus = $registration['attendance_status'] ?? 'unmarked';
          $attBadgeClass = match ($attStatus) {
            'attended' => 'badge-success',
            'absent'   => 'badge-danger',
            'excused'  => 'badge-warning',
            default    => 'badge-neutral',
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
            <span class="badge badge-pill <?= e($attBadgeClass) ?>" style="font-size: var(--font-size-xs); text-transform: uppercase;">
              <span class="badge-dot" aria-hidden="true"></span>
              <?= e($attStatus) ?>
            </span>
            <a href="<?= e(url('/admin/events/' . $registration['event_id'] . '/attendance')) ?>" class="text-primary" style="font-size: var(--font-size-xs); text-decoration: underline; display: inline-flex; align-items: center; gap: 0.25rem;">
              <span>View Event Attendance Roster</span>
              <?= icon('arrow-right', ['width' => '12', 'height' => '12']) ?>
            </a>
          </div>
          <?php if ($attStatus === 'attended' && !empty($registration['checked_in_at'])): ?>
            <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-top: 0.4rem; line-height: 1.5; display: flex; flex-direction: column; gap: 0.2rem;">
              <div style="display: flex; align-items: center; gap: 0.4rem;">
                <?= icon('clock', ['width' => '13', 'height' => '13']) ?>
                <span>Checked in: <strong><?= e(date('M d, Y H:i:s', strtotime((string) $registration['checked_in_at']))) ?></strong></span>
              </div>
              <?php if (!empty($registration['checked_in_by_name'])): ?>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                  <?= icon('user', ['width' => '13', 'height' => '13']) ?>
                  <span>Verified by: <strong><?= e($registration['checked_in_by_name']) ?></strong></span>
                </div>
              <?php endif; ?>
              <?php if (!empty($registration['check_in_method'])): ?>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                  <?= icon('check-circle', ['width' => '13', 'height' => '13']) ?>
                  <span>Intake Method: <strong><?= e($methodLabel) ?></strong></span>
                </div>
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
              <button type="submit" class="btn btn-primary" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <?= icon('check', ['width' => '14', 'height' => '14']) ?>
                <span>Approve Pass</span>
              </button>
            </form>

            <?php if ($isEventFull): ?>
              <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/waitlist')) ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;" title="Event is full; move application to waitlist">
                  <?= icon('bookmark', ['width' => '14', 'height' => '14']) ?>
                  <span>Move to Waitlist</span>
                </button>
              </form>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Action: Promote Waitlisted -->
          <?php if ($status === 'waitlisted'): ?>
            <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/promote')) ?>" method="POST">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <?= icon('arrow-up', ['width' => '14', 'height' => '14']) ?>
                <span>Promote to Confirmed</span>
              </button>
            </form>
          <?php endif; ?>

          <!-- Action: Cancel Registration -->
          <?php if (in_array($status, ['confirmed', 'pending', 'waitlisted'], true)): ?>
            <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/cancel')) ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this registration?');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-danger" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <?= icon('x', ['width' => '14', 'height' => '14']) ?>
                <span>Cancel Registration</span>
              </button>
            </form>
          <?php endif; ?>

          <!-- Action: Reactivate Cancelled -->
          <?php if ($status === 'cancelled'): ?>
            <form action="<?= e(url('/admin/registrations/' . $registration['id'] . '/reactivate')) ?>" method="POST" onsubmit="return confirm('Reactivate this registration? A fresh pass code will be generated.');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <?= icon('refresh-cw', ['width' => '14', 'height' => '14']) ?>
                <span>Reactivate Registration</span>
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
