<?php
  /** @var array $admin */
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= e(url('/admin/admins')) ?>" class="text-white-50 text-decoration-none small">&larr; Back to Admins</a>
        <span class="text-white-50">&bull;</span>
        <span class="badge bg-danger">SUPER ADMIN ONLY</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Reset Administrator Password</h2>
      <p class="text-white-50 mb-0">Direct credential reset for <?= e($admin['name']) ?> (<?= e($admin['email']) ?>)</p>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mx-auto" style="max-width: 550px;">
  <div class="card-body p-4">
    <form action="<?= e(url('/admin/admins/' . $admin['id'] . '/password')) ?>" method="POST">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat new password">
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="<?= e(url('/admin/admins')) ?>" class="btn btn-light border">Cancel</a>
        <button type="submit" class="btn btn-danger fw-semibold px-4">Update Password</button>
      </div>
    </form>
  </div>
</div>
