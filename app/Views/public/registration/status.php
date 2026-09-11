<div class="row justify-center" style="max-width: 600px; margin: 2rem auto;">
  <div class="card p-6" style="box-shadow: 0 8px 24px rgba(0,0,0,0.06); border-top: 4px solid var(--color-primary);">
    <div class="text-center mb-5">
      <span class="badge badge-primary mb-2">Self-Service Portal</span>
      <h1 class="card-title mb-1" style="font-size: var(--font-size-2xl);">
        Check Registration Status
      </h1>
      <p class="text-secondary text-caption mb-0">
        Enter your official Registration Code to check your current registration state or retrieve your attendance pass.
      </p>
    </div>

    <!-- Lookup Form -->
    <form method="POST" action="<?= e(url('/registration/status')) ?>" class="mb-5" novalidate>
      <?= csrf_field() ?>

      <div class="form-group mb-3">
        <label for="reg_code_input" class="form-label font-weight-medium">
          Registration Code
        </label>
        <div class="flex gap-2">
          <input type="text" id="reg_code_input" name="code" class="form-control" placeholder="e.g. REG-26-XXXXX" value="<?= e($code ?? '') ?>" required maxlength="30" style="font-family: monospace; font-size: 1.1rem; text-transform: uppercase;">
          <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
            Lookup Status &rarr;
          </button>
        </div>
        <span class="form-hint text-muted text-caption">Format: <span class="code-inline">REG-YY-XXXXX</span> (located on your confirmation receipt).</span>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger mt-3" role="alert">
          <div class="alert-content">
            <?= e($error) ?>
          </div>
        </div>
      <?php endif; ?>
    </form>

    <!-- Status Result Card (Rendered upon successful query) -->
    <?php if (!empty($status)): ?>
      <div class="card bg-surface-subtle p-5 border mt-4 text-left" style="border-radius: 8px;">
        <div class="flex justify-between items-start gap-2 mb-3">
          <div>
            <span class="text-caption text-muted text-uppercase d-block">Attendee</span>
            <h2 class="font-weight-bold m-0" style="font-size: var(--font-size-lg);">
              <?= e($status['attendee_name']) ?>
            </h2>
          </div>
          <div>
            <?php if ($status['status'] === 'confirmed'): ?>
              <span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Confirmed</span>
            <?php elseif ($status['status'] === 'pending'): ?>
              <span class="badge badge-info"><span class="badge-dot" aria-hidden="true"></span> Pending Approval</span>
            <?php elseif ($status['status'] === 'waitlisted'): ?>
              <span class="badge badge-warning"><span class="badge-dot" aria-hidden="true"></span> Waitlisted</span>
            <?php else: ?>
              <span class="badge badge-danger"><span class="badge-dot" aria-hidden="true"></span> Cancelled</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="border-top pt-3 mb-4 text-caption text-secondary" style="line-height: 1.7;">
          <div><strong>Event:</strong> <?= e($status['event_title']) ?></div>
          <div><strong>Initiative:</strong> <?= e($status['campaign_title']) ?></div>
          <div><strong>Schedule:</strong> <?= e(date('D, M d, Y &bull; h:i A', strtotime((string) $status['start_time']))) ?></div>
          <div><strong>Format:</strong> <span class="text-capitalize"><?= e(str_replace('_', ' ', (string) $status['format'])) ?></span></div>
          <?php if (!empty($status['venue_name'])): ?>
            <div><strong>Venue:</strong> <?= e($status['venue_name']) ?></div>
          <?php endif; ?>
          <div><strong>Code:</strong> <span class="code-inline"><?= e($status['registration_code']) ?></span></div>
        </div>

        <!-- Conditional Guidance Notice -->
        <?php if ($status['status'] === 'confirmed'): ?>
          <div class="mb-4">
            <a href="<?= e(url('/registration/pass/' . rawurlencode((string) $status['registration_code']))) ?>" class="btn btn-primary w-100 text-center">
              View &amp; Print Attendance Pass &rarr;
            </a>
          </div>
        <?php elseif ($status['status'] === 'pending'): ?>
          <div class="alert alert-info mb-0 text-caption" role="status">
            <strong>Approval in Progress:</strong> Your registration has been received and is awaiting coordinator approval. Once approved, your attendance pass will become available here.
          </div>
        <?php elseif ($status['status'] === 'waitlisted'): ?>
          <div class="alert alert-warning mb-0 text-caption" role="status">
            <strong>Waitlist Active:</strong> You are currently on the waitlist for this event. If a seat is released, you will be promoted and your status will automatically transition to Confirmed.
          </div>
        <?php else: ?>
          <div class="alert alert-danger mb-0 text-caption" role="status">
            <strong>Registration Cancelled:</strong> This registration has been marked as cancelled. If this was done in error, please register again from the event page.
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="text-center mt-4">
      <a href="<?= e(url('/events')) ?>" class="text-caption text-muted">
        &larr; Back to Events Catalog
      </a>
    </div>
  </div>
</div>
