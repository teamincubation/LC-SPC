<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\ValidationException;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\UserRepository;
use DateTime;
use InvalidArgumentException;
use RuntimeException;

/**
 * Event Domain Service
 * Encapsulates event business rules, validation, campaign-scoped slug management, and audit logging.
 */
class EventService
{
    private EventRepository $eventRepo;
    private CampaignRepository $campaignRepo;
    private UserRepository $userRepo;
    private AuditService $auditService;
    private EventFormService $formService;
    private RegistrationRepository $regRepo;

    public const ALLOWED_CATEGORIES = [
        'workshop',
        'listening_circle',
        'training',
        'seminar',
        'pledge_drive',
    ];

    public const ALLOWED_FORMATS = [
        'in_person',
        'online',
        'hybrid',
    ];

    public const ALLOWED_EVENT_TYPES = [
        'offline',
        'online',
        'hybrid',
    ];

    public const ALLOWED_STATUSES = [
        'draft',
        'published',
        'ongoing',
        'completed',
        'cancelled',
    ];

    public const ELIGIBLE_COORDINATOR_ROLES = [
        'super_admin',
        'coordinator',
        'staff',
    ];

    public function __construct(
        ?EventRepository $eventRepo = null,
        ?CampaignRepository $campaignRepo = null,
        ?UserRepository $userRepo = null,
        ?AuditService $auditService = null,
        ?EventFormService $formService = null,
        ?RegistrationRepository $regRepo = null
    ) {
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->campaignRepo = $campaignRepo ?? new CampaignRepository();
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->formService = $formService ?? new EventFormService();
        $this->regRepo = $regRepo ?? new RegistrationRepository();
    }

