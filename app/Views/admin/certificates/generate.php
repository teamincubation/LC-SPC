<?php

declare(strict_types=1);

/**
 * Certificate Generation & Batch Processing View
 */
?>

<div class="page-header mb-6" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h1 class="page-title">Generate Certificates</h1>
    <p class="text-secondary">Upload participant CSV data, validate structure, and generate high-resolution certificates in resilient batches.</p>
  </div>
  <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-outline">
    <?= icon('list') ?>
    <span>Certificate Repository</span>
  </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
  <!-- Step 1 & 2: Template Selection & CSV Upload -->
  <div class="card md:col-span-2">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem;">
      <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
        <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: var(--color-primary); color: #fff; font-size: 0.8rem;">1</span>
        Select Template & Upload CSV
      </h2>
    </div>
    <div class="card-body" style="padding: 1.5rem;">
      <form id="csvValidationForm" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">

        <!-- Template Selector -->
        <div class="form-group mb-5">
          <label for="templateSelect" class="form-label font-semibold">Certificate Template <span class="text-danger">*</span></label>
          <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <select id="templateSelect" name="template_id" class="form-select" style="flex: 1; min-width: 260px;" required>
              <option value="">-- Choose an Active Template --</option>
              <?php foreach ($templates as $tmpl): ?>
                <option value="<?= e($tmpl['id']) ?>" data-variables="<?= e(json_encode($tmpl['layout_config']['variables'] ?? [])) ?>">
                  <?= e($tmpl['name']) ?> (<?= e(ucfirst($tmpl['certificate_type'])) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <a id="downloadSampleBtn" href="#" class="btn btn-secondary btn-sm" style="display: none; white-space: nowrap;">
              <?= icon('download') ?>
              <span>Download Sample CSV</span>
            </a>
          </div>
          <div id="templateInfo" class="form-text text-secondary mt-2" style="font-size: var(--font-size-xs); display: none;"></div>
        </div>

        <!-- Drag & Drop Zone -->
        <div class="form-group mb-5">
          <label class="form-label font-semibold">Recipient Data (CSV) <span class="text-danger">*</span></label>
          <div id="dropZone" style="border: 2px dashed var(--border-strong); border-radius: 8px; padding: 2.5rem 1.5rem; text-align: center; background: var(--bg-surface-subtle); cursor: pointer; transition: all 0.2s ease;">
            <div style="font-size: 2.5rem; color: var(--color-primary); margin-bottom: 0.75rem;">
              <?= icon('upload-cloud') ?>
            </div>
            <p style="font-weight: var(--font-weight-medium); margin-bottom: 0.25rem;">
              Click to browse or drag & drop CSV file here
            </p>
            <p class="text-secondary" style="font-size: var(--font-size-xs); margin-bottom: 1rem;">
              Max file size: 10 MB. File must be valid UTF-8 CSV with header row matching template variables.
            </p>
            <span id="selectedFileName" class="badge badge-neutral" style="display: none; font-size: 0.85rem; padding: 0.4rem 0.8rem;"></span>
            <input type="file" id="csvFileInput" name="csv_file" accept=".csv,text/csv" style="display: none;" required>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button type="submit" id="btnValidateCsv" class="btn btn-primary" disabled>
            <?= icon('check-circle') ?>
            <span>Validate CSV</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Requirements Sidebar -->
  <div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem;">
      <h3 style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); margin: 0;">
        Data Requirements
      </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem 1.5rem; font-size: var(--font-size-sm); line-height: 1.6;">
      <div style="margin-bottom: 1rem;">
        <strong style="display: block; color: var(--text-primary); margin-bottom: 0.25rem;">Mandatory Columns:</strong>
        <ul style="padding-left: 1.25rem; margin: 0; color: var(--text-secondary);">
          <li><code>name</code> — Full name of the recipient</li>
          <li><code>phone</code> — Contact phone number (auto-normalized to E.164)</li>
        </ul>
      </div>

      <div style="margin-bottom: 1rem;">
        <strong style="display: block; color: var(--text-primary); margin-bottom: 0.25rem;">Custom Columns:</strong>
        <p class="text-secondary" style="margin: 0; font-size: var(--font-size-xs);">
          Any custom variables defined in the selected template must also appear in the header row.
        </p>
      </div>

      <div style="margin-bottom: 1rem;">
        <strong style="display: block; color: var(--text-primary); margin-bottom: 0.25rem;">Validation Protections:</strong>
        <ul style="padding-left: 1.25rem; margin: 0; color: var(--text-secondary); font-size: var(--font-size-xs);">
          <li>Automatic UTF-8 BOM stripping</li>
          <li>Spreadsheet formula injection sanitization</li>
          <li>Duplicate entry detection</li>
          <li>Chunked processing to prevent timeouts</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Step 3: Validation Results & Actions (Dynamic) -->
