<!-- Registration Directory Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <h1>Event Registrations</h1>
    <p>Enrollment records, pass codes, capacity tracking, and attendee roster governance.</p>
  </div>

  <div class="admin-page-header-actions">
    <?php if (in_array($userRole, ['super_admin', 'coordinator'], true)): ?>
      <a href="<?= e(url('/admin/registrations/create')) ?>" class="btn btn-primary btn-sm">
        <?= icon('plus', ['width' => '14', 'height' => '14']) ?>
        <span>New Registration</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI Status Metrics -->
<section class="metric-grid mb-6" aria-label="Registration Overview Metrics">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      <?= icon('check-circle', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($kpis['confirmed'] ?? 0)) ?></div>
    <div class="card-metric-label">Confirmed Passes</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-warning); color: var(--text-warning);">
      <?= icon('clock', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($kpis['pending'] ?? 0)) ?></div>
    <div class="card-metric-label">Pending Approval</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      <?= icon('bookmark', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($kpis['waitlisted'] ?? 0)) ?></div>
    <div class="card-metric-label">Waitlisted</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      <?= icon('file-text', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($kpis['total'] ?? 0)) ?></div>
    <div class="card-metric-label">Total Enrollments</div>
  </div>
</section>

<!-- Filter & Search Toolbar -->
<div class="admin-filter-bar mb-6">
  <form action="<?= e(url('/admin/registrations')) ?>" method="GET" style="display: flex; flex-direction: column; gap: 0.85rem; width: 100%;">
    <!-- Top Filter Row: Campaign, Event, Keyword Search, Buttons -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
      <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; flex: 1;">
        <!-- Campaign Selector -->
        <select name="campaign_id" class="form-control" style="width: auto; min-width: 170px; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);" aria-label="Filter by campaign">
          <option value="">All Campaigns</option>
          <?php foreach ($campaigns as $camp): ?>
            <option value="<?= e((string) $camp['id']) ?>" <?= ((int) ($filters['campaign_id'] ?? 0) === (int) $camp['id']) ? 'selected' : '' ?>>
              <?= e($camp['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Event Selector -->
        <select name="event_id" class="form-control" style="width: auto; min-width: 180px; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);" aria-label="Filter by event">
          <option value="">All Events</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= e((string) $ev['id']) ?>" <?= ((int) ($filters['event_id'] ?? 0) === (int) $ev['id']) ? 'selected' : '' ?>>
              <?= e($ev['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Keyword Search -->
        <input 
          type="search" 
          name="search" 
          value="<?= e($filters['search'] ?? '') ?>" 
          placeholder="Search pass code, name, email, phone..." 
          class="form-control" 
          style="min-width: 220px; flex: 1; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);"
          aria-label="Search registrations"
        >

        <button type="submit" class="btn btn-outline btn-sm">
          <?= icon('search', ['width' => '13', 'height' => '13']) ?>
          <span>Filter</span>
        </button>
        <?php if (!empty($filters['campaign_id']) || !empty($filters['event_id']) || !empty($filters['status']) || !empty($filters['search'])): ?>
          <a href="<?= e(url('/admin/registrations')) ?>" class="btn btn-outline btn-sm" title="Clear all filters">
            <?= icon('x', ['width' => '13', 'height' => '13']) ?>
            <span>Reset</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="filter-tabs" style="border-top: 1px solid var(--border-color); padding-top: 0.6rem; margin-bottom: 0;">
      <span class="text-caption text-secondary" style="font-weight: var(--font-weight-medium); margin-right: 0.25rem; align-self: center;">Status:</span>
      
      <?php
        $baseQuery = [];
        if (!empty($filters['campaign_id'])) $baseQuery['campaign_id'] = $filters['campaign_id'];
        if (!empty($filters['event_id'])) $baseQuery['event_id'] = $filters['event_id'];
        if (!empty($filters['search'])) $baseQuery['search'] = $filters['search'];
        
        $currentStatus = $filters['status'] ?? '';
      ?>
      <a href="<?= e(url('/admin/registrations', $baseQuery)) ?>" class="filter-tab <?= empty($currentStatus) ? 'active' : '' ?>">All</a>
      <a href="<?= e(url('/admin/registrations', array_merge($baseQuery, ['status' => 'confirmed']))) ?>" class="filter-tab <?= ($currentStatus === 'confirmed') ? 'active' : '' ?>">Confirmed</a>
      <a href="<?= e(url('/admin/registrations', array_merge($baseQuery, ['status' => 'pending']))) ?>" class="filter-tab <?= ($currentStatus === 'pending') ? 'active' : '' ?>">Pending</a>
      <a href="<?= e(url('/admin/registrations', array_merge($baseQuery, ['status' => 'waitlisted']))) ?>" class="filter-tab <?= ($currentStatus === 'waitlisted') ? 'active' : '' ?>">Waitlisted</a>
      <a href="<?= e(url('/admin/registrations', array_merge($baseQuery, ['status' => 'cancelled']))) ?>" class="filter-tab <?= ($currentStatus === 'cancelled') ? 'active' : '' ?>">Cancelled</a>
    </div>
  </form>
</div>

<!-- Privacy Shield Notification for Masked Roles -->
<?php if (in_array($userRole, ['staff', 'viewer'], true)): ?>
  <div class="alert alert-info mb-6" style="display: flex; align-items: center; gap: 0.75rem; font-size: var(--font-size-sm); background-color: var(--bg-surface-subtle); border-left: 4px solid var(--color-primary);">
    <div style="color: var(--color-primary); display: flex; align-items: center;">
      <?= icon('shield', ['width' => '20', 'height' => '20']) ?>
    </div>
    <div>
      <strong>Privacy Shield Active:</strong> Contact emails and phone numbers are masked for role <code><?= e($userRole) ?></code> in compliance with data minimization rules.
    </div>
  </div>
<?php endif; ?>

<!-- Registration Roster Table -->
<div class="card">
  <div class="card-body" style="padding: 0; overflow-x: auto;">
    <table class="table" style="width: 100%; border-collapse: collapse;">
      <thead>
        <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
          <th style="padding: 0.75rem 1rem;">Pass Code</th>
          <th style="padding: 0.75rem 1rem;">Attendee Name</th>
          <th style="padding: 0.75rem 1rem;">Event & Campaign</th>
          <th style="padding: 0.75rem 1rem;">Category</th>
          <th style="padding: 0.75rem 1rem;">Status</th>
          <th style="padding: 0.75rem 1rem;">Enrolled At</th>
          <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <div class="empty-state-icon">
                  <?= icon('clipboard', ['width' => '32', 'height' => '32']) ?>
                </div>
                <div class="empty-state-title">No registrations found</div>
                <div class="empty-state-description">
                  No registration records match the selected filter criteria.
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <?php
              $status = $item['status'] ?? 'confirmed';
              $badgeClass = match ($status) {
                'confirmed'  => 'badge-success',
                'pending'    => 'badge-warning',
                'waitlisted' => 'badge-neutral',
                'cancelled'  => 'badge-danger',
                default      => 'badge-neutral',
              };
            ?>
            <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.15s ease;">
              <!-- Pass Code -->
              <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                <code style="font-weight: var(--font-weight-bold); font-size: var(--font-size-sm); color: var(--color-primary-dark);">
                  <?= e($item['registration_code']) ?>
                </code>
              </td>

              <!-- Attendee Name & Contact Snapshot -->
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: var(--font-weight-medium); color: var(--text-primary);">
                  <?= e($item['participant_name']) ?>
                </div>
                <div class="text-secondary" style="font-size: var(--font-size-xs);">
                  <?php if (!empty($item['participant_email'])): ?>
                    <span><?= e($item['participant_email']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($item['participant_phone'])): ?>
                    <span style="margin-left: 0.35rem;">&bull; <?= e($item['participant_phone']) ?></span>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Event & Campaign -->
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: var(--font-weight-medium); color: var(--text-primary);">
                  <?= e($item['event_title']) ?>
                </div>
                <div class="text-secondary" style="font-size: var(--font-size-xs);">
                  <?= e($item['campaign_title']) ?>
                </div>
              </td>

              <!-- Category -->
              <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                <span class="badge badge-pill badge-neutral" style="text-transform: capitalize;">
                  <?= e($item['participant_category']) ?>
                </span>
              </td>

              <!-- Status Badge -->
              <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                <span class="badge badge-pill <?= e($badgeClass) ?>" style="text-transform: capitalize;">
                  <span class="badge-dot" aria-hidden="true"></span>
                  <?= e($status) ?>
                </span>
              </td>

              <!-- Enrolled Timestamp -->
              <td style="padding: 0.75rem 1rem; white-space: nowrap; font-size: var(--font-size-sm);" class="text-secondary">
                <?= e(date('M d, Y H:i', strtotime((string) $item['created_at']))) ?>
              </td>

              <!-- Actions -->
              <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                  <a href="<?= e(url('/admin/registrations/' . $item['id'])) ?>" class="btn btn-sm btn-outline btn-icon" title="View Details" aria-label="View Details">
                    <?= icon('eye', ['width' => '13', 'height' => '13']) ?>
                  </a>
                  <?php if ($status === 'confirmed'): ?>
                    <a href="<?= e(url('/admin/registrations/' . $item['id'] . '/pass')) ?>" class="btn btn-sm btn-outline btn-icon" title="View Digital Pass" aria-label="View Digital Pass">
                      <?= icon('credit-card', ['width' => '13', 'height' => '13']) ?>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Controls -->
  <?php if (!empty($pagination) && ($pagination['total_pages'] > 1)): ?>
    <div class="card-footer" style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.25rem; flex-wrap: wrap; gap: 0.75rem; border-top: 1px solid var(--border-color);">
      <div class="text-secondary" style="font-size: var(--font-size-sm);">
        Showing page <strong><?= e((string) $pagination['page']) ?></strong> of <strong><?= e((string) $pagination['total_pages']) ?></strong> (<?= e((string) $pagination['total']) ?> total records)
      </div>

      <div style="display: flex; gap: 0.35rem;">
        <?php
          $qParams = $filters;
          if ($pagination['page'] > 1):
            $qParams['page'] = $pagination['page'] - 1;
        ?>
          <a href="<?= e(url('/admin/registrations', $qParams)) ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
        <?php endif; ?>

        <?php
          if ($pagination['has_more']):
            $qParams['page'] = $pagination['page'] + 1;
        ?>
          <a href="<?= e(url('/admin/registrations', $qParams)) ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
