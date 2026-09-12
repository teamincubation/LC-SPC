<?php
  /** @var array $event */
  /** @var array|null $template */
  /** @var array $defaultConfig */

  $isCompleted = ($event['status'] ?? '') === 'completed';
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= e(url('/admin/certificates')) ?>" class="text-white-50 text-decoration-none small">&larr; Back to Certificates</a>
        <span class="text-white-50">&bull;</span>
        <span class="badge bg-<?= $isCompleted ? 'success' : 'warning text-dark' ?>"><?= e(strtoupper($event['status'] ?? 'draft')) ?></span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Certificate Template Designer</h2>
      <p class="text-white-50 mb-0">Customize visual assets, background, organization seal, and dual signatories for <strong><?= e($event['title']) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= e(url('/admin/events/' . $event['id'] . '/certificates')) ?>" class="btn btn-outline-light btn-sm">
        <i class="bi bi-people me-1"></i> Issue Certificates
      </a>
    </div>
  </div>
</div>

<?php if (!$isCompleted): ?>
  <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
    <div>
      <strong>Notice: Event Not Completed</strong><br>
      <span class="small">Certificates cannot be generated or downloaded by attendees until this event's status is changed to <strong>Completed</strong>. You can configure and test the template now in advance.</span>
    </div>
  </div>
<?php endif; ?>

