<?php

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Http\Controllers\Access\StaffInvitationController;
use App\Http\Controllers\Billing\BusinessBillingController;
use App\Http\Controllers\Billing\PaddleWebhookController;
use App\Http\Controllers\Billing\PlatformBillingSupportController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ClientAttachmentDownloadController;
use App\Http\Controllers\ClientFormPublicController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\CommunicationActionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Invoices\DownloadInvoiceController;
use App\Http\Controllers\Marketing\FeatureController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\Marketing\ResourceController;
use App\Http\Controllers\Marketing\SolutionController;
use App\Http\Controllers\Marketing\TrustController;
use App\Http\Controllers\Marketing\UseCaseController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\Platform\PlatformOperationsController;
use App\Http\Controllers\Platform\SupportAccessController;
use App\Http\Controllers\PublicBooking\BookingBusinessController;
use App\Http\Controllers\PublicBooking\PublicAppointmentController;
use App\Http\Controllers\PublicBooking\PublicBookingFlowController;
use App\Http\Controllers\PublicBooking\PublicBookingPaymentController;
use App\Http\Controllers\PublicBooking\PublicWaitlistController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\Shop\AppointmentOperationsController;
use App\Http\Controllers\Shop\BusinessConfigurationController;
use App\Http\Controllers\Shop\CalendarController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ClientRecords\ClientAttachmentController;
use App\Http\Controllers\Shop\ClientRecords\ClientController;
use App\Http\Controllers\Shop\ClientRecords\ClientDuplicateController;
use App\Http\Controllers\Shop\ClientRecords\ClientFormController;
use App\Http\Controllers\Shop\ClientRecords\ClientNoteController;
use App\Http\Controllers\Shop\ClientRecords\ClientPrivacyController;
use App\Http\Controllers\Shop\CommissionController;
use App\Http\Controllers\Shop\CommunicationSettingsController;
use App\Http\Controllers\Shop\DashboardController;
use App\Http\Controllers\Shop\DashboardRedirectController;
use App\Http\Controllers\Shop\InventoryController;
use App\Http\Controllers\Shop\OperationalExceptionController;
use App\Http\Controllers\Shop\PrintDailyScheduleController;
use App\Http\Controllers\Shop\ReportController;
use App\Http\Controllers\Shop\ScheduleBlockController;
use App\Http\Controllers\Shop\WalkInQueueController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhooks\AppointmentPaymentWebhookController;
use App\Http\Controllers\Webhooks\CommunicationWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$shopModules = [
    'calendar' => [
        'label' => 'Calendar',
        'description' => 'Coordinate appointments, staff availability, and physical resources with explicit local-time context.',
        'requirements' => 'FR-06 and FR-07',
        'nonGoal' => 'No appointment, availability, capacity, or rescheduling behavior is included in this shell delivery.',
    ],
    'walk-in-queue' => [
        'label' => 'Walk-in Queue',
        'description' => 'Keep front-desk arrivals and waiting clients visible on touch-friendly screens.',
        'requirements' => 'FR-08',
        'nonGoal' => 'No waiting-time calculation, assignment, or appointment conversion is included in this shell delivery.',
    ],
    'checkout-sales' => [
        'label' => 'Checkout / Sales',
        'description' => 'Close completed services with traceable totals, tenders, receipts, and adjustments.',
        'requirements' => 'FR-14, FR-15, and FR-17',
        'nonGoal' => 'No cart, payment, refund, tax, tip, deposit, commission, or receipt behavior is included in this shell delivery.',
    ],
    'staff' => [
        'label' => 'Staff',
        'description' => 'Prepare for schedulable staff profiles, location assignments, working hours, roles, and permissions.',
        'requirements' => 'FR-05',
        'nonGoal' => 'No staff profile, membership, invitation, role, schedule, or authorization policy is included in this shell delivery.',
    ],
    'services' => [
        'label' => 'Services',
        'description' => 'Prepare the service catalogue, staff variants, durations, prices, add-ons, and resource needs.',
        'requirements' => 'FR-04',
        'nonGoal' => 'No catalogue, price, duration, add-on, or resource configuration behavior is included in this shell delivery.',
    ],
    'inventory' => [
        'label' => 'Inventory',
        'description' => 'Prepare lightweight retail stock visibility with durable movement history.',
        'requirements' => 'FR-16',
        'nonGoal' => 'No product, stock movement, low-stock, retail sale, or adjustment behavior is included in this shell delivery.',
    ],
    'reports' => [
        'label' => 'Reports',
        'description' => 'Prepare operational, sales, tax, payment, staff, and export surfaces with clear filters.',
        'requirements' => 'FR-18',
        'nonGoal' => 'No metrics, projections, financial totals, reconciliation, or exports are included in this shell delivery.',
    ],
    'settings' => [
        'label' => 'Settings',
        'description' => 'Prepare business, location, policy, notification, security, and audit settings.',
        'requirements' => 'FR-02, FR-03, and FR-19',
        'nonGoal' => 'No business, location, policy, provider, audit, or security configuration behavior is included in this shell delivery.',
    ],
    'subscription-billing' => [
        'label' => 'Subscription & Billing',
        'description' => 'Prepare business-owned SaaS billing and entitlement visibility separately from appointment payments.',
        'requirements' => 'FR-01',
        'nonGoal' => 'No plan selection, subscription provider, invoice, trial, entitlement, or billing lifecycle is included in this shell delivery.',
    ],
];

