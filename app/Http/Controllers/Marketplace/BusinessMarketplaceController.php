<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\BusinessConfiguration\Services\BusinessSetupAccess;
use App\Domain\Marketplace\LocalGrowth\Services\PublicOfferQuery;
use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Domain\Marketplace\LocalGrowth\Services\SeoPageRegistry;
use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\Marketplace\Models\Message;
use App\Domain\Marketplace\Services\BookingMessages;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BusinessMarketplaceController extends Controller
{
    public function index(Request $request, Business $business)
    {
        return Inertia::render('Marketplace/BusinessInbox', ['businessLabel' => $business->name, 'inbox' => $this->inbox($request, $business)]);
    }

    public function settings(Request $request, Business $business)
    {
        abort_unless(app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);
        $settings = true;

        return Inertia::render('Marketplace/Business', [
            'businessLabel' => $business->name, 'canManageListing' => $settings,
            'listing' => ['listed' => (bool) $business->marketplace_listed || ! $business->marketplace_consented_at, 'version' => (int) $business->marketplace_version,
                'ready' => config('marketplace-growth.enabled') ? app(PublicOfferQuery::class)->query()->where('o.business_id', $business->id)->exists() : ($business->configuration_published_at && $business->online_booking_enabled && $business->locations()->where('is_active', true)->where('status', 'active')->exists()),
                'url' => '/marketplace/salons/'.$business->booking_slug,
                'preview_url' => route('business.marketplace.preview', $business),
                'save_url' => route('business.marketplace.listing', $business),
                'setup_url' => route('business.configuration.show', $business)],
            'publication' => $settings && config('marketplace-growth.enabled') ? $this->publicationPanel($business) : null,
            'stats' => $settings ? [
                'bookings' => PublicBookingFlow::query()->where('business_id', $business->id)->where('discovery_source', 'marketplace')->where('status', 'confirmed')->count(),
                'started' => PublicBookingFlow::query()->where('business_id', $business->id)->where('discovery_source', 'marketplace')->count(),
            ] : null,
        ]);
    }

    private function inbox(Request $request, Business $business): array
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'view' => ['nullable', 'in:all,unread']]);
        $membership = app(TenantContext::class)->membership();
        $contact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $query = CustomerBooking::query()->where('business_id', $business->id)->whereHas('appointment', function ($q) use ($membership) {
            if ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')) {
                if (! $membership->hasRole('owner', 'web')) {
                    $q->whereIn('location_id', $membership->locations()->pluck('locations.id'));
                }
            } elseif ($membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web') && $membership->staffProfile) {
                $q->whereHas('segments', fn ($q) => $q->where('staff_profile_id', $membership->staffProfile->id));
            } else {
                $q->whereRaw('1 = 0');
            }
        });
        if (! $contact) {
            $query->whereRaw('1 = 0');
        }
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            // Treat wildcard characters as literal input; never search message bodies.
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->where(fn ($q) => $q->whereHas('customer', fn ($c) => $c->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$literal.'%']))
                ->orWhereHas('appointment', fn ($a) => $a->whereRaw("booking_reference LIKE ? ESCAPE '!'", ['%'.$literal.'%'])));
        }
        if (($filters['view'] ?? 'all') === 'unread') {
            $query->whereHas('messages', fn ($q) => $q->where('sender', 'customer')->whereNull('read_at'));
        }
        $threads = $query->with(['appointment', 'customer', 'latestMessage'])
            ->withCount(['messages as unread' => fn ($q) => $q->where('sender', 'customer')->whereNull('read_at')])
            ->orderByDesc(Message::query()->selectRaw('MAX(id)')->whereColumn('booking_id', 'marketplace_bookings.id'))->orderByDesc('id')
            ->paginate(25)->appends($filters)->through(fn ($b) => [
                'id' => $b->appointment->public_id, 'name' => $b->customer->name ?: $b->appointment->client_name,
                'reference' => $b->appointment->booking_reference,
                'starts_at' => $b->appointment->starts_at_utc->setTimezone($b->appointment->time_zone)->format('d M Y, H:i T'),
                'url' => route('business.marketplace.thread', [$business, $b->appointment->public_id]),
                'preview' => $b->latestMessage ? ($b->latestMessage->sender === 'business' ? 'You: ' : '').Str::limit($b->latestMessage->body, 100) : 'No messages yet',
                'unread' => (int) $b->unread,
            ]);
        // Pagination always returns to the inbox rather than keeping a thread URL.
        $threads->withPath(route('business.marketplace.index', $business));

        return ['threads' => $threads, 'q' => $search, 'view' => $filters['view'] ?? 'all', 'url' => route('business.marketplace.index', $business), 'canContact' => $contact];
    }

    public function listing(Request $request, Business $business)
    {
        abort_unless(app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);
        $data = $request->validate(['listed' => ['required', 'boolean'], 'version' => ['required', 'integer', 'min:1'], 'consent' => ['required_if:listed,true', 'accepted_if:listed,true']]);
        DB::transaction(function () use ($business, $data) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            if ((int) $business->marketplace_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['listed' => 'Your listing changed in another window. Refresh this page before saving.']);
            }
            if ($data['listed'] && (! $business->configuration_published_at || ! $business->online_booking_enabled || ! $business->locations()->where('is_active', true)->where('status', 'active')->exists())) {
                throw ValidationException::withMessages(['listed' => 'Publish your booking page with an active location before listing your business.']);
            }
            $before = ['listed' => (bool) $business->marketplace_listed || ! $business->marketplace_consented_at];
            $business->forceFill(['marketplace_listed' => $data['listed'], 'marketplace_consented_at' => now(), 'marketplace_version' => $business->marketplace_version + 1])->save();
            app(AuditWriter::class)->write('marketplace.listing.updated', $business, target: $business, before: $before, after: ['listed' => $data['listed']], metadata: ['consent_version' => 'marketplace-listing-2026-11-08']);
        });

        return back()->with('marketplace_status', $data['listed'] ? 'Your business is listed on Marketplace.' : 'Your business is hidden from Marketplace. Your direct booking link still works.');
    }

    public function publication(Request $request, Business $business)
    {
        abort_unless(config('marketplace-growth.enabled') && app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);
        $data = $request->validate(['version' => ['required', 'integer'], 'consent' => ['accepted']]);
        DB::transaction(function () use ($business, $data) {
            $locked = Business::query()->lockForUpdate()->findOrFail($business->id);
            if ((int) $locked->marketplace_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['publication' => 'Your business changed. Refresh listing details before requesting publication.']);
            }
            app(PublicSupplyProjection::class)->rebuild($locked->id);
            app(SeoPageRegistry::class)->requestProfile($locked);
            app(AuditWriter::class)->write('marketplace.seo.requested', $locked, target: $locked, metadata: ['consent_version' => 'local-search-publication-2026-11-08']);
        });

        return back()->with('marketplace_status', 'Your public profile is up to date.');
    }

    private function publicationPanel(Business $business): array
    {
        $page = DB::table('marketplace_seo_pages')->where('business_id', $business->id)->where('kind', 'profile')->first();
        $work = DB::table('marketplace_projection_work')->where('business_id', $business->id)->first();
        $candidate = $page ?: (object) ['path' => '/marketplace/salons/'.$business->booking_slug, 'kind' => 'profile', 'business_id' => $business->id, 'geography_id' => null, 'term' => null, 'title' => $business->name.' — services & appointments', 'context' => null];
        $ready = app(SeoPageRegistry::class)->readiness($candidate);

        return [
            'ready' => $ready['ready'],
            'area_available' => DB::table('marketplace_geographies')->where('country', strtolower($business->country_code))->exists(),
            'status' => $page?->status ?? 'draft',
            'approved' => $page && app(SeoPageRegistry::class)->approved($page),
            'search_enabled' => $page && app(SeoPageRegistry::class)->indexable($page),
            'updating' => $work && $work->generation !== $work->projected_generation,
            'support_reference' => $work?->failure_reference,
            'request_url' => route('business.marketplace.publication', $business),
            'report_url' => route('business.marketplace.outcomes', $business),
            'services_url' => route('business.services.index', $business),
            'locations_url' => route('business.locations.index', $business),
        ];
    }

    public function preview(Business $business)
    {
        abort_unless(app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);

        return app(SalonController::class)->render($business, true);
    }

    public function thread(Business $business, string $appointment, BookingMessages $messages)
    {
        $booking = $this->booking($business, $appointment);
        $before = request()->validate(['before' => ['nullable', 'ulid']])['before'] ?? null;
        $thread = $messages->thread($booking, 'business', $before);

        return Inertia::render('Marketplace/Conversation', [
            'businessLabel' => $business->name, 'businessView' => true, 'inbox' => $this->inbox(request(), $business),
            'booking' => app(CustomerAccountController::class)->summary($booking),
            'customerName' => $booking->customer->name ?: $booking->appointment->client_name,
            'messages' => $thread['items'], 'olderUrl' => $thread['next'] ? request()->fullUrlWithQuery(['before' => $thread['next']]) : null, 'paused' => $booking->messages_paused,
            'postUrl' => route('business.marketplace.message', [$business, $appointment]),
            'backUrl' => route('business.marketplace.index', $business),
            'canReply' => Gate::allows('update', $booking->appointment),
        ]);
    }

    public function message(Request $request, Business $business, string $appointment, BookingMessages $messages)
    {
        $booking = $this->booking($business, $appointment);
        Gate::authorize('update', $booking->appointment);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000'], 'idempotency_key' => ['required', 'uuid']]);
        $messages->send($booking, 'business', trim($data['body']), $data['idempotency_key'], $request->user()->id);

        return redirect()->route('business.marketplace.thread', [$business, $appointment])->with('marketplace_status', 'Message sent.');
    }

    private function booking(Business $business, string $appointment): CustomerBooking
    {
        abort_unless(app(TenantContext::class)->membership()->hasPermissionTo(PermissionName::ClientContactView->value, 'web'), 403);
        $booking = CustomerBooking::query()->where('business_id', $business->id)->whereHas('appointment', fn ($q) => $q->where('public_id', $appointment))->with(['appointment.business', 'appointment.serviceLines', 'customer'])->firstOrFail();
        Gate::authorize('view', $booking->appointment);

        return $booking;
    }
}
