<?php
/**
 * Event-Centric Public Registration Pass View
 * View: app/Views/public/events/pass_v2.php
 */
?>

<div class="pass-container card card-elevated" style="max-width: 600px; margin: 2rem auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08); text-align: center;">
  
  <!-- Pass Header Banner -->
  <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 2rem 1.5rem;">
    <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.4); margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem; border-radius: 20px;">
      CONFIRMED REGISTRATION PASS
    </span>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem 0; color: #fff;">
      <?= e($event['title']) ?>
    </h1>
    <p style="margin: 0; font-size: 0.95rem; opacity: 0.9;">
      <?= date('F j, Y', strtotime($event['start_time'])) ?> &bull; <?= date('g:i A', strtotime($event['start_time'])) ?>
    </p>
  </div>

  <div class="card-body" style="padding: 2rem;">
    <!-- Pass QR Code -->
    <div class="pass-qr-box" style="margin: 0 auto 1.5rem auto; width: 220px; height: 220px; background: #fff; padding: 10px; border: 2px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
      <?= $qrSvg ?>
    </div>

    <!-- Pass Code Pill -->
    <div style="margin-bottom: 1.5rem;">
      <span style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
        PASS IDENTIFIER CODE
      </span>
      <span style="display: inline-block; font-family: monospace; font-size: 1.4rem; font-weight: 700; letter-spacing: 0.1em; color: #0f172a; background: #f8fafc; padding: 0.4rem 1.25rem; border-radius: 8px; border: 1px dashed #cbd5e1;">
        <?= e($registration['registration_code']) ?>
      </span>
    </div>

    <!-- Attendee Details -->
    <div style="background: #f8fafc; border-radius: 12px; padding: 1.25rem; text-align: left; margin-bottom: 1.5rem; font-size: 0.95rem;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
        <span style="color: #64748b;">Attendee Name:</span>
        <strong style="color: #1e293b;"><?= e($participant['full_name'] ?? 'Attendee') ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
        <span style="color: #64748b;">WhatsApp / Phone:</span>
        <strong style="color: #1e293b;"><?= e($registration['phone_normalized'] ?? ($participant['phone'] ?? 'N/A')) ?></strong>
      </div>
      <?php if (!empty($event['venue_name'])): ?>
        <div style="display: flex; justify-content: space-between;">
          <span style="color: #64748b;">Venue:</span>
          <strong style="color: #1e293b;"><?= e($event['venue_name']) ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <!-- WhatsApp Group Redirection Banner -->
    <?php if (!empty($whatsappUrl)): ?>
      <div id="whatsappCard" style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem;">
          <span style="font-size: 1.3rem;">💬</span>
          <h4 style="margin: 0; color: #065f46; font-weight: 700; font-size: 1.05rem;">
            Official Event WhatsApp Group
          </h4>
        </div>
        <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #047857;">
          Join the WhatsApp group for event announcements, resource materials, and live coordination.
        </p>
        
        <?php if ($whatsappAuto): ?>
          <div style="margin-bottom: 0.75rem; font-size: 0.9rem; color: #065f46;">
            Redirecting automatically in <strong id="waCountdown" style="font-size: 1.1rem; color: #047857;"><?= (int) $whatsappCountdown ?></strong> seconds...
          </div>
        <?php endif; ?>

        <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
          <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #10b981; color: #fff; font-weight: 600; padding: 0.65rem 1.25rem; border-radius: 8px; text-decoration: none;">
            Join WhatsApp Group &rarr;
          </a>
          <?php if ($whatsappAuto): ?>
            <button type="button" id="cancelWaRedirectBtn" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.65rem 1rem; border-radius: 8px;">
              Stay on Pass Page
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Actions -->
    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
      <button type="button" onclick="window.print()" class="btn btn-secondary" style="padding: 0.65rem 1.25rem; border-radius: 8px;">
        🖨️ Print Pass
      </button>
      <a href="<?= e(url('/check-in/' . $event['slug'])) ?>" class="btn btn-primary" style="padding: 0.65rem 1.25rem; border-radius: 8px; text-decoration: none;">
        Go to Check-in Portal &rarr;
      </a>
    </div>
  </div>
</div>

<?php if (!empty($whatsappUrl) && $whatsappAuto): ?>
<script>
  (function() {
    var seconds = <?= (int) $whatsappCountdown ?>;
    var targetUrl = <?= json_encode($whatsappUrl) ?>;
    var countdownEl = document.getElementById('waCountdown');
    var cancelBtn = document.getElementById('cancelWaRedirectBtn');
    var timer = null;

    if (seconds > 0 && countdownEl) {
      timer = setInterval(function() {
        seconds--;
        countdownEl.textContent = seconds;
        if (seconds <= 0) {
          clearInterval(timer);
          window.location.href = targetUrl;
        }
      }, 1000);
    }

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function() {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
        var waCard = document.getElementById('whatsappCard');
        if (waCard) {
          var notice = waCard.querySelector('div[style*="Redirecting automatically"]');
          if (notice) notice.style.display = 'none';
          cancelBtn.style.display = 'none';
        }
      });
    }
  })();
</script>
<?php endif; ?>