$platformModules = [
    'businesses' => ['label' => 'Businesses', 'description' => 'Tenant lifecycle and safe business search.'],
    'subscriptions' => ['label' => 'Subscriptions', 'description' => 'Normalized subscription state and recovery operations.'],
    'plans-entitlements' => ['label' => 'Plans & entitlements', 'description' => 'Server-enforced capabilities and numeric limits independent of plan names.'],
    'payments-invoices' => ['label' => 'Payments & invoices', 'description' => 'SaaS billing evidence and provider reconciliation.'],
    'coupons' => ['label' => 'Coupons', 'description' => 'Commercial discount configuration and audit history.'],
    'support-access' => ['label' => 'Support access', 'description' => 'Reasoned, scoped, expiring, visible, and audited tenant support grants.'],
    'notification-logs' => ['label' => 'Notification logs', 'description' => 'Tenant-safe delivery attempts, failures, and replay evidence.'],
    'system-health' => ['label' => 'System health', 'description' => 'Queue, webhook, provider, backup, and service health.'],
    'feature-flags' => ['label' => 'Feature flags', 'description' => 'Explicit, monitored platform capability controls.'],
    'audit-logs' => ['label' => 'Audit logs', 'description' => 'Immutable evidence for sensitive platform operations.'],
];

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('marketing.home');
Route::get('/features', [FeatureController::class, 'index'])->name('marketing.features');
Route::get('/pricing', PricingController::class)->name('marketing.pricing');
Route::get('/company', [TrustController::class, 'company'])->name('marketing.company');
Route::get('/security', [TrustController::class, 'security'])->name('marketing.security');
Route::get('/resources', [ResourceController::class, 'index'])->name('marketing.resources');
Route::get('/guides/{guide}', [ResourceController::class, 'guide'])->where('guide', '[a-z0-9-]+')->name('marketing.guides.show');
Route::get('/features/{feature}', [FeatureController::class, 'show'])
    ->where('feature', '[a-z0-9-]+')
    ->name('marketing.features.show');
Route::get('/solutions', [SolutionController::class, 'index'])->name('marketing.solutions');
Route::get('/solutions/{solution}', [SolutionController::class, 'show'])
    ->where('solution', '[a-z0-9-]+')
    ->name('marketing.solutions.show');
Route::get('/use-cases', [UseCaseController::class, 'index'])->name('marketing.use-cases');
Route::get('/use-cases/{useCase}', [UseCaseController::class, 'show'])
    ->where('useCase', '[a-z0-9-]+')
    ->name('marketing.use-cases.show');
