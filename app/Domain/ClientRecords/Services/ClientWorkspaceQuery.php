<?php

namespace App\Domain\ClientRecords\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only CRM projection. Every history query retains business, location and own scope. */
class ClientWorkspaceQuery
{
    public const LIVE = ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'];

    public function contact(Membership $membership): bool
    {
        return $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
    }

    public function finance(Membership $membership): bool
    {
        return $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web') || $membership->hasPermissionTo(PermissionName::CheckoutManage->value, 'web');
    }

    public function scopeAppointments(Builder $query, Membership $membership): Builder
    {
        $query->where('appointments.business_id', $membership->business_id);
        if (! $membership->hasRole('owner', 'web')) {
            $query->whereIn('appointments.location_id', $membership->locations()->select('locations.id'));
        }
        if (! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') && ! $membership->hasRole('owner', 'web')) {
            $query->whereHas('segments', fn ($q) => $q->where('staff_profile_id', $membership->staffProfile?->id ?? 0));
        }

        return $query;
    }

    public function clients(Membership $membership): Builder
    {
        $query = Client::query()->where('clients.business_id', $membership->business_id)->where('clients.status', 'active');
        if (! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') && ! $membership->hasRole('owner', 'web')) {
            $query->whereHas('appointments', fn ($q) => $this->scopeAppointments($q, $membership));
        }

        return $query;
    }

    public function search(Builder $query, string $term, bool $contact): Builder
    {
        if ($term === '') {
            return $query;
        }
        $name = ClientIdentityNormalizer::name($term);
        $email = str_replace(['%', '_'], '', mb_strtolower(trim($term)));
        $digits = preg_replace('/\D+/', '', $term);

        return $query->where(function ($q) use ($name, $email, $digits, $contact, $term) {
            $q->whereRaw('1 = 0');
            if ($name !== '') {
                $q->orWhere('normalized_name', 'like', '%'.$name.'%');
            }
            if (preg_match('/^[a-zA-Z0-9]{26}$/', $term)) {
                $q->orWhere('public_id', strtoupper($term));
            }
            if ($contact && $email !== '') {
                $q->orWhere('normalized_email', 'like', '%'.$email.'%');
                if ($digits !== '') {
                    $q->orWhere('normalized_mobile', 'like', '%'.$digits.'%');
                }
            }
        });
    }

    public function directory(Membership $membership, array $filters): mixed
    {
        $query = $this->search($this->clients($membership), $filters['search'], $this->contact($membership));
        $completed = fn ($q) => $this->scopeAppointments($q, $membership)->where('appointments.status', 'completed');
        $upcoming = fn ($q) => $this->scopeAppointments($q, $membership)->whereIn('appointments.status', self::LIVE)->where('starts_at_utc', '>=', now());
        $query->with('preferredStaff')->withCount(['appointments as visit_count' => $completed])
            ->withMax(['appointments as last_visit' => $completed], 'starts_at_utc')
            ->withMin(['appointments as next_appointment' => $upcoming], 'starts_at_utc')
            ->withCount(['appointments as no_show_count' => fn ($q) => $this->scopeAppointments($q, $membership)->where('appointments.status', 'no_show')])
            ->withCount(['notes as important_notes' => fn ($q) => $q->where('is_important', true)
                ->when(! $membership->hasPermissionTo(PermissionName::SensitiveNotesView->value, 'web'), fn ($q) => $q->where('visibility', 'standard'))]);
        match ($filters['relationship']) {
            'upcoming' => $query->whereHas('appointments', $upcoming),
            'unbooked' => $query->whereDoesntHave('appointments', $upcoming),
            'returning' => $query->whereHas('appointments', $completed),
            'new' => $query->whereDoesntHave('appointments', $completed),
            'no_shows' => $query->whereHas('appointments', fn ($q) => $this->scopeAppointments($q, $membership)->where('appointments.status', 'no_show')),
            'duplicates' => $query->where(function ($q) use ($membership) {
                $candidates = ClientDuplicateCandidate::query()->where('business_id', $membership->business_id)->where('status', 'pending');
                $q->whereIn('clients.id', (clone $candidates)->select('first_client_id'))->orWhereIn('clients.id', (clone $candidates)->select('second_client_id'));
            }),
            'lapsed' => $query->whereHas('appointments', $completed)->whereDoesntHave('appointments', fn ($q) => $completed($q)->where('starts_at_utc', '>=', now()->subDays(90)))->whereDoesntHave('appointments', $upcoming),
            default => null,
        };
        if ($filters['staff']) {
            $query->whereHas('preferredStaff', fn ($q) => $q->where('public_id', $filters['staff'])->where('business_id', $membership->business_id));
        }
        match ($filters['sort']) {
            'recent' => $query->orderByDesc('created_at'),
            'visits' => $query->orderByDesc('visit_count'),
            'last_visit' => $query->orderByRaw('last_visit is null')->orderByDesc('last_visit'),
            'next_appointment' => $query->orderByRaw('next_appointment is null')->orderBy('next_appointment'),
            default => $query->orderBy('name'),
        };

        return $query->orderBy('clients.id')->paginate(30)->withQueryString()->through(fn ($client) => [
            'public_id' => $client->public_id, 'name' => $client->name,
            'mobile' => $this->contact($membership) ? $client->mobile : null,
            'email' => $this->contact($membership) ? $client->email : null,
            'visit_count' => $client->visit_count, 'last_visit' => $client->last_visit,
            'next_appointment' => $client->next_appointment, 'preferred_staff' => $client->preferredStaff?->display_name,
            'important_notes' => $client->important_notes, 'no_show_count' => $client->no_show_count,
        ]);
    }

