<!-- Event Management Roster Header -->
<div class="card mb-6">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.25rem;">
        Event Management
      </h1>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Workshops, listening circles, training sessions, seminars, modality, and capacity administration.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <?php if (!empty($canCreate)): ?>
        <a href="<?= e(url('/admin/events/create')) ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.35rem;">
          <span aria-hidden="true">&#43;</span>
          <span>New Event</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- KPI Status Metrics -->
<section class="grid grid-cols-5 mb-6" aria-label="Event Overview Metrics">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      &#9654;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['published'] ?? 0)) ?></div>
    <div class="card-metric-label">Published</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-info); color: var(--text-info);">
      &#9679;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['ongoing'] ?? 0)) ?></div>
    <div class="card-metric-label">Ongoing</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-warning); color: var(--text-warning);">
      &#9998;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['draft'] ?? 0)) ?></div>
    <div class="card-metric-label">Drafts</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      &#10003;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['completed'] ?? 0)) ?></div>
    <div class="card-metric-label">Completed</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      &#128197;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['total'] ?? 0)) ?></div>
    <div class="card-metric-label">Total Events</div>
  </div>
</section>

<!-- Filter & Search Toolbar -->
<div class="card mb-6" style="padding: 1rem 1.25rem;">
  <form action="<?= e(url('/admin/events')) ?>" method="GET" style="display: flex; flex-direction: column; gap: 0.85rem;">
    <!-- Top Filter Bar: Campaign, Category, Format, Search, Actions -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
      <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; flex: 1;">
        <!-- Campaign Selector -->
        <select name="campaign_id" class="form-control" style="width: auto; min-width: 180px; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);" aria-label="Filter by campaign">
          <option value="">All Campaigns</option>
          <?php foreach ($campaigns as $camp): ?>
            <option value="<?= e((string) $camp['id']) ?>" <?= (!empty($filters['campaign_id']) && (int) $filters['campaign_id'] === (int) $camp['id']) ? 'selected' : '' ?>>
              <?= e($camp['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Category Selector -->
        <select name="category" class="form-control" style="width: auto; min-width: 150px; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);" aria-label="Filter by category">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= (($filters['category'] ?? '') === $cat) ? 'selected' : '' ?>>
              <?= e(ucwords(str_replace('_', ' ', $cat))) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Format Selector -->
        <select name="format" class="form-control" style="width: auto; min-width: 130px; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);" aria-label="Filter by format">
          <option value="">All Formats</option>
          <?php foreach ($formats as $fmt): ?>
            <option value="<?= e($fmt) ?>" <?= (($filters['format'] ?? '') === $fmt) ? 'selected' : '' ?>>
              <?= e(ucwords(str_replace('_', ' ', $fmt))) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Search Input -->
        <input 
          type="search" 
          name="search" 
          value="<?= e($filters['search'] ?? '') ?>" 
          placeholder="Search title, venue, slug..." 
          class="form-control" 
          style="min-width: 200px; flex: 1; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);"
          aria-label="Search events"
        >

        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <?php if (!empty($filters['campaign_id']) || !empty($filters['status']) || !empty($filters['category']) || !empty($filters['format']) || !empty($filters['search']) || !empty($isTrash)): ?>
          <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-sm" title="Clear all filters">Reset</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status Filter Pills -->
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; border-top: 1px solid var(--border-color); pt: 0.6rem; padding-top: 0.6rem;">
      <span class="text-caption text-secondary" style="font-weight: var(--font-weight-medium); margin-right: 0.25rem;">Status:</span>
      
      <?php
        $baseQuery = [];
        if (!empty($filters['campaign_id'])) $baseQuery['campaign_id'] = $filters['campaign_id'];
        if (!empty($filters['category'])) $baseQuery['category'] = $filters['category'];
        if (!empty($filters['format'])) $baseQuery['format'] = $filters['format'];
        if (!empty($filters['search'])) $baseQuery['search'] = $filters['search'];
        
        $buildUrl = function (?string $status = null, bool $trash = false) use ($baseQuery) {
            $q = $baseQuery;
            if ($status !== null) $q['status'] = $status;
            if ($trash) $q['trash'] = '1';
            return url('/admin/events' . (!empty($q) ? '?' . http_build_query($q) : ''));
        };
      ?>

      <a href="<?= e($buildUrl(null, false)) ?>" 
         class="badge <?= empty($filters['status']) && empty($isTrash) ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        All (<?= e((string) ($counts['total'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('published', false)) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'published' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        Published (<?= e((string) ($counts['published'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('ongoing', false)) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'ongoing' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        Ongoing (<?= e((string) ($counts['ongoing'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('draft', false)) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'draft' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        Drafts (<?= e((string) ($counts['draft'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('completed', false)) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'completed' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        Completed (<?= e((string) ($counts['completed'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('cancelled', false)) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'cancelled' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        Cancelled (<?= e((string) ($counts['cancelled'] ?? 0)) ?>)
      </a>

      <?php if (!empty($canDelete)): ?>
        <a href="<?= e($buildUrl(null, true)) ?>" 
           class="badge <?= !empty($isTrash) ? 'badge-danger' : 'badge-neutral' ?>" 
           style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
          Trash &bull; Soft-Deleted
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Event Roster Table -->
<div class="table-responsive">
  <table class="table table-hover" aria-label="Events list">
    <thead>
      <tr>
        <th style="width: 24%;">Event &amp; Category</th>
        <th style="width: 16%;">Campaign</th>
        <th style="width: 14%;">Modality / Venue</th>
        <th style="width: 16%;">Schedule</th>
        <th style="width: 8%;">Capacity</th>
        <th style="width: 8%;">Status</th>
        <th style="width: 14%; text-align: right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($events)): ?>
        <tr>
          <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;" aria-hidden="true">&#128197;</div>
            <p style="font-weight: var(--font-weight-medium); margin-bottom: 0.25rem;">No events found.</p>
            <span class="text-caption text-muted">
              <?= (!empty($filters['search']) || !empty($filters['campaign_id']) || !empty($filters['status'])) 
                ? 'Try refining your filters or resetting the search query.' 
                : 'Click "New Event" above to schedule an initiative.' ?>
            </span>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($events as $ev): ?>
          <?php
            $st = $ev['status'] ?? 'draft';
            $statusBadge = match ($st) {
                'published' => 'badge-success',
                'ongoing'   => 'badge-info',
                'completed' => 'badge-neutral',
                'cancelled' => 'badge-danger',
                default     => 'badge-warning',
            };
            $fmtBadge = match ($ev['format'] ?? 'in_person') {
                'online'    => 'badge-info',
                'hybrid'    => 'badge-primary',
                default     => 'badge-neutral',
            };
            $isSoftDeleted = !empty($ev['deleted_at']);
          ?>
          <tr style="<?= $isSoftDeleted ? 'opacity: 0.7; background-color: var(--bg-surface-subtle);' : '' ?>">
            <td>
              <div style="font-weight: var(--font-weight-semibold); color: var(--text-primary); margin-bottom: 0.2rem;">
                <a href="<?= e(url('/admin/events/' . $ev['id'])) ?>" style="color: inherit; text-decoration: none;">
                  <?= e($ev['title']) ?>
                </a>
              </div>
              <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                <span class="badge badge-neutral" style="font-size: 10px; padding: 0.15rem 0.4rem;">
                  <?= e(ucwords(str_replace('_', ' ', $ev['category'] ?? 'workshop'))) ?>
                </span>
                <code style="font-size: 10px; background: var(--bg-surface-subtle); padding: 0.1rem 0.35rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                  <?= e($ev['slug']) ?>
                </code>
              </div>
            </td>

            <td>
              <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-medium);">
                <a href="<?= e(url('/admin/campaigns/' . $ev['campaign_id'])) ?>" style="color: inherit; text-decoration: none;" title="View Parent Campaign">
                  <?= e($ev['campaign_title'] ?? 'Campaign #' . $ev['campaign_id']) ?>
                </a>
              </div>
              <?php if (!empty($ev['coordinator_name'])): ?>
                <div class="text-caption text-secondary" style="font-size: 11px;">
                  Coord: <?= e($ev['coordinator_name']) ?>
                </div>
              <?php endif; ?>
            </td>

            <td>
              <div style="margin-bottom: 0.2rem;">
                <span class="badge <?= e($fmtBadge) ?>" style="font-size: 10px;">
                  <?= e(ucfirst(str_replace('_', '-', $ev['format'] ?? 'in_person'))) ?>
                </span>
              </div>
              <div class="text-caption text-secondary" style="font-size: 11px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($ev['venue_name'] ?? $ev['online_meeting_url'] ?? '') ?>">
                <?php if ($ev['format'] === 'online'): ?>
                  Virtual Meeting
                <?php else: ?>
                  <?= e($ev['venue_name'] ?? 'Venue TBD') ?>
                <?php endif; ?>
              </div>
            </td>

            <td>
              <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-medium);">
                <?= e(date('M d, Y', strtotime($ev['start_time']))) ?>
              </div>
              <div class="text-caption text-muted" style="font-size: 11px;">
                <?= e(date('h:i A', strtotime($ev['start_time']))) ?> &ndash; <?= e(date('h:i A', strtotime($ev['end_time']))) ?>
              </div>
            </td>

            <td>
              <span style="font-size: var(--font-size-xs); font-weight: var(--font-weight-medium);">
                <?= ((int) $ev['capacity'] === 0) ? 'Unlimited' : e((string) $ev['capacity']) ?>
              </span>
            </td>

            <td>
              <?php if ($isSoftDeleted): ?>
                <span class="badge badge-danger">
                  <span class="badge-dot" aria-hidden="true"></span>
                  Deleted
                </span>
              <?php else: ?>
                <span class="badge <?= e($statusBadge) ?>">
                  <span class="badge-dot" aria-hidden="true"></span>
                  <?= e(ucfirst($st)) ?>
                </span>
              <?php endif; ?>
            </td>

            <td style="text-align: right; white-space: nowrap;">
              <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <a href="<?= e(url('/admin/events/' . $ev['id'])) ?>" class="btn btn-outline btn-sm" title="View Event Details">
                  View
                </a>

                <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
                  <a href="<?= e(url('/admin/events/' . $ev['id'] . '/edit')) ?>" class="btn btn-outline btn-sm" title="Edit Event">
                    Edit
                  </a>
                <?php endif; ?>

                <?php if (!$isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/events/' . $ev['id'] . '/delete')) ?>" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to soft-delete event &quot;<?= e(addslashes($ev['title'])) ?>&quot;?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-danger); border-color: var(--border-danger);" title="Soft-delete Event">
                      Delete
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/events/' . $ev['id'] . '/restore')) ?>" method="POST" style="margin: 0; display: inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm" title="Restore soft-deleted event">
                      Restore
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
