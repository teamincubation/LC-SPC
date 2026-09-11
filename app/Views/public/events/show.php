<!-- Breadcrumb Navigation -->
<nav class="mb-4" aria-label="Breadcrumb">
  <ol class="breadcrumb flex gap-2 text-caption text-muted list-none p-0 m-0">
    <li><a href="<?= e(url('/')) ?>">Home</a> &rsaquo;</li>
    <li><a href="<?= e(url('/events')) ?>">Events</a> &rsaquo;</li>
    <li class="font-weight-medium text-secondary" aria-current="page"><?= e($event['title']) ?></li>
  </ol>
</nav>

<div class="grid grid-cols-3 gap-6">
  <!-- Left / Main Column: Event Briefing (2 cols) -->
  <div style="grid-column: span 2;">
    <article class="card mb-6" style="border-left: 4px solid var(--color-primary);">
      <!-- Badges -->
      <div class="flex items-center gap-2 mb-2 flex-wrap">
        <span class="badge badge-primary text-capitalize">
          <?= e(str_replace('_', ' ', (string) $event['category'])) ?>
        </span>
        <span class="badge badge-neutral text-capitalize">
          <?= e(str_replace('_', ' ', (string) $event['format'])) ?>
        </span>
        <span class="badge <?= e($availability['badgeClass'] ?? 'badge-neutral') ?>">
          <?= e($availability['label'] ?? 'Registration Open') ?>
        </span>
      </div>

      <!-- Title -->
      <h1 class="card-title mb-2" style="font-size: var(--font-size-2xl);">
        <?= e($event['title']) ?>
      </h1>

      <p class="text-caption text-muted mb-4">
        Part of the initiative: 
        <strong>
          <a href="<?= e(url('/campaigns/' . rawurlencode((string) $event['campaign_slug']))) ?>">
            <?= e($event['campaign_title']) ?>
          </a>
        </strong>
      </p>

      <!-- Description Content -->
      <?php if (!empty($event['description'])): ?>
        <div class="text-body text-secondary mb-6" style="line-height: 1.65; white-space: pre-line;">
          <?= e($event['description']) ?>
        </div>
      <?php endif; ?>

      <!-- Safe Space & Guidelines Commitment -->
      <div class="alert alert-info p-4" style="background-color: var(--color-primary-tint); border: 1px solid rgba(191, 30, 46, 0.15);">
        <div class="flex items-start gap-3">
          <span style="font-size: 1.25rem;">&#9829;</span>
          <div class="text-caption text-secondary" style="line-height: 1.5;">
            <strong style="color: var(--color-primary);">Community Care &amp; Non-Clinical Space:</strong>
            Listening Community workshops and events are safe, confidential, peer-led awareness spaces. We do not provide clinical therapy, medical treatment, or emergency psychiatric intervention. Everyone is welcome to listen, learn, and share with mutual respect.
          </div>
        </div>
      </div>
    </article>
  </div>

  <!-- Right Column: Schedule, Logistics & Registration CTA (1 col) -->
  <div>
    <!-- Registration Action Card -->
    <div class="card mb-4" style="box-shadow: 0 4px 14px rgba(0,0,0,0.06);">
      <div class="card-header pb-2">
        <h2 class="card-title" style="font-size: var(--font-size-md); margin: 0;">
          Registration Status
        </h2>
      </div>

      <div class="p-4 pt-2">
        <div class="mb-3">
          <span class="badge <?= e($availability['badgeClass'] ?? 'badge-neutral') ?> w-100 text-center py-2" style="font-size: var(--font-size-sm); display: block;">
            <?= e($availability['label'] ?? 'Registration Open') ?>
          </span>
          <p class="text-caption text-secondary mt-2 mb-0" style="line-height: 1.4;">
            <?= e($availability['notice'] ?? '') ?>
          </p>
        </div>

        <?php if (!empty($availability['canRegister'])): ?>
          <a href="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']) . '/register')) ?>" class="btn btn-primary w-100 text-center mb-2">
            <?= !empty($availability['isWaitlist']) ? 'Join Waitlist &rarr;' : 'Register for Free &rarr;' ?>
          </a>
        <?php else: ?>
          <button type="button" class="btn btn-outline w-100 text-center mb-2" disabled>
            Registration Closed
          </button>
        <?php endif; ?>

        <div class="text-center">
          <a href="<?= e(url('/registration/status')) ?>" class="text-caption text-muted">
            Already registered? Check your status &rarr;
          </a>
        </div>
      </div>
    </div>

    <!-- Event Logistics Card -->
    <div class="card">
      <div class="card-header pb-2">
        <h2 class="card-title" style="font-size: var(--font-size-md); margin: 0;">
          Event Details
        </h2>
      </div>

      <div class="p-4 pt-2 text-caption text-secondary" style="line-height: 1.6;">
        <div class="mb-3">
          <strong class="text-secondary d-block">Start Time:</strong>
          <?= e(date('l, F d, Y &bull; h:i A', strtotime((string) $event['start_time']))) ?>
        </div>

        <div class="mb-3">
          <strong class="text-secondary d-block">End Time:</strong>
          <?= e(date('l, F d, Y &bull; h:i A', strtotime((string) $event['end_time']))) ?>
        </div>

        <div class="mb-3">
          <strong class="text-secondary d-block">Format:</strong>
          <span class="text-capitalize"><?= e(str_replace('_', ' ', (string) $event['format'])) ?></span>
        </div>

        <?php if ($event['format'] === 'online'): ?>
          <div class="mb-3">
            <strong class="text-secondary d-block">Location:</strong>
            Online Meeting link will be shared on your registered attendance pass.
          </div>
        <?php elseif (!empty($event['venue_name'])): ?>
          <div class="mb-3">
            <strong class="text-secondary d-block">Venue:</strong>
            <?= e($event['venue_name']) ?>
            <?php if (!empty($event['venue_address'])): ?>
              <div class="text-muted mt-1"><?= e($event['venue_address']) ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="mb-2">
          <strong class="text-secondary d-block">Cost:</strong>
          <span class="badge badge-success">100% Free Entry</span>
        </div>
      </div>
    </div>
  </div>
</div>