    public function appointments(Client $client, Membership $membership): Builder
    {
        return $this->scopeAppointments($client->appointments()->getQuery(), $membership)->reorder();
    }

    public function visit(Appointment $visit, Membership $membership): array
    {
        $zone = $visit->time_zone;
        $calendar = $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') || $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web');

        return [
            'public_id' => $visit->public_id, 'reference' => $visit->booking_reference, 'status' => $visit->status,
            'starts_at' => $visit->starts_at_utc->toIso8601String(), 'time_zone' => $zone,
            'local_date' => $visit->starts_at_utc->setTimezone($zone)->toDateString(),
            'location' => $visit->location?->name, 'location_id' => $visit->location?->public_id,
            'source' => $visit->source, 'duration' => (int) $visit->starts_at_utc->diffInMinutes($visit->ends_at_utc),
            'price_minor' => $this->finance($membership) ? $visit->price_minor : null,
            'currency' => $this->finance($membership) ? $visit->currency_code : null,
            'calendar' => $calendar,
            'checkout' => $visit->status === 'completed' && $membership->hasPermissionTo(PermissionName::CheckoutManage->value, 'web'),
            'services' => $visit->serviceLines->map(fn ($line) => [
                'name' => $line->name,
                'performers' => $line->segments->map(fn ($segment) => $segment->staff?->display_name)->filter()->unique()->values(),
            ]),
        ];
    }

    public function profile(Client $client, Membership $membership): array
    {
        $base = $this->appointments($client, $membership);
        $stats = (clone $base)->selectRaw("count(case when status = 'completed' then 1 end) as visit_count, max(case when status = 'completed' then starts_at_utc end) as last_visit, count(case when status like 'cancelled%' then 1 end) as cancellations, count(case when status = 'no_show' then 1 end) as no_shows")->first();
        $with = ['location', 'serviceLines.segments.staff'];
        $next = (clone $base)->whereIn('status', self::LIVE)->where('ends_at_utc', '>', now())->with($with)->orderBy('starts_at_utc')->first();
        $visits = (clone $base)->with($with)->orderByDesc('starts_at_utc')->orderByDesc('id')->paginate(20, ['*'], 'visits_page')->withQueryString();
        $services = DB::table('appointment_service_lines as l')->join('appointments as a', 'a.id', '=', 'l.appointment_id')
            ->where('l.business_id', $membership->business_id)->whereIn('a.id', (clone $base)->where('status', 'completed')->select('appointments.id'))
            ->selectRaw('l.name, count(*) as count, max(a.starts_at_utc) as last_used')->groupBy('l.name')->orderByDesc('count')->orderByDesc('last_used')->limit(4)->get();
        $financial = null;
        if ($this->finance($membership)) {
            $sales = Sale::query()->where('business_id', $client->business_id)->where('client_id', $client->id)
                ->when(! $membership->hasRole('owner', 'web'), fn ($q) => $q->whereIn('location_id', $membership->locations()->select('locations.id')))
                ->when(! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') && ! $membership->hasRole('owner', 'web'), fn ($q) => $q->whereIn('appointment_id', (clone $base)->select('appointments.id')));
            $totals = (clone $sales)->selectRaw("currency_code, sum(case when status = 'completed' then paid_minor + deposit_applied_minor - refunded_minor else 0 end) as net_minor, sum(case when status = 'completed' then refunded_minor else 0 end) as refunded_minor, sum(case when status = 'completed' then tip_minor else 0 end) as tips_minor, sum(case when status = 'open' and balance_minor > 0 then balance_minor else 0 end) as outstanding_minor, count(case when status = 'completed' then 1 end) as sale_count")->groupBy('currency_code')->get();
            $payments = PaymentTransaction::query()->where('business_id', $client->business_id)->whereIn('sale_id', (clone $sales)->select('id'))
                ->with('sale.appointment')->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20, ['*'], 'payments_page')->withQueryString()
                ->through(fn ($t) => ['public_id' => $t->public_id, 'kind' => $t->kind, 'status' => $t->status, 'method' => $t->method,
                    'amount_minor' => $t->amount_minor, 'currency' => $t->currency_code, 'at' => $t->occurred_at?->toIso8601String(),
                    'reference' => $t->sale?->appointment?->booking_reference ?? 'Retail sale']);
            $pending = (clone $base)->where('status', 'completed')->whereDoesntHave('sale', fn ($q) => $q->where('status', 'completed'))->count();
            $financial = ['totals' => $totals, 'payments' => $payments, 'awaiting_checkout' => $pending];
        }
        $walkIns = WalkInEntry::query()->where('business_id', $client->business_id)->where('client_id', $client->id)->whereNull('appointment_id')
            ->when(! $membership->hasRole('owner', 'web'), fn ($q) => $q->whereIn('location_id', $membership->locations()->select('locations.id')))
            ->when(! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') && ! $membership->hasRole('owner', 'web'), fn ($q) => $q->where('assigned_staff_profile_id', $membership->staffProfile?->id ?? 0))
            ->with('service')->orderByDesc('arrived_at')->limit(10)->get()->map(fn ($w) => ['public_id' => $w->public_id, 'status' => $w->status, 'arrived_at' => $w->arrived_at->toIso8601String(), 'service' => $w->service?->name]);

        return [
            'summary' => $stats->only(['visit_count', 'last_visit', 'cancellations', 'no_shows']),
            'upcoming' => $next ? $this->visit($next, $membership) : null,
            'recentAppointments' => (clone $base)->where('starts_at_utc', '<=', now())->with($with)->orderByDesc('starts_at_utc')->limit(4)->get()->map(fn ($v) => $this->visit($v, $membership)),
            'lastAppointment' => (clone $base)->where('status', 'completed')->orderByDesc('starts_at_utc')->value('public_id'),
            'appointments' => $visits->getCollection()->map(fn ($v) => $this->visit($v, $membership)),
            'visitPagination' => collect($visits->toArray())->only(['links', 'total', 'current_page', 'last_page', 'from', 'to']), 'frequentServices' => $services, 'financial' => $financial, 'walkIns' => $walkIns,
        ];
    }

    public function matches(Membership $membership, ?string $mobile, ?string $email): mixed
    {
        $phone = ClientIdentityNormalizer::mobile($mobile);
        $mail = ClientIdentityNormalizer::email($email);
        if (! $phone && ! $mail) {
            return collect();
        }

        return $this->clients($membership)->where(function ($q) use ($phone, $mail) {
            if ($phone) {
                $q->where('normalized_mobile', $phone);
            }
            if ($mail) {
                $phone ? $q->orWhere('normalized_email', $mail) : $q->where('normalized_email', $mail);
            }
        })->limit(5)->get();
    }
}
