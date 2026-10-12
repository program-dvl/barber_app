<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckIcon, CalendarDaysIcon, UsersIcon, BuildingStorefrontIcon, ChartBarIcon, BellIcon, CreditCardIcon } from '@heroicons/vue/24/outline';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import { capacityEstimate, capacityAnnualSaving } from '@/Support/capacityPricing';
import { emitMarketingEvent } from '@/Support/marketingTelemetry';
import '../../../css/public-pricing.css';

const props = defineProps({ catalog: Object, seo: Object });
const page = usePage();
const market = computed(() => props.catalog.markets[0]);
const interval = ref(market.value?.intervals.monthly ? 'monthly' : 'annual');
const locations = ref(1), staff = ref(1);
const rate = computed(() => market.value?.intervals[interval.value]);
const estimate = computed(() => capacityEstimate(rate.value, locations.value, staff.value));
const savings = computed(() => capacityAnnualSaving(market.value, locations.value, staff.value));
const savingPercent = computed(() => { const monthly = capacityEstimate(market.value?.intervals.monthly, locations.value, staff.value); return monthly?.total_minor ? (savings.value / (monthly.total_minor * 12) * 100).toFixed(1) : null; });
const authenticated = computed(() => Boolean(page.props.auth?.user));
const signupHref = computed(() => authenticated.value ? route('dashboard') : route('register', estimate.value ? { plan: 'capacity', interval: interval.value, locations: estimate.value.locations, staff: estimate.value.staff } : {}));
const money = amount => new Intl.NumberFormat('en-US', { style: 'currency', currency: market.value?.currency || 'USD', minimumFractionDigits: amount % 100 ? 2 : 0, maximumFractionDigits: 2 }).format(amount / 100);
const choices = [{ label: 'Solo professional', locations: 1, staff: 1 }, { label: 'Growing team', locations: 1, staff: 4 }, { label: 'Two locations', locations: 2, staff: 8 }];
function choose(example) { locations.value = example.locations; staff.value = example.staff; }
function step(kind, delta) { const field = kind === 'locations' ? locations : staff; const max = kind === 'locations' ? props.catalog.max_locations : props.catalog.max_staff; field.value = Math.max(1, Math.min(max, (Number(field.value) || 1) + delta)); }
const features = [
    { icon: CalendarDaysIcon, title: 'Appointments & walk-ins', body: 'Bring bookings, availability and your walk-in queue together.' },
    { icon: BuildingStorefrontIcon, title: 'Online booking', body: 'Give clients a booking link for your services, team and locations.' },
    { icon: UsersIcon, title: 'Clients & team', body: 'Keep client history, staff schedules and access permissions organized.' },
    { icon: CreditCardIcon, title: 'Checkout & sales', body: 'Record services, payments, tips and receipts in one workflow.' },
    { icon: ChartBarIcon, title: 'Business reporting', body: 'Understand appointments, sales and team performance.' },
    { icon: BellIcon, title: 'Client notifications', body: 'Routine email notifications and a pooled monthly text allowance.' },
];
const faq = computed(() => [
    { question: 'Who counts as bookable staff?', answer: 'A person who takes appointments counts as one bookable staff member. Count each person once, even if they work across multiple locations. Receptionists and administrators who do not take appointments are included without an extra staff charge. Inactive profiles and physical chairs do not count.' },
    { question: 'How are locations charged?', answer: 'The base subscription includes one location and one bookable staff member. Add the location rate for each additional active branch and the staff rate for each additional person taking appointments. The calculator shows the complete subscription total.' },
    { question: 'How does the free trial work?', answer: `Create your account and verify your email to start your ${props.catalog.trial_days}-day trial. No credit card is needed. You choose and confirm a paid subscription separately; your trial does not automatically charge you.` },
    { question: 'Can I change my team size or cancel?', answer: 'Yes. Review changes in Subscription & billing. Capacity increases can start after payment is confirmed; reductions and billing-cycle changes take effect at renewal. Cancellation stops renewal at the end of your paid period, so you keep access for the time you have paid for.' },
    { question: 'What is included in the text allowance?', answer: `Each bookable staff member adds ${market.value?.intervals.monthly?.sms_monthly_allowance || market.value?.intervals.annual?.sms_monthly_allowance || 'an allowance of'} text credits per month, shared across your business. Credits reset monthly, even with yearly billing. Message length and destination affect credit use; supported US and Canadian numbers currently use the allowance. Extra texts are not automatically purchased.` },
    { question: 'Are taxes and client payment fees included?', answer: 'Prices are in USD, before applicable tax. The final subscription total is shown before you pay. Fees for processing payments from your clients are separate from your ClipperDesk subscription. Your business can keep its own operating currency.' },
]);
watch(interval, value => emitMarketingEvent('marketing_pricing_interval_changed', { interval: value }));
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <div class="cd-pricing-page">
            <section class="pricing-intro"><PublicContainer><p class="cd-eyebrow">Simple, flexible pricing</p><h1>One plan.<br/><span>Built around your business.</span></h1><p class="pricing-intro-copy">All the core tools. One subscription based on your locations<br class="pricing-desktop-break"/> and the people who take appointments.</p><div class="pricing-trial-note"><CheckIcon aria-hidden="true"/><span>{{ catalog.trial_days }}-day free trial</span><span aria-hidden="true">·</span><span>No credit card required</span></div></PublicContainer></section>

            <section class="pricing-builder-section" aria-labelledby="pricing-builder-title"><PublicContainer>
                <div v-if="catalog.available" class="pricing-builder">
                    <div class="pricing-controls">
                        <div class="pricing-builder-heading"><p class="cd-eyebrow">Your business, your price</p><h2 id="pricing-builder-title">Make it yours.</h2><p>Start with 1 location and 1 bookable staff member.<br/>Add capacity as your business grows.</p></div>
                        <fieldset class="pricing-cycle"><legend class="sr-only">Billing cycle</legend><label v-for="cadence in ['monthly', 'annual']" :key="cadence" :class="{ selected: interval === cadence, unavailable: !market.intervals[cadence] }"><input v-model="interval" type="radio" name="public_billing_cycle" :value="cadence" :disabled="!market.intervals[cadence]"/><span>{{ cadence === 'annual' ? 'Yearly' : 'Monthly' }}<small v-if="cadence === 'annual' && savings > 0">Save {{ savingPercent }}%</small></span></label></fieldset>
                        <div class="pricing-capacity-fields">
                            <div v-for="kind in ['locations', 'staff']" :key="kind" class="pricing-capacity-field"><label :for="`pricing-${kind}`">{{ kind === 'locations' ? 'Locations' : 'Bookable staff' }}</label><div class="pricing-number-control"><button type="button" :aria-label="`Decrease ${kind === 'staff' ? 'bookable staff' : 'locations'}`" :disabled="Number(kind === 'locations' ? locations : staff) <= 1" @click="step(kind, -1)">−</button><input v-if="kind === 'locations'" id="pricing-locations" v-model="locations" type="number" inputmode="numeric" min="1" :max="catalog.max_locations" step="1" aria-describedby="pricing-locations-help"/><input v-else id="pricing-staff" v-model="staff" type="number" inputmode="numeric" min="1" :max="catalog.max_staff" step="1" aria-describedby="pricing-staff-help"/><button type="button" :aria-label="`Increase ${kind === 'staff' ? 'bookable staff' : 'locations'}`" :disabled="Number(kind === 'locations' ? locations : staff) >= (kind === 'locations' ? catalog.max_locations : catalog.max_staff)" @click="step(kind, 1)">+</button></div><p :id="`pricing-${kind}-help`">{{ kind === 'locations' ? 'Your active branches or stores.' : 'People taking appointments, counted once.' }}</p></div>
                        </div>
                        <div class="pricing-examples"><span>Try an example</span><div><button v-for="example in choices" :key="example.label" type="button" :aria-pressed="Number(locations) === example.locations && Number(staff) === example.staff" @click="choose(example)">{{ example.label }}</button></div></div>
                        <dl v-if="rate" class="pricing-unit-rates"><div><dt>Base subscription<small>1 location + 1 bookable staff</small></dt><dd>{{ money(rate.base_minor) }}<small>/ {{ interval === 'annual' ? 'year' : 'month' }}</small></dd></div><div><dt>Each extra location</dt><dd>{{ money(rate.location_minor) }}<small>/ {{ interval === 'annual' ? 'year' : 'month' }}</small></dd></div><div><dt>Each extra bookable staff</dt><dd>{{ money(rate.staff_minor) }}<small>/ {{ interval === 'annual' ? 'year' : 'month' }}</small></dd></div></dl>
                        <p class="pricing-admin-note"><CheckIcon aria-hidden="true"/>Receptionists and non-bookable admins are included.</p>
                    </div>
                    <div class="pricing-quote">
                        <div class="pricing-quote-content" aria-live="polite" aria-atomic="true"><p class="pricing-quote-eyebrow">Your ClipperDesk subscription</p><template v-if="estimate"><p class="pricing-quote-amount"><strong>{{ money(estimate.total_minor) }}</strong><span>/ {{ interval === 'annual' ? 'year' : 'month' }}</span></p><p class="pricing-quote-cadence">{{ estimate.currency }} · {{ interval === 'annual' ? 'Billed yearly, upfront' : 'Billed monthly' }} · before tax</p><p v-if="interval === 'annual'" class="pricing-monthly-equivalent">Equivalent to {{ money(Math.round(estimate.total_minor / 12)) }} / month</p><dl class="pricing-breakdown"><div><dt>1 location + 1 bookable staff</dt><dd>{{ money(estimate.base_minor) }}</dd></div><div v-if="estimate.locations > 1"><dt>{{ estimate.locations - 1 }} extra {{ estimate.locations === 2 ? 'location' : 'locations' }} × {{ money(estimate.location_minor) }}</dt><dd>{{ money((estimate.locations - 1) * estimate.location_minor) }}</dd></div><div v-if="estimate.staff > 1"><dt>{{ estimate.staff - 1 }} extra staff × {{ money(estimate.staff_minor) }}</dt><dd>{{ money((estimate.staff - 1) * estimate.staff_minor) }}</dd></div></dl><p v-if="interval === 'annual' && savings > 0" class="pricing-savings">You save {{ money(savings) }} a year</p><ul class="pricing-quote-includes"><li><CheckIcon aria-hidden="true"/>All core tools included</li><li><CheckIcon aria-hidden="true"/><span>{{ estimate.sms_monthly_allowance.toLocaleString('en-US') }} text credits each month<small>For supported US & Canadian numbers</small></span></li><li><CheckIcon aria-hidden="true"/>Routine email notifications included</li></ul></template><p v-else class="pricing-input-error" role="status">Enter whole numbers between 1 and {{ catalog.max_locations }} locations, and 1 and {{ catalog.max_staff.toLocaleString('en-US') }} bookable staff.</p></div>
                        <div class="pricing-quote-action"><Link v-if="estimate" :href="signupHref" class="cd-button pricing-signup" data-cta-context="pricing_capacity" :data-cta-action="authenticated ? 'dashboard' : 'trial'">{{ authenticated ? 'Open your workspace' : 'Start your free trial' }}<ArrowRightIcon aria-hidden="true"/></Link><button v-else type="button" class="cd-button pricing-signup" disabled>Enter your business size</button><p>{{ authenticated ? 'Review subscription changes inside your workspace.' : `Explore ClipperDesk for ${catalog.trial_days} days. No payment today.` }}</p></div>
                    </div>
                </div>
                <div v-else class="pricing-unavailable"><h2 id="pricing-builder-title">Start with a free trial.</h2><p>Explore the tools for your business. Contact our team for subscription pricing.</p><Link :href="authenticated ? route('dashboard') : route('register')" class="cd-button cd-button-primary">{{ authenticated ? 'Open your workspace' : 'Start your free trial' }}</Link><a v-if="page.props.brand?.support_email" :href="`mailto:${page.props.brand.support_email}`">Ask about pricing</a></div>
                <p v-if="catalog.available" class="pricing-tax-note">Prices are in USD, before applicable tax. Client payment processing fees are separate.</p>
            </PublicContainer></section>

            <section class="pricing-features" aria-labelledby="pricing-included-title"><PublicContainer><div class="pricing-section-heading"><p class="cd-eyebrow">Included at every size</p><h2 id="pricing-included-title">The tools to run your day.</h2><p>The same core features, whether you work solo or across locations.</p></div><div class="pricing-feature-grid"><article v-for="feature in features" :key="feature.title"><component :is="feature.icon" aria-hidden="true"/><h3>{{ feature.title }}</h3><p>{{ feature.body }}</p></article></div></PublicContainer></section>

            <section class="pricing-faq" aria-labelledby="pricing-faq-title"><PublicContainer><div class="pricing-faq-grid"><div class="pricing-section-heading"><p class="cd-eyebrow">A little more clarity</p><h2 id="pricing-faq-title">Good questions.<br/>Straight answers.</h2><p><span v-if="page.props.brand?.support_email">Still need a hand?<br/></span><a v-if="page.props.brand?.support_email" :href="`mailto:${page.props.brand.support_email}`">Talk to our team <ArrowRightIcon aria-hidden="true"/></a><Link v-else :href="route('marketing.features')">Explore the product <ArrowRightIcon aria-hidden="true"/></Link></p></div><FaqList :items="faq"/></div><p class="pricing-policy-note">Read our <Link :href="route('refund.show')">refund and cancellation policy</Link> before subscribing.</p></PublicContainer></section>
        </div>
    </HomeLayout>
</template>
