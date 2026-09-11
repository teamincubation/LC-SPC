<!-- Hero Section -->
<div class="card mb-8 p-8" style="background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%); border-left: 5px solid var(--color-primary); box-shadow: 0 8px 24px rgba(0,0,0,0.04);">
  <div class="flex justify-between items-center flex-wrap gap-6">
    <div style="flex: 2 1 400px;">
      <div class="flex items-center gap-3 mb-3">
        <img src="<?= e(asset('images/listening-community-logo.png')) ?>" alt="<?= e($appName) ?>" class="brand-logo" style="max-height: 48px;">
        <span class="badge badge-primary text-uppercase font-weight-medium" style="letter-spacing: 0.05em;">Community Awareness</span>
      </div>

      <h1 class="page-title mb-2" style="font-size: 2.25rem; font-weight: 700; color: #0f172a; line-height: 1.2;">
        <?= e($fullTitle) ?>
      </h1>

      <p class="text-secondary mb-4" style="font-size: var(--font-size-md); line-height: 1.6; max-width: 650px;">
        Creating safe, non-judgmental, and confidential spaces for open conversations on mental well-being, empathy, and suicide prevention. Words and beyond.
      </p>

      <div class="flex flex-wrap gap-3 items-center">
        <a href="<?= e(url('/events')) ?>" class="btn btn-primary btn-lg">
          Browse Upcoming Events &rarr;
        </a>
        <a href="<?= e(url('/campaigns')) ?>" class="btn btn-outline btn-lg">
          Explore Campaigns
        </a>
        <a href="<?= e(url('/registration/status')) ?>" class="btn btn-ghost btn-lg text-secondary">
          My Pass / Status
        </a>
      </div>
    </div>

    <!-- Quick Status / Pass Lookup Widget -->
    <div style="flex: 1 1 300px; max-width: 360px;">
      <div class="card p-5 bg-surface-subtle border" style="box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <h2 class="card-title mb-1" style="font-size: var(--font-size-md);">
          Have a Pass Code?
        </h2>
        <p class="text-caption text-muted mb-3">
          Enter your registration code to view your pass or verify registration status.
        </p>
        <form method="POST" action="<?= e(url('/registration/status')) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="form-group mb-2">
            <input type="text" name="code" class="form-control form-control-sm" placeholder="REG-YY-XXXXX" required maxlength="30" style="font-family: monospace; text-transform: uppercase;">
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">
            Check Status &rarr;
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Featured Active Campaigns -->
<?php if (!empty($activeCampaigns)): ?>
  <section class="mb-8" aria-labelledby="featured-campaigns-title">
    <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <span class="badge badge-success"><span class="badge-dot" aria-hidden="true"></span> Active</span>
        <h2 id="featured-campaigns-title" class="card-title m-0" style="font-size: var(--font-size-xl);">
          Current Campaign Initiatives
        </h2>
      </div>
      <a href="<?= e(url('/campaigns')) ?>" class="btn btn-outline btn-sm">
        View All Initiatives &rarr;
      </a>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <?php foreach (array_slice($activeCampaigns, 0, 2) as $camp): ?>
        <article class="card flex flex-col justify-between" style="border-top: 3px solid var(--color-primary);">
          <div>
            <span class="badge badge-primary mb-2">
              <?= (int) ($camp['published_event_count'] ?? 0) ?> Upcoming Event<?= (int) ($camp['published_event_count'] ?? 0) === 1 ? '' : 's' ?>
            </span>
            <h3 class="card-title mb-1" style="font-size: var(--font-size-lg);">
              <a href="<?= e(url('/campaigns/' . rawurlencode((string) $camp['slug']))) ?>" class="text-decoration-none">
                <?= e($camp['title']) ?>
              </a>
            </h3>
            <?php if (!empty($camp['theme'])): ?>
              <p class="text-primary font-weight-medium text-caption mb-2">
                Theme: <?= e($camp['theme']) ?>
              </p>
            <?php endif; ?>
            <?php if (!empty($camp['description'])): ?>
              <p class="text-secondary text-caption mb-3" style="line-height: 1.5;">
                <?= e(mb_strimwidth(strip_tags((string) $camp['description']), 0, 150, '...')) ?>
              </p>
            <?php endif; ?>
          </div>

          <div class="card-footer bg-transparent pt-3 mt-auto border-top flex justify-between items-center">
            <span class="text-caption text-muted">
              <?= e(date('M Y', strtotime((string) $camp['start_date']))) ?> &mdash; <?= e(date('M Y', strtotime((string) $camp['end_date']))) ?>
            </span>
            <a href="<?= e(url('/campaigns/' . rawurlencode((string) $camp['slug']))) ?>" class="btn btn-primary btn-sm">
              Explore &rarr;
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- Upcoming Events Grid -->
<section class="mb-8" aria-labelledby="upcoming-events-title">
  <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
      <h2 id="upcoming-events-title" class="card-title m-0" style="font-size: var(--font-size-xl);">
        Upcoming Events &amp; Workshops
      </h2>
      <p class="text-caption text-muted m-0">Open registration sessions across universities and community centers.</p>
    </div>
    <a href="<?= e(url('/events')) ?>" class="btn btn-outline btn-sm">
      Browse All Events &rarr;
    </a>
  </div>

  <?php if (empty($upcomingEvents)): ?>
    <div class="card p-6 text-center text-secondary">
      <p class="mb-1">No upcoming events are currently scheduled.</p>
      <p class="text-caption text-muted">Check back soon or explore past campaigns.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-3 gap-4">
      <?php foreach ($upcomingEvents as $event): ?>
        <article class="card flex flex-col justify-between" style="border-top: 2px solid var(--border-color);">
          <div>
            <div class="flex justify-between items-center gap-2 mb-2">
              <span class="badge badge-primary text-capitalize" style="font-size: 0.75rem;">
                <?= e(str_replace('_', ' ', (string) $event['category'])) ?>
              </span>
              <span class="badge badge-neutral text-capitalize" style="font-size: 0.75rem;">
                <?= e(str_replace('_', ' ', (string) $event['format'])) ?>
              </span>
            </div>

            <h3 class="card-title mb-1" style="font-size: var(--font-size-md);">
              <a href="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']))) ?>" class="text-decoration-none">
                <?= e($event['title']) ?>
              </a>
            </h3>

            <p class="text-caption text-secondary mb-2">
              <?= e(date('D, M d &bull; h:i A', strtotime((string) $event['start_time']))) ?>
            </p>

            <p class="text-caption text-muted mb-3">
              <?= e($event['format'] === 'online' ? 'Online Session' : ($event['venue_name'] ?? 'In Person')) ?>
            </p>
          </div>

          <div class="card-footer bg-transparent pt-3 mt-auto border-top flex justify-between items-center">
            <span class="badge badge-success" style="font-size: 0.75rem;">Free Entry</span>
            <a href="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']))) ?>" class="btn btn-primary btn-sm">
              Register &rarr;
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Values & Principles -->
<section class="grid grid-cols-3 gap-4 mb-8" aria-label="Listening Community Principles">
  <div class="card p-5">
    <div style="font-size: 1.75rem; color: var(--color-primary); margin-bottom: 0.5rem;">&#10003;</div>
    <h3 class="card-title mb-2" style="font-size: var(--font-size-md);">100% Free &amp; Open Access</h3>
    <p class="text-caption text-secondary m-0" style="line-height: 1.5;">
      All sessions, workshops, and listening circles are completely free to attend. No commercial paywalls or fees.
    </p>
  </div>

  <div class="card p-5">
    <div style="font-size: 1.75rem; color: var(--color-primary); margin-bottom: 0.5rem;">&#9829;</div>
    <h3 class="card-title mb-2" style="font-size: var(--font-size-md);">Safe &amp; Non-Clinical Space</h3>
    <p class="text-caption text-secondary m-0" style="line-height: 1.5;">
      Empathetic listening and community awareness without clinical judgment. Strictly non-clinical, peer-support environments.
    </p>
  </div>

  <div class="card p-5">
    <div style="font-size: 1.75rem; color: var(--color-primary); margin-bottom: 0.5rem;">&#9733;</div>
    <h3 class="card-title mb-2" style="font-size: var(--font-size-md);">Verified Certificates</h3>
    <p class="text-caption text-secondary m-0" style="line-height: 1.5;">
      Attendees receive authentic digital attendance passes and cryptographically verifiable participation certificates.
    </p>
  </div>
</section>

<!-- Collapsible Technical Diagnostic Details (Preserved for Foundation Verification) -->
<details class="card p-4 mt-6">
  <summary class="font-weight-medium text-secondary" style="cursor: pointer;">
    System Foundation Status &amp; Diagnostics
  </summary>
  <div class="border-top pt-3 mt-3 text-caption text-secondary">
    <div class="grid grid-cols-4 gap-2 mb-3">
      <div><strong>Core:</strong> <?= e($appName) ?></div>
      <div><strong>DB:</strong> <?= !empty($dbConnected) ? 'Connected' : 'Offline' ?></div>
      <div><strong>PHP:</strong> <?= e($phpVersion) ?></div>
      <div><strong>Env:</strong> <?= e($env) ?></div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm">
        <tbody>
          <tr>
            <td>Base Path</td>
            <td><code><?= e($basePath ?: '/') ?></code></td>
          </tr>
          <tr>
            <td>Database Host</td>
            <td><code><?= e($dbStatus['host'] ?? '127.0.0.1') ?></code></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</details>
