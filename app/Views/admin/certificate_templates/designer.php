<?php

declare(strict_types=1);

/**
 * Visual Certificate Designer Workspace
 * Professional 3-Column Interactive Certificate Editor for LC-SPC V3.
 *
 * Architecture:
 * - Canonical A4 Landscape Canvas: 2480 x 1754 px @ 300 DPI equivalent.
 * - 1:1 Pixel and coordinate parity across live browser canvas, GD image, and ISO PDF.
 * - Grouped Signature components (signature image + dividing line + signatory name + designation).
 * - Dynamic QR code encoding {{verification_url}} (deterministic preview fallback; never persists test tokens).
 * - Strict Option A asset upload security (PNG/WebP/JPG only, strictly no SVG).
 * - Snap-to-center alignment guides (X = 1240, Y = 877) and alignment tools.
 * - Dual-layer preview: fast live browser canvas + modal backend CertificateRenderer stream.
 * - Viewport-filling layout with zero outer vertical page scrolling and mobile-first drawer adaptations.
 */

$layout = $template['layout_config'] ?? [];
if (is_string($layout)) {
    $layout = json_decode($layout, true) ?: [];
}
$elements = $layout['elements'] ?? \App\Services\CertificateRenderer::getDefaultElements();
$dummyData = \App\Services\VariableRegistry::getPreviewDummyData();
$dummyData['template_name'] = $template['name'];
$activeBackground = !empty($template['background_image_path']) ? url('/' . $template['background_image_path']) : null;
$activeSeal = !empty($template['seal_image_path']) ? url('/' . $template['seal_image_path']) : null;
$activeSig1 = !empty($template['signature1_image_path']) ? url('/' . $template['signature1_image_path']) : null;
$activeSig2 = !empty($template['signature2_image_path']) ? url('/' . $template['signature2_image_path']) : null;
?>

<!-- Google Fonts for Typography Hierarchy -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;800&family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* Full-bleed Viewport Adaptation */
.admin-content:has(.designer-workspace) {
  padding: 0 !important;
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  height: calc(100vh - 64px);
  overflow: hidden;
}

@supports not selector(:has(*)) {
  .designer-workspace {
    margin: -2rem -1.5rem;
    width: calc(100% + 3rem);
    height: calc(100vh - 64px);
  }
}

/* Designer Workspace Theme Tokens */
.designer-workspace {
  --ds-bg-dark: #090d16;
  --ds-bg-panel: #111827;
  --ds-bg-subtle: #1f2937;
  --ds-bg-hover: #374151;
  --ds-border: #2d3748;
  --ds-border-light: #4b5563;
  --ds-text: #f9fafb;
  --ds-text-muted: #9ca3af;
  --ds-text-dim: #6b7280;
  --ds-accent: #bf1e2e;
  --ds-accent-hover: #dc2626;
  --ds-cyan: #06b6d4;
  --ds-cyan-glow: rgba(6, 182, 212, 0.4);

  display: flex;
  flex-direction: column;
  height: calc(100vh - 64px);
  background: var(--ds-bg-dark);
  color: var(--ds-text);
  overflow: hidden;
  user-select: none;
  font-family: var(--font-sans, system-ui, -apple-system, sans-serif);
}

/* Top Toolbar */
.designer-toolbar {
  height: 56px;
  background: var(--ds-bg-panel);
  border-bottom: 1px solid var(--ds-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1rem;
  gap: 0.75rem;
  z-index: 30;
  flex-shrink: 0;
}

.designer-toolbar-left,
.designer-toolbar-center,
.designer-toolbar-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.designer-title-block {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.designer-back-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 6px;
  color: var(--ds-text-muted);
  text-decoration: none;
  transition: all 0.15s ease;
}
.designer-back-btn:hover {
  background: var(--ds-bg-subtle);
  color: var(--ds-text);
}

.designer-tpl-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--ds-text);
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.designer-status-badge {
  font-size: 0.7rem;
  padding: 2px 8px;
  border-radius: 12px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.toolbar-sep {
  width: 1px;
  height: 24px;
  background: var(--ds-border);
  margin: 0 0.25rem;
}

.tool-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  height: 34px;
  padding: 0 0.65rem;
  background: var(--ds-bg-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 6px;
  color: var(--ds-text);
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.15s ease;
}
.tool-btn:hover:not(:disabled) {
  background: var(--ds-bg-hover);
  border-color: var(--ds-border-light);
}
.tool-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.tool-btn.is-active {
  background: rgba(6, 182, 212, 0.15);
  border-color: var(--ds-cyan);
  color: var(--ds-cyan);
  box-shadow: 0 0 8px var(--ds-cyan-glow);
}
.tool-btn-primary {
  background: var(--ds-accent);
  border-color: var(--ds-accent);
  color: #ffffff;
  font-weight: 600;
}
.tool-btn-primary:hover:not(:disabled) {
  background: var(--ds-accent-hover);
  border-color: var(--ds-accent-hover);
}

.zoom-badge {
  font-size: 0.75rem;
  font-family: var(--font-mono, monospace);
  font-weight: 600;
  min-width: 44px;
  text-align: center;
  color: var(--ds-text-muted);
}

/* 3-Column Workspace Main Layout */
.designer-body {
  display: grid;
  grid-template-columns: 320px 1fr 320px;
  flex: 1;
  height: calc(100% - 56px);
  overflow: hidden;
}

/* Left & Right Panels */
.designer-panel {
  background: var(--ds-bg-panel);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  z-index: 20;
}
.designer-panel-left {
  border-right: 1px solid var(--ds-border);
}
.designer-panel-right {
  border-left: 1px solid var(--ds-border);
}

/* Panel Tabs */
.panel-tabs {
  display: flex;
  border-bottom: 1px solid var(--ds-border);
  background: rgba(17, 24, 39, 0.8);
  flex-shrink: 0;
}
.panel-tab-btn {
  flex: 1;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--ds-text-muted);
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}
.panel-tab-btn:hover {
  color: var(--ds-text);
  background: rgba(255, 255, 255, 0.02);
}
.panel-tab-btn.is-active {
  color: var(--ds-text);
  border-bottom-color: var(--ds-accent);
  background: rgba(191, 30, 46, 0.05);
}

