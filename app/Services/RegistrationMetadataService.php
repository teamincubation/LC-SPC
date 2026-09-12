<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use Throwable;

/**
 * Technical Registration Metadata Service
 * Collects client network and device metadata non-intrusively.
 * 
 * Compliance & Privacy Rules:
 * - Documents acquisition source: HTTP request headers, GeoIP/AS lookup (IP-API fallback)
 * - Gracefully handles offline or unavailable metadata without blocking registration
 * - Raw IP/User-Agent protected under RBAC; masked for exports
 * - NEVER collects clinical, psychological, health, or sensitive personal data.
 */
class RegistrationMetadataService
{
    /**
     * Parse client device, OS, and browser from User-Agent string.
     */
    public function parseUserAgent(?string $ua): array
    {
        if (empty($ua)) {
            return [
                'device_type'      => 'Unknown',
                'operating_system' => 'Unknown',
                'browser'          => 'Unknown',
            ];
        }

        // Device Type
        $deviceType = 'Desktop';
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $ua)) {
            $deviceType = 'Tablet';
        } elseif (preg_match('/(mobi|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop)/i', $ua)) {
            $deviceType = 'Mobile';
        }

        // Operating System
        $os = 'Unknown OS';
        if (preg_match('/windows nt 10/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/windows nt 6\.3/i', $ua)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/windows nt 6\.1/i', $ua)) {
            $os = 'Windows 7';
        } elseif (preg_match('/windows nt/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/mac os x/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        }

        // Browser
        $browser = 'Unknown Browser';
        if (preg_match('/edg/i', $ua)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/chrome|crios/i', $ua) && !preg_match('/opr|opera/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox|fxios/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome|crios/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/opera|opr/i', $ua)) {
            $browser = 'Opera';
        }

        return [
            'device_type'      => $deviceType,
            'operating_system' => $os,
            'browser'          => $browser,
        ];
    }

    /**
     * Resolve network ISP / AS Name gracefully without blocking.
     * Acquisition source: Local network resolution / IP-API JSON endpoint.
     */
    public function resolveNetworkDetails(string $ip): array
    {
        // Reserved/Private/Local IPs
        if (in_array($ip, ['127.0.0.1', '::1'], true) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return [
                'isp'     => 'Local/Internal Network',
                'as_name' => 'Loopback/Private AS',
                'country' => 'Localhost',
                'city'    => 'Localhost',
            ];
        }

        // Optional quick lookup with strict 800ms timeout
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 0.8,
                    'ignore_errors' => true,
                ],
            ]);

            $url = "http://ip-api.com/json/{$ip}?fields=status,country,city,isp,as";
            $response = @file_get_contents($url, false, $ctx);

            if ($response !== false) {
                $data = json_decode($response, true);
                if (is_array($data) && ($data['status'] ?? '') === 'success') {
                    return [
                        'isp'     => mb_substr((string) ($data['isp'] ?? 'Unknown ISP'), 0, 100),
                        'as_name' => mb_substr((string) ($data['as'] ?? 'Unknown AS'), 0, 100),
                        'country' => mb_substr((string) ($data['country'] ?? 'Unknown'), 0, 50),
                        'city'    => mb_substr((string) ($data['city'] ?? 'Unknown'), 0, 100),
                    ];
                }
            }
        } catch (Throwable) {
            // Gracefully ignore any network lookup failure
        }

        return [
            'isp'     => 'Not Available',
            'as_name' => 'Not Available',
            'country' => null,
            'city'    => null,
        ];
    }

    /**
     * Capture and record technical metadata for a registration.
     */
    public function recordMetadata(int $registrationId, Request $request): bool
    {
        $ip = $request->ip();
        $ua = $request->userAgent();

        $uaParsed = $this->parseUserAgent($ua);
        $network = $this->resolveNetworkDetails($ip);

        $sql = "INSERT INTO `registration_metadata` (
                    `registration_id`, `ip_address`, `device_type`, `operating_system`, 
                    `browser`, `isp`, `as_name`, `country`, `city`, `raw_user_agent`, `created_at`
                ) VALUES (
                    :reg_id, :ip, :device, :os, 
                    :browser, :isp, :as_name, :country, :city, :ua, :now
                )";

        try {
            Database::execute($sql, [
                ':reg_id'  => $registrationId,
                ':ip'      => mb_substr($ip, 0, 45),
                ':device'  => $uaParsed['device_type'],
                ':os'      => $uaParsed['operating_system'],
                ':browser' => $uaParsed['browser'],
                ':isp'     => $network['isp'],
                ':as_name' => $network['as_name'],
                ':country' => $network['country'],
                ':city'    => $network['city'],
                ':ua'      => mb_substr((string) $ua, 0, 255),
                ':now'     => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (Throwable) {
            // Gracefully ignore failure so registration is never aborted due to metadata insert
            return false;
        }
    }

    /**
     * Mask IP address for privacy in exports and unprivileged views.
     */
    public static function maskIp(?string $ip): string
    {
        if (empty($ip)) {
            return 'N/A';
        }

        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.***.***';
        }

        return substr($ip, 0, 4) . '::****';
    }
}
