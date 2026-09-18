<?php

declare(strict_types=1);

/**
 * Edit Certificate Template Metadata View
 */
$selectedVars = $template['required_variables'] ?? [];
if (is_string($selectedVars)) {
    $selectedVars = json_decode($selectedVars, true) ?: [];
}
?>

<div class="page-header mb-6">
  <div>
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
      <a href="<?= e(url('/admin/certificate-templates')) ?>" class="text-secondary" style="font-size: var(--font-size-xs); text-decoration: none;">&larr; Back to Templates</a>
    </div>
    <h1 class="page-title">Edit Template: <?= e($template['name']) ?></h1>
    <p class="text-secondary">Update template configuration and required dynamic variables.</p>
  </div>
</div>

<div class="card" style="max-width: 800px;">
  <form action="<?= e(url('/admin/certificate-templates/' . $template['id'])) ?>" method="POST">
    <?= csrf_field() ?>

    <div class="card-body">
      <div class="form-group mb-4">
        <label for="name" class="form-label">Template Name <span style="color: var(--color-danger);">*</span></label>
        <input type="text" id="name" name="name" class="form-control" value="<?= e($template['name']) ?>" required>
      </div>

      <div class="form-group mb-4">
        <label for="description" class="form-label">Description / Internal Notes</label>
        <textarea id="description" name="description" class="form-control" rows="2"><?= e($template['description'] ?? '') ?></textarea>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;" class="mb-4">
        <div class="form-group">
          <label for="certificate_type" class="form-label">Certificate Type</label>
          <select id="certificate_type" name="certificate_type" class="form-control">
            <?php foreach (['participation', 'appreciation', 'volunteer', 'speaker', 'merit'] as $t): ?>
              <option value="<?= e($t) ?>" <?= ($template['certificate_type'] === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="status" class="form-label">Status</label>
          <select id="status" name="status" class="form-control">
            <option value="active" <?= ($template['status'] === 'active') ? 'selected' : '' ?>>Active (Available for CSV Generation)</option>
            <option value="inactive" <?= ($template['status'] === 'inactive') ? 'selected' : '' ?>>Inactive (Draft)</option>
          </select>
        </div>
      </div>

      <div class="form-group mb-4">
        <label class="form-label mb-2">Required Template Variables</label>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem;">
          <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-surface-subtle); padding: 0.5rem 0.75rem; border-radius: var(--radius-sm);">
            <input type="checkbox" name="required_variables[]" value="name" checked disabled>
            <input type="hidden" name="required_variables[]" value="name">
            <span><strong>{{name}}</strong> <span class="badge badge-danger" style="font-size: 10px; margin-left: 4px;">REQUIRED</span></span>
          </label>

          <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-surface-subtle); padding: 0.5rem 0.75rem; border-radius: var(--radius-sm);">
            <input type="checkbox" name="required_variables[]" value="phone" checked disabled>
            <input type="hidden" name="required_variables[]" value="phone">
            <span><strong>{{phone}}</strong> <span class="badge badge-danger" style="font-size: 10px; margin-left: 4px;">REQUIRED</span></span>
          </label>

          <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-surface-subtle); padding: 0.5rem 0.75rem; border-radius: var(--radius-sm);">
            <input type="checkbox" name="required_variables[]" value="certificate_number" checked disabled>
            <input type="hidden" name="required_variables[]" value="certificate_number">
            <span><strong>{{certificate_number}}</strong></span>
          </label>

          <?php foreach ($allVars as $k => $v): ?>
            <?php if (!in_array($k, ['name', 'phone', 'certificate_number'], true)): ?>
              <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem;">
                <input type="checkbox" name="required_variables[]" value="<?= e($k) ?>" <?= in_array($k, $selectedVars, true) ? 'checked' : '' ?>>
                <span><code><?= e($v['placeholder']) ?></code> &mdash; <?= e($v['label']) ?></span>
              </label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
      <a href="<?= e(url('/admin/certificate-templates/' . $template['id'] . '/designer')) ?>" class="btn btn-secondary">
        <?= icon('layout') ?>
        <span>Open Visual Designer</span>
      </a>
      <div style="display: flex; gap: 0.75rem;">
        <a href="<?= e(url('/admin/certificate-templates')) ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <?= icon('check') ?>
          <span>Save Changes</span>
        </button>
      </div>
    </div>
  </form>
</div>
