<!-- Breadcrumb Navigation -->
<nav class="mb-4" aria-label="Breadcrumb">
  <ol class="breadcrumb flex gap-2 text-caption text-muted list-none p-0 m-0">
    <li><a href="<?= e(url('/')) ?>">Home</a> &rsaquo;</li>
    <li><a href="<?= e(url('/campaigns')) ?>">Campaigns</a> &rsaquo;</li>
    <li class="font-weight-medium text-secondary" aria-current="page"><?= e($campaign['title']) ?></li>
  </ol>
</nav>

<!-- Campaign Header Card -->
<div class="card mb-6" style="border-left: 4px solid var(--color-primary);">
  <div class="card-header flex justify-between items-start flex-wrap gap-3">
    <div>
      <span class="badge badge-primary mb-2">Campaign Initiative</span>
      <h1 class="card-title" style="font-size: var(--font-size-2xl); margin: 0 0 0.25rem 0;">
        <?= e($campaign['title']) ?>
      </h1>
      <?php if (!empty($campaign['theme'])): ?>
        <p class="text-primary font-weight-medium mb-1">
          Theme: <?= e($campaign['theme']) ?>
        </p>
      <?php endif; ?>
      <p class="text-caption text-muted m-0">
        Initiative Duration: <?= e(date('F d, Y', strtotime((string) $campaign['start_date']))) ?> &mdash; <?= e(date('F d, Y', strtotime((string) $campaign['end_date']))) ?>
      </p>
    </div>
    <div>
      <?php if ($campaign['status'] === 'active'): ?>
        <span class="badge badge-success">
          <span class="badge-dot" aria-hidden="true"></span>
          Active Campaign
        </span>
      <?php else: ?>
        <span class="badge badge-neutral">Completed</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($campaign['description'])): ?>
    <div class="card-body pt-2">
      <div class="text-body text-secondary" style="line-height: 1.6; white-space: pre-line;">
        <?= e($campaign['description']) ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Affiliated Events Section -->
<section aria-labelledby="campaign-events-heading">
  <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
      <h2 id="campaign-events-heading" class="card-title" style="font-size: var(--font-size-xl); margin: 0;">
        Campaign Events &amp; Workshops
      </h2>
      <p class="text-secondary text-caption m-0">
        Upcoming open sessions and workshops conducted as part of this initiative.
      </p>
    </div>
    <a href="<?= e(url('/events?campaign=' . rawurlencode((string) $campaign['slug']))) ?>" class="btn btn-outline btn-sm">
      Filter in Catalog &rarr;
    </a>
  </div>

  <?php if (empty($events)): ?>
    <div class="card p-6 text-center text-secondary">
      <p class="mb-1">No upcoming events are currently scheduled under this campaign.</p>
      <p class="text-caption text-muted">Please check back soon or browse other active campaigns.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-2 gap-4">
      <?php foreach ($events as $event): ?>
        <article class="card flex flex-col justify-between">
          <div>
            <div class="flex justify-between items-center gap-2 mb-2">
              <span class="badge badge-neutral text-capitalize">
                <?= e(str_replace('_', ' ', (string) $event['format'])) ?>
              </span>
              <span class="badge badge-primary text-capitalize">
                <?= e((string) $event['category']) ?>
              </span>
            </div>

            <h3 class="card-title mb-2" style="font-size: var(--font-size-lg);">
              <a href="<?= e(url('/events/' . rawurlencode((string) $campaign['slug']) . '/' . rawurlencode((string) $event['slug']))) ?>" class="text-decoration-none">
                <?= e($event['title']) ?>
              </a>
            </h3>

            <p class="text-caption text-secondary mb-2">
              <strong>Date &amp; Time:</strong> <?= e(date('D, M d, Y &bull; h:i A', strtotime((string) $event['start_time']))) ?>
            </p>

            <?php if ($event['format'] === 'online'): ?>
              <p class="text-caption text-secondary mb-3">
                <strong>Location:</strong> Online Interactive Session
              </p>
            <?php elseif (!empty($event['venue_name'])): ?>
              <p class="text-caption text-secondary mb-3">
                <strong>Venue:</strong> <?= e($event['venue_name']) ?>
              </p>
            <?php endif; ?>

            <?php if (!empty($event['description'])): ?>
              <p class="text-secondary text-caption mb-3" style="line-height: 1.4;">
                <?= e(mb_strimwidth(strip_tags((string) $event['description']), 0, 130, '...')) ?>
              </p>
            <?php endif; ?>
          </div>

          <div class="card-footer bg-transparent pt-3 mt-auto border-top flex justify-between items-center">
            <span class="badge badge-success">Free Entry</span>
            <a href="<?= e(url('/events/' . rawurlencode((string) $campaign['slug']) . '/' . rawurlencode((string) $event['slug']))) ?>" class="btn btn-primary btn-sm">
              Details &amp; Register &rarr;
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
