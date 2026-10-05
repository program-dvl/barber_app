<?php

namespace App\Domain\Communications\Services;

use App\Domain\ClientRecords\Services\ClientWorkspaceQuery;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PublicBooking\Models\WaitlistMatch;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotificationWorkspaceQuery
{
    public function base(Business $business, Membership $membership): Builder
    {
        $query = CommunicationMessage::query()->where('business_id', $business->id)->whereIn('channel', ['email', 'sms']);
        if (! $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web')) {
            $query->whereHas('intent', fn ($q) => $q->whereNotIn('intent_type', ['deposit_request', 'deposit_received', 'payment_receipt']));
        }
        if (! $membership->hasRole('owner', 'web')) {
            $locationIds = $membership->locations()->pluck('locations.id');
            $query->whereHas('intent', function ($q) use ($business, $locationIds): void {
                $q->where(function ($sources) use ($business, $locationIds): void {
                    $sources->where(fn ($s) => $s->where('source_type', Appointment::class)->whereIn('source_id', Appointment::query()->select('id')->where('business_id', $business->id)->whereIn('location_id', $locationIds)))
                        ->orWhere(fn ($s) => $s->where('source_type', WalkInEntry::class)->whereIn('source_id', WalkInEntry::query()->select('id')->where('business_id', $business->id)->whereIn('location_id', $locationIds)))
                        ->orWhere(fn ($s) => $s->where('source_type', WaitlistMatch::class)->whereIn('source_id', WaitlistMatch::query()->select('id')->where('business_id', $business->id)->whereHas('request', fn ($r) => $r->whereIn('location_id', $locationIds))));
                });
            });
            if (! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')) {
                $query->whereHas('intent', fn ($q) => $q->where('source_type', Appointment::class)
                    ->whereIn('source_id', app(ClientWorkspaceQuery::class)->scopeAppointments(Appointment::query(), $membership)->select('appointments.id')));
            }
        }

        return $query;
    }

    public function history(Business $business, Membership $membership, array $filters): array
    {
        $query = $this->base($business, $membership);
        foreach (['channel', 'status'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['type'] ?? null) {
            $query->whereHas('intent', fn ($q) => $q->where('intent_type', $filters['type']));
        }
        if ($filters['client'] ?? null) {
            $query->whereHas('client', fn ($q) => $q->where('public_id', $filters['client']));
        }
        if ($filters['appointment'] ?? null) {
            $query->whereHas('intent', fn ($q) => $q->where('source_type', Appointment::class)->whereIn('source_id', Appointment::query()->select('id')->where('business_id', $business->id)->where('public_id', $filters['appointment'])));
        }
        foreach (['from', 'to'] as $field) {
            if ($filters[$field] ?? null) {
                $date = CarbonImmutable::parse($filters[$field], $business->time_zone ?: 'UTC');
                $query->where('created_at', $field === 'from' ? '>=' : '<=', ($field === 'from' ? $date->startOfDay() : $date->endOfDay())->utc());
            }
        }
        if ($term = trim($filters['search'] ?? '')) {
            $contact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
            $destination = $term;
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $query->where(function ($q) use ($term, $contact, $destination, $business): void {
                $q->whereHas('intent', fn ($i) => $i->where('source_type', Appointment::class)->whereIn('source_id', Appointment::query()->select('id')->where('business_id', $business->id)->where('booking_reference', 'like', '%'.$term.'%')));
                if ($contact) {
                    $q->orWhereHas('client', fn ($c) => $c->where('name', 'like', '%'.$term.'%')->orWhere('public_id', $destination));
                    $q->orWhereIn('recipient_hash', [CommunicationConsentService::destinationHash('email', $destination), CommunicationConsentService::destinationHash('sms', $destination)]);
                }
            });
        }
        $page = $query->with(['intent', 'client'])->latest('id')->paginate(25, ['*'], 'page', (int) ($filters['page'] ?? 1));

        return ['data' => $page->getCollection()->map(fn ($m) => $this->row($m, $membership))->all(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()];
    }

    public function overview(Business $business, Membership $membership): array
    {
        $base = $this->base($business, $membership);
        $recent = (clone $base)->where('created_at', '>=', now()->subDays(7))->selectRaw('status, provider, count(*) as total')->groupBy('status', 'provider')->get();
        $counts = ['sent' => 0, 'delivered' => 0, 'failed' => 0, 'suppressed' => 0, 'simulated' => 0];
        foreach ($recent as $group) {
            $key = $group->provider === 'fake' && in_array($group->status, ['sent', 'delivered'], true) ? 'simulated' : $group->status;
            if (isset($counts[$key])) {
                $counts[$key] += $group->total;
            }
        }
        $upcoming = (clone $base)->whereIn('status', ['queued', 'retried'])->where('next_attempt_at', '>', now())->with(['intent', 'client'])->orderBy('next_attempt_at')->limit(4)->get();

        return ['counts' => $counts, 'needs_attention' => (clone $base)->where('status', 'failed')->count(), 'upcoming' => $upcoming->map(fn ($m) => $this->row($m, $membership))->all()];
    }

    public function row(CommunicationMessage $message, Membership $membership): array
    {
        $contact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $variables = $message->template_variables ?? [];

        return ['id' => $message->id, 'type' => $message->intent->intent_type,
            'name' => NotificationWorkspaceCatalog::automations()[$message->intent->intent_type]['name'] ?? 'Client notification',
            'client_name' => $contact ? ($message->client?->name ?? $variables['client_name'] ?? 'Client') : 'Client details restricted',
            'client_id' => $contact && $membership->hasPermissionTo(PermissionName::ClientView->value, 'web') ? $message->client?->public_id : null,
            'recipient' => $contact ? $message->recipient : null, 'reference' => $contact ? ($variables['booking_reference'] ?? null) : null,
            'location' => $contact ? ($variables['location_name'] ?? null) : null,
            'channel' => $message->channel, 'status' => $message->status, 'simulated' => $message->provider === 'fake',
            'issue' => NotificationWorkspaceCatalog::issue($message->last_error_code ?: $message->suppression_reason),
            'can_retry' => $membership->hasPermissionTo(PermissionName::SettingsManage->value, 'web') && app(CommunicationSupportService::class)->canReplay($message),
            'created_at' => $message->created_at->toIso8601String(), 'scheduled_at' => $message->next_attempt_at?->toIso8601String(),
            'sent_at' => $message->sent_at?->toIso8601String(), 'delivered_at' => $message->delivered_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(), 'time_zone' => $message->time_zone, 'attempts' => $message->attempt_count];
    }

    /** Minimal Calendar context, bounded to the most recent outcome per type/channel. */
    public function appointmentSummary(Business $business, Membership $membership, array $publicIds): array
    {
        if ($publicIds === [] || ! $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web')) {
            return [];
        }
        $appointments = app(ClientWorkspaceQuery::class)->scopeAppointments(Appointment::query(), $membership)->whereIn('public_id', $publicIds)->pluck('public_id', 'id');
        $latest = DB::table('communication_messages as m')->join('communication_intents as i', 'i.id', '=', 'm.communication_intent_id')
            ->where('m.business_id', $business->id)->where('i.business_id', $business->id)->where('i.source_type', Appointment::class)->whereIn('i.source_id', $appointments->keys())
            ->whereIn('i.intent_type', ['booking_confirmation', 'booking_approved', 'appointment_reminder', 'appointment_changed', 'appointment_cancelled'])
            ->whereIn('m.channel', ['email', 'sms'])->selectRaw('MAX(m.id)')->groupBy('i.source_id', 'i.intent_type', 'm.channel');

        return CommunicationMessage::query()->where('business_id', $business->id)->whereIn('id', $latest)->with('intent')->latest('id')->get()->groupBy(fn ($m) => $appointments->get($m->intent->source_id))->map(fn ($messages) => $messages->map(fn ($m) => [
            'name' => NotificationWorkspaceCatalog::automations()[$m->intent->intent_type]['name'], 'channel' => $m->channel,
            'status' => $m->status, 'simulated' => $m->provider === 'fake',
        ])->values()->all())->all();
    }
}
