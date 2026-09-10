<div class="card mb-6">
  <div class="card-header">
    <div>
      <h1 class="card-title" style="font-size: var(--font-size-xl); margin-bottom: 0.25rem;">
        Create New Campaign
      </h1>
      <p class="text-secondary" style="font-size: var(--font-size-sm); margin: 0;">
        Initialize a multi-year campaign initiative with theme and duration parameters.
      </p>
    </div>
    <a href="<?= e(url('/admin/campaigns')) ?>" class="btn btn-outline btn-sm">
      &larr; Return to Roster
    </a>
  </div>
</div>

<div class="card">
  <form action="<?= e(url('/admin/campaigns')) ?>" method="POST" novalidate>
    <?= csrf_field() ?>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <!-- Campaign Title -->
      <div class="form-group">
        <label for="campaign-title" class="form-label form-label-required">Campaign Title</label>
        <input 
          type="text" 
          id="campaign-title" 
          name="title" 
          value="<?= e($old['title'] ?? '') ?>" 
          class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. Suicide Prevention Campaign 2026" 
          maxlength="191" 
          required 
          autofocus
        >
        <?php if (!empty($errors['title'])): ?>
          <span class="form-error"><?= e($errors['title']) ?></span>
        <?php else: ?>
          <span class="form-hint">Official title of the initiative (3 to 191 characters).</span>
        <?php endif; ?>
      </div>

      <!-- Campaign Slug -->
      <div class="form-group">
        <label for="campaign-slug" class="form-label">Custom Slug (Optional)</label>
        <input 
          type="text" 
          id="campaign-slug" 
          name="slug" 
          value="<?= e($old['slug'] ?? '') ?>" 
          class="form-control <?= !empty($errors['slug']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. spc-2026 (auto-generated from title if blank)" 
          maxlength="191"
        >
        <?php if (!empty($errors['slug'])): ?>
          <span class="form-error"><?= e($errors['slug']) ?></span>
        <?php else: ?>
          <span class="form-hint">Unique URL slug. Leave blank to automatically generate from the title.</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <!-- Campaign Theme / Slogan -->
      <div class="form-group">
        <label for="campaign-theme" class="form-label">Theme / Slogan</label>
        <input 
          type="text" 
          id="campaign-theme" 
          name="theme" 
          value="<?= e($old['theme'] ?? '') ?>" 
          class="form-control <?= !empty($errors['theme']) ? 'is-invalid' : '' ?>" 
          placeholder="e.g. Words and Beyond &mdash; Listening Saves Lives" 
          maxlength="255"
        >
        <?php if (!empty($errors['theme'])): ?>
          <span class="form-error"><?= e($errors['theme']) ?></span>
        <?php else: ?>
          <span class="form-hint">Guiding motto or programmatic theme for this campaign.</span>
        <?php endif; ?>
      </div>

      <!-- Lifecycle Status -->
      <div class="form-group">
        <label for="campaign-status" class="form-label form-label-required">Initial Status</label>
        <select 
          id="campaign-status" 
          name="status" 
          class="form-control <?= !empty($errors['status']) ? 'is-invalid' : '' ?>" 
          required
        >
          <?php $currentStatus = $old['status'] ?? 'draft'; ?>
          <option value="draft" <?= $currentStatus === 'draft' ? 'selected' : '' ?>>Draft &mdash; Internal planning</option>
          <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>Active &mdash; Publicly visible &amp; open</option>
          <option value="completed" <?= $currentStatus === 'completed' ? 'selected' : '' ?>>Completed &mdash; Concluded initiative</option>
          <option value="archived" <?= $currentStatus === 'archived' ? 'selected' : '' ?>>Archived &mdash; Historical retention</option>
        </select>
        <?php if (!empty($errors['status'])): ?>
          <span class="form-error"><?= e($errors['status']) ?></span>
        <?php else: ?>
          <span class="form-hint">Draft campaigns cannot publish events publicly.</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <!-- Start Date -->
      <div class="form-group">
        <label for="campaign-start-date" class="form-label form-label-required">Start Date</label>
        <input 
          type="date" 
          id="campaign-start-date" 
          name="start_date" 
          value="<?= e($old['start_date'] ?? '') ?>" 
          class="form-control <?= !empty($errors['start_date']) ? 'is-invalid' : '' ?>" 
          required
        >
        <?php if (!empty($errors['start_date'])): ?>
          <span class="form-error"><?= e($errors['start_date']) ?></span>
        <?php else: ?>
          <span class="form-hint">Official launch/kickoff date (YYYY-MM-DD).</span>
        <?php endif; ?>
      </div>

      <!-- End Date -->
      <div class="form-group">
        <label for="campaign-end-date" class="form-label form-label-required">End Date</label>
        <input 
          type="date" 
          id="campaign-end-date" 
          name="end_date" 
          value="<?= e($old['end_date'] ?? '') ?>" 
          class="form-control <?= !empty($errors['end_date']) ? 'is-invalid' : '' ?>" 
          required
        >
        <?php if (!empty($errors['end_date'])): ?>
          <span class="form-error"><?= e($errors['end_date']) ?></span>
        <?php else: ?>
          <span class="form-hint">Conclusion date (must be on or after start date).</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Description -->
    <div class="form-group mb-6">
      <label for="campaign-description" class="form-label">Campaign Description &amp; Objectives</label>
      <textarea 
        id="campaign-description" 
        name="description" 
        rows="5" 
        class="form-control <?= !empty($errors['description']) ? 'is-invalid' : '' ?>" 
        placeholder="Provide an overview of target communities, listening circle schedules, and awareness objectives..."
        maxlength="5000"
      ><?= e($old['description'] ?? '') ?></textarea>
      <?php if (!empty($errors['description'])): ?>
        <span class="form-error"><?= e($errors['description']) ?></span>
      <?php else: ?>
        <span class="form-hint">Detailed campaign overview and community guidelines (up to 5000 characters).</span>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
      <a href="<?= e(url('/admin/campaigns')) ?>" class="btn btn-outline">
        Cancel
      </a>
      <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem;">
        Save Campaign &rarr;
      </button>
    </div>
  </form>
</div>