<div id="validationResultsSection" style="display: none;" class="mb-8">
  <div class="card mb-6">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
      <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
        <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: var(--color-primary); color: #fff; font-size: 0.8rem;">2</span>
        Validation Summary
      </h2>
      <div style="display: flex; gap: 0.5rem;">
        <form id="errorReportForm" method="POST" action="<?= e(url('/admin/certificates/download-error-report')) ?>" style="display: inline;">
          <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" id="btnDownloadErrorReport" class="btn btn-outline btn-sm" style="display: none;">
            <?= icon('download') ?>
            <span>Download Error Report (CSV)</span>
          </button>
        </form>
        <button type="button" id="btnStartGeneration" class="btn btn-success btn-sm">
          <?= icon('award') ?>
          <span id="btnStartGenLabel">Generate Valid Certificates</span>
        </button>
      </div>
    </div>
    <div class="card-body" style="padding: 1.5rem;">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div style="background: var(--bg-surface-subtle); border-radius: 8px; padding: 1.25rem; text-align: center;">
          <div class="text-secondary" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: var(--font-weight-semibold);">Total Processed</div>
          <div id="statTotal" style="font-size: 2rem; font-weight: var(--font-weight-bold); color: var(--text-primary);">0</div>
        </div>
        <div style="background: var(--bg-success); border: 1px solid var(--border-success); border-radius: 8px; padding: 1.25rem; text-align: center;">
          <div style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: var(--font-weight-semibold); color: var(--text-success);">Valid Records</div>
          <div id="statValid" style="font-size: 2rem; font-weight: var(--font-weight-bold); color: var(--text-success);">0</div>
        </div>
        <div style="background: var(--bg-danger); border: 1px solid var(--border-danger); border-radius: 8px; padding: 1.25rem; text-align: center;">
          <div style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: var(--font-weight-semibold); color: var(--text-danger);">Invalid Records</div>
          <div id="statInvalid" style="font-size: 2rem; font-weight: var(--font-weight-bold); color: var(--text-danger);">0</div>
        </div>
      </div>

      <!-- Invalid Rows Detail Table -->
      <div id="invalidRowsContainer" style="display: none;">
        <h4 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-danger); margin-bottom: 0.75rem;">
          Rows with Validation Errors (will be excluded from generation):
        </h4>
        <div class="table-container" style="max-height: 320px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 6px;">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 80px;">Line #</th>
                <th>Recipient Name</th>
                <th>Phone Number</th>
                <th>Validation Errors</th>
              </tr>
            </thead>
            <tbody id="invalidRowsTableBody">
              <!-- Dynamically populated -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Step 4: Batch Processing Modal / Progress State -->
