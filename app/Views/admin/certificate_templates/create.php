<?php

declare(strict_types=1);

/**
 * Create Certificate Template View
 */
?>

<div class="page-header mb-6">
  <div>
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
      <a href="<?= e(url('/admin/certificate-templates')) ?>" class="text-secondary" style="font-size: var(--font-size-xs); text-decoration: none;">&larr; Back to Templates</a>
    </div>
    <h1 class="page-title">Create Certificate Template</h1>
    <p class="text-secondary">Define initial template attributes and select required dynamic variables.</p>
  </div>
</div>

<div class="card" style="max-width: 800px;">
  <form action="<?= e(url('/admin/certificate-templates')) ?>" method="POST">
    <?= csrf_field() ?>

    <div class="card-body">
      <div class="form-group mb-4">
        <label for="name" class="form-label">Template Name <span style="color: var(--color-danger);">*</span></label>
        <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Standard Workshop Participation Certificate">
        <span class="text-muted" style="font-size: var(--font-size-xs);">Human-readable template title for administrative selection.</span>
      </div>

      <div class="form-group mb-4">
        <label for="description" class="form-label">Description / Internal Notes</label>
        <textarea id="description" name="description" class="form-control" rows="2" placeholder="Brief description of when this template should be used..."></textarea>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;" class="mb-4">
        <div class="form-group">
          <label for="certificate_type" class="form-label">Certificate Type</label>
          <select id="certificate_type" name="certificate_type" class="form-control">
            <option value="participation">Participation</option>
            <option value="appreciation">Appreciation</option>
            <option value="volunteer">Volunteer Service</option>
            <option value="speaker">Speaker / Facilitator</option>
            <option value="merit">Merit &amp; Achievement</option>
          </select>
        </div>

        <div class="form-group">
          <label for="status" class="form-label">Status</label>
          <select id="status" name="status" class="form-control">
            <option value="active">Active (Available for CSV Generation)</option>
            <option value="inactive">Inactive (Draft)</option>
          </select>
        </div>
      </div>

      <div class="form-group mb-4">
        <label class="form-label mb-2">Required Template Variables</label>
        <p class="text-secondary mb-3" style="font-size: var(--font-size-xs);">
          Every template strictly requires <strong>{{name}}</strong> and <strong>{{phone}}</strong>. Select any additional variables that your CSV uploads will include.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem;">
          <!-- Mandatory Variables -->
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

          <!-- Optional Registry Variables -->
          <?php foreach ($allVars as $k => $v): ?>
            <?php if (!in_array($k, ['name', 'phone', 'certificate_number'], true)): ?>
              <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem;">
                <input type="checkbox" name="required_variables[]" value="<?= e($k) ?>" <?= in_array($k, ['event_title', 'date', 'place'], true) ? 'checked' : '' ?>>
                <span><code><?= e($v['placeholder']) ?></code> &mdash; <?= e($v['label']) ?></span>
              </label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.75rem;">
      <a href="<?= e(url('/admin/certificate-templates')) ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <?= icon('check') ?>
        <span>Create &amp; Open Visual Designer</span>
      </button>
    </div>
  </form>
</div>
