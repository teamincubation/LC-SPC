<div class="mb-6">
  <div class="flex justify-between items-center flex-wrap gap-4 mb-2">
    <div>
      <h1 class="page-title" style="font-size: var(--font-size-2xl); margin-bottom: 0.25rem;">
        Upcoming Community Events
      </h1>
      <p class="text-secondary">
        Browse open awareness workshops, listening circles, and community sessions. Free and accessible to all.
      </p>
    </div>
    <div>
      <a href="<?= e(url('/registration/status')) ?>" class="btn btn-outline btn-sm">
        Check Registration Status &rarr;
      </a>
    </div>
  </div>
</div>

<!-- Filters Bar -->
<div class="card mb-6 p-4">
  <form method="GET" action="<?= e(url('/events')) ?>" class="flex flex-wrap items-end gap-3" role="search" aria-label="Event Filters">
    <div class="form-group mb-0" style="flex: 1 1 180px;">
      <label for="filter_category" class="form-label" style="font-size: var(--font-size-xs);">Category</label>
      <select id="filter_category" name="category" class="form-select form-select-sm">
        <option value="">All Categories</option>
        <option value="workshop" <?= ($filters['category'] ?? '') === 'workshop' ? 'selected' : '' ?>>Workshop</option>
        <option value="seminar" <?= ($filters['category'] ?? '') === 'seminar' ? 'selected' : '' ?>>Seminar</option>
        <option value="webinar" <?= ($filters['category'] ?? '') === 'webinar' ? 'selected' : '' ?>>Webinar</option>
        <option value="listening_circle" <?= ($filters['category'] ?? '') === 'listening_circle' ? 'selected' : '' ?>>Listening Circle</option>
        <option value="training" <?= ($filters['category'] ?? '') === 'training' ? 'selected' : '' ?>>Training</option>
        <option value="community_drive" <?= ($filters['category'] ?? '') === 'community_drive' ? 'selected' : '' ?>>Community Drive</option>
      </select>
    </div>

    <div class="form-group mb-0" style="flex: 1 1 150px;">
      <label for="filter_format" class="form-label" style="font-size: var(--font-size-xs);">Format</label>
      <select id="filter_format" name="format" class="form-select form-select-sm">
        <option value="">All Formats</option>
        <option value="in_person" <?= ($filters['format'] ?? '') === 'in_person' ? 'selected' : '' ?>>In Person</option>
        <option value="online" <?= ($filters['format'] ?? '') === 'online' ? 'selected' : '' ?>>Online</option>
        <option value="hybrid" <?= ($filters['format'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
      </select>
    </div>

    <?php if (!empty($campaigns)): ?>
      <div class="form-group mb-0" style="flex: 1 1 200px;">
        <label for="filter_campaign" class="form-label" style="font-size: var(--font-size-xs);">Campaign</label>
        <select id="filter_campaign" name="campaign" class="form-select form-select-sm">
          <option value="">All Campaigns</option>
          <?php foreach ($campaigns as $camp): ?>
            <option value="<?= e($camp['slug']) ?>" <?= ($filters['campaign_slug'] ?? '') === $camp['slug'] ? 'selected' : '' ?>>
              <?= e($camp['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="form-group mb-0" style="flex: 2 1 220px;">
      <label for="filter_search" class="form-label" style="font-size: var(--font-size-xs);">Search</label>
      <input type="text" id="filter_search" name="search" class="form-control form-control-sm" placeholder="Search event title or venue..." value="<?= e($filters['search'] ?? '') ?>">
    </div>

    <div class="flex gap-2 mb-0">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <?php if (!empty(array_filter($filters))): ?>
        <a href="<?= e(url('/events')) ?>" class="btn btn-ghost btn-sm">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Events Grid -->
<?php if (empty($events)): ?>
  <div class="card p-8 text-center text-secondary">
    <p class="mb-2" style="font-size: var(--font-size-lg);">No upcoming events matched your filter criteria.</p>
    <p class="text-caption text-muted mb-4">Try adjusting your filters or resetting to view all upcoming events.</p>
    <div>
      <a href="<?= e(url('/events')) ?>" class="btn btn-outline btn-sm">Reset Filters</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-cols-2 gap-4">
    <?php foreach ($events as $event): ?>
      <article class="card flex flex-col justify-between" style="border-top: 3px solid var(--color-primary);">
        <div>
          <!-- Tags Header -->
          <div class="flex justify-between items-center gap-2 mb-2">
            <span class="badge badge-primary text-capitalize">
              <?= e(str_replace('_', ' ', (string) $event['category'])) ?>
            </span>
            <span class="badge badge-neutral text-capitalize">
              <?= e(str_replace('_', ' ', (string) $event['format'])) ?>
            </span>
          </div>

          <!-- Title -->
          <h2 class="card-title mb-1" style="font-size: var(--font-size-xl);">
            <a href="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']))) ?>" class="text-decoration-none">
              <?= e($event['title']) ?>
            </a>
          </h2>

          <!-- Campaign Badge -->
          <p class="text-caption text-muted mb-3">
            Part of initiative: <strong><a href="<?= e(url('/campaigns/' . rawurlencode((string) $event['campaign_slug']))) ?>"><?= e($event['campaign_title']) ?></a></strong>
          </p>

          <!-- Schedule & Location -->
          <div class="mb-3 text-caption text-secondary" style="line-height: 1.6;">
            <div>
              <strong>Schedule:</strong> <?= e(date('D, M d, Y &bull; h:i A', strtotime((string) $event['start_time']))) ?>
            </div>
            <?php if ($event['format'] === 'online'): ?>
              <div>
                <strong>Location:</strong> Online Interactive Meeting
              </div>
            <?php elseif (!empty($event['venue_name'])): ?>
              <div>
                <strong>Venue:</strong> <?= e($event['venue_name']) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Description snippet -->
          <?php if (!empty($event['description'])): ?>
            <p class="text-secondary text-caption mb-3" style="line-height: 1.4;">
              <?= e(mb_strimwidth(strip_tags((string) $event['description']), 0, 140, '...')) ?>
            </p>
          <?php endif; ?>
        </div>

        <!-- Footer CTA -->
        <div class="card-footer bg-transparent pt-3 mt-auto border-top flex justify-between items-center">
          <span class="badge badge-success">Free Entry</span>
          <a href="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']))) ?>" class="btn btn-primary btn-sm">
            View &amp; Register &rarr;
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
