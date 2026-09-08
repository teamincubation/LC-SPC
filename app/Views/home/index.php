<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
    <div>
      <h1 class="card-title"><?= e($fullTitle) ?></h1>
      <p style="color:var(--text-muted); font-size:0.95rem; margin-top:0.25rem;">
        Dedicated standalone portal for the Suicide Prevention Campaign.
      </p>
    </div>
    <span class="badge badge-success">Phase 0: Foundation Ready</span>
  </div>

  <p>
    The production-ready architecture foundation for <strong><?= e($appName) ?></strong> has been established. 
    This application runs completely independently from the main Team Incubation website with its own dedicated MySQL database, 
    isolated routing, and hardened security controls.
  </p>
</div>

<div class="grid grid-cols-2">
  <div class="card">
    <h2 class="card-title" style="margin-bottom:1rem; font-size:1.15rem;">Application Architecture</h2>
    <table class="data-table">
      <tbody>
        <tr>
          <th>Application</th>
          <td><strong><?= e($appName) ?></strong></td>
        </tr>
        <tr>
          <th>Full Title</th>
          <td><?= e($fullTitle) ?></td>
        </tr>
        <tr>
          <th>Environment</th>
          <td>
            <span class="badge <?= $env === 'production' ? 'badge-success' : 'badge-neutral' ?>">
              <?= e(strtoupper($env)) ?>
            </span>
          </td>
        </tr>
        <tr>
          <th>Base Path</th>
          <td><code class="code-inline"><?= e($basePath ?: '/') ?></code></td>
        </tr>
        <tr>
          <th>PHP Runtime</th>
          <td><code class="code-inline">PHP <?= e($phpVersion) ?></code></td>
        </tr>
        <tr>
          <th>Version</th>
          <td><code class="code-inline"><?= e($version) ?></code></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 class="card-title" style="margin-bottom:1rem; font-size:1.15rem;">Subsystem Readiness</h2>
    <table class="data-table">
      <tbody>
        <tr>
          <th>Dedicated Database</th>
          <td>
            <?php if ($dbConnected): ?>
              <span class="badge badge-success">&#10003; Connected (<?= e($dbStatus['latency_ms'] ?? 0) ?>ms)</span>
            <?php else: ?>
              <span class="badge badge-warning">Configured (Awaiting Hostinger Env)</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Apache Security</th>
          <td><span class="badge badge-success">&#10003; Multi-tier .htaccess</span></td>
        </tr>
        <tr>
          <th>CSRF Protection</th>
          <td><span class="badge badge-success">&#10003; Enforced</span></td>
        </tr>
        <tr>
          <th>Security Headers</th>
          <td><span class="badge badge-success">&#10003; CSP &amp; HSTS Ready</span></td>
        </tr>
        <tr>
          <th>Session Hardening</th>
          <td><span class="badge badge-success">&#10003; HttpOnly &amp; SameSite</span></td>
        </tr>
        <tr>
          <th>Health Check</th>
          <td><a href="<?= e(url('/health')) ?>" class="badge badge-neutral">&#8599; /health</a></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php if ($debug): ?>
  <!-- Diagnostics displayed ONLY in development/debug mode -->
  <div class="card" style="border-left: 4px solid var(--color-warning);">
    <h2 class="card-title" style="font-size:1.1rem; color:var(--color-warning); margin-bottom:0.5rem;">
      Developer Diagnostics (APP_DEBUG = true)
    </h2>
    <p style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:0.75rem;">
      This panel is automatically hidden in production when <code class="code-inline">APP_DEBUG=false</code>.
    </p>
    <table class="data-table">
      <tbody>
        <tr>
          <th>Database Status</th>
          <td><code class="code-inline"><?= e($dbStatus['status'] ?? 'unknown') ?></code></td>
        </tr>
        <tr>
          <th>Configured DB Name</th>
          <td><code class="code-inline"><?= e(config('database.database')) ?></code></td>
        </tr>
        <tr>
          <th>Timezone</th>
          <td><code class="code-inline"><?= e(date_default_timezone_get()) ?></code></td>
        </tr>
        <tr>
          <th>Server Time</th>
          <td><code class="code-inline"><?= date('Y-m-d H:i:s T') ?></code></td>
        </tr>
      </tbody>
    </table>
  </div>
<?php endif; ?>
