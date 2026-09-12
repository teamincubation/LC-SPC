<?php
  /** @var array $admin */
  /** @var array $roles */
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= e(url('/admin/admins')) ?>" class="text-white-50 text-decoration-none small">&larr; Back to Admins</a>
        <span class="text-white-50">&bull;</span>
        <span class="badge bg-danger">SUPER ADMIN ONLY</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Edit Administrator Profile</h2>
      <p class="text-white-50 mb-0"><?= e($admin['name']) ?> (<?= e($admin['email']) ?>)</p>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mx-auto" style="max-width: 650px;">
  <div class="card-body p-4">
    <form action="<?= e(url('/admin/admins/' . $admin['id'])) ?>" method="POST">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($admin['name']) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" required value="<?= e($admin['email']) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Phone / Mobile</label>
        <input type="tel" name="phone" class="form-control" value="<?= e($admin['phone'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">System Role <span class="text-danger">*</span></label>
        <select name="role" class="form-select">
          <?php foreach ($roles as $roleKey => $roleLabel): ?>
            <option value="<?= e($roleKey) ?>" <?= ($admin['role'] ?? '') === $roleKey ? 'selected' : '' ?>>
              <?= e($roleLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold small">Account Status <span class="text-danger">*</span></label>
        <select name="status" class="form-select">
          <option value="active" <?= ($admin['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ($admin['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="suspended" <?= ($admin['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <a href="<?= e(url('/admin/admins/' . $admin['id'] . '/password')) ?>" class="text-danger text-decoration-none small">
          <i class="bi bi-key me-1"></i> Reset Password
        </a>
        <div class="d-flex gap-2">
          <a href="<?= e(url('/admin/admins')) ?>" class="btn btn-light border">Cancel</a>
          <button type="submit" class="btn btn-primary fw-semibold px-4">Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>
