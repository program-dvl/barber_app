<?php

namespace App\Domain\ClientRecords\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientAttachment;
use App\Domain\ClientRecords\Models\ClientAttachmentAccessLink;
use App\Domain\PlatformAccess\Models\Business;
use App\Support\Files\TenantPrivateStorage;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientAttachmentService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(
        private readonly TenantPrivateStorage $storage,
        private readonly TenantContext $context,
    ) {}

    public function store(Client $client, string $contents, string $originalName, string $kind, string $visibility = 'standard', ?int $appointmentId = null, ?int $staffProfileId = null): ClientAttachment
    {
        if ($contents === '' || strlen($contents) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachment' => 'Attach a non-empty file no larger than 10 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw ValidationException::withMessages(['attachment' => 'Only verified JPEG, PNG, and PDF files are accepted.']);
        }
        if (! in_array($kind, ['file', 'before', 'after', 'profile_photo'], true) || ! in_array($visibility, ['standard', 'sensitive'], true)) {
            throw ValidationException::withMessages(['attachment' => 'The attachment classification is not supported.']);
        }
        $publicId = (string) Str::ulid();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf',
        };
        $business = Business::query()->findOrFail($client->business_id);
        $key = $this->storage->put($business, 'files/clients/'.$client->public_id.'/'.$publicId.'.'.$extension, $contents);

        return ClientAttachment::query()->create([
            'business_id' => $client->business_id,
            'public_id' => $publicId,
            'client_id' => $client->id,
            'appointment_id' => $appointmentId,
            'uploaded_by_staff_profile_id' => $staffProfileId,
            'kind' => $kind,
            'visibility' => $visibility,
            'object_key' => $key,
            'original_name' => Str::limit(basename($originalName), 255, ''),
            'mime_type' => $mime,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'scan_status' => 'clean',
            'retention_class' => 'client_context',
        ]);
    }

    public function storePrivacyExport(Client $client, string $contents): ClientAttachment
    {
        $publicId = (string) Str::ulid();
        $business = Business::query()->findOrFail($client->business_id);
        $key = $this->storage->put($business, 'files/privacy/'.$client->public_id.'/'.$publicId.'.json', $contents);

        return ClientAttachment::query()->create([
            'business_id' => $client->business_id,
            'public_id' => $publicId,
            'client_id' => $client->id,
            'kind' => 'privacy_export',
            'visibility' => 'sensitive',
            'object_key' => $key,
            'original_name' => 'client-data-export-'.$client->public_id.'.json',
            'mime_type' => 'application/json',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'scan_status' => 'clean',
            'retention_class' => 'privacy_export',
            'retention_until' => now()->addDays(30),
        ]);
    }

    /** @return array{token:string,expires_at:string} */
    public function issueDownload(ClientAttachment $attachment, int $ttlMinutes = 15): array
    {
        if ($attachment->scan_status !== 'clean' || $ttlMinutes < 1 || $ttlMinutes > 60) {
            throw ValidationException::withMessages(['attachment' => 'This file is not available for download.']);
        }
        ClientAttachmentAccessLink::query()->where('business_id', $attachment->business_id)
            ->where('client_attachment_id', $attachment->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        $token = bin2hex(random_bytes(32));
        $expires = CarbonImmutable::now()->addMinutes($ttlMinutes);
        ClientAttachmentAccessLink::query()->create([
            'business_id' => $attachment->business_id,
            'client_attachment_id' => $attachment->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expires,
        ]);

        return ['token' => $token, 'expires_at' => $expires->toIso8601String()];
    }

    public function resolve(string $token): ClientAttachment
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            abort(404);
        }
        $link = ClientAttachmentAccessLink::query()->with('attachment.business')->where('token_hash', hash('sha256', $token))->first();
        abort_unless($link, 404);
        abort_if($link->revoked_at || $link->expires_at->isPast(), 410, 'This private file link has expired.');
        abort_unless($link->attachment->scan_status === 'clean', 410);
        $link->forceFill(['last_accessed_at' => now(), 'access_count' => $link->access_count + 1])->save();

        return $link->attachment;
    }

    public function contents(ClientAttachment $attachment): string
    {
        return $this->context->run($attachment->business, null, fn () => $this->storage->getStoredKey($attachment->business, $attachment->object_key));
    }
}
