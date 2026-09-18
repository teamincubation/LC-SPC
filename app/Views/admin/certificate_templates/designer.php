<?php

declare(strict_types=1);

/**
 * Visual Certificate Designer Workspace
 * Features live interactive canvas, drag-and-drop element positioning,
 * variable insertion palette, asset uploads (background, seal, signatures),
 * and 1:1 pixel/coordinate parity with final PDF & image generation.
 */
$layout = $template['layout_config'] ?? [];
if (is_string($layout)) {
    $layout = json_decode($layout, true) ?: [];
}
$elements = $layout['elements'] ?? \App\Services\CertificateRenderer::getDefaultElements();
$dummyData = \App\Services\VariableRegistry::getPreviewDummyData();
$dummyData['template_name'] = $template['name'];
?>

<div class="page-header mb-4" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
  <div>
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
      <a href="<?= e(url('/admin/certificate-templates')) ?>" class="text-secondary" style="font-size: var(--font-size-xs); text-decoration: none;">&larr; Templates Directory</a>
      <span class="text-muted">&bull;</span>
      <span class="text-secondary" style="font-size: var(--font-size-xs);"><?= e($template['name']) ?></span>
    </div>
    <h1 class="page-title" style="font-size: var(--font-size-2xl);">Visual Certificate Designer</h1>
  </div>

  <div style="display: flex; gap: 0.5rem; align-items: center;">
    <a href="<?= e(url('/admin/certificate-templates/' . $template['id'] . '/preview')) ?>" target="_blank" class="btn btn-secondary btn-sm" id="btnLivePreview">
      <?= icon('eye') ?>
      <span>Rendered Preview</span>
    </a>
    <button type="button" class="btn btn-primary btn-sm" id="btnSaveDesigner">
      <?= icon('check') ?>
      <span>Save Layout</span>
    </button>
  </div>
</div>

