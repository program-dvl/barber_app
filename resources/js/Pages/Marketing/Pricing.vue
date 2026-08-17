<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ComparisonTable from '@/Components/Marketing/ComparisonTable.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { CheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { emitMarketingEvent } from '@/Support/marketingTelemetry';

const props = defineProps({ catalog: Object });
const page = usePage();
const interval = ref('monthly');
const authenticated = computed(() => Boolean(page.props.auth?.user));
const money = (minor, currency) => new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 0 }).format(minor / 100);
const cadence = computed(() => interval.value === 'monthly' ? 'month' : 'year');
const entitlementLabels = {
    'locations.max': 'Active locations',
    'staff.max': 'Active staff profiles',
    'messaging.monthly_allowance': 'Included mobile messages / month',
    'deposits.enabled': 'Appointment deposit capability',
    'inventory.enabled': 'Inventory operations',
    'reporting.advanced': 'Advanced reporting',
    'branding.custom': 'Custom booking branding',
    'support.priority': 'Priority support routing',
    'exports.enabled': 'Business data exports',
};
const displayValue = (value) => typeof value === 'boolean' ? (value ? 'Included' : 'Not included') : new Intl.NumberFormat('en-IN').format(value);
const comparisonRows = computed(() => Object.entries(entitlementLabels).map(([key, label]) => [label, ...props.catalog.plans.map((plan) => displayValue(plan.entitlements[key]))]));
const signupHref = (plan) => authenticated.value ? route('dashboard') : route('register', { plan: plan.code, interval: interval.value });
const faq = computed(() => [
    { question: 'Does Good Hours include a trial?', answer: `A verified owner registration starts a ${props.catalog.trial_days}-day trial. Registration itself does not charge a payment method.` },
    { question: 'What changes between monthly and annual billing?', answer: 'The selected interval changes the subscription cadence. Annual savings shown above are calculated from the currently effective monthly and annual catalog prices.' },
    { question: 'Can I cancel?', answer: 'Paid cancellation is scheduled for the end of the current period by default. Access, billing recovery and dated export availability follow the verified subscription lifecycle.' },
    { question: 'Are appointment payments included?', answer: 'No. Paddle is used for the Good Hours SaaS subscription. Client deposits and appointment payments are a separate salon commerce flow and live card collection remains provider-qualified.' },
    { question: 'What about taxes and payment fees?', answer: 'Paddle presents applicable subscription tax and payment details during its provider-hosted checkout frame. This page does not claim a universal tax or processing-fee outcome.' },
]);
watch(interval, (value) => emitMarketingEvent('marketing_pricing_interval_changed', { interval: value }));
</script>

