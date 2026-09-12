<?php
  /** @var array $settings */
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge bg-primary">GLOBAL CONFIGURATION</span>
        <span class="text-white-50">&bull;</span>
        <span class="text-white-50 small">Form Engine Rules</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Global Form Settings</h2>
      <p class="text-white-50 mb-0">Configure default behavior, country dial codes, location permissions, and WhatsApp onboarding redirects for all event registration forms.</p>
    </div>
  </div>
</div>

<form action="<?= e(url('/admin/form-settings')) ?>" method="POST">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Registration Behavior Card -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-shield-check text-primary"></i> Registration & Location Rules
          </h5>
        </div>
        <div class="card-body p-4">
          <!-- Mandatory Location Access -->
          <div class="mb-4 p-3 border rounded bg-light">
            <div class="form-check form-switch d-flex justify-content-between align-items-center ps-0 mb-2">
              <label class="form-check-label fw-bold text-dark" for="mandatoryLocation">
                Mandatory Location Access
              </label>
              <input class="form-check-input ms-0" 
                     type="checkbox" 
                     role="switch" 
                     name="mandatory_location_access" 
                     value="1" 
                     id="mandatoryLocation"
                     <?= !empty($settings['mandatory_location_access']) ? 'checked' : '' ?>>
            </div>
            <p class="small text-muted mb-0">
              When enabled, registration forms will prompt attendees for browser geolocation. If denied, the form displays a polite notification explaining that verified location is requested for attendance auditing.
            </p>
          </div>

          <!-- Country Code Defaults -->
          <div class="row g-3 mb-4">
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Default Country Dial Code</label>
              <input type="text" name="default_country_code" class="form-control" value="<?= e($settings['default_country_code'] ?? '+91') ?>" placeholder="+91">
              <div class="form-text small">Default prefix for WhatsApp / Mobile fields.</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Default Country ISO Code</label>
              <input type="text" name="default_country_iso" class="form-control" value="<?= e($settings['default_country_iso'] ?? 'IN') ?>" placeholder="IN" maxlength="2">
              <div class="form-text small">ISO 3166-1 alpha-2 (e.g. IN, US, GB).</div>
            </div>
          </div>

          <!-- Success Message -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Default Registration Success Message</label>
            <textarea name="registration_success_message" class="form-control" rows="3" placeholder="Thank you for registering! We look forward to your presence."><?= e($settings['registration_success_message'] ?? '') ?></textarea>
            <div class="form-text small">Displayed on the pass page immediately following successful registration.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- WhatsApp Community Onboarding Card -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-whatsapp text-success"></i> WhatsApp Community Redirect
          </h5>
        </div>
        <div class="card-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold small">Global WhatsApp Community / Group Link</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-link-45deg"></i></span>
              <input type="url" name="whatsapp_group_url" class="form-control" value="<?= e($settings['whatsapp_group_url'] ?? '') ?>" placeholder="https://chat.whatsapp.com/...">
            </div>
            <div class="form-text small">Can be overridden on a per-event form basis.</div>
          </div>

          <!-- Auto Redirect Switch -->
          <div class="mb-4 p-3 border rounded bg-light">
            <div class="form-check form-switch d-flex justify-content-between align-items-center ps-0 mb-2">
              <label class="form-check-label fw-bold text-dark" for="whatsappAutoRedirect">
                Automatic Post-Registration Redirect
              </label>
              <input class="form-check-input ms-0" 
                     type="checkbox" 
                     role="switch" 
                     name="whatsapp_auto_redirect" 
                     value="1" 
                     id="whatsappAutoRedirect"
                     <?= !empty($settings['whatsapp_auto_redirect']) ? 'checked' : '' ?>>
            </div>
            <p class="small text-muted mb-0">
              When active, participants viewing their registration pass will see an animated countdown timer before automatically opening the WhatsApp group.
            </p>
          </div>

          <!-- Countdown Seconds -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Auto-Redirect Countdown (Seconds)</label>
            <div class="input-group" style="max-width: 200px;">
              <input type="number" name="whatsapp_countdown_seconds" class="form-control" min="1" max="30" value="<?= e($settings['whatsapp_countdown_seconds'] ?? 5) ?>">
              <span class="input-group-text">sec</span>
            </div>
            <div class="form-text small">Recommended: 3 to 7 seconds so users can glance at their pass.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end mt-4">
    <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm">
      <i class="bi bi-check2-circle me-1"></i> Save Global Form Settings
    </button>
  </div>
</form>
