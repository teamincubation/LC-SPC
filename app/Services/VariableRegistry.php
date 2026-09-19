<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\ValidationException;

/**
 * Centralized Variable Registry for Certificate Platform V3
 * Manages variable definitions, data types, examples, and mandatory requirements.
 */
class VariableRegistry
{
    /**
     * Complete list of registered variables.
     */
    public static function getAll(): array
    {
        return [
            'name' => [
                'key'         => 'name',
                'placeholder' => '{{name}}',
                'label'       => 'Recipient Full Name',
                'type'        => 'string',
                'required'    => true,
                'description' => 'Full legal name of the certificate recipient',
                'example'     => 'John Mathew',
            ],
            'phone' => [
                'key'         => 'phone',
                'placeholder' => '{{phone}}',
                'label'       => 'Recipient Mobile / WhatsApp',
                'type'        => 'phone',
                'required'    => true,
                'description' => 'Mobile number used for recipient identification & verification',
                'example'     => '+919876543210',
            ],
            'certificate_number' => [
                'key'         => 'certificate_number',
                'placeholder' => '{{certificate_number}}',
                'label'       => 'Certificate ID',
                'type'        => 'string',
                'required'    => true,
                'description' => 'Non-sequential, unique public certificate identifier',
                'example'     => 'LC26-X7Q9-M4KP',
            ],
            'email' => [
                'key'         => 'email',
                'placeholder' => '{{email}}',
                'label'       => 'Email Address',
                'type'        => 'email',
                'required'    => false,
                'description' => 'Recipient email address for records or delivery',
                'example'     => 'john@example.com',
            ],
            'date' => [
                'key'         => 'date',
                'placeholder' => '{{date}}',
                'label'       => 'Issue / Event Date',
                'type'        => 'date',
                'required'    => false,
                'description' => 'Formatted date displayed on the certificate',
                'example'     => '15 September 2026',
            ],
            'event_title' => [
                'key'         => 'event_title',
                'placeholder' => '{{event_title}}',
                'label'       => 'Event / Program Title',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Name of the workshop, program, or conference',
                'example'     => 'World Suicide Prevention Day Workshop',
            ],
            'event_start_date' => [
                'key'         => 'event_start_date',
                'placeholder' => '{{event_start_date}}',
                'label'       => 'Event Start Date',
                'type'        => 'date',
                'required'    => false,
                'description' => 'Starting date of multi-day programs',
                'example'     => '10 September 2026',
            ],
            'event_end_date' => [
                'key'         => 'event_end_date',
                'placeholder' => '{{event_end_date}}',
                'label'       => 'Event End Date',
                'type'        => 'date',
                'required'    => false,
                'description' => 'Conclusion date of multi-day programs',
                'example'     => '12 September 2026',
            ],
            'event_type' => [
                'key'         => 'event_type',
                'placeholder' => '{{event_type}}',
                'label'       => 'Event / Session Type',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Category e.g. Workshop, Seminar, Training, Conference',
                'example'     => 'Workshop',
            ],
            'place' => [
                'key'         => 'place',
                'placeholder' => '{{place}}',
                'label'       => 'Place / Location',
                'type'        => 'string',
                'required'    => false,
                'description' => 'City or venue where the program was held',
                'example'     => 'Kozhikode',
            ],
            'certificate_type' => [
                'key'         => 'certificate_type',
                'placeholder' => '{{certificate_type}}',
                'label'       => 'Certificate Type',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Participation, Merit, Completion, Volunteer, Speaker',
                'example'     => 'Certificate of Participation',
            ],
            'organization' => [
                'key'         => 'organization',
                'placeholder' => '{{organization}}',
                'label'       => 'Organization / Institution',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Issuing or partner institution name',
                'example'     => 'Listening Community SPC',
            ],
            'designation' => [
                'key'         => 'designation',
                'placeholder' => '{{designation}}',
                'label'       => 'Recipient Designation / Role',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Role e.g. Student, Volunteer, Psychologist, Facilitator',
                'example'     => 'Volunteer',
            ],
            'course_name' => [
                'key'         => 'course_name',
                'placeholder' => '{{course_name}}',
                'label'       => 'Course Name',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Name of certified training course',
                'example'     => 'Peer Support & Crisis Intervention',
            ],
            'workshop_name' => [
                'key'         => 'workshop_name',
                'placeholder' => '{{workshop_name}}',
                'label'       => 'Workshop Name',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Name of specific workshop module',
                'example'     => 'First Response Listening Skills',
            ],
            'duration' => [
                'key'         => 'duration',
                'placeholder' => '{{duration}}',
                'label'       => 'Duration',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Program length e.g. 10 Hours, 3 Days, 2 Weeks',
                'example'     => '16 Hours (2 Days)',
            ],
            'issued_by' => [
                'key'         => 'issued_by',
                'placeholder' => '{{issued_by}}',
                'label'       => 'Issued By Authority',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Name or office issuing the credential',
                'example'     => 'Academic Council, LC-SPC',
            ],
            'verification_url' => [
                'key'         => 'verification_url',
                'placeholder' => '{{verification_url}}',
                'label'       => 'Public Verification URL',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Canonical public verification URL for the certificate',
                'example'     => 'https://teami.in/LC/certificates/verify/DEMO_TEMPLATE_VERIFICATION_TOKEN',
            ],
        ];
    }

