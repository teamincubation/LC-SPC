<?php

declare(strict_types=1);

/**
 * Certificate Platform V3 - Global Certificate Settings View
 */
?>

<div class="page-header mb-6">
  <div>
    <h1 class="page-title">Certificate Settings</h1>
    <p class="text-secondary">Configure cryptographic ID generation, canvas rendering, output formats, and custom typography.</p>
  </div>
</div>

<form action="<?= e(url('/admin/certificate-settings')) ?>" method="POST" class="mb-8">
  <?= csrf_field() ?>

  <!-- Card 1: Certificate ID Generation & Entropy -->
  <div class="card mb-6">
    <div class="card-header">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span class="text-primary" aria-hidden="true"><?= icon('shield') ?></span>
        <h2 class="card-title" style="font-size: var(--font-size-lg); margin: 0;">Certificate ID Generation &amp; Security</h2>
      </div>
      <span class="badge <?= $entropyBits >= 40.0 ? 'badge-success' : 'badge-danger' ?>">
        Entropy: <?= e(number_format($entropyBits, 1)) ?> bits
        <?= $entropyBits >= 40.0 ? '(Secure)' : '(Insufficient)' ?>
      </span>
    </div>
    <div class="card-body">
      <p class="text-secondary mb-4" style="font-size: var(--font-size-sm);">
        Certificate IDs are non-sequential, non-predictable public identifiers generated using cryptographically secure CSPRNG (<code>random_bytes()</code>).
        A minimum of <strong>40.0 bits of entropy</strong> is strictly enforced by the system to prevent guessing and enumeration.
      </p>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;" class="mb-4">
        <div class="form-group">
          <label for="cert_id_prefix" class="form-label">ID Prefix</label>
          <input type="text" id="cert_id_prefix" name="cert_id_prefix" class="form-control" value="<?= e($settings['cert_id_prefix'] ?? 'LC') ?>" placeholder="e.g. LC or LC-SPC" maxlength="15">
          <span class="text-muted" style="font-size: var(--font-size-xs);">Institutional prefix placed at the start.</span>
        </div>

        <div class="form-group">
          <label for="cert_id_separator" class="form-label">Segment Separator</label>
          <input type="text" id="cert_id_separator" name="cert_id_separator" class="form-control" value="<?= e($settings['cert_id_separator'] ?? '-') ?>" maxlength="2">
          <span class="text-muted" style="font-size: var(--font-size-xs);">Delimiter separating segments (typically <code>-</code>).</span>
        </div>

        <div class="form-group">
          <label for="cert_id_segment_count" class="form-label">Random Segments Count</label>
          <input type="number" id="cert_id_segment_count" name="cert_id_segment_count" class="form-control" value="<?= e($settings['cert_id_segment_count'] ?? '2') ?>" min="2" max="6">
          <span class="text-muted" style="font-size: var(--font-size-xs);">Minimum 2 segments required.</span>
        </div>

        <div class="form-group">
          <label for="cert_id_segment_length" class="form-label">Segment Length (Chars)</label>
          <input type="number" id="cert_id_segment_length" name="cert_id_segment_length" class="form-control" value="<?= e($settings['cert_id_segment_length'] ?? '4') ?>" min="4" max="10">
          <span class="text-muted" style="font-size: var(--font-size-xs);">Minimum 4 characters per segment.</span>
        </div>
      </div>

      <div class="form-group mb-4">
        <label for="cert_id_charset" class="form-label">Random Character Set (Unambiguous Symbols)</label>
        <input type="text" id="cert_id_charset" name="cert_id_charset" class="form-control" value="<?= e($settings['cert_id_charset'] ?? '23456789ABCDEFGHJKLMNPQRSTUVWXYZ') ?>" style="font-family: var(--font-mono);">
        <span class="text-muted" style="font-size: var(--font-size-xs);">Excludes ambiguous characters (0, O, 1, I) to maximize readability. At least 16 unique symbols required.</span>
      </div>

      <div class="form-group mb-4">
        <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
          <input type="checkbox" name="cert_id_include_year" value="1" <?= (!empty($settings['cert_id_include_year']) && $settings['cert_id_include_year'] !== '0') ? 'checked' : '' ?>>
          <span>Include 2-digit Year Segment in Certificate ID (e.g. <code>26</code>)</span>
        </label>
      </div>

      <!-- Live Generated Sample Preview Box -->
      <div style="background: var(--bg-surface-subtle); border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; display: block;">Live Sample Certificate ID Preview:</span>
          <span style="font-family: var(--font-mono); font-size: var(--font-size-xl); font-weight: 700; color: var(--color-primary);"><?= e($sampleId) ?></span>
        </div>
        <div style="font-size: var(--font-size-xs); color: var(--text-secondary); max-width: 380px;">
          Public Verification uses an independent 256-bit CSPRNG bearer token for tamper-proof verification URLs.
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Output Format & Canvas Rendering -->
  <div class="card mb-6">
    <div class="card-header">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span class="text-primary" aria-hidden="true"><?= icon('layers') ?></span>
        <h2 class="card-title" style="font-size: var(--font-size-lg); margin: 0;">Output Format &amp; Rendering Engine</h2>
      </div>
    </div>
    <div class="card-body">
      <div class="mb-4">
        <label class="form-label mb-2">Automated Output Generation Format</label>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          <label class="form-check" style="display: flex; align-items: center; gap: 0.75rem;">
            <input type="radio" name="output_format" value="pdf_image" <?= (($settings['output_format'] ?? '') === 'pdf_image' || empty($settings['output_format'])) ? 'checked' : '' ?>>
            <span><strong>PDF + High-Resolution Image (Recommended)</strong> - Generates both ISO-compliant A4 Landscape PDF and high-res JPG sharing the exact coordinate model.</span>
          </label>
          <label class="form-check" style="display: flex; align-items: center; gap: 0.75rem;">
            <input type="radio" name="output_format" value="pdf" <?= (($settings['output_format'] ?? '') === 'pdf') ? 'checked' : '' ?>>
            <span><strong>PDF Only</strong> - Generates ISO-compliant A4 Landscape vector PDF credential.</span>
          </label>
          <label class="form-check" style="display: flex; align-items: center; gap: 0.75rem;">
            <input type="radio" name="output_format" value="image" <?= (($settings['output_format'] ?? '') === 'image') ? 'checked' : '' ?>>
            <span><strong>Image Only</strong> - Generates 300 DPI high-resolution JPEG credential.</span>
          </label>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
        <div class="form-group">
          <label class="form-label">Canvas Width (px)</label>
          <input type="text" class="form-control" name="canvas_width" value="<?= e($settings['canvas_width'] ?? '2480') ?>" readonly>
          <span class="text-muted" style="font-size: var(--font-size-xs);">Standard A4 Landscape width (297mm @ 300 DPI).</span>
        </div>
        <div class="form-group">
          <label class="form-label">Canvas Height (px)</label>
          <input type="text" class="form-control" name="canvas_height" value="<?= e($settings['canvas_height'] ?? '1754') ?>" readonly>
          <span class="text-muted" style="font-size: var(--font-size-xs);">Standard A4 Landscape height (210mm @ 300 DPI).</span>
        </div>
        <div class="form-group">
          <label class="form-label">Print Resolution (DPI)</label>
          <input type="text" class="form-control" name="dpi" value="<?= e($settings['dpi'] ?? '300') ?>" readonly>
          <span class="text-muted" style="font-size: var(--font-size-xs);">300 DPI publication quality standard.</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 3: Public Verification & Rate Limiting -->
  <div class="card mb-6">
    <div class="card-header">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span class="text-primary" aria-hidden="true"><?= icon('lock') ?></span>
        <h2 class="card-title" style="font-size: var(--font-size-lg); margin: 0;">Public Verification Security &amp; Throttling</h2>
      </div>
    </div>
    <div class="card-body">
      <div class="form-group mb-4">
        <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
          <input type="checkbox" name="public_search_phone_enabled" value="1" <?= (!empty($settings['public_search_phone_enabled']) && $settings['public_search_phone_enabled'] !== '0') ? 'checked' : '' ?>>
          <span>Enable Mobile / WhatsApp Number Search on Public Verification Portal</span>
        </label>
        <span class="text-muted" style="font-size: var(--font-size-xs); display: block; margin-top: 0.25rem;">
          Allows attendees to retrieve all certificates issued to their normalized mobile number.
        </span>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
        <div class="form-group">
          <label for="public_rate_limit_max_attempts" class="form-label">Failed Lookup Threshold (IP Lockout)</label>
          <input type="number" id="public_rate_limit_max_attempts" name="public_rate_limit_max_attempts" class="form-control" value="<?= e($settings['public_rate_limit_max_attempts'] ?? '10') ?>" min="3" max="50">
          <span class="text-muted" style="font-size: var(--font-size-xs);">Number of consecutive invalid lookups before IP throttling engages.</span>
        </div>

        <div class="form-group">
          <label for="public_rate_limit_lockout_seconds" class="form-label">Lockout Duration (Seconds)</label>
          <input type="number" id="public_rate_limit_lockout_seconds" name="public_rate_limit_lockout_seconds" class="form-control" value="<?= e($settings['public_rate_limit_lockout_seconds'] ?? '900') ?>" min="60" max="86400">
          <span class="text-muted" style="font-size: var(--font-size-xs);">900 seconds = 15 minutes security lockout.</span>
        </div>
      </div>
    </div>
    <div class="card-footer" style="display: flex; justify-content: flex-end;">
      <button type="submit" class="btn btn-primary">
        <?= icon('check') ?>
        <span>Save Certificate Settings</span>
      </button>
    </div>
  </div>
