<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Core\QrCode;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EventFormRepository;
use App\Repositories\EventRepository;
use App\Repositories\FormSettingsRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use App\Services\AuditService;
use App\Services\EventFormService;
use App\Services\ParticipantService;
use App\Services\RegistrationMetadataService;
use App\Services\RegistrationService;
use Throwable;

/**
 * Public Event Registration Controller (V2 Event-Centric Engine)
 * Handles 1:1 dedicated event form display, custom fields, duplicate phone prevention,
 * technical background metadata collection, pass generation, and optional WhatsApp group redirection.
 */
class PublicEventRegistrationController
{
    private EventFormRepository $formRepo;
    private EventRepository $eventRepo;
    private FormSettingsRepository $settingsRepo;
    private RegistrationRepository $regRepo;
    private ParticipantRepository $participantRepo;
    private RegistrationService $regService;
    private RegistrationMetadataService $metadataService;
    private EventFormService $formService;
    private AuditService $auditService;

    public function __construct(
        ?EventFormRepository $formRepo = null,
        ?EventRepository $eventRepo = null,
        ?FormSettingsRepository $settingsRepo = null,
        ?RegistrationRepository $regRepo = null,
        ?ParticipantRepository $participantRepo = null,
        ?RegistrationService $regService = null,
        ?RegistrationMetadataService $metadataService = null,
        ?EventFormService $formService = null,
        ?AuditService $auditService = null
    ) {
        $this->formRepo = $formRepo ?? new EventFormRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->settingsRepo = $settingsRepo ?? new FormSettingsRepository();
        $this->regRepo = $regRepo ?? new RegistrationRepository();
        $this->participantRepo = $participantRepo ?? new ParticipantRepository();
        $this->regService = $regService ?? new RegistrationService();
        $this->metadataService = $metadataService ?? new RegistrationMetadataService();
        $this->formService = $formService ?? new EventFormService();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Show dedicated public event registration form.
     * GET /register/{slug}
     */
    public function showForm(Request $request, array $vars): Response
    {
        $slug = trim((string) ($vars['slug'] ?? ''));
        $form = $this->formRepo->findBySlug($slug);

        if (!$form) {
            // Check if slug matches event slug
            $event = $this->eventRepo->findBySlug($slug);
            if ($event) {
                $form = $this->formRepo->findByEventId((int) $event['id']);
            }
        }

        if (!$form) {
            return Response::html(View::render('errors/404', [
                'title'   => 'Registration Form Not Found',
                'message' => 'The event registration form could not be found.',
            ]), 404);
        }

        $event = $this->eventRepo->findById((int) $form['event_id']);
        if (!$event || !empty($event['deleted_at'])) {
            return Response::html(View::render('errors/404', [
                'title'   => 'Event Not Found',
                'message' => 'The event associated with this form is no longer available.',
            ]), 404);
        }

        // Form fields and global settings
        $fields = $this->formRepo->getFields((int) $form['id']);
        $globalSettings = $this->settingsRepo->get();

        // Eligibility / Timing
        $now = time();
        $startTime = strtotime((string) $event['start_time']);
        $endTime = strtotime((string) $event['end_time']);
        $deadlineTime = !empty($event['registration_deadline']) ? strtotime((string) $event['registration_deadline']) : null;

        $isClosed = ($form['status'] === 'closed') || ($event['status'] === 'cancelled') || ($now > $endTime);
        $deadlinePassed = ($deadlineTime !== null && $now > $deadlineTime);

        $confirmedCount = $this->regRepo->countConfirmedByEvent((int) $event['id']);
        $capacity = (int) $event['capacity'];
        $isFull = ($capacity > 0 && $confirmedCount >= $capacity);

        return Response::html(View::render('public/events/register_v2', [
            'title'          => $form['form_title'],
            'form'           => $form,
            'event'          => $event,
            'fields'         => $fields,
            'globalSettings' => $globalSettings,
            'isClosed'       => $isClosed,
            'deadlinePassed' => $deadlinePassed,
            'isFull'         => $isFull,
            'confirmedCount' => $confirmedCount,
            'errors'         => Session::getFlash('errors', []),
            'old'            => Session::getFlash('old', []),
        ], 'layouts/public'));
    }

    /**
     * Handle registration form submission.
     * POST /register/{slug}
     */
    public function submitForm(Request $request, array $vars): Response
    {
        $slug = trim((string) ($vars['slug'] ?? ''));
        $form = $this->formRepo->findBySlug($slug);

        if (!$form) {
            $event = $this->eventRepo->findBySlug($slug);
            if ($event) {
                $form = $this->formRepo->findByEventId((int) $event['id']);
            }
        }

        if (!$form || $form['status'] === 'closed') {
            Session::flash('error', 'Registration for this event is closed.');
            return Response::redirect(url("/register/{$slug}"));
        }

        $eventId = (int) $form['event_id'];
        $event = $this->eventRepo->findById($eventId);
        if (!$event || !empty($event['deleted_at']) || $event['status'] === 'cancelled') {
            Session::flash('error', 'This event is unavailable.');
            return Response::redirect(url("/register/{$slug}"));
        }

        // Honeypot check
        if (!empty($request->post('website'))) {
            // Silently redirect bot
            return Response::redirect(url("/register/{$slug}"));
        }

        $fields = $this->formRepo->getFields((int) $form['id']);
        $globalSettings = $this->settingsRepo->get();

        $errors = [];

        // 1. Mandatory Locked Field: Full Name
        $fullName = trim((string) $request->post('full_name', ''));
        if (empty($fullName)) {
            $errors['full_name'] = 'Full legal name is required.';
        } elseif (mb_strlen($fullName) < 2) {
            $errors['full_name'] = 'Full name must be at least 2 characters.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors['full_name'] = 'Full name may not exceed 100 characters.';
        }

        // 2. Mandatory Locked Field: WhatsApp / Mobile Number (+91 default)
        $countryCode = trim((string) $request->post('country_code', $globalSettings['default_country_code'] ?? '+91'));
        $rawPhone = trim((string) $request->post('phone', ''));
        $cleanDigits = preg_replace('/[^\d]/', '', $rawPhone);

        if (empty($cleanDigits) || strlen($cleanDigits) < 7 || strlen($cleanDigits) > 15) {
            $errors['phone'] = 'Please enter a valid WhatsApp / Mobile number.';
        }

        $phoneNormalized = $countryCode . $cleanDigits;

        // 3. Duplicate Phone Check Per-Event
        if (empty($errors['phone'])) {
            $existingReg = $this->regRepo->findByEventAndPhone($eventId, $phoneNormalized);
            if ($existingReg && $existingReg['status'] !== 'cancelled') {
                $maskedPass = RegistrationService::maskCode((string) $existingReg['registration_code']);
                $errors['phone'] = "This mobile number is already registered for this event (Pass Code: {$maskedPass}). Duplicate enrollments are prevented.";
            }
        }

        // Optional Place
        $place = trim((string) $request->post('place', ''));

        // 4. Custom fields validation & collection
        $customData = [];
        foreach ($fields as $f) {
            $key = $f['field_key'];
            if (in_array($key, ['full_name', 'phone', 'place'], true)) {
                continue;
            }

            $val = $request->post($key);
            if ($f['is_required'] && (is_null($val) || trim((string) $val) === '')) {
                $errors[$key] = "{$f['field_label']} is required.";
            } else {
                $customData[$key] = $val;
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            return Response::redirect(url("/register/{$slug}"));
        }

        // 5. Atomic Participant + Registration Creation
        try {
            $registration = Database::transaction(function () use (
                $eventId,
                $form,
                $event,
                $fullName,
                $phoneNormalized,
                $countryCode,
                $cleanDigits,
                $place,
                $customData,
                $request
            ) {
                // Find or create participant
                $participant = $this->participantRepo->findByPhone($phoneNormalized);
                if (!$participant) {
                    // Try by clean digits
                    $stmt = Database::getConnection()->prepare("SELECT * FROM `participants` WHERE `phone` LIKE :d LIMIT 1");
                    $stmt->execute([':d' => "%{$cleanDigits}"]);
                    $participant = $stmt->fetch() ?: null;
                }

                if ($participant) {
                    $participantId = (int) $participant['id'];
                    $this->participantRepo->update($participantId, [
                        'full_name'  => $fullName,
                        'phone'      => $phoneNormalized,
                        'place'      => $place ?: ($participant['place'] ?? null),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $participantId = $this->participantRepo->create([
                        'full_name'  => $fullName,
                        'phone'      => $phoneNormalized,
                        'email'      => $cleanDigits . '@participant.lc',
                        'place'      => $place ?: null,
                        'status'     => 'active',
                    ]);
                }

                // Generate Pass Code
                $passCode = $this->regService->generateUniqueRegistrationCode();

                // Determine lifecycle status based on capacity & approval
                $confirmedCount = $this->regRepo->countConfirmedByEvent($eventId);
                $capacity = (int) $event['capacity'];
                $requiresApproval = (int) ($event['requires_approval'] ?? 0);

                if ($requiresApproval === 1) {
                    $status = 'pending';
                } elseif ($capacity > 0 && $confirmedCount >= $capacity) {
                    $status = 'waitlisted';
                } else {
                    $status = 'confirmed';
                }

                // Insert event registration
                $regId = $this->regRepo->create([
                    'registration_code' => $passCode,
                    'event_id'          => $eventId,
                    'participant_id'    => $participantId,
                    'form_id'           => (int) $form['id'],
                    'custom_data'       => $customData,
                    'phone_normalized'  => $phoneNormalized,
                    'country_code'      => $countryCode,
                    'photo_path'        => null,
                    'status'            => $status,
                    'attendance_status' => 'unmarked',
                ]);

                // Technical non-intrusive metadata
                $this->metadataService->recordMetadata($regId, $request);

                // Audit log
                $this->auditService->log(
                    'registration.create',
                    'event_registration',
                    $regId,
                    [
                        'event_id'          => $eventId,
                        'event_title'       => $event['title'],
                        'participant_id'    => $participantId,
                        'participant_name'  => $fullName,
                        'phone_normalized'  => $phoneNormalized,
                        'registration_code' => RegistrationService::maskCode($passCode),
                        'status'            => $status,
                    ]
                );

                return $this->regRepo->findById($regId);
            });

            // Redirect to pass confirmation
            return Response::redirect(url("/register/pass/{$registration['registration_code']}"));

        } catch (Throwable $e) {
            Session::flash('error', 'Registration could not be completed: ' . $e->getMessage());
            Session::flash('old', $request->all());
            return Response::redirect(url("/register/{$slug}"));
        }
    }

    /**
     * Show registration pass and optional WhatsApp group auto-redirect.
     * GET /register/pass/{code}
     */
    public function showPass(Request $request, array $vars): Response
    {
        $code = trim((string) ($vars['code'] ?? ''));
        $reg = $this->regRepo->findByCode($code);

        if (!$reg) {
            return Response::html(View::render('errors/404', [
                'title'   => 'Pass Not Found',
                'message' => 'The requested registration pass could not be located.',
            ]), 404);
        }

        $event = $this->eventRepo->findById((int) $reg['event_id']);
        $participant = $this->participantRepo->findById((int) $reg['participant_id']);
        $form = !empty($reg['form_id']) ? $this->formRepo->findById((int) $reg['form_id']) : null;
        $globalSettings = $this->settingsRepo->get();

        // Pass QR points to verification
        $passUrl = url("/register/pass/{$reg['registration_code']}");
        $qrSvg = QrCode::svg($passUrl, 260, 2, '#000000', '#ffffff');

        // Resolve WhatsApp redirect configuration
        $whatsappUrl = !empty($form['whatsapp_group_url']) ? $form['whatsapp_group_url'] : ($globalSettings['whatsapp_group_url'] ?? null);
        $whatsappAuto = !empty($form['whatsapp_auto_redirect']) ? (bool) $form['whatsapp_auto_redirect'] : (!empty($globalSettings['whatsapp_auto_redirect']));
        $whatsappCountdown = !empty($form['whatsapp_countdown_seconds']) ? (int) $form['whatsapp_countdown_seconds'] : (int) ($globalSettings['whatsapp_countdown_seconds'] ?? 5);

        return Response::html(View::render('public/events/pass_v2', [
            'title'             => 'Registration Pass — ' . ($event['title'] ?? 'Event'),
            'registration'      => $reg,
            'event'             => $event,
            'participant'       => $participant,
            'qrSvg'             => $qrSvg,
            'passUrl'           => $passUrl,
            'whatsappUrl'       => $whatsappUrl,
            'whatsappAuto'      => $whatsappAuto,
            'whatsappCountdown' => $whatsappCountdown,
        ], 'layouts/public'));
    }

    /**
     * Route handler alias for GET /register/{slug}
     */
    public function show(Request $request, array $vars): Response
    {
        return $this->showForm($request, $vars);
    }

    /**
     * Route handler alias for POST /register/{slug}
     */
    public function submit(Request $request, array $vars): Response
    {
        return $this->submitForm($request, $vars);
    }

    /**
     * Route handler alias for GET /register/pass/{code}
     */
    public function pass(Request $request, array $vars): Response
    {
        return $this->showPass($request, $vars);
    }
}
