<?php
/**
 * Check-in Confirmation View
 * View: app/Views/public/checkin/success.php
 */
?>

<div class="checkin-success-card card card-elevated" style="max-width: 580px; margin: 2rem auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08); text-align: center;">
  
  <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 2.5rem 1.5rem;">
    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem auto; font-size: 2.2rem;">
      ✓
    </div>
    <h1 style="font-size: 1.6rem; font-weight: 700; margin: 0 0 0.5rem 0; color: #fff;">
      <?= $already ? 'Attendance Already Confirmed' : 'Check-in Confirmed!' ?>
    </h1>
    <p style="margin: 0; font-size: 1rem; opacity: 0.95;">
      Welcome to <?= e($event['title']) ?>
    </p>
  </div>

  <div class="card-body" style="padding: 2rem;">
    <div style="background: #f8fafc; border-radius: 12px; padding: 1.5rem; text-align: left; margin-bottom: 1.5rem;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.65rem;">
        <span style="color: #64748b;">Participant Name:</span>
        <strong style="color: #0f172a;"><?= e($registration['full_name'] ?? 'Participant') ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.65rem;">
        <span style="color: #64748b;">Registration Code:</span>
        <span style="font-family: monospace; font-weight: 700; color: #0284c7;"><?= e($registration['registration_code']) ?></span>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.65rem;">
        <span style="color: #64748b;">Status:</span>
        <span class="badge badge-success" style="background: #10b981; color: #fff; padding: 0.25rem 0.65rem; border-radius: 6px; font-weight: 600;">
          Checked In / Present
        </span>
      </div>
      <div style="display: flex; justify-content: space-between;">
        <span style="color: #64748b;">Check-in Timestamp:</span>
        <strong style="color: #0f172a;"><?= date('M j, Y g:i A', strtotime($attendedAt)) ?></strong>
      </div>
      <?php if (!empty($distance)): ?>
        <div style="display: flex; justify-content: space-between; margin-top: 0.65rem; padding-top: 0.65rem; border-top: 1px dashed #e2e8f0;">
          <span style="color: #64748b;">Venue Distance:</span>
          <span style="color: #059669; font-weight: 600;"><?= round((float) $distance, 1) ?> meters</span>
        </div>
      <?php endif; ?>
    </div>

    <p style="color: #475569; font-size: 0.95rem; margin-bottom: 1.5rem;">
      Your attendance has been recorded for this event. After the session concludes, your certificate of participation will be unlocked and available for download.
    </p>

    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
      <a href="<?= e(url('/register/pass/' . $registration['registration_code'])) ?>" class="btn btn-secondary">
        View My Pass &rarr;
      </a>
      <a href="<?= e(url('/')) ?>" class="btn btn-primary">
        Back to Home
      </a>
    </div>
  </div>
</div>
