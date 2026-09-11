<div class="row justify-center" style="max-width: 620px; margin: 2rem auto;">
  <div class="card p-6 text-center" style="box-shadow: 0 10px 25px rgba(0,0,0,0.06); border-top: 4px solid var(--color-primary);">
    <!-- Success Icon / Status Indicator -->
    <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--color-primary-tint); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.25rem auto;">
      <?php if ($confirmation['status'] === 'confirmed'): ?>
        &#10003;
      <?php elseif ($confirmation['status'] === 'pending'): ?>
        &#9203;
      <?php else: ?>
        &#8987;
      <?php endif; ?>
    </div>

    <!-- Header -->
    <span class="text-caption text-uppercase letter-spacing-wide text-muted font-weight-medium">
      Registration Received
    </span>
    
    <h1 class="card-title mb-2 mt-1" style="font-size: var(--font-size-2xl);">
      <?php if ($confirmation['status'] === 'confirmed'): ?>
        You're Registered!
      <?php elseif ($confirmation['status'] === 'pending'): ?>
        Registration Pending Approval
      <?php else: ?>
        Added to Official Waitlist
      <?php endif; ?>
    </h1>

    <p class="text-secondary mb-4" style="font-size: var(--font-size-md);">
      Thank you, <strong><?= e($confirmation['attendee_name']) ?></strong>.
      <?php if ($confirmation['status'] === 'confirmed'): ?>
        Your registration is confirmed. Please save your registration code below.
      <?php elseif ($confirmation['status'] === 'pending'): ?>
        Your registration is being reviewed by the event coordinator.
      <?php else: ?>
        You are currently on the waitlist. You will be notified if a seat becomes available.
      <?php endif; ?>
    </p>

    <!-- Registration Code Display Box -->
    <div class="p-4 mb-4" style="background: var(--bg-surface-subtle); border-radius: 8px; border: 1px dashed var(--border-color);">
      <span class="text-caption text-muted text-uppercase d-block mb-1">Your Registration Code</span>
      <div style="font-family: monospace; font-size: 1.6rem; font-weight: 700; letter-spacing: 0.1em; color: var(--color-primary);">
        <?= e($confirmation['registration_code']) ?>
      </div>
      <span class="text-caption text-muted mt-1 d-block">
        Use this code to check your registration status or retrieve your pass anytime.
      </span>
    </div>

    <!-- Event Summary Details -->
    <div class="text-left border-top border-bottom py-4 mb-5 text-caption text-secondary" style="line-height: 1.8;">
      <div class="flex justify-between py-1 border-bottom-subtle">
        <span class="text-muted">Event:</span>
        <strong class="text-dark text-right"><?= e($confirmation['event_title']) ?></strong>
      </div>
      <div class="flex justify-between py-1 border-bottom-subtle">
        <span class="text-muted">Campaign Initiative:</span>
        <span class="text-right"><?= e($confirmation['campaign_title']) ?></span>
      </div>
      <div class="flex justify-between py-1 border-bottom-subtle">
        <span class="text-muted">Date &amp; Time:</span>
        <span class="text-right font-weight-medium text-dark">
          <?= e(date('l, M d, Y &bull; h:i A', strtotime((string) $confirmation['start_time']))) ?>
        </span>
      </div>
      <div class="flex justify-between py-1 border-bottom-subtle">
        <span class="text-muted">Format:</span>
        <span class="text-right text-capitalize"><?= e(str_replace('_', ' ', (string) $confirmation['format'])) ?></span>
      </div>
      <?php if (!empty($confirmation['venue_name'])): ?>
        <div class="flex justify-between py-1">
          <span class="text-muted">Venue:</span>
          <span class="text-right"><?= e($confirmation['venue_name']) ?></span>
        </div>
      <?php endif; ?>
      <div class="flex justify-between py-1">
        <span class="text-muted">Status:</span>
        <span class="text-right">
          <?php if ($confirmation['status'] === 'confirmed'): ?>
            <span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Confirmed</span>
          <?php elseif ($confirmation['status'] === 'pending'): ?>
            <span class="badge badge-info"><span class="badge-dot" aria-hidden="true"></span> Pending Approval</span>
          <?php else: ?>
            <span class="badge badge-warning"><span class="badge-dot" aria-hidden="true"></span> Waitlisted</span>
          <?php endif; ?>
        </span>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-col gap-3">
      <?php if (!empty($confirmation['can_access_pass'])): ?>
        <a href="<?= e(url('/registration/pass/' . rawurlencode((string) $confirmation['registration_code']))) ?>" class="btn btn-primary btn-lg w-100 text-center">
          View &amp; Print Attendance Pass &rarr;
        </a>
      <?php endif; ?>

      <div class="flex justify-center gap-3 mt-2">
        <a href="<?= e(url('/events')) ?>" class="btn btn-outline btn-sm">
          Browse More Events
        </a>
        <a href="<?= e(url('/registration/status')) ?>" class="btn btn-ghost btn-sm">
          Check Status Later
        </a>
      </div>
    </div>
  </div>
</div>
