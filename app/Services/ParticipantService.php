<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\ValidationException;
use App\Repositories\ParticipantRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Participant Domain Service
 * Encapsulates participant validation, safe deduplication, privacy masking, server-side consent timestamps, and forensic audit trails.
 */
class ParticipantService
{
    public const ALLOWED_CATEGORIES = [
        'student',
        'professional',
        'community',
        'other',
    ];

    public const ALLOWED_STATUSES = [
        'active',
        'flagged',
        'blocked',
    ];

    private ParticipantRepository $participantRepo;
    private AuditService $auditService;

    public function __construct(
        ?ParticipantRepository $participantRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->participantRepo = $participantRepo ?? new ParticipantRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    public function getRepository(): ParticipantRepository
    {
        return $this->participantRepo;
    }

    /**
     * Retrieve paginated participants with server-side role-based contact PII masking.
     */
    public function getPaginatedParticipants(array $filters, int $page, int $perPage, string $userRole): array
    {
        $pagination = $this->participantRepo->paginate($filters, $page, $perPage);

        // Enforce server-side contact PII masking for staff and viewer roles
        if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            $pagination['items'] = $this->maskParticipantList($pagination['items']);
        }

        return $pagination;
    }

    /**
     * Retrieve a single participant by ID with server-side contact PII masking.
     */
    public function getParticipantById(int $id, string $userRole): ?array
    {
        $participant = $this->participantRepo->findById($id);
        if (!$participant) {
            return null;
        }

        if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            return $this->maskParticipant($participant);
        }

