<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;

/**
 * Audit Logging Service
 * Handles append-only security and operational audit trail with recursive metadata sanitization.
 */
class AuditService
{
    private AuditLogRepository $auditRepo;
    private ?UserRepository $userRepo;

    public function __construct(?AuditLogRepository $auditRepo = null, ?UserRepository $userRepo = null)
    {
        $this->auditRepo = $auditRepo ?? new AuditLogRepository();
        $this->userRepo = $userRepo;
    }

    /**
     * Record an audit event.
     *
     * @param string $action Dot-notated action slug (e.g. 'auth.login', 'user.create')
     * @param string $entityType Target model type ('user', 'campaign', 'event', etc.)
     * @param int|null $entityId Primary key of target entity
     * @param array|null $metadata Context data (sanitized automatically)
     * @param int|null $actorId User ID executing action (auto-resolved from session if null)
     * @param string $actorType 'admin', 'system', or 'anonymous'
     * @param string|null $ip Client IP (auto-resolved from Request if null)
     * @param string|null $userAgent Client User Agent (auto-resolved if null)
     * @return int Inserted audit log ID
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $metadata = null,
        ?int $actorId = null,
        string $actorType = 'admin',
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        // Resolve actor from session if not explicitly provided
        if ($actorId === null && Session::isStarted() && Session::has('_auth_user_id')) {
            $actorId = (int) Session::get('_auth_user_id');
            $actorType = 'admin';
        }

        // Auto-resolve IP and User Agent if not passed
        if ($ip === null || $userAgent === null) {
            $request = Request::capture();
            $ip = $ip ?? $request->ip();
            $userAgent = $userAgent ?? $request->userAgent();
        }

        // Sanitize and enhance metadata payload
        $cleanedMetadata = $this->sanitizeMetadata($metadata ?? []);

        // Graceful deletion preservation: retain actor details in metadata snapshot
        if ($actorId !== null) {
            if (Session::isStarted() && Session::has('_auth_user_email')) {
                $cleanedMetadata['_actor_snapshot'] = [
                    'id'    => $actorId,
                    'email' => Session::get('_auth_user_email'),
                    'name'  => Session::get('_auth_user_name'),
                    'role'  => Session::get('_auth_user_role'),
                ];
            } elseif ($this->userRepo !== null) {
                $user = $this->userRepo->findByIdIncludingDeleted($actorId);
                if ($user) {
                    $cleanedMetadata['_actor_snapshot'] = [
                        'id'    => $user['id'],
                        'email' => $user['email'],
                        'name'  => $user['name'],
                        'role'  => $user['role'],
                    ];
                }
            }
        }

        return $this->auditRepo->create([
            'actor_id'    => $actorId,
            'actor_type'  => $actorType,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
            'metadata'    => !empty($cleanedMetadata) ? $cleanedMetadata : null,
        ]);
    }

    /**
     * Recursively scrub sensitive credentials, passwords, hashes, tokens, and cookies from metadata.
     */
    public function sanitizeMetadata(array $data): array
    {
        $sensitivePatterns = [
            'password',
            'password_hash',
            'password_confirmation',
            'pass',
            'hash',
            'token',
            '_csrf_token',
            'csrf_token',
            'secret',
            'cookie',
            'session',
            'credit_card',
            'cvv',
        ];

        $cleaned = [];
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $isSensitive = false;

            foreach ($sensitivePatterns as $pattern) {
                if (str_contains($normalizedKey, $pattern)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $cleaned[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $cleaned[$key] = $this->sanitizeMetadata($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
