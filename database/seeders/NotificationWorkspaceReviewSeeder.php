<?php

namespace Database\Seeders;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\CommunicationConsentService;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Offline synthetic evidence. Never dispatches a message or calls a provider. */
class NotificationWorkspaceReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains(config('database.connections.sqlite.database'), 'notification-workspace-review')) {
            throw new \RuntimeException('Use an isolated notification-workspace-review SQLite database.');
        }
        $this->call(GoodHoursDemoSeeder::class);
        $owner = User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
        if (! Hash::check('NotificationsReview-LocalOnly-2026', $owner->password)) {
            $owner->update(['password' => Hash::make('NotificationsReview-LocalOnly-2026')]);
        }
        $business = Business::query()->where('slug', 'good-hours-demo-tenant')->firstOrFail();
        $client = Client::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Sarah Alexandra Montgomery-Wellington', 'email' => 'sarah@example.test', 'mobile' => '+14155552671']);
        $start = CarbonImmutable::parse('2026-10-10 14:30', $business->time_zone);
        $appointment = Appointment::query()->firstOrCreate(['business_id' => $business->id, 'idempotency_key' => 'notification-workspace-review-appointment'], ['location_id' => $business->locations()->firstOrFail()->id, 'client_id' => $client->id, 'request_hash' => hash('sha256', 'notification-workspace-review-appointment'), 'booking_reference' => 'BK-1042', 'status' => 'confirmed', 'source' => 'phone', 'client_name' => $client->name, 'client_email' => $client->email, 'client_mobile' => $client->mobile, 'starts_at_utc' => $start->utc(), 'ends_at_utc' => $start->addHour()->utc(), 'time_zone' => $business->time_zone, 'local_starts_at' => $start->format('Y-m-d H:i:s'), 'local_ends_at' => $start->addHour()->format('Y-m-d H:i:s'), 'price_minor' => 75000, 'currency_code' => $business->currency_code]);
        $staff = StaffProfile::query()->where('business_id', $business->id)->firstOrFail();
        $line = $appointment->serviceLines()->firstOrCreate(['sequence' => 1], ['business_id' => $business->id, 'service_id' => Service::query()->where('business_id', $business->id)->firstOrFail()->id, 'name' => 'Signature cut', 'primary_staff_profile_id' => $staff->id, 'bookable_minutes' => 60, 'price_minor' => 75000, 'currency_code' => $business->currency_code, 'configuration_snapshot' => []]);
        $appointment->segments()->firstOrCreate(['sequence' => 1], ['business_id' => $business->id, 'appointment_service_line_id' => $line->id, 'staff_profile_id' => $staff->id, 'kind' => 'service', 'starts_at_utc' => $start->utc(), 'ends_at_utc' => $start->addHour()->utc(), 'time_zone' => $business->time_zone, 'local_starts_at' => $start->format('Y-m-d H:i:s'), 'local_ends_at' => $start->addHour()->format('Y-m-d H:i:s'), 'occupies_staff' => true]);
        $names = ['booking_confirmation', 'appointment_reminder', 'appointment_changed', 'payment_receipt', 'queue_update', 'waitlist_opening'];
        $states = ['sent', 'delivered', 'failed', 'suppressed', 'retried', 'queued'];
        for ($i = 0; $i < 33; $i++) {
            $state = $states[$i % 6];
            $intent = CommunicationIntent::query()->firstOrCreate(['business_id' => $business->id, 'event_key' => 'notification-review-'.$i], ['business_id' => $business->id, 'client_id' => $client->id, 'event_type' => 'review.fixture', 'event_key' => 'notification-review-'.$i, 'intent_type' => $names[$i % 6], 'category' => 'transactional', 'legal_basis' => 'contract_performance', 'locale' => 'en-US', 'time_zone' => $business->time_zone, 'scheduled_for_utc' => now()->addHours($i + 1), 'local_scheduled_for' => now()->addHours($i + 1)->setTimezone($business->time_zone)->format('Y-m-d H:i:s P'), 'status' => $state, 'correlation_id' => (string) str()->uuid()]);
            if ($i % 6 < 4) {
                $intent->update(['source_type' => Appointment::class, 'source_id' => $appointment->id]);
            }
            CommunicationMessage::query()->firstOrCreate(['idempotency_key' => hash('sha256', 'review-message-'.$i)], ['business_id' => $business->id, 'communication_template_id' => app(CommunicationTemplateService::class)->defaultTemplate($business->id, $names[$i % 6], $i % 2 ? 'sms' : 'email')->id, 'communication_intent_id' => $intent->id, 'client_id' => $client->id, 'channel' => $i % 2 ? 'sms' : 'email', 'recipient' => $i % 2 ? $client->mobile : $client->email, 'recipient_hash' => CommunicationConsentService::destinationHash($i % 2 ? 'sms' : 'email', $i % 2 ? $client->mobile : $client->email), 'idempotency_key' => hash('sha256', 'review-message-'.$i), 'category' => 'transactional', 'legal_basis' => 'contract_performance', 'locale' => 'en-US', 'time_zone' => $business->time_zone, 'template_variables' => ['client_name' => $client->name, 'booking_reference' => 'BK-'.(1042 + $i), 'location_name' => 'Main studio'], 'status' => $state, 'provider' => $i % 4 === 0 ? 'fake' : ($i % 2 ? 'twilio' : 'resend'), 'attempt_count' => in_array($state, ['queued', 'suppressed']) ? 0 : 1, 'last_error_code' => $state === 'failed' ? 'provider_not_configured' : null, 'suppression_reason' => $state === 'suppressed' ? 'destination_suppressed' : null, 'queued_at' => now()->subMinutes($i * 10), 'created_at' => now()->subMinutes($i * 10), 'sent_at' => in_array($state, ['sent', 'delivered']) ? now()->subMinutes($i * 10) : null, 'delivered_at' => $state === 'delivered' ? now()->subMinutes($i * 10)->addSeconds(3) : null, 'failed_at' => $state === 'failed' ? now()->subMinutes($i * 10) : null, 'next_attempt_at' => in_array($state, ['queued', 'retried']) ? now()->addHours($i + 1) : null]);
        }
        $this->command?->info('Notifications review business: '.$business->public_id);
    }
}
