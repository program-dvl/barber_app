<script setup>
import AppSelect from '@/Components/Product/AppSelect.vue';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ArrowUpCircleIcon, CheckCircleIcon, ChevronRightIcon, DocumentTextIcon, LockClosedIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ businessLabel: String, subscription: Object, trial: Object, plans: Array, planRanks: Object, entitlements: Object, invoices: Array, payments: Array, exportAvailable: Boolean, checkoutStatus: String, checkoutAttempt: Object, pendingChange: Object, billingReadiness: Object, planChangeStatus: String, planChangeTargetPriceId: Number });
const page = usePage();
const interval = ref(props.subscription.billing_interval || 'monthly');
const busy = ref(false);
const error = ref('');
const errorArea = ref('');
const selectedPlan = ref(null);
const planDialog = ref(null);
const cancelDialog = ref(null);
const cancellationReason = ref('No longer needed');
const recoveringCheckout = ref(['pending', 'processing'].includes(props.checkoutAttempt?.status));
const recoveringPlanChange = ref(props.planChangeStatus === 'success' && Boolean(props.planChangeTargetPriceId));
const actionNotice = ref(props.planChangeStatus === 'confirmed' ? 'Stripe confirmed your new plan and your access is up to date.' : '');

const labels = { 'locations.max': 'location', 'staff.max': 'team members', 'messaging.monthly_allowance': 'messages each month', 'deposits.enabled': 'Appointment deposits', 'inventory.enabled': 'Inventory management', 'reporting.advanced': 'Advanced reporting', 'branding.custom': 'Custom booking page branding', 'support.priority': 'Priority support', 'exports.enabled': 'Business data export' };
const featureKeys = ['locations.max', 'staff.max', 'messaging.monthly_allowance', 'deposits.enabled', 'inventory.enabled', 'reporting.advanced', 'branding.custom', 'support.priority'];
const isTrial = computed(() => props.subscription.status === 'trialing');
const status = computed(() => ({ trialing: 'Free trial', active: 'Active', past_due: 'Payment needs attention', grace: 'Payment recovery period', restricted: 'Limited access', cancel_scheduled: 'Cancels at period end', canceled: 'Canceled', terminated: 'Closed' }[props.subscription.status] || props.subscription.status.replaceAll('_', ' ')));
const statusTone = computed(() => ['past_due', 'grace', 'restricted'].includes(props.subscription.status) ? 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]' : 'bg-[var(--status-success-soft)] text-[var(--status-success)]');
const primaryDate = computed(() => isTrial.value ? props.trial.ends_at : (props.subscription.cancel_at || props.subscription.current_period_ends_at));
const dateLabel = computed(() => isTrial.value ? 'Trial ends' : (props.subscription.cancel_at ? 'Access ends' : 'Next renewal'));
const trialDaysRemaining = computed(() => props.trial.ends_at ? Math.max(0, Math.ceil((new Date(props.trial.ends_at).getTime() - Date.now()) / 86400000)) : null);
const planCards = computed(() => (props.plans || [])
    .map(plan => ({ ...plan, price: (plan.prices || []).find(price => price.billing_interval === interval.value) }))
    .filter(plan => plan.price)
    .filter(plan => !props.subscription.provider_subscription_id
        || plan.id === props.subscription.billing_plan_id
        || planRank(plan.code) > planRank(props.subscription.plan.code)));
const date = value => value ? new Intl.DateTimeFormat('en-GB', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(value)) : '—';
const money = (amount, currency = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount / 100);
const planName = plan => plan.name.replace(/^ClipperDesk\s+/i, '');
const entitlement = (plan, key) => (plan.entitlements || []).find(item => item.definition?.key === key)?.value;
const feature = (plan, key) => {
    const value = entitlement(plan, key);
    if (typeof value === 'boolean') return value ? labels[key] : null;
    if (value === undefined || value === null) return null;
    if (key === 'locations.max') return `${value} ${value === 1 ? 'location' : 'locations'}`;
    if (key === 'staff.max') return `${value} team members`;
    if (key === 'messaging.monthly_allowance') return value > 0 ? `${value.toLocaleString()} messages each month` : null;
    return labels[key] || key;
};
const visibleFeatures = plan => featureKeys.map(key => feature(plan, key)).filter(Boolean).slice(0, 5);
const annualSavings = plan => {
    if (interval.value !== 'annual') return null;
    const monthly = (plan.prices || []).find(price => price.billing_interval === 'monthly');
    return monthly && monthly.amount_minor * 12 > plan.price.amount_minor ? monthly.amount_minor * 12 - plan.price.amount_minor : null;
};
const isCurrentPrice = plan => props.subscription.billing_plan_id === plan.id
    && props.subscription.billing_interval === plan.price.billing_interval;
