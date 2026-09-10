<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\CapacityExceededException;
use App\Core\Exceptions\InvalidStateTransitionException;
use App\Core\Exceptions\RegistrationCodeGenerationException;
use App\Core\Exceptions\ValidationException;
use App\Repositories\EventRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use RuntimeException;

/**
 * Event Registration Service
 * Coordinates registration lifecycle, capacity evaluation, duplicate prevention,
 * pass code generation, and transactional boundaries.
 */
class RegistrationService
{
    private RegistrationRepository $registrationRepo;
    private EventRepository $eventRepo;
    private ParticipantRepository $participantRepo;
    private ParticipantService $participantService;
    private AuditService $auditService;

    public function __construct(
        ?RegistrationRepository $registrationRepo = null,
        ?EventRepository $eventRepo = null,
        ?ParticipantRepository $participantRepo = null,
        ?ParticipantService $participantService = null,
        ?AuditService $auditService = null
    ) {
        $this->registrationRepo = $registrationRepo ?? new RegistrationRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->participantRepo = $participantRepo ?? new ParticipantRepository();
        $this->participantService = $participantService ?? new ParticipantService();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Generate unique registration pass code: REG-{YY}-{5_CHAR_CROCKFORD_BASE32}
     * Retries collision up to 10 times in application space before throwing controlled exception.
     *
     * @throws RegistrationCodeGenerationException
     */
    public function generateUniqueRegistrationCode(): string
    {
        $year = date('y');
        // Crockford Base32 subset: 32 chars omitting 0, O, 1, I
        $alphabet = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
        $alphabetLength = strlen($alphabet);
        $maxRetries = 10;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $bytes = random_bytes(5);
            $randomStr = '';
            for ($i = 0; $i < 5; $i++) {
                $randomStr .= $alphabet[ord($bytes[$i]) % $alphabetLength];
            }

            $code = "REG-{$year}-{$randomStr}";

            if (!$this->registrationRepo->codeExists($code)) {
                return $code;
            }
        }

        throw new RegistrationCodeGenerationException(
            "Failed to generate a unique registration code after {$maxRetries} attempts. Please try again."
        );
    }

    /**
     * Mask pass code for audit logs and non-sensitive display (e.g. REG-26-****3).
     * Strictly prevents full bearer codes from leaking into audit metadata or logs.
     */
    public static function maskCode(string $code): string
    {
        $trimmed = trim($code);
        if (preg_match('/^(REG-\d{2}-)([A-Z0-9]{4})([A-Z0-9])$/i', $trimmed, $m)) {
            return $m[1] . '****' . $m[3];
        }

        $len = strlen($trimmed);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($trimmed, 0, 4) . '****' . substr($trimmed, -1);
    }

