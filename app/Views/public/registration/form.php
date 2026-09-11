<!-- Breadcrumb Navigation -->
<nav class="mb-4" aria-label="Breadcrumb">
  <ol class="breadcrumb flex gap-2 text-caption text-muted list-none p-0 m-0">
    <li><a href="<?= e(url('/')) ?>">Home</a> &rsaquo;</li>
    <li><a href="<?= e(url('/events')) ?>">Events</a> &rsaquo;</li>
    <li>
      <a href="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']))) ?>">
        <?= e($event['title']) ?>
      </a> &rsaquo;
    </li>
    <li class="font-weight-medium text-secondary" aria-current="page">Register</li>
  </ol>
</nav>

<div class="row justify-center" style="max-width: 680px; margin: 0 auto;">
  <!-- Event Summary Banner -->
  <div class="card mb-4" style="border-left: 4px solid var(--color-primary);">
    <div class="card-header pb-2">
      <span class="badge badge-primary mb-1">Registration Portal</span>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin: 0 0 0.25rem 0;">
        <?= e($event['title']) ?>
      </h1>
      <p class="text-caption text-muted m-0">
        Initiative: <strong><?= e($event['campaign_title']) ?></strong> &bull; <?= e(date('l, F d, Y &bull; h:i A', strtotime((string) $event['start_time']))) ?>
      </p>
    </div>

    <?php if (!empty($isWaitlist)): ?>
      <div class="p-4 pt-2">
        <div class="alert alert-warning mb-0" role="alert">
          <div class="alert-content">
            <strong>Waitlist Notice:</strong> Regular seating for this session is currently filled. Submitting this form will register you on the official waitlist. If a seat becomes available, your status will be automatically updated.
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Registration Form Card -->
  <div class="card p-6">
    <div class="card-header border-bottom pb-3 mb-4">
      <h2 class="card-title" style="font-size: var(--font-size-lg); margin: 0;">
        Attendee Registration
      </h2>
      <span class="text-caption text-muted">Fields marked with <span class="text-danger">*</span> are required.</span>
    </div>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-danger mb-4" role="alert">
        <div class="alert-content">
          <?= e($errors['general']) ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']) . '/register')) ?>" id="publicRegForm" novalidate>
      <?= csrf_field() ?>

      <!-- Anti-Spam Honeypot Field (Invisible to human users) -->
      <div style="position: absolute; left: -9999px; top: -9999px; height: 0; width: 0; overflow: hidden;" aria-hidden="true">
        <label for="reg_website">Leave this field blank</label>
        <input type="text" id="reg_website" name="website" tabindex="-1" autocomplete="off" value="">
      </div>

      <!-- Full Name (Required) -->
      <div class="form-group mb-4">
        <label for="full_name" class="form-label form-label-required font-weight-medium">
          Full Name <span class="text-danger">*</span>
        </label>
        <input type="text" id="full_name" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" placeholder="e.g. Priya Sharma" value="<?= e($old['full_name'] ?? '') ?>" required autocomplete="name" maxlength="150">
        <?php if (isset($errors['full_name'])): ?>
          <span class="form-error text-danger text-caption"><?= e($errors['full_name']) ?></span>
        <?php else: ?>
          <span class="form-hint text-muted text-caption">This name will appear on your official attendance pass and verified certificate.</span>
        <?php endif; ?>
      </div>

      <!-- Category (Required) -->
      <div class="form-group mb-4">
        <label for="category" class="form-label form-label-required font-weight-medium">
          Participation Category <span class="text-danger">*</span>
        </label>
        <select id="category" name="category" class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>" required>
          <option value="">-- Please select your category --</option>
          <option value="student" <?= ($old['category'] ?? '') === 'student' ? 'selected' : '' ?>>Student / Youth Volunteer</option>
          <option value="professional" <?= ($old['category'] ?? '') === 'professional' ? 'selected' : '' ?>>Professional / Educator / Counselor</option>
          <option value="community" <?= ($old['category'] ?? '') === 'community' ? 'selected' : '' ?>>Community Member / Supporter</option>
          <option value="other" <?= ($old['category'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
        </select>
        <?php if (isset($errors['category'])): ?>
          <span class="form-error text-danger text-caption"><?= e($errors['category']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Email Address (Optional) -->
      <div class="form-group mb-4">
        <label for="email" class="form-label font-weight-medium">
          Email Address <span class="text-muted font-normal text-caption">(Optional)</span>
        </label>
        <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" placeholder="e.g. priya.sharma@example.com" value="<?= e($old['email'] ?? '') ?>" autocomplete="email" maxlength="191">
        <?php if (isset($errors['email'])): ?>
          <span class="form-error text-danger text-caption"><?= e($errors['email']) ?></span>
        <?php else: ?>
          <span class="form-hint text-muted text-caption">Optional. Providing an email enables easy pass retrieval if you misplace your code.</span>
        <?php endif; ?>
      </div>

      <!-- Phone Number (Optional) -->
      <div class="form-group mb-4">
        <label for="phone" class="form-label font-weight-medium">
          Phone Number <span class="text-muted font-normal text-caption">(Optional)</span>
        </label>
        <input type="tel" id="phone" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" placeholder="e.g. +91 9876543210" value="<?= e($old['phone'] ?? '') ?>" autocomplete="tel" maxlength="25">
        <?php if (isset($errors['phone'])): ?>
          <span class="form-error text-danger text-caption"><?= e($errors['phone']) ?></span>
        <?php else: ?>
          <span class="form-hint text-muted text-caption">Optional. For operational session updates if needed.</span>
        <?php endif; ?>
      </div>

      <!-- Organization / Institution (Optional) -->
      <div class="form-group mb-4">
        <label for="organization_name" class="form-label font-weight-medium">
          Organization, School, or College <span class="text-muted font-normal text-caption">(Optional)</span>
        </label>
        <input type="text" id="organization_name" name="organization_name" class="form-control <?= isset($errors['organization_name']) ? 'is-invalid' : '' ?>" placeholder="e.g. Delhi University / Freelance" value="<?= e($old['organization_name'] ?? '') ?>" maxlength="191">
        <?php if (isset($errors['organization_name'])): ?>
          <span class="form-error text-danger text-caption"><?= e($errors['organization_name']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Compliance & Consents (Mandatory) -->
      <div class="border-top pt-4 mb-4">
        <div class="form-group mb-3">
          <label class="form-check flex items-start gap-2">
            <input type="checkbox" name="agreed_guidelines" value="1" class="form-check-input mt-1" required>
            <span class="form-check-label text-caption text-secondary" style="line-height: 1.5;">
              <strong class="text-dark">I agree to the Community Guidelines:</strong>
              I understand that Listening Community events are safe, respectful, non-judgmental spaces. I agree to treat all participants with confidentiality and kindness.
            </span>
          </label>
          <?php if (isset($errors['agreed_guidelines'])): ?>
            <span class="form-error text-danger text-caption d-block mt-1"><?= e($errors['agreed_guidelines']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group mb-4">
          <label class="form-check flex items-start gap-2">
            <input type="checkbox" name="privacy_consent" value="1" class="form-check-input mt-1" required>
            <span class="form-check-label text-caption text-secondary" style="line-height: 1.5;">
              <strong class="text-dark">Privacy Notice Consent:</strong>
              I consent to the collection of my registration details strictly for event organization and certificate generation under LC-SPC data protection principles. My information will never be shared with advertisers or third parties.
            </span>
          </label>
          <?php if (isset($errors['privacy_consent'])): ?>
            <span class="form-error text-danger text-caption d-block mt-1"><?= e($errors['privacy_consent']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Action Button with Double Submission Defense -->
      <div class="flex justify-between items-center gap-4 pt-2">
        <a href="<?= e(url('/events/' . rawurlencode((string) $event['campaign_slug']) . '/' . rawurlencode((string) $event['slug']))) ?>" class="btn btn-outline">
          &larr; Back
        </a>
        <button type="submit" id="btnSubmitReg" class="btn btn-primary">
          <?= !empty($isWaitlist) ? 'Submit Waitlist Registration &rarr;' : 'Complete Free Registration &rarr;' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  // Idempotency: Prevent double-click submissions
  document.getElementById('publicRegForm')?.addEventListener('submit', function (e) {
    const btn = document.getElementById('btnSubmitReg');
    if (btn && !btn.disabled) {
      btn.disabled = true;
      btn.innerText = 'Processing Registration...';
    }
  });
</script>
