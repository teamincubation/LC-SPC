<?php

declare(strict_types=1);

/**
 * Modernized Event Management Roster
 * Aligned to 12-Panel Design System Reference (Panel 4: Events).
 */
?>

<!-- Event Management Page Header -->
<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Event Management</h1>
    <p class="admin-page-desc">Workshops, listening circles, training sessions, seminars, modality, and capacity administration.</p>
  </div>

  <div class="admin-page-actions">
    <?php if (!empty($canCreate)): ?>
      <a href="<?= e(url('/admin/events/create')) ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
        <?= icon('plus', ['class' => 'svg-icon-sm']) ?>
        <span>New Event</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Compact 4-Column Responsive KPI Grid (Solves oversized single vertical block bug) -->
<section class="metric-grid mb-6" aria-label="Event Overview Metrics">
  <!-- Published -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #ECFDF5; color: #059669;">
        <?= icon('check-circle') ?>
      </div>
      <span class="badge-pill badge-pill-success">Published</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['published'] ?? 0)) ?></div>
      <div class="metric-card-label">Published Events</div>
    </div>
  </div>

  <!-- Ongoing -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #EFF6FF; color: #2563EB;">
        <?= icon('clock') ?>
      </div>
      <span class="badge-pill badge-pill-info">Live / Ongoing</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['ongoing'] ?? 0)) ?></div>
      <div class="metric-card-label">Ongoing Events</div>
    </div>
  </div>

  <!-- Completed -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #F3F4F6; color: #4B5563;">
        <?= icon('award') ?>
      </div>
      <span class="badge-pill badge-pill-neutral">Completed</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['completed'] ?? 0)) ?></div>
      <div class="metric-card-label">Completed</div>
    </div>
  </div>

  <!-- Total Events & Drafts -->
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #FFFBEB; color: #D97706;">
        <?= icon('calendar') ?>
      </div>
      <span class="badge-pill badge-pill-warning"><?= e((string) ($counts['draft'] ?? 0)) ?> Drafts</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['total'] ?? array_sum($counts))) ?></div>
      <div class="metric-card-label">Total Events</div>
    </div>
  </div>
</section>

