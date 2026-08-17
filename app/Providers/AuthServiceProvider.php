<?php

namespace App\Providers;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Policies\ClientPolicy;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessPermission;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Policies\AuditEventPolicy;
use App\Domain\PlatformAccess\Policies\BusinessPolicy;
use App\Domain\PlatformAccess\Policies\LocationPolicy;
use App\Domain\PlatformAccess\Policies\MembershipPolicy;
use App\Domain\PlatformAccess\Policies\StaffInvitationPolicy;
use App\Domain\PlatformAccess\Policies\StaffProfilePolicy;
use App\Domain\SchedulingOperations\Models\Appointment as SchedulingAppointment;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Domain\SchedulingOperations\Policies\AppointmentPolicy as SchedulingAppointmentPolicy;
use App\Domain\SchedulingOperations\Policies\WalkInEntryPolicy;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Client::class => ClientPolicy::class,
        AuditEvent::class => AuditEventPolicy::class,
        Business::class => BusinessPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Location::class => LocationPolicy::class,
        Membership::class => MembershipPolicy::class,
        StaffInvitation::class => StaffInvitationPolicy::class,
        StaffProfile::class => StaffProfilePolicy::class,
        SchedulingAppointment::class => SchedulingAppointmentPolicy::class,
        WalkInEntry::class => WalkInEntryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (! in_array($ability, PermissionName::values(), true)) {
                return null;
            }

            $membership = app(TenantContext::class)->membership();

            // A newly introduced permission may not have been backfilled yet.
            // Never turn that deploy-order issue into a public exception.
            if (! BusinessPermission::query()->where('name', $ability)->where('guard_name', 'web')->exists()) {
                return false;
            }

            return $membership
                && (int) $membership->user_id === (int) $user->getKey()
                && $membership->isActive()
                && $membership->hasPermissionTo($ability, 'web');
        });
    }
}
