<!-- Event Create Page Header -->
<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Schedule New Event</h1>
    <p class="admin-page-desc">Configure workshop, listening circle, seminar, or training sessions within an active campaign.</p>
  </div>

  <div class="admin-page-actions">
    <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-auto">
      <?= icon('arrow-left', ['class' => 'svg-icon-sm']) ?>
      <span>Back to Events</span>
    </a>
  </div>
</div>

<div class="card">
  <form action="<?= e(url('/admin/events')) ?>" method="POST" novalidate>
    <?= csrf_field() ?>

    <!-- Parent Campaign & Assigned Coordinator -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div class="form-group">
        <label for="event-campaign" class="form-label form-label-required">Parent Campaign</label>
        <select 
          id="event-campaign" 
          name="campaign_id" 
          class="form-control <?= !empty($errors['campaign_id']) ? 'is-invalid' : '' ?>" 
          required
        >
          <option value="">&mdash; Select Parent Campaign &mdash;</option>
          <?php foreach ($campaigns as $camp): ?>
            <?php $selected = (!empty($old['campaign_id']) && (int) $old['campaign_id'] === (int) $camp['id']) ? 'selected' : ''; ?>
            <option value="<?= e((string) $camp['id']) ?>" <?= $selected ?>>
              <?= e($camp['title']) ?> (<?= e(ucfirst($camp['status'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['campaign_id'])): ?>
          <span class="form-error"><?= e($errors['campaign_id']) ?></span>
        <?php else: ?>
          <span class="form-hint">Event will be organized under this umbrella initiative.</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="event-coordinator" class="form-label">Event Coordinator (Optional)</label>
        <select 
          id="event-coordinator" 
          name="coordinator_id" 
          class="form-control <?= !empty($errors['coordinator_id']) ? 'is-invalid' : '' ?>"
        >
          <option value="">&mdash; Unassigned / Open &mdash;</option>
          <?php foreach ($coordinators as $coord): ?>
            <?php $selected = (!empty($old['coordinator_id']) && (int) $old['coordinator_id'] === (int) $coord['id']) ? 'selected' : ''; ?>
            <option value="<?= e((string) $coord['id']) ?>" <?= $selected ?>>
              <?= e($coord['name']) ?> &lt;<?= e($coord['email']) ?>&gt; (<?= e(ucfirst($coord['role'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['coordinator_id'])): ?>
          <span class="form-error"><?= e($errors['coordinator_id']) ?></span>
        <?php else: ?>
          <span class="form-hint">Designated lead coordinator responsible for this session.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Title & Custom Slug -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div class="form-group">
        <label for="event-title" class="form-label form-label-required">Event Title</label>
        <input 
          type="text" 
          id="event-title" 
          name="title" 
          value="<?= e($old['title'] ?? '') ?>" 
          class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. Peer Listening Circle &mdash; Session 1" 
          maxlength="191" 
          required 
          autofocus
        >
        <?php if (!empty($errors['title'])): ?>
          <span class="form-error"><?= e($errors['title']) ?></span>
        <?php else: ?>
          <span class="form-hint">Clear descriptive title for participants (3 to 191 characters).</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="event-slug" class="form-label">Custom Slug (Optional)</label>
        <input 
          type="text" 
          id="event-slug" 
          name="slug" 
          value="<?= e($old['slug'] ?? '') ?>" 
          class="form-control <?= !empty($errors['slug']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. listening-circle-1 (auto-generated if left blank)" 
          maxlength="191"
        >
        <?php if (!empty($errors['slug'])): ?>
          <span class="form-error"><?= e($errors['slug']) ?></span>
        <?php else: ?>
          <span class="form-hint">Unique URL slug within this campaign. Leave blank to auto-generate.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Category, Format & Status -->
    <div class="grid grid-cols-3 gap-4 mb-4">
      <div class="form-group">
        <label for="event-category" class="form-label form-label-required">Category</label>
        <select 
          id="event-category" 
          name="category" 
          class="form-control <?= !empty($errors['category']) ? 'is-invalid' : '' ?>" 
          required
        >
          <?php $currentCat = $old['category'] ?? 'workshop'; ?>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $currentCat === $cat ? 'selected' : '' ?>>
              <?= e(ucwords(str_replace('_', ' ', $cat))) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['category'])): ?>
          <span class="form-error"><?= e($errors['category']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="event-format" class="form-label form-label-required">Delivery Format</label>
        <select 
          id="event-format" 
          name="format" 
          class="form-control <?= !empty($errors['format']) ? 'is-invalid' : '' ?>" 
          required
          onchange="toggleModalityFields(this.value);"
        >
          <?php $currentFmt = $old['format'] ?? 'in_person'; ?>
          <?php foreach ($formats as $fmt): ?>
            <option value="<?= e($fmt) ?>" <?= $currentFmt === $fmt ? 'selected' : '' ?>>
              <?= e(ucwords(str_replace('_', ' ', $fmt))) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['format'])): ?>
          <span class="form-error"><?= e($errors['format']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="event-status" class="form-label form-label-required">Initial Status</label>
        <select 
          id="event-status" 
          name="status" 
          class="form-control <?= !empty($errors['status']) ? 'is-invalid' : '' ?>" 
          required
        >
          <?php $currentSt = $old['status'] ?? 'draft'; ?>
          <?php foreach ($statuses as $st): ?>
            <option value="<?= e($st) ?>" <?= $currentSt === $st ? 'selected' : '' ?>>
              <?= e(ucfirst($st)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['status'])): ?>
          <span class="form-error"><?= e($errors['status']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Modality Fields: Venue vs Online Link -->
    <div class="card mb-4" style="background-color: var(--bg-surface-subtle); padding: 1.25rem;">
      <h3 style="font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">
        Location &amp; Delivery Modality
      </h3>

      <div id="venue-fields-group" class="grid grid-cols-2 gap-4 mb-3">
        <div class="form-group">
          <label for="event-venue-name" class="form-label">Venue Name</label>
          <input 
            type="text" 
            id="event-venue-name" 
            name="venue_name" 
            value="<?= e($old['venue_name'] ?? '') ?>" 
            class="form-control <?= !empty($errors['venue_name']) ? 'is-invalid' : '' ?>" 
            placeholder="e.g. Community Health Auditorium, Hall B" 
            maxlength="255"
          >
          <?php if (!empty($errors['venue_name'])): ?>
            <span class="form-error"><?= e($errors['venue_name']) ?></span>
          <?php else: ?>
            <span class="form-hint">Required for in-person and hybrid sessions.</span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="event-venue-address" class="form-label">Venue Address (Optional)</label>
          <input 
            type="text" 
            id="event-venue-address" 
            name="venue_address" 
            value="<?= e($old['venue_address'] ?? '') ?>" 
            class="form-control <?= !empty($errors['venue_address']) ? 'is-invalid' : '' ?>" 
            placeholder="Full physical street address, building, floor"
          >
          <?php if (!empty($errors['venue_address'])): ?>
            <span class="form-error"><?= e($errors['venue_address']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div id="online-fields-group" class="form-group mb-0">
        <label for="event-online-url" class="form-label">Online Meeting URL</label>
        <input 
          type="url" 
          id="event-online-url" 
          name="online_meeting_url" 
          value="<?= e($old['online_meeting_url'] ?? '') ?>" 
          class="form-control <?= !empty($errors['online_meeting_url']) ? 'is-invalid' : '' ?>" 
          placeholder="https://meet.google.com/... or https://zoom.us/j/..." 
          maxlength="255"
        >
        <?php if (!empty($errors['online_meeting_url'])): ?>
          <span class="form-error"><?= e($errors['online_meeting_url']) ?></span>
        <?php else: ?>
          <span class="form-hint">Required for virtual/online sessions. Must be a valid http:// or https:// URL.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Schedule: Start, End, Registration Deadline -->
    <div class="grid grid-cols-3 gap-4 mb-4">
      <div class="form-group">
        <label for="event-start-time" class="form-label form-label-required">Start Date &amp; Time</label>
        <input 
          type="datetime-local" 
          id="event-start-time" 
          name="start_time" 
          value="<?= e($old['start_time'] ?? '') ?>" 
          class="form-control <?= !empty($errors['start_time']) ? 'is-invalid' : '' ?>" 
          required
        >
        <?php if (!empty($errors['start_time'])): ?>
          <span class="form-error"><?= e($errors['start_time']) ?></span>
        <?php else: ?>
          <span class="form-hint">Session commencement.</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="event-end-time" class="form-label form-label-required">End Date &amp; Time</label>
        <input 
          type="datetime-local" 
          id="event-end-time" 
          name="end_time" 
          value="<?= e($old['end_time'] ?? '') ?>" 
          class="form-control <?= !empty($errors['end_time']) ? 'is-invalid' : '' ?>" 
          required
        >
        <?php if (!empty($errors['end_time'])): ?>
          <span class="form-error"><?= e($errors['end_time']) ?></span>
        <?php else: ?>
          <span class="form-hint">Must be after start time.</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="event-reg-deadline" class="form-label">Registration Deadline (Optional)</label>
        <input 
          type="datetime-local" 
          id="event-reg-deadline" 
          name="registration_deadline" 
          value="<?= e($old['registration_deadline'] ?? '') ?>" 
          class="form-control <?= !empty($errors['registration_deadline']) ? 'is-invalid' : '' ?>"
        >
        <?php if (!empty($errors['registration_deadline'])): ?>
          <span class="form-error"><?= e($errors['registration_deadline']) ?></span>
        <?php else: ?>
          <span class="form-hint">Cannot be after start time.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Capacity & Approval Settings -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div class="form-group">
        <label for="event-capacity" class="form-label">Capacity Limit</label>
        <input 
          type="number" 
          id="event-capacity" 
          name="capacity" 
          value="<?= e((string) ($old['capacity'] ?? '')) ?>" 
          class="form-control <?= !empty($errors['capacity']) ? 'is-invalid' : '' ?>" 
          placeholder="0 = Unlimited seats" 
          min="0"
        >
        <?php if (!empty($errors['capacity'])): ?>
          <span class="form-error"><?= e($errors['capacity']) ?></span>
        <?php else: ?>
          <span class="form-hint">Leave blank or enter 0 for uncapped capacity. Enter a positive number to cap seats.</span>
        <?php endif; ?>
      </div>

      <div class="form-group" style="display: flex; flex-direction: column; justify-content: center; pt: 1rem;">
        <label class="form-label" style="margin-bottom: 0.5rem;">Registration Approval Policy</label>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <input 
            type="checkbox" 
            id="event-requires-approval" 
            name="requires_approval" 
            value="1" 
            <?= !empty($old['requires_approval']) ? 'checked' : '' ?>
            style="width: 1.1rem; height: 1.1rem;"
          >
          <label for="event-requires-approval" style="font-size: var(--font-size-sm); cursor: pointer; margin: 0;">
            Require manual approval for attendees before admission
          </label>
        </div>
        <span class="form-hint">When checked, registered participants must be reviewed by a coordinator.</span>
      </div>
    </div>

    <!-- Description -->
    <div class="form-group mb-6">
      <label for="event-description" class="form-label">Description &amp; Agenda</label>
      <textarea 
        id="event-description" 
        name="description" 
        rows="4" 
        class="form-control <?= !empty($errors['description']) ? 'is-invalid' : '' ?>" 
        placeholder="Session outline, prerequisite knowledge, target audience, facilitator bio, etc." 
        maxlength="10000"
      ><?= e($old['description'] ?? '') ?></textarea>
      <?php if (!empty($errors['description'])): ?>
        <span class="form-error"><?= e($errors['description']) ?></span>
      <?php else: ?>
        <span class="form-hint">Detailed narrative for participants and facilitation team (up to 10,000 characters).</span>
      <?php endif; ?>
    </div>

    <!-- Submission Actions -->
    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
      <a href="<?= e(url('/admin/events')) ?>" class="btn btn-outline btn-auto">
        Cancel
      </a>
      <button type="submit" class="btn btn-primary btn-auto" style="display: inline-flex; align-items: center; gap: 0.4rem;">
        <?= icon('plus', ['class' => 'svg-icon-sm']) ?>
        <span>Schedule Event</span>
      </button>
    </div>
  </form>
</div>
