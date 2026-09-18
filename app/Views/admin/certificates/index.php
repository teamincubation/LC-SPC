<?php

declare(strict_types=1);

/**
 * Certificate Platform V3 - Certificate Repository View
 */
?>

<div class="page-header mb-6" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h1 class="page-title">Certificate Repository</h1>
    <p class="text-secondary">Search, inspect, download, and manage issued V3 digital credentials.</p>
  </div>
  <div style="display: flex; gap: 0.75rem;">
    <a href="<?= e(url('/admin/certificates/generate')) ?>" class="btn btn-primary">
      <?= icon('plus-circle') ?>
      <span>Generate Certificates</span>
    </a>
  </div>
</div>

<!-- Filters Bar -->
<div class="card mb-6">
  <div class="card-body" style="padding: 1.25rem 1.5rem;">
    <form method="GET" action="<?= e(url('/admin/certificates')) ?>" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
      <!-- Search -->
      <div style="flex: 2; min-width: 220px;">
        <label for="search" class="form-label" style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);">Search</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Certificate ID, Name, Phone, Event..." value="<?= e($filters['search'] ?? '') ?>">
      </div>

      <!-- Status Filter -->
      <div style="flex: 1; min-width: 140px;">
        <label for="status" class="form-label" style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);">Status</label>
        <select id="status" name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="invalid" <?= ($filters['status'] ?? '') === 'invalid' ? 'selected' : '' ?>>Invalid / Revoked</option>
        </select>
      </div>

      <!-- Template Filter -->
      <div style="flex: 1; min-width: 180px;">
        <label for="template_id" class="form-label" style="font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);">Template</label>
        <select id="template_id" name="template_id" class="form-select">
          <option value="">All Templates</option>
          <?php foreach ($templates as $tmpl): ?>
            <option value="<?= e($tmpl['id']) ?>" <?= ((string)($filters['template_id'] ?? '')) === ((string)$tmpl['id']) ? 'selected' : '' ?>>
              <?= e($tmpl['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Actions -->
      <div style="display: flex; gap: 0.5rem;">
        <button type="submit" class="btn btn-secondary">
          <?= icon('filter') ?>
          <span>Filter</span>
        </button>
        <?php if (!empty(array_filter($filters))): ?>
          <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline">
            <span>Reset</span>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Certificates Table -->
<div class="card">
  <div class="card-body" style="padding: 0;">
    <div class="table-container" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
      <table class="data-table" style="min-width: 820px; width: 100%;">
        <thead>
          <tr>
            <th>Certificate ID</th>
            <th>Recipient Name</th>
            <th>Phone</th>
            <th>Template / Event</th>
            <th>Issue Date</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($certificates)): ?>
            <tr>
              <td colspan="7" class="text-muted" style="text-align: center; padding: 3rem 1rem;">
                <div style="max-width: 360px; margin: 0 auto;">
                  <div style="font-size: 2rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?= icon('award') ?></div>
                  <h3 style="font-size: var(--font-size-md); margin-bottom: 0.25rem;">No Certificates Found</h3>
                  <p class="text-secondary mb-4" style="font-size: var(--font-size-xs);">
                    <?= !empty(array_filter($filters)) ? 'No certificates match the current search filters.' : 'Upload a CSV to generate your first batch of certificates.' ?>
                  </p>
                  <a href="<?= e(url('/admin/certificates/generate')) ?>" class="btn btn-primary btn-sm">
                    <?= icon('plus-circle') ?>
                    <span>Generate Certificates</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
              <tr>
                <td>
                  <strong style="font-family: var(--font-mono); font-size: 0.85rem;"><?= e($cert['certificate_id']) ?></strong>
                </td>
                <td>
                  <strong><?= e($cert['name']) ?></strong>
                </td>
                <td>
                  <span style="font-family: var(--font-mono); font-size: 0.82rem; color: var(--text-secondary);">
                    <?= e($cert['phone']) ?>
                  </span>
                </td>
                <td>
                  <div><?= e($cert['template_name'] ?? 'Custom Template') ?></div>
                  <?php if (!empty($cert['event_title'])): ?>
                    <div class="text-muted" style="font-size: var(--font-size-xs);"><?= e($cert['event_title']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="font-size: 0.82rem; color: var(--text-secondary);">
                    <?= e($cert['date'] ?? date('Y-m-d', strtotime($cert['created_at']))) ?>
                  </span>
                </td>
                <td>
                  <?php if ($cert['status'] === 'active'): ?>
                    <span class="badge badge-success">Active</span>
                  <?php else: ?>
                    <span class="badge badge-danger">Invalid / Revoked</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                    <a href="<?= e(url('/admin/certificates/' . $cert['id'])) ?>" class="btn btn-outline btn-sm" title="View Details">
                      <?= icon('eye') ?>
                    </a>
                    <a href="<?= e(url('/admin/certificates/' . $cert['id'] . '/pdf')) ?>" target="_blank" class="btn btn-outline btn-sm" title="Download PDF">
                      <?= icon('file-text') ?>
                    </a>
                    <a href="<?= e(url('/admin/certificates/' . $cert['id'] . '/image')) ?>" target="_blank" class="btn btn-outline btn-sm" title="Download Image">
                      <?= icon('image') ?>
                    </a>
                    <a href="<?= e(url('/certificates/verify/' . $cert['verification_token'])) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Public Verification Portal">
                      <?= icon('external-link') ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.75rem;">
        <span class="text-secondary" style="font-size: var(--font-size-xs);">
          Showing <?= e((string) count($certificates)) ?> of <?= e((string) $pagination['total_count']) ?> records
        </span>
        <div style="display: flex; gap: 0.25rem;">
          <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
            <?php
              $query = array_merge($filters, ['page' => $p]);
              $pageUrl = url('/admin/certificates?' . http_build_query($query));
            ?>
            <a href="<?= e($pageUrl) ?>" class="btn btn-sm <?= $p === $pagination['current_page'] ? 'btn-primary' : 'btn-outline' ?>" style="min-width: 32px; padding: 0.25rem 0.5rem; text-align: center;">
              <?= $p ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
