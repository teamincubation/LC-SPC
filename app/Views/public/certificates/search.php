<?php

declare(strict_types=1);

/**
 * Public Certificate Search Portal View (Mobile-First Redesign)
 */
?>

<div class="cert-portal-card" style="max-width: 680px; margin: 1.5rem auto;">
  <!-- Header & Institutional Branding -->
  <header class="cert-portal-header">
    <div class="cert-portal-badge">
      <?= icon('award', ['style' => 'width: 14px; height: 14px;']) ?>
      <span>Official Credential Verification</span>
    </div>
    <h1 class="cert-portal-title">
      Find Your Certificate
    </h1>
    <p class="cert-portal-subtitle">
      Verify authenticity and download high-resolution certificates issued by Listening Community.
    </p>
  </header>

  <div class="cert-portal-body">
    <!-- Segmented Navigation Tabs (Phone Search vs Certificate ID Search) -->
    <div class="cert-tabs-nav" role="tablist" aria-label="Certificate Search Mode">
      <button 
        type="button" 
        id="tabPhone" 
        role="tab" 
        aria-selected="<?= $mode === 'phone' ? 'true' : 'false' ?>" 
        aria-controls="formPhone" 
        tabindex="<?= $mode === 'phone' ? '0' : '-1' ?>" 
        class="cert-tab-btn <?= $mode === 'phone' ? 'active' : '' ?>"
      >
        <?= icon('phone') ?>
        <span>Search by Phone</span>
      </button>
      <button 
        type="button" 
        id="tabCertId" 
        role="tab" 
        aria-selected="<?= $mode === 'cert_id' ? 'true' : 'false' ?>" 
        aria-controls="formCertId" 
        tabindex="<?= $mode === 'cert_id' ? '0' : '-1' ?>" 
        class="cert-tab-btn <?= $mode === 'cert_id' ? 'active' : '' ?>"
      >
        <?= icon('hash') ?>
        <span>Search by Certificate ID</span>
      </button>
    </div>

    <!-- Phone Search Form Panel -->
    <form 
      id="formPhone" 
      role="tabpanel" 
      aria-labelledby="tabPhone" 
      method="GET" 
      action="<?= e(url('/certificates')) ?>" 
      style="display: <?= $mode === 'phone' ? 'block' : 'none' ?>;"
      class="cert-search-form"
    >
      <div class="cert-form-group">
        <label for="phoneInput" class="cert-form-label">Registered Mobile Number</label>
        <div class="cert-input-wrapper">
          <span class="cert-input-icon" aria-hidden="true"><?= icon('phone') ?></span>
          <input 
            type="tel" 
            id="phoneInput" 
            name="phone" 
            class="cert-input" 
            placeholder="e.g. +91 98765 43210 or 9876543210" 
            value="<?= e($phoneInput) ?>" 
            required
            autocomplete="tel"
            inputmode="tel"
            aria-describedby="phoneHelp"
          >
        </div>
        <div id="phoneHelp" class="cert-help-text">
          Enter the mobile number provided during program registration.
        </div>
      </div>

      <button type="submit" id="btnSubmitPhone" class="cert-btn-primary">
        <?= icon('search') ?>
        <span class="btn-text">Find My Certificates</span>
      </button>
    </form>

    <!-- Certificate ID Form Panel -->
    <form 
      id="formCertId" 
      role="tabpanel" 
      aria-labelledby="tabCertId" 
      method="GET" 
      action="<?= e(url('/certificates')) ?>" 
      style="display: <?= $mode === 'cert_id' ? 'block' : 'none' ?>;"
      class="cert-search-form"
    >
      <div class="cert-form-group">
        <label for="certIdInput" class="cert-form-label">Official Certificate ID</label>
        <div class="cert-input-wrapper">
          <span class="cert-input-icon" aria-hidden="true"><?= icon('hash') ?></span>
          <input 
            type="text" 
            id="certIdInput" 
            name="certificate_id" 
            class="cert-input cert-input-mono" 
            placeholder="e.g. CERT-2026-AB3X9K7M" 
            value="<?= e($certIdInput) ?>" 
            required
            autocomplete="off"
            spellcheck="false"
            aria-describedby="certIdHelp"
          >
        </div>
        <div id="certIdHelp" class="cert-help-text">
          Enter the exact alphanumeric Certificate ID printed on your document.
        </div>
      </div>

      <button type="submit" id="btnSubmitCertId" class="cert-btn-primary">
        <?= icon('search') ?>
        <span class="btn-text">Verify Certificate ID</span>
      </button>
    </form>

    <!-- Live Status Announcement for Accessibility -->
    <div id="searchLiveRegion" class="sr-only" aria-live="polite"></div>

    <!-- Search Results / Feedback Display -->
    <?php if ($searched): ?>
      <section class="cert-results-section" aria-label="Search Results">
        <?php if (!empty($errorMessage)): ?>
          <!-- No Results / User Reassurance State -->
          <div class="cert-empty-state" role="alert">
            <div class="cert-empty-icon" aria-hidden="true">
              <?= icon('alert-circle') ?>
            </div>
            <div class="cert-empty-content">
              <h2 class="cert-empty-title"><?= e($errorMessage) ?></h2>
              <p class="cert-empty-desc">
                Please double check the entered phone number (including country code) or Certificate ID. If you recently attended, please allow a few minutes for generation to finalize.
              </p>
            </div>
          </div>
        <?php elseif (!empty($results)): ?>
          <!-- Matching Results List -->
          <h2 class="cert-results-title">
            <?= icon('check-circle', ['style' => 'color: #059669;']) ?>
            <span>Matching Verified Credentials (<?= count($results) ?>)</span>
          </h2>
          <div class="cert-results-list">
            <?php foreach ($results as $cert): ?>
              <article class="cert-result-card">
                <div class="cert-result-info">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                    <span class="cert-badge-active">
                      <?= icon('check-circle', ['style' => 'width: 12px; height: 12px;']) ?>
                      <span>Active</span>
                    </span>
                    <span class="cert-result-id"><?= e($cert['certificate_id']) ?></span>
                  </div>
                  <h3 class="cert-result-name"><?= e($cert['recipient_name']) ?></h3>
                  <div class="cert-result-meta">
                    <span><?= icon('calendar', ['style' => 'width: 13px; height: 13px; display: inline-block; vertical-align: -1px;']) ?> Issued: <?= e($cert['issue_date']) ?></span>
                    <span>&bull;</span>
                    <span><?= e($cert['event_title']) ?></span>
                  </div>
                </div>
                <div class="cert-result-action">
                  <a href="<?= e(url('/certificates/verify/' . $cert['verification_token'])) ?>" class="cert-btn-verify-now" aria-label="View and verify certificate for <?= e($cert['recipient_name']) ?>">
                    <?= icon('award') ?>
                    <span>View &amp; Verify</span>
                  </a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>

  <!-- Trust Guarantee Sub-footer -->
  <footer class="cert-trust-footer">
    <div class="cert-trust-inner">
      <div style="display: flex; align-items: center; justify-content: center; gap: 0.35rem; font-weight: 600; color: #475569;">
        <?= icon('shield', ['style' => 'color: #059669; width: 15px; height: 15px;']) ?>
        <span>Institutional Certificate Verification System</span>
      </div>
      <div>
        Every credential is cryptographically protected with 256-bit hash validation and registered in the Listening Community SPC database.
      </div>
    </div>
  </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabPhone = document.getElementById('tabPhone');
  const tabCertId = document.getElementById('tabCertId');
  const formPhone = document.getElementById('formPhone');
  const formCertId = document.getElementById('formCertId');
  const phoneInput = document.getElementById('phoneInput');
  const certIdInput = document.getElementById('certIdInput');
  const liveRegion = document.getElementById('searchLiveRegion');

  function setMode(mode, focusInput = false) {
    if (mode === 'phone') {
      tabPhone.classList.add('active');
      tabPhone.setAttribute('aria-selected', 'true');
      tabPhone.setAttribute('tabindex', '0');
      tabCertId.classList.remove('active');
      tabCertId.setAttribute('aria-selected', 'false');
      tabCertId.setAttribute('tabindex', '-1');
      formPhone.style.display = 'block';
      formCertId.style.display = 'none';
      if (focusInput) phoneInput.focus();
    } else {
      tabCertId.classList.add('active');
      tabCertId.setAttribute('aria-selected', 'true');
      tabCertId.setAttribute('tabindex', '0');
      tabPhone.classList.remove('active');
      tabPhone.setAttribute('aria-selected', 'false');
      tabPhone.setAttribute('tabindex', '-1');
      formCertId.style.display = 'block';
      formPhone.style.display = 'none';
      if (focusInput) certIdInput.focus();
    }
  }

  tabPhone.addEventListener('click', () => setMode('phone', true));
  tabCertId.addEventListener('click', () => setMode('cert_id', true));

  // Keyboard arrow navigation between tabs (WAI-ARIA Tab pattern)
  const tabs = [tabPhone, tabCertId];
  tabs.forEach((tab, index) => {
    tab.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        e.preventDefault();
        const nextTab = tabs[(index + 1) % tabs.length];
        nextTab.click();
        nextTab.focus();
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault();
        const prevTab = tabs[(index - 1 + tabs.length) % tabs.length];
        prevTab.click();
        prevTab.focus();
      }
    });
  });

  // Loading state handling on form submissions
  function handleFormSubmit(form, btn) {
    form.addEventListener('submit', () => {
      btn.disabled = true;
      const btnText = btn.querySelector('.btn-text');
      if (btnText) {
        btnText.textContent = 'Searching credential registry...';
      }
      if (liveRegion) {
        liveRegion.textContent = 'Searching credential repository, please wait...';
      }
    });
  }

  const btnSubmitPhone = document.getElementById('btnSubmitPhone');
  if (formPhone && btnSubmitPhone) handleFormSubmit(formPhone, btnSubmitPhone);

  const btnSubmitCertId = document.getElementById('btnSubmitCertId');
  if (formCertId && btnSubmitCertId) handleFormSubmit(formCertId, btnSubmitCertId);
});
</script>
