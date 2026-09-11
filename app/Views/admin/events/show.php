<?php
  $st = $event['status'] ?? 'draft';
  $statusBadge = match ($st) {
      'published' => 'badge-success',
      'ongoing'   => 'badge-info',
      'completed' => 'badge-neutral',
      'cancelled' => 'badge-danger',
      default     => 'badge-warning',
  };
  $fmtBadge = match ($event['format'] ?? 'in_person') {
      'online'    => 'badge-info',
      'hybrid'    => 'badge-primary',
      default     => 'badge-neutral',
  };
  $isSoftDeleted = !empty($event['deleted_at']);
?>

<!-- Event Overview Header -->
<div class="card mb-6" style="border-left: 4px solid var(--color-primary);">
  <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
        <h1 class="card-title" style="font-size: var(--font-size-xl); margin: 0;">
          <?= e($event['title']) ?>
        </h1>
        <span class="badge badge-neutral" style="font-size: var(--font-size-xs);">
          <?= e(ucwords(str_replace('_', ' ', $event['category'] ?? 'workshop'))) ?>
        </span>
        <span class="badge <?= e($fmtBadge) ?>" style="font-size: var(--font-size-xs);">
          <?= e(ucfirst(str_replace('_', '-', $event['format'] ?? 'in_person'))) ?>
        </span>
        <?php if ($isSoftDeleted): ?>
          <span class="badge badge-danger">
            <span class="badge-dot" aria-hidden="true"></span>
            Soft-Deleted
          </span>
        <?php else: ?>
          <span class="badge <?= e($statusBadge) ?>">
            <span class="badge-dot" aria-hidden="true"></span>
            <?= e(ucfirst($st)) ?>
          </span>
        <?php endif; ?>
      </div>

      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Part of campaign: 
        <a href="<?= e(url('/admin/campaigns/' . $event['campaign_id'])) ?>" style="font-weight: var(--font-weight-medium); color: var(--color-primary); text-decoration: none;">
          <?= e($event['campaign_title'] ?? 'Campaign #' . $event['campaign_id']) ?>
        </a>
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-sm">
        &larr; All Events
      </a>

      <?php if (!$isSoftDeleted && in_array($st, ['published', 'ongoing', 'completed'], true)): ?>
        <a href="<?= e(url('/admin/checkin/event/' . $event['id'])) ?>" class="btn btn-primary btn-sm">
          <span>&#9989; Check-In</span>
        </a>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/attendance')) ?>" class="btn btn-outline btn-sm">
          <span>&#128101; Attendance</span>
        </a>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/certificates')) ?>" class="btn btn-outline btn-sm">
          <span>&#127891; Certificates</span>
        </a>
      <?php endif; ?>

      <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
        <a href="<?= e(url('/admin/events/' . $event['id'] . '/edit')) ?>" class="btn btn-outline btn-sm">
          Edit Event
        </a>
      <?php endif; ?>

      <?php if (!$isSoftDeleted && !empty($canDelete)): ?>
        <form action="<?= e(url('/admin/events/' . $event['id'] . '/delete')) ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to soft-delete this event?');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-danger); border-color: var(--border-danger);">
            Delete Event
          </button>
        </form>
      <?php endif; ?>

      <?php if ($isSoftDeleted && !empty($canDelete)): ?>
        <form action="<?= e(url('/admin/events/' . $event['id'] . '/restore')) ?>" method="POST" style="margin: 0;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary btn-sm">
            Restore Event
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Event Details Grid -->
<div class="grid grid-cols-3 gap-6 mb-6">
  <!-- Left Column: Details & Schedule -->
  <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Description & Agenda Card -->
    <div class="card">
      <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 0.75rem;">
        Event Overview &amp; Session Agenda
      </h2>

      <?php if (!empty($event['description'])): ?>
        <div style="font-size: var(--font-size-sm); line-height: 1.6; white-space: pre-line; background-color: var(--bg-surface-subtle); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <?= e($event['description']) ?>
        </div>
      <?php else: ?>
        <p class="text-caption text-muted" style="margin: 0;">No description provided for this session.</p>
      <?php endif; ?>
    </div>

    <!-- Schedule & Modality Card -->
    <div class="card">
      <h2 style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: 1rem;">
        Schedule &amp; Location Logistics
      </h2>

      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <span class="text-caption text-secondary" style="display: block; margin-bottom: 0.2rem;">Start Date &amp; Time</span>
          <div style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);">
            <?= e(date('l, F j, Y', strtotime($event['start_time']))) ?>
          </div>
          <div class="text-caption text-muted">
            <?= e(date('h:i A (T)', strtotime($event['start_time']))) ?>
          </div>
        </div>

        <div>
          <span class="text-caption text-secondary" style="display: block; margin-bottom: 0.2rem;">End Date &amp; Time</span>
          <div style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);">
            <?= e(date('l, F j, Y', strtotime($event['end_time']))) ?>
          </div>
          <div class="text-caption text-muted">
            <?= e(date('h:i A (T)', strtotime($event['end_time']))) ?>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4 pt-3" style="border-top: 1px solid var(--border-color);">
        <div>
          <span class="text-caption text-secondary" style="display: block; margin-bottom: 0.2rem;">Delivery Modality</span>
          <span class="badge <?= e($fmtBadge) ?>" style="font-size: var(--font-size-xs);">
            <?= e(ucfirst(str_replace('_', '-', $event['format'] ?? 'in_person'))) ?>
          </span>
        </div>

        <div>
          <span class="text-caption text-secondary" style="display: block; margin-bottom: 0.2rem;">Registration Deadline</span>
          <div style="font-size: var(--font-size-sm); font-weight: var(--font-weight-medium);">
            <?= !empty($event['registration_deadline']) ? e(date('M j, Y h:i A', strtotime($event['registration_deadline']))) : 'None (Closes at start)' ?>
          </div>
        </div>
      </div>

      <?php if (in_array($event['format'], ['in_person', 'hybrid'], true) && !empty($event['venue_name'])): ?>
        <div class="pt-3 mt-3" style="border-top: 1px solid var(--border-color);">
          <span class="text-caption text-secondary" style="display: block; margin-bottom: 0.2rem;">Physical Venue</span>
          <div style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);">
            <?= e($event['venue_name']) ?>
          </div>
          <?php if (!empty($event['venue_address'])): ?>
            <div class="text-caption text-secondary" style="margin-top: 0.15rem; white-space: pre-line;">
              <?= e($event['venue_address']) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (in_array($event['format'], ['online', 'hybrid'], true) && !empty($event['online_meeting_url'])): ?>
        <div class="pt-3 mt-3" style="border-top: 1px solid var(--border-color);">
          <span class="text-caption text-secondary" style="display: block; margin-bottom: 0.2rem;">Virtual Meeting Room</span>
          <a href="<?= e($event['online_meeting_url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size: var(--font-size-sm); word-break: break-all; color: var(--color-primary);">
            <?= e($event['online_meeting_url']) ?> &rarr;
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick Status Transition Form (Coordinators & Super Admin) -->
    <?php if (!$isSoftDeleted && !empty($canEdit)): ?>
      <div class="card" style="padding: 1rem 1.25rem;">
        <form action="<?= e(url('/admin/events/' . $event['id'] . '/status')) ?>" method="POST" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
          <?= csrf_field() ?>
          <div>
            <span style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm); display: block;">
              Lifecycle Status Transition
            </span>
            <span class="text-caption text-muted">Update the current lifecycle state of this event.</span>
          </div>

          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <select name="status" class="form-control" style="width: auto; padding: 0.35rem 0.65rem; font-size: var(--font-size-sm);" aria-label="Change status">
              <option value="draft" <?= $st === 'draft' ? 'selected' : '' ?>>Draft</option>
              <option value="published" <?= $st === 'published' ? 'selected' : '' ?>>Published</option>
              <option value="ongoing" <?= $st === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
              <option value="completed" <?= $st === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="cancelled" <?= $st === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">
              Update Status
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right Column: Governance & Capacity Metrics -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Capacity & Access Policy Card -->
    <div class="card">
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">
        Capacity &amp; Admission Policy
      </h3>

      <div class="mb-3">
        <span class="text-caption text-secondary" style="display: block;">Capacity Limit</span>
        <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); color: var(--text-primary); margin-top: 0.2rem;">
          <?= ((int) $event['capacity'] === 0) ? 'Unlimited Seats' : e((string) $event['capacity']) . ' Seats' ?>
        </div>
        <span class="text-caption text-muted">
          <?= ((int) $event['capacity'] === 0) ? 'No registration cap enforced.' : 'Registrations capped at ' . e((string) $event['capacity']) . '.' ?>
        </span>
      </div>

      <div class="pt-3" style="border-top: 1px solid var(--border-color);">
        <span class="text-caption text-secondary" style="display: block;">Approval Requirement</span>
        <div style="margin-top: 0.25rem;">
          <?php if (!empty($event['requires_approval'])): ?>
            <span class="badge badge-warning" style="font-size: var(--font-size-xs);">
              Manual Review Required
            </span>
          <?php else: ?>
            <span class="badge badge-neutral" style="font-size: var(--font-size-xs);">
              Automatic Confirmation
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Governance & Lead Coordinator Card -->
    <div class="card">
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">
        Administration
      </h3>

      <div class="mb-3">
        <span class="text-caption text-secondary" style="display: block;">Assigned Coordinator</span>
        <?php if (!empty($event['coordinator_name'])): ?>
          <div style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm); margin-top: 0.2rem;">
            <?= e($event['coordinator_name']) ?>
          </div>
          <div class="text-caption text-secondary">
            <?= e($event['coordinator_email'] ?? '') ?>
          </div>
        <?php else: ?>
          <span class="text-caption text-muted" style="margin-top: 0.2rem; display: block;">Unassigned</span>
        <?php endif; ?>
      </div>

      <div class="pt-3 mb-3" style="border-top: 1px solid var(--border-color);">
        <span class="text-caption text-secondary" style="display: block;">Unique Event Slug</span>
        <div style="margin-top: 0.25rem;">
          <code style="font-size: var(--font-size-xs); background-color: var(--bg-surface-subtle); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <?= e($event['slug']) ?>
          </code>
        </div>
      </div>

      <div class="pt-3 text-caption text-muted" style="border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.25rem;">
        <div>Created: <?= e(date('M j, Y h:i A', strtotime($event['created_at']))) ?></div>
        <div>Updated: <?= e(date('M j, Y h:i A', strtotime($event['updated_at']))) ?></div>
        <?php if (!empty($event['deleted_at'])): ?>
          <div style="color: var(--color-danger); font-weight: var(--font-weight-semibold);">
            Deleted: <?= e(date('M j, Y h:i A', strtotime($event['deleted_at']))) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
