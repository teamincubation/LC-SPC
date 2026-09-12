<?php
  /** @var array $admins */
?>

<div class="card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border: none;">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge bg-danger">SUPER ADMIN ONLY</span>
        <span class="text-white-50">&bull;</span>
        <span class="text-white-50 small">Access Governance</span>
      </div>
      <h2 class="h3 fw-bold mb-1 text-white">Administrator Management</h2>
      <p class="text-white-50 mb-0">Manage system administrators, roles, status, security credentials, and granular module permissions.</p>
    </div>
    <a href="<?= e(url('/admin/admins/create')) ?>" class="btn btn-primary fw-semibold shadow-sm">
      <i class="bi bi-person-plus-fill me-1"></i> Add Administrator
    </a>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
    <h5 class="card-title fw-bold mb-0 text-dark">System Administrators</h5>
    <span class="badge bg-light text-dark border"><?= count($admins) ?> Total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th>Administrator</th>
          <th>Contact</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody class="small">
        <?php foreach ($admins as $admin): ?>
          <tr>
            <td>
              <div class="fw-bold text-dark"><?= e($admin['name']) ?></div>
              <div class="text-muted small"><?= e($admin['email']) ?></div>
            </td>
            <td>
              <?= !empty($admin['phone']) ? e($admin['phone']) : '<span class="text-muted">—</span>' ?>
            </td>
            <td>
              <?php 
                $roleBadge = match ($admin['role'] ?? 'viewer') {
                  'super_admin' => 'bg-danger',
                  'admin'       => 'bg-primary',
                  'coordinator' => 'bg-info',
                  'staff'       => 'bg-secondary',
                  default       => 'bg-light text-dark border',
                };
              ?>
              <span class="badge <?= $roleBadge ?>"><?= e(\App\Services\RoleService::getRoleLabel($admin['role'] ?? 'viewer')) ?></span>
            </td>
            <td>
              <?php if (($admin['status'] ?? '') === 'active'): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary"><?= e(ucfirst($admin['status'] ?? 'inactive')) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-muted">
              <?= e(date('M d, Y', strtotime((string) $admin['created_at']))) ?>
            </td>
            <td class="text-end">
              <div class="dropdown">
                <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Manage
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                  <li>
                    <a class="dropdown-item small" href="<?= e(url('/admin/admins/' . $admin['id'] . '/edit')) ?>">
                      <i class="bi bi-pencil me-2 text-primary"></i> Edit Profile & Role
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item small" href="<?= e(url('/admin/admins/' . $admin['id'] . '/permissions')) ?>">
                      <i class="bi bi-shield-lock me-2 text-warning"></i> Module Permissions
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item small" href="<?= e(url('/admin/admins/' . $admin['id'] . '/password')) ?>">
                      <i class="bi bi-key me-2 text-danger"></i> Reset Password
                    </a>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
