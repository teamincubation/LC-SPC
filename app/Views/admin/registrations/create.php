<!-- Create Registration Form -->
<div class="card mb-6">
  <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.25rem;">
        New Event Registration
      </h1>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Enroll a participant into a scheduled awareness workshop or listening circle.
      </p>
    </div>
    <a href="<?= e(url('/admin/registrations')) ?>" class="btn btn-outline btn-sm">&larr; Back to Directory</a>
  </div>
</div>

<form action="<?= e(url('/admin/registrations')) ?>" method="POST" class="card" style="max-width: 800px; margin: 0 auto; padding: 1.5rem;">
  <?= csrf_field() ?>

  <!-- Flash Validation Alert -->
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-6" style="padding: 1rem 1.25rem;">
      <div style="font-weight: var(--font-weight-semibold); margin-bottom: 0.25rem;">Please address the following validation issues:</div>
      <ul style="margin: 0; padding-left: 1.25rem; font-size: var(--font-size-sm);">
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- Step 1: Event Selection -->
  <fieldset class="mb-6" style="border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 1.25rem;">
    <legend style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-md); padding: 0 0.5rem; color: var(--color-primary-dark);">
      1. Select Scheduled Event
    </legend>

    <div class="form-group mb-3">
      <label for="event_id" class="form-label" style="font-weight: var(--font-weight-medium);">Event Session <span class="text-danger">*</span></label>
      <select name="event_id" id="event_id" class="form-control" required style="width: 100%;">
        <option value="">-- Choose an Event --</option>
        <?php foreach ($events as $ev): ?>
          <?php
            $capText = ((int) $ev['capacity'] === 0) ? 'Unlimited Capacity' : "Capped at {$ev['capacity']} seats";
            $requiresApprovalText = (!empty($ev['requires_approval'])) ? ' [Approval Required]' : '';
            $selected = ((int) ($old['event_id'] ?? $selectedEventId) === (int) $ev['id']) ? 'selected' : '';
          ?>
          <option value="<?= e((string) $ev['id']) ?>" <?= $selected ?>>
            <?= e($ev['title']) ?> (<?= e(date('M d, Y H:i', strtotime((string) $ev['start_time']))) ?>) — <?= e($capText . $requiresApprovalText) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="text-caption text-secondary">Only active, published events are eligible for registration enrollment.</span>
    </div>
  </fieldset>

  <!-- Step 2: Participant Mode Selection -->
  <fieldset class="mb-6" style="border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 1.25rem;">
    <legend style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-md); padding: 0 0.5rem; color: var(--color-primary-dark);">
      2. Participant Identity
    </legend>

    <div style="display: flex; gap: 1.5rem; margin-bottom: 1.25rem;">
      <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer;">
        <input type="radio" name="participant_mode" value="existing" id="mode_existing" <?= (($old['participant_mode'] ?? 'existing') === 'existing') ? 'checked' : '' ?> onchange="toggleParticipantMode()">
        <span>Select Existing Participant</span>
      </label>
      <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer;">
        <input type="radio" name="participant_mode" value="new" id="mode_new" <?= (($old['participant_mode'] ?? '') === 'new') ? 'checked' : '' ?> onchange="toggleParticipantMode()">
        <span>Register New Participant</span>
      </label>
    </div>

    <!-- Mode A: Existing Participant Selector -->
    <div id="section_existing_participant">
      <div class="form-group mb-3">
        <label for="participant_id" class="form-label" style="font-weight: var(--font-weight-medium);">Select Participant</label>
        <select name="participant_id" id="participant_id" class="form-control" style="width: 100%;">
          <option value="">-- Choose Existing Participant --</option>
          <?php foreach ($recentParticipants as $p): ?>
            <?php
              $contactInfo = !empty($p['email']) ? $p['email'] : (!empty($p['phone']) ? $p['phone'] : 'ID #' . $p['id']);
              $selected = ((int) ($old['participant_id'] ?? $selectedParticipantId) === (int) $p['id']) ? 'selected' : '';
            ?>
            <option value="<?= e((string) $p['id']) ?>" <?= $selected ?>>
              <?= e($p['full_name']) ?> (<?= e($contactInfo) ?> &bull; <?= e(ucfirst($p['category'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <span class="text-caption text-secondary">Search existing participants by name, email, or stakeholder category.</span>
      </div>
    </div>

    <!-- Mode B: New Participant Inline Form -->
    <div id="section_new_participant" style="display: none; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
      <div class="form-group mb-3">
        <label for="full_name" class="form-label" style="font-weight: var(--font-weight-medium);">Full Legal Name <span class="text-danger">*</span></label>
        <input type="text" name="full_name" id="full_name" class="form-control" value="<?= e($old['full_name'] ?? '') ?>" placeholder="e.g. Jane Doe" maxlength="150">
        <span class="text-caption text-secondary">Printed on attendance passes and future certificates.</span>
      </div>

      <div class="grid grid-cols-2 gap-4 mb-3">
        <div class="form-group">
          <label for="email" class="form-label" style="font-weight: var(--font-weight-medium);">Email Address</label>
          <input type="email" name="email" id="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" placeholder="name@example.com" maxlength="191">
        </div>
        <div class="form-group">
          <label for="phone" class="form-label" style="font-weight: var(--font-weight-medium);">Phone / WhatsApp</label>
          <input type="tel" name="phone" id="phone" class="form-control" value="<?= e($old['phone'] ?? '') ?>" placeholder="+91 98765 43210" maxlength="25">
        </div>
      </div>
      <span class="text-caption text-secondary" style="display: block; margin-top: -0.5rem; margin-bottom: 0.75rem;">At least one contact method (Email or Phone) is required for operational event notifications.</span>

      <div class="grid grid-cols-2 gap-4 mb-3">
        <div class="form-group">
          <label for="category" class="form-label" style="font-weight: var(--font-weight-medium);">Category <span class="text-danger">*</span></label>
          <select name="category" id="category" class="form-control">
            <option value="student" <?= (($old['category'] ?? '') === 'student') ? 'selected' : '' ?>>Student</option>
            <option value="professional" <?= (($old['category'] ?? '') === 'professional') ? 'selected' : '' ?>>Professional</option>
            <option value="community" <?= (($old['category'] ?? 'community') === 'community') ? 'selected' : '' ?>>Community Member</option>
            <option value="other" <?= (($old['category'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label for="organization_name" class="form-label" style="font-weight: var(--font-weight-medium);">Organization / Institution</label>
          <input type="text" name="organization_name" id="organization_name" class="form-control" value="<?= e($old['organization_name'] ?? '') ?>" placeholder="College, workplace, or NGO" maxlength="191">
        </div>
      </div>

      <div class="form-group mb-3">
        <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: var(--font-size-sm); cursor: pointer;">
          <input type="checkbox" name="agreed_guidelines" value="1" <?= !empty($old['agreed_guidelines']) ? 'checked' : '' ?> style="margin-top: 0.2rem;">
          <span>I confirm participant consent to the <strong>Community Guidelines & Code of Conduct</strong>. <span class="text-danger">*</span></span>
        </label>
      </div>

      <div class="form-group mb-2">
        <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: var(--font-size-sm); cursor: pointer;">
          <input type="checkbox" name="privacy_consent" value="1" <?= !empty($old['privacy_consent']) ? 'checked' : '' ?> style="margin-top: 0.2rem;">
          <span>I confirm participant consent to <strong>Privacy Notice & Data Processing</strong>. <span class="text-danger">*</span></span>
        </label>
      </div>
    </div>
  </fieldset>

  <!-- Step 3: Operational Notes -->
  <fieldset class="mb-6" style="border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 1.25rem;">
    <legend style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-md); padding: 0 0.5rem; color: var(--color-primary-dark);">
      3. Operational Logistics Notes (Optional)
    </legend>

    <div class="form-group mb-1">
      <label for="admin_notes" class="form-label" style="font-weight: var(--font-weight-medium);">Administrative Logistics Notes</label>
      <textarea name="admin_notes" id="admin_notes" rows="2" class="form-control" maxlength="255" placeholder="Operational logistics only (e.g. Registration desk pickup, Reserved front-row audio seating, College badge verification requested)"><?= e($old['admin_notes'] ?? '') ?></textarea>
      <span class="text-caption text-secondary">Max 255 characters. Restricted strictly to non-sensitive operational logistics. Prohibited medical, clinical, counselling, or distress notes will be rejected with a validation error.</span>
    </div>
  </fieldset>

  <!-- Form Actions -->
  <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
    <a href="<?= e(url('/admin/registrations')) ?>" class="btn btn-outline">Cancel</a>
    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">
      Confirm Enrollment & Generate Pass
    </button>
  </div>
</form>

<script>
function toggleParticipantMode() {
  var isNew = document.getElementById('mode_new').checked;
  var existingSec = document.getElementById('section_existing_participant');
  var newSec = document.getElementById('section_new_participant');
  
  if (isNew) {
    existingSec.style.display = 'none';
    newSec.style.display = 'block';
  } else {
    existingSec.style.display = 'block';
    newSec.style.display = 'none';
  }
}
document.addEventListener('DOMContentLoaded', toggleParticipantMode);
</script>
