<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CampaignRepository;
use DateTime;
use InvalidArgumentException;
use RuntimeException;

/**
 * Campaign Domain Service
 * Encapsulates campaign business rules, validation, slug management, and audit logging.
 */
class CampaignService
{
    private CampaignRepository $campaignRepo;
    private AuditService $auditService;

    public const ALLOWED_STATUSES = ['draft', 'active', 'completed', 'archived'];

    public function __construct(?CampaignRepository $campaignRepo = null, ?AuditService $auditService = null)
    {
        $this->campaignRepo = $campaignRepo ?? new CampaignRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Validate campaign input data.
     *
     * @param array $data Input attributes
     * @param int|null $id Existing campaign ID for updates
     * @return array Validation errors keyed by field name
     */
    public function validate(array $data, ?int $id = null): array
    {
        $errors = [];

        // Title validation: required|string|min:3|max:191
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Campaign title is required.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'Campaign title must be at least 3 characters.';
        } elseif (mb_strlen($title) > 191) {
            $errors['title'] = 'Campaign title may not exceed 191 characters.';
        }

        // Slug validation (if explicitly provided)
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug !== '') {
            $slug = strtolower($slug);
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $errors['slug'] = 'Slug may only contain lowercase letters, numbers, and single hyphens.';
            } elseif (strlen($slug) > 191) {
                $errors['slug'] = 'Slug may not exceed 191 characters.';
            } elseif ($this->campaignRepo->slugExists($slug, $id)) {
                $errors['slug'] = "The slug '{$slug}' is already in use by another campaign.";
            }
        }

        // Theme validation: optional|string|max:255
        $theme = trim((string) ($data['theme'] ?? ''));
        if ($theme !== '' && mb_strlen($theme) > 255) {
            $errors['theme'] = 'Campaign theme may not exceed 255 characters.';
        }

        // Description validation: optional|string|max:5000
        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '' && mb_strlen($description) > 5000) {
            $errors['description'] = 'Description may not exceed 5000 characters.';
        }

        // Start Date validation: required|date_format:Y-m-d
        $startDateStr = trim((string) ($data['start_date'] ?? ''));
        $validStartDate = false;
        if ($startDateStr === '') {
            $errors['start_date'] = 'Start date is required.';
        } else {
            $d = DateTime::createFromFormat('Y-m-d', $startDateStr);
            if ($d && $d->format('Y-m-d') === $startDateStr) {
                $validStartDate = true;
            } else {
                $errors['start_date'] = 'Start date must be a valid date in YYYY-MM-DD format.';
            }
        }

        // End Date validation: required|date_format:Y-m-d|after_or_equal:start_date
        $endDateStr = trim((string) ($data['end_date'] ?? ''));
        if ($endDateStr === '') {
            $errors['end_date'] = 'End date is required.';
        } else {
            $d = DateTime::createFromFormat('Y-m-d', $endDateStr);
            if ($d && $d->format('Y-m-d') === $endDateStr) {
                if ($validStartDate && $startDateStr > $endDateStr) {
                    $errors['end_date'] = 'End date must be on or after the start date.';
                }
            } else {
                $errors['end_date'] = 'End date must be a valid date in YYYY-MM-DD format.';
            }
        }

        // Status validation: required|in:draft,active,completed,archived
        $status = trim((string) ($data['status'] ?? ''));
        if ($status === '') {
            $errors['status'] = 'Campaign status is required.';
        } elseif (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Invalid status selected. Must be draft, active, completed, or archived.';
        }

        return $errors;
    }

    /**
     * Generate a URL-friendly, lowercase alphanumeric slug from a string.
     */
    public static function slugify(string $text): string
    {
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text ?: '');
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text ?: '');
        // Trim hyphens
        $text = trim($text ?: '', '-');
        // Remove duplicate hyphens
        $text = preg_replace('~-+~', '-', $text ?: '');
        // Lowercase
        $text = strtolower($text ?: '');

        return $text !== '' ? $text : 'campaign';
    }

    /**
     * Generate a unique slug, appending incrementing numeric suffixes on collisions.
     */
    public function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = self::slugify($title);
        $slug = $baseSlug;
        $counter = 2;

        while ($this->campaignRepo->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create a new campaign with business validation and audit logging.
     *
     * @param array $data Form attributes
     * @param int $actorId Authenticated user ID creating the campaign
     * @return array Created campaign record
     * @throws InvalidArgumentException When validation fails
     */
    public function createCampaign(array $data, int $actorId): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            $firstError = reset($errors);
            $ex = new InvalidArgumentException($firstError);
            $ex->errors = $errors;
            throw $ex;
        }

        // Generate or verify slug
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->generateUniqueSlug((string) $data['title']);
        } else {
            $slug = strtolower($slug);
        }

        $campaignId = $this->campaignRepo->create([
            'title'       => $data['title'],
            'slug'        => $slug,
            'theme'       => $data['theme'] ?? null,
            'description' => $data['description'] ?? null,
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'status'      => $data['status'] ?? 'draft',
            'created_by'  => $actorId,
        ]);

        $campaign = $this->campaignRepo->findById($campaignId);

        // Audit Trail
        $this->auditService->log(
            'campaign.create',
            'campaign',
            $campaignId,
            [
                'title'      => $campaign['title'] ?? '',
                'slug'       => $campaign['slug'] ?? '',
                'status'     => $campaign['status'] ?? '',
                'start_date' => $campaign['start_date'] ?? '',
                'end_date'   => $campaign['end_date'] ?? '',
            ],
            $actorId
        );

        return $campaign ?? [];
    }

    /**
     * Update an existing campaign with business validation and audit logging.
     *
     * @throws InvalidArgumentException When validation fails
     * @throws RuntimeException When campaign not found
     */
    public function updateCampaign(int $id, array $data, int $actorId): array
    {
        $existing = $this->campaignRepo->findById($id);
        if (!$existing) {
            throw new RuntimeException("Campaign with ID {$id} not found or deleted.");
        }

        $errors = $this->validate($data, $id);
        if (!empty($errors)) {
            $firstError = reset($errors);
            $ex = new InvalidArgumentException($firstError);
            $ex->errors = $errors;
            throw $ex;
        }

        // Generate or verify slug
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->generateUniqueSlug((string) $data['title'], $id);
        } else {
            $slug = strtolower($slug);
        }

        $oldStatus = $existing['status'];
        $newStatus = $data['status'] ?? $oldStatus;

        $this->campaignRepo->update($id, [
            'title'       => $data['title'],
            'slug'        => $slug,
            'theme'       => $data['theme'] ?? null,
            'description' => $data['description'] ?? null,
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'status'      => $newStatus,
        ]);

        $updated = $this->campaignRepo->findById($id);

        // Audit update
        $this->auditService->log(
            'campaign.update',
            'campaign',
            $id,
            [
                'title'      => $updated['title'] ?? '',
                'slug'       => $updated['slug'] ?? '',
                'status'     => $updated['status'] ?? '',
                'start_date' => $updated['start_date'] ?? '',
                'end_date'   => $updated['end_date'] ?? '',
            ],
            $actorId
        );

        // Audit status change if transitioned
        if ($oldStatus !== $newStatus) {
            $this->auditService->log(
                'campaign.status_change',
                'campaign',
                $id,
                [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
                $actorId
            );
        }

        return $updated ?? [];
    }

    /**
     * Transition campaign status directly.
     */
    public function updateStatus(int $id, string $status, int $actorId): bool
    {
        $status = trim($status);
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid campaign status: {$status}");
        }

        $campaign = $this->campaignRepo->findById($id);
        if (!$campaign) {
            throw new RuntimeException("Campaign with ID {$id} not found.");
        }

        $oldStatus = $campaign['status'];
        if ($oldStatus === $status) {
            return true;
        }

        $data = $campaign;
        $data['status'] = $status;
        $this->campaignRepo->update($id, $data);

        $this->auditService->log(
            'campaign.status_change',
            'campaign',
            $id,
            [
                'old_status' => $oldStatus,
                'new_status' => $status,
            ],
            $actorId
        );

        return true;
    }

    /**
     * Soft-delete a campaign with RESTRICT verification and audit logging.
     *
     * @throws RuntimeException If active events exist or campaign not found
     */
    public function softDeleteCampaign(int $id, int $actorId): bool
    {
        $campaign = $this->campaignRepo->findById($id);
        if (!$campaign) {
            throw new RuntimeException("Campaign with ID {$id} not found or already deleted.");
        }

        // Rule: RESTRICT on active child events
        if ($this->campaignRepo->hasEvents($id)) {
            throw new RuntimeException("Cannot delete campaign '{$campaign['title']}': active events are associated with it. Remove or reassign events first.");
        }

        $success = $this->campaignRepo->softDelete($id);
        if ($success) {
            $this->auditService->log(
                'campaign.delete',
                'campaign',
                $id,
                [
                    'title' => $campaign['title'],
                    'slug'  => $campaign['slug'],
                ],
                $actorId
            );
        }

        return $success;
    }

    /**
     * Restore a soft-deleted campaign with audit logging.
     */
    public function restoreCampaign(int $id, int $actorId): bool
    {
        $campaign = $this->campaignRepo->findById($id, true);
        if (!$campaign) {
            throw new RuntimeException("Campaign with ID {$id} not found.");
        }

        if ($campaign['deleted_at'] === null) {
            return true; // Already active
        }

        $success = $this->campaignRepo->restore($id);
        if ($success) {
            $this->auditService->log(
                'campaign.restore',
                'campaign',
                $id,
                [
                    'title' => $campaign['title'],
                    'slug'  => $campaign['slug'],
                ],
                $actorId
            );
        }

        return $success;
    }

    public function getRepository(): CampaignRepository
    {
        return $this->campaignRepo;
    }
}