<!-- Filter & Search Toolbar -->
<div class="admin-filter-bar mb-6">
  <form action="<?= e(url('/admin/events')) ?>" method="GET" style="display: flex; flex-direction: column; gap: 0.85rem;">
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

    <!-- Status Tabs -->
    <div class="filter-tabs" role="tablist" aria-label="Filter events by status">
      <a href="<?= e($buildUrl(null, false)) ?>" 
         class="filter-tab <?= empty($filters['status']) && empty($isTrash) ? 'is-active' : '' ?>">
        <span>All</span>
        <span class="badge-count"><?= e((string) ($counts['total'] ?? array_sum($counts))) ?></span>
      </a>

      <a href="<?= e($buildUrl('published', false)) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'published' ? 'is-active' : '' ?>">
        <span>Published</span>
        <span class="badge-count"><?= e((string) ($counts['published'] ?? 0)) ?></span>
      </a>

      <a href="<?= e($buildUrl('ongoing', false)) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'ongoing' ? 'is-active' : '' ?>">
        <span>Ongoing</span>
        <span class="badge-count"><?= e((string) ($counts['ongoing'] ?? 0)) ?></span>
      </a>

      <a href="<?= e($buildUrl('draft', false)) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'draft' ? 'is-active' : '' ?>">
        <span>Drafts</span>
        <span class="badge-count"><?= e((string) ($counts['draft'] ?? 0)) ?></span>
      </a>

      <a href="<?= e($buildUrl('completed', false)) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'completed' ? 'is-active' : '' ?>">
        <span>Completed</span>
        <span class="badge-count"><?= e((string) ($counts['completed'] ?? 0)) ?></span>
      </a>

      <a href="<?= e($buildUrl('cancelled', false)) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'cancelled' ? 'is-active' : '' ?>">
        <span>Cancelled</span>
        <span class="badge-count"><?= e((string) ($counts['cancelled'] ?? 0)) ?></span>
      </a>

      <?php if (!empty($canDelete)): ?>
        <a href="<?= e($buildUrl(null, true)) ?>" 
           class="filter-tab <?= !empty($isTrash) ? 'is-active' : '' ?>" style="<?= !empty($isTrash) ? 'background-color: var(--color-danger);' : '' ?>">
          <span>Trash</span>
        </a>
      <?php endif; ?>
    </div>

    <!-- Multi-select Filter Bar -->
    <div class="filter-controls">
      <div class="filter-controls-group">
        <?php if (!empty($filters['status'])): ?>
          <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <?php endif; ?>
        <?php if (!empty($isTrash)): ?>
          <input type="hidden" name="trash" value="1">
        <?php endif; ?>

        <!-- Campaign Selector -->
        <select name="campaign_id" class="form-control" style="width: auto; min-width: 170px; height: 38px; font-size: var(--font-size-sm);" aria-label="Filter by campaign">
          <option value="">All Campaigns</option>
          <?php foreach ($campaigns as $camp): ?>
            <option value="<?= e((string) $camp['id']) ?>" <?= (!empty($filters['campaign_id']) && (int) $filters['campaign_id'] === (int) $camp['id']) ? 'selected' : '' ?>>
              <?= e($camp['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Category Selector -->
        <select name="category" class="form-control" style="width: auto; min-width: 140px; height: 38px; font-size: var(--font-size-sm);" aria-label="Filter by category">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= (($filters['category'] ?? '') === $cat) ? 'selected' : '' ?>>
              <?= e(ucwords(str_replace('_', ' ', $cat))) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Format Selector -->
        <select name="format" class="form-control" style="width: auto; min-width: 120px; height: 38px; font-size: var(--font-size-sm);" aria-label="Filter by format">
          <option value="">All Formats</option>
          <?php foreach ($formats as $fmt): ?>
            <option value="<?= e($fmt) ?>" <?= (($filters['format'] ?? '') === $fmt) ? 'selected' : '' ?>>
              <?= e(ucwords(str_replace('_', ' ', $fmt))) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Search Input -->
        <div class="search-input-wrapper">
          <span class="search-icon" aria-hidden="true"><?= icon('search') ?></span>
          <input 
            type="search" 
            name="search" 
            value="<?= e($filters['search'] ?? '') ?>" 
            placeholder="Search events..." 
            class="form-control" 
            aria-label="Search events"
          >
        </div>

        <button type="submit" class="btn btn-secondary btn-auto">
          <?= icon('filter', ['class' => 'svg-icon-sm']) ?>
          <span>Filter</span>
        </button>

        <?php if (!empty($filters['campaign_id']) || !empty($filters['status']) || !empty($filters['category']) || !empty($filters['format']) || !empty($filters['search']) || !empty($isTrash)): ?>
          <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-auto" title="Clear filters">
            Reset
          </a>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<!-- Event Roster Table -->
<?php if (empty($events)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">
      <?= icon('calendar') ?>
    </div>
    <h2 class="empty-state-title">No events found</h2>
    <p class="empty-state-desc">
      <?= (!empty($filters['search']) || !empty($filters['campaign_id']) || !empty($filters['status'])) 
        ? 'Try refining your filters or resetting the search query.' 
        : 'Click "New Event" above to schedule an initiative.' ?>
    </p>
    <?php if (!empty($canCreate)): ?>
      <a href="<?= e(url('/admin/events/create')) ?>" class="btn btn-primary btn-auto">
        <?= icon('plus', ['class' => 'svg-icon-sm']) ?>
        <span>Create Event</span>
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table" aria-label="Events list">
      <thead>
        <tr>
          <th style="width: 26%;">Event &amp; Category</th>
          <th style="width: 16%;">Campaign</th>
          <th style="width: 14%;">Modality / Venue</th>
          <th style="width: 16%;">Schedule</th>
          <th style="width: 8%;">Capacity</th>
          <th style="width: 8%;">Status</th>
          <th style="width: 12%; text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
          <?php
            $st = $ev['status'] ?? 'draft';
            $pillClass = match ($st) {
                'published' => 'badge-pill-success',
                'ongoing'   => 'badge-pill-info',
                'completed' => 'badge-pill-neutral',
                'cancelled' => 'badge-pill-danger',
                default     => 'badge-pill-warning',
            };
            $fmtPill = match ($ev['format'] ?? 'in_person') {
                'online'    => 'badge-pill-info',
                'hybrid'    => 'badge-pill-warning',
                default     => 'badge-pill-neutral',
            };
            $isSoftDeleted = !empty($ev['deleted_at']);
          ?>
          <tr style="<?= $isSoftDeleted ? 'opacity: 0.65; background-color: var(--bg-surface-subtle);' : '' ?>">
            <td>
              <div style="font-weight: var(--font-weight-semibold); color: var(--text-primary); margin-bottom: 0.2rem;">
                <a href="<?= e(url('/admin/events/' . $ev['id'])) ?>" style="color: inherit; text-decoration: none;">
                  <?= e($ev['title']) ?>
                </a>
              </div>
              <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                <span class="badge-pill badge-pill-neutral" style="font-size: 10px; padding: 0.15rem 0.45rem;">
                  <?= e(ucwords(str_replace('_', ' ', $ev['category'] ?? 'workshop'))) ?>
                </span>
                <code style="font-size: 10px; background: var(--bg-surface-subtle); padding: 0.1rem 0.35rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                  <?= e($ev['slug']) ?>
                </code>
              </div>
            </td>

            <td>
              <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-medium);">
                <a href="<?= !empty($ev['campaign_id']) ? e(url('/admin/campaigns/' . $ev['campaign_id'])) : '#' ?>" style="color: inherit; text-decoration: none;" title="View Parent Campaign">
                  <?= e($ev['campaign_title'] ?? (!empty($ev['campaign_id']) ? 'Campaign #' . $ev['campaign_id'] : 'General')) ?>
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
                <span class="badge-pill <?= e($fmtPill) ?>" style="font-size: 10px;">
                  <?= e(ucfirst(str_replace('_', '-', $ev['format'] ?? 'in_person'))) ?>
                </span>
              </div>
              <div class="text-caption text-secondary" style="font-size: 11px; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($ev['venue_name'] ?? $ev['online_meeting_url'] ?? '') ?>">
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
                <span class="badge-pill badge-pill-danger">
                  <span class="badge-pill-dot" aria-hidden="true"></span>
                  Deleted
                </span>
              <?php else: ?>
                <span class="badge-pill <?= e($pillClass) ?>">
                  <span class="badge-pill-dot" aria-hidden="true"></span>
                  <?= e(ucfirst($st)) ?>
                </span>
              <?php endif; ?>
            </td>

            <td style="text-align: right;">
              <div class="table-actions">
                <a href="<?= e(url('/admin/events/' . $ev['id'])) ?>" class="btn-icon" title="View Event" aria-label="View event <?= e($ev['title']) ?>">
                  <?= icon('eye') ?>
                </a>

                <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
                  <a href="<?= e(url('/admin/events/' . $ev['id'] . '/edit')) ?>" class="btn-icon btn-icon-primary" title="Edit Event" aria-label="Edit event <?= e($ev['title']) ?>">
                    <?= icon('pencil') ?>
                  </a>
                <?php endif; ?>

                <?php if (!$isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/events/' . $ev['id'] . '/delete')) ?>" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to delete event &quot;<?= e(addslashes($ev['title'])) ?>&quot;?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-icon btn-icon-danger" title="Delete Event" aria-label="Delete event <?= e($ev['title']) ?>">
                      <?= icon('trash') ?>
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/events/' . $ev['id'] . '/restore')) ?>" method="POST" style="margin: 0; display: inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-icon btn-icon-primary" title="Restore Event" aria-label="Restore event <?= e($ev['title']) ?>">
                      <?= icon('refresh') ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
