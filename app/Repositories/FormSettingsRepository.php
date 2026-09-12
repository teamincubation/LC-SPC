<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Global Form Settings Repository
 */
class FormSettingsRepository
{
    /**
     * Get the single global form settings record.
     */
    public function getSettings(): array
    {
        $settings = Database::fetch("SELECT * FROM `form_settings` WHERE `id` = 1 LIMIT 1");
        if (!$settings) {
            return [
                'id'                           => 1,
                'mandatory_location_access'    => 0,
                'default_country_code'         => '+91',
                'default_country_iso'          => 'IN',
                'registration_success_message' => 'Thank you for registering! Your registration pass has been generated. Please present your pass QR at the event check-in.',
                'whatsapp_group_url'           => null,
                'whatsapp_auto_redirect'       => 0,
                'whatsapp_countdown_seconds'   => 5,
            ];
        }
        return $settings;
    }

    /**
     * Alias for getSettings().
     */
    public function get(): array
    {
        return $this->getSettings();
    }

    /**
     * Update global form settings.
     */
    public function updateSettings(array $data): bool
    {
        $existing = Database::fetch("SELECT `id` FROM `form_settings` WHERE `id` = 1 LIMIT 1");
        if (!$existing) {
            $sql = "INSERT INTO `form_settings` (
                        `id`, `mandatory_location_access`, `default_country_code`, `default_country_iso`,
                        `registration_success_message`, `whatsapp_group_url`, `whatsapp_auto_redirect`, `whatsapp_countdown_seconds`
                    ) VALUES (1, :loc, :cc, :iso, :msg, :wa, :redir, :cd)";
        } else {
            $sql = "UPDATE `form_settings` SET
                        `mandatory_location_access` = :loc,
                        `default_country_code` = :cc,
                        `default_country_iso` = :iso,
                        `registration_success_message` = :msg,
                        `whatsapp_group_url` = :wa,
                        `whatsapp_auto_redirect` = :redir,
                        `whatsapp_countdown_seconds` = :cd
                    WHERE `id` = 1";
        }

        return Database::execute($sql, [
            ':loc'   => (int) ($data['mandatory_location_access'] ?? 0),
            ':cc'    => trim((string) ($data['default_country_code'] ?? '+91')),
            ':iso'   => strtoupper(trim((string) ($data['default_country_iso'] ?? 'IN'))),
            ':msg'   => trim((string) ($data['registration_success_message'] ?? '')),
            ':wa'    => !empty($data['whatsapp_group_url']) ? trim((string) $data['whatsapp_group_url']) : null,
            ':redir' => (int) ($data['whatsapp_auto_redirect'] ?? 0),
            ':cd'    => max(1, (int) ($data['whatsapp_countdown_seconds'] ?? 5)),
        ]) >= 0;
    }
}