<!-- Designer Container (2 Columns: Sidebar Tools + Center Canvas) -->
<div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start;">

  <!-- Left Sidebar: Variable Palette & Selected Element Properties -->
  <div style="display: flex; flex-direction: column; gap: 1.25rem;">

    <!-- Variable Palette -->
    <div class="card">
      <div class="card-header" style="padding: 0.75rem 1rem;">
        <h3 class="card-title" style="font-size: var(--font-size-sm); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
          <?= icon('tag') ?>
          <span>Insert Variable Palette</span>
        </h3>
      </div>
      <div class="card-body" style="padding: 0.75rem;">
        <p class="text-secondary mb-2" style="font-size: 11px;">Click any variable below to insert it into the active text element:</p>
        <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
          <?php foreach ($allVars as $k => $v): ?>
            <button type="button" class="btn btn-secondary btn-sm var-badge" data-tag="<?= e($v['placeholder']) ?>" style="padding: 2px 8px; font-size: 11px; font-family: var(--font-mono); border-radius: 4px;">
              <?= e($v['placeholder']) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Active Element Inspector / Properties -->
    <div class="card" id="elementInspectorCard">
      <div class="card-header" style="padding: 0.75rem 1rem;">
        <h3 class="card-title" style="font-size: var(--font-size-sm); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
          <?= icon('sliders') ?>
          <span>Element Properties</span>
        </h3>
      </div>
      <div class="card-body" style="padding: 0.75rem;">
        <div id="noSelectionNotice" class="text-muted" style="font-size: var(--font-size-xs); text-align: center; padding: 1rem 0;">
          Select an element on the canvas to configure its typography and exact coordinates.
        </div>

        <div id="inspectorControls" style="display: none;">
          <div class="form-group mb-2">
            <label class="form-label" style="font-size: 11px;">Text / Dynamic Template</label>
            <textarea id="propText" class="form-control" rows="2" style="font-size: 12px; font-family: var(--font-mono);"></textarea>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;" class="mb-2">
            <div>
              <label class="form-label" style="font-size: 11px;">X Position (px)</label>
              <input type="number" id="propX" class="form-control" style="font-size: 12px;" min="0" max="2480">
            </div>
            <div>
              <label class="form-label" style="font-size: 11px;">Y Position (px)</label>
              <input type="number" id="propY" class="form-control" style="font-size: 12px;" min="0" max="1754">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;" class="mb-2">
            <div>
              <label class="form-label" style="font-size: 11px;">Font Size (pt)</label>
              <input type="number" id="propFontSize" class="form-control" style="font-size: 12px;" min="8" max="120">
            </div>
            <div>
              <label class="form-label" style="font-size: 11px;">Color (Hex)</label>
              <input type="color" id="propColor" class="form-control" style="height: 36px; padding: 2px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;" class="mb-2">
            <div>
              <label class="form-label" style="font-size: 11px;">Font Weight</label>
              <select id="propWeight" class="form-control" style="font-size: 12px;">
                <option value="normal">Normal (400)</option>
                <option value="bold">Bold (700)</option>
              </select>
            </div>
            <div>
              <label class="form-label" style="font-size: 11px;">Alignment</label>
              <select id="propAlign" class="form-control" style="font-size: 12px;">
                <option value="center">Center</option>
                <option value="left">Left</option>
                <option value="right">Right</option>
              </select>
            </div>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
            <button type="button" class="btn btn-secondary btn-sm" id="btnApplyInspector" style="flex: 1;">Update Element</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btnDeleteElement" style="color: var(--color-danger);" title="Remove Element"><?= icon('trash-2') ?></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Template Assets (Background, Signatures, Seal) -->
    <div class="card">
      <div class="card-header" style="padding: 0.75rem 1rem;">
        <h3 class="card-title" style="font-size: var(--font-size-sm); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
          <?= icon('image') ?>
          <span>Template Background &amp; Assets</span>
        </h3>
      </div>
      <div class="card-body" style="padding: 0.75rem;">
        <div class="form-group mb-3">
          <label class="form-label" style="font-size: 11px;">Custom Background Image (A4 Landscape)</label>
          <input type="file" id="uploadBg" class="form-control" accept=".jpg,.jpeg,.png,.webp" style="font-size: 11px;">
          <?php if (!empty($template['background_image_path'])): ?>
            <span class="text-success" style="font-size: 10px; display: block; margin-top: 2px;">Active: <?= e(basename($template['background_image_path'])) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group mb-3">
          <label class="form-label" style="font-size: 11px;">Official Seal / Emblem (PNG)</label>
          <input type="file" id="uploadSeal" class="form-control" accept=".png,.webp,.jpg" style="font-size: 11px;">
        </div>

        <div class="form-group mb-3">
          <label class="form-label" style="font-size: 11px;">Signatory 1 Image &amp; Name</label>
          <input type="file" id="uploadSig1" class="form-control mb-1" accept=".png,.webp" style="font-size: 11px;">
          <input type="text" id="sig1Name" class="form-control mb-1" value="<?= e($template['signature1_name'] ?? '') ?>" placeholder="Signatory 1 Name" style="font-size: 11px;">
          <input type="text" id="sig1Title" class="form-control" value="<?= e($template['signature1_designation'] ?? '') ?>" placeholder="Signatory 1 Title" style="font-size: 11px;">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size: 11px;">Signatory 2 Image &amp; Name</label>
          <input type="file" id="uploadSig2" class="form-control mb-1" accept=".png,.webp" style="font-size: 11px;">
          <input type="text" id="sig2Name" class="form-control mb-1" value="<?= e($template['signature2_name'] ?? '') ?>" placeholder="Signatory 2 Name" style="font-size: 11px;">
          <input type="text" id="sig2Title" class="form-control" value="<?= e($template['signature2_designation'] ?? '') ?>" placeholder="Signatory 2 Title" style="font-size: 11px;">
        </div>
      </div>
    </div>
  </div>

  <!-- Right Side: Certificate Canvas Representation (A4 Landscape 2480x1754) -->
  <div style="display: flex; flex-direction: column; gap: 0.75rem;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <span class="text-secondary" style="font-size: var(--font-size-xs);">
        Standard Canvas: <strong>2480 &times; 1754 px</strong> (A4 Landscape @ 300 DPI) &bull; Drag elements or click to edit coordinates.
      </span>
      <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-secondary btn-sm" id="btnAddText">
          <?= icon('plus') ?>
          <span>Add Text Element</span>
        </button>
      </div>
    </div>

    <!-- Canvas Wrapper with Fixed 1.414 Aspect Ratio -->
    <div id="canvasViewport" style="position: relative; width: 100%; aspect-ratio: 2480 / 1754; background: #FFFFFF; border: 1px solid var(--border-strong); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); overflow: hidden; user-select: none;">

      <!-- Canvas Render Layer (Absolute Coordinate Grid: 2480 x 1754) -->
      <div id="certCanvas" style="position: absolute; width: 2480px; height: 1754px; top: 0; left: 0; transform-origin: top left;">
        <!-- Canvas Background -->
        <?php if (!empty($template['background_image_path'])): ?>
          <img id="canvasBgImg" src="<?= e(url('/' . $template['background_image_path'])) ?>" alt="Background" style="position: absolute; top: 0; left: 0; width: 2480px; height: 1754px; pointer-events: none;">
        <?php else: ?>
          <div id="canvasDefaultBorder" style="position: absolute; inset: 80px; border: 16px solid #0F172A; pointer-events: none;">
            <div style="position: absolute; inset: 12px; border: 4px solid #BF1E2E;"></div>
          </div>
        <?php endif; ?>

        <!-- Dynamic Draggable Elements Container -->
        <div id="elementsLayer" style="position: absolute; inset: 0;"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const CANVAS_WIDTH = 2480;
  const CANVAS_HEIGHT = 1754;
  const dummyData = <?= json_encode($dummyData) ?>;
  let elements = <?= json_encode($elements) ?>;
  let selectedElementId = null;

  const viewport = document.getElementById('canvasViewport');
  const canvas = document.getElementById('certCanvas');
  const elementsLayer = document.getElementById('elementsLayer');

  // Automatic Viewport Scaling to match container width
  function updateScale() {
    const containerWidth = viewport.clientWidth;
    const scale = containerWidth / CANVAS_WIDTH;
    canvas.style.transform = `scale(${scale})`;
  }
  window.addEventListener('resize', updateScale);
  updateScale();

  function replaceVars(text) {
    return text.replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, (match, p1) => {
      return dummyData[p1] !== undefined ? dummyData[p1] : match;
    });
  }

  function renderElements() {
    elementsLayer.innerHTML = '';
    elements.forEach((el, index) => {
      const elDiv = document.createElement('div');
      elDiv.className = 'designer-element' + (selectedElementId === index ? ' is-selected' : '');
      elDiv.dataset.index = index;

      const align = el.align || 'center';
      const x = el.x || 1240;
      const y = el.y || 800;
      const fontSize = el.font_size || 24;
      const fontWeight = el.font_weight || 'normal';
      const color = el.color || '#0F172A';

      elDiv.style.position = 'absolute';
      elDiv.style.fontSize = `${fontSize}px`;
      elDiv.style.fontWeight = fontWeight;
      elDiv.style.color = color;
      elDiv.style.cursor = 'move';
      elDiv.style.whiteSpace = 'nowrap';
      elDiv.style.padding = '4px 8px';
      elDiv.style.borderRadius = '4px';

      if (selectedElementId === index) {
        elDiv.style.outline = '3px dashed #BF1E2E';
        elDiv.style.background = 'rgba(191, 30, 46, 0.08)';
      }

      if (el.type === 'signature1' || el.type === 'signature2') {
        elDiv.style.textAlign = 'center';
        elDiv.innerHTML = `<div style="border-top: 3px solid #CBD5E1; padding-top: 8px;"><strong>${el.name || 'Signatory'}</strong><br><small style="color: #64748B;">${el.title || 'Title'}</small></div>`;
      } else if (el.type === 'qr_code') {
        elDiv.style.textAlign = 'center';
        elDiv.innerHTML = `<div style="width: 180px; height: 180px; border: 2px solid #CBD5E1; background: #FFF; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; color: #64748B;">[QR VERIFICATION]</div>`;
      } else if (el.type === 'seal') {
        elDiv.style.textAlign = 'center';
        elDiv.innerHTML = `<div style="width: 160px; height: 160px; border-radius: 50%; border: 3px double #B48228; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; color: #B48228;">[OFFICIAL SEAL]</div>`;
      } else {
        elDiv.textContent = replaceVars(el.text || '');
      }

      elementsLayer.appendChild(elDiv);

      // Alignment coordinate adjustments
      const rect = elDiv.getBoundingClientRect();
      const scale = viewport.clientWidth / CANVAS_WIDTH;
      const unscaledWidth = rect.width / scale;

      let drawX = x;
      if (align === 'center') {
        drawX = x - (unscaledWidth / 2);
      } else if (align === 'right') {
        drawX = x - unscaledWidth;
      }
      elDiv.style.left = `${drawX}px`;
      elDiv.style.top = `${y}px`;

      // Selection & Drag handling
      elDiv.addEventListener('mousedown', (e) => {
        e.stopPropagation();
        selectElement(index);

        let startX = e.clientX;
        let startY = e.clientY;
        let initialElX = el.x;
        let initialElY = el.y;

        function onMouseMove(moveEvent) {
          const dx = (moveEvent.clientX - startX) / (viewport.clientWidth / CANVAS_WIDTH);
          const dy = (moveEvent.clientY - startY) / (viewport.clientWidth / CANVAS_WIDTH);

          el.x = Math.round(initialElX + dx);
          el.y = Math.round(initialElY + dy);
          renderElements();
          updateInspector();
        }

        function onMouseUp() {
          window.removeEventListener('mousemove', onMouseMove);
          window.removeEventListener('mouseup', onMouseUp);
        }

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
      });
    });
  }

  function selectElement(index) {
    selectedElementId = index;
    renderElements();
    updateInspector();
  }

  function updateInspector() {
    const card = document.getElementById('elementInspectorCard');
    const notice = document.getElementById('noSelectionNotice');
    const controls = document.getElementById('inspectorControls');

    if (selectedElementId === null || !elements[selectedElementId]) {
      notice.style.display = 'block';
      controls.style.display = 'none';
      return;
    }

    notice.style.display = 'none';
    controls.style.display = 'block';

    const el = elements[selectedElementId];
    document.getElementById('propText').value = el.text || '';
    document.getElementById('propX').value = el.x || 0;
    document.getElementById('propY').value = el.y || 0;
    document.getElementById('propFontSize').value = el.font_size || 24;
    document.getElementById('propColor').value = el.color || '#0F172A';
    document.getElementById('propWeight').value = el.font_weight || 'normal';
    document.getElementById('propAlign').value = el.align || 'center';
  }

  document.getElementById('btnApplyInspector').addEventListener('click', () => {
    if (selectedElementId === null || !elements[selectedElementId]) return;
    const el = elements[selectedElementId];
    el.text = document.getElementById('propText').value;
    el.x = parseInt(document.getElementById('propX').value, 10) || 0;
    el.y = parseInt(document.getElementById('propY').value, 10) || 0;
    el.font_size = parseInt(document.getElementById('propFontSize').value, 10) || 24;
    el.color = document.getElementById('propColor').value;
    el.font_weight = document.getElementById('propWeight').value;
    el.align = document.getElementById('propAlign').value;
    renderElements();
  });

  document.getElementById('btnDeleteElement').addEventListener('click', () => {
    if (selectedElementId === null) return;
    elements.splice(selectedElementId, 1);
    selectedElementId = null;
    renderElements();
    updateInspector();
  });

  document.getElementById('btnAddText').addEventListener('click', () => {
    elements.push({
      type: 'text',
      text: 'New Text Element',
      x: 1240,
      y: 900,
      font_size: 28,
      font_family: 'arial',
      font_weight: 'normal',
      color: '#0F172A',
      align: 'center',
    });
    selectElement(elements.length - 1);
  });

  // Variable insertion into active text element
  document.querySelectorAll('.var-badge').forEach(btn => {
    btn.addEventListener('click', () => {
      const tag = btn.dataset.tag;
      if (selectedElementId !== null && elements[selectedElementId]) {
        elements[selectedElementId].text = (elements[selectedElementId].text || '') + ' ' + tag;
        renderElements();
        updateInspector();
      } else {
        elements.push({
          type: 'dynamic_text',
          text: tag,
          x: 1240,
          y: 900,
          font_size: 32,
          font_family: 'arial',
          font_weight: 'bold',
          color: '#0F172A',
          align: 'center',
        });
        selectElement(elements.length - 1);
      }
    });
  });

  // Save Designer Action via FormData (handles files + JSON)
  document.getElementById('btnSaveDesigner').addEventListener('click', async () => {
    // Validate mandatory variables before submitting
    let hasName = false;
    let hasPhone = false;
    elements.forEach(el => {
      if ((el.text || '').includes('{{name}}')) hasName = true;
      if ((el.text || '').includes('{{phone}}')) hasPhone = true;
    });

    if (!hasName || !hasPhone) {
      alert('Error: Certificate template MUST contain both {{name}} and {{phone}} variables before saving.');
      return;
    }

    const formData = new FormData();
    formData.append('layout_config', JSON.stringify({ elements: elements }));

    const bgFile = document.getElementById('uploadBg').files[0];
    if (bgFile) formData.append('background_image', bgFile);

    const sealFile = document.getElementById('uploadSeal').files[0];
    if (sealFile) formData.append('seal_image', sealFile);

    const sig1File = document.getElementById('uploadSig1').files[0];
    if (sig1File) formData.append('signature1_image', sig1File);
    formData.append('signature1_name', document.getElementById('sig1Name').value);
    formData.append('signature1_designation', document.getElementById('sig1Title').value);

    const sig2File = document.getElementById('uploadSig2').files[0];
    if (sig2File) formData.append('signature2_image', sig2File);
    formData.append('signature2_name', document.getElementById('sig2Name').value);
    formData.append('signature2_designation', document.getElementById('sig2Title').value);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    formData.append('_csrf_token', csrfToken);

    try {
      const res = await fetch('<?= e(url('/admin/certificate-templates/' . $template['id'] . '/designer')) ?>', {
        method: 'POST',
        body: formData,
      });
      const json = await res.json();
      if (json.status === 'success') {
        alert('Layout and assets saved successfully!');
        window.location.reload();
      } else {
        alert(json.error || 'Failed to save layout.');
      }
    } catch (err) {
      alert('Server error saving layout: ' + err.message);
    }
  });

  // Deselect when clicking outside
  viewport.addEventListener('click', () => {
    selectedElementId = null;
    renderElements();
    updateInspector();
  });

  renderElements();
});
</script>