</form>

<!-- Card 4: Custom Font Management -->
<div class="card mb-8">
  <div class="card-header" style="justify-content: space-between; align-items: center;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <span class="text-primary" aria-hidden="true"><?= icon('type') ?></span>
      <h2 class="card-title" style="font-size: var(--font-size-lg); margin: 0;">Custom Typography &amp; Font Manager</h2>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('fontUploadModal').style.display='flex'">
      <?= icon('upload') ?>
      <span>Upload Font (.ttf, .otf)</span>
    </button>
  </div>
  <div class="card-body" style="padding: 0;">
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Font Name</th>
            <th>Family</th>
            <th>Format</th>
            <th>Size</th>
            <th>Status</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Arial (System Default)</strong></td>
            <td><code>Arial, Helvetica, sans-serif</code></td>
            <td><span class="badge badge-neutral">TTF</span></td>
            <td>Built-in</td>
            <td><span class="badge badge-success">Active</span></td>
            <td style="text-align: right;"><span class="text-muted">System Default</span></td>
          </tr>
          <?php if (empty($fonts)): ?>
            <tr>
              <td colspan="6" class="text-muted" style="text-align: center; padding: 2rem;">No custom fonts uploaded yet. Custom TrueType (.ttf) and OpenType (.otf) fonts will appear here.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($fonts as $f): ?>
              <tr>
                <td><strong><?= e($f['name']) ?></strong></td>
                <td><code><?= e($f['font_family']) ?></code></td>
                <td><span class="badge badge-info"><?= strtoupper(e($f['format'])) ?></span></td>
                <td><?= number_format(((int) $f['file_size']) / 1024, 1) ?> KB</td>
                <td><span class="badge badge-success">Active</span></td>
                <td style="text-align: right;">
                  <form action="<?= e(url('/admin/certificate-settings/fonts/' . $f['id'] . '/delete')) ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this custom font?');" style="display: inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger);" title="Delete Font">
                      <?= icon('trash-2') ?>
                      <span>Delete</span>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Upload Custom Font -->
