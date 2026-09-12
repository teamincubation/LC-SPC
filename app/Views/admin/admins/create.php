<?php
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
      <h2 class="h3 fw-bold mb-1 text-white">Create Administrator</h2>
      <p class="text-white-50 mb-0">Provision a new operational administrator account.</p>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mx-auto" style="max-width: 650px;">
  <div class="card-body p-4">
    <form action="<?= e(url('/admin/admins')) ?>" method="POST">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required placeholder="e.g. John Doe">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" required placeholder="admin@example.com">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Phone / Mobile (Optional)</label>
        <input type="tel" name="phone" class="form-control" placeholder="+91 9876543210">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Initial Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold small">System Role <span class="text-danger">*</span></label>
        <select name="role" class="form-select">
          <?php foreach ($roles as $roleKey => $roleLabel): ?>
            <option value="<?= e($roleKey) ?>" <?= $roleKey === 'staff' ? 'selected' : '' ?>>
              <?= e($roleLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text small">Role determines baseline system access and permission bounds.</div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="<?= e(url('/admin/admins')) ?>" class="btn btn-light border">Cancel</a>
        <button type="submit" class="btn btn-primary fw-semibold px-4">Create Account</button>
      </div>
    </form>
  </div>
</div>
