<!-- Participant Creation Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline btn-sm btn-icon" title="Return to Participants" aria-label="Return to Participants">
        <?= icon('arrow-left', ['width' => '14', 'height' => '14']) ?>
      </a>
      <div>
        <h1>Register Participant</h1>
        <p>Establish a canonical attendee identity with verified contact governance and server-side consent logging.</p>
      </div>
    </div>
  </div>
  <div class="admin-page-header-actions">
    <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline btn-sm">
      <?= icon('list', ['width' => '14', 'height' => '14']) ?>
      <span>All Participants</span>
    </a>
  </div>
</div>

<?php if (!empty($duplicate)): ?>
  <!-- Duplicate Warning Intervention Banner -->
  <div class="alert alert-warning mb-6" role="alert" style="border-left: 4px solid var(--warning); background-color: var(--bg-surface); padding: 1.25rem;">
    <div style="display: flex; align-items: flex-start; gap: 1rem;">
      <div style="color: var(--warning);">
        <?= icon('alert-triangle', ['width' => '24', 'height' => '24']) ?>
      </div>
      <div style="flex: 1;">
        <h4 style="margin: 0 0 0.5rem; font-size: var(--font-size-md); font-weight: var(--font-weight-bold); color: var(--text-primary);">
          Potential Duplicate Detected
        </h4>
        <p style="margin: 0 0 0.75rem; font-size: var(--font-size-sm); color: var(--text-secondary);">
          <?= e($duplicate['message'] ?? 'A participant with matching contact details already exists in the registry.') ?>
        </p>
        <?php if (!empty($duplicate['id'])): ?>
          <div style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.75rem; background-color: var(--bg-surface-subtle); border-radius: var(--border-radius-sm); font-size: var(--font-size-xs); margin-bottom: 0.75rem;">
            <strong>Existing Match:</strong>
            <span>ID #<?= e((string) $duplicate['id']) ?></span>
            <?php if (!empty($duplicate['name'])): ?>
              <span>&mdash; <?= e($duplicate['name']) ?></span>
            <?php endif; ?>
            <a href="<?= e(url('/admin/participants/' . (int) $duplicate['id'])) ?>" target="_blank" style="margin-left: 0.5rem; text-decoration: underline; color: var(--primary);">
              View Profile &nearr;
            </a>
          </div>
        <?php endif; ?>
        <p style="margin: 0; font-size: var(--font-size-xs); color: var(--text-muted);">
          To avoid creating unintended duplicates, verify if this attendee already exists. If this is a distinct person sharing contact information (e.g. family member, shared organization line), tick the <strong>Confirm Distinct Participant</strong> checkbox below before re-submitting.
        </p>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <form action="<?= e(url('/admin/participants')) ?>" method="POST" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($duplicate)): ?>
      <!-- Override Confirmation Checkbox -->
      <div class="form-group mb-6" style="padding: 1rem; background-color: var(--bg-surface-subtle); border: 1px solid var(--border-color); border-radius: var(--border-radius-md);">
        <label class="form-checkbox" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
          <input 
            type="checkbox" 
            name="confirm_distinct" 
            value="1" 
            id="confirm-distinct-checkbox"
            style="margin-top: 0.2rem;"
            required
          >
          <div>
            <strong style="color: var(--text-primary); font-size: var(--font-size-sm); display: block;">
              Confirm Distinct Participant
            </strong>
            <span class="text-secondary" style="font-size: var(--font-size-xs);">
              I have verified the records and confirm this is an intentionally distinct person sharing contact information. Do not merge or overwrite existing records.
            </span>
          </div>
        </label>
      </div>
    <?php endif; ?>

    <!-- Full Name & Stakeholder Category -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div class="form-group">
        <label for="participant-name" class="form-label form-label-required">Full Name</label>
        <input 
          type="text" 
          id="participant-name" 
          name="full_name" 
          value="<?= e($old['full_name'] ?? '') ?>" 
          class="form-control <?= !empty($errors['full_name']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. John Doe / Ananya Sharma" 
          maxlength="191" 
          required 
          autofocus
        >
        <?php if (!empty($errors['full_name'])): ?>
          <span class="form-error"><?= e($errors['full_name']) ?></span>
        <?php else: ?>
          <span class="form-hint">Legal or preferred full name for certificates and identification (2 to 191 characters).</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="participant-category" class="form-label form-label-required">Stakeholder Category</label>
        <select 
          id="participant-category" 
          name="category" 
          class="form-control <?= !empty($errors['category']) ? 'is-invalid' : '' ?>" 
          required
        >
          <?php foreach ($categories as $cat): ?>
            <?php $selected = (!empty($old['category']) && $old['category'] === $cat) || (empty($old['category']) && $cat === 'community') ? 'selected' : ''; ?>
            <option value="<?= e($cat) ?>" <?= $selected ?>>
              <?= e(ucfirst($cat)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['category'])): ?>
          <span class="form-error"><?= e($errors['category']) ?></span>
        <?php else: ?>
          <span class="form-hint">Primary stakeholder cohort classification for session alignment.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Email & Phone Contact -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div class="form-group">
        <label for="participant-email" class="form-label">Email Address</label>
        <input 
          type="email" 
          id="participant-email" 
          name="email" 
          value="<?= e($old['email'] ?? '') ?>" 
          class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. participant@example.com" 
          maxlength="191"
        >
        <?php if (!empty($errors['email'])): ?>
          <span class="form-error"><?= e($errors['email']) ?></span>
        <?php else: ?>
          <span class="form-hint">Official email for digital notices and verified attendance credentials.</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="participant-phone" class="form-label">Phone Number</label>
        <input 
          type="tel" 
          id="participant-phone" 
          name="phone" 
          value="<?= e($old['phone'] ?? '') ?>" 
          class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. +91 9876543210" 
          maxlength="32"
        >
        <?php if (!empty($errors['phone'])): ?>
          <span class="form-error"><?= e($errors['phone']) ?></span>
        <?php else: ?>
          <span class="form-hint">Primary contact number (E.164 international or standard local format).</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Organization & Account Status -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div class="form-group">
        <label for="participant-org" class="form-label">Affiliation / Organization</label>
        <input 
          type="text" 
          id="participant-org" 
          name="organization_name" 
          value="<?= e($old['organization_name'] ?? '') ?>" 
          class="form-control <?= !empty($errors['organization_name']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. Central University / Apex Healthcare / Self" 
          maxlength="191"
        >
        <?php if (!empty($errors['organization_name'])): ?>
          <span class="form-error"><?= e($errors['organization_name']) ?></span>
        <?php else: ?>
          <span class="form-hint">Institutional affiliation, college, employer, or community group (optional).</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="participant-status" class="form-label form-label-required">Initial Account Status</label>
        <select 
          id="participant-status" 
          name="status" 
          class="form-control <?= !empty($errors['status']) ? 'is-invalid' : '' ?>" 
          required
        >
          <?php foreach ($statuses as $st): ?>
            <?php $selected = (!empty($old['status']) && $old['status'] === $st) || (empty($old['status']) && $st === 'active') ? 'selected' : ''; ?>
            <option value="<?= e($st) ?>" <?= $selected ?>>
              <?= e(ucfirst($st)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['status'])): ?>
          <span class="form-error"><?= e($errors['status']) ?></span>
        <?php else: ?>
          <span class="form-hint">Active participants are eligible for session enrolment and certificates.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Server-Side Consent & Guidelines Compliance Section -->
    <div class="card mb-6" style="background-color: var(--bg-surface-subtle); border: 1px solid var(--border-color); padding: 1.25rem;">
      <h3 style="font-size: var(--font-size-md); margin: 0 0 0.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
        <?= icon('shield', ['width' => '18', 'height' => '18']) ?>
        <span>Institutional Consent &amp; Safe Space Compliance</span>
      </h3>
      <p class="text-secondary" style="font-size: var(--font-size-xs); margin-bottom: 1rem;">
        Affirmative confirmations below record permanent, immutable server-side audit timestamps (<code>agreed_guidelines_at</code> and <code>privacy_consent_at</code>). Once committed, consent timestamps cannot be modified.
      </p>

      <div class="form-group mb-3">
        <label class="form-checkbox" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
          <input 
            type="checkbox" 
            name="agreed_guidelines" 
            value="1" 
            id="agreed-guidelines-checkbox"
            <?= !empty($old['agreed_guidelines']) ? 'checked' : '' ?> 
            style="margin-top: 0.2rem;"
            required
          >
          <div>
            <strong style="color: var(--text-primary); font-size: var(--font-size-sm); display: block;">
              Community Guidelines &amp; Safe Space Conduct Agreement <span style="color: var(--danger);">*</span>
            </strong>
            <span class="text-secondary" style="font-size: var(--font-size-xs);">
              The participant acknowledges and commits to the LC-SPC Safe Space Code of Conduct, mutual respect, and listening circle guidelines.
            </span>
          </div>
        </label>
        <?php if (!empty($errors['agreed_guidelines'])): ?>
          <span class="form-error" style="display: block; margin-top: 0.25rem;"><?= e($errors['agreed_guidelines']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-checkbox" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
          <input 
            type="checkbox" 
            name="privacy_consent" 
            value="1" 
            id="privacy-consent-checkbox"
            <?= !empty($old['privacy_consent']) ? 'checked' : '' ?> 
            style="margin-top: 0.2rem;"
            required
          >
          <div>
            <strong style="color: var(--text-primary); font-size: var(--font-size-sm); display: block;">
              Data Governance &amp; Privacy Notice Consent <span style="color: var(--danger);">*</span>
            </strong>
            <span class="text-secondary" style="font-size: var(--font-size-xs);">
              The participant has given explicit consent for identity and contact processing solely for event administration, verification, and accreditation.
            </span>
          </div>
        </label>
        <?php if (!empty($errors['privacy_consent'])): ?>
          <span class="form-error" style="display: block; margin-top: 0.25rem;"><?= e($errors['privacy_consent']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form Action Controls -->
    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
      <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline">
        Cancel
      </a>
      <button type="submit" class="btn btn-primary">
        <?= icon('user-plus', ['width' => '14', 'height' => '14']) ?>
        <span>Create Participant Record</span>
      </button>
    </div>
  </form>
</div>
