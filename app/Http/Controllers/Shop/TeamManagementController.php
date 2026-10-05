<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Services\BusinessActivationManager;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\PlatformAccess\Actions\IssueStaffInvitation;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PlatformAccess\Services\MembershipRestorer;
use App\Domain\PlatformAccess\Services\MembershipRevoker;
use App\Domain\SchedulingOperations\Services\TeamWorkspaceQuery;
use App\Http\Controllers\Controller;
use App\Notifications\WorkspaceAccessChangedNotification;
use App\Rules\E164Phone;
use App\Support\Audit\AuditWriter;
use App\Support\AuditEventPresentation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamManagementController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly BusinessActivationManager $activation,
        private readonly ReadinessEvaluator $readiness,
        private readonly EntitlementEvaluator $entitlements,
        private readonly MembershipAccessManager $access,
        private readonly AuditWriter $audit,
    ) {}

    public function index(Request $request, Business $business, TeamWorkspaceQuery $workspace): Response
    {
        $actor = $this->context->membership();
        abort_unless($actor, 403);
        $data = $workspace->build($request, $business, $actor);
        $manage = $data['can']['manage'];
        $roles = $manage ? BusinessRole::query()->where('business_id', $business->id)
            ->whereIn('name', ['owner', 'manager', 'receptionist', 'barber_stylist', 'accountant'])
            ->with('permissions:id,name')->orderBy('id')->get() : collect();
        $invitations = $manage ? $business->invitations()->whereNull('accepted_at')->whereNull('revoked_at')
            ->where(fn ($q) => $q->where('expires_at', '>', now())->orWhere('created_at', '>=', now()->subDays(90)))
            ->with(['role:id,name', 'role.permissions:id,name', 'locations:id,public_id,name', 'staffProfile:id,public_id,display_name'])->latest()->limit(100)->get()
            ->filter(fn ($i) => $actor->hasRole('owner', 'web') || $i->locations->pluck('id')->diff($data['locations']->pluck('id'))->isEmpty()) : collect();
        $seatLimit = $manage ? (int) ($this->entitlements->value($business, 'staff.max') ?? 0) : 0;
        $used = $manage ? $business->staffProfiles()->where('status', 'active')->count() : 0;
        $services = $business->services()->where('kind', 'service')->where('is_active', true)->with(['category', 'locations'])
            ->whereHas('locations', fn ($q) => $q->whereIn('locations.public_id', $data['locations']->pluck('public_id')))
            ->orderBy('name')->get()->map(fn ($s) => [...$s->only(['public_id', 'name', 'duration_minutes', 'currency_code']),
                'price_minor' => $manage ? $s->price_minor : null, 'category' => $s->category?->name ?: 'Other services',
                'locations' => $s->locations->where('pivot.is_eligible', true)->pluck('public_id')->values()]);

        return Inertia::render('Team/Index', [...$data,
            'services' => $services, 'readiness' => Inertia::optional(fn () => $manage ? $this->readiness->evaluate($business)->toArray() : []),
            'roleSuggestions' => config('business-onboarding.team_roles', []),
            'accessRoles' => $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'label' => $this->roleLabel($r->name),
                'description' => $this->roleDescription($r->name), 'permission_names' => $r->permissions->pluck('name')->sort()->values()]),
            'accessModules' => $manage ? $this->accessModules() : [],
            'grantablePermissions' => $manage ? $actor->getAllPermissions()->pluck('name')->values() : [],
            'pendingInvitations' => $invitations->map(fn ($i) => ['public_id' => $i->public_id, 'email' => $i->email,
                'role_id' => $i->role_id, 'permission_names' => $i->role->permissions->pluck('name')->sort()->values(), 'location_ids' => $i->locations->pluck('public_id')->values(),
                'staff_id' => $i->staffProfile?->public_id, 'person' => $i->staffProfile?->display_name, 'role' => $this->roleLabel($i->role->name),
                'expires_at' => $i->expires_at->toIso8601String(), 'expired' => $i->expires_at->lte(now()), 'locations' => $i->locations->pluck('name')->values()])->values(),
            'seatAllowance' => ['used' => $used, 'limit' => $seatLimit, 'remaining' => max(0, $seatLimit - $used), 'can_add' => $seatLimit === 0 || $used < $seatLimit],
            'canManageOwners' => $actor->hasRole('owner', 'web'),
            'activityPreview' => Inertia::optional(fn () => $manage && $data['can']['audit'] ? $this->activityQuery($business)->limit(5)->get()->map(fn ($e) => $this->activityItem($e)) : []),
        ]);
    }

    public function store(Request $request, Business $business, IssueStaffInvitation $invitations): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32', new E164Phone],
            'title' => ['nullable', 'string', 'max:255'],
            'online_visible' => ['required', 'boolean'],
            'location' => ['required', 'string'],
            'service_ids' => ['array'],
            'service_ids.*' => ['string'],
            'working_days' => ['present', 'array'],
            'working_days.*' => ['integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'invite_access' => ['sometimes', 'boolean'],
            'access_role_id' => ['nullable', 'integer'],
            'custom_access' => ['sometimes', 'boolean'],
            'permission_names' => ['array'],
            'permission_names.*' => ['string', Rule::in(PermissionName::values())],
        ]);
        $location = $business->locations()->where('public_id', $data['location'])->where('is_active', true)->firstOrFail();
        $this->authorizeLocations([$location->id]);
        $serviceIds = $business->services()->where('is_active', true)->whereHas('locations', fn ($q) => $q->where('locations.id', $location->id)->where('location_service.is_eligible', true))->whereIn('public_id', $data['service_ids'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all();
        abort_unless(count($serviceIds) === count(array_unique($data['service_ids'] ?? [])), 404);
        DB::transaction(function () use ($business, $data, $location, $serviceIds, $request, $invitations): void {
            $locked = Business::query()->lockForUpdate()->findOrFail($business->id);
            $existing = $locked->staffProfiles()->whereRaw('lower(email) = ?', [strtolower(trim($data['email']))])->with(['locations', 'availabilityRules', 'serviceAssignments'])->first();
            if ($existing) {
                $requested = collect($data['working_days'])->unique()->sort()->values()->all();
                $working = $existing->availabilityRules->where('kind', 'working');
                $exact = $existing->display_name === trim($data['display_name']) && ($existing->title ?: null) === (($data['title'] ?? null) ?: null)
                    && ($existing->mobile ?: null) === ($data['mobile'] ?? null) && $existing->status === 'active' && $existing->online_visible === (bool) $data['online_visible']
                    && $existing->locations->pluck('id')->all() === [$location->id]
                    && $working->pluck('day_of_week')->sort()->values()->all() === $requested
                    && $working->every(fn ($r) => $r->location_id === $location->id && substr($r->starts_at, 0, 5) === $data['starts_at'] && substr($r->ends_at, 0, 5) === $data['ends_at'])
                    && $existing->serviceAssignments->where('is_active', true)->where('is_qualified', true)->pluck('service_id')->sort()->values()->all() === collect($serviceIds)->sort()->values()->all();
                if (! $exact || ($data['invite_access'] ?? false)) {
                    throw ValidationException::withMessages(['email' => 'A team member already uses this email. Open their profile to edit their schedule, services or access.', 'working_days' => 'Existing schedules require a reviewed change from the staff profile.']);
                }

                return; // Exact create retry is harmless and cannot rewrite an existing schedule or service version.
            }
            $staff = $this->activation->saveProvider($business, [
                ...$data,
                'location_id' => $location->getKey(),
                'service_ids' => $serviceIds,
            ]);

            if ((bool) ($data['invite_access'] ?? false)) {
                if ($staff->membership_id) {
                    throw ValidationException::withMessages(['invite_access' => 'This team member already has workspace access.']);
                }

                $role = $this->resolveRequestedRole($business, $staff, $data, $request);
                $invitations->handle(
                    inviter: $this->context->membership(),
                    email: $staff->email,
                    role: $role,
                    locationIds: [$location->getKey()],
                    staffProfile: $staff,
                );
            }

        });

        return back()->with('status', (bool) ($data['invite_access'] ?? false)
            ? 'Team member saved and a secure workspace invitation was emailed.'
            : 'Team member and weekly availability saved without login access.');
    }

    public function updateAccess(Request $request, Business $business, Membership $membership): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((int) $membership->business_id === (int) $business->getKey(), 404);
        $this->authorizeLocations($membership->locations()->pluck('locations.id')->all());
        $actorMembership = $this->context->membership();
        if ((int) $membership->getKey() === (int) $actorMembership?->getKey()) {
            throw new AuthorizationException('Manage your own security from Profile & security, not from Team access.');
        }

        $data = $request->validate([
            'access_role_id' => ['nullable', 'integer'],
            'custom_access' => ['required', 'boolean'],
            'permission_names' => ['array'],
            'permission_names.*' => ['string', Rule::in(PermissionName::values())],
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $targetIsOwner = $membership->hasRole('owner', 'web');
        if ($targetIsOwner && ! $actorMembership->hasRole('owner', 'web')) {
            throw new AuthorizationException('Only an owner may change another owner’s access.');
        }

        $locationIds = $business->locations()->whereIn('public_id', $data['location_ids'])->pluck('id')->all();
        abort_unless(count($locationIds) === count(array_unique($data['location_ids'])), 404);
        $this->authorizeLocations($locationIds);
        DB::transaction(function () use ($business, $membership, $data, $request, $locationIds): void {
            $role = $this->resolveRequestedRole($business, $membership->staffProfile, $data, $request, $membership);
            $reason = $data['reason'] ?: 'Access updated by an authorized team manager.';
            $this->access->assignCustomRole($membership, $role, $request->user(), $reason);

            $beforeLocations = $membership->locations()->pluck('locations.public_id')->all();
            $membership->locations()->syncWithPivotValues($locationIds, ['business_id' => $business->getKey()]);
            $this->audit->write(
                'membership.locations.changed', $business, $request->user(), $membership,
                $reason, ['locations' => $beforeLocations], ['locations' => $data['location_ids']],
            );
        });

        $membership->refresh();
        $role = $membership->getRoleNames()->first();
        $membership->user?->notify(new WorkspaceAccessChangedNotification(
            businessId: $business->getKey(),
            businessPublicId: $business->public_id,
            businessName: $business->name,
            change: 'updated',
            role: $role ? $this->roleLabel($role) : null,
        ));

        return back()->with('status', 'Workspace role, module access and locations updated.');
    }

    public function inviteAccess(Request $request, Business $business, StaffProfile $staffProfile, IssueStaffInvitation $invitations): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((int) $staffProfile->business_id === (int) $business->getKey(), 404);
        $this->authorizeLocations($staffProfile->locations()->pluck('locations.id')->all());
        if ($staffProfile->membership_id) {
            throw ValidationException::withMessages(['email' => 'This team member already has workspace access.']);
        }
        if (! filled($staffProfile->email)) {
            throw ValidationException::withMessages(['email' => 'Add an email address to this team profile before inviting access.']);
        }

        $data = $request->validate([
            'access_role_id' => ['nullable', 'integer'],
            'custom_access' => ['required', 'boolean'],
            'permission_names' => ['array'],
            'permission_names.*' => ['string', Rule::in(PermissionName::values())],
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['string'],
        ]);
        $locationIds = $business->locations()->whereIn('public_id', $data['location_ids'])->pluck('id')->all();
        abort_unless(count($locationIds) === count(array_unique($data['location_ids'])), 404);
        $this->authorizeLocations($locationIds);
        $role = $this->resolveRequestedRole($business, $staffProfile, $data, $request);
        $invitations->handle(
            inviter: $this->context->membership(),
            email: $staffProfile->email,
            role: $role,
            locationIds: $locationIds,
            staffProfile: $staffProfile,
        );

        return back()->with('status', 'A secure workspace invitation was emailed.');
    }

    public function revokeAccess(Request $request, Business $business, Membership $membership, MembershipRevoker $revoker): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((int) $membership->business_id === (int) $business->getKey(), 404);
        $this->authorizeLocations($membership->locations()->pluck('locations.id')->all());
        if ($membership->hasRole('owner', 'web') && ! $this->context->membership()?->hasRole('owner', 'web')) {
            throw new AuthorizationException('Only an owner may remove another owner’s access.');
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $revoker->revoke($membership, $request->user(), $data['reason']);

        return back()->with('status', 'Workspace access removed immediately. Historical activity remains attributable.');
    }

    public function restoreAccess(Request $request, Business $business, Membership $membership, MembershipRestorer $restorer): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((int) $membership->business_id === (int) $business->getKey(), 404);
        $this->authorizeLocations($membership->locations()->pluck('locations.id')->all());
        if ($membership->hasRole('owner', 'web') && ! $this->context->membership()?->hasRole('owner', 'web')) {
            throw new AuthorizationException('Only an owner may restore another owner’s access.');
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $restorer->restore($membership, $request->user(), $data['reason']);

        return back()->with('status', 'Workspace access restored. The team member can sign in with their existing password.');
    }

    public function activity(Request $request, Business $business): Response
    {
        abort_unless($this->context->membership()?->hasPermissionTo(PermissionName::AuditView->value, 'web'), 403);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['access', 'appointments', 'clients', 'payments', 'configuration', 'all'])],
        ]);
        $query = $this->activityQuery($business);
        if (filled($data['search'] ?? null)) {
            $search = '%'.strtolower(trim($data['search'])).'%';
            $query->where(fn ($builder) => $builder
                ->whereRaw('lower(action) like ?', [$search])
                ->orWhereRaw('lower(reason) like ?', [$search])
                ->orWhereHas('actor', fn ($actor) => $actor->whereRaw('lower(name) like ?', [$search])));
        }
        if (($data['category'] ?? 'all') !== 'all') {
            $prefixes = $this->activityCategoryPrefixes()[$data['category']];
            $query->where(fn ($builder) => collect($prefixes)->each(fn (string $prefix) => $builder->orWhere('action', 'like', $prefix.'%')));
        }

        return Inertia::render('Team/Activity', [
            'business' => $business->only(['public_id', 'name', 'time_zone', 'locale']),
            'filters' => ['search' => $data['search'] ?? '', 'category' => $data['category'] ?? 'all'],
            'events' => $query->paginate(30)->withQueryString()->through(fn (AuditEvent $event): array => $this->activityItem($event)),
        ]);
    }

    private function authorizeLocations(array $ids): void
    {
        $actor = $this->context->membership();
        abort_unless($actor->hasRole('owner', 'web') || collect($ids)->diff($actor->locations()->pluck('locations.id'))->isEmpty(), 403);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->context->membership()?->hasPermissionTo(PermissionName::StaffManage->value, 'web'), 403);
    }

    /** @param array<string, mixed> $data */
    private function resolveRequestedRole(Business $business, mixed $staff, array $data, Request $request, ?Membership $membership = null): BusinessRole
    {
        $actorMembership = $this->context->membership();
        $reason = 'Access selected by '.$request->user()->name.'.';
        if ((bool) ($data['custom_access'] ?? false)) {
            $permissions = array_values(array_unique($data['permission_names'] ?? []));
            if ($permissions === []) {
                throw ValidationException::withMessages(['permission_names' => 'Choose at least one module for custom access.']);
            }
            foreach ($permissions as $permission) {
                if (! $this->access->allows($actorMembership, PermissionName::from($permission))) {
                    throw new AuthorizationException('You cannot grant access that your own role does not have.');
                }
            }
            $identifier = $membership?->public_id ?? $staff?->public_id;

            return $this->access->defineCustomRole($business, 'custom_access_'.$identifier, $permissions, $request->user(), $reason);
        }

        $role = BusinessRole::query()->where('business_id', $business->getKey())->whereKey((int) ($data['access_role_id'] ?? 0))->firstOrFail();
        if ($role->name === 'owner' && ! $actorMembership->hasRole('owner', 'web')) {
            throw new AuthorizationException('Only an owner may grant owner access.');
        }
        foreach ($role->permissions()->pluck('name') as $permission) {
            if (! $this->access->allows($actorMembership, PermissionName::from($permission))) {
                throw new AuthorizationException('You cannot grant a role with access beyond your own.');
            }
        }

        return $role;
    }

    /** @return list<array<string, mixed>> */
    private function accessModules(): array
    {
        return [
            ['key' => 'calendar', 'label' => 'Calendar & bookings', 'description' => 'View the full calendar, manage appointments and handle walk-ins.', 'permissions' => ['calendar.view.all', 'appointments.manage.all', 'walk_ins.manage']],
            ['key' => 'clients', 'label' => 'Clients', 'description' => 'View contact details, records, notes and forms.', 'permissions' => ['clients.contact.view', 'clients.view', 'clients.manage', 'clients.notes.manage', 'clients.forms.manage', 'clients.attachments.view']],
            ['key' => 'checkout', 'label' => 'Checkout', 'description' => 'Complete visits and apply approved discounts.', 'permissions' => ['checkout.manage', 'discounts.apply']],
            ['key' => 'reports', 'label' => 'Reports & exports', 'description' => 'See revenue, commissions, activity and exports.', 'permissions' => ['revenue.view', 'commissions.view.all', 'audit.view', 'exports.create']],
            ['key' => 'team', 'label' => 'Team management', 'description' => 'Invite people and manage roles, access and schedules.', 'permissions' => ['staff.manage']],
            ['key' => 'settings', 'label' => 'Business settings', 'description' => 'Manage services, locations, policies and workspace setup.', 'permissions' => ['settings.manage']],
            ['key' => 'billing', 'label' => 'Subscription & billing', 'description' => 'Manage plans, invoices and subscription billing.', 'permissions' => ['billing.manage']],
        ];
    }

    private function roleLabel(string $role): string
    {
        return ['owner' => 'Owner', 'manager' => 'Manager', 'receptionist' => 'Receptionist', 'barber_stylist' => 'Professional', 'accountant' => 'Accountant'][$role]
            ?? 'Custom access';
    }

    private function roleDescription(string $role): string
    {
        return [
            'owner' => 'Everything, including team access and subscription billing.',
            'manager' => 'Day-to-day operations, team, settings, reports and audit activity.',
            'receptionist' => 'Calendar, clients, walk-ins and checkout without financial reporting.',
            'barber_stylist' => 'Their own calendar, appointments, client notes and commission view.',
            'accountant' => 'Revenue, cash close, commissions, exports and audit activity.',
        ][$role] ?? 'A tailored set of module permissions.';
    }

    private function activityQuery(Business $business)
    {
        return AuditEvent::query()->where('business_id', $business->getKey())->with(['actor:id,name,email', 'business:id,time_zone'])->latest('occurred_at');
    }

    /** @return array<string, mixed> */
    private function activityItem(AuditEvent $event): array
    {
        return [
            'public_id' => $event->public_id,
            'actor' => $event->actor?->name ?? 'System',
            'actor_email' => $event->actor?->email,
            'action' => $event->action,
            'label' => app(AuditEventPresentation::class)->label($event),
            'summary' => app(AuditEventPresentation::class)->summary($event),
            'reason' => $event->reason,
            'before' => $event->before,
            'after' => $event->after,
            'source' => $event->source,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ];
    }

    /** @return array<string, list<string>> */
    private function activityCategoryPrefixes(): array
    {
        return [
            'access' => ['staff.', 'membership.', 'business.role.', 'support.'],
            'appointments' => ['appointment.', 'walk_in.', 'schedule.'],
            'clients' => ['client.'],
            'payments' => ['sale.', 'payment.', 'refund.', 'cash_close.'],
            'configuration' => ['activation.', 'configuration.', 'service.', 'location.'],
        ];
    }
}