        return $participant;
    }

    /**
     * Mask email address for display to non-privileged staff/viewer roles.
     * Example: 'rahul.sharma@example.com' -> 'r***@example.com'
     */
    public static function maskEmail(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return '';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (mb_strlen($local) <= 1) {
            return '***@' . $domain;
        }

        $firstChar = mb_substr($local, 0, 1);
        return $firstChar . '***@' . $domain;
    }

    /**
     * Mask phone number for display to non-privileged staff/viewer roles.
     * Preserves country code if present, masks middle digits, preserves last 4 digits.
     * Example: '+91 9876543210' -> '+91 *****3210'
     */
    public static function maskPhone(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        // Extract digits
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($digits);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $lastFour = substr($digits, -4);
        
        // Preserve leading + if present
        $prefix = (str_starts_with($phone, '+') && str_contains($phone, ' '))
            ? explode(' ', $phone)[0] . ' '
            : (str_starts_with($phone, '+') ? '+' : '');

        return $prefix . '*****' . $lastFour;
    }

    /**
     * Apply contact PII masking to a single participant record.
     * If userRole is provided and user has coordinator role, unmasked data is returned.
     */
    public static function maskParticipant(array $participant, ?string $userRole = null): array
    {
        if ($userRole !== null && RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            $unmasked = $participant;
            $unmasked['_is_masked'] = false;
            return $unmasked;
        }

        $masked = $participant;
        $masked['email'] = self::maskEmail($participant['email'] ?? null);
        $masked['phone'] = self::maskPhone($participant['phone'] ?? null);
        $masked['_is_masked'] = true;
        return $masked;
    }

    /**
     * Apply contact PII masking to a collection of participant records.
     */
    public static function maskParticipantList(array $participants, ?string $userRole = null): array
    {
        return array_map(fn($p) => self::maskParticipant($p, $userRole), $participants);
    }

    /**
     * Validate participant data attributes.
     *
     * @param array $data Input form attributes
     * @param bool $isCreate Whether validation is for creation (requires consent checkboxes)
     * @return array Map of field => error message
     */
    public function validate(array $data, bool $isCreate = false): array
    {
        $errors = [];

        // 1. Full Name: required|string|min:2|max:150
        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '') {
            $errors['full_name'] = 'Full name is required (2 to 150 characters).';
        } elseif (mb_strlen($fullName) < 2) {
            $errors['full_name'] = 'Full name must be at least 2 characters.';
        } elseif (mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Full name may not exceed 150 characters.';
        }

        // 2. Email: optional|email|max:191
        if (!empty($data['email'])) {
            $email = trim((string) $data['email']);
            if (mb_strlen($email) > 191) {
                $errors['email'] = 'Email address may not exceed 191 characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please provide a valid email address.';
            }
        }

        // 3. Phone: optional|regex|min:7|max:25
        if (!empty($data['phone'])) {
            $phone = trim((string) $data['phone']);
            if (mb_strlen($phone) < 7 || mb_strlen($phone) > 25 || !preg_match('/^[0-9+\-\s()]{7,25}$/', $phone)) {
                $errors['phone'] = 'Phone number must be between 7 and 25 digits/characters.';
            }
        }

        // 4. Category: required|in:student,professional,community,other
        $category = trim((string) ($data['category'] ?? 'community'));
        if ($category === '') {
            $category = 'community';
        }
        if (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $errors['category'] = 'Invalid category selected. Must be student, professional, community, or other.';
        }

        // 5. Organization Name: optional|max:191
        if (!empty($data['organization_name']) && mb_strlen((string) $data['organization_name']) > 191) {
            $errors['organization_name'] = 'Organization name may not exceed 191 characters.';
        }

        // 6. Consent Affirmations (Required during creation only)
        if ($isCreate) {
            if (empty($data['agreed_guidelines'])) {
                $errors['agreed_guidelines'] = 'Consent to Community Guidelines is mandatory.';
            }
            if (empty($data['privacy_consent'])) {
                $errors['privacy_consent'] = 'Consent to the Privacy Notice is mandatory.';
            }
        }

        // 7. Status: optional|in:active,flagged,blocked (defaults to 'active')
        $status = trim((string) ($data['status'] ?? 'active'));
        if ($status === '') {
            $status = 'active';
        }
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Status must be active, flagged, or blocked.';
        }

        return $errors;
    }

    /**
     * Administrative Participant Creation.
     * Checks for potential duplicates. If found and not explicitly confirmed, raises duplicate warning.
     * Never silently merges or overwrites records.
     *
     * @throws ValidationException On validation failure or unconfirmed duplicate
     */
    public function createParticipant(array $data, int $actorId, bool $confirmDistinct = false): array
    {
        $errors = $this->validate($data, true);
        if (!empty($errors)) {
            $firstError = reset($errors);
            throw new ValidationException($firstError, $errors);
        }

        $fullName = trim((string) $data['full_name']);
        $email = !empty($data['email']) ? trim(strtolower((string) $data['email'])) : null;
        $phone = !empty($data['phone']) ? trim((string) $data['phone']) : null;

        // Duplicate Detection (Without Silent Merging)
        if (!$confirmDistinct && $email !== null) {
            $duplicate = $this->participantRepo->findPotentialDuplicate($fullName, $email, $phone);
            if ($duplicate !== null) {
                $warningMsg = "A participant with matching contact details already exists: {$duplicate['full_name']} (ID: #{$duplicate['id']}, Status: " . ucfirst($duplicate['status']) . "). If this is a distinct individual who shares contact info, check 'Confirm distinct participant' below.";
                $ex = new ValidationException($warningMsg, [
                    'duplicate_detected' => $warningMsg,
                    'duplicate_id'       => (string) $duplicate['id'],
                    'duplicate_name'     => $duplicate['full_name'],
                ]);
                throw $ex;
            }
        }

        // Generate immutable compliance timestamps strictly server-side
        $now = date('Y-m-d H:i:s');

        $insertData = [
            'full_name'            => $fullName,
            'email'                => $email,
            'phone'                => $phone,
            'category'             => $data['category'] ?? 'community',
            'organization_name'    => !empty($data['organization_name']) ? trim((string) $data['organization_name']) : null,
            'agreed_guidelines_at' => $now,
            'privacy_consent_at'   => $now,
            'status'               => $data['status'] ?? 'active',
        ];

        $participantId = $this->participantRepo->create($insertData);
        $participant = $this->participantRepo->findById($participantId);

        // Forensic Audit Logging (Scrubbed of raw contact PII)
        $this->auditService->log(
            'participant.create',
            'participant',
            $participantId,
            [
                'category'   => $insertData['category'],
                'has_email'  => !empty($insertData['email']),
                'has_phone'  => !empty($insertData['phone']),
                'status'     => $insertData['status'],
                'is_distinct'=> $confirmDistinct,
            ],
            $actorId
        );

        return $participant ?? [];
    }

    /**
     * Reusable Domain Deduplication Logic for Phase 1E (Event Registration).
     * Resolves to existing record on high-confidence match or creates new.
     *
    /**
     * Resolve existing participant by contact match or create a new participant.
     * Reusable domain logic for future Phase 1E registration workflows.
     *
     * @throws RuntimeException If candidate is blocked
     */
    public function resolveOrCreateParticipant(array $data, int $actorId = 0): array
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = !empty($data['email']) ? trim(strtolower((string) $data['email'])) : null;
        $phone = !empty($data['phone']) ? trim((string) $data['phone']) : null;

        if ($email !== null) {
            $candidate = $this->participantRepo->findPotentialDuplicate($fullName, $email, $phone);
            if ($candidate !== null) {
                $normTarget = preg_replace('/\s+/', ' ', trim(mb_strtolower($fullName)));
                $normCand = preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $candidate['full_name'])));
                if ($normTarget === $normCand) {
                    if ($candidate['status'] === 'blocked') {
                        throw new RuntimeException('Registration cannot be processed at this time.');
                    }
                    return $candidate;
                }
            }
        }

        // No match or email is null -> create new distinct participant
        return $this->createParticipant($data, $actorId, true);
    }

    /**
     * Update participant details.
     * Strictly excludes consent timestamps (immutable historical records).
     */
    public function updateParticipant(int $id, array $data, int $actorId): array
    {
        $existing = $this->participantRepo->findById($id);
        if (!$existing) {
            throw new RuntimeException("Participant with ID {$id} not found.");
        }

        $errors = $this->validate($data, false);
        if (!empty($errors)) {
            $firstError = reset($errors);
            throw new ValidationException($firstError, $errors);
        }

        // Whitelisted updatable fields only (consent timestamps strictly excluded)
        $updateData = [
            'full_name'         => isset($data['full_name']) ? trim((string) $data['full_name']) : $existing['full_name'],
            'email'             => array_key_exists('email', $data) ? (!empty($data['email']) ? trim(strtolower((string) $data['email'])) : null) : $existing['email'],
            'phone'             => array_key_exists('phone', $data) ? (!empty($data['phone']) ? trim((string) $data['phone']) : null) : $existing['phone'],
            'category'          => $data['category'] ?? $existing['category'],
            'organization_name' => array_key_exists('organization_name', $data) ? (!empty($data['organization_name']) ? trim((string) $data['organization_name']) : null) : $existing['organization_name'],
            'status'            => $data['status'] ?? $existing['status'],
        ];

        $oldStatus = $existing['status'];
        $newStatus = $updateData['status'];

        $this->participantRepo->update($id, $updateData);
        $updated = $this->participantRepo->findById($id);

        // Audit Trail
        $this->auditService->log(
            'participant.update',
            'participant',
            $id,
            [
                'category'  => $updateData['category'],
                'has_email' => !empty($updateData['email']),
                'has_phone' => !empty($updateData['phone']),
                'status'    => $newStatus,
            ],
            $actorId
        );

        if ($oldStatus !== $newStatus) {
            $this->auditService->log(
                'participant.status_change',
                'participant',
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
     * Sanitize and scrub status transition reason.
     * Complies with Rule 11: Prohibits clinical, distress, counselling, and medical details.
     */
    public static function sanitizeStatusReason(?string $reason): ?string
    {
        if (empty($reason)) {
            return null;
        }

        $clean = mb_substr(trim(strip_tags((string) $reason)), 0, 255);
        if ($clean === '') {
            return null;
        }

        // Proactive keyword scrubbing for sensitive clinical/distress information
        $prohibited = [
            'suicide', 'suicidal', 'counselling', 'counseling', 'depression', 'depressed',
            'psychological', 'psychiatric', 'therapy', 'therapist', 'medication', 'distress',
            'self-harm', 'mental health', 'clinical'
        ];

        foreach ($prohibited as $term) {
            $clean = preg_replace('/\b' . preg_quote($term, '/') . '\b/i', '[REDACTED]', (string) $clean);
        }

        return $clean;
    }

    /**
     * Transition participant lifecycle status with optional audit reason.
     * Does NOT modify database schema; reason is stored exclusively in audit_logs.metadata.
     */
    public function updateStatus(int $id, string $status, ?string $reason, int $actorId): bool
    {
        $status = trim($status);
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid participant status: {$status}");
        }

        $participant = $this->participantRepo->findById($id);
        if (!$participant) {
            throw new RuntimeException("Participant with ID {$id} not found.");
        }

        $oldStatus = $participant['status'];
        if ($oldStatus === $status) {
            return true;
        }

        $this->participantRepo->updateStatus($id, $status);

        // Sanitize reason for audit log; scrub any clinical/sensitive terms
        $sanitizedReason = self::sanitizeStatusReason($reason);

        $metadata = [
            'old_status' => $oldStatus,
            'new_status' => $status,
        ];
        if ($sanitizedReason !== null && $sanitizedReason !== '') {
            $metadata['reason'] = $sanitizedReason;
        }

        $this->auditService->log(
            'participant.status_change',
            'participant',
            $id,
            $metadata,
            $actorId
        );

        return true;
    }
}