<div id="fontUploadModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
  <div class="card" style="width: 100%; max-width: 480px; box-shadow: var(--shadow-xl);">
    <div class="card-header" style="justify-content: space-between; align-items: center;">
      <h3 class="card-title" style="font-size: var(--font-size-md); margin: 0;">Upload Custom Font</h3>
      <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('fontUploadModal').style.display='none'">
        <?= icon('x') ?>
      </button>
    </div>
    <form action="<?= e(url('/admin/certificate-settings/fonts')) ?>" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="card-body">
        <div class="form-group mb-4">
          <label for="font_name" class="form-label">Font Display Name</label>
          <input type="text" id="font_name" name="font_name" class="form-control" required placeholder="e.g. Montserrat Bold">
        </div>
        <div class="form-group mb-4">
          <label for="font_file" class="form-label">Font File (.ttf, .otf)</label>
          <input type="file" id="font_file" name="font_file" class="form-control" accept=".ttf,.otf" required>
          <span class="text-muted" style="font-size: var(--font-size-xs);">Max 5 MB. Files are validated for authentic TrueType/OpenType magic headers.</span>
        </div>
      </div>
      <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('fontUploadModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <?= icon('upload') ?>
          <span>Upload Font</span>
        </button>
      </div>
    </form>
  </div>
</div>