    /**
     * Validate operational administrative notes.
     * Enforces non-sensitive logistics only (max 255 chars).
     * Prohibited clinical, psychological, counselling, distress, suicide-risk, or medication terms
     * are strictly REJECTED with a ValidationException (no silent redaction).
     *
     * @throws ValidationException
     */
    public function validateAdminNotes(?string $notes): void
    {
        if ($notes === null || trim($notes) === '') {
            return;
        }

        $trimmed = trim($notes);
        if (mb_strlen($trimmed) > 255) {
            throw new ValidationException(
                'Administrative notes must not exceed 255 characters.',
                ['admin_notes' => 'Administrative notes must not exceed 255 characters.']
            );
        }

        $sensitivePatterns = [
            'suicide',
            'counselling',
            'counseling',
            'depression',
            'therapy',
            'distress',
            'medication',
            'psychiatric',
            'psychiatry',
            'psychological',
            'psychologist',
            'self-harm',
            'mental health',
            'crisis',
            'clinical',
            'diagnosis',
            'prescribed',
            'bipolar',
            'schizophrenia',
        ];

        $lower = mb_strtolower($trimmed);
        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                throw new ValidationException(
                    'Administrative notes must contain non-sensitive operational logistics only. Clinical, medical, or counselling content is strictly prohibited.',
                    ['admin_notes' => 'Clinical, medical, or counselling content is strictly prohibited.']
                );
            }
        }
    }

    /**
     * Register an existing participant for an event.
     * Executes inside Database::transaction() with pessimistic row locking (SELECT ... FOR UPDATE).
     *
     * @throws RuntimeException
     * @throws ValidationException
     * @throws RegistrationCodeGenerationException
     */
    public function registerParticipant(int $eventId, int $participantId, ?string $adminNotes = null, int $actorId = 0): array
    {
        $this->validateAdminNotes($adminNotes);

        return Database::transaction(function () use ($eventId, $participantId, $adminNotes, $actorId) {
            // 1. Lock event row FOR UPDATE to evaluate capacity and eligibility atomically
            $event = $this->registrationRepo->lockEventForRegistration($eventId);
            if (!$event || !empty($event['deleted_at'])) {
                throw new RuntimeException("Event not found or has been deleted.");
            }

            // Strict Event Eligibility Gate: status must be 'published'
            if ($event['status'] !== 'published') {
                throw new RuntimeException("Registrations are only permitted for published events. Current event status is '{$event['status']}'.");
            }

            // Verify registration deadline if configured
            if (!empty($event['registration_deadline'])) {
                $deadlineTime = strtotime((string) $event['registration_deadline']);
                if ($deadlineTime < time()) {
                    throw new RuntimeException("The registration deadline for this event has passed.");
                }
            }

            // 2. Verify participant identity & status
            $participant = $this->participantRepo->findById($participantId);
            if (!$participant) {
                throw new RuntimeException("Participant with ID {$participantId} not found.");
            }

            if ($participant['status'] === 'blocked') {
                throw new RuntimeException("Registration cannot be processed at this time.");
            }

            // 3. Duplicate Registration Check with lock (SELECT ... FOR UPDATE)
            $existing = $this->registrationRepo->findByEventAndParticipantForUpdate($eventId, $participantId);
            if ($existing !== null) {
                $currentStatus = $existing['status'];

                // Active registrations: return existing without duplicate row
                if ($currentStatus === 'confirmed') {
                    return [
                        'status'       => 'already_confirmed',
                        'registration' => $this->registrationRepo->findById((int) $existing['id']),
                        'message'      => "Participant is already registered for this event.",
                    ];
                }

                if ($currentStatus === 'pending') {
                    return [
                        'status'       => 'already_pending',
                        'registration' => $this->registrationRepo->findById((int) $existing['id']),
                        'message'      => "Participant already has a pending registration awaiting coordinator approval.",
                    ];
                }

                if ($currentStatus === 'waitlisted') {
                    return [
                        'status'       => 'already_waitlisted',
                        'registration' => $this->registrationRepo->findById((int) $existing['id']),
                        'message'      => "Participant is currently on the waitlist for this event.",
                    ];
                }

                // In-Place Reactivation of Cancelled Registration
                if ($currentStatus === 'cancelled') {
                    $confirmedCount = $this->registrationRepo->countConfirmedByEvent($eventId);
                    $capacity = (int) $event['capacity'];
                    $requiresApproval = (int) ($event['requires_approval'] ?? 0);

                    // Determine target lifecycle status based on current capacity and approval rules
                    if ($requiresApproval === 1) {
                        $targetStatus = 'pending';
                    } elseif ($capacity === 0 || $confirmedCount < $capacity) {
                        $targetStatus = 'confirmed';
                    } else {
                        $targetStatus = 'waitlisted';
                    }

                    // Security: Regenerate brand-new pass code to invalidate any previously shared/printed pass
                    $newCode = $this->generateUniqueRegistrationCode();
                    $previousCode = (string) $existing['registration_code'];

                    $this->registrationRepo->reactivate((int) $existing['id'], $targetStatus, $newCode, $adminNotes);

                    // Forensic Audit logging with masked credentials
                    $this->auditService->log(
                        'registration.reactivate',
                        'registration',
                        (int) $existing['id'],
                        [
                            'event_id'             => $eventId,
                            'participant_id'       => $participantId,
                            'previous_status'      => 'cancelled',
                            'previous_masked_code' => self::maskCode($previousCode),
                            'new_masked_code'      => self::maskCode($newCode),
                            'new_status'           => $targetStatus,
                        ],
                        $actorId
                    );

                    return [
                        'status'       => 'reactivated',
                        'registration' => $this->registrationRepo->findById((int) $existing['id']),
                        'new_status'   => $targetStatus,
                        'message'      => "Cancelled registration successfully reactivated with a new pass code.",
                    ];
                }
            }

            // 4. Fresh Registration Creation
            $confirmedCount = $this->registrationRepo->countConfirmedByEvent($eventId);
            $capacity = (int) $event['capacity'];
            $requiresApproval = (int) ($event['requires_approval'] ?? 0);

            // Determine target lifecycle status:
            // - requires_approval = 1 -> pending (does NOT consume capacity)
            // - capacity = 0 (unlimited) -> confirmed
            // - confirmed_count < capacity -> confirmed
            // - confirmed_count >= capacity -> waitlisted
            if ($requiresApproval === 1) {
                $targetStatus = 'pending';
            } elseif ($capacity === 0 || $confirmedCount < $capacity) {
                $targetStatus = 'confirmed';
            } else {
                $targetStatus = 'waitlisted';
            }

            $registrationCode = $this->generateUniqueRegistrationCode();

            $newRegId = $this->registrationRepo->create([
                'registration_code' => $registrationCode,
                'event_id'          => $eventId,
                'participant_id'    => $participantId,
                'status'            => $targetStatus,
                'attendance_status' => 'unmarked',
                'admin_notes'       => $adminNotes,
            ]);

            // Forensic Audit logging with masked code
            $this->auditService->log(
                'registration.create',
                'registration',
                $newRegId,
                [
                    'event_id'       => $eventId,
                    'participant_id' => $participantId,
                    'status'         => $targetStatus,
                    'masked_code'    => self::maskCode($registrationCode),
                ],
                $actorId
            );

            return [
                'status'       => 'created',
                'registration' => $this->registrationRepo->findById($newRegId),
                'new_status'   => $targetStatus,
                'message'      => "Registration successfully created.",
            ];
        });
    }

    /**
     * Inline participant resolution/creation and event registration.
     * Both participant creation and registration execute inside the SAME database transaction.
     * If registration fails for any reason, newly created participants roll back completely.
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function createRegistrationWithParticipant(int $eventId, array $participantData, ?string $adminNotes = null, int $actorId = 0): array
    {
        $this->validateAdminNotes($adminNotes);

        return Database::transaction(function () use ($eventId, $participantData, $adminNotes, $actorId) {
            // Reuses ParticipantService deduplication, consent validation, and server-side timestamps
            $participant = $this->participantService->resolveOrCreateParticipant($participantData, $actorId);
            $participantId = (int) $participant['id'];

            return $this->registerParticipant($eventId, $participantId, $adminNotes, $actorId);
        });
    }

    /**
     * Approve a pending registration.
     * Executes inside transaction with pessimistic locking on both event and registration rows.
     * Checks real-time confirmed capacity. If capacity is exhausted, throws CapacityExceededException.
     *
     * @throws InvalidStateTransitionException
     * @throws CapacityExceededException
     */
    public function approveRegistration(int $id, int $actorId = 0): array
    {
        return Database::transaction(function () use ($id, $actorId) {
            $reg = $this->registrationRepo->lockRegistration($id);
            if (!$reg) {
                throw new RuntimeException("Registration with ID {$id} not found.");
            }

            if ($reg['status'] !== 'pending') {
                throw new InvalidStateTransitionException("Only registrations in 'pending' status can be approved. Current status is '{$reg['status']}'.");
            }

            $eventId = (int) $reg['event_id'];
            $event = $this->registrationRepo->lockEventForRegistration($eventId);
            if (!$event || !empty($event['deleted_at'])) {
                throw new RuntimeException("Referenced event not found or deleted.");
            }

            $confirmedCount = $this->registrationRepo->countConfirmedByEvent($eventId);
            $capacity = (int) $event['capacity'];

            // Strict capacity check under lock: do not oversubscribe
            if ($capacity > 0 && $confirmedCount >= $capacity) {
                throw new CapacityExceededException(
                    "Event capacity is full ({$confirmedCount}/{$capacity} seats confirmed). Cannot approve registration as confirmed. You may leave it pending or move it to the waitlist."
                );
            }

            $this->registrationRepo->updateStatus($id, 'confirmed');

            // Audit log with masked code
            $this->auditService->log(
                'registration.approve',
                'registration',
                $id,
                [
                    'event_id'        => $eventId,
                    'previous_status' => 'pending',
                    'status'          => 'confirmed',
                    'masked_code'     => self::maskCode((string) $reg['registration_code']),
                ],
                $actorId
            );

            return $this->registrationRepo->findById($id) ?? [];
        });
    }

    /**
     * Move a pending registration to waitlisted when event capacity is full.
     * Executes transactionally under pessimistic locks.
     * Verifies that the event is genuinely full; rejects transition if confirmed seats remain available.
     *
     * @throws InvalidStateTransitionException
     */
    public function movePendingToWaitlist(int $id, int $actorId = 0): array
    {
        return Database::transaction(function () use ($id, $actorId) {
            $reg = $this->registrationRepo->lockRegistration($id);
            if (!$reg) {
                throw new RuntimeException("Registration with ID {$id} not found.");
            }

            if ($reg['status'] !== 'pending') {
                throw new InvalidStateTransitionException("Only registrations in 'pending' status can be moved to the waitlist. Current status is '{$reg['status']}'.");
            }

            $eventId = (int) $reg['event_id'];
            $event = $this->registrationRepo->lockEventForRegistration($eventId);
            if (!$event || !empty($event['deleted_at'])) {
                throw new RuntimeException("Referenced event not found or deleted.");
            }

            $confirmedCount = $this->registrationRepo->countConfirmedByEvent($eventId);
            $capacity = (int) $event['capacity'];

            // Rejection condition: Event must genuinely be full
            if ($capacity === 0 || $confirmedCount < $capacity) {
                throw new InvalidStateTransitionException(
                    "Cannot move pending registration to waitlist: event still has available confirmed capacity ({$confirmedCount}/{$capacity} seats confirmed). Please approve the registration instead."
                );
            }

            $this->registrationRepo->updateStatus($id, 'waitlisted');

            // Audit log with masked code
            $this->auditService->log(
                'registration.waitlist_move',
                'registration',
                $id,
                [
                    'event_id'        => $eventId,
                    'previous_status' => 'pending',
                    'status'          => 'waitlisted',
                    'masked_code'     => self::maskCode((string) $reg['registration_code']),
                ],
                $actorId
            );

            return $this->registrationRepo->findById($id) ?? [];
        });
    }

    /**
     * Promote a waitlisted registration to confirmed.
     * Executes inside transaction with pessimistic locks on both event and registration rows.
     * Recalculates confirmed count and verifies capacity before promoting.
     *
     * @throws InvalidStateTransitionException
     * @throws CapacityExceededException
     */
    public function promoteWaitlist(int $id, int $actorId = 0): array
    {
        return Database::transaction(function () use ($id, $actorId) {
            $reg = $this->registrationRepo->lockRegistration($id);
            if (!$reg) {
                throw new RuntimeException("Registration with ID {$id} not found.");
            }

            if ($reg['status'] !== 'waitlisted') {
                throw new InvalidStateTransitionException("Only waitlisted registrations can be promoted. Current status is '{$reg['status']}'.");
            }

            $eventId = (int) $reg['event_id'];
            $event = $this->registrationRepo->lockEventForRegistration($eventId);
            if (!$event || !empty($event['deleted_at'])) {
                throw new RuntimeException("Referenced event not found or deleted.");
            }

            $confirmedCount = $this->registrationRepo->countConfirmedByEvent($eventId);
            $capacity = (int) $event['capacity'];

            if ($capacity > 0 && $confirmedCount >= $capacity) {
                throw new CapacityExceededException(
                    "Cannot promote waitlisted registration: event capacity is currently full ({$confirmedCount}/{$capacity} seats confirmed)."
                );
            }

            $this->registrationRepo->updateStatus($id, 'confirmed');

            // Audit log with masked code
            $this->auditService->log(
                'registration.waitlist_promote',
                'registration',
                $id,
                [
                    'event_id'        => $eventId,
                    'previous_status' => 'waitlisted',
                    'status'          => 'confirmed',
                    'masked_code'     => self::maskCode((string) $reg['registration_code']),
                ],
                $actorId
            );

            return $this->registrationRepo->findById($id) ?? [];
        });
    }

    /**
     * Cancel an active, pending, or waitlisted registration.
     * Rejects cancellation of already-cancelled registrations.
     * Cancelling a confirmed registration immediately frees up one confirmed capacity seat.
     *
     * @throws InvalidStateTransitionException
     * @throws ValidationException
     */
    public function cancelRegistration(int $id, ?string $reason = null, int $actorId = 0): array
    {
        if ($reason !== null) {
            $this->validateAdminNotes($reason);
        }

        return Database::transaction(function () use ($id, $reason, $actorId) {
            $reg = $this->registrationRepo->lockRegistration($id);
            if (!$reg) {
                throw new RuntimeException("Registration with ID {$id} not found.");
            }

            $currentStatus = $reg['status'];

            // Explicit rejection of already-cancelled records
            if ($currentStatus === 'cancelled') {
                throw new InvalidStateTransitionException("Registration is already cancelled.");
            }

            if (!in_array($currentStatus, ['confirmed', 'pending', 'waitlisted'], true)) {
                throw new InvalidStateTransitionException("Invalid registration status for cancellation: '{$currentStatus}'.");
            }

            $this->registrationRepo->updateStatus($id, 'cancelled');

            // Audit log with masked code and clean operational reason
            $this->auditService->log(
                'registration.cancel',
                'registration',
                $id,
                [
                    'event_id'        => (int) $reg['event_id'],
                    'previous_status' => $currentStatus,
                    'status'          => 'cancelled',
                    'masked_code'     => self::maskCode((string) $reg['registration_code']),
                    'reason'          => $reason ? trim($reason) : 'Administrative cancellation',
                ],
                $actorId
            );

            return $this->registrationRepo->findById($id) ?? [];
        });
    }

    /**
     * Apply server-side contact PII masking for staff and viewer administrative roles.
     * Coordinator and super_admin receive unmasked contact information.
     */
    public function maskRegistration(array $registration, string $userRole): array
    {
        if (in_array($userRole, [RoleService::ROLE_STAFF, RoleService::ROLE_VIEWER], true)) {
            if (!empty($registration['participant_email'])) {
                $registration['participant_email'] = ParticipantService::maskEmail($registration['participant_email']);
            }
            if (!empty($registration['participant_phone'])) {
                $registration['participant_phone'] = ParticipantService::maskPhone($registration['participant_phone']);
            }
            // If participant fields are indexed without prefix
            if (!empty($registration['email'])) {
                $registration['email'] = ParticipantService::maskEmail($registration['email']);
            }
            if (!empty($registration['phone'])) {
                $registration['phone'] = ParticipantService::maskPhone($registration['phone']);
            }
        }

        return $registration;
    }

    /**
     * Apply server-side contact PII masking across a list of registrations.
     */
    public function maskRegistrationList(array $registrations, string $userRole): array
    {
        if (!in_array($userRole, [RoleService::ROLE_STAFF, RoleService::ROLE_VIEWER], true)) {
            return $registrations;
        }

        return array_map(fn($reg) => $this->maskRegistration($reg, $userRole), $registrations);
    }

    /**
     * Format minimal-disclosure public pass data.
     * Contains NO contact PII, internal identifiers, administrative data, or sensitive personal information.
     */
    public function formatPublicPass(array $registration): array
    {
        return [
            'attendee_name'       => $registration['participant_name'] ?? $registration['full_name'] ?? '',
            'category'            => $registration['participant_category'] ?? $registration['category'] ?? 'community',
            'event_title'         => $registration['event_title'] ?? '',
            'campaign_title'      => $registration['campaign_title'] ?? '',
            'event_format'        => $registration['event_format'] ?? 'in_person',
            'event_start_time'    => $registration['event_start_time'] ?? '',
            'event_end_time'      => $registration['event_end_time'] ?? '',
            'venue_name'          => $registration['event_venue_name'] ?? null,
            'venue_address'       => $registration['event_venue_address'] ?? null,
            'online_meeting_url'  => $registration['event_online_meeting_url'] ?? null,
            'registration_code'   => $registration['registration_code'] ?? '',
            'status'              => 'confirmed',
        ];
    }
}
