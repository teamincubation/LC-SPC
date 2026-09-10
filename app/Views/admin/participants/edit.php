<!-- Participant Edit Header -->
<div class="card mb-6">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.25rem;">
        Edit Participant: <?= e($participant['full_name']) ?>
      </h1>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Update attendee profile, category alignment, or contact information. Consent timestamps remain permanently immutable.
      </p>
    </div>
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <a href="<?= e(url('/admin/participants/' . (int) $participant['id'])) ?>" class="btn btn-outline btn-sm">
        &larr; View Profile
      </a>
      <a href="<?= e(url('/admin/participants')) ?>" class="btn btn-outline btn-sm">
        Directory
      </a>
    </div>
  </div>
</div>

<div class="card">
  <form action="<?= e(url('/admin/participants/' . (int) $participant['id'])) ?>" method="POST" novalidate>
    <?= csrf_field() ?>

    <!-- Full Name & Stakeholder Category -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div class="form-group">
        <label for="participant-name" class="form-label form-label-required">Full Name</label>
        <input 
          type="text" 
          id="participant-name" 
          name="full_name" 
          value="<?= e($old['full_name'] ?? $participant['full_name']) ?>" 
          class="form-control <?= !empty($errors['full_name']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. John Doe / Ananya Sharma" 
          maxlength="191" 
          required 
          autofocus
        >
        <?php if (!empty($errors['full_name'])): ?>
          <span class="form-error"><?= e($errors['full_name']) ?></span>
        <?php else: ?>
          <span class="form-hint">Legal or preferred full name for certificates and identification.</span>
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
          <?php $currentCat = $old['category'] ?? $participant['category']; ?>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= ($currentCat === $cat) ? 'selected' : '' ?>>
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
          value="<?= e($old['email'] ?? ($participant['email'] ?? '')) ?>" 
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
          value="<?= e($old['phone'] ?? ($participant['phone'] ?? '')) ?>" 
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
          value="<?= e($old['organization_name'] ?? ($participant['organization_name'] ?? '')) ?>" 
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
        <label for="participant-status" class="form-label form-label-required">Account Status</label>
        <select 
          id="participant-status" 
          name="status" 
          class="form-control <?= !empty($errors['status']) ? 'is-invalid' : '' ?>" 
          required
        >
          <?php $currentStatus = $old['status'] ?? $participant['status']; ?>
          <?php foreach ($statuses as $st): ?>
            <option value="<?= e($st) ?>" <?= ($currentStatus === $st) ? 'selected' : '' ?>>
              <?= e(ucfirst($st)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['status'])): ?>
          <span class="form-error"><?= e($errors['status']) ?></span>
        <?php else: ?>
          <span class="form-hint">Only active participants may be registered for campaign events.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Read-Only Immutable Compliance & Consent Audit Card -->
    <div class="card mb-6" style="background-color: var(--bg-surface-subtle); border: 1px solid var(--border-color); padding: 1.25rem;">
      <h3 style="font-size: var(--font-size-md); margin: 0 0 0.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
        <span>&#128274;</span> Immutable Compliance Timestamps
      </h3>
      <p class="text-secondary" style="font-size: var(--font-size-xs); margin-bottom: 1rem;">
        Pursuant to data governance policies, server-side consent timestamps are permanently locked and cannot be altered after creation.
      </p>

      <div class="grid grid-cols-2 gap-4">
        <div style="padding: 0.75rem 1rem; background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);">
          <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-bottom: 0.25rem;">
            Guidelines Agreement (<code>agreed_guidelines_at</code>)
          </div>
          <div style="font-weight: var(--font-weight-semibold); color: var(--text-primary); font-size: var(--font-size-sm); display: flex; align-items: center; gap: 0.4rem;">
            <span style="color: var(--success);">&#10003;</span>
            <span><?= !empty($participant['agreed_guidelines_at']) ? e(date('F j, Y, g:i A', strtotime($participant['agreed_guidelines_at']))) : '<em>Not Recorded</em>' ?></span>
          </div>
        </div>

        <div style="padding: 0.75rem 1rem; background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);">
          <div style="font-size: var(--font-size-xs); color: var(--text-secondary); margin-bottom: 0.25rem;">
            Privacy Consent (<code>privacy_consent_at</code>)
          </div>
          <div style="font-weight: var(--font-weight-semibold); color: var(--text-primary); font-size: var(--font-size-sm); display: flex; align-items: center; gap: 0.4rem;">
            <span style="color: var(--success);">&#10003;</span>
            <span><?= !empty($participant['privacy_consent_at']) ? e(date('F j, Y, g:i A', strtotime($participant['privacy_consent_at']))) : '<em>Not Recorded</em>' ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Action Controls -->
    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
      <a href="<?= e(url('/admin/participants/' . (int) $participant['id'])) ?>" class="btn btn-outline">
        Cancel
      </a>
      <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
        <span>Save Participant Changes</span>
        <span aria-hidden="true">&rarr;</span>
      </button>
    </div>
  </form>
</div>
