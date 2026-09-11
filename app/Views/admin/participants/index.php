<!-- Participant Directory Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <h1>Participant Directory</h1>
    <p>Canonical attendee identities, stakeholder categories, contact governance, and compliance records.</p>
  </div>

  <div class="admin-page-header-actions">
    <?php if (!empty($canCreate)): ?>
      <a href="<?= e(url('/admin/participants/create')) ?>" class="btn btn-primary btn-sm">
        <?= icon('plus', ['width' => '14', 'height' => '14']) ?>
        <span>Register Participant</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI Status Metrics -->
<section class="metric-grid mb-6" aria-label="Participant Overview Metrics">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      <?= icon('check-circle', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['active'] ?? 0)) ?></div>
    <div class="card-metric-label">Active Participants</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-warning); color: var(--text-warning);">
      <?= icon('flag', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['flagged'] ?? 0)) ?></div>
    <div class="card-metric-label">Flagged for Review</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-danger); color: var(--text-danger);">
      <?= icon('alert-triangle', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['blocked'] ?? 0)) ?></div>
    <div class="card-metric-label">Blocked</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      <?= icon('users', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['total'] ?? 0)) ?></div>
    <div class="card-metric-label">Total Participants</div>
  </div>
</section>

<!-- Filter & Search Toolbar -->
<div class="admin-filter-bar mb-6">
  <form action="<?= e(url('/admin/participants')) ?>" method="GET" style="display: flex; flex-direction: column; gap: 0.85rem; width: 100%;">
    <!-- Top Filter Bar: Category, Keyword Search, Submit/Reset -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
      <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; flex: 1;">
        <!-- Category Selector -->
        <select name="category" class="form-control" style="width: auto; min-width: 170px; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);" aria-label="Filter by category">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= (($filters['category'] ?? '') === $cat) ? 'selected' : '' ?>>
              <?= e(ucfirst($cat)) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Search Input -->
        <input 
          type="search" 
          name="search" 
          value="<?= e($filters['search'] ?? '') ?>" 
          placeholder="Search name, email, phone, organization..." 
          class="form-control" 
          style="min-width: 240px; flex: 1; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);"
          aria-label="Search participants"
        >

        <button type="submit" class="btn btn-outline btn-sm">
          <?= icon('search', ['width' => '13', 'height' => '13']) ?>
          <span>Filter</span>
        </button>
        <?php if (!empty($filters['status']) || !empty($filters['category']) || !empty($filters['search'])): ?>
          <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline btn-sm" title="Clear all filters">
            <?= icon('x', ['width' => '13', 'height' => '13']) ?>
            <span>Reset</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status Filter Pills -->
    <div class="filter-tabs" style="border-top: 1px solid var(--border-color); padding-top: 0.6rem; margin-bottom: 0;">
      <span class="text-caption text-secondary" style="font-weight: var(--font-weight-medium); margin-right: 0.25rem; align-self: center;">Status:</span>
      
      <?php
        $baseQuery = [];
        if (!empty($filters['category'])) $baseQuery['category'] = $filters['category'];
        if (!empty($filters['search'])) $baseQuery['search'] = $filters['search'];
        
        $buildUrl = function (?string $status = null) use ($baseQuery) {
            $q = $baseQuery;
            if ($status !== null) $q['status'] = $status;
            return url('/admin/participants' . (!empty($q) ? '?' . http_build_query($q) : ''));
        };
      ?>

      <a href="<?= e($buildUrl(null)) ?>" 
         class="filter-tab <?= empty($filters['status']) ? 'active' : '' ?>">
        All (<?= e((string) ($counts['total'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('active')) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'active' ? 'active' : '' ?>">
        Active (<?= e((string) ($counts['active'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('flagged')) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'flagged' ? 'active' : '' ?>">
        Flagged (<?= e((string) ($counts['flagged'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('blocked')) ?>" 
         class="filter-tab <?= ($filters['status'] ?? '') === 'blocked' ? 'active' : '' ?>">
        Blocked (<?= e((string) ($counts['blocked'] ?? 0)) ?>)
      </a>
    </div>
  </form>
</div>

<!-- Participant Roster Table -->
<div class="table-responsive">
  <table class="table table-hover" aria-label="Participants list">
    <thead>
      <tr>
        <th style="width: 8%;">ID</th>
        <th style="width: 26%;">Participant &amp; Stakeholder Category</th>
        <th style="width: 20%;">Organization / Affiliation</th>
        <th style="width: 24%;">Contact Information</th>
        <th style="width: 10%;">Status</th>
        <th style="width: 12%; text-align: right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pagination['items'])): ?>
        <tr>
          <td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon">
                <?= icon('users', ['width' => '32', 'height' => '32']) ?>
              </div>
              <div class="empty-state-title">No participants found</div>
              <div class="empty-state-description">
                <?= (!empty($filters['search']) || !empty($filters['category']) || !empty($filters['status'])) 
                  ? 'Try refining your search keyword or clearing the filters.' 
                  : 'Click "Register Participant" above to add the first profile.' ?>
              </div>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($pagination['items'] as $pt): ?>
          <?php
            $st = $pt['status'] ?? 'active';
            $statusBadge = match ($st) {
                'active'  => 'badge-success',
                'flagged' => 'badge-warning',
                'blocked' => 'badge-danger',
                default   => 'badge-neutral',
            };
            $catBadge = match ($pt['category'] ?? 'community') {
                'student'      => 'badge-primary',
                'professional' => 'badge-info',
                default        => 'badge-neutral',
            };
          ?>
          <tr>
            <td>
              <code style="font-size: 11px; background: var(--bg-surface-subtle); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                #<?= e((string) $pt['id']) ?>
              </code>
            </td>

            <td>
              <div style="font-weight: var(--font-weight-semibold); color: var(--text-primary); margin-bottom: 0.2rem;">
                <a href="<?= e(url('/admin/participants/' . $pt['id'])) ?>" style="color: inherit; text-decoration: none;">
                  <?= e($pt['full_name']) ?>
                </a>
              </div>
              <span class="badge badge-pill <?= e($catBadge) ?>" style="font-size: 10px;">
                <?= e(ucfirst($pt['category'] ?? 'community')) ?>
              </span>
            </td>

            <td>
              <div style="font-size: var(--font-size-xs);">
                <?= !empty($pt['organization_name']) ? e($pt['organization_name']) : '<span class="text-muted">&mdash;</span>' ?>
              </div>
            </td>

            <td>
              <div style="font-size: var(--font-size-xs);">
                <?php if (!empty($pt['email'])): ?>
                  <div><?= e($pt['email']) ?></div>
                <?php endif; ?>
                <?php if (!empty($pt['phone'])): ?>
                  <div class="text-caption text-secondary" style="font-size: 11px;"><?= e($pt['phone']) ?></div>
                <?php endif; ?>
                <?php if (empty($pt['email']) && empty($pt['phone'])): ?>
                  <span class="text-muted text-caption">No contact info provided</span>
                <?php endif; ?>
              </div>
            </td>

            <td>
              <span class="badge badge-pill <?= e($statusBadge) ?>">
                <span class="badge-dot" aria-hidden="true"></span>
                <?= e(ucfirst($st)) ?>
              </span>
            </td>

            <td style="text-align: right; white-space: nowrap;">
              <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <a href="<?= e(url('/admin/participants/' . $pt['id'])) ?>" class="btn btn-outline btn-sm btn-icon" title="View Profile" aria-label="View Profile">
                  <?= icon('eye', ['width' => '13', 'height' => '13']) ?>
                </a>

                <?php if (!empty($canEdit)): ?>
                  <a href="<?= e(url('/admin/participants/' . $pt['id'] . '/edit')) ?>" class="btn btn-outline btn-sm btn-icon" title="Edit Participant" aria-label="Edit Participant">
                    <?= icon('edit', ['width' => '13', 'height' => '13']) ?>
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

<!-- Server-Side Pagination Bar -->
<?php if (!empty($pagination['total']) && $pagination['total'] > 0): ?>
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 1rem 0.25rem;">
    <div class="text-caption text-secondary">
      <?php
        $startItem = (($pagination['page'] - 1) * $pagination['per_page']) + 1;
        $endItem = min($pagination['total'], $pagination['page'] * $pagination['per_page']);
      ?>
      Showing <strong><?= e((string) $startItem) ?></strong> to <strong><?= e((string) $endItem) ?></strong> of <strong><?= e((string) $pagination['total']) ?></strong> participants
    </div>

    <?php if ($pagination['last_page'] > 1): ?>
      <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
        <?php
          $queryForPage = function (int $pageNum) use ($filters) {
              $params = array_filter($filters, fn($v) => $v !== null && $v !== '');
              $params['page'] = $pageNum;
              return url('/admin/participants?' . http_build_query($params));
          };
        ?>

        <?php if ($pagination['page'] > 1): ?>
          <a href="<?= e($queryForPage($pagination['page'] - 1)) ?>" class="btn btn-outline btn-sm">
            &laquo; Previous
          </a>
        <?php else: ?>
          <button class="btn btn-outline btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">
            &laquo; Previous
          </button>
        <?php endif; ?>

        <span style="font-size: var(--font-size-xs); padding: 0 0.5rem;">
          Page <strong><?= e((string) $pagination['page']) ?></strong> of <strong><?= e((string) $pagination['last_page']) ?></strong>
        </span>

        <?php if ($pagination['has_more']): ?>
          <a href="<?= e($queryForPage($pagination['page'] + 1)) ?>" class="btn btn-outline btn-sm">
            Next &raquo;
          </a>
        <?php else: ?>
          <button class="btn btn-outline btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">
            Next &raquo;
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