<template>
    <HomeLayout>
        <Head title="Good Hours pricing for salons and barbershops" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Pricing' }]" />
                <p class="gh-eyebrow mt-10">Pricing</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">Choose the operating depth your Business needs.</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">Good Hours uses a {{ catalog.trial_days }}-day verified trial. Paid prices and allowances below appear only when the complete, effective Paddle catalog is available.</p>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <div v-if="!catalog.available" class="mx-auto max-w-3xl rounded-[var(--radius-xl)] border border-[var(--brand-rust)]/30 bg-[var(--surface-raised)] p-7 sm:p-10" role="status">
                    <ExclamationTriangleIcon class="size-8 text-[var(--brand-rust)]" aria-hidden="true" />
                    <h2 class="mt-5 font-display text-3xl font-semibold text-[var(--text-strong)]">Paid pricing is not available in this environment.</h2>
                    <p class="mt-4 leading-7 text-[var(--text-muted)]">{{ catalog.reason }} We will not show placeholder, expired or unmapped provider prices. The verified trial can still be started without a charge.</p>
                    <Link :href="authenticated ? route('dashboard') : route('register')" class="gh-button gh-button-primary mt-7">{{ authenticated ? 'Open dashboard' : 'Start your trial' }}</Link>
                </div>

                <template v-else>
                    <fieldset class="mx-auto flex w-fit rounded-xl border border-[var(--border-strong)] bg-[var(--surface-raised)] p-1" aria-label="Billing interval">
                        <legend class="sr-only">Choose a billing interval</legend>
                        <label v-for="choice in ['monthly', 'annual']" :key="choice" class="cursor-pointer">
                            <input v-model="interval" class="peer sr-only" type="radio" name="billing_interval" :value="choice" />
                            <span class="flex min-h-11 items-center rounded-lg px-5 text-sm font-extrabold capitalize text-[var(--text-muted)] peer-checked:bg-[var(--brand-pine)] peer-checked:text-white peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2">{{ choice }}</span>
                        </label>
                    </fieldset>

                    <div class="mt-12 grid gap-6 lg:grid-cols-2" aria-live="polite">
                        <article v-for="plan in catalog.plans" :key="plan.code" class="gh-marketing-card flex flex-col">
                            <p class="gh-eyebrow">{{ plan.name }}</p>
                            <h2 class="mt-4 text-2xl font-extrabold text-[var(--text-strong)]">{{ plan.description }}</h2>
                            <p class="mt-8 text-[var(--text-muted)]"><span class="font-display text-5xl font-semibold text-[var(--text-strong)]">{{ money(plan.prices[interval].amount_minor, plan.prices[interval].currency) }}</span> / {{ cadence }}</p>
                            <p v-if="interval === 'annual' && plan.annual_savings_minor" class="mt-2 text-sm font-bold text-[var(--brand-pine)]">{{ money(plan.annual_savings_minor, catalog.currency) }} less per year than 12 current monthly payments</p>
                            <ul class="mt-7 grow space-y-3">
                                <li v-for="key in ['locations.max', 'staff.max', 'messaging.monthly_allowance', 'exports.enabled']" :key="key" class="flex gap-3 text-sm leading-6 text-[var(--text-muted)]">
                                    <CheckIcon class="mt-1 size-4 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><span><strong class="text-[var(--text-strong)]">{{ displayValue(plan.entitlements[key]) }}</strong> {{ entitlementLabels[key].toLowerCase() }}</span>
                                </li>
                            </ul>
                            <Link :href="signupHref(plan)" class="gh-button gh-button-primary mt-8" :data-plan="plan.code" :data-interval="interval" data-cta-context="pricing_plan" :data-cta-action="authenticated ? 'dashboard' : 'trial'">{{ authenticated ? 'Open dashboard' : `Choose ${plan.name.replace('Good Hours ', '')}` }}</Link>
                            <p class="mt-3 text-xs leading-5 text-[var(--text-muted)]">Selection is reviewed after verification and trial creation. No charge at registration.</p>
                        </article>
                    </div>

                    <div class="mt-18">
                        <SectionHeading eyebrow="Complete comparison" title="Limits and capabilities come from the same entitlement catalog" />
                        <ComparisonTable class="mt-9" caption="Good Hours plan entitlement comparison" :columns="['Capability', ...catalog.plans.map((plan) => plan.name)]" :rows="comparisonRows" />
                    </div>
                </template>
            </PublicContainer>
        </section>

        <section class="gh-public-section bg-[var(--surface-subtle)]">
            <PublicContainer>
                <SectionHeading eyebrow="Commercial clarity" title="Subscription billing is separate from salon payments" description="Paddle owns the Good Hours SaaS checkout frame. Appointment deposits, retail tenders and client payment evidence belong to each salon's operating workflow and are not bundled into the subscription price." />
                <div class="mt-9 max-w-4xl rounded-[var(--radius-lg)] border-l-4 border-[var(--brand-rust)] bg-white p-6 leading-7 text-[var(--text-muted)]"><strong class="text-[var(--text-strong)]">Launch qualification:</strong> live Paddle checkout remains blocked until the production domain, seller identity, credentials, price mappings, webhook and settlement path are certified. This page never opens a sandbox checkout.</div>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Pricing questions" title="What happens before and after the trial" />
                <FaqList class="mt-9" :items="faq" />
            </PublicContainer>
        </section>
    </HomeLayout>
</template>
