<?php
  /** @var array $forms */
  /** @var \App\Services\EventFormService $formService */
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge bg-success">1 EVENT = 1 FORM ARCHITECTURE</span>
        <span class="text-white-50">&bull;</span>
        <span class="text-white-50 small">Automated Lifecycle</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Event Registration Forms</h2>
      <p class="text-white-50 mb-0">Every event possesses exactly one dedicated public registration form with a unique, permanent URL and QR code.</p>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
    <h5 class="card-title fw-bold mb-0 text-dark">Active Registration Forms</h5>
    <span class="badge bg-light text-dark border"><?= count($forms) ?> Total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th>Event & Form</th>
          <th>Public Registration URL</th>
          <th>Slug Lock Status</th>
          <th>Registrations</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody class="small">
        <?php if (empty($forms)): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-muted">No event forms available. Create an event to automatically generate its form.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($forms as $form): 
            $regUrl = $formService->getPublicRegistrationUrl($form['slug']);
            $isLocked = ((int) ($form['registrations_count'] ?? 0)) > 0;
          ?>
            <tr>
              <td>
                <div class="fw-bold text-dark"><?= e($form['event_title'] ?? 'Event') ?></div>
                <div class="text-muted small"><?= e($form['form_title']) ?></div>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <a href="<?= e($regUrl) ?>" target="_blank" class="font-monospace text-decoration-none small text-primary text-truncate" style="max-width: 280px;">
                    <?= e($regUrl) ?>
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-secondary p-1" title="Copy Registration URL" onclick="navigator.clipboard.writeText('<?= e($regUrl) ?>'); alert('Copied registration URL!');">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </div>
              </td>
              <td>
                <?php if ($isLocked): ?>
                  <span class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1">
                    <i class="bi bi-lock-fill text-muted"></i> Locked (Registrations Active)
                  </span>
                <?php else: ?>
                  <span class="badge bg-light text-success border d-inline-flex align-items-center gap-1">
                    <i class="bi bi-unlock text-success"></i> Editable Slug
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-primary rounded-pill"><?= (int) ($form['registrations_count'] ?? 0) ?></span>
              </td>
              <td>
                <?php 
                  $statusBadge = match ($form['status'] ?? 'published') {
                    'published' => 'bg-success',
                    'draft'     => 'bg-warning text-dark',
                    'closed'    => 'bg-danger',
                    default     => 'bg-secondary',
                  };
                ?>
                <span class="badge <?= $statusBadge ?>"><?= e(strtoupper($form['status'] ?? 'published')) ?></span>
              </td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <a href="<?= e(url('/admin/forms/' . $form['id'])) ?>" class="btn btn-sm btn-primary fw-semibold">
                    <i class="bi bi-sliders me-1"></i> Builder
                  </a>
                  <a href="<?= e($regUrl) ?>" target="_blank" class="btn btn-sm btn-light border" title="View Public Form">
                    <i class="bi bi-box-arrow-up-right"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
