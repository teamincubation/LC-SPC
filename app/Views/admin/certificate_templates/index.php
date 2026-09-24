<?php

declare(strict_types=1);

/**
 * Certificate Templates Directory View
 */
?>

<div class="page-header mb-6" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h1 class="page-title">Certificate Templates</h1>
    <p class="text-secondary">Create and manage reusable certificate designs with dynamic variable layouts.</p>
  </div>
  <a href="<?= e(url('/admin/certificate-templates/create')) ?>" class="btn btn-primary">
    <?= icon('plus-circle') ?>
    <span>New Template</span>
  </a>
</div>

<div class="card">
  <div class="card-body" style="padding: 0;">
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Template Name</th>
            <th>Type</th>
            <th>Variables</th>
            <th>Status</th>
            <th>Created</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($templates)): ?>
            <tr>
              <td colspan="6" class="text-muted" style="text-align: center; padding: 3rem 1rem;">
                <div style="max-width: 360px; margin: 0 auto;">
                  <div style="font-size: 2rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?= icon('file-text', ['size' => 'lg']) ?></div>
                  <h3 style="font-size: var(--font-size-md); margin-bottom: 0.25rem;">No Certificate Templates Found</h3>
                  <p class="text-secondary mb-4" style="font-size: var(--font-size-xs);">Create your first template to begin generating automated certificates from CSV data.</p>
                  <a href="<?= e(url('/admin/certificate-templates/create')) ?>" class="btn btn-primary btn-sm">
                    <?= icon('plus-circle') ?>
                    <span>Create Template</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($templates as $t): ?>
              <tr>
                <td>
                  <strong><?= e($t['name']) ?></strong>
                  <?php if (!empty($t['description'])): ?>
                    <div class="text-muted" style="font-size: var(--font-size-xs);"><?= e($t['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge badge-neutral"><?= e(ucfirst($t['certificate_type'])) ?></span>
                </td>
                <td>
                  <?php
                  $vList = $t['required_variables'] ?? [];
                  if (is_string($vList)) {
                      $vList = json_decode($vList, true) ?: [];
                  }
                  ?>
                  <span style="font-size: var(--font-size-xs); font-family: var(--font-mono); color: var(--text-secondary);">
                    <?= e(count($vList)) ?> variables (<?= e(implode(', ', array_slice(array_map(fn($v) => '{{' . (is_array($v) ? ($v['key'] ?? '') : $v) . '}}', $vList), 0, 2))) ?>...)
                  </span>
                </td>
                <td>
                  <?php if ($t['status'] === 'active'): ?>
                    <span class="badge badge-success">Active</span>
                  <?php else: ?>
                    <span class="badge badge-neutral">Inactive</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="font-size: var(--font-size-xs); color: var(--text-muted);"><?= date('d M Y', strtotime((string)$t['created_at'])) ?></span>
                </td>
                <td style="text-align: right;">
                  <div style="display: flex; gap: 0.35rem; justify-content: flex-end; flex-wrap: wrap;">
                    <a href="<?= e(url('/admin/certificate-templates/' . $t['id'] . '/designer')) ?>" class="btn btn-primary btn-sm" title="Visual Designer">
                      <?= icon('layout') ?>
                      <span>Designer</span>
                    </a>
                    <a href="<?= e(url('/admin/certificate-templates/' . $t['id'] . '/preview')) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Live Preview">
                      <?= icon('eye') ?>
                      <span>Preview</span>
                    </a>
                    <a href="<?= e(url('/admin/certificate-templates/' . $t['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm" title="Edit Metadata">
                      <?= icon('edit') ?>
                    </a>
                    <form action="<?= e(url('/admin/certificate-templates/' . $t['id'] . '/duplicate')) ?>" method="POST" style="display: inline;">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-secondary btn-sm" title="Duplicate Template">
                        <?= icon('copy') ?>
                      </button>
                    </form>
                    <form action="<?= e(url('/admin/certificate-templates/' . $t['id'] . '/delete')) ?>" method="POST" onsubmit="return confirm('Delete template <?= e(addslashes($t['name'])) ?>?');" style="display: inline;">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger);" title="Delete Template">
                        <?= icon('trash-2') ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
