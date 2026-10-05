<?php

namespace App\Providers;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Providers\StripeSubscriptionProvider;
use App\Domain\BusinessConfiguration\Contracts\AppointmentImpactSource;
use App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration;
use App\Domain\BusinessConfiguration\Services\AvailabilityConfigurationService;
use App\Domain\ClientRecords\Contracts\ClientIdentityLinker;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\Communications\Contracts\EmailChannelProvider;
use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Providers\ConfiguredMobileChannelProvider;
use App\Domain\Communications\Providers\SesEmailProvider;
use App\Domain\MoneyCommerce\Contracts\AppointmentPaymentProvider;
use App\Domain\MoneyCommerce\Providers\StripeAppointmentPaymentProvider;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\AvailabilityQuery;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldCommand;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldExpiryCommand;
use App\Domain\SchedulingOperations\Services\AppointmentLifecycleService;
use App\Domain\SchedulingOperations\Services\AtomicBookingService;
use App\Domain\SchedulingOperations\Services\AvailabilitySearchService;
use App\Domain\SchedulingOperations\Services\CalendarQueryService;
use App\Domain\SchedulingOperations\Services\SchedulingAppointmentImpactSource;
use App\Support\Tenancy\TenantContext;
use Aws\Ses\SesClient;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use LemonSqueezy\Laravel\LemonSqueezy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();
        LemonSqueezy::ignoreRoutes();
        $this->app->singleton(TenantContext::class);
        $this->app->bind(SubscriptionProvider::class, StripeSubscriptionProvider::class);
        $this->app->bind(AvailabilityConfiguration::class, AvailabilityConfigurationService::class);
        $this->app->bind(AppointmentImpactSource::class, SchedulingAppointmentImpactSource::class);
        $this->app->bind(ClientIdentityLinker::class, ClientIdentityService::class);
        $this->app->singleton(SesClient::class, function () {
            $options = ['version' => '2010-12-01', 'region' => config('services.ses.region'), 'retries' => 0, 'http' => ['connect_timeout' => 5, 'timeout' => 10]];
            if (filled(config('services.ses.key')) && filled(config('services.ses.secret'))) {
                $options['credentials'] = array_filter(['key' => config('services.ses.key'), 'secret' => config('services.ses.secret'), 'token' => config('services.ses.token')]);
            }

            return new SesClient($options);
        });
        $this->app->bind(EmailChannelProvider::class, SesEmailProvider::class);
        $this->app->bind(MobileChannelProvider::class, ConfiguredMobileChannelProvider::class);
        $this->app->bind(AppointmentPaymentProvider::class, StripeAppointmentPaymentProvider::class);
        $this->app->bind(AvailabilityQuery::class, AvailabilitySearchService::class);
        $this->app->bind(AppointmentLifecycleCommand::class, AppointmentLifecycleService::class);
        $this->app->bind(BookingCommitCommand::class, AtomicBookingService::class);
        $this->app->bind(CalendarQuery::class, CalendarQueryService::class);
        $this->app->bind(CapacityHoldCommand::class, AtomicBookingService::class);
        $this->app->bind(CapacityHoldExpiryCommand::class, AtomicBookingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sensitive models use explicit fillable attributes. Global unguarding is intentionally disabled.
    }
}
