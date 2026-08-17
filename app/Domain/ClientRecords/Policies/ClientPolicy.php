<?php

namespace App\Domain\ClientRecords\Policies;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        $membership = $this->membership($user, $client);
        if (! $membership?->hasPermissionTo(PermissionName::ClientView->value, 'web')) {
            return false;
        }
        if ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') || $membership->hasRole('owner', 'web')) {
            return true;
        }

        return $membership->staffProfile
            && $client->appointments()->whereHas('segments', fn ($query) => $query->where('staff_profile_id', $membership->staffProfile->id))->exists();
    }

    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $this->membership($user, $client)?->hasPermissionTo(PermissionName::ClientManage->value, 'web');
    }

    public function addNote(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $this->membership($user, $client)?->hasPermissionTo(PermissionName::ClientNotesManage->value, 'web');
    }

    public function viewSensitive(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $this->membership($user, $client)?->hasPermissionTo(PermissionName::SensitiveNotesView->value, 'web');
    }

    public function viewAttachments(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $this->membership($user, $client)?->hasPermissionTo(PermissionName::ClientAttachmentsView->value, 'web');
    }

    public function manageForms(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $this->membership($user, $client)?->hasPermissionTo(PermissionName::ClientFormsManage->value, 'web');
    }

    public function managePrivacy(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $this->membership($user, $client)?->hasPermissionTo(PermissionName::ClientPrivacyManage->value, 'web');
    }

    private function membership(User $user, Client $client): mixed
    {
        $context = app(TenantContext::class);
        $membership = $context->membership();

        return $context->hasBusiness()
            && $context->business()->id === $client->business_id
            && $membership?->user_id === $user->id
            && $membership->isActive() ? $membership : null;
    }
}
