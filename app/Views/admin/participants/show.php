<!-- Participant Profile Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline btn-sm btn-icon" title="Return to Directory" aria-label="Return to Directory">
        <?= icon('arrow-left', ['width' => '14', 'height' => '14']) ?>
      </a>
      <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
          <h1 style="margin: 0;"><?= e($participant['full_name']) ?></h1>
          <span class="badge badge-pill badge-neutral" style="font-size: var(--font-size-xs);">
            ID #<?= e((string) $participant['id']) ?>
          </span>

          <span class="badge badge-pill badge-info" style="font-size: var(--font-size-xs);">
            <?= e(ucfirst($participant['category'])) ?>
          </span>

          <?php if ($participant['status'] === 'active'): ?>
            <span class="badge badge-pill badge-success">
              <span class="badge-dot" aria-hidden="true"></span>
              Active
            </span>
          <?php elseif ($participant['status'] === 'flagged'): ?>
            <span class="badge badge-pill badge-warning">
              <span class="badge-dot" aria-hidden="true"></span>
              Flagged
            </span>
          <?php else: ?>
            <span class="badge badge-pill badge-danger">
              <span class="badge-dot" aria-hidden="true"></span>
              Blocked
            </span>
          <?php endif; ?>

          <?php if (!empty($participant['_is_masked'])): ?>
            <span class="badge badge-pill badge-neutral" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 11px;">
              <?= icon('shield', ['width' => '11', 'height' => '11']) ?>
              <span>PII Masked (Role: <?= e(ucfirst($userRole)) ?>)</span>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline btn-sm">
      <?= icon('list', ['width' => '14', 'height' => '14']) ?>
      <span>Directory</span>
    </a>

    <?php if (!empty($canEdit)): ?>
      <a href="<?= e(url('/admin/participants/' . (int) $participant['id'] . '/edit')) ?>" class="btn btn-primary btn-sm">
        <?= icon('edit', ['width' => '14', 'height' => '14']) ?>
        <span>Edit Participant</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($participant['_is_masked'])): ?>
  <!-- Privacy Shield Notice Banner -->
  <div class="alert alert-info mb-6" role="alert" style="background-color: var(--bg-surface-subtle); border-left: 4px solid var(--color-primary); padding: 0.875rem 1.25rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <div style="color: var(--color-primary); display: flex; align-items: center;">
        <?= icon('shield', ['width' => '20', 'height' => '20']) ?>
      </div>
      <span style="font-size: var(--font-size-sm); color: var(--text-secondary);">
        <strong>Privacy Shield Active:</strong> Contact information is masked server-side to safeguard participant privacy under your current operational role (<strong><?= e(ucfirst($userRole)) ?></strong>). Full PII is restricted to authorized coordinators and administrators.
      </span>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-2 gap-6 mb-6">
  <!-- Contact & Affiliation Details Card -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title" style="font-size: var(--font-size-md); display: flex; align-items: center; gap: 0.5rem;">
        <?= icon('user', ['width' => '16', 'height' => '16']) ?>
        <span>Identity &amp; Contact Details</span>
      </h2>
    </div>

    <table class="table" style="margin: 0; font-size: var(--font-size-sm);">
      <tbody>
        <tr>
          <th style="width: 35%; color: var(--text-secondary); font-weight: var(--font-weight-normal);">Full Legal Name</th>
          <td style="color: var(--text-primary); font-weight: var(--font-weight-medium);"><?= e($participant['full_name']) ?></td>
        </tr>
        <tr>
          <th style="color: var(--text-secondary); font-weight: var(--font-weight-normal);">Stakeholder Cohort</th>
          <td style="color: var(--text-primary);"><?= e(ucfirst($participant['category'])) ?></td>
        </tr>
        <tr>
          <th style="color: var(--text-secondary); font-weight: var(--font-weight-normal);">Email Address</th>
          <td style="color: var(--text-primary);">
            <?php if (!empty($participant['email'])): ?>
              <span style="font-family: monospace; font-size: 0.9em;"><?= e($participant['email']) ?></span>
            <?php else: ?>
              <span class="text-muted"><em>Not Provided</em></span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th style="color: var(--text-secondary); font-weight: var(--font-weight-normal);">Phone Contact</th>
          <td style="color: var(--text-primary);">
            <?php if (!empty($participant['phone'])): ?>
              <span style="font-family: monospace; font-size: 0.9em;"><?= e($participant['phone']) ?></span>
            <?php else: ?>
              <span class="text-muted"><em>Not Provided</em></span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th style="color: var(--text-secondary); font-weight: var(--font-weight-normal);">Affiliation / Org</th>
          <td style="color: var(--text-primary);">
            <?php if (!empty($participant['organization_name'])): ?>
              <?= e($participant['organization_name']) ?>
            <?php else: ?>
              <span class="text-muted"><em>Independent / None</em></span>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Compliance & Consent Audit Card -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title" style="font-size: var(--font-size-md); display: flex; align-items: center; gap: 0.5rem;">
        <?= icon('file-text', ['width' => '16', 'height' => '16']) ?>
        <span>Compliance &amp; Consent Audit</span>
      </h2>
    </div>

    <table class="table" style="margin: 0; font-size: var(--font-size-sm);">
      <tbody>
        <tr>
          <th style="width: 40%; color: var(--text-secondary); font-weight: var(--font-weight-normal);">Safe Space Guidelines</th>
          <td style="color: var(--text-primary);">
            <?php if (!empty($participant['agreed_guidelines_at'])): ?>
              <span style="color: var(--color-success); font-weight: var(--font-weight-medium); display: inline-flex; align-items: center; gap: 0.35rem;">
                <?= icon('check', ['width' => '14', 'height' => '14']) ?>
                <span>Agreed</span>
              </span>
              <div style="font-size: var(--font-size-xs); color: var(--text-muted); font-family: monospace;">
                <?= e(date('M d, Y H:i:s T', strtotime($participant['agreed_guidelines_at']))) ?>
              </div>
            <?php else: ?>
              <span class="text-danger" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <?= icon('x', ['width' => '14', 'height' => '14']) ?>
                <span>Not Recorded</span>
              </span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th style="color: var(--text-secondary); font-weight: var(--font-weight-normal);">Privacy Notice Consent</th>
          <td style="color: var(--text-primary);">
            <?php if (!empty($participant['privacy_consent_at'])): ?>
              <span style="color: var(--color-success); font-weight: var(--font-weight-medium); display: inline-flex; align-items: center; gap: 0.35rem;">
                <?= icon('check', ['width' => '14', 'height' => '14']) ?>
                <span>Consented</span>
              </span>
              <div style="font-size: var(--font-size-xs); color: var(--text-muted); font-family: monospace;">
                <?= e(date('M d, Y H:i:s T', strtotime($participant['privacy_consent_at']))) ?>
              </div>
            <?php else: ?>
              <span class="text-danger" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <?= icon('x', ['width' => '14', 'height' => '14']) ?>
                <span>Not Recorded</span>
              </span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th style="color: var(--text-secondary); font-weight: var(--font-weight-normal);">Registry Timestamp</th>
          <td style="color: var(--text-secondary); font-size: var(--font-size-xs);">
            <?= !empty($participant['created_at']) ? e(date('M d, Y H:i:s T', strtotime($participant['created_at']))) : '&mdash;' ?>
          </td>
        </tr>
        <tr>
          <th style="color: var(--text-secondary); font-weight: var(--font-weight-normal);">Last Profile Update</th>
          <td style="color: var(--text-secondary); font-size: var(--font-size-xs);">
            <?= !empty($participant['updated_at']) ? e(date('M d, Y H:i:s T', strtotime($participant['updated_at']))) : '&mdash;' ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($canEdit)): ?>
  <!-- Status Lifecycle Management Card -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title" style="font-size: var(--font-size-md); display: flex; align-items: center; gap: 0.5rem;">
        <?= icon('settings', ['width' => '16', 'height' => '16']) ?>
        <span>Lifecycle Status Transition</span>
      </h2>
    </div>

    <form action="<?= e(url('/admin/participants/' . (int) $participant['id'] . '/status')) ?>" method="POST" style="padding: 1.25rem;">
      <?= csrf_field() ?>

      <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="form-group">
          <label for="status-select" class="form-label form-label-required">Account Lifecycle Status</label>
          <select id="status-select" name="status" class="form-control" required>
            <?php foreach ($statuses as $st): ?>
              <option value="<?= e($st) ?>" <?= ($participant['status'] === $st) ? 'selected' : '' ?>>
                <?= e(ucfirst($st)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">Blocked participants cannot be registered for workshops or receive certificates.</span>
        </div>

        <div class="form-group">
          <label for="status-reason" class="form-label">Administrative Reason (Optional)</label>
          <textarea 
            id="status-reason" 
            name="reason" 
            rows="2" 
            class="form-control" 
            placeholder="e.g. Duplicate profile flagged by attendee request / Contact bounced."
            maxlength="255"
          ></textarea>
          <span class="form-hint" style="color: var(--text-muted); font-size: var(--font-size-xs);">
            Audit log rationale. <strong>Strictly prohibited:</strong> Do not record clinical, psychological, distress, or counselling information.
          </span>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end;">
        <button type="submit" class="btn btn-outline btn-sm">
          Update Lifecycle Status
        </button>
      </div>
    </form>
  </div>
<?php endif; ?>