    /**
     * Parse and normalize date/time input to ANSI Y-m-d H:i:s.
     * Supports HTML5 datetime-local (Y-m-d\TH:i), Y-m-d H:i:s, Y-m-d H:i.
     * Returns null if invalid or impossible date.
     */
    public static function parseDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = trim($value);
        $formats = [
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ];

        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $clean);
            if ($dt && $dt->format($fmt) === $clean) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * Validate event form/input data.
     *
     * @param array $data Raw input attributes
     * @param int|null $id Existing event ID for update operations
     * @return array Validation errors keyed by field name
     */
    public function validate(array $data, ?int $id = null): array
    {
        $errors = [];

        // 1. Campaign Validation: optional under V2 (Event-centric). If provided and > 0, must exist.
        $campaignId = isset($data['campaign_id']) && $data['campaign_id'] !== '' ? (int) $data['campaign_id'] : null;
        if ($campaignId !== null && $campaignId > 0) {
            $campaign = $this->campaignRepo->findById($campaignId);
            if (!$campaign) {
                $errors['campaign_id'] = 'The selected campaign does not exist or has been soft-deleted.';
            }
        }

        // 2. Coordinator Assignment Validation: optional, if provided must be active non-deleted user with eligible role
        if (!empty($data['coordinator_id'])) {
            $coordinatorId = (int) $data['coordinator_id'];
            $coordinator = $this->userRepo->findById($coordinatorId);

            if (!$coordinator || ($coordinator['status'] ?? '') !== 'active' || !empty($coordinator['deleted_at'])) {
                $errors['coordinator_id'] = 'The selected coordinator is invalid, inactive, or soft-deleted.';
            } elseif (!in_array($coordinator['role'] ?? '', self::ELIGIBLE_COORDINATOR_ROLES, true)) {
                $errors['coordinator_id'] = 'The selected user does not have an authorized staff/coordinator role.';
            }
        }

        // 3. Title: required|string|min:3|max:191
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Event title is required.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'Event title must be at least 3 characters.';
        } elseif (mb_strlen($title) > 191) {
            $errors['title'] = 'Event title may not exceed 191 characters.';
        }

        // 4. Slug: optional/auto-generated; if provided, must be valid and globally unique
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug !== '') {
            $slug = strtolower($slug);
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $errors['slug'] = 'Slug may only contain lowercase letters, numbers, and single hyphens.';
            } elseif (strlen($slug) > 191) {
                $errors['slug'] = 'Slug may not exceed 191 characters.';
            } elseif ($this->eventRepo->slugExists($slug, $id)) {
                $errors['slug'] = "The slug '{$slug}' is already in use by another event.";
            }
        }

        // 5. Category: required|in:workshop,listening_circle,training,seminar,pledge_drive
        $category = trim((string) ($data['category'] ?? ''));
        if ($category === '') {
            $errors['category'] = 'Event category is required.';
        } elseif (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $errors['category'] = 'Invalid category selected. Must be workshop, listening circle, training, seminar, or pledge drive.';
        }

        // 6. Format: required|in:in_person,online,hybrid
        $format = trim((string) ($data['format'] ?? ''));
        if ($format === '') {
            $errors['format'] = 'Event format is required.';
        } elseif (!in_array($format, self::ALLOWED_FORMATS, true)) {
            $errors['format'] = 'Invalid format selected. Must be in_person, online, or hybrid.';
        }

        // 7. Event Type: optional|in:offline,online,hybrid (default offline)
        $eventType = trim((string) ($data['event_type'] ?? 'offline'));
        if (!in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
            $errors['event_type'] = 'Invalid event type selected. Must be offline, online, or hybrid.';
        }

        // 8. Modality fields (Venue vs. Online URL)
        $venueName = trim((string) ($data['venue_name'] ?? ''));
        $onlineUrl = trim((string) ($data['online_meeting_url'] ?? ''));

        if (in_array($format, ['in_person', 'hybrid'], true) && $venueName === '') {
            $errors['venue_name'] = 'Venue name is required for in-person and hybrid events.';
        } elseif ($venueName !== '' && mb_strlen($venueName) > 255) {
            $errors['venue_name'] = 'Venue name may not exceed 255 characters.';
        }

        if (!empty($data['venue_address']) && mb_strlen((string) $data['venue_address']) > 2000) {
            $errors['venue_address'] = 'Venue address may not exceed 2000 characters.';
        }

        if ($format === 'online' && $onlineUrl === '') {
            $errors['online_meeting_url'] = 'Online meeting URL is required for virtual sessions.';
        } elseif ($onlineUrl !== '') {
            if (mb_strlen($onlineUrl) > 255) {
                $errors['online_meeting_url'] = 'Meeting URL may not exceed 255 characters.';
            } elseif (!filter_var($onlineUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $onlineUrl)) {
                $errors['online_meeting_url'] = 'Online meeting URL must be a valid http:// or https:// web address.';
            }
        }

        // 9. Schedule Date/Time Validation
        $startTime = self::parseDateTime($data['start_time'] ?? null);
        $endTime = self::parseDateTime($data['end_time'] ?? null);

        if ($startTime === null) {
            $errors['start_time'] = 'A valid event start date and time is required.';
        }

        if ($endTime === null) {
            $errors['end_time'] = 'A valid event conclusion date and time is required.';
        }

        if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
            $errors['end_time'] = 'Event conclusion time must be strictly after the start time.';
        }

        // 10. Registration Deadline Validation
        if (!empty($data['registration_deadline'])) {
            $regDeadline = self::parseDateTime($data['registration_deadline']);
            if ($regDeadline === null) {
                $errors['registration_deadline'] = 'Registration deadline must be a valid date and time.';
            } elseif ($startTime !== null && $regDeadline > $startTime) {
                $errors['registration_deadline'] = 'Registration deadline cannot be after the event start time.';
            }
        }

        // 11. Geofencing validation
        if (isset($data['latitude']) && $data['latitude'] !== '') {
            $lat = (float) $data['latitude'];
            if ($lat < -90.0 || $lat > 90.0) {
                $errors['latitude'] = 'Latitude must be between -90 and 90 degrees.';
            }
        }
        if (isset($data['longitude']) && $data['longitude'] !== '') {
            $lng = (float) $data['longitude'];
            if ($lng < -180.0 || $lng > 180.0) {
                $errors['longitude'] = 'Longitude must be between -180 and 180 degrees.';
            }
        }
        if (isset($data['geofence_radius_meters']) && $data['geofence_radius_meters'] !== '') {
            $radius = (int) $data['geofence_radius_meters'];
            if ($radius <= 0) {
                $errors['geofence_radius_meters'] = 'Geofence radius must be a positive integer in meters.';
            }
        }

        // 12. Capacity Validation
        $rawCapacity = isset($data['capacity']) ? trim((string) $data['capacity']) : '';
        if ($rawCapacity === '' || $rawCapacity === '0') {
            // Valid unlimited
        } elseif (ctype_digit($rawCapacity) && (int) $rawCapacity > 0) {
            // Valid positive capped capacity
        } else {
            $errors['capacity'] = 'Capacity must be a positive integer, 0, or left blank for unlimited.';
        }

        // 13. Status Validation
        $status = trim((string) ($data['status'] ?? 'draft'));
        if ($status === '') {
            $status = 'draft';
        }
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Invalid status selected. Must be draft, published, ongoing, completed, or cancelled.';
        }

        // 14. Description
        if (!empty($data['description']) && mb_strlen((string) $data['description']) > 10000) {
            $errors['description'] = 'Description may not exceed 10000 characters.';
        }

        return $errors;
    }

    /**
     * Generate unique slug globally for an event.
     */
    public function generateUniqueSlug(?int $campaignId, string $title, ?int $excludeId = null): string
    {
        $baseSlug = CampaignService::slugify($title);
        if (empty($baseSlug)) {
            $baseSlug = 'event-' . bin2hex(random_bytes(3));
        }
        $slug = $baseSlug;
        $counter = 2;

        while ($this->eventRepo->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create a new event with full business validation, atomic form provisioning, and audit trail.
     * Enforces: ONE EVENT = ONE REGISTRATION FORM inside a single database transaction.
     *
     * @throws ValidationException When validation fails
     */
    public function createEvent(array $data, int $actorId): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            $firstError = reset($errors);
            throw new ValidationException($firstError, $errors);
        }

        $campaignId = isset($data['campaign_id']) && $data['campaign_id'] !== '' ? (int) $data['campaign_id'] : null;

        // Resolve global unique slug
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->generateUniqueSlug($campaignId, (string) $data['title']);
        } else {
            $slug = strtolower($slug);
        }

        // Normalize capacity
        $rawCapacity = isset($data['capacity']) ? trim((string) $data['capacity']) : '';
        $capacity = ($rawCapacity === '' || $rawCapacity === '0') ? 0 : (int) $rawCapacity;

        $insertData = [
            'campaign_id'           => $campaignId,
            'coordinator_id'        => !empty($data['coordinator_id']) ? (int) $data['coordinator_id'] : null,
            'title'                 => trim((string) $data['title']),
            'slug'                  => $slug,
            'category'              => $data['category'],
            'event_type'            => $data['event_type'] ?? 'offline',
            'collaboration_with'    => !empty($data['collaboration_with']) ? trim((string) $data['collaboration_with']) : null,
            'collaboration_logo'    => !empty($data['collaboration_logo']) ? trim((string) $data['collaboration_logo']) : null,
            'description'           => !empty($data['description']) ? trim((string) $data['description']) : null,
            'format'                => $data['format'],
            'venue_name'            => !empty($data['venue_name']) ? trim((string) $data['venue_name']) : null,
            'venue_address'         => !empty($data['venue_address']) ? trim((string) $data['venue_address']) : null,
            'timezone'              => !empty($data['timezone']) ? trim((string) $data['timezone']) : 'Asia/Kolkata',
            'online_meeting_url'    => !empty($data['online_meeting_url']) ? trim((string) $data['online_meeting_url']) : null,
            'start_time'            => self::parseDateTime($data['start_time']),
            'end_time'              => self::parseDateTime($data['end_time']),
            'checkin_start_date'    => !empty($data['checkin_start_date']) ? $data['checkin_start_date'] : null,
            'checkin_start_time'    => !empty($data['checkin_start_time']) ? $data['checkin_start_time'] : null,
            'latitude'              => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude'             => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'geofence_radius_meters'=> isset($data['geofence_radius_meters']) && $data['geofence_radius_meters'] !== '' ? (int) $data['geofence_radius_meters'] : null,
            'capacity'              => $capacity,
            'registration_deadline' => self::parseDateTime($data['registration_deadline'] ?? null),
            'requires_approval'     => !empty($data['requires_approval']) ? 1 : 0,
            'status'                => $data['status'] ?? 'draft',
        ];

        // ATOMIC TRANSACTION: 1 EVENT = 1 REGISTRATION FORM
        return \App\Core\Database::transaction(function () use ($insertData, $campaignId, $slug, $actorId) {
            $eventId = $this->eventRepo->create($insertData);

            // Automatically provision unique registration form with dedicated stable URL
            $formId = $this->formService->createFormForEvent($eventId, $insertData['title'], $slug);

            $event = $this->eventRepo->findById($eventId);

            // Audit Trail
            $this->auditService->log(
                'event.create',
                'event',
                $eventId,
                [
                    'campaign_id'    => $campaignId,
                    'title'          => $insertData['title'],
                    'slug'           => $slug,
                    'form_id'        => $formId,
                    'category'       => $insertData['category'],
                    'event_type'     => $insertData['event_type'],
                    'format'         => $insertData['format'],
                    'status'         => $insertData['status'],
                    'start_time'     => $insertData['start_time'],
                    'end_time'       => $insertData['end_time'],
                    'coordinator_id' => $insertData['coordinator_id'],
                ],
                $actorId
            );

            return $event ?? [];
        });
    }

    /**
     * Update an existing event with validation and audit logging.
     * Architectural guarantee: Event edits do NOT mutate existing public registration URLs or form slugs.
     *
     * @throws ValidationException When validation fails
     * @throws RuntimeException When event not found
     */
    public function updateEvent(int $id, array $data, int $actorId): array
    {
        $existing = $this->eventRepo->findById($id);
        if (!$existing) {
            throw new RuntimeException("Event with ID {$id} not found or deleted.");
        }

        $errors = $this->validate($data, $id);
        if (!empty($errors)) {
            $firstError = reset($errors);
            throw new ValidationException($firstError, $errors);
        }

        $campaignId = isset($data['campaign_id']) && $data['campaign_id'] !== '' ? (int) $data['campaign_id'] : null;

        // Resolve slug (maintain existing slug unless explicitly altered and unique)
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $existing['slug'];
        } else {
            $slug = strtolower($slug);
        }

        // CRITICAL FIX 2: Slug Immutability & Synchronization Enforcement
        if ($slug !== $existing['slug']) {
            $regCount = $this->regRepo->countByEvent($id);
            if ($regCount > 0) {
                throw new ValidationException(
                    'Event URL slug cannot be modified because registrations have already been received for this event.',
                    ['slug' => 'Event URL slug cannot be modified after registrations have begun.']
                );
            }

            // When allowed (0 registrations), keep the corresponding event_forms.slug synchronized
            $form = $this->formService->getFormByEventId($id);
            if ($form && ($form['slug'] ?? '') !== $slug) {
                $this->formService->updateForm((int) $form['id'], ['slug' => $slug]);
            }
        }

        // Normalize capacity
        $rawCapacity = isset($data['capacity']) ? trim((string) $data['capacity']) : '';
        $capacity = ($rawCapacity === '' || $rawCapacity === '0') ? 0 : (int) $rawCapacity;

        $oldStatus = $existing['status'];
        $newStatus = $data['status'] ?? $oldStatus;

        $updateData = [
            'campaign_id'           => $campaignId,
            'coordinator_id'        => !empty($data['coordinator_id']) ? (int) $data['coordinator_id'] : null,
            'title'                 => trim((string) $data['title']),
            'slug'                  => $slug,
            'category'              => $data['category'],
            'event_type'            => $data['event_type'] ?? ($existing['event_type'] ?? 'offline'),
            'collaboration_with'    => !empty($data['collaboration_with']) ? trim((string) $data['collaboration_with']) : null,
            'collaboration_logo'    => !empty($data['collaboration_logo']) ? trim((string) $data['collaboration_logo']) : null,
            'description'           => !empty($data['description']) ? trim((string) $data['description']) : null,
            'format'                => $data['format'],
            'venue_name'            => !empty($data['venue_name']) ? trim((string) $data['venue_name']) : null,
            'venue_address'         => !empty($data['venue_address']) ? trim((string) $data['venue_address']) : null,
            'timezone'              => !empty($data['timezone']) ? trim((string) $data['timezone']) : ($existing['timezone'] ?? 'Asia/Kolkata'),
            'online_meeting_url'    => !empty($data['online_meeting_url']) ? trim((string) $data['online_meeting_url']) : null,
            'start_time'            => self::parseDateTime($data['start_time']),
            'end_time'              => self::parseDateTime($data['end_time']),
            'checkin_start_date'    => !empty($data['checkin_start_date']) ? $data['checkin_start_date'] : null,
            'checkin_start_time'    => !empty($data['checkin_start_time']) ? $data['checkin_start_time'] : null,
            'latitude'              => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude'             => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'geofence_radius_meters'=> isset($data['geofence_radius_meters']) && $data['geofence_radius_meters'] !== '' ? (int) $data['geofence_radius_meters'] : null,
            'capacity'              => $capacity,
            'registration_deadline' => self::parseDateTime($data['registration_deadline'] ?? null),
            'requires_approval'     => !empty($data['requires_approval']) ? 1 : 0,
            'status'                => $newStatus,
        ];

        $this->eventRepo->update($id, $updateData);
        $updated = $this->eventRepo->findById($id);

        // Audit Trail
        $this->auditService->log(
            'event.update',
            'event',
            $id,
            [
                'campaign_id' => $campaignId,
                'title'       => $updateData['title'],
                'slug'        => $slug,
                'status'      => $newStatus,
            ],
            $actorId
        );

        // Audit status transition if changed
        if ($oldStatus !== $newStatus) {
            $this->auditService->log(
                'event.status_change',
                'event',
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
     * Directly update event lifecycle status.
     */
    public function updateStatus(int $id, string $status, int $actorId): bool
    {
        $status = trim($status);
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid event status: {$status}");
        }

        $event = $this->eventRepo->findById($id);
        if (!$event) {
            throw new RuntimeException("Event with ID {$id} not found.");
        }

        $oldStatus = $event['status'];
        if ($oldStatus === $status) {
            return true;
        }

        $data = $event;
        $data['status'] = $status;
        $this->eventRepo->update($id, $data);

        $this->auditService->log(
            'event.status_change',
            'event',
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
     * Soft-delete an event.
     */
    public function softDeleteEvent(int $id, int $actorId): bool
    {
        $event = $this->eventRepo->findById($id);
        if (!$event) {
            throw new RuntimeException("Event with ID {$id} not found or already deleted.");
        }

        $success = $this->eventRepo->softDelete($id);
        if ($success) {
            $this->auditService->log(
                'event.delete',
                'event',
                $id,
                [
                    'campaign_id' => $event['campaign_id'],
                    'title'       => $event['title'],
                    'slug'        => $event['slug'],
                ],
                $actorId
            );
        }

        return $success;
    }

    /**
     * Restore a soft-deleted event.
     */
    public function restoreEvent(int $id, int $actorId): bool
    {
        $event = $this->eventRepo->findById($id, true);
        if (!$event) {
            throw new RuntimeException("Event with ID {$id} not found.");
        }

        if ($event['deleted_at'] === null) {
            return true;
        }

        $success = $this->eventRepo->restore($id);
        if ($success) {
            $this->auditService->log(
                'event.restore',
                'event',
                $id,
                [
                    'campaign_id' => $event['campaign_id'],
                    'title'       => $event['title'],
                    'slug'        => $event['slug'],
                ],
                $actorId
            );
        }

        return $success;
    }

    public function getRepository(): EventRepository
    {
        return $this->eventRepo;
    }
}