    /**
     * Get list of system-wide mandatory variables for CSV data.
     * Every template data row MUST have 'name' and 'phone'.
     */
    public static function getMandatoryDataKeys(): array
    {
        return ['name', 'phone'];
    }

    /**
     * Validate that a template configuration contains the mandatory variables:
     * {{name}} and {{phone}} must be present.
     *
     * @throws ValidationException
     */
    public static function validateTemplateRequirements(array $variables): void
    {
        $keys = array_map(function ($item) {
            if (is_array($item) && isset($item['key'])) {
                return trim($item['key']);
            }
            if (is_string($item)) {
                return trim(str_replace(['{{', '}}'], '', $item));
            }
            return '';
        }, $variables);

        $missing = [];
        if (!in_array('name', $keys, true)) {
            $missing[] = '{{name}} (Recipient Full Name)';
        }
        if (!in_array('phone', $keys, true)) {
            $missing[] = '{{phone}} (Recipient Phone / WhatsApp)';
        }

        if (!empty($missing)) {
            throw new ValidationException(
                'Template validation failed: Every certificate template MUST include mandatory variables: ' . implode(', ', $missing) . '.',
                ['required_variables' => 'Missing mandatory variables: ' . implode(', ', $missing)]
            );
        }
    }

    /**
     * Extract all {{variable}} placeholders from a text template or layout config.
     */
    public static function extractPlaceholders(string $text): array
    {
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Replace placeholders with recipient data snapshot.
     */
    public static function replacePlaceholders(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', function ($matches) use ($data) {
            $key = $matches[1];
            return isset($data[$key]) ? (string) $data[$key] : '';
        }, $template);
    }

    /**
     * Safe dummy sample data for live designer preview.
     */
    public static function getPreviewDummyData(): array
    {
        return [
            'name'               => 'John Mathew',
            'phone'              => '+919876543210',
            'email'              => 'john@example.com',
            'certificate_number' => 'LC26-X7Q9-M4KP',
            'date'               => '15 September 2026',
            'event_title'        => 'World Suicide Prevention Day',
            'event_start_date'   => '15 September 2026',
            'event_end_date'     => '15 September 2026',
            'event_type'         => 'Workshop',
            'place'              => 'Kozhikode',
            'certificate_type'   => 'Certificate of Participation',
            'organization'       => 'Listening Community SPC',
            'designation'        => 'Participant',
            'course_name'        => 'Peer Support & Crisis Intervention',
            'workshop_name'      => 'First Response Listening Skills',
            'duration'           => '8 Hours',
            'issued_by'          => 'Program Director, LC-SPC',
            'verification_token' => 'DEMO_TEMPLATE_VERIFICATION_TOKEN',
            'verification_url'   => 'https://teami.in/LC/certificates/verify/DEMO_TEMPLATE_VERIFICATION_TOKEN',
        ];
    }
}
