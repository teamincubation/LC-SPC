<?php
  $status = $event['status'] ?? 'published';
  $statusBadge = match ($status) {
    'published' => 'badge-primary',
    'ongoing'   => 'badge-success',
    'completed' => 'badge-secondary',
    default     => 'badge-light',
  };
  $confirmed = (int) ($metrics['confirmed'] ?? 0);
  $attended = (int) ($metrics['attended'] ?? 0);
  $remaining = max(0, $confirmed - $attended);
  $turnout = (float) ($metrics['turnout_percentage'] ?? 0.0);
?>

<!-- Console Header -->
<div class="admin-page-header">
  <div class="admin-page-header-title">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/checkin')) ?>" class="btn btn-outline btn-sm btn-icon" title="Check-In Events" aria-label="Check-In Events">
        <?= icon('arrow-left', ['width' => '14', 'height' => '14']) ?>
      </a>
      <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
          <h1 style="margin: 0; font-size: var(--font-size-xl);"><?= e($event['title']) ?></h1>
          <span class="badge badge-pill <?= e($statusBadge) ?>" style="text-transform: uppercase; font-size: var(--font-size-xs);">
            <span class="badge-dot" aria-hidden="true"></span>
            <?= e($status) ?>
          </span>
          <span id="net-status-badge" class="badge badge-pill badge-success" style="font-size: var(--font-size-xs);">
            <span class="badge-dot" aria-hidden="true"></span> Online
          </span>
        </div>
        <p style="margin-top: 0.25rem;">
          <?= e(date('M d, Y', strtotime((string) $event['start_time']))) ?> &bull;
          <?= e(date('h:i A', strtotime((string) $event['start_time']))) ?> &ndash; <?= e(date('h:i A', strtotime((string) $event['end_time']))) ?>
          <?php if (!empty($event['venue_name'])): ?>
            &bull; <?= e($event['venue_name']) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>

  <div class="admin-page-header-actions">
    <!-- Sound Toggle Button (Enabled by Default) -->
    <button type="button" id="sound-toggle-btn" class="btn btn-outline btn-sm" aria-label="Toggle scan sound feedback">
      <span id="sound-icon"><?= icon('volume-2', ['width' => '14', 'height' => '14']) ?></span>
      <span id="sound-text">Sound: ON</span>
    </button>
    <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="btn btn-outline btn-sm">
      <?= icon('users', ['width' => '14', 'height' => '14']) ?>
      <span>Roster</span>
    </a>
  </div>
</div>

