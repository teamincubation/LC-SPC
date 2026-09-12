<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\ValidationException;
use App\Core\QrCode;
use App\Repositories\EventFormRepository;
use App\Repositories\EventRepository;

/**
 * Event Registration Form Domain Service
 * Manages 1-to-1 event form lifecycles, collision-safe unique URLs, locked fields, and QR distribution.
 */
class EventFormService
{
    private EventFormRepository $formRepo;
    private EventRepository $eventRepo;

    public function __construct(
        ?EventFormRepository $formRepo = null,
        ?EventRepository $eventRepo = null
    ) {
        $this->formRepo = $formRepo ?? new EventFormRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
    }

    /**
     * Generate a collision-safe unique URL slug for an event registration form.
     */
    public function generateUniqueSlug(string $title, ?int $excludeFormId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        if (empty($base)) {
            $base = 'event-' . bin2hex(random_bytes(3));
        }

        $slug = $base;
        $counter = 2;

        while ($this->formRepo->slugExists($slug, $excludeFormId)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the authoritative public registration URL for an event form.
     */
    public function getPublicRegistrationUrl(string $slug): string
    {
        return url("/register/{$slug}");
    }

    /**
     * Generate an inline standalone vector SVG QR code pointing exactly to the public registration URL.
     */
    public function getPublicRegistrationQrSvg(string $slug, int $size = 280): string
    {
        $targetUrl = $this->getPublicRegistrationUrl($slug);
        return QrCode::svg($targetUrl, $size, 2, '#000000', '#ffffff');
    }

    /**
     * Get the authoritative public check-in URL for an event.
     */
    public function getPublicCheckInUrl(string $slug): string
    {
        return url("/check-in/{$slug}");
    }

    /**
     * Generate an inline standalone vector SVG QR code pointing exactly to the public check-in URL.
     */
    public function getPublicCheckInQrSvg(string $slug, int $size = 280): string
    {
        $targetUrl = $this->getPublicCheckInUrl($slug);
        return QrCode::svg($targetUrl, $size, 2, '#000000', '#ffffff');
    }

    /**
     * Atomically provision an Event Registration Form for an event.
     * Enforces: ONE Event = ONE Registration Form.
     */
    public function createFormForEvent(int $eventId, string $title, ?string $customSlug = null): int
    {
        $existing = $this->formRepo->findByEventId($eventId);
        if ($existing) {
            return (int) $existing['id'];
        }

        $slug = !empty($customSlug) 
            ? $this->generateUniqueSlug($customSlug)
            : $this->generateUniqueSlug($title);

        $formId = $this->formRepo->create([
            'event_id'   => $eventId,
            'form_title' => $title,
            'slug'       => $slug,
            'status'     => 'published',
        ]);

        // Provision locked global mandatory fields
        $this->provisionDefaultFields($formId);

        return $formId;
    }

    /**
     * Update form settings with strict slug lock enforcement once registrations exist.
     */
    public function updateForm(int $formId, array $data): bool
    {
        $form = $this->formRepo->findById($formId);
        if (!$form) {
            throw new ValidationException(['form' => 'Event registration form not found.']);
        }

        // Slug modification verification
        if (isset($data['slug'])) {
            $newSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $data['slug']), '-'));
            if ($newSlug !== $form['slug']) {
                $regCount = $this->formRepo->countRegistrations($formId);
                if ($regCount > 0) {
                    throw new ValidationException([
                        'slug' => 'Registration form URL cannot be modified because registrations have already been received for this event.'
                    ]);
                }

                if ($this->formRepo->slugExists($newSlug, $formId)) {
                    throw new ValidationException(['slug' => "The custom URL slug [{$newSlug}] is already in use by another event."]);
                }

                $data['slug'] = $newSlug;
            }
        }

        return $this->formRepo->update($formId, $data);
    }

    /**
     * Add a custom field to a form.
     */
    public function addCustomField(int $formId, array $data): int
    {
        $label = trim((string) ($data['field_label'] ?? ''));
        if (empty($label)) {
            throw new ValidationException(['field_label' => 'Field label is required.']);
        }

        $key = trim((string) ($data['field_key'] ?? ''));
        if (empty($key)) {
            $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
        } else {
            $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $key));
        }

        // Prevent collisions with reserved core keys
        if (in_array($key, ['full_name', 'phone'], true)) {
            $key .= '_' . bin2hex(random_bytes(2));
        }

        $validTypes = ['text', 'long_text', 'number', 'email', 'dropdown', 'radio', 'checkbox', 'date'];
        $type = (string) ($data['field_type'] ?? 'text');
        if (!in_array($type, $validTypes, true)) {
            $type = 'text';
        }

        return $this->formRepo->addField($formId, [
            'field_key'     => $key,
            'field_label'   => $label,
            'field_type'    => $type,
            'is_required'   => (int) ($data['is_required'] ?? 0),
            'is_locked'     => 0,
            'sort_order'    => (int) ($data['sort_order'] ?? 10),
            'options_json'  => $data['options_json'] ?? null,
            'placeholder'   => $data['placeholder'] ?? null,
            'help_text'     => $data['help_text'] ?? null,
        ]);
    }

    /**
     * Delete custom field with locked safeguard.
     */
    public function deleteCustomField(int $fieldId): bool
    {
        $field = $this->formRepo->findFieldById($fieldId);
        if (!$field) {
            throw new ValidationException(['field' => 'Field not found.']);
        }

        if ($field['is_locked']) {
            throw new ValidationException(['field' => 'Cannot delete global mandatory fields (Full Name and WhatsApp Number are locked permanently).']);
        }

        return $this->formRepo->deleteField($fieldId);
    }

    /**
     * Provision locked standard default fields.
     */
    private function provisionDefaultFields(int $formId): void
    {
        $fields = [
            [
                'field_key'   => 'full_name',
                'field_label' => 'Full Name',
                'field_type'  => 'text',
                'is_required' => 1,
                'is_locked'   => 1,
                'sort_order'  => 1,
                'placeholder' => 'Enter your full legal name',
            ],
            [
                'field_key'   => 'phone',
                'field_label' => 'WhatsApp / Mobile Number',
                'field_type'  => 'text',
                'is_required' => 1,
                'is_locked'   => 1,
                'sort_order'  => 2,
                'placeholder' => 'e.g. 9876543210',
            ],
            [
                'field_key'   => 'place',
                'field_label' => 'Place',
                'field_type'  => 'text',
                'is_required' => 0,
                'is_locked'   => 0,
                'sort_order'  => 3,
                'placeholder' => 'City or town of residence',
            ],
        ];

        foreach ($fields as $f) {
            $this->formRepo->addField($formId, $f);
        }
    }

    /**
     * Retrieve the dedicated registration form for an event.
     */
    public function getFormByEventId(int $eventId): ?array
    {
        return $this->formRepo->findByEventId($eventId);
    }
}
