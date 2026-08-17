<?php

namespace App\Domain\ClientRecords\Services;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientFormRequest;
use App\Domain\ClientRecords\Models\ClientFormRequestLink;
use App\Domain\ClientRecords\Models\ClientFormSubmission;
use App\Domain\ClientRecords\Models\ClientFormTemplate;
use App\Domain\ClientRecords\Models\ClientFormTemplateVersion;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientFormService
{
    public const FIELD_TYPES = ['text', 'number', 'date', 'yes_no', 'multiple_choice', 'signature'];

    /** @param list<array<string,mixed>> $fields @param list<int> $serviceIds */
    public function publish(ClientFormTemplate $template, string $title, ?string $introduction, array $fields, array $serviceIds, ?int $staffProfileId = null): ClientFormTemplateVersion
    {
        $fields = $this->validatedFields($fields);

        return DB::transaction(function () use ($template, $title, $introduction, $fields, $serviceIds, $staffProfileId): ClientFormTemplateVersion {
            $template = ClientFormTemplate::query()->where('business_id', $template->business_id)->lockForUpdate()->findOrFail($template->id);
            $validServiceIds = Service::query()->where('business_id', $template->business_id)->whereKey($serviceIds)->pluck('id')->all();
            if (count(array_unique($serviceIds)) !== count($validServiceIds)) {
                throw ValidationException::withMessages(['services' => 'Every associated service must belong to this business.']);
            }
            $next = $template->current_version + 1;
            $version = ClientFormTemplateVersion::query()->create([
                'business_id' => $template->business_id,
                'client_form_template_id' => $template->id,
                'created_by_staff_profile_id' => $staffProfileId,
                'version' => $next,
                'title' => trim($title),
                'introduction' => $introduction,
                'fields' => $fields,
                'published_at' => now(),
            ]);
            $template->forceFill(['current_version' => $next, 'status' => 'published'])->save();
            $template->services()->syncWithPivotValues($validServiceIds, ['business_id' => $template->business_id]);

            return $version;
        }, 3);
    }

    public function request(Client $client, ClientFormTemplate $template, ?Appointment $appointment = null): ClientFormRequest
    {
        abort_unless($client->business_id === $template->business_id && (! $appointment || $appointment->business_id === $client->business_id), 404);
        $template = ClientFormTemplate::query()->where('business_id', $client->business_id)->findOrFail($template->id);
        $version = ClientFormTemplateVersion::query()->where('business_id', $client->business_id)
            ->where('client_form_template_id', $template->id)->where('version', $template->current_version)->firstOrFail();

        return ClientFormRequest::query()->create([
            'business_id' => $client->business_id,
            'client_id' => $client->id,
            'appointment_id' => $appointment?->id,
            'client_form_template_version_id' => $version->id,
            'status' => 'requested',
            'requested_at' => now(),
            'due_at' => $appointment?->starts_at_utc,
        ]);
    }

    public function requestForAppointmentReference(Client $client, ClientFormTemplate $template, ?string $appointmentPublicId): ClientFormRequest
    {
        $appointment = $appointmentPublicId
            ? Appointment::query()->where('business_id', $client->business_id)->where('client_id', $client->id)->where('public_id', $appointmentPublicId)->firstOrFail()
            : null;

        return $this->request($client, $template, $appointment);
    }

    /** @return array{token:string,expires_at:string} */
    public function issueSecureLink(ClientFormRequest $request, int $ttlMinutes = 10080): array
    {
        ClientFormRequestLink::query()->where('business_id', $request->business_id)->where('client_form_request_id', $request->id)
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        $token = bin2hex(random_bytes(32));
        $expires = CarbonImmutable::now()->addMinutes($ttlMinutes);
        ClientFormRequestLink::query()->create([
            'business_id' => $request->business_id,
            'client_form_request_id' => $request->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expires,
        ]);

        return ['token' => $token, 'expires_at' => $expires->toIso8601String()];
    }

    public function resolveSecureLink(string $token): ClientFormRequestLink
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            abort(404);
        }
        $link = ClientFormRequestLink::query()->with(['request.version.template', 'request.appointment', 'request.submission'])
            ->where('token_hash', hash('sha256', $token))->first();
        abort_unless($link, 404);
        abort_if($link->revoked_at || $link->used_at || $link->expires_at->isPast() || $link->request->status !== 'requested', 410, 'This form link has expired or has already been completed.');

        return $link;
    }

    /** @param array<string,mixed> $answers */
    public function submit(ClientFormRequest $request, array $answers, ?string $signature, array $identity): ClientFormSubmission
    {
        return DB::transaction(function () use ($request, $answers, $signature, $identity): ClientFormSubmission {
            $request = ClientFormRequest::query()->with(['version.template'])->where('business_id', $request->business_id)->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'requested') {
                throw ValidationException::withMessages(['form' => 'This form request has already been completed or closed.']);
            }
            $this->validateAnswers($request->version->fields, $answers, $signature);
            $client = Client::query()->where('business_id', $request->business_id)->findOrFail($request->client_id);
            $submittedAt = now();
            $submission = ClientFormSubmission::query()->create([
                'business_id' => $request->business_id,
                'client_id' => $request->client_id,
                'appointment_id' => $request->appointment_id,
                'client_form_request_id' => $request->id,
                'client_form_template_version_id' => $request->client_form_template_version_id,
                'wording_snapshot' => [
                    'template_public_id' => $request->version->template->public_id,
                    'version' => $request->version->version,
                    'title' => $request->version->title,
                    'introduction' => $request->version->introduction,
                    'fields' => $request->version->fields,
                ],
                'answers' => $answers,
                'signature' => $signature,
                'signature_hash' => $signature ? hash('sha256', $signature) : null,
                'submitted_identity_snapshot' => [
                    'client_public_id' => $client->public_id,
                    'client_name' => $client->name,
                    'email' => $identity['email'] ?? $client->email,
                    'mobile' => $identity['mobile'] ?? $client->mobile,
                    'identity_method' => $identity['method'] ?? 'staff_assisted',
                    'appointment_public_id' => $request->appointment?->public_id,
                ],
                'submitted_at' => $submittedAt,
            ]);
            $request->forceFill(['status' => 'completed', 'completed_at' => $submittedAt])->save();

            return $submission;
        }, 3);
    }

    /** @param array<string,mixed> $answers */
    public function submitSecure(ClientFormRequestLink $link, array $answers, ?string $signature): ClientFormSubmission
    {
        $submission = $this->submit($link->request, $answers, $signature, ['method' => 'secure_form_link']);
        $link->forceFill(['used_at' => now()])->save();

        return $submission;
    }

    public function seedStarterTemplates(int $businessId): int
    {
        $templates = [
            'Consultation' => ['consultation', [['label' => 'What would you like us to know?', 'type' => 'text', 'required' => false]]],
            'Allergy declaration' => ['allergy', [['label' => 'Do you have any known allergies?', 'type' => 'yes_no', 'required' => true], ['label' => 'Please describe them', 'type' => 'text', 'required' => false]]],
            'Patch test' => ['patch_test', [['label' => 'Patch test completed?', 'type' => 'yes_no', 'required' => true], ['label' => 'Patch test date', 'type' => 'date', 'required' => true], ['label' => 'Signature', 'type' => 'signature', 'required' => true]]],
            'Treatment consent' => ['treatment_consent', [['label' => 'I consent to this treatment', 'type' => 'yes_no', 'required' => true], ['label' => 'Signature', 'type' => 'signature', 'required' => true]]],
            'Hair-colour history' => ['hair_colour', [['label' => 'Describe treatments from the last 12 months', 'type' => 'text', 'required' => true]]],
            'Photography consent' => ['photography', [['label' => 'May we take before and after photos?', 'type' => 'yes_no', 'required' => true], ['label' => 'Signature', 'type' => 'signature', 'required' => true]]],
        ];
        $created = 0;
        foreach ($templates as $name => [$purpose, $fields]) {
            $template = ClientFormTemplate::query()->firstOrCreate(
                ['business_id' => $businessId, 'name' => $name],
                ['purpose' => $purpose, 'status' => 'draft', 'request_before_appointment' => true]
            );
            if ((int) $template->current_version === 0) {
                $this->publish($template, $name, null, $fields, []);
                $created++;
            }
        }

        return $created;
    }

    /** @param list<array<string,mixed>> $fields @return list<array<string,mixed>> */
    private function validatedFields(array $fields): array
    {
        if ($fields === [] || count($fields) > 100) {
            throw ValidationException::withMessages(['fields' => 'A form needs between 1 and 100 fields.']);
        }

        $normalized = collect($fields)->map(function ($field, $index): array {
            $label = trim((string) ($field['label'] ?? ''));
            $type = (string) ($field['type'] ?? '');
            if ($label === '' || mb_strlen($label) > 500 || ! in_array($type, self::FIELD_TYPES, true)) {
                throw ValidationException::withMessages(["fields.{$index}" => 'Each field needs valid wording and a supported type.']);
            }
            $options = array_values(array_filter(array_map('strval', $field['options'] ?? []), fn ($value) => trim($value) !== ''));
            if ($type === 'multiple_choice' && $options === []) {
                throw ValidationException::withMessages(["fields.{$index}.options" => 'Multiple-choice fields need options.']);
            }

            $id = (string) ($field['id'] ?? Str::slug($label).'-'.($index + 1));
            if (! preg_match('/^[a-z0-9_-]{1,100}$/', $id)) {
                throw ValidationException::withMessages(["fields.{$index}.id" => 'Field identifiers may contain lowercase letters, numbers, underscores, and dashes.']);
            }

            return ['id' => $id, 'label' => $label, 'type' => $type, 'required' => (bool) ($field['required'] ?? false), 'options' => $options];
        });
        if ($normalized->pluck('id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['fields' => 'Every field needs a unique identifier.']);
        }

        return $normalized->all();
    }

    /** @param list<array<string,mixed>> $fields @param array<string,mixed> $answers */
    private function validateAnswers(array $fields, array $answers, ?string $signature): void
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = $field['type'] === 'signature' ? $signature : ($answers[$field['id']] ?? null);
            if ($field['required'] && blank($value) && $value !== false && $value !== 0) {
                $errors[$field['id']] = 'This answer is required.';

                continue;
            }
            if ($field['type'] === 'number' && filled($value) && ! is_numeric($value)) {
                $errors[$field['id']] = 'Enter a number.';
            }
            if ($field['type'] === 'date' && filled($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                $errors[$field['id']] = 'Enter a date in YYYY-MM-DD format.';
            }
            if ($field['type'] === 'yes_no' && filled($value) && ! in_array($value, [true, false, 1, 0, 'yes', 'no'], true)) {
                $errors[$field['id']] = 'Choose yes or no.';
            }
            if ($field['type'] === 'multiple_choice' && filled($value) && ! in_array((string) $value, $field['options'], true)) {
                $errors[$field['id']] = 'Choose one of the listed options.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
