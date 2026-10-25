<script setup>
import { inject as injectRoute } from 'vue';
const route = injectRoute('route');
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckIcon, CalendarDaysIcon, UsersIcon, BuildingStorefrontIcon, ChartBarIcon, BellIcon, CreditCardIcon } from '@heroicons/vue/24/outline';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import { capacityEstimate, capacityAnnualSaving, capacityMonthlyEquivalent } from '@/Support/capacityPricing';
import { emitMarketingEvent } from '@/Support/marketingTelemetry';
import '../../../css/public-pricing.css';

const props = defineProps({ catalog: Object, seo: Object });
const page = usePage();
const market = computed(() => props.catalog.markets[0]);
const interval = ref(market.value?.intervals.monthly ? 'monthly' : 'annual');
const monthlyRate = computed(() => market.value?.intervals.monthly);
const trialHref = computed(() => authenticated.value ? route('dashboard') : route('register'));
const trialLabel = computed(() => authenticated.value ? 'Open dashboard' : `Start your ${props.catalog.trial_days}-day free trial`);
const staffTiers = computed(() => monthlyRate.value?.staff_tiers || []);
const staffRange = computed(() => staffTiers.value.length ? `${money(staffTiers.value.at(-1).unit_minor)}–${money(staffTiers.value[0].unit_minor)}` : money(monthlyRate.value?.staff_minor || 0));
function reset() { interval.value = market.value?.intervals.monthly ? 'monthly' : 'annual'; locations.value = 1; staff.value = 1; }
const locations = ref(1), staff = ref(1);
const rate = computed(() => market.value?.intervals[interval.value]);
const estimate = computed(() => capacityEstimate(rate.value, locations.value, staff.value));
const savings = computed(() => capacityAnnualSaving(market.value, locations.value, staff.value));
const savingPercent = computed(() => { const monthly = capacityEstimate(market.value?.intervals.monthly, locations.value, staff.value); return monthly?.total_minor ? (savings.value / (monthly.total_minor * 12) * 100).toFixed(1) : null; });
const authenticated = computed(() => Boolean(page.props.auth?.user));
const signupHref = computed(() => authenticated.value ? route('dashboard') : route('register', estimate.value ? { plan: 'capacity', interval: interval.value, locations: estimate.value.locations, staff: estimate.value.staff } : {}));
const money = amount => new Intl.NumberFormat('en-US', { style: 'currency', currency: market.value?.currency || 'USD', minimumFractionDigits: amount % 100 ? 2 : 0, maximumFractionDigits: 2 }).format(amount / 100);
const choices = [{ label: 'Solo professional', locations: 1, staff: 1 }, { label: 'Small team', locations: 1, staff: 5 }, { label: 'Growing business', locations: 1, staff: 10 }, { label: 'Large team', locations: 1, staff: 20 }, { label: 'Multi-location business', locations: 4, staff: 20 }];
function choose(example) { locations.value = example.locations; staff.value = example.staff; }
function step(kind, delta) { const field = kind === 'locations' ? locations : staff; const max = kind === 'locations' ? props.catalog.max_locations : props.catalog.max_staff; field.value = Math.max(1, Math.min(max, (Number(field.value) || 1) + delta)); }
const features = [
    { icon: CalendarDaysIcon, title: 'Appointments & walk-ins', body: 'Bring bookings, availability and your walk-in queue together.' },
    { icon: BuildingStorefrontIcon, title: 'Online booking', body: 'Give clients a booking link for your services, team and locations.' },
    { icon: UsersIcon, title: 'Clients & staff schedules', body: 'Keep client history, staff schedules and access permissions organized.' },
    { icon: CreditCardIcon, title: 'Checkout & sales', body: 'Keep services, payments, tips and receipts together.' },
    { icon: ChartBarIcon, title: 'Business reporting', body: 'Understand appointments, sales and team performance.' },
    { icon: BellIcon, title: 'Client notifications', body: 'Keep clients informed with booking confirmations and reminders.' },
];
const faq = computed(() => [
    { question: 'Who counts as bookable staff?', answer: 'Each active person who takes appointments counts once, even if they work at several locations. Receptionists and administrators who don’t take appointments are included at no extra charge.' },
    { question: 'How does the free trial work?', answer: `Create an account and verify your email to start your ${props.catalog.trial_days}-day trial. No credit card is required. Choose and confirm a paid subscription separately; the trial never charges you automatically.` },
    { question: 'Can I change my team size or cancel?', answer: 'Review changes in Subscription & billing before confirming. Added locations and staff become available when payment is complete; reductions and changes to your billing cycle or pricing start at your next renewal. Your price reflects the locations and bookable staff you choose. Cancel to stop renewal and keep access until your paid period ends.' },
    { question: 'What is included in the text allowance?', answer: market.value ? `${market.value.intervals.monthly?.sms_monthly_allowance ?? market.value.intervals.annual?.sms_monthly_allowance ?? 0} credits per bookable staff member each month, shared across your business. Credits reset monthly, including on yearly billing, and do not roll over. Text messages are available for US and Canadian numbers, with client consent and enough credits. Longer messages use more credits. Extra credits are never purchased automatically. Existing subscriptions keep their allowances.` : 'The current text allowance is shown with available subscription prices and in your billing workspace. Text messages require client consent and enough credits and are available for US and Canadian numbers. Extra credits are never purchased automatically.' },
    { question: 'Are taxes included?', answer: 'All subscription prices are in USD. Applicable tax is added at checkout and shown before payment. Your business’s operating currency stays unchanged.' },
    { question: 'Can I collect client payments through ClipperDesk?', answer: 'Record payments you collect, including split and partial payments. To charge a client’s card or send a card refund, use your own payment service. Online deposits are not currently available.' },
]);
watch(interval, value => emitMarketingEvent('marketing_pricing_interval_changed', { interval: value }));
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <div class="cd-pricing-page">
            <section class="cd-public-section cd-family-hero pricing-hero"><PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Pricing' }]" />
                <div class="cd-family-hero-grid mt-10">
                    <div class="cd-family-hero-copy">
                        <p class="cd-eyebrow">Pricing</p>
                        <h1 class="mt-5 max-w-4xl font-display cd-public-title font-semibold leading-[0.98] tracking-[-0.055em] text-balance">One platform.<br/>A price that grows with you.</h1>
                        <p class="mt-7 max-w-2xl text-lg leading-8">Manage bookings, clients, team schedules and reporting in one place. One subscription for your whole business.</p>
                    </div>
                    <div class="pricing-start-card">
                        <div v-if="monthlyRate" class="pricing-starting"><span>Starting at</span><p><strong>{{ money(monthlyRate.base_minor) }}</strong><span>/month</span></p><small>USD · 1 location + 1 bookable staff member</small></div>
                        <p v-else class="pricing-start-unavailable">Explore ClipperDesk with a free trial.</p>
                        <Link :href="trialHref" class="cd-button cd-button-primary pricing-hero-cta" data-cta-context="pricing_hero" :data-cta-action="authenticated ? 'dashboard' : 'trial'">{{ trialLabel }}<ArrowRightIcon aria-hidden="true"/></Link>
                        <div class="pricing-trial-note"><CheckIcon aria-hidden="true"/><span>No credit card required</span></div>
                    </div>
                </div>
            </PublicContainer></section>

            <section class="pricing-features" aria-labelledby="pricing-included-title"><PublicContainer><div class="pricing-section-heading"><h2 id="pricing-included-title">All core features included.</h2><p>The same tools for a solo professional or a multi-location team.</p></div><div class="pricing-feature-grid"><article v-for="feature in features" :key="feature.title"><component :is="feature.icon" aria-hidden="true"/><h3>{{ feature.title }}</h3><p>{{ feature.body }}</p></article></div></PublicContainer></section>

            <section v-if="monthlyRate" class="pricing-growth" aria-labelledby="pricing-growth-title"><PublicContainer>
                <div class="pricing-section-heading"><h2 id="pricing-growth-title">Simple pricing as you grow.</h2></div>
                <div class="pricing-growth-grid">
                    <article><h3>Base subscription</h3><p class="pricing-growth-price">{{ money(monthlyRate.base_minor) }}<small>/month</small></p><p>Includes 1 location and 1 bookable staff member.</p></article>
                    <article><h3>Additional locations</h3><p class="pricing-growth-price">{{ money(monthlyRate.location_minor) }}<small>/month each</small></p><p>Manage your branches under one subscription.</p></article>
                    <article><h3>Additional bookable staff</h3><p class="pricing-growth-price">{{ staffRange }}<small>/month each</small></p><p>Lower rates apply to the next staff you add as your team grows.</p></article>
                </div>
                <details v-if="staffTiers.length" class="pricing-tier-details"><summary>How progressive staff pricing works</summary><div class="pricing-tier-grid"><p><strong>First staff member</strong><span>Included in the base subscription</span></p><p v-for="(tier, index) in staffTiers" :key="index"><strong>Staff {{ index === 0 ? 2 : staffTiers[index - 1].up_to + 2 }}{{ tier.up_to ? `–${tier.up_to + 1}` : ' onward' }}</strong><span>{{ money(tier.unit_minor) }} each / month</span></p></div><p>Count your bookable team once across all locations. Each rate applies only to the people in that band; earlier staff keep their band’s rate.</p></details>
                <p class="pricing-growth-note"><CheckIcon aria-hidden="true"/>Non-bookable administrators and receptionists are included.<span>Yearly billing gives you two months free.</span></p>
            </PublicContainer></section>

            <section class="pricing-builder-section" aria-labelledby="pricing-builder-title"><PublicContainer>
                <details v-if="catalog.available" class="pricing-estimator"><summary><span><strong id="pricing-builder-title">Estimate your subscription</strong><small>A clear breakdown for your team and locations</small></span><span class="pricing-estimator-open">Open calculator <span aria-hidden="true">+</span></span></summary><div class="pricing-builder">
                    <div class="pricing-controls">
                        <p class="pricing-calculator-help">Choose your locations and the people who take appointments.</p>
                        <fieldset class="pricing-cycle"><legend class="sr-only">Billing cycle</legend><label v-for="cadence in ['monthly', 'annual']" :key="cadence" :class="{ selected: interval === cadence, unavailable: !market.intervals[cadence] }"><input v-model="interval" type="radio" name="public_billing_cycle" :value="cadence" :disabled="!market.intervals[cadence]"/><span>{{ cadence === 'annual' ? 'Yearly' : 'Monthly' }}<small v-if="cadence === 'annual' && savings > 0">Save {{ savingPercent }}%</small></span></label></fieldset>
                        <div class="pricing-capacity-fields">
                            <div v-for="kind in ['locations', 'staff']" :key="kind" class="pricing-capacity-field"><label :for="`pricing-${kind}`">{{ kind === 'locations' ? 'Locations' : 'Bookable staff' }}</label><div class="pricing-number-control"><button type="button" :aria-label="`Decrease ${kind === 'staff' ? 'bookable staff' : 'locations'}`" :disabled="Number(kind === 'locations' ? locations : staff) <= 1" @click="step(kind, -1)">−</button><input v-if="kind === 'locations'" id="pricing-locations" v-model="locations" type="number" inputmode="numeric" min="1" :max="catalog.max_locations" step="1" aria-describedby="pricing-locations-help"/><input v-else id="pricing-staff" v-model="staff" type="number" inputmode="numeric" min="1" :max="catalog.max_staff" step="1" aria-describedby="pricing-staff-help"/><button type="button" :aria-label="`Increase ${kind === 'staff' ? 'bookable staff' : 'locations'}`" :disabled="Number(kind === 'locations' ? locations : staff) >= (kind === 'locations' ? catalog.max_locations : catalog.max_staff)" @click="step(kind, 1)">+</button></div><p :id="`pricing-${kind}-help`">{{ kind === 'locations' ? 'Your active branches or stores.' : 'People taking appointments, counted once.' }}</p></div>
                        </div>
                        <div class="pricing-examples"><span>Try an example</span><button type="button" class="pricing-reset" @click="reset">Reset</button><div><button v-for="example in choices" :key="example.label" type="button" :aria-pressed="Number(locations) === example.locations && Number(staff) === example.staff" @click="choose(example)">{{ example.label }}</button></div></div>


                    </div>
                    <div class="pricing-quote">
                        <div class="pricing-quote-content" aria-live="polite" aria-atomic="true"><p class="pricing-quote-eyebrow">Estimated subscription</p><template v-if="estimate"><p class="pricing-quote-amount"><strong>{{ money(estimate.total_minor) }}</strong><span>/ {{ interval === 'annual' ? 'year' : 'month' }}</span></p><p class="pricing-quote-cadence">{{ estimate.currency }} · {{ interval === 'annual' ? 'Billed yearly, upfront' : 'Billed monthly' }} · before tax</p><p v-if="interval === 'annual'" class="pricing-monthly-equivalent">Equivalent to {{ money(capacityMonthlyEquivalent(estimate.total_minor)) }} / month</p><dl class="pricing-breakdown"><div><dt>1 location + 1 bookable staff</dt><dd>{{ money(estimate.base_minor) }}</dd></div><div v-if="estimate.locations > 1"><dt>{{ estimate.locations - 1 }} extra {{ estimate.locations === 2 ? 'location' : 'locations' }} × {{ money(estimate.location_minor) }}</dt><dd>{{ money((estimate.locations - 1) * estimate.location_minor) }}</dd></div><div v-for="line in estimate.staff_lines" :key="line.from"><dt>Staff {{ line.from }}{{ line.to > line.from ? `–${line.to}` : '' }} · {{ line.quantity }} × {{ money(line.unit_minor) }}</dt><dd>{{ money(line.total_minor) }}</dd></div></dl><p v-if="interval === 'annual' && savings > 0" class="pricing-savings">You save {{ money(savings) }} a year</p><ul class="pricing-quote-includes"><li><CheckIcon aria-hidden="true"/><span>{{ estimate.sms_monthly_allowance.toLocaleString('en-US') }} text credits each month<small>For supported US & Canadian numbers</small></span></li></ul></template><p v-else class="pricing-input-error" role="status">Enter whole numbers between 1 and {{ catalog.max_locations }} locations, and 1 and {{ catalog.max_staff.toLocaleString('en-US') }} bookable staff.</p></div>
                        <div class="pricing-quote-action"><Link v-if="estimate" :href="signupHref" class="cd-button pricing-signup" data-cta-context="pricing_capacity" :data-cta-action="authenticated ? 'dashboard' : 'trial'">{{ authenticated ? 'Open your workspace' : 'Start your free trial' }}<ArrowRightIcon aria-hidden="true"/></Link><button v-else type="button" class="cd-button pricing-signup" disabled>Enter your business size</button><p>{{ authenticated ? 'Review subscription changes inside your workspace.' : 'No payment today. Confirm your subscription separately after signup.' }}</p></div>
                    </div>
                </div></details>
                <div v-else class="pricing-unavailable"><h2 id="pricing-builder-title">Start with a free trial.</h2><p>Subscription pricing is currently unavailable. You can still explore ClipperDesk with a free trial.</p><Link :href="authenticated ? route('dashboard') : route('register')" class="cd-button cd-button-primary">{{ authenticated ? 'Open your workspace' : 'Start your free trial' }}</Link><a v-if="page.props.brand?.support_email" :href="`mailto:${page.props.brand.support_email}`">Ask about pricing</a></div>
                <p v-if="catalog.available" class="pricing-tax-note">Prices are in USD, before tax. Fees charged by your client payment service are separate.</p>
            </PublicContainer></section>

            <section class="pricing-faq" aria-labelledby="pricing-faq-title"><PublicContainer><div class="pricing-faq-grid"><div class="pricing-section-heading"><h2 id="pricing-faq-title">Pricing questions, answered.</h2><p><a v-if="page.props.brand?.support_email" :href="`mailto:${page.props.brand.support_email}`">Contact support <ArrowRightIcon aria-hidden="true"/></a><Link v-else :href="route('marketing.features')">Explore the product <ArrowRightIcon aria-hidden="true"/></Link></p></div><FaqList :items="faq"/></div><p class="pricing-policy-note">Read our <Link :href="route('refund.show')">refund and cancellation policy</Link> before subscribing.</p></PublicContainer></section>
        </div>
    </HomeLayout>
</template>
