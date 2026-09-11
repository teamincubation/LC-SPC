<?php

declare(strict_types=1);

/**
 * Modernized Campaign Management Roster
 * Aligned to 12-Panel Design System Reference (Panel 3: Campaigns).
 */
?>

<!-- Campaign Page Header -->
<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Campaign Management</h1>
    <p class="admin-page-desc">Multi-year initiatives, themes, timelines, and program governance.</p>
  </div>

  <div class="admin-page-actions">
    <?php if (!empty($canCreate)): ?>
      <a href="<?= e(url('/admin/campaigns/create')) ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
        <?= icon('plus', ['class' => 'svg-icon-sm']) ?>
        <span>New Campaign</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI Status Metrics -->
<section class="metric-grid mb-6" aria-label="Campaign Overview Metrics">
  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #ECFDF5; color: #059669;">
        <?= icon('target') ?>
      </div>
      <span class="badge-pill badge-pill-success">Active</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['active'] ?? 0)) ?></div>
      <div class="metric-card-label">Active Campaigns</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #FFFBEB; color: #D97706;">
        <?= icon('pencil') ?>
      </div>
      <span class="badge-pill badge-pill-warning">Draft</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['draft'] ?? 0)) ?></div>
      <div class="metric-card-label">Draft Campaigns</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: #EFF6FF; color: #2563EB;">
        <?= icon('award') ?>
      </div>
      <span class="badge-pill badge-pill-info">Completed</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['completed'] ?? 0)) ?></div>
      <div class="metric-card-label">Completed</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-card-top">
      <div class="metric-card-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
        <?= icon('folder') ?>
      </div>
      <span class="badge-pill badge-pill-neutral">Total</span>
    </div>
    <div class="metric-card-body">
      <div class="metric-card-value"><?= e((string) ($counts['total'] ?? 0)) ?></div>
      <div class="metric-card-label">Total Campaigns</div>
    </div>
  </div>
</section>

<!-- Filter Toolbar & Search -->
<div class="admin-filter-bar mb-6">
  <form action="<?= e(url('/admin/campaigns')) ?>" method="GET" style="display: flex; flex-direction: column; gap: 0.75rem;">
    <!-- Status Filter Pills / Tabs -->
    <div class="filter-tabs" role="tablist" aria-label="Filter campaigns by status">
      <a href="<?= e(url('/admin/campaigns' . ($search ? '?search=' . urlencode($search) : ''))) ?>" 
         class="filter-tab <?= empty($currentStatus) && empty($isTrash) ? 'is-active' : '' ?>">
        <span>All</span>
        <span class="badge-count"><?= e((string) ($counts['total'] ?? 0)) ?></span>
      </a>

      <a href="<?= e(url('/admin/campaigns?status=active' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="filter-tab <?= ($currentStatus ?? '') === 'active' ? 'is-active' : '' ?>">
        <span>Active</span>
        <span class="badge-count"><?= e((string) ($counts['active'] ?? 0)) ?></span>
      </a>

      <a href="<?= e(url('/admin/campaigns?status=draft' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="filter-tab <?= ($currentStatus ?? '') === 'draft' ? 'is-active' : '' ?>">
        <span>Drafts</span>
        <span class="badge-count"><?= e((string) ($counts['draft'] ?? 0)) ?></span>
      </a>

      <a href="<?= e(url('/admin/campaigns?status=completed' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="filter-tab <?= ($currentStatus ?? '') === 'completed' ? 'is-active' : '' ?>">
        <span>Completed</span>
        <span class="badge-count"><?= e((string) ($counts['completed'] ?? 0)) ?></span>
      </a>

      <a href="<?= e(url('/admin/campaigns?status=archived' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="filter-tab <?= ($currentStatus ?? '') === 'archived' ? 'is-active' : '' ?>">
        <span>Archived</span>
        <span class="badge-count"><?= e((string) ($counts['archived'] ?? 0)) ?></span>
      </a>

      <?php if (!empty($canDelete)): ?>
        <a href="<?= e(url('/admin/campaigns?trash=1' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
           class="filter-tab <?= !empty($isTrash) ? 'is-active' : '' ?>" style="<?= !empty($isTrash) ? 'background-color: var(--color-danger);' : '' ?>">
          <span>Trash</span>
        </a>
      <?php endif; ?>
    </div>

    <!-- Search Controls -->
    <div class="filter-controls">
      <div class="filter-controls-group">
        <?php if (!empty($currentStatus)): ?>
          <input type="hidden" name="status" value="<?= e($currentStatus) ?>">
        <?php endif; ?>
        <?php if (!empty($isTrash)): ?>
          <input type="hidden" name="trash" value="1">
        <?php endif; ?>

        <div class="search-input-wrapper">
          <span class="search-icon" aria-hidden="true"><?= icon('search') ?></span>
          <input 
            type="search" 
            name="search" 
            value="<?= e($search ?? '') ?>" 
            placeholder="Search campaigns..." 
            class="form-control" 
            aria-label="Search campaigns"
          >
        </div>

        <button type="submit" class="btn btn-secondary btn-auto">
          <?= icon('filter', ['class' => 'svg-icon-sm']) ?>
          <span>Filter</span>
        </button>

        <?php if (!empty($search) || !empty($currentStatus) || !empty($isTrash)): ?>
          <a href="<?= e(url('/admin/campaigns')) ?>" class="btn btn-outline btn-auto" title="Clear filters">
            Reset
          </a>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<!-- Campaign Roster Table -->