<div id="progressSection" style="display: none;" class="card mb-8">
  <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem;">
    <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
      <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: var(--color-primary); color: #fff; font-size: 0.8rem;">3</span>
      Generating Certificates...
    </h2>
  </div>
  <div class="card-body" style="padding: 2rem 1.5rem; text-align: center;">
    <div style="max-width: 580px; margin: 0 auto;">
      <!-- Progress Bar -->
      <div style="background: var(--bg-surface-muted); border-radius: 999px; height: 18px; overflow: hidden; margin-bottom: 1rem; position: relative;">
        <div id="batchProgressBar" style="width: 0%; height: 100%; background: var(--color-primary); transition: width 0.3s ease; border-radius: 999px;"></div>
      </div>

      <div style="display: flex; justify-content: space-between; font-size: var(--font-size-sm); margin-bottom: 1.5rem;">
        <span id="batchProgressLabel" class="text-secondary">Initializing batch...</span>
        <strong id="batchProgressPercentage">0%</strong>
      </div>

      <!-- Live Counters -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div style="background: var(--bg-surface-subtle); padding: 0.75rem; border-radius: 6px;">
          <div class="text-secondary" style="font-size: var(--font-size-xs);">Total To Generate</div>
          <div id="counterTotal" style="font-size: 1.25rem; font-weight: var(--font-weight-bold);">0</div>
        </div>
        <div style="background: var(--bg-success); padding: 0.75rem; border-radius: 6px;">
          <div style="font-size: var(--font-size-xs); color: var(--text-success);">Generated</div>
          <div id="counterGenerated" style="font-size: 1.25rem; font-weight: var(--font-weight-bold); color: var(--text-success);">0</div>
        </div>
        <div style="background: var(--bg-danger); padding: 0.75rem; border-radius: 6px;">
          <div style="font-size: var(--font-size-xs); color: var(--text-danger);">Failed</div>
          <div id="counterFailed" style="font-size: 1.25rem; font-weight: var(--font-weight-bold); color: var(--text-danger);">0</div>
        </div>
      </div>

      <!-- Completion Banner -->
      <div id="batchCompleteBox" style="display: none; padding: 1.5rem; background: var(--bg-success); border: 1px solid var(--border-success); border-radius: 8px; margin-top: 1.5rem;">
        <div style="color: var(--color-success); font-size: 2.5rem; margin-bottom: 0.5rem;">
          <?= icon('check-circle') ?>
        </div>
        <h3 style="color: var(--text-success); font-size: var(--font-size-lg); margin-bottom: 0.5rem;">Certificate Batch Complete!</h3>
        <p class="text-secondary mb-4" style="font-size: var(--font-size-sm);">All valid certificates have been rendered and stored in the secure repository.</p>
        <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-primary">
          <?= icon('list') ?>
          <span>View Certificate Repository</span>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const templateSelect = document.getElementById('templateSelect');
  const downloadSampleBtn = document.getElementById('downloadSampleBtn');
  const templateInfo = document.getElementById('templateInfo');
  const dropZone = document.getElementById('dropZone');
  const csvFileInput = document.getElementById('csvFileInput');
  const selectedFileName = document.getElementById('selectedFileName');
  const btnValidateCsv = document.getElementById('btnValidateCsv');
  const csvValidationForm = document.getElementById('csvValidationForm');

  const validationResultsSection = document.getElementById('validationResultsSection');
  const statTotal = document.getElementById('statTotal');
  const statValid = document.getElementById('statValid');
  const statInvalid = document.getElementById('statInvalid');
  const invalidRowsContainer = document.getElementById('invalidRowsContainer');
  const invalidRowsTableBody = document.getElementById('invalidRowsTableBody');
  const btnDownloadErrorReport = document.getElementById('btnDownloadErrorReport');
  const btnStartGeneration = document.getElementById('btnStartGeneration');
  const btnStartGenLabel = document.getElementById('btnStartGenLabel');

  const progressSection = document.getElementById('progressSection');
  const batchProgressBar = document.getElementById('batchProgressBar');
  const batchProgressLabel = document.getElementById('batchProgressLabel');
  const batchProgressPercentage = document.getElementById('batchProgressPercentage');
  const counterTotal = document.getElementById('counterTotal');
  const counterGenerated = document.getElementById('counterGenerated');
  const counterFailed = document.getElementById('counterFailed');
  const batchCompleteBox = document.getElementById('batchCompleteBox');

  const baseUrl = '<?= e(rtrim(url(''), '/')) ?>';
  const csrfToken = '<?= e(csrf_token()) ?>';

  // Template change handler
  templateSelect.addEventListener('change', () => {
    const val = templateSelect.value;
    if (val) {
      downloadSampleBtn.href = `${baseUrl}/admin/certificates/sample-csv/${val}`;
      downloadSampleBtn.style.display = 'inline-flex';
      
      const selectedOption = templateSelect.options[templateSelect.selectedIndex];
      try {
        const vars = JSON.parse(selectedOption.getAttribute('data-variables') || '[]');
        const varList = vars.map(v => `<code>{{${v.key}}}</code>`).join(', ');
        templateInfo.innerHTML = `Variables expected in CSV: <code>{{name}}</code>, <code>{{phone}}</code>${varList ? ', ' + varList : ''}`;
        templateInfo.style.display = 'block';
      } catch (e) {
        templateInfo.style.display = 'none';
      }
    } else {
      downloadSampleBtn.style.display = 'none';
      templateInfo.style.display = 'none';
    }
    updateValidateBtnState();
  });

  // Drag & drop handlers
  dropZone.addEventListener('click', () => csvFileInput.click());

  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      dropZone.style.borderColor = 'var(--color-primary)';
      dropZone.style.background = 'var(--color-primary-tint)';
    });
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      dropZone.style.borderColor = 'var(--border-strong)';
      dropZone.style.background = 'var(--bg-surface-subtle)';
    });
  });

  dropZone.addEventListener('drop', (e) => {
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      csvFileInput.files = e.dataTransfer.files;
      handleFileSelected();
    }
  });

  csvFileInput.addEventListener('change', handleFileSelected);

  function handleFileSelected() {
    if (csvFileInput.files && csvFileInput.files[0]) {
      const file = csvFileInput.files[0];
      selectedFileName.textContent = `Selected: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
      selectedFileName.style.display = 'inline-block';
    } else {
      selectedFileName.style.display = 'none';
    }
    updateValidateBtnState();
  }

  function updateValidateBtnState() {
    btnValidateCsv.disabled = !(templateSelect.value && csvFileInput.files && csvFileInput.files.length > 0);
  }

  // Validate CSV Form submission
  csvValidationForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!templateSelect.value || !csvFileInput.files[0]) return;

    btnValidateCsv.disabled = true;
    btnValidateCsv.innerHTML = `<span>Validating CSV...</span>`;

    const formData = new FormData(csvValidationForm);

    try {
      const response = await fetch(`${baseUrl}/admin/certificates/validate-csv`, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const data = await response.json();

      if (!response.ok || data.error) {
        alert(data.error || 'Validation failed. Please check the file format.');
        btnValidateCsv.disabled = false;
        btnValidateCsv.innerHTML = `<span>Validate CSV</span>`;
        return;
      }

      // Display validation results
      statTotal.textContent = data.total_records;
      statValid.textContent = data.valid_count;
      statInvalid.textContent = data.invalid_count;

      if (data.invalid_count > 0) {
        btnDownloadErrorReport.style.display = 'inline-flex';
        invalidRowsContainer.style.display = 'block';
        invalidRowsTableBody.innerHTML = '';
        data.invalid_rows.forEach(row => {
          const tr = document.createElement('tr');
          const errorsList = row.errors.map(err => `<span class="badge badge-danger" style="margin-right: 4px; margin-bottom: 2px;">${escapeHtml(err)}</span>`).join('');
          tr.innerHTML = `
            <td><strong>#${row.line}</strong></td>
            <td>${escapeHtml(row.data.name || '-')}</td>
            <td><code>${escapeHtml(row.data.phone || '-')}</code></td>
            <td>${errorsList}</td>
          `;
          invalidRowsTableBody.appendChild(tr);
        });
      } else {
        btnDownloadErrorReport.style.display = 'none';
        invalidRowsContainer.style.display = 'none';
      }

      if (data.valid_count > 0) {
        btnStartGeneration.disabled = false;
        btnStartGenLabel.textContent = `Generate ${data.valid_count} Valid Certificate(s)`;
      } else {
        btnStartGeneration.disabled = true;
        btnStartGenLabel.textContent = 'No Valid Records to Generate';
      }

      validationResultsSection.style.display = 'block';
      validationResultsSection.scrollIntoView({ behavior: 'smooth' });

    } catch (err) {
      alert('An unexpected network error occurred while validating: ' + err.message);
    } finally {
      btnValidateCsv.disabled = false;
      btnValidateCsv.innerHTML = `<span>Validate CSV</span>`;
    }
  });

  // Start Batch Generation
  btnStartGeneration.addEventListener('click', async () => {
    if (!confirm('Start generating certificates for all valid records in this batch?')) return;

    btnStartGeneration.disabled = true;
    validationResultsSection.style.display = 'none';
    progressSection.style.display = 'block';
    progressSection.scrollIntoView({ behavior: 'smooth' });

    try {
      const startRes = await fetch(`${baseUrl}/admin/certificates/start-batch`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
          '_csrf_token': csrfToken
        })
      });

      const startData = await startRes.json();
      if (!startRes.ok || startData.error) {
        alert('Failed to initialize batch: ' + (startData.error || 'Server error'));
        return;
      }

      const batchId = startData.batch_id;
      const totalValid = startData.total_valid;
      counterTotal.textContent = totalValid;

      // Start chunk loop
      await processChunkLoop(batchId, totalValid);

    } catch (err) {
      alert('Error starting batch: ' + err.message);
    }
  });

  // Chunk processing loop
  async function processChunkLoop(batchId, totalValid) {
    let isComplete = false;

    while (!isComplete) {
      try {
        const chunkRes = await fetch(`${baseUrl}/admin/certificates/process-chunk`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({
            '_csrf_token': csrfToken,
            'batch_id': batchId,
            'chunk_size': 25
          })
        });

        const chunkData = await chunkRes.json();

        if (!chunkRes.ok || chunkData.error) {
          batchProgressLabel.textContent = 'Error processing chunk: ' + (chunkData.error || 'Server error');
          batchProgressLabel.style.color = 'var(--color-danger)';
          break;
        }

        const percentage = chunkData.percentage || 0;
        batchProgressBar.style.width = `${percentage}%`;
        batchProgressPercentage.textContent = `${percentage}%`;
        batchProgressLabel.textContent = `Generated ${chunkData.processed} of ${chunkData.total} certificates...`;

        counterGenerated.textContent = chunkData.processed;
        counterFailed.textContent = chunkData.failed;

        if (chunkData.is_complete) {
          isComplete = true;
          batchProgressLabel.textContent = 'Batch generation complete!';
          batchProgressBar.style.width = '100%';
          batchProgressPercentage.textContent = '100%';
          batchCompleteBox.style.display = 'block';
        }

      } catch (err) {
        batchProgressLabel.textContent = 'Connection error: ' + err.message + '. Retrying in 3s...';
        await new Promise(r => setTimeout(r, 3000));
      }
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
});
</script>
