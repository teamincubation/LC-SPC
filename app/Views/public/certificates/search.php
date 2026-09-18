<?php

declare(strict_types=1);

/**
 * Public Certificate Search Portal View
 */
?>

<div class="auth-card" style="max-width: 680px; margin: 2rem auto; width: 100%;">
  <div class="auth-card-header" style="text-align: center; padding: 2rem 1.5rem 1.5rem; border-bottom: 1px solid var(--border-color);">
    <div style="font-size: 2.5rem; color: var(--color-primary); margin-bottom: 0.5rem;">
      <?= icon('award') ?>
    </div>
    <h1 style="font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold); margin: 0 0 0.5rem 0; color: var(--text-primary);">
      Find Your Certificate
    </h1>
    <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
      Verify and download your official Listening Community participation credential.
    </p>
  </div>

  <div class="auth-card-body" style="padding: 1.5rem;">
    <!-- Tabs: Phone Search vs Certificate ID Search -->
    <div style="display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem;">
      <button type="button" id="tabPhone" class="btn" style="flex: 1; border: none; border-radius: 0; border-bottom: 2px solid <?= $mode === 'phone' ? 'var(--color-primary)' : 'transparent' ?>; background: none; color: <?= $mode === 'phone' ? 'var(--color-primary)' : 'var(--text-secondary)' ?>; font-weight: var(--font-weight-semibold); padding: 0.75rem;">
        <?= icon('phone') ?>
        <span>Search by Phone</span>
      </button>
      <button type="button" id="tabCertId" class="btn" style="flex: 1; border: none; border-radius: 0; border-bottom: 2px solid <?= $mode === 'cert_id' ? 'var(--color-primary)' : 'transparent' ?>; background: none; color: <?= $mode === 'cert_id' ? 'var(--color-primary)' : 'var(--text-secondary)' ?>; font-weight: var(--font-weight-semibold); padding: 0.75rem;">
        <?= icon('hash') ?>
        <span>Search by Certificate ID</span>
      </button>
    </div>

    <!-- Phone Search Form -->
    <form id="formPhone" method="GET" action="<?= e(url('/certificates')) ?>" style="display: <?= $mode === 'phone' ? 'block' : 'none' ?>;">
      <div class="form-group mb-4">
        <label for="phoneInput" class="form-label font-semibold">Phone Number</label>
        <div style="position: relative;">
          <input 
            type="tel" 
            id="phoneInput" 
            name="phone" 
            class="form-control" 
            placeholder="e.g. +91 98765 43210 or 9876543210" 
            value="<?= e($phoneInput) ?>" 
            required
            autocomplete="tel"
          >
        </div>
        <div class="form-text text-muted" style="font-size: var(--font-size-xs);">
          Enter the mobile number provided during participation.
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
        <?= icon('search') ?>
        <span>Find My Certificates</span>
      </button>
    </form>

    <!-- Certificate ID Form -->
    <form id="formCertId" method="GET" action="<?= e(url('/certificates')) ?>" style="display: <?= $mode === 'cert_id' ? 'block' : 'none' ?>;">
      <div class="form-group mb-4">
        <label for="certIdInput" class="form-label font-semibold">Certificate ID</label>
        <input 
          type="text" 
          id="certIdInput" 
          name="certificate_id" 
          class="form-control" 
          placeholder="e.g. CERT-2026-AB3X9K7M" 
          value="<?= e($certIdInput) ?>" 
          required
          style="font-family: var(--font-mono); text-transform: uppercase;"
        >
        <div class="form-text text-muted" style="font-size: var(--font-size-xs);">
          Enter the exact Certificate ID printed on your document.
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
        <?= icon('search') ?>
        <span>Verify Certificate</span>
      </button>
    </form>

    <!-- Results Display -->
    <?php if ($searched): ?>
      <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
        <?php if (!empty($errorMessage)): ?>
          <div class="alert alert-warning" style="margin-bottom: 0;">
            <div style="display: flex; gap: 0.5rem; align-items: center;">
              <?= icon('alert-circle') ?>
              <span><?= e($errorMessage) ?></span>
            </div>
          </div>
        <?php elseif (!empty($results)): ?>
          <h3 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 1rem;">
            Matching Certificates Found (<?= count($results) ?>):
          </h3>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($results as $cert): ?>
              <div class="card" style="border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: var(--bg-surface-subtle);">
                <div>
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span class="badge badge-success" style="font-size: 0.7rem;">Active</span>
                    <strong style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-primary);"><?= e($cert['certificate_id']) ?></strong>
                  </div>
                  <h4 style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); margin: 0 0 0.25rem 0;"><?= e($cert['recipient_name']) ?></h4>
                  <div class="text-secondary" style="font-size: var(--font-size-xs);">
                    <span><?= e($cert['event_title']) ?></span> &bull; <span>Issued: <?= e($cert['issue_date']) ?></span>
                  </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                  <a href="<?= e(url('/certificates/verify/' . $cert['verification_token'])) ?>" class="btn btn-primary btn-sm">
                    <?= icon('award') ?>
                    <span>View &amp; Verify</span>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabPhone = document.getElementById('tabPhone');
  const tabCertId = document.getElementById('tabCertId');
  const formPhone = document.getElementById('formPhone');
  const formCertId = document.getElementById('formCertId');

  tabPhone.addEventListener('click', () => {
    tabPhone.style.borderBottom = '2px solid var(--color-primary)';
    tabPhone.style.color = 'var(--color-primary)';
    tabCertId.style.borderBottom = '2px solid transparent';
    tabCertId.style.color = 'var(--text-secondary)';
    formPhone.style.display = 'block';
    formCertId.style.display = 'none';
  });

  tabCertId.addEventListener('click', () => {
    tabCertId.style.borderBottom = '2px solid var(--color-primary)';
    tabCertId.style.color = 'var(--color-primary)';
    tabPhone.style.borderBottom = '2px solid transparent';
    tabPhone.style.color = 'var(--text-secondary)';
    formCertId.style.display = 'block';
    formPhone.style.display = 'none';
  });
});
</script>
