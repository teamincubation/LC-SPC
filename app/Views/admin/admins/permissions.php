<?php
  /** @var array $admin */
  /** @var array $groupedPerms */
  /** @var array $assignedIds */
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= e(url('/admin/admins')) ?>" class="text-white-50 text-decoration-none small">&larr; Back to Admins</a>
        <span class="text-white-50">&bull;</span>
        <span class="badge bg-danger">SUPER ADMIN ONLY</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Module Permissions Assignment</h2>
      <p class="text-white-50 mb-0">Manage granular module permissions for <strong><?= e($admin['name']) ?></strong> (<?= e(\App\Services\RoleService::getRoleLabel($admin['role'] ?? 'viewer')) ?>)</p>
    </div>
  </div>
</div>

<?php if (($admin['role'] ?? '') === 'super_admin'): ?>
  <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-shield-check fs-3 text-primary"></i>
    <div>
      <strong>Super Administrator Full Bypass Active</strong><br>
      <span class="small">Users with the <strong>Super Administrator</strong> role automatically bypass all permission checks and possess unrestricted access across all system modules.</span>
    </div>
  </div>
<?php endif; ?>

<form action="<?= e(url('/admin/admins/' . $admin['id'] . '/permissions')) ?>" method="POST">
  <?= csrf_field() ?>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
      <h5 class="card-title fw-bold mb-0 text-dark">Module Action Permissions Matrix</h5>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelectAll">Select All</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAll">Deselect All</button>
      </div>
    </div>
    <div class="card-body p-4">
      <div class="row g-4">
        <?php foreach ($groupedPerms as $moduleName => $permissions): ?>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 border bg-light">
              <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark small text-uppercase">
                  <?= e(str_replace('_', ' ', $moduleName)) ?>
                </span>
                <span class="badge bg-light text-muted border small"><?= count($permissions) ?> actions</span>
              </div>
              <div class="card-body p-3 d-flex flex-column gap-2">
                <?php foreach ($permissions as $p): 
                  $isChecked = in_array((int) $p['id'], $assignedIds, true);
                ?>
                  <div class="form-check">
                    <input class="form-check-input perm-checkbox" 
                           type="checkbox" 
                           name="permissions[]" 
                           value="<?= (int) $p['id'] ?>" 
                           id="perm_<?= (int) $p['id'] ?>"
                           <?= $isChecked ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="perm_<?= (int) $p['id'] ?>">
                      <strong><?= e(ucfirst($p['action'])) ?></strong>
                      <?php if (!empty($p['description'])): ?>
                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($p['description']) ?></div>
                      <?php endif; ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center">
      <a href="<?= e(url('/admin/admins')) ?>" class="btn btn-light border btn-sm">Cancel</a>
      <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm">Save Permissions</button>
    </div>
  </div>
</form>

<script>
  document.getElementById('btnSelectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
  });
  document.getElementById('btnDeselectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
  });
</script>