const planRank = code => props.planRanks?.[code] ?? 0;
const planChangeKind = plan => {
    if (isCurrentPrice(plan)) return 'current';
    if (props.pendingChange) return 'unavailable';
    if (props.subscription.status !== 'active') return 'unavailable';
    if (plan.id === props.subscription.billing_plan_id) return 'interval_switch';
    if (planRank(plan.code) < planRank(props.subscription.plan.code)) return 'unavailable';
    return 'upgrade';
};
const planActionLabel = plan => ({
    current: 'Your current plan',
    unavailable: props.pendingChange ? 'Scheduled change exists' : 'Resolve billing status first',
    interval_switch: plan.price.billing_interval === 'annual' ? 'Switch to annual' : 'Switch to monthly',
    upgrade: 'Upgrade securely',
}[planChangeKind(plan)]);
const selectedChangeKind = computed(() => selectedPlan.value ? planChangeKind(selectedPlan.value) : null);
const selectedChangeDescription = computed(() => {
    if (!selectedPlan.value) return '';
    if (selectedChangeKind.value === 'interval_switch' && props.subscription.billing_interval === 'annual') return `Stripe will schedule the monthly interval for your next renewal so your paid annual term is preserved. You will see the exact effective date before confirming.`;
    return `Stripe will show the exact effective date, credit, proration, and amount due before you confirm. Your access changes only after Stripe confirms the update.`;
});
const checkoutUrl = price => route('business.billing.checkout.form', {
    business: page.props.tenant.public_id,
    price_id: price.id,
});
const openPlanChange = plan => {
    selectedPlan.value = plan;
    planDialog.value?.open();
};
const changePlan = async () => {
    if (!selectedPlan.value) return;
    busy.value = true; error.value = ''; errorArea.value = 'subscription';
    try {
        const response = await axios.post(route('business.billing.plan-change', page.props.tenant.public_id), {
            price_id: selectedPlan.value.price.id,
            reason: `Owner self-service ${selectedChangeKind.value}.`,
        });
        window.location.assign(response.data.url);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'The plan change could not be completed.';
        busy.value = false;
    }
};
const subscriptionAction = async action => {
    busy.value = true; error.value = ''; errorArea.value = 'subscription';
    try { await axios.post(route(`business.billing.${action}`, page.props.tenant.public_id), { reason: action === 'cancel' ? `Owner self-service cancellation: ${cancellationReason.value}.` : 'Owner chose to keep the subscription before cancellation took effect.' }); window.location.reload(); } catch (exception) { error.value = exception.response?.data?.message || 'The subscription could not be updated.'; busy.value = false; }
};
const confirmCancellation = () => subscriptionAction('cancel');

const recoverCheckout = async () => {
    if (!recoveringCheckout.value || !props.checkoutAttempt?.attempt_id) return;

    for (let attempt = 0; attempt < 10; attempt += 1) {
        try {
            const response = await axios.post(route('business.billing.checkout.status', page.props.tenant.public_id), { attempt_id: props.checkoutAttempt.attempt_id });
            if (response.data.status === 'confirmed') {
                window.location.replace(`${route('business.billing.show', page.props.tenant.public_id)}?checkout=success`);
                return;
            }
            if (['failed', 'expired'].includes(response.data.status)) {
                errorArea.value = 'checkout';
                error.value = 'Stripe could not complete this checkout. No paid access was granted; review the payment details and try again.';
                recoveringCheckout.value = false;
                return;
            }
        } catch (exception) {
            errorArea.value = 'checkout';
            error.value = exception.response?.data?.message || 'Stripe confirmation is taking longer than expected. Your account remains safe while the signed webhook retries.';
            recoveringCheckout.value = false;
            return;
        }
        await new Promise(resolve => window.setTimeout(resolve, 1500));
    }
    recoveringCheckout.value = false;
};

