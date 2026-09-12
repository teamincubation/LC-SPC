<?php
/**
 * Event-Centric Public Registration Form View
 * View: app/Views/public/events/register_v2.php
 */
$defaultCountryCode = $form['default_country_code'] ?? ($globalSettings['default_country_code'] ?? '+91');
?>

<div class="registration-portal-card card card-elevated" style="max-width: 680px; margin: 2rem auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
  <?php if (!empty($form['banner_path'])): ?>
    <div class="event-form-banner" style="width: 100%; max-height: 220px; overflow: hidden; background: #f1f5f9;">
      <img src="<?= e(asset($form['banner_path'])) ?>" alt="<?= e($event['title']) ?> Banner" style="width: 100%; height: auto; object-fit: cover;">
    </div>
  <?php endif; ?>

  <div class="card-body" style="padding: 2rem;">
    <!-- Event Details Header -->
    <div class="event-header-block" style="margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--color-border-subtle, #e2e8f0);">
      <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
        <span class="badge badge-primary"><?= ucfirst(e($event['event_type'] ?? 'offline')) ?> Event</span>
        <span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', e($event['category'] ?? 'workshop'))) ?></span>
        <?php if (!empty($event['collaboration_with'])): ?>
          <span class="badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;">
            In collaboration with <?= e($event['collaboration_with']) ?>
          </span>
        <?php endif; ?>
      </div>

      <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--color-text-emphasis, #0f172a); margin: 0 0 0.5rem 0; line-height: 1.3;">
        <?= e($event['title']) ?>
      </h1>

      <div class="event-meta-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 1rem; font-size: 0.9rem; color: #475569;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <span style="font-size: 1.1rem;">📅</span>
          <div>
            <strong>Date:</strong> <?= date('F j, Y', strtotime($event['start_time'])) ?>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <span style="font-size: 1.1rem;">⏰</span>
          <div>
            <strong>Time:</strong> <?= date('g:i A', strtotime($event['start_time'])) ?> &ndash; <?= date('g:i A', strtotime($event['end_time'])) ?>
          </div>
        </div>
        <?php if (!empty($event['venue_name'])): ?>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.1rem;">📍</span>
            <div>
              <strong>Venue:</strong> <?= e($event['venue_name']) ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isClosed): ?>
      <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 0.25rem 0; font-weight: 600;">Registration Closed</h4>
        <p style="margin: 0;">Registration for this event is concluded or currently unavailable.</p>
      </div>
    <?php elseif ($deadlinePassed): ?>
      <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 0.25rem 0; font-weight: 600;">Deadline Passed</h4>
        <p style="margin: 0;">The registration deadline for this event has passed.</p>
      </div>
    <?php else: ?>

      <?php if ($isFull): ?>
        <div class="alert alert-info" role="alert" style="margin-bottom: 1.5rem;">
          <strong>Capacity Reached:</strong> This event has reached standard capacity. Further registrations will be placed on the waitlist.
        </div>
      <?php endif; ?>

      <form action="<?= e(url('/register/' . $form['slug'])) ?>" method="POST" id="eventRegistrationForm" novalidate>
        <?= csrf_field() ?>

        <!-- Honeypot -->
        <div style="display: none;" aria-hidden="true">
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem; color: #1e293b;">
          Participant Details
        </h3>

        <!-- Locked Field: Full Legal Name -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label for="full_name" class="form-label" style="display: block; font-weight: 500; margin-bottom: 0.35rem;">
            Full Legal Name <span style="color: #e11d48;">*</span>
          </label>
          <input 
            type="text" 
            id="full_name" 
            name="full_name" 
            class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
            value="<?= e($old['full_name'] ?? '') ?>" 
            placeholder="Enter your full legal name" 
            required
            maxlength="100"
            style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem;"
          >
          <?php if (isset($errors['full_name'])): ?>
            <div class="invalid-feedback" style="color: #e11d48; font-size: 0.85rem; margin-top: 0.25rem;">
              <?= e($errors['full_name']) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Locked Field: WhatsApp / Mobile Number (+91 Default) -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label for="phone" class="form-label" style="display: block; font-weight: 500; margin-bottom: 0.35rem;">
            WhatsApp / Mobile Number <span style="color: #e11d48;">*</span>
          </label>
          <div style="display: flex; gap: 0.5rem;">
            <select 
              name="country_code" 
              id="country_code" 
              class="form-select" 
              style="width: 110px; padding: 0.65rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #fff;"
            >
              <option value="+91" <?= ($old['country_code'] ?? $defaultCountryCode) === '+91' ? 'selected' : '' ?>>+91 (IN)</option>
              <option value="+1" <?= ($old['country_code'] ?? '') === '+1' ? 'selected' : '' ?>>+1 (US)</option>
              <option value="+44" <?= ($old['country_code'] ?? '') === '+44' ? 'selected' : '' ?>>+44 (UK)</option>
              <option value="+971" <?= ($old['country_code'] ?? '') === '+971' ? 'selected' : '' ?>>+971 (AE)</option>
              <option value="+65" <?= ($old['country_code'] ?? '') === '+65' ? 'selected' : '' ?>>+65 (SG)</option>
              <option value="+61" <?= ($old['country_code'] ?? '') === '+61' ? 'selected' : '' ?>>+61 (AU)</option>
            </select>
            <input 
              type="tel" 
              id="phone" 
              name="phone" 
              class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" 
              value="<?= e($old['phone'] ?? '') ?>" 
              placeholder="e.g. 9876543210" 
              required
              maxlength="15"
              style="flex: 1; padding: 0.65rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem;"
            >
          </div>
          <small style="display: block; color: #64748b; font-size: 0.8rem; margin-top: 0.3rem;">
            Enter 10-digit mobile number. You will use this mobile number for quick event check-in.
          </small>
          <?php if (isset($errors['phone'])): ?>
            <div class="invalid-feedback" style="color: #e11d48; font-size: 0.85rem; margin-top: 0.25rem;">
              <?= e($errors['phone']) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Optional Place -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label for="place" class="form-label" style="display: block; font-weight: 500; margin-bottom: 0.35rem;">
            Place / City of Residence
          </label>
          <input 
            type="text" 
            id="place" 
            name="place" 
            class="form-control" 
            value="<?= e($old['place'] ?? '') ?>" 
            placeholder="e.g. Kozhikode, Kerala"
            style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem;"
          >
        </div>

        <!-- Dynamic Custom Fields -->
        <?php foreach ($fields as $field): ?>
          <?php 
            if (in_array($field['field_key'], ['full_name', 'phone', 'place'], true)) {
                continue;
            }
            $fKey = e($field['field_key']);
            $fType = $field['field_type'];
            $fReq = !empty($field['is_required']);
            $fLabel = e($field['field_label']);
            $fVal = $old[$field['field_key']] ?? '';
          ?>
          <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="<?= $fKey ?>" class="form-label" style="display: block; font-weight: 500; margin-bottom: 0.35rem;">
              <?= $fLabel ?> <?= $fReq ? '<span style="color: #e11d48;">*</span>' : '' ?>
            </label>

            <?php if ($fType === 'long_text'): ?>
              <textarea 
                id="<?= $fKey ?>" 
                name="<?= $fKey ?>" 
                class="form-control" 
                rows="3" 
                <?= $fReq ? 'required' : '' ?>
                placeholder="<?= e($field['placeholder'] ?? '') ?>"
                style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem;"
              ><?= e($fVal) ?></textarea>

            <?php elseif ($fType === 'dropdown'): ?>
              <?php $opts = !empty($field['options_json']) ? json_decode($field['options_json'], true) : []; ?>
              <select 
                id="<?= $fKey ?>" 
                name="<?= $fKey ?>" 
                class="form-select" 
                <?= $fReq ? 'required' : '' ?>
                style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #fff;"
              >
                <option value="">-- Select an option --</option>
                <?php if (is_array($opts)): ?>
                  <?php foreach ($opts as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $fVal === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>

            <?php else: ?>
              <input 
                type="<?= $fType === 'email' ? 'email' : ($fType === 'number' ? 'number' : ($fType === 'date' ? 'date' : 'text')) ?>" 
                id="<?= $fKey ?>" 
                name="<?= $fKey ?>" 
                class="form-control" 
                value="<?= e($fVal) ?>" 
                <?= $fReq ? 'required' : '' ?>
                placeholder="<?= e($field['placeholder'] ?? '') ?>"
                style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem;"
              >
            <?php endif; ?>

            <?php if (!empty($field['help_text'])): ?>
              <small style="display: block; color: #64748b; font-size: 0.8rem; margin-top: 0.25rem;">
                <?= e($field['help_text']) ?>
              </small>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div style="margin-top: 2rem;">
          <button 
            type="submit" 
            class="btn btn-primary btn-block" 
            style="width: 100%; padding: 0.85rem; font-size: 1.05rem; font-weight: 600; border-radius: 8px;"
          >
            Complete Registration &rarr;
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
