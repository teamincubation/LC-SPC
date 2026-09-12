<?php
/**
 * Event-Centric Public Check-in Portal
 * View: app/Views/public/checkin/index.php
 */
?>

<div class="checkin-portal-card card card-elevated" style="max-width: 620px; margin: 2rem auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
  
  <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 2rem 1.5rem; text-align: center;">
    <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.4); margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem; border-radius: 20px;">
      EVENT CHECK-IN PORTAL
    </span>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem 0; color: #fff;">
      <?= e($event['title']) ?>
    </h1>
    <p style="margin: 0; font-size: 0.95rem; opacity: 0.9;">
      <?= date('F j, Y', strtotime($event['start_time'])) ?> &bull; <?= date('g:i A', strtotime($event['start_time'])) ?>
    </p>
    <?php if (!empty($event['venue_name'])): ?>
      <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; opacity: 0.85;">
        📍 <?= e($event['venue_name']) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="card-body" style="padding: 2rem;">

    <?php if (!$timing['can_checkin']): ?>
      <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem; text-align: center;">
        <h4 style="margin: 0 0 0.25rem 0; font-weight: 700;">Check-in Notice</h4>
        <p style="margin: 0; font-size: 0.95rem;"><?= e($timing['message']) ?></p>
      </div>

      <div style="text-align: center; margin-top: 1.5rem;">
        <a href="<?= e(url('/register/' . $event['slug'])) ?>" class="btn btn-secondary">
          Not registered yet? Register for this event
        </a>
      </div>

    <?php else: ?>

      <form action="<?= e(url('/check-in/' . $event['slug'])) ?>" method="POST" id="checkinForm" novalidate>
        <?= csrf_field() ?>

        <input type="hidden" name="latitude" id="geoLatitude" value="">
        <input type="hidden" name="longitude" id="geoLongitude" value="">

        <div style="margin-bottom: 1.5rem; text-align: center;">
          <h2 style="font-size: 1.2rem; font-weight: 700; color: #1e293b; margin: 0 0 0.5rem 0;">
            Verify Registered Mobile Number
          </h2>
          <p style="margin: 0; font-size: 0.9rem; color: #64748b;">
            Enter the WhatsApp or Mobile number you used when registering for this event.
          </p>
        </div>

        <!-- Mobile Number Verification Input -->
        <div class="form-group" style="margin-bottom: 1.5rem;">
          <label for="checkinPhone" class="form-label" style="display: block; font-weight: 600; margin-bottom: 0.4rem;">
            WhatsApp / Mobile Number <span style="color: #e11d48;">*</span>
          </label>
          <div style="display: flex; gap: 0.5rem;">
            <select 
              name="country_code" 
              id="country_code" 
              class="form-select" 
              style="width: 110px; padding: 0.75rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; background: #fff;"
            >
              <option value="+91" selected>+91 (IN)</option>
              <option value="+1">+1 (US)</option>
              <option value="+44">+44 (UK)</option>
              <option value="+971">+971 (AE)</option>
              <option value="+65">+65 (SG)</option>
              <option value="+61">+61 (AU)</option>
            </select>
            <input 
              type="tel" 
              id="checkinPhone" 
              name="phone" 
              class="form-control" 
              placeholder="e.g. 9876543210" 
              required
              autofocus
              maxlength="15"
              style="flex: 1; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1.05rem;"
            >
          </div>
        </div>

        <!-- Optional Geofence Status Badge -->
        <?php if ($hasGeofence): ?>
          <div id="geofenceNotice" style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 0.85rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.25rem;">📍</span>
            <div style="font-size: 0.85rem; color: #475569;">
              <strong style="color: #1e293b;">Venue Perimeter Verification:</strong>
              <span id="geoStatusText">Requesting venue GPS proximity...</span>
            </div>
          </div>
        <?php endif; ?>

        <button 
          type="submit" 
          id="submitCheckinBtn"
          class="btn btn-primary btn-block" 
          style="width: 100%; padding: 0.9rem; font-size: 1.1rem; font-weight: 700; border-radius: 8px; background: #059669; border-color: #059669;"
        >
          Confirm My Check-in &rarr;
        </button>

        <div style="margin-top: 1.25rem; text-align: center;">
          <a href="<?= e(url('/register/' . $event['slug'])) ?>" class="btn-link" style="color: #059669; font-weight: 600; font-size: 0.95rem; text-decoration: underline; display: inline-flex; align-items: center; gap: 0.35rem;">
            Not registered yet? Register for this event
          </a>
        </div>
      </form>

      <!-- Check-in QR Share & Info -->
      <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; text-align: center;">
        <span style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 0.75rem;">
          Event Venue QR Code for Check-in
        </span>
        <div style="display: inline-block; padding: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;">
          <?= $qrSvg ?>
        </div>
      </div>

    <?php endif; ?>
  </div>
</div>

<?php if ($hasGeofence): ?>
<script>
  (function() {
    var geoStatus = document.getElementById('geoStatusText');
    var latInput = document.getElementById('geoLatitude');
    var lngInput = document.getElementById('geoLongitude');

    if ('geolocation' in navigator) {
      navigator.geolocation.getCurrentPosition(
        function(position) {
          latInput.value = position.coords.latitude;
          lngInput.value = position.coords.longitude;
          if (geoStatus) {
            geoStatus.textContent = 'GPS location acquired (' + position.coords.accuracy.toFixed(0) + 'm accuracy).';
            geoStatus.style.color = '#059669';
          }
        },
        function(error) {
          if (geoStatus) {
            geoStatus.textContent = 'Location access optional/denied: ' + error.message;
            geoStatus.style.color = '#b45309';
          }
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
      );
    } else {
      if (geoStatus) {
        geoStatus.textContent = 'Geolocation not supported by your browser.';
      }
    }
  })();
</script>
<?php endif; ?>
