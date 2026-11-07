<?php

namespace App\Domain\Communications\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Models\CommunicationActionLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class CommunicationActionLinkService
{
    public function issue(int $businessId, ?Client $client, string $purpose, ?Model $target, CarbonImmutable $expiresAt): CommunicationActionLink
    {
        return CommunicationActionLink::query()->create([
            'business_id' => $businessId, 'client_id' => $client?->id, 'purpose' => $purpose,
            'target_type' => $target?->getMorphClass(), 'target_id' => $target?->getKey(), 'expires_at' => $expiresAt->utc(),
        ]);
    }

    public function url(CommunicationActionLink $link): string
    {
        return URL::temporarySignedRoute('communications.action', $link->expires_at, ['link' => $link->public_id]);
    }

    public function shortUrl(CommunicationActionLink $link): string
    {
        // Lock the existing capability so retries/concurrent rendering reuse one
        // code. Older queued links gain a code only when they are rendered.
        $token = DB::transaction(function () use ($link): string {
            $current = CommunicationActionLink::query()->where('business_id', $link->business_id)
                ->lockForUpdate()->findOrFail($link->id);
            $this->assertUsable($current);
            if (! $current->short_token) {
                $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
                $current->forceFill(['short_token' => $token, 'short_token_hash' => hash('sha256', $token)])->save();
            }

            return $current->short_token;
        }, 3);

        return URL::route('communications.action.short', ['code' => $token]);
    }

    public function resolveShort(string $token): CommunicationActionLink
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{22}$/D', $token), 404);
        $link = CommunicationActionLink::query()->where('short_token_hash', hash('sha256', $token))->firstOrFail();
        $this->assertUsable($link);

        return $link;
    }

    public function assertUsable(CommunicationActionLink $link): void
    {
        abort_if($link->revoked_at || $link->used_at || $link->expires_at->isPast(), 410, 'This action link is no longer available.');
    }

    public function revokeTarget(int $businessId, Model $target, ?string $purpose = null): int
    {
        return CommunicationActionLink::query()->where('business_id', $businessId)->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())->when($purpose, fn ($query) => $query->where('purpose', $purpose))
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
    }
}