<form action="<?= e(url('/admin/events/' . $event['id'] . '/certificates/designer')) ?>" method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Left Column: Asset Uploads & Signatories -->
    <div class="col-lg-7">
      <!-- Background & Seal Card -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-image text-primary"></i> Background & Official Seal
          </h5>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Custom Certificate Background (JPG/PNG)</label>
              <?php if (!empty($template['background_image_path'])): ?>
                <div class="mb-2 p-2 border rounded bg-light text-center">
                  <img src="<?= e(url($template['background_image_path'])) ?>" alt="Current Background" class="img-fluid rounded" style="max-height: 100px;">
                  <div class="small text-muted mt-1">Active Custom Background</div>
                </div>
              <?php endif; ?>
              <input type="file" name="background_image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
              <div class="form-text small">Recommended: 2480 &times; 1754 px (A4 Landscape, 300 DPI). If empty, standard classic border is used.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold small">Organization Seal (Transparent PNG)</label>
              <?php if (!empty($template['seal_image_path'])): ?>
                <div class="mb-2 p-2 border rounded bg-light text-center">
                  <img src="<?= e(url($template['seal_image_path'])) ?>" alt="Current Seal" class="img-fluid" style="max-height: 100px;">
                  <div class="small text-muted mt-1">Active Seal</div>
                </div>
              <?php endif; ?>
              <input type="file" name="seal_image" class="form-control form-control-sm" accept="image/png,image/webp">
              <div class="form-text small">Rendered bottom center above QR. Transparent PNG recommended.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Signatories Card -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-pen text-primary"></i> Dual Authorized Signatories
          </h5>
        </div>
        <div class="card-body p-4">
          <div class="row g-4">
            <!-- Signature 1 (Left) -->
            <div class="col-md-6 border-end">
              <h6 class="fw-bold text-secondary mb-3">Signatory 1 (Left — Coordinator)</h6>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Signature Image (PNG)</label>
                <?php if (!empty($template['signature1_image_path'])): ?>
                  <div class="mb-2 p-2 border rounded bg-light text-center">
                    <img src="<?= e(url($template['signature1_image_path'])) ?>" alt="Signature 1" class="img-fluid" style="max-height: 50px;">
                  </div>
                <?php endif; ?>
                <input type="file" name="signature1_image" class="form-control form-control-sm" accept="image/png,image/webp,image/jpeg">
                <div class="form-text small">Transparent background PNG is best.</div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Authority Full Name</label>
                <input type="text" name="signature1_name" class="form-control form-control-sm" value="<?= e($template['signature1_name'] ?? '') ?>" placeholder="e.g. Dr. Priya Sharma">
              </div>
              <div class="mb-2">
                <label class="form-label small fw-semibold">Designation / Role</label>
                <input type="text" name="signature1_designation" class="form-control form-control-sm" value="<?= e($template['signature1_designation'] ?? '') ?>" placeholder="e.g. Program Coordinator">
              </div>
            </div>

            <!-- Signature 2 (Right) -->
            <div class="col-md-6">
              <h6 class="fw-bold text-secondary mb-3">Signatory 2 (Right — Executive)</h6>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Signature Image (PNG)</label>
                <?php if (!empty($template['signature2_image_path'])): ?>
                  <div class="mb-2 p-2 border rounded bg-light text-center">
                    <img src="<?= e(url($template['signature2_image_path'])) ?>" alt="Signature 2" class="img-fluid" style="max-height: 50px;">
                  </div>
                <?php endif; ?>
                <input type="file" name="signature2_image" class="form-control form-control-sm" accept="image/png,image/webp,image/jpeg">
                <div class="form-text small">Transparent background PNG is best.</div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Authority Full Name</label>
                <input type="text" name="signature2_name" class="form-control form-control-sm" value="<?= e($template['signature2_name'] ?? '') ?>" placeholder="e.g. Rajesh Kumar">
              </div>
              <div class="mb-2">
                <label class="form-label small fw-semibold">Designation / Role</label>
                <input type="text" name="signature2_designation" class="form-control form-control-sm" value="<?= e($template['signature2_designation'] ?? '') ?>" placeholder="e.g. Executive Director">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="d-flex justify-content-end gap-2 mb-4">
        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
          <i class="bi bi-check2-circle me-1"></i> Save Certificate Template
        </button>
      </div>
    </div>

    <!-- Right Column: Dynamic Variables & Visual Guidelines -->
    <div class="col-lg-5">
      <!-- Dynamic Variables Card -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-braces text-primary"></i> Dynamic Template Variables
          </h5>
        </div>
        <div class="card-body p-4">
          <p class="small text-muted">These placeholders are automatically resolved during on-demand rendering for each individual recipient:</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Variable</th>
                  <th>Resolved Value</th>
                </tr>
              </thead>
              <tbody class="small">
                <tr>
                  <td><code>{Name}</code></td>
                  <td>Recipient attendee legal full name</td>
                </tr>
                <tr>
                  <td><code>{Event}</code></td>
                  <td><strong><?= e($event['title']) ?></strong></td>
                </tr>
                <tr>
                  <td><code>{EventDate}</code></td>
                  <td><?= e(date('F d, Y', strtotime((string) $event['start_time']))) ?></td>
                </tr>
                <tr>
                  <td><code>{Date}</code></td>
                  <td>Date of certificate issuance</td>
                </tr>
                <tr>
                  <td><code>{CertificateID}</code></td>
                  <td>10-character cryptographically unique uppercase ID (e.g. <code>A7K92P4XQ1</code>)</td>
                </tr>
                <tr>
                  <td><code>{Organization}</code></td>
                  <td>Listening Community &bull; Suicide Prevention Campaign</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Security & Anti-Fraud Features Card -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-shield-check text-success"></i> Security & Anti-Fraud Features
          </h5>
        </div>
        <div class="card-body p-4">
          <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small text-muted">
            <li class="d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill text-success mt-1"></i>
              <span><strong>10-Character Random Unique ID</strong>: Printed prominently and encoded into verification URLs.</span>
            </li>
            <li class="d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill text-success mt-1"></i>
              <span><strong>Real-time QR Verification</strong>: Scannable directly by employers and universities linking to <code>/verify/{id}</code>.</span>
            </li>
            <li class="d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill text-success mt-1"></i>
              <span><strong>Strict Attendance Gating</strong>: Un-attended, absent, or cancelled registrants are cryptographically blocked from generating certificates.</span>
            </li>
            <li class="d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill text-success mt-1"></i>
              <span><strong>Completed Event Status Requirement</strong>: Certificates are unlocked only when the event is concluded.</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</form>
