<?php
  /** @var array $form */
  /** @var array $fields */
  /** @var int $regCount */
  /** @var string $regUrl */
  /** @var string $regQrSvg */
  /** @var string $checkInUrl */
  /** @var string $checkInQrSvg */

  $isLocked = $regCount > 0;
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= e(url('/admin/forms')) ?>" class="text-white-50 text-decoration-none small">&larr; Back to Forms</a>
        <span class="text-white-50">&bull;</span>
        <span class="badge bg-success">1 EVENT = 1 FORM</span>
        <span class="text-white-50">&bull;</span>
        <span class="badge bg-primary rounded-pill"><?= $regCount ?> Registrations</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white"><?= e($form['form_title']) ?></h2>
      <p class="text-white-50 mb-0">Event: <strong><?= e($form['event_title'] ?? '') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= e($regUrl) ?>" target="_blank" class="btn btn-light btn-sm fw-semibold">
        <i class="bi bi-box-arrow-up-right me-1"></i> View Live Form
      </a>
      <a href="<?= e($checkInUrl) ?>" target="_blank" class="btn btn-outline-light btn-sm">
        <i class="bi bi-qr-code-scan me-1"></i> Check-in Portal
      </a>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Left Column: Form Settings & Assets -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom">
        <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
          <i class="bi bi-sliders text-primary"></i> Form Configuration & Identity
        </h5>
      </div>
      <div class="card-body p-4">
        <form action="<?= e(url('/admin/forms/' . $form['id'])) ?>" method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Public Form Title <span class="text-danger">*</span></label>
            <input type="text" name="form_title" class="form-control" required value="<?= e($form['form_title']) ?>">
          </div>

          <!-- Public URL & Slug (With Immutability Rule) -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">
              Unique Public Registration Slug 
              <?php if ($isLocked): ?>
                <span class="badge bg-secondary ms-1"><i class="bi bi-lock-fill"></i> Locked</span>
              <?php else: ?>
                <span class="badge bg-success ms-1"><i class="bi bi-unlock"></i> Editable</span>
              <?php endif; ?>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light font-monospace small">/register/</span>
              <input type="text" 
                     name="slug" 
                     class="form-control font-monospace <?= $isLocked ? 'bg-light' : '' ?>" 
                     value="<?= e($form['slug']) ?>" 
                     required 
                     pattern="[a-z0-9\-]+"
                     <?= $isLocked ? 'readonly' : '' ?>>
              <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= e($regUrl) ?>'); alert('Copied URL to clipboard!');">
                <i class="bi bi-clipboard"></i> Copy
              </button>
            </div>
            <?php if ($isLocked): ?>
              <div class="form-text text-muted small mt-1">
                <i class="bi bi-info-circle text-primary"></i> Slug is locked permanently to protect existing registration links and printed QR codes because <strong><?= $regCount ?></strong> registration(s) have been submitted.
              </div>
            <?php else: ?>
              <div class="form-text small mt-1">
                Lowercase letters, numbers, and hyphens only. Locked once registrations begin.
              </div>
            <?php endif; ?>
          </div>

          <!-- Banner Upload -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Form Header Banner (Optional)</label>
            <?php if (!empty($form['banner_path'])): ?>
              <div class="mb-2 p-2 border rounded bg-light text-center">
                <img src="<?= e(url('/storage/' . $form['banner_path'])) ?>" alt="Banner" class="img-fluid rounded" style="max-height: 120px;">
              </div>
            <?php endif; ?>
            <input type="file" name="banner" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
            <div class="form-text small">Recommended: 1200 &times; 400 px. Max 5 MB.</div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <div class="form-check form-switch pt-2">
                <input class="form-check-input" type="checkbox" role="switch" name="photo_upload_enabled" value="1" id="photoUploadSwitch" <?= !empty($form['photo_upload_enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold small" for="photoUploadSwitch">Participant Photo Upload</label>
                <div class="form-text" style="font-size: 0.75rem;">Allows optional attendee badge photo.</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="form-check form-switch pt-2">
                <input class="form-check-input" type="checkbox" role="switch" name="location_access_required" value="1" id="locationSwitch" <?= !empty($form['location_access_required']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold small" for="locationSwitch">Location Access Required</label>
                <div class="form-text" style="font-size: 0.75rem;">Prompts for browser coordinates on signup.</div>
              </div>
            </div>
          </div>

          <hr class="my-4 text-muted opacity-25">

          <!-- WhatsApp Community Link -->
          <h6 class="fw-bold text-dark mb-3"><i class="bi bi-whatsapp text-success me-1"></i> WhatsApp Group Onboarding</h6>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Event WhatsApp Group URL</label>
            <input type="url" name="whatsapp_group_url" class="form-control" value="<?= e($form['whatsapp_group_url'] ?? '') ?>" placeholder="https://chat.whatsapp.com/...">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <div class="form-check form-switch pt-2">
                <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_auto_redirect" value="1" id="waRedirectSwitch" <?= !empty($form['whatsapp_auto_redirect']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold small" for="waRedirectSwitch">Auto-Redirect Timer</label>
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Countdown (Seconds)</label>
              <input type="number" name="whatsapp_countdown_seconds" class="form-control form-control-sm" min="1" max="30" value="<?= e($form['whatsapp_countdown_seconds'] ?? 5) ?>">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Custom Success Message</label>
            <textarea name="custom_success_message" class="form-control" rows="2" placeholder="Leave blank to use global default"><?= e($form['custom_success_message'] ?? '') ?></textarea>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold small">Form Publication Status</label>
            <select name="status" class="form-select">
              <option value="published" <?= ($form['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published (Accepting Registrations)</option>
              <option value="draft" <?= ($form['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft (Internal Testing Only)</option>
              <option value="closed" <?= ($form['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed (Registration Ended)</option>
            </select>
          </div>

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm">Save Form Configuration</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Column: Fields Manager & QR Codes -->
  <div class="col-lg-5">
    <!-- QR Code & Public Links Card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom">
        <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
          <i class="bi bi-qr-code text-primary"></i> Public Registration QR Code
        </h5>
      </div>
      <div class="card-body p-4 text-center">
        <div class="mb-3 d-inline-block p-3 bg-light border rounded">
          <?= $regQrSvg ?>
        </div>
        <div class="small text-muted font-monospace mb-2"><?= e($regUrl) ?></div>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText('<?= e($regUrl) ?>'); alert('Copied registration URL!');">
            <i class="bi bi-clipboard me-1"></i> Copy URL
          </button>
          <a href="<?= e($regUrl) ?>" target="_blank" class="btn btn-sm btn-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i> Open Form
          </a>
        </div>
      </div>
    </div>

    <!-- Fields Manager Card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-bold mb-0 text-dark">Form Fields Manager</h5>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addFieldCollapse">
          <i class="bi bi-plus-lg me-1"></i> Add Custom Field
        </button>
      </div>
      <div class="card-body p-4">
        <!-- Collapse Form for Adding Field -->
        <div class="collapse mb-4 p-3 border rounded bg-light" id="addFieldCollapse">
          <h6 class="fw-bold text-dark mb-3">Add Custom Field</h6>
          <form action="<?= e(url('/admin/forms/' . $form['id'] . '/fields')) ?>" method="POST">
            <?= csrf_field() ?>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Field Label <span class="text-danger">*</span></label>
              <input type="text" name="field_label" class="form-control form-control-sm" required placeholder="e.g. College / Organization">
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label small fw-semibold">Field Type</label>
                <select name="field_type" class="form-select form-select-sm">
                  <option value="text">Single-line Text</option>
                  <option value="long_text">Long Text / Textarea</option>
                  <option value="number">Number</option>
                  <option value="email">Email Address</option>
                  <option value="dropdown">Dropdown Select</option>
                  <option value="radio">Radio Options</option>
                  <option value="checkbox">Checkbox (Multiple)</option>
                  <option value="date">Date</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Requirement</label>
                <select name="is_required" class="form-select form-select-sm">
                  <option value="0">Optional</option>
                  <option value="1">Mandatory (Required)</option>
                </select>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Placeholder</label>
              <input type="text" name="placeholder" class="form-control form-control-sm" placeholder="Input placeholder...">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Options (For Dropdown/Radio/Checkbox — 1 per line)</label>
              <textarea name="options_json" class="form-control form-control-sm" rows="3" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="collapse" data-bs-target="#addFieldCollapse">Cancel</button>
              <button type="submit" class="btn btn-sm btn-primary">Save Field</button>
            </div>
          </form>
        </div>

        <!-- Field List -->
        <div class="d-flex flex-column gap-2">
          <?php foreach ($fields as $field): 
            $isFieldLocked = !empty($field['is_locked']);
          ?>
            <div class="p-3 border rounded bg-white d-flex justify-content-between align-items-center">
              <div>
                <div class="d-flex align-items-center gap-2">
                  <span class="fw-semibold text-dark small"><?= e($field['field_label']) ?></span>
                  <?php if ($isFieldLocked): ?>
                    <span class="badge bg-light text-secondary border small"><i class="bi bi-lock-fill"></i> Locked Core</span>
                  <?php else: ?>
                    <span class="badge bg-light text-primary border small"><?= e(ucfirst($field['field_type'])) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($field['is_required'])): ?>
                    <span class="badge bg-danger-subtle text-danger small">Required</span>
                  <?php endif; ?>
                </div>
                <div class="text-muted font-monospace" style="font-size: 0.75rem;">key: <?= e($field['field_key']) ?></div>
              </div>

              <?php if (!$isFieldLocked): ?>
                <form action="<?= e(url('/admin/forms/' . $form['id'] . '/fields/' . $field['id'] . '/delete')) ?>" method="POST" onsubmit="return confirm('Remove this custom field?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Remove field">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
