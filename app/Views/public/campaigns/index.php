<div class="mb-6">
  <div class="flex justify-between items-center flex-wrap gap-4 mb-2">
    <div>
      <h1 class="page-title" style="font-size: var(--font-size-2xl); margin-bottom: 0.25rem;">
        Awareness Initiatives &amp; Campaigns
      </h1>
      <p class="text-secondary">
        Explore thematic initiatives and public community drives by the Listening Community.
      </p>
    </div>
    <div>
      <a href="<?= e(url('/events')) ?>" class="btn btn-outline btn-sm">
        View All Events &rarr;
      </a>
    </div>
  </div>
</div>

<!-- Active Campaigns Section -->
<section class="mb-8" aria-labelledby="active-campaigns-heading">
  <div class="flex items-center gap-2 mb-4">
    <span class="badge badge-success">
      <span class="badge-dot" aria-hidden="true"></span>
      Active Initiatives
    </span>
    <h2 id="active-campaigns-heading" class="card-title" style="font-size: var(--font-size-lg); margin: 0;">
      Current Campaigns
    </h2>
  </div>

  <?php if (empty($activeCampaigns)): ?>
    <div class="card p-6 text-center text-secondary">
      <p class="mb-2">There are currently no active public campaigns scheduled.</p>
      <p class="text-caption text-muted">Check back soon for new community initiatives and awareness drives.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-2 gap-4">
      <?php foreach ($activeCampaigns as $camp): ?>
        <article class="card flex flex-col justify-between" style="border-top: 3px solid var(--color-primary);">
          <div>
            <div class="flex justify-between items-start gap-2 mb-2">
              <span class="badge badge-primary">
                <?= (int) ($camp['published_event_count'] ?? 0) ?> Upcoming Event<?= (int) ($camp['published_event_count'] ?? 0) === 1 ? '' : 's' ?>
              </span>
              <span class="text-caption text-muted">
                <?= e(date('M d, Y', strtotime((string) $camp['start_date']))) ?> &mdash; <?= e(date('M d, Y', strtotime((string) $camp['end_date']))) ?>
              </span>
            </div>
            
            <h3 class="card-title mb-1" style="font-size: var(--font-size-xl);">
              <a href="<?= e(url('/campaigns/' . rawurlencode((string) $camp['slug']))) ?>" class="text-decoration-none">
                <?= e($camp['title']) ?>
              </a>
            </h3>

            <?php if (!empty($camp['theme'])): ?>
              <p class="text-primary font-weight-medium mb-2" style="font-size: var(--font-size-sm);">
                <em>Theme: <?= e($camp['theme']) ?></em>
              </p>
            <?php endif; ?>

            <?php if (!empty($camp['description'])): ?>
              <p class="text-secondary mb-4" style="font-size: var(--font-size-sm); line-height: 1.5;">
                <?= e(mb_strimwidth(strip_tags((string) $camp['description']), 0, 180, '...')) ?>
              </p>
            <?php endif; ?>
          </div>

          <div class="card-footer bg-transparent pt-3 mt-auto border-top flex justify-between items-center">
            <span class="text-caption text-muted">Listening Community SPC</span>
            <a href="<?= e(url('/campaigns/' . rawurlencode((string) $camp['slug']))) ?>" class="btn btn-primary btn-sm">
              Explore Campaign &rarr;
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Archived / Completed Campaigns Section -->
<?php if (!empty($archivedCampaigns)): ?>
  <section class="mt-8 pt-6 border-top" aria-labelledby="archived-campaigns-heading">
    <div class="flex items-center gap-2 mb-4">
      <span class="badge badge-neutral">Archived</span>
      <h2 id="archived-campaigns-heading" class="card-title" style="font-size: var(--font-size-md); margin: 0;">
        Past Initiatives Archive
      </h2>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th scope="col">Campaign Initiative</th>
              <th scope="col">Theme</th>
              <th scope="col">Timeline</th>
              <th scope="col">Total Events</th>
              <th scope="col" class="text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($archivedCampaigns as $arch): ?>
              <tr>
                <td>
                  <strong><?= e($arch['title']) ?></strong>
                </td>
                <td class="text-secondary"><?= e($arch['theme'] ?? 'General Awareness') ?></td>
                <td class="text-muted text-caption">
                  <?= e(date('M Y', strtotime((string) $arch['start_date']))) ?> &mdash; <?= e(date('M Y', strtotime((string) $arch['end_date']))) ?>
                </td>
                <td><?= (int) ($arch['total_event_count'] ?? 0) ?> events</td>
                <td class="text-right">
                  <a href="<?= e(url('/campaigns/' . rawurlencode((string) $arch['slug']))) ?>" class="btn btn-ghost btn-sm">
                    View &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php endif; ?>
