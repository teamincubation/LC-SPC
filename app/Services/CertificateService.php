<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\CertificateException;
use App\Core\Exceptions\ValidationException;
use App\Core\QrCode;
use App\Repositories\CertificateRepository;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use InvalidArgumentException;
use Throwable;

/**
 * Certificate Domain Service
 * Manages certificate eligibility verification, dual-identifier generation,
 * atomic single/bulk issuance, superseded credentials, and on-demand JPG rendering.
 */
class CertificateService
{
    public const TYPE_PARTICIPATION = 'participation';
    public const TYPE_VOLUNTEER     = 'volunteer';
    public const TYPE_SPEAKER       = 'speaker';
    public const TYPE_APPRECIATION  = 'appreciation';

    public const VALID_TYPES = [
        self::TYPE_PARTICIPATION,
        self::TYPE_VOLUNTEER,
        self::TYPE_SPEAKER,
        self::TYPE_APPRECIATION,
    ];

    private const CROCKFORD_CHARS = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private CertificateRepository $certRepo;
    private RegistrationRepository $regRepo;
    private EventRepository $eventRepo;
    private AuditService $auditService;

    public function __construct(
        ?CertificateRepository $certRepo = null,
        ?RegistrationRepository $regRepo = null,
        ?EventRepository $eventRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->certRepo = $certRepo ?? new CertificateRepository();
        $this->regRepo = $regRepo ?? new RegistrationRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Retrieve certificate by primary ID with server-side privacy masking.
     */
    public function getCertificateById(int $id, string $userRole): ?array
    {
        $cert = $this->certRepo->findById($id);
        if (!$cert) {
            return null;
        }

        return $this->maskCertificate($cert, $userRole);
    }

    /**
     * Retrieve raw certificate by primary ID without role masking (for PDF/JPG generation & mutation).
     */
    public function getRawCertificateById(int $id): ?array
    {
        return $this->certRepo->findById($id);
    }

    /**
     * Retrieve certificate by 256-bit verification token.
     */
    public function getCertificateByToken(string $token): ?array
    {
        return $this->certRepo->findByToken(trim($token));
    }

    /**
     * Generate non-sequential, Crockford Base32 human-readable certificate number.
     * Format: LC-{YYYY}-SPC-{5_CROCKFORD}
     */
    public function generateCertificateNumber(): string
    {
        $year = date('Y');
        $charsetLen = strlen(self::CROCKFORD_CHARS);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $code .= self::CROCKFORD_CHARS[random_int(0, $charsetLen - 1)];
            }

            $candidate = "LC-{$year}-SPC-{$code}";
            if ($this->certRepo->findByNumber($candidate) === null) {
                return $candidate;
            }
        }