.panel-content {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.panel-content::-webkit-scrollbar {
  width: 6px;
}
.panel-content::-webkit-scrollbar-thumb {
  background: var(--ds-border);
  border-radius: 3px;
}

/* Section Headings */
.panel-section-title {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--ds-text-muted);
  font-weight: 600;
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* Quick Add Component Buttons Grid */
.add-elements-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
.btn-add-element {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 0.65rem;
  background: var(--ds-bg-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 6px;
  color: var(--ds-text);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}
.btn-add-element:hover {
  background: var(--ds-bg-hover);
  border-color: var(--ds-border-light);
  transform: translateY(-1px);
}
.btn-add-element svg {
  width: 14px;
  height: 14px;
  color: var(--ds-cyan);
  flex-shrink: 0;
}

/* Variable Chips Palette */
.var-group {
  margin-bottom: 0.75rem;
}
.var-group-label {
  font-size: 0.68rem;
  color: var(--ds-text-dim);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.35rem;
}
.var-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.var-chip {
  background: rgba(31, 41, 55, 0.8);
  border: 1px solid var(--ds-border);
  border-radius: 4px;
  padding: 2px 7px;
  font-family: var(--font-mono, monospace);
  font-size: 0.7rem;
  color: #93c5fd;
  cursor: pointer;
  transition: all 0.15s ease;
}
.var-chip:hover {
  background: rgba(59, 130, 246, 0.15);
  border-color: #3b82f6;
  color: #bfdbfe;
  transform: translateY(-1px);
}
.var-chip.is-mandatory {
  color: #fca5a5;
  border-color: rgba(239, 68, 68, 0.4);
}
.var-chip.is-mandatory:hover {
  background: rgba(239, 68, 68, 0.15);
  border-color: #ef4444;
}

/* Asset Upload Cards */
.asset-card {
  background: var(--ds-bg-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 8px;
  padding: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.asset-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.asset-card-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ds-text);
  margin: 0;
}
.asset-preview-thumb {
  width: 100%;
  height: 80px;
  border-radius: 4px;
  background: #000000;
  border: 1px solid var(--ds-border);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
}
.asset-preview-thumb img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.asset-preview-empty {
  font-size: 0.7rem;
  color: var(--ds-text-dim);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}
.asset-actions {
  display: flex;
  gap: 0.5rem;
}
.btn-asset-upload {
  flex: 1;
  padding: 0.35rem 0.5rem;
  font-size: 0.75rem;
  background: var(--ds-bg-hover);
  border: 1px solid var(--ds-border-light);
  border-radius: 4px;
  color: var(--ds-text);
  cursor: pointer;
  text-align: center;
  transition: all 0.15s ease;
}
.btn-asset-upload:hover {
  background: #4b5563;
}
.btn-asset-delete {
  padding: 0.35rem 0.6rem;
  font-size: 0.75rem;
  background: rgba(220, 38, 38, 0.15);
  border: 1px solid rgba(220, 38, 38, 0.3);
  border-radius: 4px;
  color: #f87171;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-asset-delete:hover {
  background: rgba(220, 38, 38, 0.25);
  color: #fca5a5;
}

/* Layer List Items */
.layers-list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.layer-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.45rem 0.65rem;
  background: var(--ds-bg-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 6px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.15s ease;
}
.layer-item:hover {
  background: var(--ds-bg-hover);
  border-color: var(--ds-border-light);
}
.layer-item.is-selected {
  background: rgba(191, 30, 46, 0.15);
  border-color: var(--ds-accent);
  color: #ffffff;
}
.layer-item-info {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  overflow: hidden;
}
.layer-item-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 150px;
}
.layer-item-actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.btn-layer-action {
  background: transparent;
  border: none;
  color: var(--ds-text-dim);
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 3px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-layer-action:hover {
  color: var(--ds-text);
  background: rgba(255, 255, 255, 0.1);
}

/* Center Canvas Workbench Viewport */
.designer-workbench {
  position: relative;
  background: #090d16;
  background-image: radial-gradient(#1e293b 1px, transparent 1px);
  background-size: 24px 24px;
  overflow: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  flex: 1;
}

/* Canvas Viewport Outer Frame */
.canvas-wrapper {
  position: relative;
  width: 2480px;
  height: 1754px;
  transform-origin: center center;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.1);
  background: #ffffff;
  user-select: none;
  flex-shrink: 0;
  transition: transform 0.05s ease-out;
}

/* Canonical Canvas Inner Layer */
.cert-canvas-surface {
  position: absolute;
  inset: 0;
  width: 2480px;
  height: 1754px;
  overflow: hidden;
  background-size: 100% 100%;
  background-position: 0 0;
  background-repeat: no-repeat;
}

/* Default Institutional Frame if no background */
.canvas-default-frame {
  position: absolute;
  inset: 80px;
  border: 16px solid #0f172a;
  pointer-events: none;
}
.canvas-default-inner-frame {
  position: absolute;
  inset: 12px;
  border: 4px solid #bf1e2e;
  pointer-events: none;
}

/* Alignment Guide Lines */
.guide-v-center {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 1240px;
  width: 2px;
  background: var(--ds-cyan);
  box-shadow: 0 0 8px var(--ds-cyan-glow);
  z-index: 999;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.15s ease;
}
.guide-h-center {
  position: absolute;
  left: 0;
  right: 0;
  top: 877px;
  height: 2px;
  background: var(--ds-cyan);
  box-shadow: 0 0 8px var(--ds-cyan-glow);
  z-index: 999;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.15s ease;
}
.guide-visible {
  opacity: 0.9 !important;
}

/* Live Elements on Canvas */
.canvas-element {
  position: absolute;
  cursor: move;
  user-select: none;
  white-space: nowrap;
  box-sizing: border-box;
}

.canvas-element.is-selected {
  outline: 3px dashed #bf1e2e;
  outline-offset: 4px;
  background: rgba(191, 30, 46, 0.04);
}

.element-hud {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%);
  background: #0f172a;
  color: #38bdf8;
  border: 1px solid #0284c7;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 14px;
  font-family: monospace;
  font-weight: 600;
  pointer-events: none;
  white-space: nowrap;
  z-index: 1000;
}

/* Grouped Signature Component Visual Unit */
.signature-component-unit {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  pointer-events: auto;
}
.signature-component-image {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.signature-component-image img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.signature-placeholder-box {
  width: 100%;
  height: 100%;
  border: 2px dashed #cbd5e1;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  font-style: italic;
  background: rgba(248, 250, 252, 0.5);
}
.signature-component-line {
  width: 100%;
  height: 3px;
  background: #cbd5e1;
  margin-top: 10px;
}
.signature-component-name {
  margin-top: 8px;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
}
.signature-component-title {
  margin-top: 4px;
  font-weight: 400;
  color: #64748b;
  line-height: 1.2;
}

/* Official Seal Visual Unit */
.seal-component-unit {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.seal-component-unit img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.seal-placeholder-badge {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  border: 5px double #b48228;
  background: rgba(254, 249, 195, 0.3);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #b48228;
  font-weight: 700;
  text-align: center;
}

/* QR Code Visual Unit */
.qr-component-unit {
  width: 100%;
  height: 100%;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}
.qr-preview-svg {
  width: 85%;
  height: 85%;
}
.qr-caption-text {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
  margin-top: 2px;
}

/* Property Inspector Inputs */
.inspector-form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.75rem;
}
.inspector-form-group label {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--ds-text-muted);
}
.inspector-input,
.inspector-select,
.inspector-textarea {
  background: var(--ds-bg-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 6px;
  color: var(--ds-text);
  font-size: 0.8rem;
  padding: 0.45rem 0.65rem;
  transition: all 0.15s ease;
  width: 100%;
  box-sizing: border-box;
}
.inspector-input:focus,
.inspector-select:focus,
.inspector-textarea:focus {
  outline: none;
  border-color: var(--ds-cyan);
  box-shadow: 0 0 0 2px var(--ds-cyan-glow);
}
.inspector-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
.inspector-row-3 {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 0.5rem;
}

.align-toggle-group {
  display: flex;
  border: 1px solid var(--ds-border);
  border-radius: 6px;
  overflow: hidden;
}
.align-toggle-btn {
  flex: 1;
  height: 32px;
  background: var(--ds-bg-subtle);
  border: none;
  color: var(--ds-text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}
.align-toggle-btn:hover {
  background: var(--ds-bg-hover);
  color: var(--ds-text);
}
.align-toggle-btn.is-active {
  background: var(--ds-cyan);
  color: #090d16;
}

/* Mandatory Variables Checklist Card */
.checklist-card {
  background: var(--ds-bg-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 8px;
  padding: 0.85rem;
}
.checklist-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.35rem 0;
  font-size: 0.75rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.checklist-item:last-child {
  border-bottom: none;
}
.status-pill {
  font-size: 0.68rem;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
}
.status-pill-ok {
  background: rgba(16, 185, 129, 0.2);
  color: #34d399;
}
.status-pill-missing {
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
}

/* Rendered Preview Modal */
.preview-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}
.preview-modal-overlay.is-open {
  display: flex;
}
.preview-modal-card {
  background: #111827;
  border: 1px solid var(--ds-border);
  border-radius: 12px;
  width: 90vw;
  max-width: 1200px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.preview-modal-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--ds-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.preview-modal-body {
  padding: 1.5rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #090d16;
}
.preview-modal-image-wrapper {
  max-width: 100%;
  position: relative;
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  border-radius: 4px;
  overflow: hidden;
}
.preview-modal-image-wrapper img {
  display: block;
  max-width: 100%;
  height: auto;
}
.preview-modal-footer {
  padding: 0.85rem 1.25rem;
  border-top: 1px solid var(--ds-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #111827;
}

/* Toast Notifications */
.designer-toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: #1e293b;
  color: #ffffff;
  border: 1px solid var(--ds-cyan);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
  padding: 0.75rem 1.25rem;
  border-radius: 8px;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  z-index: 2000;
  opacity: 0;
  transform: translateY(10px);
  transition: all 0.2s ease;
  pointer-events: none;
}
.designer-toast.show {
  opacity: 1;
  transform: translateY(0);
}

/* Mobile & Tablet Drawer Adaptations */
@media (max-width: 1024px) {
  .designer-body {
    grid-template-columns: 280px 1fr 280px;
  }
}
@media (max-width: 768px) {
  .designer-body {
    grid-template-columns: 1fr;
    position: relative;
  }
  .designer-panel-left {
    display: none;
  }
  .designer-panel-right {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 45vh;
    border-top: 1px solid var(--ds-border);
    border-left: none;
    box-shadow: 0 -10px 25px rgba(0,0,0,0.5);
  }
  .tool-btn-text {
    display: none;
  }
}
</style>

<div class="designer-workspace">
  <!-- Top Toolbar -->
  <header class="designer-toolbar">
    <!-- Left: Navigation & Template Title -->
    <div class="designer-toolbar-left">
      <a href="<?= e(url('/admin/certificate-templates')) ?>" class="designer-back-btn" title="Back to Templates Directory">
        <?= icon('arrow-left') ?>
      </a>
      <div class="designer-title-block">
        <span class="designer-tpl-name" title="<?= e($template['name']) ?>"><?= e($template['name']) ?></span>
        <span class="designer-status-badge"><?= e($template['status'] ?? 'active') ?></span>
      </div>
    </div>

    <!-- Center: History, Zoom, Snap & Alignments -->
    <div class="designer-toolbar-center">
      <!-- Undo / Redo -->
      <button type="button" class="tool-btn" id="btnUndo" title="Undo (Ctrl+Z)" disabled>
        <?= icon('rotate-ccw') ?>
      </button>
      <button type="button" class="tool-btn" id="btnRedo" title="Redo (Ctrl+Y)" disabled>
        <?= icon('rotate-cw') ?>
      </button>

      <div class="toolbar-sep"></div>

      <!-- Zoom Controls -->
      <button type="button" class="tool-btn" id="btnZoomOut" title="Zoom Out (−)">
        <?= icon('minus') ?>
      </button>
      <span class="zoom-badge" id="zoomLevelDisplay">45%</span>
      <button type="button" class="tool-btn" id="btnZoomIn" title="Zoom In (+)">
        <?= icon('plus') ?>
      </button>
      <button type="button" class="tool-btn" id="btnZoomFit" title="Fit to Viewport">
        Fit
      </button>
      <button type="button" class="tool-btn" id="btnZoom100" title="100% Actual Scale">
        1:1
      </button>

      <div class="toolbar-sep"></div>

      <!-- Snap to Center Toggle -->
      <button type="button" class="tool-btn is-active" id="btnToggleSnap" title="Snap to Center Guides (X=1240, Y=877)">
        <?= icon('crosshair') ?>
        <span class="tool-btn-text">Snap: ON</span>
      </button>

      <!-- Quick Alignment Actions -->
      <button type="button" class="tool-btn" id="btnAlignCenterX" title="Align Active Element to Center X (1240px)">
        <?= icon('align-center') ?>
        <span class="tool-btn-text">Center X</span>
      </button>
    </div>

    <!-- Right: Preview & Save Actions -->
    <div class="designer-toolbar-right">
      <button type="button" class="tool-btn" id="btnOpenPreviewModal">
        <?= icon('eye') ?>
        <span>Rendered Preview</span>
      </button>
      <button type="button" class="tool-btn tool-btn-primary" id="btnSaveDesigner">
        <?= icon('save') ?>
        <span id="saveBtnText">Save Layout</span>
      </button>
    </div>
  </header>

  <!-- 3-Column Main Workspace -->
  <div class="designer-body">
    <!-- Left Sidebar: Elements, Assets, Layers -->
    <aside class="designer-panel designer-panel-left">
      <div class="panel-tabs">
        <button type="button" class="panel-tab-btn is-active" data-tab="tab-elements">
          <?= icon('plus-circle') ?>
          <span>Elements</span>
        </button>
        <button type="button" class="panel-tab-btn" data-tab="tab-assets">
          <?= icon('image') ?>
          <span>Assets</span>
        </button>
        <button type="button" class="panel-tab-btn" data-tab="tab-layers">
          <?= icon('layers') ?>
          <span>Layers</span>
        </button>
      </div>

      <!-- TAB 1: Elements & Dynamic Variables -->
      <div class="panel-content" id="tab-elements">
        <div>
          <h4 class="panel-section-title">Add Components</h4>
          <div class="add-elements-grid">
            <button type="button" class="btn-add-element" id="btnAddText">
              <?= icon('type') ?>
              <span>Body Text</span>
            </button>
            <button type="button" class="btn-add-element" id="btnAddHeading">
              <?= icon('award') ?>
              <span>Heading</span>
            </button>
            <button type="button" class="btn-add-element" id="btnAddSeal">
              <?= icon('shield') ?>
              <span>Official Seal</span>
            </button>
            <button type="button" class="btn-add-element" id="btnAddQr">
              <?= icon('maximize') ?>
              <span>QR Code</span>
            </button>
            <button type="button" class="btn-add-element" id="btnAddSig1">
              <?= icon('edit-3') ?>
              <span>Signature 1</span>
            </button>
            <button type="button" class="btn-add-element" id="btnAddSig2">
              <?= icon('edit-3') ?>
              <span>Signature 2</span>
            </button>
          </div>
        </div>

        <div>
          <h4 class="panel-section-title">Dynamic Variables</h4>
          <p style="font-size: 0.7rem; color: var(--ds-text-dim); margin-top: -0.25rem; margin-bottom: 0.5rem;">
            Click to insert into selected text or create a new dynamic element:
          </p>

          <!-- Recipient Group -->
          <div class="var-group">
            <div class="var-group-label">Recipient (Personalization)</div>
            <div class="var-chips">
              <span class="var-chip is-mandatory" data-tag="{{name}}" title="Mandatory recipient full name">{{name}} *</span>
              <span class="var-chip" data-tag="{{email}}">{{email}}</span>
              <span class="var-chip" data-tag="{{volunteer_id}}">{{volunteer_id}}</span>
              <span class="var-chip" data-tag="{{hours}}">{{hours}}</span>
            </div>
          </div>

          <!-- Certificate Group -->
          <div class="var-group">
            <div class="var-group-label">Certificate Metadata</div>
            <div class="var-chips">
              <span class="var-chip" data-tag="{{certificate_number}}">{{certificate_number}}</span>
              <span class="var-chip" data-tag="{{issue_date}}">{{issue_date}}</span>
              <span class="var-chip" data-tag="{{expiry_date}}">{{expiry_date}}</span>
              <span class="var-chip" data-tag="{{verification_url}}" title="Dynamic canonical public verification link">{{verification_url}}</span>
            </div>
          </div>

          <!-- Event Group -->
          <div class="var-group">
            <div class="var-group-label">Event &amp; Program Context</div>
            <div class="var-chips">
              <span class="var-chip" data-tag="{{event_title}}">{{event_title}}</span>
              <span class="var-chip" data-tag="{{date}}">{{date}}</span>
              <span class="var-chip" data-tag="{{place}}">{{place}}</span>
              <span class="var-chip" data-tag="{{role}}">{{role}}</span>
              <span class="var-chip" data-tag="{{workshop_name}}">{{workshop_name}}</span>
              <span class="var-chip" data-tag="{{duration}}">{{duration}}</span>
              <span class="var-chip" data-tag="{{issued_by}}">{{issued_by}}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: Template Assets (Strict Option A: PNG/WebP/JPG Only) -->
      <div class="panel-content" id="tab-assets" style="display: none;">
        <!-- Background Asset Card -->
        <div class="asset-card">
          <div class="asset-card-header">
            <span class="asset-card-title">Background Image</span>
            <span style="font-size: 0.68rem; color: var(--ds-text-dim);">2480 &times; 1754 px</span>
          </div>
          <div class="asset-preview-thumb" id="bgThumbContainer">
            <?php if ($activeBackground): ?>
              <img src="<?= e($activeBackground) ?>" id="bgThumbImg" alt="Background Preview">
            <?php else: ?>
              <div class="asset-preview-empty" id="bgThumbEmpty">
                <?= icon('image') ?>
                <span>Default Border Active</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="asset-actions">
            <button type="button" class="btn-asset-upload" onclick="triggerAssetUpload('background')">
              <?= $activeBackground ? 'Replace Image' : 'Upload Image' ?>
            </button>
            <?php if ($activeBackground): ?>
              <button type="button" class="btn-asset-delete" onclick="deleteAsset('background')">Remove</button>
            <?php endif; ?>
          </div>
          <span style="font-size: 0.65rem; color: var(--ds-text-dim);">JPG, PNG, WebP (max 5MB). Strictly NO SVG for security.</span>
        </div>

        <!-- Official Seal Asset Card -->
        <div class="asset-card">
          <div class="asset-card-header">
            <span class="asset-card-title">Official Seal / Emblem</span>
            <span style="font-size: 0.68rem; color: var(--ds-text-dim);">Transparent PNG</span>
          </div>
          <div class="asset-preview-thumb" id="sealThumbContainer">
            <?php if ($activeSeal): ?>
              <img src="<?= e($activeSeal) ?>" id="sealThumbImg" alt="Seal Preview">
            <?php else: ?>
              <div class="asset-preview-empty" id="sealThumbEmpty">
                <?= icon('shield') ?>
                <span>Gold Badge Placeholder</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="asset-actions">
            <button type="button" class="btn-asset-upload" onclick="triggerAssetUpload('seal')">
              <?= $activeSeal ? 'Replace Seal' : 'Upload Seal' ?>
            </button>
            <?php if ($activeSeal): ?>
              <button type="button" class="btn-asset-delete" onclick="deleteAsset('seal')">Remove</button>
            <?php endif; ?>
          </div>
          <span style="font-size: 0.65rem; color: var(--ds-text-dim);">PNG or WebP with alpha transparency (max 3MB).</span>
        </div>

        <!-- Signature 1 Asset Card -->
        <div class="asset-card">
          <div class="asset-card-header">
            <span class="asset-card-title">Signature 1 (Grouped)</span>
          </div>
          <div class="asset-preview-thumb" id="sig1ThumbContainer">
            <?php if ($activeSig1): ?>
              <img src="<?= e($activeSig1) ?>" id="sig1ThumbImg" alt="Signature 1 Preview">
            <?php else: ?>
              <div class="asset-preview-empty" id="sig1ThumbEmpty">
                <?= icon('edit-3') ?>
                <span>Signature 1 Image</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="asset-actions">
            <button type="button" class="btn-asset-upload" onclick="triggerAssetUpload('signature1')">
              <?= $activeSig1 ? 'Replace Image' : 'Upload Image' ?>
            </button>
            <?php if ($activeSig1): ?>
              <button type="button" class="btn-asset-delete" onclick="deleteAsset('signature1')">Remove</button>
            <?php endif; ?>
          </div>
          <div style="display: flex; flex-direction: column; gap: 0.35rem; margin-top: 0.25rem;">
            <input type="text" id="assetSig1Name" class="inspector-input" placeholder="Signatory 1 Name" value="<?= e($template['signature1_name'] ?? '') ?>" oninput="syncSignatureMetadata()">
            <input type="text" id="assetSig1Title" class="inspector-input" placeholder="Signatory 1 Title / Designation" value="<?= e($template['signature1_designation'] ?? '') ?>" oninput="syncSignatureMetadata()">
          </div>
        </div>

        <!-- Signature 2 Asset Card -->
        <div class="asset-card">
          <div class="asset-card-header">
            <span class="asset-card-title">Signature 2 (Grouped)</span>
          </div>
          <div class="asset-preview-thumb" id="sig2ThumbContainer">
            <?php if ($activeSig2): ?>
              <img src="<?= e($activeSig2) ?>" id="sig2ThumbImg" alt="Signature 2 Preview">
            <?php else: ?>
              <div class="asset-preview-empty" id="sig2ThumbEmpty">
                <?= icon('edit-3') ?>
                <span>Signature 2 Image</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="asset-actions">
            <button type="button" class="btn-asset-upload" onclick="triggerAssetUpload('signature2')">
              <?= $activeSig2 ? 'Replace Image' : 'Upload Image' ?>
            </button>
            <?php if ($activeSig2): ?>
              <button type="button" class="btn-asset-delete" onclick="deleteAsset('signature2')">Remove</button>
            <?php endif; ?>
          </div>
          <div style="display: flex; flex-direction: column; gap: 0.35rem; margin-top: 0.25rem;">
            <input type="text" id="assetSig2Name" class="inspector-input" placeholder="Signatory 2 Name" value="<?= e($template['signature2_name'] ?? '') ?>" oninput="syncSignatureMetadata()">
            <input type="text" id="assetSig2Title" class="inspector-input" placeholder="Signatory 2 Title / Designation" value="<?= e($template['signature2_designation'] ?? '') ?>" oninput="syncSignatureMetadata()">
          </div>
        </div>

        <!-- Hidden input for file picking -->
        <input type="file" id="hiddenAssetInput" style="display: none;" accept=".png,.webp,.jpg,.jpeg">
      </div>

      <!-- TAB 3: Layer Hierarchy -->
      <div class="panel-content" id="tab-layers" style="display: none;">
        <h4 class="panel-section-title">Element Layers (Z-Index)</h4>
        <div class="layers-list" id="layersListContainer">
          <!-- Populated dynamically by JS -->
        </div>
      </div>
    </aside>

    <!-- Center Canvas Workbench Viewport -->
    <main class="designer-workbench" id="workbenchViewport">
      <!-- 2480 x 1754 px Canonical Canvas Representation -->
      <div class="canvas-wrapper" id="canvasWrapper">
        <!-- Canvas Surface Layer -->
        <div class="cert-canvas-surface" id="certCanvasSurface" style="<?= $activeBackground ? 'background-image: url(\'' . e($activeBackground) . '\');' : '' ?>">
          <?php if (!$activeBackground): ?>
            <div class="canvas-default-frame" id="canvasDefaultFrame">
              <div class="canvas-default-inner-frame"></div>
            </div>
          <?php endif; ?>

          <!-- Center Alignment Snap Guide Lines -->
          <div class="guide-v-center" id="guideVCenter"></div>
          <div class="guide-h-center" id="guideHCenter"></div>

          <!-- Dynamic Elements Container -->
          <div id="elementsContainer" style="position: absolute; inset: 0;"></div>
        </div>
      </div>
    </main>

    <!-- Right Sidebar: Contextual Property Inspector -->
    <aside class="designer-panel designer-panel-right">
      <div class="panel-tabs">
        <div class="panel-tab-btn is-active" style="cursor: default;">
          <?= icon('sliders') ?>
          <span>Properties</span>
        </div>
      </div>

      <div class="panel-content" id="inspectorContent">
        <!-- Panel A: Default / Canvas Overview (When no element selected) -->
        <div id="inspectorDefaultOverview">
          <h4 class="panel-section-title">Canvas Overview</h4>
          <div class="checklist-card mb-3">
            <div class="checklist-item">
              <span style="color: var(--ds-text-muted);">Standard Resolution</span>
              <span style="font-family: monospace; font-weight: 600;">2480 &times; 1754 px</span>
            </div>
            <div class="checklist-item">
              <span style="color: var(--ds-text-muted);">Output Media</span>
              <span>A4 Landscape @ 300 DPI</span>
            </div>
            <div class="checklist-item">
              <span style="color: var(--ds-text-muted);">Total Elements</span>
              <span id="overviewTotalElements" style="font-weight: 600;">0</span>
            </div>
          </div>

          <h4 class="panel-section-title">Compliance &amp; Verification</h4>
          <div class="checklist-card">
            <div class="checklist-item">
              <span>Mandatory: {{name}}</span>
              <span class="status-pill status-pill-missing" id="chkNameStatus">Checking...</span>
            </div>
            <div class="checklist-item">
              <span>QR Code Verification</span>
              <span class="status-pill status-pill-missing" id="chkQrStatus">Checking...</span>
            </div>
            <div class="checklist-item">
              <span>Certificate ID Element</span>
              <span class="status-pill status-pill-missing" id="chkIdStatus">Checking...</span>
            </div>
          </div>

          <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; font-size: 0.75rem; color: #93c5fd; line-height: 1.4;">
            <strong>Pro Tip:</strong> Click any element on the canvas to inspect and edit its typography, alignment, and coordinates. Use arrow keys to nudge by 1px (or Shift+Arrow for 10px).
          </div>
        </div>

        <!-- Panel B: Text / Dynamic Text Inspector -->
        <div id="inspectorTextPanel" style="display: none;">
          <h4 class="panel-section-title">Typography &amp; Content</h4>

          <div class="inspector-form-group">
            <label>Text Content / Template</label>
            <textarea id="propTextContent" class="inspector-textarea" rows="3" placeholder="Enter text or variables"></textarea>
          </div>

          <div class="inspector-form-group">
            <label>Font Family</label>
            <select id="propFontFamily" class="inspector-select">
              <option value="arial">Arial (Sans-Serif)</option>
              <option value="montserrat">Montserrat (Modern Sans)</option>
              <option value="cinzel">Cinzel (Formal Institutional)</option>
              <option value="playfair">Playfair Display (Academic Serif)</option>
              <option value="georgia">Georgia (Classic Serif)</option>
              <option value="times">Times New Roman</option>
              <option value="courier">Courier New (Monospace)</option>
            </select>
          </div>

          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>Font Size (pt)</label>
              <input type="number" id="propFontSize" class="inspector-input" min="8" max="140" value="28">
            </div>
            <div class="inspector-form-group">
              <label>Color (Hex)</label>
              <div style="display: flex; gap: 0.35rem; align-items: center;">
                <input type="color" id="propColorPicker" style="width: 36px; height: 32px; padding: 0; border: none; background: transparent; cursor: pointer; border-radius: 4px;">
                <input type="text" id="propColorHex" class="inspector-input" style="font-family: monospace;" value="#0F172A">
              </div>
            </div>
          </div>

          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>Font Weight</label>
              <select id="propFontWeight" class="inspector-select">
                <option value="normal">Regular (400)</option>
                <option value="bold">Bold (700)</option>
              </select>
            </div>
            <div class="inspector-form-group">
              <label>Alignment</label>
              <div class="align-toggle-group">
                <button type="button" class="align-toggle-btn" data-align="left" title="Align Left">L</button>
                <button type="button" class="align-toggle-btn is-active" data-align="center" title="Align Center">C</button>
                <button type="button" class="align-toggle-btn" data-align="right" title="Align Right">R</button>
              </div>
            </div>
          </div>

          <h4 class="panel-section-title" style="margin-top: 0.75rem;">Layout Coordinates</h4>
          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>X Position (px)</label>
              <input type="number" id="propTextX" class="inspector-input" min="0" max="2480">
            </div>
            <div class="inspector-form-group">
              <label>Y Position (px)</label>
              <input type="number" id="propTextY" class="inspector-input" min="0" max="1754">
            </div>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="tool-btn" id="btnPropDuplicate" style="flex: 1;">
              <?= icon('copy') ?>
              <span>Duplicate</span>
            </button>
            <button type="button" class="tool-btn" id="btnPropDelete" style="color: #f87171; border-color: rgba(239, 68, 68, 0.4);" title="Delete element">
              <?= icon('trash-2') ?>
              <span>Delete</span>
            </button>
          </div>
        </div>

        <!-- Panel C: Grouped Signature Component Inspector -->
        <div id="inspectorSigPanel" style="display: none;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <h4 class="panel-section-title" style="margin: 0;">Grouped Signature</h4>
            <span class="status-pill status-pill-ok" id="sigTypeBadge">Sig 1</span>
          </div>

          <div class="inspector-form-group">
            <label>Signatory Name</label>
            <input type="text" id="propSigName" class="inspector-input" placeholder="e.g. Program Coordinator">
          </div>

          <div class="inspector-form-group">
            <label>Signatory Designation / Title</label>
            <input type="text" id="propSigTitle" class="inspector-input" placeholder="e.g. Academic Lead, LC-SPC">
          </div>

          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>Image Width (px)</label>
              <input type="number" id="propSigWidth" class="inspector-input" min="100" max="500" value="220">
            </div>
            <div class="inspector-form-group">
              <label>Image Height (px)</label>
              <input type="number" id="propSigHeight" class="inspector-input" min="30" max="200" value="80">
            </div>
          </div>

          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>Name Size (pt)</label>
              <input type="number" id="propSigNameSize" class="inspector-input" min="12" max="36" value="22">
            </div>
            <div class="inspector-form-group">
              <label>Title Size (pt)</label>
              <input type="number" id="propSigTitleSize" class="inspector-input" min="10" max="28" value="18">
            </div>
          </div>

          <div class="inspector-form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
              <input type="checkbox" id="propSigShowLine" checked>
              <span>Show Divider Line</span>
            </label>
          </div>

          <h4 class="panel-section-title" style="margin-top: 0.75rem;">Anchor Coordinates</h4>
          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>Center X (px)</label>
              <input type="number" id="propSigX" class="inspector-input" min="0" max="2480">
            </div>
            <div class="inspector-form-group">
              <label>Top Y (px)</label>
              <input type="number" id="propSigY" class="inspector-input" min="0" max="1754">
            </div>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="tool-btn" id="btnSigUploadImage" style="flex: 1;">
              <?= icon('upload') ?>
              <span>Upload Image</span>
            </button>
            <button type="button" class="tool-btn" id="btnSigDelete" style="color: #f87171; border-color: rgba(239, 68, 68, 0.4);" title="Delete signature">
              <?= icon('trash-2') ?>
            </button>
          </div>
        </div>

        <!-- Panel D: QR Code Inspector -->
        <div id="inspectorQrPanel" style="display: none;">
          <h4 class="panel-section-title">QR Code Verification</h4>

          <div class="inspector-form-group">
            <label>Size (Square px)</label>
            <input type="number" id="propQrSize" class="inspector-input" min="80" max="400" value="180">
          </div>

          <div class="inspector-form-group">
            <label>Dynamic URL Payload</label>
            <input type="text" id="propQrUrl" class="inspector-input" value="{{verification_url}}" style="font-family: monospace;" readonly>
            <span style="font-size: 0.65rem; color: var(--ds-text-dim); margin-top: 0.25rem;">
              Resolved dynamically at certificate generation time as: <code>https://teami.in/LC/certificates/verify/[token]</code>. Never exposes raw database IDs.
            </span>
          </div>

          <div class="inspector-form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
              <input type="checkbox" id="propQrShowBorder" checked>
              <span>Show Outer Framing Border</span>
            </label>
          </div>

          <h4 class="panel-section-title" style="margin-top: 0.75rem;">Coordinates</h4>
          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>Center X (px)</label>
              <input type="number" id="propQrX" class="inspector-input" min="0" max="2480">
            </div>
            <div class="inspector-form-group">
              <label>Top Y (px)</label>
              <input type="number" id="propQrY" class="inspector-input" min="0" max="1754">
            </div>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="tool-btn" id="btnQrDelete" style="color: #f87171; border-color: rgba(239, 68, 68, 0.4); width: 100%;">
              <?= icon('trash-2') ?>
              <span>Remove QR Element</span>
            </button>
          </div>
        </div>

        <!-- Panel E: Official Seal Inspector -->
        <div id="inspectorSealPanel" style="display: none;">
          <h4 class="panel-section-title">Official Seal</h4>

          <div class="inspector-form-group">
            <label>Size (Square px)</label>
            <input type="number" id="propSealSize" class="inspector-input" min="60" max="400" value="160">
          </div>

          <h4 class="panel-section-title" style="margin-top: 0.75rem;">Coordinates</h4>
          <div class="inspector-row-2">
            <div class="inspector-form-group">
              <label>Center X (px)</label>
              <input type="number" id="propSealX" class="inspector-input" min="0" max="2480">
            </div>
            <div class="inspector-form-group">
              <label>Top Y (px)</label>
              <input type="number" id="propSealY" class="inspector-input" min="0" max="1754">
            </div>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="tool-btn" onclick="triggerAssetUpload('seal')" style="flex: 1;">
              <?= icon('upload') ?>
              <span>Upload Seal</span>
            </button>
            <button type="button" class="tool-btn" id="btnSealDelete" style="color: #f87171; border-color: rgba(239, 68, 68, 0.4);">
              <?= icon('trash-2') ?>
            </button>
          </div>
        </div>
      </div>
    </aside>
  </div>
</div>

<!-- Rendered Backend Preview Modal -->
<div class="preview-modal-overlay" id="renderedPreviewModal">
  <div class="preview-modal-card">
    <div class="preview-modal-header">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <?= icon('shield', ['style' => 'color: var(--ds-cyan);']) ?>
        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #ffffff;">Backend Rendered Certificate Preview</h3>
      </div>
      <button type="button" class="tool-btn" id="btnClosePreviewModal" style="width: 32px; height: 32px; padding: 0;">
        <?= icon('x') ?>
      </button>
    </div>
    <div class="preview-modal-body">
      <div class="preview-modal-image-wrapper">
        <img id="renderedPreviewImg" src="" alt="Live Engine Preview">
      </div>
    </div>
    <div class="preview-modal-footer">
      <div style="font-size: 0.75rem; color: var(--ds-text-dim);">
        Rendered via Canonical <code>CertificateRenderer</code> GD Engine &bull; Exact 2480 &times; 1754 px Output
      </div>
      <div style="display: flex; gap: 0.5rem;">
        <a href="<?= e(url('/admin/certificate-templates/' . $template['id'] . '/preview?format=pdf')) ?>" target="_blank" class="tool-btn">
          <?= icon('file-text') ?>
          <span>Download Test PDF</span>
        </a>
        <a href="<?= e(url('/admin/certificate-templates/' . $template['id'] . '/preview')) ?>" target="_blank" class="tool-btn">
          <?= icon('external-link') ?>
          <span>Open Full Size</span>
        </a>
        <button type="button" class="tool-btn tool-btn-primary" id="btnRefreshModalPreview">
          <?= icon('refresh-cw') ?>
          <span>Refresh</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notification -->
<div class="designer-toast" id="designerToast">
  <?= icon('check-circle', ['style' => 'color: var(--ds-cyan);']) ?>
  <span id="designerToastMsg">Layout saved successfully</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Canonical Resolution Constants
  const CANVAS_WIDTH = 2480;
  const CANVAS_HEIGHT = 1754;
  const CENTER_X = 1240;
  const CENTER_Y = 877;
  const SNAP_THRESHOLD = 12;

  // Template State
  const templateId = <?= json_encode((int) $template['id']) ?>;
  const dummyData = <?= json_encode($dummyData) ?>;
  let templateAssets = {
    background: <?= json_encode($activeBackground) ?>,
    seal: <?= json_encode($activeSeal) ?>,
    signature1: <?= json_encode($activeSig1) ?>,
    signature2: <?= json_encode($activeSig2) ?>,
    sig1Name: <?= json_encode($template['signature1_name'] ?? '') ?>,
    sig1Title: <?= json_encode($template['signature1_designation'] ?? '') ?>,
    sig2Name: <?= json_encode($template['signature2_name'] ?? '') ?>,
    sig2Title: <?= json_encode($template['signature2_designation'] ?? '') ?>,
  };

  let elements = <?= json_encode($elements) ?>;
  let selectedIndex = null;
  let snapEnabled = true;
  let currentZoom = 0.45;

  // History Stack for Undo / Redo
  const historyStack = [];
  let historyIndex = -1;

  function pushHistory() {
    if (historyIndex < historyStack.length - 1) {
      historyStack.splice(historyIndex + 1);
    }
    historyStack.push(JSON.stringify(elements));
    if (historyStack.length > 30) historyStack.shift();
    historyIndex = historyStack.length - 1;
    updateUndoRedoButtons();
  }

  function updateUndoRedoButtons() {
    document.getElementById('btnUndo').disabled = historyIndex <= 0;
    document.getElementById('btnRedo').disabled = historyIndex >= historyStack.length - 1;
  }

  document.getElementById('btnUndo').addEventListener('click', () => {
    if (historyIndex > 0) {
      historyIndex--;
      elements = JSON.parse(historyStack[historyIndex]);
      renderCanvas();
      updateInspector();
      renderLayers();
      updateUndoRedoButtons();
    }
  });

  document.getElementById('btnRedo').addEventListener('click', () => {
    if (historyIndex < historyStack.length - 1) {
      historyIndex++;
      elements = JSON.parse(historyStack[historyIndex]);
      renderCanvas();
      updateInspector();
      renderLayers();
      updateUndoRedoButtons();
    }
  });

  // DOM Elements
  const workbench = document.getElementById('workbenchViewport');
  const canvasWrapper = document.getElementById('canvasWrapper');
  const elementsContainer = document.getElementById('elementsContainer');
  const guideVCenter = document.getElementById('guideVCenter');
  const guideHCenter = document.getElementById('guideHCenter');
  const zoomDisplay = document.getElementById('zoomLevelDisplay');

  // Zoom & Viewport Scaling
  function applyZoom(scale) {
    currentZoom = Math.min(Math.max(scale, 0.15), 1.5);
    canvasWrapper.style.transform = `scale(${currentZoom})`;
    zoomDisplay.textContent = Math.round(currentZoom * 100) + '%';
  }

  function fitToViewport() {
    const pad = 80;
    const availW = workbench.clientWidth - pad;
    const availH = workbench.clientHeight - pad;
    const scale = Math.min(availW / CANVAS_WIDTH, availH / CANVAS_HEIGHT);
    applyZoom(scale);
  }

  window.addEventListener('resize', () => {
    // Keep fit if near bounds
    if (currentZoom < 0.6) fitToViewport();
  });

  document.getElementById('btnZoomIn').addEventListener('click', () => applyZoom(currentZoom + 0.05));
  document.getElementById('btnZoomOut').addEventListener('click', () => applyZoom(currentZoom - 0.05));
  document.getElementById('btnZoomFit').addEventListener('click', fitToViewport);
  document.getElementById('btnZoom100').addEventListener('click', () => applyZoom(1.0));

  // Snap Toggle
  const btnToggleSnap = document.getElementById('btnToggleSnap');
  btnToggleSnap.addEventListener('click', () => {
    snapEnabled = !snapEnabled;
    btnToggleSnap.classList.toggle('is-active', snapEnabled);
    btnToggleSnap.querySelector('.tool-btn-text').textContent = snapEnabled ? 'Snap: ON' : 'Snap: OFF';
    showToast(snapEnabled ? 'Snap guides enabled (1240 / 877)' : 'Snap guides disabled');
  });

  // Center X Action
  document.getElementById('btnAlignCenterX').addEventListener('click', () => {
    if (selectedIndex !== null && elements[selectedIndex]) {
      elements[selectedIndex].x = CENTER_X;
      renderCanvas();
      updateInspector();
      pushHistory();
      showToast('Aligned element to Center X (1240px)');
    }
  });

  // Variable Replacement for Live Preview
  function replaceVars(text) {
    if (!text) return '';
    return text.replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, (match, key) => {
      return dummyData[key] !== undefined ? dummyData[key] : match;
    });
  }

  // Render Elements on Canvas Surface
  function renderCanvas() {
    elementsContainer.innerHTML = '';

    elements.forEach((el, index) => {
      if (el.visible === false) return;

      const elDiv = document.createElement('div');
      elDiv.className = 'canvas-element' + (selectedIndex === index ? ' is-selected' : '');
      elDiv.dataset.index = index;

      const x = parseInt(el.x ?? CENTER_X, 10);
      const y = parseInt(el.y ?? 400, 10);
      const align = el.align || 'center';

      switch (el.type) {
        case 'text':
        case 'dynamic_text': {
          const fontSize = el.font_size || 28;
          const fontWeight = el.font_weight || 'normal';
          const fontStyle = el.font_style || 'normal';
          const color = el.color || '#0F172A';
          const family = resolveFontFamilyCss(el.font_family || 'arial');

          elDiv.style.fontFamily = family;
          elDiv.style.fontSize = `${fontSize}px`;
          elDiv.style.fontWeight = fontWeight;
          elDiv.style.fontStyle = fontStyle;
          elDiv.style.color = color;
          elDiv.style.textAlign = align;
          elDiv.style.top = `${y}px`;
          elDiv.style.transform = 'translateY(-80%)'; // Baseline calibration matching GD imagettftext
          elDiv.textContent = replaceVars(el.text || '');

          if (el.max_width) {
            elDiv.style.maxWidth = `${el.max_width}px`;
            elDiv.style.whiteSpace = 'normal';
          }

          elementsContainer.appendChild(elDiv);

          // Position calculation based on alignment
          const rect = elDiv.getBoundingClientRect();
          const unscaledW = rect.width / currentZoom;
          let drawX = x;
          if (align === 'center') {
            drawX = x - (unscaledW / 2);
          } else if (align === 'right') {
            drawX = x - unscaledW;
          }
          elDiv.style.left = `${drawX}px`;
          break;
        }

        case 'seal': {
          const size = parseInt(el.size || 160, 10);
          elDiv.style.width = `${size}px`;
          elDiv.style.height = `${size}px`;
          elDiv.style.left = `${x - (size / 2)}px`;
          elDiv.style.top = `${y}px`;

          const sealUnit = document.createElement('div');
          sealUnit.className = 'seal-component-unit';
          if (templateAssets.seal) {
            sealUnit.innerHTML = `<img src="${templateAssets.seal}" alt="Seal">`;
          } else {
            sealUnit.innerHTML = `<div class="seal-placeholder-badge"><span>OFFICIAL</span><span style="font-size: 11px;">SEAL</span></div>`;
          }
          elDiv.appendChild(sealUnit);
          elementsContainer.appendChild(elDiv);
          break;
        }

        case 'qr_code': {
          const size = parseInt(el.size || 180, 10);
          elDiv.style.width = `${size}px`;
          elDiv.style.height = `${size}px`;
          elDiv.style.left = `${x - (size / 2)}px`;
          elDiv.style.top = `${y}px`;

          const qrUnit = document.createElement('div');
          qrUnit.className = 'qr-component-unit';
          if (el.show_border !== false) {
            qrUnit.style.border = '2px solid #CBD5E1';
          }
          qrUnit.innerHTML = `
            <svg class="qr-preview-svg" viewBox="0 0 100 100" fill="#0F172A">
              <rect x="10" y="10" width="24" height="24" fill="#0F172A"/>
              <rect x="14" y="14" width="16" height="16" fill="#FFFFFF"/>
              <rect x="18" y="18" width="8" height="8" fill="#0F172A"/>
              <rect x="66" y="10" width="24" height="24" fill="#0F172A"/>
              <rect x="70" y="14" width="16" height="16" fill="#FFFFFF"/>
              <rect x="74" y="18" width="8" height="8" fill="#0F172A"/>
              <rect x="10" y="66" width="24" height="24" fill="#0F172A"/>
              <rect x="14" y="70" width="16" height="16" fill="#FFFFFF"/>
              <rect x="18" y="74" width="8" height="8" fill="#0F172A"/>
              <rect x="42" y="14" width="16" height="16" fill="#0F172A"/>
              <rect x="42" y="42" width="16" height="16" fill="#0F172A"/>
              <rect x="66" y="42" width="24" height="16" fill="#0F172A"/>
              <rect x="42" y="66" width="16" height="24" fill="#0F172A"/>
              <rect x="66" y="74" width="16" height="16" fill="#0F172A"/>
            </svg>
            <span class="qr-caption-text">VERIFY AUTHENTICITY</span>
          `;
          elDiv.appendChild(qrUnit);
          elementsContainer.appendChild(elDiv);
          break;
        }

        case 'signature1':
        case 'signature2': {
          const isSig1 = el.type === 'signature1';
          const width = parseInt(el.width || 220, 10);
          const height = parseInt(el.height || 80, 10);
          const name = isSig1 ? (templateAssets.sig1Name || el.name || 'Program Coordinator') : (templateAssets.sig2Name || el.name || 'Executive Director');
          const title = isSig1 ? (templateAssets.sig1Title || el.title || 'Academic Lead, LC-SPC') : (templateAssets.sig2Title || el.title || 'Campaign Director, LC-SPC');
          const imgSrc = isSig1 ? templateAssets.signature1 : templateAssets.signature2;
          const nameFontSize = parseInt(el.name_font_size || 22, 10);
          const titleFontSize = parseInt(el.title_font_size || 18, 10);
          const showLine = el.show_line !== false;

          elDiv.style.width = `${width}px`;
          elDiv.style.left = `${x - (width / 2)}px`;
          elDiv.style.top = `${y}px`;

          const sigGroup = document.createElement('div');
          sigGroup.className = 'signature-component-unit';

          const imgBox = document.createElement('div');
          imgBox.className = 'signature-component-image';
          imgBox.style.height = `${height}px`;
          if (imgSrc) {
            imgBox.innerHTML = `<img src="${imgSrc}" alt="Signature">`;
          } else {
            imgBox.innerHTML = `<div class="signature-placeholder-box">${isSig1 ? 'Signature 1' : 'Signature 2'}</div>`;
          }
          sigGroup.appendChild(imgBox);

          if (showLine) {
            const line = document.createElement('div');
            line.className = 'signature-component-line';
            sigGroup.appendChild(line);
          }

          if (name) {
            const nameEl = document.createElement('div');
            nameEl.className = 'signature-component-name';
            nameEl.style.fontSize = `${nameFontSize}px`;
            nameEl.textContent = name;
            sigGroup.appendChild(nameEl);
          }

          if (title) {
            const titleEl = document.createElement('div');
            titleEl.className = 'signature-component-title';
            titleEl.style.fontSize = `${titleFontSize}px`;
            titleEl.textContent = title;
            sigGroup.appendChild(titleEl);
          }

          elDiv.appendChild(sigGroup);
          elementsContainer.appendChild(elDiv);
          break;
        }
      }

      // Selection & Drag Interaction
      attachDragHandlers(elDiv, el, index);
    });

    updateOverviewChecklist();
  }

  function resolveFontFamilyCss(family) {
    const f = (family || '').toLowerCase();
    if (f.includes('cinzel')) return "'Cinzel', serif";
    if (f.includes('montserrat')) return "'Montserrat', sans-serif";
    if (f.includes('playfair')) return "'Playfair Display', serif";
    if (f.includes('times')) return "'Times New Roman', serif";
    if (f.includes('georgia')) return "Georgia, serif";
    if (f.includes('courier')) return "'Courier New', monospace";
    return "'Google Sans Flex', Arial, sans-serif";
  }

  // Drag & Snap Handling
  function attachDragHandlers(domEl, el, index) {
    domEl.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return;
      e.stopPropagation();
      selectElement(index);

      if (el.locked) return;

      const startMouseX = e.clientX;
      const startMouseY = e.clientY;
      const initialX = el.x;
      const initialY = el.y;

      // Create Drag HUD Badge
      const hud = document.createElement('div');
      hud.className = 'element-hud';
      hud.textContent = `X: ${initialX} | Y: ${initialY}`;
      domEl.appendChild(hud);

      function onMouseMove(moveEvent) {
        const dx = (moveEvent.clientX - startMouseX) / currentZoom;
        const dy = (moveEvent.clientY - startMouseY) / currentZoom;

        let newX = Math.round(initialX + dx);
        let newY = Math.round(initialY + dy);

        // Snap Guides Check
        if (snapEnabled) {
          if (Math.abs(newX - CENTER_X) <= SNAP_THRESHOLD) {
            newX = CENTER_X;
            guideVCenter.classList.add('guide-visible');
          } else {
            guideVCenter.classList.remove('guide-visible');
          }

          if (Math.abs(newY - CENTER_Y) <= SNAP_THRESHOLD) {
            newY = CENTER_Y;
            guideHCenter.classList.add('guide-visible');
          } else {
            guideHCenter.classList.remove('guide-visible');
          }
        }

        el.x = newX;
        el.y = newY;
        hud.textContent = `X: ${newX} | Y: ${newY}`;

        renderCanvas();
        updateInspector();
      }

      function onMouseUp() {
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('mouseup', onMouseUp);
        guideVCenter.classList.remove('guide-visible');
        guideHCenter.classList.remove('guide-visible');
        hud.remove();
        pushHistory();
      }

      window.addEventListener('mousemove', onMouseMove);
      window.addEventListener('mouseup', onMouseUp);
    });
  }

  // Selection Management
  function selectElement(index) {
    selectedIndex = index;
    renderCanvas();
    updateInspector();
    renderLayers();
  }

  // Deselect on Workbench Click
  workbench.addEventListener('click', (e) => {
    if (e.target === workbench || e.target === canvasWrapper || e.target === document.getElementById('certCanvasSurface')) {
      selectedIndex = null;
      renderCanvas();
      updateInspector();
      renderLayers();
    }
  });

  // Contextual Inspector Panels
  function updateInspector() {
    const defaultPanel = document.getElementById('inspectorDefaultOverview');
    const textPanel = document.getElementById('inspectorTextPanel');
    const sigPanel = document.getElementById('inspectorSigPanel');
    const qrPanel = document.getElementById('inspectorQrPanel');
    const sealPanel = document.getElementById('inspectorSealPanel');

    // Hide all
    defaultPanel.style.display = 'none';
    textPanel.style.display = 'none';
    sigPanel.style.display = 'none';
    qrPanel.style.display = 'none';
    sealPanel.style.display = 'none';

    if (selectedIndex === null || !elements[selectedIndex]) {
      defaultPanel.style.display = 'block';
      return;
    }

    const el = elements[selectedIndex];

    if (el.type === 'text' || el.type === 'dynamic_text') {
      textPanel.style.display = 'block';
      document.getElementById('propTextContent').value = el.text || '';
      document.getElementById('propFontFamily').value = el.font_family || 'arial';
      document.getElementById('propFontSize').value = el.font_size || 28;
      document.getElementById('propFontWeight').value = el.font_weight || 'normal';
      document.getElementById('propColorHex').value = el.color || '#0F172A';
      document.getElementById('propColorPicker').value = el.color || '#0F172A';
      document.getElementById('propTextX').value = el.x ?? CENTER_X;
      document.getElementById('propTextY').value = el.y ?? 400;

      document.querySelectorAll('.align-toggle-btn').forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.align === (el.align || 'center'));
      });
    } else if (el.type === 'signature1' || el.type === 'signature2') {
      sigPanel.style.display = 'block';
      const isSig1 = el.type === 'signature1';
      document.getElementById('sigTypeBadge').textContent = isSig1 ? 'Signature 1' : 'Signature 2';
      document.getElementById('propSigName').value = isSig1 ? (templateAssets.sig1Name || el.name || '') : (templateAssets.sig2Name || el.name || '');
      document.getElementById('propSigTitle').value = isSig1 ? (templateAssets.sig1Title || el.title || '') : (templateAssets.sig2Title || el.title || '');
      document.getElementById('propSigWidth').value = el.width || 220;
      document.getElementById('propSigHeight').value = el.height || 80;
      document.getElementById('propSigNameSize').value = el.name_font_size || 22;
      document.getElementById('propSigTitleSize').value = el.title_font_size || 18;
      document.getElementById('propSigShowLine').checked = el.show_line !== false;
      document.getElementById('propSigX').value = el.x ?? (isSig1 ? 500 : 1980);
      document.getElementById('propSigY').value = el.y ?? 1260;
    } else if (el.type === 'qr_code') {
      qrPanel.style.display = 'block';
      document.getElementById('propQrSize').value = el.size || 180;
      document.getElementById('propQrShowBorder').checked = el.show_border !== false;
      document.getElementById('propQrX').value = el.x ?? CENTER_X;
      document.getElementById('propQrY').value = el.y ?? 1240;
    } else if (el.type === 'seal') {
      sealPanel.style.display = 'block';
      document.getElementById('propSealSize').value = el.size || 160;
      document.getElementById('propSealX').value = el.x ?? CENTER_X;
      document.getElementById('propSealY').value = el.y ?? 1050;
    }
  }

  // Live Inspector Event Listeners (Two-Way Binding)
  document.getElementById('propTextContent').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].text = e.target.value;
    renderCanvas();
    renderLayers();
  });

  document.getElementById('propFontFamily').addEventListener('change', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].font_family = e.target.value;
    renderCanvas();
    pushHistory();
  });

  document.getElementById('propFontSize').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].font_size = parseInt(e.target.value, 10) || 28;
    renderCanvas();
  });

  document.getElementById('propFontWeight').addEventListener('change', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].font_weight = e.target.value;
    renderCanvas();
    pushHistory();
  });

  document.getElementById('propColorPicker').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    document.getElementById('propColorHex').value = e.target.value;
    elements[selectedIndex].color = e.target.value;
    renderCanvas();
  });

  document.getElementById('propColorHex').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].color = e.target.value;
    document.getElementById('propColorPicker').value = e.target.value;
    renderCanvas();
  });

  document.querySelectorAll('.align-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (selectedIndex === null) return;
      elements[selectedIndex].align = btn.dataset.align;
      document.querySelectorAll('.align-toggle-btn').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      renderCanvas();
      pushHistory();
    });
  });

  document.getElementById('propTextX').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].x = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  document.getElementById('propTextY').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].y = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  // Signature Inspector Listeners
  document.getElementById('propSigName').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    const isSig1 = elements[selectedIndex].type === 'signature1';
    if (isSig1) {
      templateAssets.sig1Name = e.target.value;
      document.getElementById('assetSig1Name').value = e.target.value;
    } else {
      templateAssets.sig2Name = e.target.value;
      document.getElementById('assetSig2Name').value = e.target.value;
    }
    elements[selectedIndex].name = e.target.value;
    renderCanvas();
  });

  document.getElementById('propSigTitle').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    const isSig1 = elements[selectedIndex].type === 'signature1';
    if (isSig1) {
      templateAssets.sig1Title = e.target.value;
      document.getElementById('assetSig1Title').value = e.target.value;
    } else {
      templateAssets.sig2Title = e.target.value;
      document.getElementById('assetSig2Title').value = e.target.value;
    }
    elements[selectedIndex].title = e.target.value;
    renderCanvas();
  });

  document.getElementById('propSigWidth').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].width = parseInt(e.target.value, 10) || 220;
    renderCanvas();
  });

  document.getElementById('propSigHeight').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].height = parseInt(e.target.value, 10) || 80;
    renderCanvas();
  });

  document.getElementById('propSigNameSize').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].name_font_size = parseInt(e.target.value, 10) || 22;
    renderCanvas();
  });

  document.getElementById('propSigTitleSize').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].title_font_size = parseInt(e.target.value, 10) || 18;
    renderCanvas();
  });

  document.getElementById('propSigShowLine').addEventListener('change', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].show_line = e.target.checked;
    renderCanvas();
    pushHistory();
  });

  document.getElementById('propSigX').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].x = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  document.getElementById('propSigY').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].y = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  // QR Inspector Listeners
  document.getElementById('propQrSize').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].size = parseInt(e.target.value, 10) || 180;
    renderCanvas();
  });

  document.getElementById('propQrShowBorder').addEventListener('change', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].show_border = e.target.checked;
    renderCanvas();
    pushHistory();
  });

  document.getElementById('propQrX').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].x = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  document.getElementById('propQrY').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].y = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  // Seal Inspector Listeners
  document.getElementById('propSealSize').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].size = parseInt(e.target.value, 10) || 160;
    renderCanvas();
  });

  document.getElementById('propSealX').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].x = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  document.getElementById('propSealY').addEventListener('input', (e) => {
    if (selectedIndex === null) return;
    elements[selectedIndex].y = parseInt(e.target.value, 10) || 0;
    renderCanvas();
  });

  // Duplicate & Delete Element Actions
  function deleteActiveElement() {
    if (selectedIndex === null || !elements[selectedIndex]) return;
    elements.splice(selectedIndex, 1);
    selectedIndex = null;
    renderCanvas();
    updateInspector();
    renderLayers();
    pushHistory();
    showToast('Element removed');
  }

  function duplicateActiveElement() {
    if (selectedIndex === null || !elements[selectedIndex]) return;
    const cloned = JSON.parse(JSON.stringify(elements[selectedIndex]));
    cloned.x = (cloned.x || CENTER_X) + 40;
    cloned.y = (cloned.y || 400) + 40;
    elements.push(cloned);
    selectElement(elements.length - 1);
    pushHistory();
    showToast('Element duplicated');
  }

  document.getElementById('btnPropDelete').addEventListener('click', deleteActiveElement);
  document.getElementById('btnPropDuplicate').addEventListener('click', duplicateActiveElement);
  document.getElementById('btnSigDelete').addEventListener('click', deleteActiveElement);
  document.getElementById('btnQrDelete').addEventListener('click', deleteActiveElement);
  document.getElementById('btnSealDelete').addEventListener('click', deleteActiveElement);

  // Add Component Buttons (Left Panel)
  document.getElementById('btnAddText').addEventListener('click', () => {
    elements.push({
      type: 'text',
      text: 'New Paragraph Text',
      x: CENTER_X,
      y: 900,
      font_size: 28,
      font_family: 'arial',
      font_weight: 'normal',
      color: '#0F172A',
      align: 'center',
    });
    selectElement(elements.length - 1);
    pushHistory();
  });

  document.getElementById('btnAddHeading').addEventListener('click', () => {
    elements.push({
      type: 'text',
      text: 'CERTIFICATE OF MERIT',
      x: CENTER_X,
      y: 400,
      font_size: 48,
      font_family: 'cinzel',
      font_weight: 'bold',
      color: '#BF1E2E',
      align: 'center',
    });
    selectElement(elements.length - 1);
    pushHistory();
  });

  document.getElementById('btnAddSeal').addEventListener('click', () => {
    const existing = elements.findIndex(e => e.type === 'seal');
    if (existing !== -1) {
      selectElement(existing);
      showToast('Official seal already placed on canvas');
      return;
    }
    elements.push({
      type: 'seal',
      x: CENTER_X,
      y: 1050,
      size: 160,
    });
    selectElement(elements.length - 1);
    pushHistory();
  });

  document.getElementById('btnAddQr').addEventListener('click', () => {
    const existing = elements.findIndex(e => e.type === 'qr_code');
    if (existing !== -1) {
      selectElement(existing);
      showToast('QR code already placed on canvas');
      return;
    }
    elements.push({
      type: 'qr_code',
      x: CENTER_X,
      y: 1240,
      size: 180,
      url_template: '{{verification_url}}',
      show_border: true,
    });
    selectElement(elements.length - 1);
    pushHistory();
  });

  document.getElementById('btnAddSig1').addEventListener('click', () => {
    const existing = elements.findIndex(e => e.type === 'signature1');
    if (existing !== -1) {
      selectElement(existing);
      return;
    }
    elements.push({
      type: 'signature1',
      x: 500,
      y: 1260,
      width: 220,
      height: 80,
      name: templateAssets.sig1Name || 'Program Coordinator',
      title: templateAssets.sig1Title || 'Academic Lead, LC-SPC',
      show_line: true,
      name_font_size: 22,
      title_font_size: 18,
    });
    selectElement(elements.length - 1);
    pushHistory();
  });

  document.getElementById('btnAddSig2').addEventListener('click', () => {
    const existing = elements.findIndex(e => e.type === 'signature2');
    if (existing !== -1) {
      selectElement(existing);
      return;
    }
    elements.push({
      type: 'signature2',
      x: 1980,
      y: 1260,
      width: 220,
      height: 80,
      name: templateAssets.sig2Name || 'Executive Director',
      title: templateAssets.sig2Title || 'Campaign Director, LC-SPC',
      show_line: true,
      name_font_size: 22,
      title_font_size: 18,
    });
    selectElement(elements.length - 1);
    pushHistory();
  });

  // Dynamic Variable Insertion
  document.querySelectorAll('.var-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const tag = chip.dataset.tag;
      if (selectedIndex !== null && (elements[selectedIndex].type === 'text' || elements[selectedIndex].type === 'dynamic_text')) {
        const txtEl = document.getElementById('propTextContent');
        const start = txtEl.selectionStart || 0;
        const end = txtEl.selectionEnd || 0;
        const oldVal = txtEl.value;
        const newVal = oldVal.substring(0, start) + ' ' + tag + ' ' + oldVal.substring(end);
        txtEl.value = newVal;
        elements[selectedIndex].text = newVal;
        renderCanvas();
        renderLayers();
        pushHistory();
      } else {
        elements.push({
          type: 'dynamic_text',
          text: tag,
          x: CENTER_X,
          y: 800,
          font_size: 32,
          font_family: 'arial',
          font_weight: tag.includes('name') ? 'bold' : 'normal',
          color: '#0F172A',
          align: 'center',
        });
        selectElement(elements.length - 1);
        pushHistory();
      }
    });
  });

  // Tab Switching in Left Sidebar
  document.querySelectorAll('.panel-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.panel-tab-btn').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');

      const target = btn.dataset.tab;
      document.getElementById('tab-elements').style.display = target === 'tab-elements' ? 'flex' : 'none';
      document.getElementById('tab-assets').style.display = target === 'tab-assets' ? 'flex' : 'none';
      document.getElementById('tab-layers').style.display = target === 'tab-layers' ? 'flex' : 'none';
    });
  });

  // Layers List Rendering
  function renderLayers() {
    const list = document.getElementById('layersListContainer');
    list.innerHTML = '';

    elements.forEach((el, index) => {
      const item = document.createElement('div');
      item.className = 'layer-item' + (selectedIndex === index ? ' is-selected' : '');

      let typeIcon = 'type';
      let label = el.text || el.type;
      if (el.type === 'seal') { typeIcon = 'shield'; label = 'Official Seal'; }
      if (el.type === 'qr_code') { typeIcon = 'maximize'; label = 'QR Verification'; }
      if (el.type === 'signature1') { typeIcon = 'edit-3'; label = `Sig 1: ${el.name || 'Signatory'}`; }
      if (el.type === 'signature2') { typeIcon = 'edit-3'; label = `Sig 2: ${el.name || 'Signatory'}`; }

      item.innerHTML = `
        <div class="layer-item-info">
          <span class="layer-item-label">${escapeHtml(label)}</span>
        </div>
        <div class="layer-item-actions">
          <button type="button" class="btn-layer-action btn-up" title="Move Up">&uarr;</button>
          <button type="button" class="btn-layer-action btn-down" title="Move Down">&darr;</button>
          <button type="button" class="btn-layer-action btn-del" style="color: #f87171;" title="Delete">&times;</button>
        </div>
      `;

      item.addEventListener('click', (e) => {
        if (!e.target.closest('.btn-layer-action')) {
          selectElement(index);
        }
      });

      item.querySelector('.btn-up').addEventListener('click', (e) => {
        e.stopPropagation();
        if (index < elements.length - 1) {
          const temp = elements[index];
          elements[index] = elements[index + 1];
          elements[index + 1] = temp;
          selectedIndex = index + 1;
          renderCanvas();
          renderLayers();
          pushHistory();
        }
      });

      item.querySelector('.btn-down').addEventListener('click', (e) => {
        e.stopPropagation();
        if (index > 0) {
          const temp = elements[index];
          elements[index] = elements[index - 1];
          elements[index - 1] = temp;
          selectedIndex = index - 1;
          renderCanvas();
          renderLayers();
          pushHistory();
        }
      });

      item.querySelector('.btn-del').addEventListener('click', (e) => {
        e.stopPropagation();
        elements.splice(index, 1);
        if (selectedIndex === index) selectedIndex = null;
        renderCanvas();
        updateInspector();
        renderLayers();
        pushHistory();
      });

      list.appendChild(item);
    });
  }

  // Overview Checklist Validation
  function updateOverviewChecklist() {
    let hasName = false;
    let hasQr = false;
    let hasId = false;

    elements.forEach(el => {
      const txt = el.text || '';
      if (txt.includes('{{name}}')) hasName = true;
      if (txt.includes('{{certificate_number}}')) hasId = true;
      if (el.type === 'qr_code') hasQr = true;
    });

    document.getElementById('overviewTotalElements').textContent = elements.length;

    const setStatus = (id, ok, okText = 'Included') => {
      const el = document.getElementById(id);
      if (!el) return;
      el.className = 'status-pill ' + (ok ? 'status-pill-ok' : 'status-pill-missing');
      el.textContent = ok ? okText : 'Missing!';
    };

    setStatus('chkNameStatus', hasName);
    setStatus('chkQrStatus', hasQr);
    setStatus('chkIdStatus', hasId);
  }

  // Synchronize Signature Name & Title Inputs
  window.syncSignatureMetadata = function() {
    templateAssets.sig1Name = document.getElementById('assetSig1Name').value;
    templateAssets.sig1Title = document.getElementById('assetSig1Title').value;
    templateAssets.sig2Name = document.getElementById('assetSig2Name').value;
    templateAssets.sig2Title = document.getElementById('assetSig2Title').value;

    elements.forEach(el => {
      if (el.type === 'signature1') {
        el.name = templateAssets.sig1Name;
        el.title = templateAssets.sig1Title;
      }
      if (el.type === 'signature2') {
        el.name = templateAssets.sig2Name;
        el.title = templateAssets.sig2Title;
      }
    });

    renderCanvas();
    updateInspector();
  };

  // Asynchronous Asset Upload (Strict Option A: PNG/WebP/JPG Only)
  let activeAssetUploadType = null;
  const fileInput = document.getElementById('hiddenAssetInput');

  window.triggerAssetUpload = function(assetType) {
    activeAssetUploadType = assetType;
    fileInput.value = '';
    if (assetType === 'background') {
      fileInput.accept = '.jpg,.jpeg,.png,.webp';
    } else {
      fileInput.accept = '.png,.webp,.jpg,.jpeg';
    }
    fileInput.click();
  };

  fileInput.addEventListener('change', async () => {
    if (!fileInput.files || !fileInput.files[0] || !activeAssetUploadType) return;
    const file = fileInput.files[0];

    // Client-side extension check
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext === 'svg') {
      alert('Security Policy: SVG uploads are strictly prohibited. Please upload PNG or WebP images.');
      return;
    }

    const formData = new FormData();
    formData.append('asset_type', activeAssetUploadType);
    formData.append('asset_file', file);
    formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    showToast(`Uploading ${activeAssetUploadType}...`);

    try {
      const res = await fetch(`<?= e(url('/admin/certificate-templates/' . $template['id'] . '/assets')) ?>`, {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();
      if (data.status === 'success') {
        showToast(data.message);
        templateAssets[activeAssetUploadType] = data.url;

        if (activeAssetUploadType === 'background') {
          document.getElementById('certCanvasSurface').style.backgroundImage = `url('${data.url}')`;
          const frame = document.getElementById('canvasDefaultFrame');
          if (frame) frame.style.display = 'none';

          const thumb = document.getElementById('bgThumbContainer');
          thumb.innerHTML = `<img src="${data.url}" id="bgThumbImg" alt="Background Preview">`;
        } else if (activeAssetUploadType === 'seal') {
          const thumb = document.getElementById('sealThumbContainer');
          thumb.innerHTML = `<img src="${data.url}" id="sealThumbImg" alt="Seal Preview">`;
        } else if (activeAssetUploadType === 'signature1') {
          const thumb = document.getElementById('sig1ThumbContainer');
          thumb.innerHTML = `<img src="${data.url}" id="sig1ThumbImg" alt="Sig1 Preview">`;
        } else if (activeAssetUploadType === 'signature2') {
          const thumb = document.getElementById('sig2ThumbContainer');
          thumb.innerHTML = `<img src="${data.url}" id="sig2ThumbImg" alt="Sig2 Preview">`;
        }

        renderCanvas();
      } else {
        alert(data.error || 'Asset upload failed.');
      }
    } catch (err) {
      alert('Upload request failed: ' + err.message);
    }
  });

  // Asynchronous Asset Delete
  window.deleteAsset = async function(assetType) {
    if (!confirm(`Are you sure you want to remove the template ${assetType}?`)) return;

    const formData = new FormData();
    formData.append('asset_type', assetType);
    formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    try {
      const res = await fetch(`<?= e(url('/admin/certificate-templates/' . $template['id'] . '/assets/delete')) ?>`, {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();
      if (data.status === 'success') {
        showToast(data.message);
        templateAssets[assetType] = null;

        if (assetType === 'background') {
          document.getElementById('certCanvasSurface').style.backgroundImage = 'none';
          const frame = document.getElementById('canvasDefaultFrame');
          if (frame) frame.style.display = 'block';
          document.getElementById('bgThumbContainer').innerHTML = `
            <div class="asset-preview-empty" id="bgThumbEmpty">
              <?= icon('image') ?>
              <span>Default Border Active</span>
            </div>
          `;
        }
        renderCanvas();
      } else {
        alert(data.error || 'Failed to remove asset.');
      }
    } catch (err) {
      alert('Delete request failed: ' + err.message);
    }
  };

  // Save Designer Layout
  document.getElementById('btnSaveDesigner').addEventListener('click', async () => {
    // Mandatory variables check: name must be present
    let hasName = false;
    elements.forEach(el => {
      const t = el.text || '';
      if (t.includes('{{name}}')) hasName = true;
    });

    if (!hasName) {
      alert('Validation Error: The certificate template MUST contain the {{name}} element before saving.');
      return;
    }

    const saveBtn = document.getElementById('btnSaveDesigner');
    const saveTxt = document.getElementById('saveBtnText');
    saveBtn.disabled = true;
    saveTxt.textContent = 'Saving...';

    const formData = new FormData();
    formData.append('layout_config', JSON.stringify({ elements: elements }));
    formData.append('signature1_name', templateAssets.sig1Name || '');
    formData.append('signature1_designation', templateAssets.sig1Title || '');
    formData.append('signature2_name', templateAssets.sig2Name || '');
    formData.append('signature2_designation', templateAssets.sig2Title || '');
    formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    try {
      const res = await fetch(`<?= e(url('/admin/certificate-templates/' . $template['id'] . '/designer')) ?>`, {
        method: 'POST',
        body: formData,
      });
      const json = await res.json();
      if (json.status === 'success') {
        showToast('Layout and settings saved successfully!');
      } else {
        alert(json.error || 'Failed to save layout.');
      }
    } catch (err) {
      alert('Error communicating with server: ' + err.message);
    } finally {
      saveBtn.disabled = false;
      saveTxt.textContent = 'Save Layout';
    }
  });

  // Rendered Preview Modal
  const previewModal = document.getElementById('renderedPreviewModal');
  const previewImg = document.getElementById('renderedPreviewImg');

  function openPreviewModal() {
    const streamUrl = `<?= e(url('/admin/certificate-templates/' . $template['id'] . '/preview')) ?>?t=` + Date.now();
    previewImg.src = streamUrl;
    previewModal.classList.add('is-open');
  }

  document.getElementById('btnOpenPreviewModal').addEventListener('click', openPreviewModal);
  document.getElementById('btnClosePreviewModal').addEventListener('click', () => previewModal.classList.remove('is-open'));
  document.getElementById('btnRefreshModalPreview').addEventListener('click', () => {
    previewImg.src = `<?= e(url('/admin/certificate-templates/' . $template['id'] . '/preview')) ?>?t=` + Date.now();
  });

  previewModal.addEventListener('click', (e) => {
    if (e.target === previewModal) previewModal.classList.remove('is-open');
  });

  // Keyboard Shortcuts
  window.addEventListener('keydown', (e) => {
    // If typing in input or textarea, don't trigger canvas shortcuts
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
      return;
    }

    if (e.key === 'Escape') {
      if (previewModal.classList.contains('is-open')) {
        previewModal.classList.remove('is-open');
      } else {
        selectedIndex = null;
        renderCanvas();
        updateInspector();
        renderLayers();
      }
    }

    // Ctrl+S: Save Layout
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      document.getElementById('btnSaveDesigner').click();
    }

    // Ctrl+Z: Undo
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('btnUndo').click();
    }

    // Ctrl+Y or Ctrl+Shift+Z: Redo
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) {
      e.preventDefault();
      document.getElementById('btnRedo').click();
    }

    // Delete / Backspace: Remove Element
    if ((e.key === 'Delete' || e.key === 'Backspace') && selectedIndex !== null) {
      e.preventDefault();
      deleteActiveElement();
    }

    // Arrow keys nudge
    if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key) && selectedIndex !== null) {
      e.preventDefault();
      const step = e.shiftKey ? 10 : 1;
      const el = elements[selectedIndex];
      if (e.key === 'ArrowUp') el.y = (el.y || 400) - step;
      if (e.key === 'ArrowDown') el.y = (el.y || 400) + step;
      if (e.key === 'ArrowLeft') el.x = (el.x || CENTER_X) - step;
      if (e.key === 'ArrowRight') el.x = (el.x || CENTER_X) + step;
      renderCanvas();
      updateInspector();
    }
  });

  // Helper: Toast Notifications
  function showToast(msg) {
    const toast = document.getElementById('designerToast');
    const toastMsg = document.getElementById('designerToastMsg');
    toastMsg.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[m]));
  }

  // Initial Boot
  pushHistory();
  fitToViewport();
  renderCanvas();
  renderLayers();
  updateInspector();
});
</script>
