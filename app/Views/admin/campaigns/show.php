<?php

declare(strict_types=1);

/**
 * Modernized Campaign Details View
 */

$st = $campaign['status'] ?? 'draft';
$pillClass = match ($st) {
    'active'    => 'badge-pill-success',
    'completed' => 'badge-pill-info',
    'archived'  => 'badge-pill-neutral',
    default     => 'badge-pill-warning',
};
$isSoftDeleted = !empty($campaign['deleted_at']);
?>

<!-- Campaign Page Header -->
<div class="admin-page-header">
  <div>
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
      <h1 class="admin-page-title" style="margin: 0;">
        <?= e($campaign['title']) ?>
      </h1>
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
    </div>

    <?php if (!empty($campaign['theme'])): ?>
      <p class="admin-page-desc" style="font-style: italic;">
        &ldquo;<?= e($campaign['theme']) ?>&rdquo;
      </p>
    <?php endif; ?>
  </div>

  <div class="admin-page-actions">
    <a href="<?= e(url('/admin/campaigns')) ?>" class="btn btn-outline btn-auto">
      <?= icon('arrow-left', ['class' => 'svg-icon-sm']) ?>
      <span>All Campaigns</span>
    </a>

    <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
      <a href="<?= e(url('/admin/campaigns/' . $campaign['id'] . '/edit')) ?>" class="btn btn-primary btn-auto">
        <?= icon('pencil', ['class' => 'svg-icon-sm']) ?>
        <span>Edit Campaign</span>
      </a>
    <?php endif; ?>

    <?php if (!$isSoftDeleted && !empty($canDelete)): ?>
      <form action="<?= e(url('/admin/campaigns/' . $campaign['id'] . '/delete')) ?>" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to delete this campaign?');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline btn-auto" style="color: var(--color-danger); border-color: var(--border-danger);">
          <?= icon('trash', ['class' => 'svg-icon-sm']) ?>
          <span>Delete</span>
        </button>
      </form>
    <?php endif; ?>

    <?php if ($isSoftDeleted && !empty($canDelete)): ?>
      <form action="<?= e(url('/admin/campaigns/' . $campaign['id'] . '/restore')) ?>" method="POST" style="margin: 0; display: inline;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-auto">
          <?= icon('refresh', ['class' => 'svg-icon-sm']) ?>
          <span>Restore</span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Campaign Details Grid -->
<div class="grid grid-cols-3 gap-6 mb-6">
  <!-- Core Information Column -->
  <div class="card" style="grid-column: span 2;">
    <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
      Campaign Overview &amp; Strategy
    </h2>

    <div class="mb-4">
      <span class="text-caption text-secondary" style="display: block; margin-bottom: 0.25rem;">Description &amp; Objectives</span>
      <?php if (!empty($campaign['description'])): ?>
        <div style="font-size: var(--font-size-sm); line-height: 1.6; white-space: pre-line; background-color: var(--bg-surface-subtle); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <?= e($campaign['description']) ?>
        </div>
      <?php else: ?>
        <p class="text-caption text-muted">No description provided for this campaign.</p>
      <?php endif; ?>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <span class="text-caption text-secondary">Public Slug Identifier</span>
        <div style="margin-top: 0.25rem;">
          <code style="font-size: var(--font-size-sm); background-color: var(--bg-surface-subtle); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <?= e($campaign['slug']) ?>
          </code>
        </div>
      </div>

      <div>
        <span class="text-caption text-secondary">Active Timeline</span>
        <div style="font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); margin-top: 0.25rem;">
          <?= e($campaign['start_date']) ?> &mdash; <?= e($campaign['end_date']) ?>
          <?php
            $days = (int) round((strtotime($campaign['end_date']) - strtotime($campaign['start_date'])) / 86400);
            echo e($days >= 0 ? " ({$days} days)" : '');
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Administrative Metadata Column -->
  <div class="card">
    <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
      Audit &amp; Governance
    </h2>

    <div style="display: flex; flex-direction: column; gap: 1rem; font-size: var(--font-size-sm);">
      <div>
        <span class="text-caption text-secondary">Created By</span>
        <div style="font-weight: var(--font-weight-medium);">
          <?= e($campaign['creator_name'] ?? 'System') ?>
        </div>
        <div class="text-caption text-muted">
          <?= e($campaign['creator_email'] ?? '') ?>
        </div>
      </div>

      <div>
        <span class="text-caption text-secondary">Record Created</span>
        <div style="font-weight: var(--font-weight-medium);">
          <?= e($campaign['created_at']) ?>
        </div>
      </div>

      <div>
        <span class="text-caption text-secondary">Last Modified</span>
        <div style="font-weight: var(--font-weight-medium);">
          <?= e($campaign['updated_at']) ?>
        </div>
      </div>

      <?php if ($isSoftDeleted): ?>
        <div style="background-color: var(--bg-danger); border: 1px solid var(--border-danger); padding: 0.75rem; border-radius: var(--radius-sm);">
          <span class="text-caption text-danger" style="font-weight: var(--font-weight-bold);">Soft-Deleted At</span>
          <div style="color: var(--text-danger); font-size: var(--font-size-xs);">
            <?= e($campaign['deleted_at']) ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
        <!-- Quick Status Transition -->
        <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 0.5rem;">
          <form action="<?= e(url('/admin/campaigns/' . $campaign['id'] . '/status')) ?>" method="POST">
            <?= csrf_field() ?>
            <label for="quick-status" class="form-label" style="font-size: var(--font-size-xs);">Transition Status</label>
            <div style="display: flex; gap: 0.5rem;">
              <select id="quick-status" name="status" class="form-control" style="padding: 0.35rem 0.5rem; font-size: var(--font-size-xs);">
                <option value="draft" <?= $st === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="active" <?= $st === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $st === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="archived" <?= $st === 'archived' ? 'selected' : '' ?>>Archived</option>
              </select>
              <button type="submit" class="btn btn-secondary btn-sm btn-auto">Update</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
