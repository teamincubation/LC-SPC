<?php
  /** @var array $groupedPerms */
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= e(url('/admin/admins')) ?>" class="text-white-50 text-decoration-none small">&larr; Back to Admins</a>
        <span class="text-white-50">&bull;</span>
        <span class="badge bg-danger">SUPER ADMIN ONLY</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Create Administrator</h2>
      <p class="text-white-50 mb-0">Provision a new operational administrator account.</p>
    </div>
  </div>
</div>

<div class="mx-auto" style="max-width: 800px;">
  <form action="<?= e(url('/admin/admins')) ?>" method="POST">
    <?= csrf_field() ?>

    <!-- Section 1: Personal Information -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom">
        <h5 class="card-title fw-bold mb-0 text-dark small text-uppercase" style="letter-spacing: 0.05em;">Personal Information</h5>
      </div>
      <div class="card-body p-4">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. John Doe">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" required placeholder="admin@example.com">
        </div>

        <div class="mb-0">
          <label class="form-label fw-semibold small">Phone / Mobile (Optional)</label>
          <input type="tel" name="phone" class="form-control" placeholder="+91 9876543210">
        </div>
      </div>
    </div>

    <!-- Section 2: Security -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom">
        <h5 class="card-title fw-bold mb-0 text-dark small text-uppercase" style="letter-spacing: 0.05em;">Security</h5>
      </div>
      <div class="card-body p-4">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Initial Password <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
          <div class="form-text small">Must be at least 8 characters in length.</div>
        </div>

        <div class="mb-0">
          <label class="form-label fw-semibold small">Confirm Password <span class="text-danger">*</span></label>
          <input type="password" name="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat password">
        </div>
      </div>
    </div>

    <!-- Section 3: Permissions -->
    <?php if (!empty($groupedPerms)): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title fw-bold mb-0 text-dark small text-uppercase" style="letter-spacing: 0.05em;">Permissions</h5>
            <div class="text-muted" style="font-size: 0.8rem;">Configure granular module permissions for this Administrator account.</div>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelectAll">Select All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAll">Deselect All</button>
          </div>
        </div>
        <div class="card-body p-4">
          <div class="row g-4">
            <?php foreach ($groupedPerms as $moduleName => $moduleData): ?>
              <div class="col-md-6">
                <div class="card h-100 border bg-light">
                  <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase">
                      <?= e($moduleData['label'] ?? ucfirst(str_replace('_', ' ', $moduleName))) ?>
                    </span>
                    <span class="badge bg-light text-muted border small"><?= count($moduleData['permissions'] ?? []) ?> actions</span>
                  </div>
                  <div class="card-body p-3 d-flex flex-column gap-2">
                    <?php foreach (($moduleData['permissions'] ?? []) as $p): ?>
                      <div class="form-check">
                        <input class="form-check-input perm-checkbox" 
                               type="checkbox" 
                               name="permissions[]" 
                               value="<?= (int) $p['id'] ?>" 
                               id="create_perm_<?= (int) $p['id'] ?>">
                        <label class="form-check-label small" for="create_perm_<?= (int) $p['id'] ?>">
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
      </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="d-flex justify-content-end gap-2 mb-5">
      <a href="<?= e(url('/admin/admins')) ?>" class="btn btn-light border px-4">Cancel</a>
      <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm">Create Administrator</button>
    </div>
  </form>
</div>

<script>
  document.getElementById('btnSelectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
  });
  document.getElementById('btnDeselectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
  });
</script>