Route::get('/book', fn () => Inertia::render('Booking/Welcome'))->name('booking.welcome');
Route::get('/book/{slug}', BookingBusinessController::class)->where('slug', '[a-z0-9-]+')->name('booking.business');
Route::get('/manage-appointment', fn () => Inertia::render('Booking/Manage'))->name('booking.manage');
Route::middleware('throttle:30,1')->prefix('/book/{slug}')->where(['slug' => '[a-z0-9-]+'])->group(function (): void {
    Route::post('/flows', [PublicBookingFlowController::class, 'start'])->name('public.booking.start');
    Route::post('/availability', [PublicBookingFlowController::class, 'search'])->name('public.booking.search');
});
Route::middleware('throttle:10,1')->prefix('/book/{slug}')->where(['slug' => '[a-z0-9-]+'])->group(function (): void {
    Route::post('/hold', [PublicBookingFlowController::class, 'hold'])->name('public.booking.hold');
    Route::post('/confirm', [PublicBookingFlowController::class, 'confirm'])->name('public.booking.confirm');
    Route::post('/deposit-payment', [PublicBookingPaymentController::class, 'store'])->name('public.booking.deposit-payment');
    Route::post('/waitlist', [PublicWaitlistController::class, 'store'])->name('public.waitlist.store');
});
Route::middleware('throttle:20,1')->where(['token' => '[a-f0-9]{64}', 'purpose' => 'reschedule|cancel|rebook|contact|waitlist|payment_status'])->group(function (): void {
    Route::get('/appointments/secure/{token}', [PublicAppointmentController::class, 'view'])->name('public.appointment.view');
    Route::get('/appointments/secure/{token}/calendar', [PublicAppointmentController::class, 'calendar'])->name('public.appointment.calendar');
    Route::get('/appointments/secure/{token}/{purpose}', [PublicAppointmentController::class, 'action'])->name('public.appointment.action');
    Route::post('/appointments/secure/{token}/{purpose}', [PublicAppointmentController::class, 'mutate'])->name('public.appointment.mutate');
    Route::get('/waitlist-offers/{token}', [PublicWaitlistController::class, 'offer'])->name('public.waitlist.offer');
    Route::post('/waitlist-offers/{token}/claim', [PublicWaitlistController::class, 'claim'])->name('public.waitlist.claim');
});
Route::middleware('throttle:20,1')->where(['token' => '[a-f0-9]{64}'])->group(function (): void {
    Route::get('/client-forms/secure/{token}', [ClientFormPublicController::class, 'view'])->name('client-forms.view');
    Route::post('/client-forms/secure/{token}', [ClientFormPublicController::class, 'submit'])->name('client-forms.submit');
    Route::get('/client-files/{token}', ClientAttachmentDownloadController::class)->name('client-attachments.download');
});
Route::get('/client-forms/completed', fn () => Inertia::render('Booking/ClientFormCompleted'))->name('client-forms.completed');
Route::get('/sitemap.xml', [SitemapController::class, 'xml'])->name('sitemap.xml');
Route::get('/sitemap', [SitemapController::class, 'legacy'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('changelog', [ChangelogController::class, 'index'])->name('changelog');

// Demo Coming Soon Page
Route::get('coming-soon', function () {
    return Inertia::render('ComingSoon');
})->name('coming-soon');

Route::post('coming-soon', [ComingSoonController::class, 'index'])->name('coming-soon.store');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{article:slug}', [BlogController::class, 'article'])->name('blog.article');

Route::get('/roadmap', [RoadmapController::class, 'index'])->name('roadmap.index');

if (app()->environment('local')) {
    Route::get('og-image/{title?}/{description?}', OgImageController::class)->name('og-image');
    Route::get('og-image-testing', fn () => view('seo.image', ['title' => 'Good Hours preview', 'description' => 'Local preview only.']));
}

Route::post('billing/webhooks/stripe', StripeWebhookController::class)->name('billing.webhooks.stripe');
Route::post('billing/webhooks/paddle', PaddleWebhookController::class)->name('billing.webhooks.paddle');
Route::post('payments/webhooks/stripe', AppointmentPaymentWebhookController::class)->name('payments.webhooks.stripe');
Route::post('communications/webhooks/resend', [CommunicationWebhookController::class, 'resend'])->name('communications.webhooks.resend');
Route::post('communications/webhooks/twilio', [CommunicationWebhookController::class, 'twilio'])->name('communications.webhooks.twilio');
Route::match(['get', 'post'], 'communications/actions/{link}', CommunicationActionController::class)->name('communications.action');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () use ($platformModules, $shopModules) {
    // Here goes your auth user endpoints
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');
    Route::get('/app/interface-patterns', fn () => Inertia::render('DesignSystem/Patterns'))
        ->name('design-system.patterns');

    Route::prefix('businesses/{business:public_id}')
        ->middleware('tenant')
        ->scopeBindings()
        ->group(function () use ($shopModules): void {
            Route::get('/dashboard', DashboardController::class)->name('business.dashboard');

            Route::get('/app/calendar', CalendarController::class)->name('business.calendar');
            Route::get('/app/calendar/print', PrintDailyScheduleController::class)->name('business.calendar.print');
            Route::post('/appointments', [AppointmentOperationsController::class, 'store'])->name('business.appointments.store');
            Route::patch('/appointments/{appointment}/status', [AppointmentOperationsController::class, 'transition'])->name('business.appointments.status');
            Route::post('/appointments/{appointment}/replace', [AppointmentOperationsController::class, 'replace'])->name('business.appointments.replace');
            Route::post('/appointments/{appointment}/copy', [AppointmentOperationsController::class, 'copy'])->name('business.appointments.copy');
            Route::patch('/appointments/{appointment}/notes', [AppointmentOperationsController::class, 'notes'])->name('business.appointments.notes');
            Route::post('/appointments/{appointment}/exceptions', [OperationalExceptionController::class, 'appointment'])->name('business.appointments.exceptions');
            Route::post('/schedule-blocks', ScheduleBlockController::class)->name('business.schedule-blocks.store');
            Route::post('/operational-exceptions/closure', [OperationalExceptionController::class, 'closure'])->name('business.operational-exceptions.closure');
            Route::get('/app/checkout-sales', [CheckoutController::class, 'index'])->name('business.checkout.index');
            Route::post('/appointments/{appointment}/checkout', [CheckoutController::class, 'open'])->name('business.checkout.open');
            Route::post('/sales/{sale}/tenders', [CheckoutController::class, 'tender'])->name('business.checkout.tender');
            Route::post('/sales/{sale}/payments/{payment}/refunds', [CheckoutController::class, 'refund'])->name('business.checkout.refund');
            Route::get('/sales/{sale}/receipt', [CheckoutController::class, 'receipt'])->name('business.checkout.receipt');
            Route::post('/locations/{location}/cash-close', [CheckoutController::class, 'close'])->name('business.cash-close.store');

            Route::get('/app/inventory', [InventoryController::class, 'index'])->name('business.inventory.index');
            Route::post('/inventory/products', [InventoryController::class, 'store'])->name('business.inventory.products.store');
            Route::post('/inventory/products/import', [InventoryController::class, 'import'])->name('business.inventory.import');
            Route::get('/inventory/products/export', [InventoryController::class, 'export'])->name('business.inventory.export');
            Route::post('/inventory/products/{product}/receipts', [InventoryController::class, 'receipt'])->name('business.inventory.receipts.store');
            Route::post('/inventory/products/{product}/adjustments', [InventoryController::class, 'adjustment'])->name('business.inventory.adjustments.store');

            Route::get('/app/reports', [ReportController::class, 'index'])->name('business.reports.index');
            Route::get('/app/reports/print', [ReportController::class, 'print'])->name('business.reports.print');
            Route::post('/report-exports', [ReportController::class, 'export'])->name('business.report-exports.store');
            Route::get('/report-exports/{reportExport}', [ReportController::class, 'exportStatus'])->name('business.report-exports.show');
            Route::get('/report-exports/{reportExport}/download', [ReportController::class, 'download'])->name('business.report-exports.download');
            Route::post('/commission-rules', [CommissionController::class, 'rule'])->name('business.commission-rules.store');
            Route::get('/staff/{staff}/statement', [CommissionController::class, 'statement'])->name('business.staff.statement');
            Route::post('/staff/{staff}/statement-adjustments', [CommissionController::class, 'adjust'])->name('business.staff.statement-adjustments.store');
            Route::post('/payroll-exports', [CommissionController::class, 'payroll'])->name('business.payroll-exports.store');

            Route::get('/app/walk-in-queue', [WalkInQueueController::class, 'index'])->name('business.walk-ins.index');
            Route::post('/walk-ins', [WalkInQueueController::class, 'store'])->name('business.walk-ins.store');
            Route::post('/walk-ins/reorder', [WalkInQueueController::class, 'reorder'])->name('business.walk-ins.reorder');
            Route::patch('/walk-ins/{walkIn}/assign', [WalkInQueueController::class, 'assign'])->name('business.walk-ins.assign');
            Route::post('/walk-ins/{walkIn}/notify', [WalkInQueueController::class, 'notify'])->name('business.walk-ins.notify');
            Route::post('/walk-ins/{walkIn}/start', [WalkInQueueController::class, 'start'])->name('business.walk-ins.start');
            Route::post('/walk-ins/{walkIn}/leave', [WalkInQueueController::class, 'leave'])->name('business.walk-ins.leave');

            Route::get('/app/clients', [ClientController::class, 'index'])->name('business.clients.index');
            Route::get('/app/clients/{client}', [ClientController::class, 'show'])->name('business.clients.show');
            Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('business.clients.update');
            Route::post('/clients/{client}/notes', [ClientNoteController::class, 'store'])->name('business.clients.notes.store');
            Route::post('/clients/{client}/attachments', [ClientAttachmentController::class, 'store'])->name('business.clients.attachments.store');
            Route::post('/clients/{client}/attachments/{attachment}/link', [ClientAttachmentController::class, 'link'])->name('business.clients.attachments.link');
            Route::get('/client-duplicates/{candidate}/preview', [ClientDuplicateController::class, 'preview'])->name('business.clients.duplicates.preview');
            Route::post('/client-duplicates/{candidate}/merge', [ClientDuplicateController::class, 'merge'])->name('business.clients.duplicates.merge');
            Route::post('/client-form-templates/publish', [ClientFormController::class, 'publish'])->name('business.clients.forms.publish');
            Route::post('/clients/{client}/form-requests', [ClientFormController::class, 'request'])->name('business.clients.forms.request');
            Route::post('/clients/{client}/privacy-requests', [ClientPrivacyController::class, 'store'])->name('business.clients.privacy.store');
            Route::post('/clients/{client}/privacy-requests/{privacyRequest}/process', [ClientPrivacyController::class, 'process'])->name('business.clients.privacy.process');

            Route::get('/communications/settings', [CommunicationSettingsController::class, 'index'])->name('business.communications.index');
            Route::patch('/communications/settings', [CommunicationSettingsController::class, 'update'])->name('business.communications.update');
            Route::post('/communications/templates', [CommunicationSettingsController::class, 'storeTemplate'])->name('business.communications.templates.store');
            Route::post('/communications/templates/{communicationTemplate}/preview', [CommunicationSettingsController::class, 'preview'])->name('business.communications.templates.preview');
            Route::post('/communications/templates/{communicationTemplate}/publish', [CommunicationSettingsController::class, 'publish'])->name('business.communications.templates.publish');
            Route::get('/communications/messages/{communicationMessage}', [CommunicationSettingsController::class, 'diagnostic'])->name('business.communications.messages.show');
            Route::post('/communications/messages/{communicationMessage}/replay', [CommunicationSettingsController::class, 'replay'])->name('business.communications.messages.replay');

            Route::get('/app/{module}', function (Business $business, string $module) use ($shopModules) {
                abort_unless(isset($shopModules[$module]), 404);

                return Inertia::render('Shop/ModulePlaceholder', [
                    'businessLabel' => $business->name,
                    'module' => $shopModules[$module],
                ]);
            })->where('module', implode('|', array_keys($shopModules)))->name('shop.module');

            Route::get('/locations/{location}', function (Business $business, Location $location) {
                return response()->json([
                    'public_id' => $location->public_id,
                    'business_public_id' => $business->public_id,
                    'name' => $location->name,
                    'time_zone' => $location->time_zone,
                ]);
            })->middleware('can:view,location')->name('business.locations.show');

            Route::post('/staff-invitations', [StaffInvitationController::class, 'store'])
                ->name('staff-invitations.store');
            Route::delete('/staff-invitations/{invitation}', [StaffInvitationController::class, 'destroy'])
                ->name('staff-invitations.destroy');

            Route::prefix('configuration')->name('business.configuration.')->group(function (): void {
                Route::get('/', [BusinessConfigurationController::class, 'show'])->name('show');
                Route::patch('/profile', [BusinessConfigurationController::class, 'updateProfile'])->name('profile.update');
                Route::post('/branding', [BusinessConfigurationController::class, 'uploadBrandAsset'])->name('branding.store');
                Route::patch('/public-booking-policy', [BusinessConfigurationController::class, 'updatePublicBookingPolicy'])->name('public-booking-policy.update');
                Route::put('/locations/{location}/hours', [BusinessConfigurationController::class, 'saveHours'])->name('locations.hours.update');
                Route::post('/locations/{location}/exceptions', [BusinessConfigurationController::class, 'storeLocationException'])->name('locations.exceptions.store');
                Route::post('/locations/{location}/resources', [BusinessConfigurationController::class, 'storeResource'])->name('locations.resources.store');
                Route::post('/first-bookable-path', [BusinessConfigurationController::class, 'createFirstBookablePath'])->name('first-bookable-path.store');
                Route::post('/services', [BusinessConfigurationController::class, 'storeService'])->name('services.store');
                Route::put('/staff/{staffProfile}/availability', [BusinessConfigurationController::class, 'saveStaffAvailability'])->name('staff.availability.update');
                Route::post('/imports/preview', [BusinessConfigurationController::class, 'previewImport'])->name('imports.preview');
                Route::get('/imports/templates/{entityType}', [BusinessConfigurationController::class, 'importTemplate'])->where('entityType', 'clients|staff|services|products')->name('imports.template');
                Route::post('/imports/{configurationImport}/commit', [BusinessConfigurationController::class, 'commitImport'])->name('imports.commit');
                Route::post('/change-previews/{subjectType}/{subjectId}', [BusinessConfigurationController::class, 'previewChange'])->where('subjectType', 'location|staff|resource')->name('change-previews.store');
                Route::post('/preview', [BusinessConfigurationController::class, 'preview'])->name('preview');
                Route::post('/publish', [BusinessConfigurationController::class, 'publish'])->name('publish');
            });

            Route::prefix('billing')->name('business.billing.')->group(function (): void {
                Route::get('/', [BusinessBillingController::class, 'show'])->name('show');
                Route::get('/checkout', [BusinessBillingController::class, 'checkoutForm'])->name('checkout.form');
                Route::post('/checkout/session', [BusinessBillingController::class, 'checkout'])
                    ->middleware('throttle:5,1')
                    ->name('checkout');
                Route::post('/checkout/confirm', [BusinessBillingController::class, 'confirmCheckout'])
                    ->middleware('throttle:30,1')
                    ->name('checkout.confirm');
                Route::post('/plan-change', [BusinessBillingController::class, 'changePlan'])->name('plan-change');
                Route::post('/cancel', [BusinessBillingController::class, 'cancel'])->name('cancel');
                Route::post('/reactivate', [BusinessBillingController::class, 'reactivate'])->name('reactivate');
                Route::get('/portal', [BusinessBillingController::class, 'portal'])->name('portal');
                Route::get('/invoices/{invoice}', [BusinessBillingController::class, 'invoice'])->name('invoices.show');
            });
        });

    Route::middleware('throttle:6,1')->where(['token' => '[A-Za-z0-9]{64}'])->group(function (): void {
        Route::get('/staff-invitations/{token}', [StaffInvitationController::class, 'show'])
            ->name('staff-invitations.show');
        Route::post('/staff-invitations/{token}/accept', [StaffInvitationController::class, 'accept'])
            ->name('staff-invitations.accept');
    });

    Route::middleware('platform.access')->prefix('platform')->name('platform.')->group(function () use ($platformModules) {
        Route::get('/', [PlatformOperationsController::class, 'overview'])->name('overview');
        Route::get('/businesses', [PlatformOperationsController::class, 'businesses'])->name('businesses.index');
        Route::get('/businesses/{business:public_id}', [PlatformOperationsController::class, 'business'])->name('businesses.show');
        Route::post('/businesses/{business:public_id}/status', [PlatformOperationsController::class, 'status'])->name('businesses.status');
        Route::post('/businesses/{business:public_id}/trial-extension', [PlatformOperationsController::class, 'extendTrial'])->name('businesses.trial-extension');
        Route::post('/businesses/{business:public_id}/plan-change', [PlatformOperationsController::class, 'changePlan'])->name('businesses.plan-change');
        Route::post('/businesses/{business:public_id}/coupon', [PlatformOperationsController::class, 'applyCoupon'])->name('businesses.coupon');
        Route::post('/businesses/{business:public_id}/exports', [PlatformOperationsController::class, 'initiateExport'])->name('businesses.exports');
        Route::post('/businesses/{business:public_id}/resend-verification', [PlatformOperationsController::class, 'resendVerification'])->name('businesses.resend-verification');
        Route::post('/businesses/{business:public_id}/invitations/{staffInvitation:public_id}/resend', [PlatformOperationsController::class, 'resendInvitation'])->name('businesses.invitations.resend');
        Route::post('/businesses/{business:public_id}/notes', [PlatformOperationsController::class, 'note'])->name('businesses.notes');
        Route::post('/businesses/{business:public_id}/billing/cancel', [PlatformBillingSupportController::class, 'cancel'])
            ->name('businesses.billing.cancel');
        Route::get('/failures', [PlatformOperationsController::class, 'failures'])->name('failures.index');
        Route::post('/failures/{type}/{id}/replay', [PlatformOperationsController::class, 'replay'])->whereNumber('id')->name('failures.replay');
        Route::get('/health', [PlatformOperationsController::class, 'health'])->name('health');
        Route::get('/feature-flags', [PlatformOperationsController::class, 'flags'])->name('feature-flags.index');
        Route::post('/feature-flags', [PlatformOperationsController::class, 'setFlag'])->name('feature-flags.store');
        Route::post('/notices', [PlatformOperationsController::class, 'publishNotice'])->name('notices.store');
        Route::get('/alerts', [PlatformOperationsController::class, 'alerts'])->name('alerts.index');
        Route::get('/audit-events', [PlatformOperationsController::class, 'auditEvents'])->name('audit-events.index');

        Route::get('/support-access', [SupportAccessController::class, 'index'])->name('support-access.index');
        Route::post('/businesses/{business:public_id}/support-access', [SupportAccessController::class, 'store'])->name('support-access.store');
        Route::post('/support-access/{grant:public_id}/enter', [SupportAccessController::class, 'enter'])->name('support-access.enter');
        Route::post('/support-access/{grant:public_id}/revoke', [SupportAccessController::class, 'revoke'])->name('support-access.revoke');
        Route::post('/support-sessions/{supportSession:public_id}/leave', [SupportAccessController::class, 'leave'])->name('support-access.leave');
        Route::get('/support/businesses/{business:public_id}', [SupportAccessController::class, 'business'])->name('support.businesses.show');
        Route::get('/support/businesses/{business:public_id}/failures', [SupportAccessController::class, 'failures'])->name('support.businesses.failures');
        Route::post('/support/businesses/{business:public_id}/failures/{type}/{id}/replay', [SupportAccessController::class, 'replay'])->whereNumber('id')->name('support.businesses.failures.replay');

        Route::get('/{module}', function (string $module) use ($platformModules) {
            abort_unless(isset($platformModules[$module]), 404);

            return Inertia::render('Platform/ModulePlaceholder', [
                'module' => $platformModules[$module],
            ]);
        })->where('module', implode('|', array_keys($platformModules)))->name('module');
    });
    Route::get('/invoices/{invoice}/download', DownloadInvoiceController::class)->name('invoices.download');

    // Roadmap authenticated routes
    Route::post('/roadmap', [RoadmapController::class, 'store'])->name('roadmap.store');
    Route::post('/roadmap/{roadmap}/vote', [RoadmapController::class, 'vote'])->name('roadmap.vote');

});