        throw new CertificateException('Failed to generate unique certificate number after multiple attempts.', 500);
    }

    /**
     * Generate 256-bit CSPRNG verification bearer token (64 lowercase hex characters).
     */
    public function generateVerificationToken(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = bin2hex(random_bytes(32));
            if ($this->certRepo->findByToken($token) === null) {
                return $token;
            }
        }

        throw new CertificateException('Failed to generate unique verification token.', 500);
    }

    /**
     * Validate eligibility rules before issuance.
     * Throws CertificateException or ValidationException on failure.
     */
    public function validateEligibility(array $registration, string $type, array $options = []): void
    {
        // 1. Validate Certificate Type
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new CertificateException("Invalid certificate type: {$type}", 422);
        }

        // 2. Event Eligibility Checks
        $eventId = (int) ($registration['event_id'] ?? 0);
        $event = $this->eventRepo->findById($eventId);
        if (!$event || !empty($event['deleted_at'])) {
            throw new CertificateException('Event not found or has been deleted.', 404);
        }

        $eventStatus = $event['status'] ?? 'draft';
        if (!in_array($eventStatus, ['published', 'ongoing', 'completed'], true)) {
            throw new CertificateException("Cannot issue certificates for an event in '{$eventStatus}' status.", 403);
        }

        // Event timeline check: event must have started
        $now = time();
        $startTime = strtotime((string) $event['start_time']);
        if ($now < $startTime) {
            throw new CertificateException('Cannot issue certificates before the event has started.', 422);
        }

        // 3. Registration Status Check: strictly 'confirmed'
        $regStatus = $registration['status'] ?? '';
        if ($regStatus !== 'confirmed') {
            throw new CertificateException("Registration status is '{$regStatus}'. Only confirmed registrations are eligible for certificates.", 403);
        }

        // 4. Participant Status Check
        $partStatus = $registration['participant_status'] ?? 'active';
        if ($partStatus === 'blocked') {
            throw new CertificateException('Participant record is blocked from receiving certificates.', 403);
        }

        if ($partStatus === 'flagged') {
            // Flagged attendees require manual confirmation & non-clinical operational justification
            $confirmed = !empty($options['confirm_flagged']);
            $reason = trim((string) ($options['flag_override_reason'] ?? ''));

            if (!$confirmed || mb_strlen($reason) < 10) {
                throw new CertificateException(
                    'Issuing a certificate to a flagged participant requires explicit coordinator confirmation and an operational justification (minimum 10 characters).',
                    422,
                    ['confirm_flagged' => 'Confirmation is required', 'flag_override_reason' => 'Reason must be at least 10 characters']
                );
            }

            self::validateNonClinicalReason($reason, 'flag_override_reason');
        }

        // 5. Attendance Status Check
        $attStatus = $registration['attendance_status'] ?? 'unmarked';
        if ($type === self::TYPE_APPRECIATION) {
            // Appreciation requires confirmed attendance or presence; absent or cancelled is prohibited
            if (in_array($attStatus, ['absent'], true)) {
                throw new CertificateException("Cannot issue certificate of appreciation to attendees marked absent.", 422);
            }
        } else {
            // Participation, volunteer, and speaker strictly require attended
            if ($attStatus !== 'attended') {
                $statusDesc = match ($attStatus) {
                    'absent'   => 'marked absent',
                    'excused'  => 'excused from attendance',
                    default    => 'unmarked (did not check in)',
                };
                throw new CertificateException("Participant was {$statusDesc}. Attendance is mandatory for {$type} certificates.", 422);
            }
        }

        // 6. Active Uniqueness Check
        $existing = $this->certRepo->findActiveByRegistrationAndType((int) $registration['id'], $type);
        if ($existing !== null) {
            throw new CertificateException(
                "An active {$type} certificate ({$existing['certificate_number']}) has already been issued for this registration.",
                409,
                ['certificate_id' => $existing['id'], 'certificate_number' => $existing['certificate_number']]
            );
        }
    }

    /**
     * Issue an individual certificate.
     */
    public function issueSingle(int $registrationId, string $type, int $userId, string $userRole, array $options = []): array
    {
        // RBAC Enforcement: coordinator (30) or super_admin (40) only
        if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            throw new CertificateException('Unauthorized: Only coordinators and administrators may issue certificates.', 403);
        }

        $registration = $this->regRepo->findById($registrationId);
        if (!$registration) {
            throw new CertificateException('Registration record not found.', 404);
        }

        // Validate complete eligibility rules
        $this->validateEligibility($registration, $type, $options);

        // Recipient legal name snapshot
        $recipientName = trim((string) ($registration['participant_name'] ?? $registration['full_name'] ?? ''));
        if ($recipientName === '') {
            throw new CertificateException('Participant name is missing from registration.', 422);
        }

        $certNumber = $this->generateCertificateNumber();
        $token = $this->generateVerificationToken();
        $issueDate = $options['issue_date'] ?? date('Y-m-d');

        $certData = [
            'certificate_number'      => $certNumber,
            'verification_token'      => $token,
            'registration_id'         => $registrationId,
            'recipient_name_snapshot' => $recipientName,
            'type'                    => $type,
            'issue_date'              => $issueDate,
            'status'                  => 'active',
            'issued_by'               => $userId,
        ];

        Database::beginTransaction();
        try {
            $certId = $this->certRepo->insert($certData);

            // Audit log creation (bearer token is masked to prevent exposure in logs)
            $this->auditService->log(
                'certificate.issue',
                'certificates',
                $certId,
                [
                    'certificate_number' => $certNumber,
                    'masked_token'       => self::maskToken($token),
                    'type'               => $type,
                    'registration_id'    => $registrationId,
                    'event_id'           => (int) $registration['event_id'],
                    'recipient_name'     => $recipientName,
                    'participant_status' => $registration['participant_status'] ?? 'active',
                    'flag_override'      => !empty($options['confirm_flagged']),
                    'flag_reason'        => $options['flag_override_reason'] ?? null,
                ],
                $userId,
                $userRole
            );

            Database::commit();

            return array_merge($certData, ['id' => $certId]);
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Controlled Bulk Certificate Issuance.
     * Issues certificates for selected eligible attendees of an event.
     * Flagged participants, absent/unmarked attendees, and already-active records are strictly excluded.
     */
    public function bulkIssue(int $eventId, string $type, array $registrationIds, int $userId, string $userRole): array
    {
        // RBAC Enforcement
        if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            throw new CertificateException('Unauthorized: Only coordinators and administrators may perform bulk issuance.', 403);
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new CertificateException("Invalid certificate type: {$type}", 422);
        }

        $event = $this->eventRepo->findById($eventId);
        if (!$event || !empty($event['deleted_at'])) {
            throw new CertificateException('Event not found or has been deleted.', 404);
        }

        if (!in_array($event['status'] ?? '', ['published', 'ongoing', 'completed'], true)) {
            throw new CertificateException("Event status '{$event['status']}' is not eligible for certificate issuance.", 403);
        }

        if (time() < strtotime((string) $event['start_time'])) {
            throw new CertificateException('Cannot issue certificates before the event has started.', 422);
        }

        // Cap batch size at 100
        $ids = array_unique(array_filter(array_map('intval', $registrationIds)));
        if (empty($ids)) {
            throw new CertificateException('No registrations selected for bulk issuance.', 422);
        }

        if (count($ids) > 100) {
            throw new CertificateException('Bulk issuance is capped at a maximum of 100 certificates per batch.', 422);
        }

        $issued = 0;
        $skipped = 0;
        $alreadyIssued = 0;
        $failed = 0;
        $details = [];

        Database::beginTransaction();
        try {
            foreach ($ids as $regId) {
                $registration = $this->regRepo->findById($regId);
                if (!$registration || (int) $registration['event_id'] !== $eventId) {
                    $failed++;
                    $details[] = ['registration_id' => $regId, 'status' => 'failed', 'reason' => 'Invalid registration or event mismatch'];
                    continue;
                }

                // Check registration status
                if (($registration['status'] ?? '') !== 'confirmed') {
                    $skipped++;
                    $details[] = ['registration_id' => $regId, 'status' => 'skipped', 'reason' => 'Registration not confirmed'];
                    continue;
                }

                // Check attendance
                if (($registration['attendance_status'] ?? '') !== 'attended') {
                    $skipped++;
                    $details[] = ['registration_id' => $regId, 'status' => 'skipped', 'reason' => 'Attendee was not marked attended'];
                    continue;
                }

                // Flagged participants are strictly excluded from bulk batches
                $partStatus = $registration['participant_status'] ?? 'active';
                if ($partStatus === 'flagged') {
                    $skipped++;
                    $details[] = ['registration_id' => $regId, 'status' => 'skipped', 'reason' => 'Flagged participant excluded from bulk batch'];
                    continue;
                }
                if ($partStatus === 'blocked') {
                    $skipped++;
                    $details[] = ['registration_id' => $regId, 'status' => 'skipped', 'reason' => 'Blocked participant'];
                    continue;
                }

                // Check if already active
                $existing = $this->certRepo->findActiveByRegistrationAndType($regId, $type);
                if ($existing !== null) {
                    $alreadyIssued++;
                    $details[] = ['registration_id' => $regId, 'status' => 'already_issued', 'certificate_number' => $existing['certificate_number']];
                    continue;
                }

                // Issue certificate
                $recipientName = trim((string) ($registration['participant_name'] ?? $registration['full_name'] ?? ''));
                if ($recipientName === '') {
                    $failed++;
                    $details[] = ['registration_id' => $regId, 'status' => 'failed', 'reason' => 'Missing participant name'];
                    continue;
                }

                $certNumber = $this->generateCertificateNumber();
                $token = $this->generateVerificationToken();

                $certId = $this->certRepo->insert([
                    'certificate_number'      => $certNumber,
                    'verification_token'      => $token,
                    'registration_id'         => $regId,
                    'recipient_name_snapshot' => $recipientName,
                    'type'                    => $type,
                    'issue_date'              => date('Y-m-d'),
                    'status'                  => 'active',
                    'issued_by'               => $userId,
                ]);

                $issued++;
                $details[] = [
                    'registration_id'    => $regId,
                    'status'             => 'issued',
                    'certificate_id'     => $certId,
                    'certificate_number' => $certNumber,
                    'recipient_name'     => $recipientName,
                ];
            }

            // Log bulk audit summary
            if ($issued > 0) {
                $this->auditService->log(
                    'certificate.bulk_issue',
                    'events',
                    $eventId,
                    [
                        'event_id'       => $eventId,
                        'type'           => $type,
                        'issued_count'   => $issued,
                        'skipped_count'  => $skipped,
                        'already_issued' => $alreadyIssued,
                        'failed_count'   => $failed,
                    ],
                    $userId,
                    $userRole
                );
            }

            Database::commit();

            return [
                'issued'         => $issued,
                'skipped'        => $skipped,
                'already_issued' => $alreadyIssued,
                'failed'         => $failed,
                'details'        => $details,
            ];
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Revoke an active certificate permanently.
     */
    public function revokeCertificate(int $certificateId, string $reason, int $userId, string $userRole): array
    {
        // RBAC Enforcement
        if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            throw new CertificateException('Unauthorized: Only coordinators and administrators may revoke certificates.', 403);
        }

        $cert = $this->certRepo->findById($certificateId);
        if (!$cert) {
            throw new CertificateException('Certificate not found.', 404);
        }

        if (($cert['status'] ?? '') === 'revoked') {
            throw new CertificateException('Certificate is already revoked and cannot be revoked again.', 422);
        }

        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 5) {
            throw new CertificateException('A valid operational revocation reason (minimum 5 characters) is required.', 422);
        }

        self::validateNonClinicalReason($cleanReason, 'revocation_reason');

        Database::beginTransaction();
        try {
            $this->certRepo->revoke($certificateId, $userId, $cleanReason);

            $this->auditService->log(
                'certificate.revoke',
                'certificates',
                $certificateId,
                [
                    'certificate_number' => $cert['certificate_number'],
                    'registration_id'    => $cert['registration_id'],
                    'reason'             => $cleanReason,
                    'previous_status'    => 'active',
                    'new_status'         => 'revoked',
                ],
                $userId,
                $userRole
            );

            Database::commit();

            return [
                'id'                 => $certificateId,
                'certificate_number' => $cert['certificate_number'],
                'status'             => 'revoked',
                'revocation_reason'  => $cleanReason,
                'revoked_at'         => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Clerical Name Correction & Superseded Credential Re-issuance.
     * Revokes the existing certificate record and generates a brand new active record
     * with the corrected legal name snapshot, new certificate number, and new verification token.
     */
    public function reissueNameCorrection(int $certificateId, string $newName, string $reason, int $userId, string $userRole): array
    {
        // RBAC Enforcement
        if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            throw new CertificateException('Unauthorized: Only coordinators and administrators may re-issue certificates.', 403);
        }

        $oldCert = $this->certRepo->findById($certificateId);
        if (!$oldCert) {
            throw new CertificateException('Certificate not found.', 404);
        }

        if (($oldCert['status'] ?? '') === 'revoked') {
            throw new CertificateException('Cannot re-issue a previously revoked certificate. Create a new issuance instead.', 422);
        }

        $trimmedName = trim($newName);
        if (mb_strlen($trimmedName) < 2 || mb_strlen($trimmedName) > 150) {
            throw new CertificateException('Recipient name must be between 2 and 150 characters.', 422);
        }

        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 5) {
            throw new CertificateException('Operational justification (minimum 5 characters) is required for name correction.', 422);
        }

        self::validateNonClinicalReason($cleanReason, 'reason');

        Database::beginTransaction();
        try {
            // 1. Invalidate old certificate as superseded
            $revocationRationale = "Superseded by replacement certificate due to clerical name correction: {$cleanReason}";
            $this->certRepo->revoke($certificateId, $userId, $revocationRationale);

            // 2. Generate new credential credentials
            $newCertNumber = $this->generateCertificateNumber();
            $newToken = $this->generateVerificationToken();

            $newCertData = [
                'certificate_number'      => $newCertNumber,
                'verification_token'      => $newToken,
                'registration_id'         => (int) $oldCert['registration_id'],
                'recipient_name_snapshot' => $trimmedName,
                'type'                    => $oldCert['type'],
                'issue_date'              => date('Y-m-d'),
                'status'                  => 'active',
                'issued_by'               => $userId,
            ];

            $newCertId = $this->certRepo->insert($newCertData);

            // 3. Log audit event connecting old and new credentials
            $this->auditService->log(
                'certificate.reissue',
                'certificates',
                $newCertId,
                [
                    'old_certificate_id'     => $certificateId,
                    'old_certificate_number' => $oldCert['certificate_number'],
                    'new_certificate_id'     => $newCertId,
                    'new_certificate_number' => $newCertNumber,
                    'old_name'               => $oldCert['recipient_name_snapshot'],
                    'new_name'               => $trimmedName,
                    'reason'                 => $cleanReason,
                ],
                $userId,
                $userRole
            );

            Database::commit();

            return [
                'old_certificate_id'     => $certificateId,
                'old_certificate_number' => $oldCert['certificate_number'],
                'new_certificate_id'     => $newCertId,
                'new_certificate_number' => $newCertNumber,
                'new_recipient_name'     => $trimmedName,
                'verification_token'     => $newToken,
            ];
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Format minimal-disclosure public verification payload.
     * Contains zero contact PII, internal IDs, or internal administrative revocation text.
     */
    public function formatPublicVerification(array $cert): array
    {
        $status = $cert['status'] ?? 'active';
        $isRevoked = ($status === 'revoked');

        $typeLabel = match ($cert['type'] ?? 'participation') {
            self::TYPE_VOLUNTEER    => 'Certificate of Volunteer Service',
            self::TYPE_SPEAKER      => 'Certificate of Facilitation & Resource Speaker',
            self::TYPE_APPRECIATION => 'Certificate of Appreciation',
            default                 => 'Certificate of Participation',
        };

        return [
            'status'                  => $status,
            'is_revoked'              => $isRevoked,
            'certificate_number'      => $cert['certificate_number'],
            'recipient_name_snapshot' => $cert['recipient_name_snapshot'],
            'type'                    => $cert['type'] ?? 'participation',
            'type_label'              => $typeLabel,
            'event_title'             => $cert['event_title'] ?? '',
            'campaign_title'          => $cert['campaign_title'] ?? '',
            'issue_date'              => $cert['issue_date'] ?? '',
            'event_format'            => $cert['event_format'] ?? 'in_person',
            'event_start_time'        => $cert['event_start_time'] ?? '',
            'issuing_organization'    => 'Listening Community – Suicide Prevention Campaign (LC-SPC)',
            'revoked_at'              => $cert['revoked_at'] ?? null,
            'revocation_statement'    => $isRevoked ? 'This certificate was officially invalidated by Listening Community SPC and is no longer recognized as an authentic credential. Physical or electronic copies of this document are void.' : null,
        ];
    }

    /**
     * Resolve signatories dynamically.
     */
    public function getSignatories(array $event): array
    {
        $coordName = !empty($event['coordinator_name']) ? $event['coordinator_name'] : 'Event Coordinator';
        $orgSignatory = config('app.certificate_signatory', 'Dr. Anand Sharma, Director, LC-SPC');

        return [
            'coordinator' => [
                'name'  => $coordName,
                'title' => 'Event Coordinator',
            ],
            'organization' => [
                'name'  => $orgSignatory,
                'title' => 'Campaign Director, LC-SPC',
            ],
        ];
    }

    /**
     * High-Resolution JPG Generator ($2480 \times 1754$, 300 DPI equivalent).
     * Renders directly to binary JPEG string on demand from the single certificate record.
     */
    public function renderJpg(array $certificate): string
    {
        $w = 2480;
        $h = 1754;

        $im = imagecreatetruecolor($w, $h);

        // Palette
        $bg = imagecolorallocate($im, 255, 255, 255);
        $primaryDark = imagecolorallocate($im, 15, 23, 42);   // #0F172A (Navy)
        $accentGold = imagecolorallocate($im, 180, 130, 40);  // Gold / Bronze
        $textDark = imagecolorallocate($im, 30, 41, 59);      // Slate #1E293B
        $textMuted = imagecolorallocate($im, 100, 116, 139);  // Slate #64748B
        $lineColor = imagecolorallocate($im, 203, 213, 225);  // #CBD5E1

        // Background
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);

        // Elegant double borders
        imagesetthickness($im, 12);
        imagerectangle($im, 70, 70, $w - 70, $h - 70, $primaryDark);
        imagesetthickness($im, 4);
        imagerectangle($im, 90, 90, $w - 90, $h - 90, $accentGold);

        // Corner ornaments
        imagesetthickness($im, 6);
        $cornerLen = 60;
        imageline($im, 110, 110, 110 + $cornerLen, 110, $accentGold);
        imageline($im, 110, 110, 110, 110 + $cornerLen, $accentGold);

        imageline($im, $w - 110, 110, $w - 110 - $cornerLen, 110, $accentGold);
        imageline($im, $w - 110, 110, $w - 110, 110 + $cornerLen, $accentGold);

        imageline($im, 110, $h - 110, 110 + $cornerLen, $h - 110, $accentGold);
        imageline($im, 110, $h - 110, 110, $h - 110 - $cornerLen, $accentGold);

        imageline($im, $w - 110, $h - 110, $w - 110 - $cornerLen, $h - 110, $accentGold);
        imageline($im, $w - 110, $h - 110, $w - 110, $h - 110 - $cornerLen, $accentGold);

        // Determine font paths
        $fontRegular = $this->resolveFont(false);
        $fontBold = $this->resolveFont(true);

        // Title mappings
        $typeLabel = match ($certificate['type'] ?? 'participation') {
            self::TYPE_VOLUNTEER    => 'CERTIFICATE OF VOLUNTEER SERVICE',
            self::TYPE_SPEAKER      => 'CERTIFICATE OF FACILITATION',
            self::TYPE_APPRECIATION => 'CERTIFICATE OF APPRECIATION',
            default                 => 'CERTIFICATE OF PARTICIPATION',
        };

        // Header Text
        $this->drawCenteredText($im, 'LISTENING COMMUNITY', 48, 220, $primaryDark, $fontBold);
        $this->drawCenteredText($im, 'SUICIDE PREVENTION CAMPAIGN &bull; OFFICIAL CREDENTIAL', 24, 275, $accentGold, $fontBold);

        // Certificate Title
        $this->drawCenteredText($im, $typeLabel, 64, 430, $primaryDark, $fontBold);
        $this->drawCenteredText($im, 'This is proudly presented to', 28, 520, $textMuted, $fontRegular);

        // Recipient Legal Name
        $recipientName = mb_strtoupper($certificate['recipient_name_snapshot'] ?? 'ATTENDEE');
        $this->drawCenteredText($im, $recipientName, 72, 650, $primaryDark, $fontBold);

        // Decorative line beneath recipient name
        imagesetthickness($im, 4);
        imageline($im, (int) ($w / 2 - 400), 690, (int) ($w / 2 + 400), 690, $accentGold);

        // Event Recognition Body
        $eventTitle = $certificate['event_title'] ?? 'Community Workshop';
        $this->drawCenteredText($im, 'in recognition of verified active participation and commitment during', 28, 770, $textMuted, $fontRegular);
        $this->drawCenteredText($im, '"' . $eventTitle . '"', 38, 850, $primaryDark, $fontBold);

        // Schedule & Venue details
        $schedule = date('F d, Y', strtotime((string) ($certificate['event_start_time'] ?? $certificate['issue_date'])));
        if (!empty($certificate['event_venue_name'])) {
            $schedule .= ' &bull; ' . $certificate['event_venue_name'];
        }
        $this->drawCenteredText($im, $schedule, 26, 920, $textMuted, $fontRegular);

        // Signatories Section
        $signatories = $this->getSignatories($certificate);

        // Left Signatory (Coordinator)
        $sigLeftX = 400;
        $sigY = 1250;
        imagesetthickness($im, 3);
        imageline($im, $sigLeftX - 200, $sigY, $sigLeftX + 200, $sigY, $lineColor);
        $this->drawCenteredTextAt($im, $signatories['coordinator']['name'], 28, $sigLeftX, $sigY + 45, $primaryDark, $fontBold);
        $this->drawCenteredTextAt($im, $signatories['coordinator']['title'], 22, $sigLeftX, $sigY + 80, $textMuted, $fontRegular);

        // Right Signatory (Organization Director)
        $sigRightX = $w - 400;
        imageline($im, $sigRightX - 200, $sigY, $sigRightX + 200, $sigY, $lineColor);
        $this->drawCenteredTextAt($im, $signatories['organization']['name'], 28, $sigRightX, $sigY + 45, $primaryDark, $fontBold);
        $this->drawCenteredTextAt($im, $signatories['organization']['title'], 22, $sigRightX, $sigY + 80, $textMuted, $fontRegular);

        // High-Density QR Verification Code (Center Bottom)
        $token = $certificate['verification_token'] ?? '';
        $verifyUrl = "https://teami.in/LC/verify/{$token}";
        $qr = new QrCode($verifyUrl);
        $qrSize = 260;
        $qrX = (int) ($w / 2 - ($qrSize / 2));
        $qrY = 1100;
        $qr->drawOnGd($im, $qrX, $qrY, $qrSize, 2);

        // QR Border
        imagesetthickness($im, 2);
        imagerectangle($im, $qrX - 2, $qrY - 2, $qrX + $qrSize + 2, $qrY + $qrSize + 2, $lineColor);

        // Certificate Number & Issue Date below QR
        $certNumber = $certificate['certificate_number'] ?? 'LC-2026-SPC-00000';
        $issueDate = date('M d, Y', strtotime((string) $certificate['issue_date']));
        $this->drawCenteredText($im, "Certificate No: {$certNumber}", 24, 1410, $primaryDark, $fontBold);
        $this->drawCenteredText($im, "Issued on: {$issueDate}", 20, 1445, $textMuted, $fontRegular);
        $this->drawCenteredText($im, "Scan QR to verify authentic credential at teami.in/LC", 18, 1475, $textMuted, $fontRegular);

        // Footer disclaimer
        $this->drawCenteredText($im, 'Official Credential &bull; Listening Community – Suicide Prevention Campaign &bull; All Rights Reserved', 16, 1630, $textMuted, $fontRegular);

        // Revocation banner if status is revoked
        if (($certificate['status'] ?? '') === 'revoked') {
            $revokedRed = imagecolorallocate($im, 220, 38, 38);
            $bannerWhite = imagecolorallocate($im, 255, 255, 255);
            imagefilledrectangle($im, 70, 70, $w - 70, 150, $revokedRed);
            $this->drawCenteredText($im, 'OFFICIALLY REVOKED / INVALID CREDENTIAL', 28, 125, $bannerWhite, $fontBold);
            $this->drawCenteredText($im, 'VOID — REVOKED CREDENTIAL — VOID', 42, 920, $revokedRed, $fontBold);
        }

        ob_start();
        imagejpeg($im, null, 95);
        $jpegData = ob_get_clean();
        imagedestroy($im);

        return (string) $jpegData;
    }

    /**
     * Render official A4 Landscape PDF binary for a certificate.
     */
    public function renderPdf(array $certificate): string
    {
        $jpegData = $this->renderJpg($certificate);

        $metadata = [
            'recipient_name'     => (string) ($certificate['recipient_name_snapshot'] ?? ''),
            'certificate_number' => (string) ($certificate['certificate_number'] ?? ''),
            'event_title'        => (string) ($certificate['event_title'] ?? ''),
            'issue_date'         => (string) ($certificate['issue_date'] ?? ''),
            'verify_url'         => "https://teami.in/LC/verify/" . (string) ($certificate['verification_token'] ?? ''),
        ];

        return $this->encapsulateA4LandscapePdf($jpegData, $metadata);
    }

    /**
     * Encapsulate high-resolution JPEG inside an ISO-compliant A4 Landscape PDF (%PDF-1.4).
     */
    private function encapsulateA4LandscapePdf(string $jpegData, array $metadata): string
    {
        $imgInfo = getimagesizefromstring($jpegData);
        $width = $imgInfo ? $imgInfo[0] : 2480;
        $height = $imgInfo ? $imgInfo[1] : 1754;

        $pageWidthPt = 841.89; // 297mm in points
        $pageHeightPt = 595.28; // 210mm in points

        $esc = function (string $text): string {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        };

        $content = "q\n";
        $content .= sprintf("%.2f 0 0 %.2f 0 0 cm\n", $pageWidthPt, $pageHeightPt);
        $content .= "/Im1 Do\n";
        $content .= "Q\n";
        $content .= "BT\n";
        $content .= "/F1 12 Tf\n";
        $content .= "3 Tr\n"; // Invisible searchable text overlay
        $content .= sprintf("72 500 Td (%s) Tj\n", $esc("Certificate Number: " . ($metadata['certificate_number'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Recipient: " . ($metadata['recipient_name'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Event: " . ($metadata['event_title'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Issue Date: " . ($metadata['issue_date'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Verification URL: " . ($metadata['verify_url'] ?? '')));
        $content .= "ET\n";

        $contentLen = strlen($content);
        $imgLen = strlen($jpegData);

        $offsets = [];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        $offsets[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Contents 4 0 R /Resources << /ProcSet [/PDF /Text /ImageC] /Font << /F1 6 0 R >> /XObject << /Im1 5 0 R >> >> >>\nendobj\n";

        $offsets[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<< /Length {$contentLen} >>\nstream\n{$content}\nendstream\nendobj\n";

        $offsets[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$imgLen} >>\nstream\n{$jpegData}\nendstream\nendobj\n";

        $offsets[6] = strlen($pdf);
        $pdf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 7\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /**
     * Resolve font path with cross-platform fallbacks.
     */
    private function resolveFont(bool $bold = false): ?string
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $candidates = [
            $bold ? $appRoot . '/public/assets/fonts/arialbd.ttf' : $appRoot . '/public/assets/fonts/arial.ttf',
            $bold ? 'C:/Windows/Fonts/arialbd.ttf' : 'C:/Windows/Fonts/arial.ttf',
            $bold ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf' : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            $bold ? '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf' : '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function drawCenteredText($im, string $text, int $size, int $y, int $color, ?string $font): void
    {
        $w = imagesx($im);
        if ($font && file_exists($font)) {
            $bbox = imagettfbbox($size, 0, $font, $text);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $x = (int) (($w - $textWidth) / 2);
            imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        } else {
            // Built-in font fallback
            $fontIdx = 5;
            $charWidth = imagefontwidth($fontIdx);
            $textWidth = strlen($text) * $charWidth;
            $x = (int) (($w - $textWidth) / 2);
            imagestring($im, $fontIdx, $x, $y - 15, $text, $color);
        }
    }

    private function drawCenteredTextAt($im, string $text, int $size, int $centerX, int $y, int $color, ?string $font): void
    {
        if ($font && file_exists($font)) {
            $bbox = imagettfbbox($size, 0, $font, $text);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $x = (int) ($centerX - ($textWidth / 2));
            imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        } else {
            $fontIdx = 5;
            $charWidth = imagefontwidth($fontIdx);
            $textWidth = strlen($text) * $charWidth;
            $x = (int) ($centerX - ($textWidth / 2));
            imagestring($im, $fontIdx, $x, $y - 15, $text, $color);
        }
    }

    /**
     * Validate non-clinical reason against sensitive keywords.
     */
    public static function validateNonClinicalReason(string $reason, string $fieldName = 'reason'): void
    {
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

        $lower = mb_strtolower($reason);
        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                throw new ValidationException(
                    'Operational notes must contain non-sensitive administrative logistics only. Clinical, medical, or counselling content is strictly prohibited.',
                    [$fieldName => 'Clinical, medical, or counselling content is strictly prohibited.']
                );
            }
        }
    }

    /**
     * Mask 256-bit bearer token for safe logging.
     * Example: '4f9b8c...e2d1' -> 'tok_****e2d1'
     */
    public static function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 8) {
            return 'tok_****';
        }
        return 'tok_****' . substr($token, -4);
    }

    /**
     * Apply server-side role privacy masking on a single certificate record.
     */
    public function maskCertificate(array $cert, string $userRole): array
    {
        if (RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            return $cert;
        }

        // Staff (20): Mask contact details
        if ($userRole === RoleService::ROLE_STAFF) {
            if (!empty($cert['participant_email'])) {
                $cert['participant_email'] = ParticipantService::maskEmail($cert['participant_email']);
            }
            if (!empty($cert['participant_phone'])) {
                $cert['participant_phone'] = ParticipantService::maskPhone($cert['participant_phone']);
            }
            // Mask internal revocation reason for staff
            if (!empty($cert['revocation_reason'])) {
                $cert['revocation_reason'] = 'Administrative invalidation';
            }
            // Hide raw token from staff
            unset($cert['verification_token']);
            return $cert;
        }

        // Viewer (10): De-identify
        $cert['recipient_name_snapshot'] = '[De-identified Attendee]';
        $cert['participant_name'] = '[De-identified Attendee]';
        $cert['participant_email'] = '***@***.***';
        $cert['participant_phone'] = '***-***-****';
        if (!empty($cert['revocation_reason'])) {
            $cert['revocation_reason'] = 'Administrative invalidation';
        }
        unset($cert['verification_token']);

        return $cert;
    }

    /**
     * Apply server-side role privacy masking on a list of certificate records.
     */
    public function maskCertificateList(array $certs, string $userRole): array
    {
        if (RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            return $certs;
        }

        return array_map(fn($c) => $this->maskCertificate($c, $userRole), $certs);
    }
}
