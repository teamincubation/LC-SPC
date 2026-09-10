<!-- Participant Directory Header -->
<div class="card mb-6">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.25rem;">
        Participant Directory
      </h1>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Canonical attendee identities, stakeholder categories, contact governance, and compliance records.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <?php if (!empty($canCreate)): ?>
        <a href="<?= e(url('/admin/participants/create')) ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.35rem;">
          <span aria-hidden="true">&#43;</span>
          <span>Register Participant</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- KPI Status Metrics -->
<section class="grid grid-cols-4 mb-6" aria-label="Participant Overview Metrics">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      &#10003;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['active'] ?? 0)) ?></div>
    <div class="card-metric-label">Active Participants</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-warning); color: var(--text-warning);">
      &#9873;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['flagged'] ?? 0)) ?></div>
    <div class="card-metric-label">Flagged for Review</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-danger); color: var(--text-danger);">
      &#9888;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['blocked'] ?? 0)) ?></div>
    <div class="card-metric-label">Blocked</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      &#128101;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['total'] ?? 0)) ?></div>
    <div class="card-metric-label">Total Participants</div>
  </div>
</section>

<!-- Filter & Search Toolbar -->
<div class="card mb-6" style="padding: 1rem 1.25rem;">
  <form action="<?= e(url('/admin/participants')) ?>" method="GET" style="display: flex; flex-direction: column; gap: 0.85rem;">
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

        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <?php if (!empty($filters['status']) || !empty($filters['category']) || !empty($filters['search'])): ?>
          <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline btn-sm" title="Clear all filters">Reset</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status Filter Pills -->
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; border-top: 1px solid var(--border-color); padding-top: 0.6rem;">
      <span class="text-caption text-secondary" style="font-weight: var(--font-weight-medium); margin-right: 0.25rem;">Status:</span>
      
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
         class="badge <?= empty($filters['status']) ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        All (<?= e((string) ($counts['total'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('active')) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'active' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        Active (<?= e((string) ($counts['active'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('flagged')) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'flagged' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
        Flagged (<?= e((string) ($counts['flagged'] ?? 0)) ?>)
      </a>

      <a href="<?= e($buildUrl('blocked')) ?>" 
         class="badge <?= ($filters['status'] ?? '') === 'blocked' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.3rem 0.65rem; font-size: var(--font-size-xs);">
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
          <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;" aria-hidden="true">&#128101;</div>
            <p style="font-weight: var(--font-weight-medium); margin-bottom: 0.25rem;">No participants found.</p>
            <span class="text-caption text-muted">
              <?= (!empty($filters['search']) || !empty($filters['category']) || !empty($filters['status'])) 
                ? 'Try refining your search keyword or clearing the filters.' 
                : 'Click "Register Participant" above to add the first profile.' ?>
            </span>
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
              <span class="badge <?= e($catBadge) ?>" style="font-size: 10px; padding: 0.15rem 0.4rem;">
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
              <span class="badge <?= e($statusBadge) ?>">
                <span class="badge-dot" aria-hidden="true"></span>
                <?= e(ucfirst($st)) ?>
              </span>
            </td>

            <td style="text-align: right; white-space: nowrap;">
              <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <a href="<?= e(url('/admin/participants/' . $pt['id'])) ?>" class="btn btn-outline btn-sm" title="View Profile">
                  View
                </a>

                <?php if (!empty($canEdit)): ?>
                  <a href="<?= e(url('/admin/participants/' . $pt['id'] . '/edit')) ?>" class="btn btn-outline btn-sm" title="Edit Participant">
                    Edit
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
