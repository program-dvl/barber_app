<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\PublicBooking\Services\WaitlistService;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicWaitlistController extends Controller
{
    public function store(Request $request, string $slug, BookingSlugManager $slugs, WaitlistService $waitlist): RedirectResponse
    {
        $business = $slugs->resolve($slug);
        abort_unless($business && $business->booking_slug === $slug && $business->online_booking_enabled, 404);
        $data = $request->validate([
            'location' => ['required', 'string'], 'service' => ['required', 'string'], 'staff' => ['nullable', 'string'],
            'client_name' => ['required', 'string', 'max:255'], 'client_mobile' => ['required', 'string', 'max:32'], 'client_email' => ['nullable', 'email'],
            'acceptable_from' => ['required', 'date', 'after_or_equal:today'], 'acceptable_until' => ['required', 'date', 'after_or_equal:acceptable_from'],
            'time_from' => ['required', 'date_format:H:i'], 'time_until' => ['required', 'date_format:H:i', 'after:time_from'],
            'notification_method' => ['required', 'in:email,whatsapp'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->where('is_active', true)->where('status', 'active')->firstOrFail();
        $service = Service::query()->where('business_id', $business->id)->where('public_id', $data['service'])->where('is_active', true)->where('online_visible', true)->firstOrFail();
        $staff = ! empty($data['staff']) ? StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['staff'])->where('status', 'active')->where('online_visible', true)->firstOrFail() : null;
        $entry = $waitlist->create($business, $location, $service, $staff, $data);

        return back()->with('status', $entry->wasRecentlyCreated ? 'You are on the waitlist.' : 'That waitlist request is already active.');
    }

    public function offer(string $token, WaitlistService $waitlist): Response
    {
        $match = $waitlist->resolveClaim($token);

        return Inertia::render('Booking/WaitlistOffer', [
            'token' => $token, 'status' => $match->status, 'expires_at' => $match->expires_at->toIso8601String(),
            'business' => $match->request->business->name, 'location' => $match->request->location->name,
            'service' => $match->request->service->name,
            'starts_at' => $match->slot_starts_at_utc->setTimezone($match->request->location->time_zone)->toIso8601String(),
        ]);
    }

    public function claim(string $token, WaitlistService $waitlist, SecureAppointmentLinkService $links): RedirectResponse
    {
        try {
            $appointment = $waitlist->claim($token);
            $view = $links->issue($appointment->loadMissing('business'), 'view');

            return redirect()->route('public.appointment.view', $view['token'])->with('status', 'The opening is yours. Your appointment is confirmed.');
        } catch (BookingRuleViolation $error) {
            return back()->withErrors(['waitlist' => $error->getMessage()]);
        }
    }
}