<!-- Operational Window Notification (if applicable) -->
<?php if (!$isWithinWindow): ?>
  <div class="alert alert-warning" style="margin-bottom: 1.25rem; font-size: var(--font-size-xs); padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
    <div style="color: var(--color-warning); display: flex; align-items: center;">
      <?= icon('alert-triangle', ['width' => '16', 'height' => '16']) ?>
    </div>
    <div>
      <strong>Timing Notice:</strong> Event is outside the standard check-in window (2h before start &ndash; 4h after end).
      <?php if ($isCoordinator): ?>
        Coordinators can check in attendees with a mandatory operational reason.
      <?php else: ?>
        Desk check-in is restricted to Program Coordinators outside this window.
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Live KPI Summary Bar -->
<section class="metric-grid mb-6" aria-label="Live Attendance Metrics">
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      <?= icon('users', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value" id="stat-confirmed"><?= e((string) $confirmed) ?></div>
    <div class="card-metric-label">Total Enrolled</div>
  </div>
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-success); color: var(--text-success);">
      <?= icon('check-circle', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value" id="stat-attended" style="color: var(--color-success);"><?= e((string) $attended) ?></div>
    <div class="card-metric-label">Attended</div>
  </div>
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-surface-subtle); color: var(--text-secondary);">
      <?= icon('clock', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value" id="stat-remaining"><?= e((string) $remaining) ?></div>
    <div class="card-metric-label">Remaining Expected</div>
  </div>
  <div class="card-metric">
    <div class="card-metric-icon" style="background-color: var(--bg-info); color: var(--text-info);">
      <?= icon('activity', ['width' => '18', 'height' => '18']) ?>
    </div>
    <div class="card-metric-value" id="stat-turnout" style="color: var(--color-primary);"><?= e((string) $turnout) ?>%</div>
    <div class="card-metric-label">Turnout Rate</div>
  </div>
</section>

<!-- Mode Navigation Tabs -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
  <button type="button" id="tab-btn-scanner" class="btn btn-primary btn-sm" onclick="switchConsoleTab('scanner')" style="display: inline-flex; align-items: center; gap: 0.4rem;">
    <?= icon('camera', ['width' => '14', 'height' => '14']) ?>
    <span>Camera Scanner</span>
  </button>
  <button type="button" id="tab-btn-manual" class="btn btn-outline btn-sm" onclick="switchConsoleTab('manual')" style="display: inline-flex; align-items: center; gap: 0.4rem;">
    <?= icon('search', ['width' => '14', 'height' => '14']) ?>
    <span>Manual Search (<?= e((string) count($attendees)) ?>)</span>
  </button>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: CAMERA SCANNER CONSOLE                                             -->
<!-- ========================================================================= -->
<div id="tab-content-scanner" style="display: block;">
  <div class="card" style="padding: 1.25rem; max-width: 600px; margin: 0 auto; text-align: center;">
    <!-- Camera Viewport with Viewfinder Reticle -->
    <div style="position: relative; width: 100%; max-width: 440px; margin: 0 auto 1rem auto; background: #000; border-radius: 10px; overflow: hidden; min-height: 280px; display: flex; align-items: center; justify-content: center;">
      <video id="scanner-video" playsinline autoplay muted style="width: 100%; height: 100%; object-fit: cover; display: block;"></video>
      <canvas id="scanner-canvas" style="display: none;"></canvas>

      <!-- Viewfinder Overlay Box -->
      <div id="viewfinder-overlay" style="position: absolute; width: 220px; height: 220px; border: 2px solid rgba(255,255,255,0.85); border-radius: 12px; pointer-events: none; box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);">
        <div style="position: absolute; top: -2px; left: -2px; width: 24px; height: 24px; border-top: 4px solid var(--color-primary); border-left: 4px solid var(--color-primary); border-top-left-radius: 12px;"></div>
        <div style="position: absolute; top: -2px; right: -2px; width: 24px; height: 24px; border-top: 4px solid var(--color-primary); border-right: 4px solid var(--color-primary); border-top-right-radius: 12px;"></div>
        <div style="position: absolute; bottom: -2px; left: -2px; width: 24px; height: 24px; border-bottom: 4px solid var(--color-primary); border-left: 4px solid var(--color-primary); border-bottom-left-radius: 12px;"></div>
        <div style="position: absolute; bottom: -2px; right: -2px; width: 24px; height: 24px; border-bottom: 4px solid var(--color-primary); border-right: 4px solid var(--color-primary); border-bottom-right-radius: 12px;"></div>
      </div>

      <!-- Camera Loading / Prompt Placeholder -->
      <div id="camera-loading" style="position: absolute; color: #fff; font-size: var(--font-size-sm); padding: 1rem; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
          <?= icon('camera', ['width' => '16', 'height' => '16']) ?>
          <span>Camera Initializing...</span>
        </div>
        <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 0.25rem;">Please allow camera permission if prompted.</div>
      </div>
    </div>

    <!-- Scanner Controls -->
    <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
      <button type="button" id="switch-camera-btn" class="btn btn-outline btn-sm" style="font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.35rem;">
        <?= icon('refresh-cw', ['width' => '13', 'height' => '13']) ?>
        <span>Switch Camera</span>
      </button>
      <button type="button" id="restart-scanner-btn" class="btn btn-outline btn-sm" style="font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.35rem;">
        <?= icon('play', ['width' => '13', 'height' => '13']) ?>
        <span>Resume Scanner</span>
      </button>
    </div>

    <!-- Live Scan Result Flash Card Container -->
    <div id="scan-result-card" style="display: none; padding: 1rem; border-radius: 8px; margin-bottom: 1.25rem; text-align: left;">
      <!-- Populated dynamically via JS -->
    </div>

    <!-- Quick Direct Code Entry Bar -->
    <div style="border-top: 1px dashed var(--border-color); padding-top: 1rem;">
      <form id="direct-code-form" onsubmit="handleDirectCodeSubmit(event)" style="display: flex; gap: 0.5rem; justify-content: center;">
        <input type="text" id="direct-code-input" class="form-input" placeholder="e.g. REG-26-8A7D3 or paste URL" style="max-width: 300px; text-transform: uppercase; font-family: monospace; font-weight: bold;" required>
        <button type="submit" class="btn btn-primary btn-sm">
          <?= icon('check', ['width' => '13', 'height' => '13']) ?>
          <span>Verify Pass</span>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- TAB 2: MANUAL SEARCH ATTENDEE LIST                                        -->
<!-- ========================================================================= -->
<div id="tab-content-manual" style="display: none;">
  <div class="card" style="padding: 1.25rem;">
    <!-- Live Search Input -->
    <div style="margin-bottom: 1.25rem;">
      <input type="text" id="roster-search-input" class="form-input" placeholder="Search attendee by name, pass code, or category..." oninput="filterRoster(this.value)">
    </div>

    <!-- Attendee List -->
    <div id="roster-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
      <?php if (empty($attendees)): ?>
        <p class="text-secondary" style="text-align: center; padding: 2rem;">No confirmed registrations found for this event.</p>
      <?php else: ?>
        <?php foreach ($attendees as $att): ?>
          <?php
            $isAttended = ($att['attendance_status'] === 'attended');
            $cardBg = $isAttended ? 'var(--bg-surface-subtle)' : 'var(--bg-surface)';
            $badge = match ($att['attendance_status']) {
              'attended' => 'badge-success',
              'absent'   => 'badge-danger',
              'excused'  => 'badge-warning',
              default    => 'badge-neutral',
            };
          ?>
          <div class="roster-item card" data-search="<?= e(strtolower($att['participant_name'] . ' ' . $att['registration_code'] . ' ' . $att['participant_category'])) ?>" style="padding: 0.9rem; background: <?= $cardBg ?>; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <strong style="font-size: var(--font-size-md); color: var(--text-primary);"><?= e($att['participant_name']) ?></strong>
                <span class="badge badge-pill <?= e($badge) ?>" id="status-badge-<?= e((string) $att['id']) ?>" style="font-size: 0.7rem; text-transform: uppercase;">
                  <span class="badge-dot" aria-hidden="true"></span>
                  <?= e($att['attendance_status']) ?>
                </span>
                <span class="badge badge-pill badge-neutral" style="font-size: 0.7rem; text-transform: capitalize;">
                  <?= e($att['participant_category'] ?? 'general') ?>
                </span>
              </div>
              <div class="text-secondary" style="font-size: var(--font-size-xs); margin-top: 0.25rem;">
                Code: <code style="font-weight: bold;"><?= e($att['registration_code']) ?></code>
                <?php if (!empty($att['participant_organization'])): ?>
                  &bull; <?= e($att['participant_organization']) ?>
                <?php endif; ?>
                <?php if (!empty($att['checked_in_at'])): ?>
                  &bull; <span style="color: var(--color-success); display: inline-flex; align-items: center; gap: 0.25rem;">
                    <?= icon('check', ['width' => '12', 'height' => '12']) ?>
                    <span>Checked in <?= e(date('h:i A', strtotime((string) $att['checked_in_at']))) ?></span>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div id="btn-container-<?= e((string) $att['id']) ?>">
              <?php if (!$isAttended): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="checkInByCode('<?= e($att['registration_code']) ?>', 'admin_manual')" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                  <?= icon('check', ['width' => '13', 'height' => '13']) ?>
                  <span>Check In</span>
                </button>
              <?php else: ?>
                <span class="badge badge-pill badge-success" style="font-size: var(--font-size-xs);">
                  <span class="badge-dot" aria-hidden="true"></span> Verified
                </span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- OUT-OF-WINDOW OVERRIDE MODAL                                              -->
<!-- ========================================================================= -->
<div id="override-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="max-width: 480px; width: 100%; padding: 1.5rem; background: var(--bg-surface); box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
    <h3 style="margin-top: 0; font-size: var(--font-size-lg);">Timing Window Override</h3>
    <p class="text-secondary" style="font-size: var(--font-size-xs); margin-bottom: 1rem;">
      This event is currently outside the standard operational window. As a Program Coordinator, you may check in this attendee by providing a mandatory operational reason.
    </p>
    <div style="margin-bottom: 1rem;">
      <label for="override-reason-input" class="form-label">Operational Reason (Non-sensitive logistics only)</label>
      <input type="text" id="override-reason-input" class="form-input" placeholder="e.g. Reconciling physical sign-in sheet" maxlength="255" required>
      <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.25rem;">Clinical, medical, or counselling content is strictly prohibited.</div>
    </div>
    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeOverrideModal()">Cancel</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="submitPendingOverride()">Authorize Check-In</button>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: CONSOLE ENGINE (AUDIO, HAPTIC, CAMERA STREAM, RATE LIMIT)     -->
<!-- ========================================================================= -->
<script>
  // Runtime State
  const EVENT_ID = <?= (int) $event['id'] ?>;
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const VERIFY_URL = '<?= e(url('/admin/checkin/verify')) ?>';
  const IS_COORDINATOR = <?= $isCoordinator ? 'true' : 'false' ?>;

  let soundEnabled = (localStorage.getItem('lc_checkin_sound') !== 'false'); // Default: ON
  let activeStream = null;
  let currentFacingMode = 'environment';
  let isScanningActive = true;
  let pendingOverrideCode = null;
  let scanCooldown = false;

  // Initialize UI on load
  document.addEventListener('DOMContentLoaded', () => {
    updateSoundUI();
    initNetworkMonitor();
    startCameraScanner();

    document.getElementById('switch-camera-btn')?.addEventListener('click', toggleCamera);
    document.getElementById('restart-scanner-btn')?.addEventListener('click', resumeScanner);
    document.getElementById('sound-toggle-btn')?.addEventListener('click', toggleSound);
  });

  // Sound Feedback Toggle
  function toggleSound() {
    soundEnabled = !soundEnabled;
    localStorage.setItem('lc_checkin_sound', soundEnabled ? 'true' : 'false');
    updateSoundUI();
  }

  function updateSoundUI() {
    const icon = document.getElementById('sound-icon');
    const text = document.getElementById('sound-text');
    if (icon && text) {
      if (soundEnabled) {
        icon.innerHTML = '<svg class="svg-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>';
        text.innerText = 'Sound: ON';
      } else {
        icon.innerHTML = '<svg class="svg-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>';
        text.innerText = 'Sound: MUTED';
      }
    }
  }

  // Audio & Haptic Sensory Feedback (Non-blocking: Failure never blocks check-in)
  function playFeedback(type = 'success') {
    // 1. Audio chime
    if (soundEnabled) {
      try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();

        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
          // Clean 880Hz (A5) pleasant chime
          osc.type = 'sine';
          osc.frequency.setValueAtTime(880, audioCtx.currentTime);
          gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15);
          osc.start();
          osc.stop(audioCtx.currentTime + 0.15);
        } else if (type === 'duplicate') {
          // Double chirp
          osc.type = 'triangle';
          osc.frequency.setValueAtTime(660, audioCtx.currentTime);
          gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.18);
          osc.start();
          osc.stop(audioCtx.currentTime + 0.18);
        } else {
          // Low 220Hz buzz for error / rejection
          osc.type = 'sawtooth';
          osc.frequency.setValueAtTime(220, audioCtx.currentTime);
          gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);
          osc.start();
          osc.stop(audioCtx.currentTime + 0.25);
        }
      } catch (e) {
        // AudioContext blocked or not supported - gracefully ignore
      }
    }

    // 2. Haptic vibration
    try {
      if (navigator.vibrate) {
        if (type === 'success') {
          navigator.vibrate(100);
        } else if (type === 'duplicate') {
          navigator.vibrate([60, 60, 60]);
        } else {
          navigator.vibrate([150, 80, 150]);
        }
      }
    } catch (e) {
      // Haptics not supported - gracefully ignore
    }
  }

  // Network Connectivity Monitor
  function initNetworkMonitor() {
    const badge = document.getElementById('net-status-badge');
    const updateStatus = () => {
      if (!badge) return;
      if (navigator.onLine) {
        badge.className = 'badge badge-pill badge-success';
        badge.innerHTML = '<span class="badge-dot" aria-hidden="true"></span> Online';
      } else {
        badge.className = 'badge badge-pill badge-warning';
        badge.innerHTML = '<span class="badge-dot" aria-hidden="true"></span> Network Paused';
      }
    };
    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
  }

  // Tab Switcher
  function switchConsoleTab(tab) {
    const scannerTab = document.getElementById('tab-content-scanner');
    const manualTab = document.getElementById('tab-content-manual');
    const scannerBtn = document.getElementById('tab-btn-scanner');
    const manualBtn = document.getElementById('tab-btn-manual');

    if (tab === 'scanner') {
      if (scannerTab) scannerTab.style.display = 'block';
      if (manualTab) manualTab.style.display = 'none';
      if (scannerBtn) { scannerBtn.className = 'btn btn-primary btn-sm'; }
      if (manualBtn) { manualBtn.className = 'btn btn-outline btn-sm'; }
      startCameraScanner();
    } else {
      if (scannerTab) scannerTab.style.display = 'none';
      if (manualTab) manualTab.style.display = 'block';
      if (scannerBtn) { scannerBtn.className = 'btn btn-outline btn-sm'; }
      if (manualBtn) { manualBtn.className = 'btn btn-primary btn-sm'; }
      stopCameraScanner();
    }
  }

  // Camera Management
  async function startCameraScanner() {
    const video = document.getElementById('scanner-video');
    const loading = document.getElementById('camera-loading');
    if (!video) return;

    try {
      if (activeStream) {
        activeStream.getTracks().forEach(track => track.stop());
      }

      const constraints = {
        video: {
          facingMode: currentFacingMode,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      };

      activeStream = await navigator.mediaDevices.getUserMedia(constraints);
      video.srcObject = activeStream;
      video.setAttribute('playsinline', 'true');
      await video.play();

      if (loading) loading.style.display = 'none';
      isScanningActive = true;
      requestAnimationFrame(scanVideoFrame);
    } catch (err) {
      if (loading) {
        loading.innerHTML = '<div style="color:#ef4444; font-weight: 600;">Camera Unavailable</div><div style="font-size:0.75rem;margin-top:0.25rem;">Please check permissions or use manual entry.</div>';
      }
    }
  }

  function stopCameraScanner() {
    isScanningActive = false;
    if (activeStream) {
      activeStream.getTracks().forEach(track => track.stop());
      activeStream = null;
    }
  }

  function toggleCamera() {
    currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
    startCameraScanner();
  }

  function resumeScanner() {
    scanCooldown = false;
    isScanningActive = true;
    const resultCard = document.getElementById('scan-result-card');
    if (resultCard) resultCard.style.display = 'none';
    requestAnimationFrame(scanVideoFrame);
  }

  // Scan Video Frame using BarcodeDetector if available
  async function scanVideoFrame() {
    if (!isScanningActive || scanCooldown) return;

    const video = document.getElementById('scanner-video');
    if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) {
      if (isScanningActive) requestAnimationFrame(scanVideoFrame);
      return;
    }

    if ('BarcodeDetector' in window) {
      try {
        const detector = new BarcodeDetector({ formats: ['qr_code', 'code_128'] });
        const barcodes = await detector.detect(video);
        if (barcodes.length > 0) {
          const rawVal = barcodes[0].rawValue;
          if (rawVal) {
            handleScannedCode(rawVal, 'qr_scan');
            return;
          }
        }
      } catch (e) {
        // Fallback or detector error
      }
    }

    if (isScanningActive) {
      requestAnimationFrame(scanVideoFrame);
    }
  }

  // Handle Scanned Raw String
  function handleScannedCode(rawString, method = 'qr_scan') {
    if (scanCooldown) return;
    scanCooldown = true;
    checkInByCode(rawString, method);
  }

  // Direct Code Entry Handler
  function handleDirectCodeSubmit(e) {
    e.preventDefault();
    const input = document.getElementById('direct-code-input');
    if (!input || !input.value.trim()) return;
    checkInByCode(input.value.trim(), 'admin_manual');
    input.value = '';
  }

  // Core Verification AJAX Call
  async function checkInByCode(codeOrUrl, method = 'qr_scan', overrideReason = null) {
    const card = document.getElementById('scan-result-card');

    try {
      const response = await fetch(VERIFY_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF_TOKEN,
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          event_id: EVENT_ID,
          code: codeOrUrl,
          method: method,
          override_reason: overrideReason
        })
      });

      const data = await response.json();

      if (response.ok && data.success) {
        if (data.already_checked_in) {
          // Idempotent Repeat Scan
          playFeedback('duplicate');
          showResultCard('info', data.message || 'Already verified', data);
        } else {
          // Successful First-Time Check-In
          playFeedback('success');
          showResultCard('success', data.message || 'Check-in confirmed', data);
          incrementAttendedMetrics();
          updateRosterItemState(data);
        }

        // Auto-resume scanner after 3.5 seconds
        setTimeout(() => {
          scanCooldown = false;
          if (isScanningActive) requestAnimationFrame(scanVideoFrame);
        }, 3500);
      } else {
        // Handle out-of-window check-in that requires coordinator override
        if (response.status === 403 && data.error && data.error.includes('operational window') && IS_COORDINATOR) {
          pendingOverrideCode = codeOrUrl;
          openOverrideModal();
          return;
        }

        playFeedback('error');
        showResultCard('error', data.error || 'Check-in rejected', data);

        setTimeout(() => {
          scanCooldown = false;
          if (isScanningActive) requestAnimationFrame(scanVideoFrame);
        }, 3500);
      }
    } catch (err) {
      playFeedback('error');
      showResultCard('error', 'Network error. Please check your connection and try again.');
      setTimeout(() => {
        scanCooldown = false;
        if (isScanningActive) requestAnimationFrame(scanVideoFrame);
      }, 3000);
    }
  }

  // Render Result Flash Card
  function showResultCard(type, message, data = null) {
    const card = document.getElementById('scan-result-card');
    if (!card) return;

    card.style.display = 'block';

    let borderCol = 'var(--color-primary)';
    let bgCol = 'var(--bg-surface-subtle)';
    let iconSvg = '<svg class="svg-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>';

    if (type === 'success') {
      borderCol = 'var(--color-success)';
      bgCol = 'var(--bg-surface-subtle)';
      iconSvg = '<svg class="svg-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
    } else if (type === 'info') {
      borderCol = 'var(--color-info)';
      bgCol = 'var(--bg-surface-subtle)';
      iconSvg = '<svg class="svg-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-info)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
    } else {
      borderCol = 'var(--color-danger)';
      bgCol = 'var(--bg-surface-subtle)';
      iconSvg = '<svg class="svg-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-danger)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
    }

    card.style.border = `2px solid ${borderCol}`;
    card.style.backgroundColor = bgCol;

    let html = `<div style="display:flex;align-items:center;gap:0.5rem;font-weight:bold;color:${borderCol};margin-bottom:0.25rem;">
      <span style="display:inline-flex;align-items:center;">${iconSvg}</span> <span>${escapeHtml(message)}</span>
    </div>`;

    if (data && data.attendee_name) {
      html += `<div style="font-size:1rem;font-weight:bold;color:var(--text-primary);margin-top:0.25rem;">${escapeHtml(data.attendee_name)}</div>`;
      html += `<div class="text-secondary" style="font-size:0.75rem;margin-top:0.15rem;">
        Pass: <code>${escapeHtml(data.masked_code || '')}</code> &bull;
        Category: <span style="text-transform:capitalize;">${escapeHtml(data.category || 'General')}</span>
      </div>`;
    }

    card.innerHTML = html;
  }

  // UI Helper: Update Stats Dynamically
  function incrementAttendedMetrics() {
    const attEl = document.getElementById('stat-attended');
    const remEl = document.getElementById('stat-remaining');
    const totEl = document.getElementById('stat-confirmed');
    const trnEl = document.getElementById('stat-turnout');

    if (attEl && remEl && totEl && trnEl) {
      let attended = parseInt(attEl.innerText || '0', 10) + 1;
      let total = parseInt(totEl.innerText || '0', 10);
      let remaining = Math.max(0, total - attended);
      let turnout = total > 0 ? ((attended / total) * 100).toFixed(1) : '0.0';

      attEl.innerText = attended;
      remEl.innerText = remaining;
      trnEl.innerText = turnout + '%';
    }
  }

  // UI Helper: Update Manual Roster Item Dynamically
  function updateRosterItemState(data) {
    const items = document.querySelectorAll('.roster-item');
    items.forEach(item => {
      const codeEl = item.querySelector('code');
      if (codeEl && data.masked_code && codeEl.innerText.trim().endsWith(data.masked_code.slice(-5))) {
        item.style.backgroundColor = 'var(--bg-surface-subtle)';
        const badge = item.querySelector('.badge');
        if (badge) {
          badge.className = 'badge badge-success';
          badge.innerText = 'attended';
        }
      }
    });
  }

  // Manual Roster Search Filter
  function filterRoster(query) {
    const q = query.trim().toLowerCase();
    const items = document.querySelectorAll('.roster-item');
    items.forEach(item => {
      const searchData = item.getAttribute('data-search') || '';
      item.style.display = searchData.includes(q) ? 'flex' : 'none';
    });
  }

  // Override Modal Handlers
  function openOverrideModal() {
    const modal = document.getElementById('override-modal');
    if (modal) modal.style.display = 'flex';
  }

  function closeOverrideModal() {
    const modal = document.getElementById('override-modal');
    if (modal) modal.style.display = 'none';
    pendingOverrideCode = null;
    scanCooldown = false;
    if (isScanningActive) requestAnimationFrame(scanVideoFrame);
  }

  function submitPendingOverride() {
    const input = document.getElementById('override-reason-input');
    const reason = input ? input.value.trim() : '';

    if (!reason) {
      alert('Please enter an operational reason.');
      return;
    }

    const code = pendingOverrideCode;
    closeOverrideModal();
    if (code) {
      checkInByCode(code, 'admin_manual', reason);
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
</script>