<?php if (empty($campaigns)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">
      <?= icon('target') ?>
    </div>
    <h2 class="empty-state-title">No campaigns found</h2>
    <p class="empty-state-desc">
      <?= !empty($search) ? 'No campaign matches the given search keyword or filter.' : 'Create your first campaign initiative to begin managing LC-SPC activities.' ?>
    </p>
    <?php if (!empty($canCreate)): ?>
      <a href="<?= e(url('/admin/campaigns/create')) ?>" class="btn btn-primary btn-auto">
        <?= icon('plus', ['class' => 'svg-icon-sm']) ?>
        <span>Create Campaign</span>
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table" aria-label="Campaigns list">
      <thead>
        <tr>
          <th style="width: 28%;">Title &amp; Theme</th>
          <th style="width: 18%;">Slug Identifier</th>
          <th style="width: 20%;">Timeline</th>
          <th style="width: 14%;">Status</th>
          <th style="width: 20%; text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($campaigns as $camp): ?>
          <?php
            $st = $camp['status'] ?? 'draft';
            $pillClass = match ($st) {
                'active'    => 'badge-pill-success',
                'completed' => 'badge-pill-info',
                'archived'  => 'badge-pill-neutral',
                default     => 'badge-pill-warning',
            };
            $isSoftDeleted = !empty($camp['deleted_at']);
          ?>
          <tr style="<?= $isSoftDeleted ? 'opacity: 0.65; background-color: var(--bg-surface-subtle);' : '' ?>">
            <td>
              <div style="font-weight: var(--font-weight-semibold); color: var(--text-primary);">
                <a href="<?= e(url('/admin/campaigns/' . $camp['id'])) ?>" style="color: inherit; text-decoration: none;">
                  <?= e($camp['title']) ?>
                </a>
              </div>
              <?php if (!empty($camp['theme'])): ?>
                <div class="text-caption text-secondary" style="font-style: italic;">
                  &ldquo;<?= e($camp['theme']) ?>&rdquo;
                </div>
              <?php endif; ?>
            </td>

            <td>
              <code style="font-size: var(--font-size-xs); background: var(--bg-surface-subtle); padding: 0.2rem 0.45rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <?= e($camp['slug']) ?>
              </code>
            </td>

            <td>
              <div style="font-size: var(--font-size-xs); font-weight: var(--font-weight-medium);">
                <?= e(date('M d', strtotime((string) $camp['start_date']))) ?> &ndash; <?= e(date('M d, Y', strtotime((string) $camp['end_date']))) ?>
              </div>
              <div class="text-caption text-muted">
                <?php
                  $days = (int) round((strtotime($camp['end_date']) - strtotime($camp['start_date'])) / 86400);
                  echo e($days >= 0 ? "Duration: {$days} days" : '');
                ?>
              </div>
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
                <a href="<?= e(url('/admin/campaigns/' . $camp['id'])) ?>" class="btn-icon" title="View Campaign" aria-label="View campaign <?= e($camp['title']) ?>">
                  <?= icon('eye') ?>
                </a>

                <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
                  <a href="<?= e(url('/admin/campaigns/' . $camp['id'] . '/edit')) ?>" class="btn-icon btn-icon-primary" title="Edit Campaign" aria-label="Edit campaign <?= e($camp['title']) ?>">
                    <?= icon('pencil') ?>
                  </a>
                <?php endif; ?>

                <?php if (!$isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/campaigns/' . $camp['id'] . '/delete')) ?>" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to delete campaign &quot;<?= e(addslashes($camp['title'])) ?>&quot;?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-icon btn-icon-danger" title="Delete Campaign" aria-label="Delete campaign <?= e($camp['title']) ?>">
                      <?= icon('trash') ?>
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/campaigns/' . $camp['id'] . '/restore')) ?>" method="POST" style="margin: 0; display: inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-icon btn-icon-primary" title="Restore Campaign" aria-label="Restore campaign <?= e($camp['title']) ?>">
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