const recoverPlanChange = async () => {
    if (!recoveringPlanChange.value || !props.planChangeTargetPriceId) return;

    for (let attempt = 0; attempt < 10; attempt += 1) {
        try {
            const response = await axios.post(route('business.billing.plan-change.status', page.props.tenant.public_id), {
                price_id: props.planChangeTargetPriceId,
            });
            if (response.data.status === 'confirmed') {
                window.location.replace(`${route('business.billing.show', page.props.tenant.public_id)}?plan_change=confirmed`);
                return;
            }
        } catch (exception) {
            if (exception.response?.status !== 202) {
                errorArea.value = 'plan-change';
                error.value = exception.response?.data?.message || 'Stripe confirmation is taking longer than expected. Your current plan remains active while synchronization retries.';
                recoveringPlanChange.value = false;
                return;
            }
        }
        await new Promise(resolve => window.setTimeout(resolve, 1500));
    }

    errorArea.value = 'plan-change';
    error.value = 'Stripe confirmation is taking longer than expected. Refresh this page shortly; your current plan remains active until Stripe confirms the change.';
    recoveringPlanChange.value = false;
};

onMounted(() => {
    recoverCheckout();
    recoverPlanChange();
});
</script>

<template>
    <AppLayout title="Subscription & billing" :business-label="businessLabel">
        <PageHeader eyebrow="Account" title="Subscription & billing" description="Choose the right operating plan for your shop. Stripe securely handles payment methods and billing details.">
            <template #actions><a href="#plans" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[var(--action-primary)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--action-primary-hover)]">View plans <ChevronRightIcon class="size-4" aria-hidden="true" /></a></template>
        </PageHeader>
        <div v-if="checkoutStatus === 'success'" class="mt-5 flex gap-3 rounded-xl border border-[var(--status-success)] bg-[var(--status-success-soft)] p-4" role="status">
            <CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />
            <div><p class="text-sm font-semibold text-[var(--text-strong)]">Subscription confirmed</p><p class="mt-1 text-sm text-[var(--text-muted)]">Your active plan, renewal details, and Stripe invoice are recorded below.</p></div>
        </div>
        <p v-else-if="checkoutStatus === 'canceled'" class="mt-5 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]" role="status">Checkout was canceled. No subscription change was made.</p>
        <div v-if="recoveringCheckout" class="mt-5 flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-4 text-sm text-[var(--text-muted)]" role="status"><span class="size-4 animate-spin rounded-full border-2 border-[var(--border-strong)] border-t-[var(--action-primary)]" aria-hidden="true" />Stripe accepted checkout. Waiting for signed subscription confirmation…</div>
        <p v-if="planChangeStatus === 'canceled'" class="mt-5 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]" role="status">The Stripe confirmation was closed. Your current plan and billing interval were not changed.</p>
        <div v-if="recoveringPlanChange" class="mt-5 flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-4 text-sm text-[var(--text-muted)]" role="status"><span class="size-4 animate-spin rounded-full border-2 border-[var(--border-strong)] border-t-[var(--action-primary)]" aria-hidden="true" />Stripe confirmed your request. Synchronizing the new plan and invoice…</div>
        <p v-if="error && errorArea === 'checkout'" class="mt-5 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]" role="alert">{{ error }}</p>
        <p v-if="error && errorArea !== 'checkout'" class="mt-5 rounded-xl border border-[var(--status-danger)] bg-[var(--status-danger-soft)] p-4 text-sm text-[var(--status-danger)]" role="alert">{{ error }}</p>
        <div v-if="actionNotice" class="mt-5 flex gap-3 rounded-xl border border-[var(--status-success)] bg-[var(--status-success-soft)] p-4" role="status"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" /><div><p class="text-sm font-semibold text-[var(--text-strong)]">Plan change confirmed</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ actionNotice }}</p></div></div>
        <div v-if="!billingReadiness.webhook_configured" class="mt-5 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]" role="status">
            <p class="font-semibold text-[var(--text-strong)]">Subscription verification needs attention</p>
            <p class="mt-1">New checkout is paused until secure Stripe event delivery is configured. Existing completed payments are checked directly with Stripe while support finishes setup.</p>
        </div>

        <section class="mt-5 grid items-start gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(19rem,0.75fr)]">
            <article class="relative overflow-hidden rounded-xl bg-[var(--brand-primary)] p-5 text-white">
                <div class="absolute -right-16 -top-20 size-56 rounded-full border border-white/15" aria-hidden="true" /><div class="absolute -bottom-24 right-24 size-44 rounded-full border border-white/10" aria-hidden="true" />
                <div class="relative"><div class="flex flex-wrap items-center gap-3"><span class="inline-flex items-center gap-2 rounded-full bg-white/12 px-3 py-1.5 text-sm font-semibold"><SparklesIcon class="size-4" aria-hidden="true" /> {{ status }}</span><span class="text-sm text-white/70">{{ businessLabel }}</span></div><p class="mt-4 text-sm font-medium text-white/70">Your current plan</p><h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ subscription.plan.name }}</h2><p class="mt-3 max-w-xl text-sm leading-6 text-white/80"><template v-if="isTrial">You have {{ trialDaysRemaining }} {{ trialDaysRemaining === 1 ? 'day' : 'days' }} remaining. Select a plan before {{ date(trial.ends_at) }} to keep uninterrupted access; after expiry, existing data remains readable while writes pause.</template><template v-else-if="subscription.status === 'restricted'">Your records remain available in read-only mode. Update billing or select a plan to restore protected operations.</template><template v-else>Your subscription, invoices, payment method, and plan controls are all in one place.</template></p><div class="mt-5 flex flex-wrap gap-x-6 gap-y-3 border-t border-white/15 pt-4 text-sm"><div><p class="text-white/60">{{ dateLabel }}</p><p class="mt-1 font-semibold">{{ date(primaryDate) }}</p></div><div><p class="text-white/60">Payment method</p><p class="mt-1 font-semibold">{{ subscription.payment_method_type ? `${subscription.payment_method_type} •••• ${subscription.payment_method_last_four}` : (subscription.provider_customer_id ? 'Manage securely in Stripe' : 'Add at secure checkout') }}</p></div><div><p class="text-white/60">Access</p><p class="mt-1 font-semibold">{{ subscription.restriction_level === 'none' ? 'Full access' : subscription.restriction_level.replaceAll('_', ' ') }}</p></div></div></div>
            </article>
            <SurfaceCard title="Payment & billing" description="Securely managed through Stripe.">
                <dl class="space-y-4 text-sm">
                    <div><dt class="font-semibold text-[var(--text-strong)]">Payment details</dt><dd class="mt-1 leading-5 text-[var(--text-muted)]">Manage your payment method and billing details in Stripe's secure portal.</dd></div>
                    <div><dt class="font-semibold text-[var(--text-strong)]">Invoices</dt><dd class="mt-1 leading-5 text-[var(--text-muted)]">Find subscription invoices and payment history below.</dd></div>
                </dl>
                <AppButton v-if="subscription.provider_customer_id" :href="route('business.billing.portal', page.props.tenant.public_id)" variant="secondary" class="mt-5 w-full">Open Stripe billing portal</AppButton>
                <p v-else class="mt-4 border-t border-[var(--border-subtle)] pt-4 text-sm leading-5 text-[var(--text-muted)]">Add a payment method when you choose a plan.</p>
            </SurfaceCard>
        </section>

        <SurfaceCard v-if="subscription.provider_subscription_id" class="mt-5" title="Subscription controls" description="Renewal and cancellation stay predictable. Canceling never deletes your records.">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div>
                    <div v-if="pendingChange" class="mb-4 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4 text-sm">
                        <p class="font-semibold text-[var(--text-strong)]">{{ pendingChange.plan_name }} scheduled</p>
                        <p class="mt-1 text-[var(--text-muted)]">Your current access continues until {{ date(pendingChange.effective_at) }}. No records are removed by the change.</p>
                    </div>
                    <p v-if="subscription.status === 'cancel_scheduled'" class="text-sm leading-6 text-[var(--text-muted)]"><strong class="text-[var(--text-strong)]">Cancellation scheduled for {{ date(subscription.cancel_at) }}.</strong> You keep your current plan until then and can undo the cancellation before it takes effect.</p>
                    <p v-else-if="subscription.status === 'active'" class="text-sm leading-6 text-[var(--text-muted)]">Your plan renews automatically on <strong class="text-[var(--text-strong)]">{{ date(subscription.current_period_ends_at) }}</strong>. If you cancel, access continues through that paid date and recurring billing stops.</p>
                    <p v-else class="text-sm leading-6 text-[var(--text-muted)]">Plan changes and cancellation are unavailable while this subscription needs billing attention. Update the payment method or contact support.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <AppButton v-if="subscription.status === 'cancel_scheduled'" :disabled="busy" @click="subscriptionAction('reactivate')">Keep my subscription</AppButton>
                    <AppButton v-else-if="subscription.status === 'active'" variant="danger" :disabled="busy" @click="cancelDialog?.open()">Cancel at renewal</AppButton>
                </div>
            </div>
        </SurfaceCard>

        <section id="plans" class="scroll-mt-6 mt-6"><div class="flex flex-col gap-4 border-b border-[var(--border-subtle)] pb-5 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--action-primary)]">Plans</p><h2 class="mt-1 text-2xl font-semibold tracking-tight text-[var(--text-strong)]">Pick the plan that fits your shop today.</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--text-muted)]">Upgrade when you need more capacity; historical invoices and subscription evidence remain intact.</p></div><div class="cd-segmented" role="group" aria-label="Billing interval"><button :class="['min-h-11 rounded-lg px-4 py-2 text-sm font-semibold transition-colors', interval === 'monthly' ? 'bg-[var(--brand-primary)] text-white' : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']" :aria-pressed="interval === 'monthly'" @click="interval = 'monthly'">Monthly</button><button :class="['min-h-11 rounded-lg px-4 py-2 text-sm font-semibold transition-colors', interval === 'annual' ? 'bg-[var(--brand-primary)] text-white' : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']" :aria-pressed="interval === 'annual'" @click="interval = 'annual'">Annual <span class="ml-1 text-xs text-[var(--status-success)]">Save up to 17%</span></button></div></div>
            <div v-if="planCards.length" class="mt-6 grid gap-5 lg:grid-cols-2"><article v-for="plan in planCards" :key="plan.id" :class="['relative rounded-xl border bg-[var(--surface-raised)] p-5', plan.code === 'pro' ? 'border-[var(--brand-primary)] shadow-[var(--shadow-raised)]' : 'border-[var(--border-subtle)]']"><span v-if="plan.code === 'pro'" class="absolute right-5 top-0 -translate-y-1/2 rounded-full bg-[var(--brand-primary)] px-3 py-1 text-xs font-semibold text-white">Most popular</span><div class="flex items-start justify-between gap-4"><div><h3 class="text-xl font-semibold text-[var(--text-strong)]">{{ planName(plan) }}</h3><p class="mt-2 min-h-10 text-sm leading-5 text-[var(--text-muted)]">{{ plan.description }}</p></div><span v-if="isCurrentPrice(plan)" class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusTone">Current</span></div><div class="mt-4 flex items-end gap-2"><span class="text-4xl font-semibold tracking-tight text-[var(--text-strong)]">{{ money(plan.price.amount_minor, plan.price.currency) }}</span><span class="mb-1 text-sm text-[var(--text-muted)]">/{{ interval === 'annual' ? 'year' : 'month' }}</span></div><p class="mt-2 min-h-5 text-sm text-[var(--status-success)]"><template v-if="annualSavings(plan)">Save {{ money(annualSavings(plan), plan.price.currency) }} compared with monthly</template><template v-else>&nbsp;</template></p><ul class="mt-4 space-y-2 border-t border-[var(--border-subtle)] pt-4 text-sm text-[var(--text-default)]"><li v-for="item in visibleFeatures(plan)" :key="item" class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />{{ item }}</li></ul><AppButton v-if="subscription.provider_subscription_id" class="mt-7 w-full" :variant="['upgrade', 'interval_switch'].includes(planChangeKind(plan)) ? 'primary' : 'secondary'" :disabled="busy || ['current', 'unavailable'].includes(planChangeKind(plan))" @click="openPlanChange(plan)">{{ planActionLabel(plan) }}</AppButton><AppButton v-else class="mt-7 w-full" :href="checkoutUrl(plan.price)" :variant="plan.code === 'pro' ? 'primary' : 'secondary'">Review and subscribe</AppButton></article></div>
            <SurfaceCard v-else class="mt-6" title="Plans are being prepared" description="No active Stripe prices are available yet for this environment."><p class="text-sm leading-6 text-[var(--text-muted)]">Your trial remains available while the verified Stripe catalog is synchronized.</p></SurfaceCard>
            <div class="mt-6 rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-5 py-4"><div class="flex items-start gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-lg bg-[var(--surface-raised)] text-[var(--action-primary)]"><LockClosedIcon class="size-4" aria-hidden="true" /></span><p class="text-sm leading-5 text-[var(--text-muted)]"><strong class="font-semibold text-[var(--text-strong)]">Clear confirmation:</strong> Stripe shows the exact proration, amount due, and effective date before anything changes. Card authentication and payment recovery remain on Stripe's secure page.</p></div></div>
        </section>

        <section class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(19rem,0.75fr)]"><SurfaceCard title="Invoices & payment history" description="Every ClipperDesk subscription invoice is retained here."><div v-if="invoices.length" class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr class="border-b border-[var(--border-subtle)] text-[var(--text-muted)]"><th class="py-3 pr-5 font-medium">Invoice</th><th class="py-3 pr-5 font-medium">Issued</th><th class="py-3 pr-5 font-medium">Status</th><th class="py-3 pr-5 text-right font-medium">Total</th><th class="py-3 text-right font-medium">Document</th></tr></thead><tbody><tr v-for="invoice in invoices" :key="invoice.public_id" class="border-b border-[var(--border-subtle)] last:border-0"><td class="py-4 pr-5 font-semibold text-[var(--text-strong)]">{{ invoice.number || invoice.public_id }}</td><td class="py-4 pr-5">{{ date(invoice.issued_at) }}</td><td class="py-4 pr-5 capitalize">{{ invoice.status }}</td><td class="py-4 pr-5 text-right font-semibold">{{ money(invoice.total_minor, invoice.currency) }}</td><td class="py-4 text-right"><a v-if="invoice.hosted_url" class="inline-flex min-h-11 min-w-11 items-center justify-center font-semibold text-[var(--action-primary)] hover:underline" :href="invoice.hosted_url" target="_blank" rel="noopener">View</a><span v-else class="text-[var(--text-muted)]">Recorded</span></td></tr></tbody></table></div><div v-else class="flex min-h-32 items-center gap-4 rounded-xl bg-[var(--surface-subtle)] p-5"><DocumentTextIcon class="size-7 shrink-0 text-[var(--text-muted)]" aria-hidden="true" /><div><p class="font-semibold text-[var(--text-strong)]">No invoices yet</p><p class="mt-1 text-sm leading-5 text-[var(--text-muted)]">Your first invoice will appear here after Stripe confirms your subscription.</p></div></div></SurfaceCard><SurfaceCard title="Your current access" description="Capabilities available to this business today."><dl class="divide-y divide-[var(--border-subtle)] text-sm"><div v-for="(value, key) in entitlements" :key="key" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"><dt class="text-[var(--text-muted)]">{{ labels[key] || key.replaceAll('.', ' ') }}</dt><dd :class="['text-right font-semibold', typeof value === 'boolean' && !value ? 'text-[var(--text-muted)]' : 'text-[var(--text-strong)]']">{{ typeof value === 'boolean' ? (value ? 'Included' : 'Not included') : value }}</dd></div></dl><p class="mt-5 text-sm" :class="exportAvailable ? 'text-[var(--status-success)]' : 'text-[var(--status-danger)]'">Data export is {{ exportAvailable ? 'available' : 'outside the documented availability window' }}.</p></SurfaceCard></section>

        <AppDialog id="plan-change" ref="planDialog" :title="selectedChangeKind === 'interval_switch' ? 'Switch billing interval?' : `Upgrade to ${selectedPlan?.name || 'this plan'}?`" :description="selectedChangeDescription" :confirm-label="busy ? 'Opening Stripe…' : 'Review and confirm in Stripe'" :confirm-disabled="busy" :close-on-confirm="false" @confirm="changePlan">
            <p v-if="error && errorArea === 'subscription'" class="mb-4 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ error }}</p>
            <div v-if="selectedPlan" class="rounded-xl bg-[var(--surface-subtle)] p-4 text-sm"><div class="flex items-center gap-3"><ArrowUpCircleIcon class="size-5 text-[var(--action-primary)]" aria-hidden="true" /><div><p class="font-semibold text-[var(--text-strong)]">{{ selectedPlan.name }}</p><p class="text-[var(--text-muted)]">{{ money(selectedPlan.price.amount_minor, selectedPlan.price.currency) }} / {{ selectedPlan.price.billing_interval === 'annual' ? 'year' : 'month' }}</p></div></div></div>
        </AppDialog>
        <AppDialog id="cancel-subscription" ref="cancelDialog" title="Cancel at the end of this billing period?" :description="`Your ${subscription.plan.name} access continues until ${date(subscription.current_period_ends_at)}. Future renewals stop, and your business records are retained.`" :confirm-label="busy ? 'Scheduling cancellation…' : 'Cancel at renewal'" :confirm-disabled="busy" :close-on-confirm="false" destructive @confirm="confirmCancellation">
            <p v-if="error && errorArea === 'subscription'" class="mb-4 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ error }}</p>
            <label class="block text-sm font-semibold text-[var(--text-strong)]">Main reason <AppSelect v-model="cancellationReason" class="cd-input mt-2 block w-full"><option>No longer needed</option><option>Too expensive</option><option>Missing a feature</option><option>Closing the business</option><option>Switching to another product</option><option>Other</option></AppSelect></label>
        </AppDialog>
    </AppLayout>
</template>
