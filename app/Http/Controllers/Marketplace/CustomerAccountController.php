<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\Marketplace\Models\Message;
use App\Domain\Marketplace\Services\BookingMessages;
use App\Domain\Marketplace\Services\BookingOwnership;
use App\Domain\Marketplace\Services\MarketplaceListings;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Http\Controllers\Controller;
use App\Rules\E164Phone;
use App\Support\Audit\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CustomerAccountController extends Controller
{
    public function index(Request $request)
    {
        $customer = Auth::guard('marketplace')->user();
        app(BookingOwnership::class)->synchronize($customer);
        $data = $request->validate(['view' => ['nullable', 'in:upcoming,history'], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $view = $data['view'] ?? 'upcoming';
        $bookings = CustomerBooking::query()->where('customer_id', $customer->id)
            ->whereHas('appointment', fn ($q) => $q->whereRaw('LOWER(TRIM(client_email)) = ?', [$customer->email]))
            ->whereHas('appointment', function ($q) use ($view) {
                if ($view === 'upcoming') {
                    $q->where('starts_at_utc', '>=', now())->whereNotIn('status', ['rescheduled', 'completed', 'cancelled_by_shop', 'cancelled_by_client', 'no_show']);
                } else {
                    $q->where(fn ($q) => $q->where('starts_at_utc', '<', now())->orWhereIn('status', ['rescheduled', 'completed', 'cancelled_by_shop', 'cancelled_by_client', 'no_show']));
                }
            })
            ->with(['appointment.business', 'appointment.location', 'appointment.serviceLines'])
            ->orderBy(Appointment::query()->select('starts_at_utc')->whereColumn('appointments.id', 'marketplace_bookings.appointment_id'), $view === 'upcoming' ? 'asc' : 'desc')->orderByDesc('id')->paginate(20)->withQueryString()->through(fn ($b) => $this->summary($b));
        $favourites = app(MarketplaceListings::class)->query()->whereIn('id', DB::table('marketplace_favourites')->where('customer_id', $customer->id)->select('business_id'))->orderBy('name')->get(['name', 'booking_slug'])->map(fn ($b) => ['name' => $b->name, 'url' => '/marketplace/salons/'.$b->booking_slug]);

        return Inertia::render('Marketplace/Account', ['bookings' => $bookings, 'favourites' => $favourites, 'view' => $view]);
    }

    public function inbox(Request $request)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $customer = Auth::guard('marketplace')->user();
        app(BookingOwnership::class)->synchronize($customer);
        $threads = CustomerBooking::query()->where('customer_id', $customer->id)
            ->whereHas('appointment', fn ($q) => $q->whereRaw('LOWER(TRIM(client_email)) = ?', [$customer->email]))
            ->with(['appointment.business', 'appointment.location', 'appointment.serviceLines'])
            ->orderByDesc(Message::query()->selectRaw('MAX(id)')->whereColumn('booking_id', 'marketplace_bookings.id'))
            ->orderByDesc('id')->paginate(20)->through(fn ($b) => $this->summary($b));

        return Inertia::render('Marketplace/Inbox', ['threads' => $threads]);
    }

    public function showProfile()
    {
        return Inertia::render('Marketplace/Profile', ['profile' => [...Auth::guard('marketplace')->user()->only(['name', 'email', 'phone']), 'photo_url' => Auth::guard('marketplace')->user()->photoUrl()]]);
    }

    public function savePhoto(Request $request, \App\Domain\Marketplace\Services\CustomerPhoto $photos)
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']], [
            'photo.required' => 'Choose a photo first.',
            'photo.image' => 'Choose a JPEG, PNG or WebP image. Your current photo is kept.',
            'photo.mimes' => 'Choose a JPEG, PNG or WebP image. Your current photo is kept.',
            'photo.max' => 'Choose a photo up to 5 MB. Your current photo is kept.',
        ]);
        $photos->save(Auth::guard('marketplace')->user(), $request->file('photo'));
        return redirect()->route('marketplace.profile')->with('marketplace_status', 'Your profile photo has been saved.');
    }

    public function removePhoto(\App\Domain\Marketplace\Services\CustomerPhoto $photos)
    {
        $photos->save(Auth::guard('marketplace')->user(), null);
        return redirect()->route('marketplace.profile')->with('marketplace_status', 'Your profile photo has been removed.');
    }

    public function photo()
    {
        $path = Auth::guard('marketplace')->user()->photo_path;
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('private')->exists($path), 404);
        return response(\Illuminate\Support\Facades\Storage::disk('private')->get($path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }

    public function profile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:32', new E164Phone]]);
        Auth::guard('marketplace')->user()->update(['name' => trim($data['name']), 'phone' => $data['phone'] ?? null]);

        return redirect()->route('marketplace.profile')->with('marketplace_status', 'Your profile has been updated.');
    }

    public function claim(Request $request, SecureAppointmentLinkService $links, BookingOwnership $ownership)
    {
        $data = $request->validate(['booking_link' => ['required', 'string', 'max:2000']]);
        $path = parse_url($data['booking_link'], PHP_URL_PATH) ?: $data['booking_link'];
        if (! preg_match('~(?:^|/)([a-f0-9]{64})$~', $path, $matches)) {
            return back()->withErrors(['booking_link' => 'Paste the private appointment link from your booking confirmation.']);
        }
        $link = $links->resolve($matches[1], 'view');
        $ownership->attach(Auth::guard('marketplace')->user(), $link->appointment);

        return back()->with('marketplace_status', 'Your booking is now in your account.');
    }

    public function manage(string $appointment, SecureAppointmentLinkService $links)
    {
        $booking = $this->owned($appointment);

        return redirect()->route('public.appointment.view', $links->issue($booking->appointment, 'view')['token']);
    }

    public function conversation(string $appointment, BookingMessages $messages)
    {
        $booking = $this->owned($appointment);
        $before = request()->validate(['before' => ['nullable', 'ulid']])['before'] ?? null;
        $thread = $messages->thread($booking, 'customer', $before);

        return Inertia::render('Marketplace/Conversation', ['booking' => $this->summary($booking), 'messages' => $thread['items'], 'olderUrl' => $thread['next'] ? request()->fullUrlWithQuery(['before' => $thread['next']]) : null, 'paused' => $booking->messages_paused]);
    }

    public function message(Request $request, string $appointment, BookingMessages $messages)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000'], 'idempotency_key' => ['required', 'uuid']]);
        $messages->send($this->owned($appointment), 'customer', trim($data['body']), $data['idempotency_key']);

        return redirect('/marketplace/account/bookings/'.$appointment.'/messages')->with('marketplace_status', 'Message sent.');
    }

    public function pause(Request $request, string $appointment)
    {
        $data = $request->validate(['paused' => ['required', 'boolean']]);
        DB::transaction(function () use ($appointment, $data) {
            $booking = $this->owned($appointment);
            CustomerBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail()->update(['messages_paused' => $data['paused']]);
            app(AuditWriter::class)->write('marketplace.messages.preference_changed', $booking->appointment->business, target: $booking, after: $data, source: 'marketplace_customer');
        });

        return back()->with('marketplace_status', $data['paused'] ? 'Messages are paused for this booking.' : 'Messages are open for this booking.');
    }

    public function report(Request $request, string $appointment)
    {
        $data = $request->validate(['reason' => ['required', 'in:unwanted_messages,booking_problem,review_concern,other'], 'details' => ['required', 'string', 'max:4000']]);
        $booking = $this->owned($appointment);
        $reference = (string) Str::ulid();
        DB::transaction(function () use ($booking, $data, $reference) {
            DB::table('marketplace_reports')->insert(['public_id' => $reference, 'customer_id' => $booking->customer_id, 'business_id' => $booking->business_id, 'booking_id' => $booking->id, 'reason' => $data['reason'], 'details' => Crypt::encryptString($data['details']), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            app(AuditWriter::class)->write('marketplace.concern.reported', $booking->appointment->business, target: $booking, metadata: ['reference' => $reference, 'reason' => $data['reason']], source: 'marketplace_customer');
        });

        return back()->with('marketplace_status', 'Your concern is saved. Contact '.config('brand.support_email').' with reference '.$reference.' for help.');
    }

    public function favourite(Request $request, string $slug, MarketplaceListings $listings)
    {
        $data = $request->validate(['saved' => ['required', 'boolean']]);
        $business = $listings->resolve($slug);
        $key = ['customer_id' => Auth::guard('marketplace')->id(), 'business_id' => $business->id];
        if ($data['saved']) {
            DB::table('marketplace_favourites')->insertOrIgnore([...$key, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('marketplace_favourites')->where($key)->delete();
        }

        return back()->with('marketplace_status', $data['saved'] ? 'Salon saved to your favourites.' : 'Salon removed from your favourites.');
    }

    private function owned(string $publicId): CustomerBooking
    {
        $customer = Auth::guard('marketplace')->user();
        app(BookingOwnership::class)->synchronize($customer);

        return CustomerBooking::query()->where('customer_id', $customer->id)
            ->whereHas('appointment', fn ($q) => $q->where('public_id', $publicId)->whereRaw('LOWER(TRIM(client_email)) = ?', [$customer->email]))
            ->with(['appointment.business', 'appointment.location', 'appointment.serviceLines'])->firstOrFail();
    }

    public function summary(CustomerBooking $booking): array
    {
        $a = $booking->appointment;

        return [
            'id' => $a->public_id, 'reference' => $a->booking_reference, 'salon' => $a->business->name,
            'salon_url' => '/marketplace/salons/'.$a->business->booking_slug,
            'starts_at' => $a->starts_at_utc->toIso8601String(), 'time_zone' => $a->time_zone,
            'status' => $a->status, 'services' => $a->serviceLines->pluck('name')->all(),
            'price_minor' => $a->price_minor, 'currency' => $a->currency_code,
            'address' => $a->location_snapshot['address'] ?? $a->location?->address,
            'unread' => Message::query()->where('booking_id', $booking->id)->where('sender', 'business')->whereNull('read_at')->count(),
        ];
    }
}
