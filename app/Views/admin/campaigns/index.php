<!-- Campaign Management Roster Header -->
<div class="card mb-6">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.25rem;">
        Campaign Management
      </h1>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Multi-year initiatives, themes, timelines, and program governance.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <?php if (!empty($canCreate)): ?>
        <a href="<?= e(url('/admin/campaigns/create')) ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.35rem;">
          <span aria-hidden="true">&#43;</span>
          <span>New Campaign</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- KPI Status Metrics -->
<section class="grid grid-cols-4 mb-6" aria-label="Campaign Overview Metrics">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      &#10003;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['active'] ?? 0)) ?></div>
    <div class="card-metric-label">Active Campaigns</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-warning); color: var(--text-warning);">
      &#9998;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['draft'] ?? 0)) ?></div>
    <div class="card-metric-label">Draft Campaigns</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-info); color: var(--text-info);">
      &#9733;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['completed'] ?? 0)) ?></div>
    <div class="card-metric-label">Completed</div>
  </div>

  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      &#128193;
    </div>
    <div class="card-metric-value"><?= e((string) ($counts['total'] ?? 0)) ?></div>
    <div class="card-metric-label">Total Campaigns</div>
  </div>
</section>

<!-- Filter & Search Toolbar -->
<div class="card mb-6" style="padding: 1rem 1.25rem;">
  <form action="<?= e(url('/admin/campaigns')) ?>" method="GET" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
    <!-- Status Filter Pills -->
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/campaigns' . ($search ? '?search=' . urlencode($search) : ''))) ?>" 
         class="badge <?= empty($currentStatus) && empty($isTrash) ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: var(--font-size-xs);">
        All (<?= e((string) ($counts['total'] ?? 0)) ?>)
      </a>

      <a href="<?= e(url('/admin/campaigns?status=active' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="badge <?= ($currentStatus ?? '') === 'active' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: var(--font-size-xs);">
        Active (<?= e((string) ($counts['active'] ?? 0)) ?>)
      </a>

      <a href="<?= e(url('/admin/campaigns?status=draft' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="badge <?= ($currentStatus ?? '') === 'draft' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: var(--font-size-xs);">
        Drafts (<?= e((string) ($counts['draft'] ?? 0)) ?>)
      </a>

      <a href="<?= e(url('/admin/campaigns?status=completed' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="badge <?= ($currentStatus ?? '') === 'completed' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: var(--font-size-xs);">
        Completed (<?= e((string) ($counts['completed'] ?? 0)) ?>)
      </a>

      <a href="<?= e(url('/admin/campaigns?status=archived' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
         class="badge <?= ($currentStatus ?? '') === 'archived' ? 'badge-primary' : 'badge-neutral' ?>" 
         style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: var(--font-size-xs);">
        Archived (<?= e((string) ($counts['archived'] ?? 0)) ?>)
      </a>

      <?php if (!empty($canDelete)): ?>
        <a href="<?= e(url('/admin/campaigns?trash=1' . ($search ? '&search=' . urlencode($search) : ''))) ?>" 
           class="badge <?= !empty($isTrash) ? 'badge-danger' : 'badge-neutral' ?>" 
           style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: var(--font-size-xs);">
          Trash &bull; Soft-Deleted
        </a>
      <?php endif; ?>
    </div>

    <!-- Keyword Search Form -->
    <div style="display: flex; align-items: center; gap: 0.5rem;">
      <?php if (!empty($currentStatus)): ?>
        <input type="hidden" name="status" value="<?= e($currentStatus) ?>">
      <?php endif; ?>
      <?php if (!empty($isTrash)): ?>
        <input type="hidden" name="trash" value="1">
      <?php endif; ?>

      <input 
        type="search" 
        name="search" 
        value="<?= e($search ?? '') ?>" 
        placeholder="Search title, theme, slug..." 
        class="form-control" 
        style="min-width: 240px; padding: 0.4rem 0.75rem; font-size: var(--font-size-sm);"
        aria-label="Search campaigns"
      >
      <button type="submit" class="btn btn-outline btn-sm">Search</button>
      <?php if (!empty($search) || !empty($currentStatus) || !empty($isTrash)): ?>
        <a href="<?= e(url('/admin/campaigns')) ?>" class="btn btn-outline btn-sm" title="Clear all filters">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Campaign Roster Table -->
<div class="table-responsive">
  <table class="table table-hover" aria-label="Campaigns list">
    <thead>
      <tr>
        <th style="width: 28%;">Campaign Title &amp; Theme</th>
        <th style="width: 18%;">Slug Identifier</th>
        <th style="width: 18%;">Schedule Timeline</th>
        <th style="width: 12%;">Status</th>
        <th style="width: 12%;">Attribution</th>
        <th style="width: 12%; text-align: right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($campaigns)): ?>
        <tr>
          <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;" aria-hidden="true">&#128194;</div>
            <p style="font-weight: var(--font-weight-medium); margin-bottom: 0.25rem;">No campaigns found.</p>
            <span class="text-caption text-muted">
              <?= !empty($search) ? 'Try refining your search keyword or clearing filters.' : 'Click "New Campaign" to create the first campaign initiative.' ?>
            </span>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($campaigns as $camp): ?>
          <?php
            $st = $camp['status'] ?? 'draft';
            $badgeClass = match ($st) {
                'active'    => 'badge-success',
                'completed' => 'badge-info',
                'archived'  => 'badge-neutral',
                default     => 'badge-warning',
            };
            $isSoftDeleted = !empty($camp['deleted_at']);
          ?>
          <tr style="<?= $isSoftDeleted ? 'opacity: 0.7; background-color: var(--bg-surface-subtle);' : '' ?>">
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
              <code style="font-size: var(--font-size-xs); background: var(--bg-surface-subtle); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <?= e($camp['slug']) ?>
              </code>
            </td>

            <td>
              <div style="font-size: var(--font-size-xs);">
                <strong><?= e($camp['start_date']) ?></strong> &rarr; <strong><?= e($camp['end_date']) ?></strong>
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
                <span class="badge badge-danger">
                  <span class="badge-dot" aria-hidden="true"></span>
                  Deleted
                </span>
              <?php else: ?>
                <span class="badge <?= e($badgeClass) ?>">
                  <span class="badge-dot" aria-hidden="true"></span>
                  <?= e(ucfirst($st)) ?>
                </span>
              <?php endif; ?>
            </td>

            <td>
              <span class="text-caption text-secondary" title="<?= e($camp['creator_email'] ?? 'System') ?>">
                <?= e($camp['creator_name'] ?? ($camp['creator_email'] ?? 'System')) ?>
              </span>
            </td>

            <td style="text-align: right; white-space: nowrap;">
              <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <a href="<?= e(url('/admin/campaigns/' . $camp['id'])) ?>" class="btn btn-outline btn-sm" title="View Campaign Details">
                  View
                </a>

                <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
                  <a href="<?= e(url('/admin/campaigns/' . $camp['id'] . '/edit')) ?>" class="btn btn-outline btn-sm" title="Edit Campaign">
                    Edit
                  </a>
                <?php endif; ?>

                <?php if (!$isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/campaigns/' . $camp['id'] . '/delete')) ?>" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to soft-delete campaign &quot;<?= e(addslashes($camp['title'])) ?>&quot;?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-danger); border-color: var(--border-danger);" title="Soft-delete Campaign">
                      Delete
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($isSoftDeleted && !empty($canDelete)): ?>
                  <form action="<?= e(url('/admin/campaigns/' . $camp['id'] . '/restore')) ?>" method="POST" style="margin: 0; display: inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm" title="Restore soft-deleted campaign">
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
