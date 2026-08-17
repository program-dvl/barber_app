<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ArrowPathIcon, ArrowUpCircleIcon, CheckCircleIcon, ChevronRightIcon, CreditCardIcon, DocumentTextIcon, LockClosedIcon, ShieldCheckIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ businessLabel: String, subscription: Object, trial: Object, plans: Array, entitlements: Object, invoices: Array, payments: Array, exportAvailable: Boolean, checkoutStatus: String, pendingChange: Object, checkoutRecovery: Boolean });
const page = usePage();
const interval = ref(props.subscription.billing_interval || 'monthly');
const busy = ref(false);
const error = ref('');
const errorArea = ref('');
const selectedPlan = ref(null);
const planDialog = ref(null);
const cancelDialog = ref(null);
const cancellationReason = ref('No longer needed');
const recoveringCheckout = ref(false);

const labels = { 'locations.max': 'location', 'staff.max': 'team members', 'messaging.monthly_allowance': 'messages each month', 'deposits.enabled': 'Appointment deposits', 'inventory.enabled': 'Inventory management', 'reporting.advanced': 'Advanced reporting', 'branding.custom': 'Custom booking page branding', 'support.priority': 'Priority support', 'exports.enabled': 'Business data export' };
const featureKeys = ['locations.max', 'staff.max', 'messaging.monthly_allowance', 'deposits.enabled', 'inventory.enabled', 'reporting.advanced', 'branding.custom', 'support.priority'];
const isTrial = computed(() => props.subscription.status === 'trialing');
const status = computed(() => ({ trialing: 'Free trial', active: 'Active', past_due: 'Payment needs attention', grace: 'Payment recovery period', restricted: 'Limited access', cancel_scheduled: 'Cancels at period end', canceled: 'Canceled', terminated: 'Closed' }[props.subscription.status] || props.subscription.status.replaceAll('_', ' ')));
const statusTone = computed(() => ['past_due', 'grace', 'restricted'].includes(props.subscription.status) ? 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]' : 'bg-[var(--status-success-soft)] text-[var(--status-success)]');
const primaryDate = computed(() => isTrial.value ? props.trial.ends_at : (props.subscription.cancel_at || props.subscription.current_period_ends_at));
const dateLabel = computed(() => isTrial.value ? 'Trial ends' : (props.subscription.cancel_at ? 'Access ends' : 'Next renewal'));
const planCards = computed(() => (props.plans || []).map(plan => ({ ...plan, price: (plan.prices || []).find(price => price.billing_interval === interval.value) })).filter(plan => plan.price));
const date = value => value ? new Intl.DateTimeFormat('en-GB', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(value)) : '—';
const money = (amount, currency = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount / 100);
const planName = plan => plan.name.replace(/^Good Hours\s+/i, '');
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
const planRank = code => ({ trial: 0, starter: 1, pro: 2 }[code] ?? 0);
const planChangeKind = plan => {
    if (isCurrentPrice(plan)) return 'current';
    if (props.subscription.status !== 'active') return 'unavailable';
    if (props.subscription.billing_interval === 'annual' && plan.price.billing_interval === 'monthly') return 'annual_to_monthly';
    if (planRank(plan.code) < planRank(props.subscription.plan.code)) return 'downgrade';
    return 'upgrade';
};
const planActionLabel = plan => ({
    current: 'Your current plan',
    unavailable: 'Resolve billing status first',
    annual_to_monthly: 'Available after annual term',
    downgrade: 'Schedule downgrade',
    upgrade: plan.code === props.subscription.plan.code ? 'Switch billing interval' : 'Upgrade now',
}[planChangeKind(plan)]);
const selectedChangeKind = computed(() => selectedPlan.value ? planChangeKind(selectedPlan.value) : null);
const selectedChangeDescription = computed(() => {
    if (!selectedPlan.value) return '';
    if (selectedChangeKind.value === 'downgrade') return `Your ${props.subscription.plan.name} access continues through ${date(props.subscription.current_period_ends_at)}. ${selectedPlan.value.name} starts at renewal; no records are deleted.`;
    return `Paddle will apply ${selectedPlan.value.name} immediately and calculate the prorated difference for the rest of this billing period. The provider confirmation updates your access.`;
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
        await axios.post(route('business.billing.plan-change', page.props.tenant.public_id), {
            price_id: selectedPlan.value.price.id,
            timing: selectedChangeKind.value === 'downgrade' ? 'period_end' : 'immediate',
            reason: `Owner self-service ${selectedChangeKind.value}.`,
        });
        window.location.reload();
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

onMounted(async () => {
    if (!props.checkoutRecovery) return;
    recoveringCheckout.value = true;
    try {
        const response = await axios.post(route('business.billing.checkout.confirm', page.props.tenant.public_id), {});
        if (response.data.status === 'confirmed') {
            window.location.replace(`${route('business.billing.show', page.props.tenant.public_id)}?checkout=success`);
            return;
        }
    } catch (exception) {
        errorArea.value = 'checkout';
        error.value = exception.response?.data?.message || 'A recent Paddle checkout could not be confirmed yet. Please try again shortly.';
    }
    recoveringCheckout.value = false;
});
</script>

<template>
    <AppLayout title="Subscription & billing" :business-label="businessLabel">
        <PageHeader eyebrow="Account" title="Subscription & billing" description="Choose the right operating plan for your shop. Payment stays inside a secure Good Hours checkout page.">
            <template #actions><a href="#plans" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[var(--action-primary)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--action-primary-hover)]">View plans <ChevronRightIcon class="size-4" aria-hidden="true" /></a></template>
        </PageHeader>
        <div v-if="checkoutStatus === 'success'" class="mt-5 flex gap-3 rounded-xl border border-[var(--status-success)] bg-[var(--status-success-soft)] p-4" role="status">
            <CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />
            <div><p class="text-sm font-semibold text-[var(--text-strong)]">Purchase completed successfully</p><p class="mt-1 text-sm text-[var(--text-muted)]">Your active plan, renewal details, and Paddle invoice are now recorded below.</p></div>
        </div>
        <p v-else-if="checkoutStatus === 'canceled'" class="mt-5 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]" role="status">Checkout was canceled. No subscription change was made.</p>
        <div v-if="recoveringCheckout" class="mt-5 flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-4 text-sm text-[var(--text-muted)]" role="status"><span class="size-4 animate-spin rounded-full border-2 border-[var(--border-strong)] border-t-[var(--action-primary)]" aria-hidden="true" />Checking your most recent Paddle purchase…</div>
        <p v-if="error && errorArea === 'checkout'" class="mt-5 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]" role="alert">{{ error }}</p>
        <p v-if="error && errorArea !== 'checkout'" class="mt-5 rounded-xl border border-[var(--status-danger)] bg-[var(--status-danger-soft)] p-4 text-sm text-[var(--status-danger)]" role="alert">{{ error }}</p>

        <section class="mt-7 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(19rem,0.75fr)]">
            <article class="relative overflow-hidden rounded-2xl bg-[var(--brand-pine)] px-6 py-7 text-white shadow-[var(--shadow-raised)] sm:px-8">
                <div class="absolute -right-16 -top-20 size-56 rounded-full border border-white/15" aria-hidden="true" /><div class="absolute -bottom-24 right-24 size-44 rounded-full border border-white/10" aria-hidden="true" />
                <div class="relative"><div class="flex flex-wrap items-center gap-3"><span class="inline-flex items-center gap-2 rounded-full bg-white/12 px-3 py-1.5 text-sm font-semibold"><SparklesIcon class="size-4" aria-hidden="true" /> {{ status }}</span><span class="text-sm text-white/70">{{ businessLabel }}</span></div><p class="mt-7 text-sm font-medium text-white/70">Your current plan</p><h2 class="mt-1 text-3xl font-semibold tracking-tight">{{ subscription.plan.name }}</h2><p class="mt-3 max-w-xl text-sm leading-6 text-white/80"><template v-if="isTrial">Explore the full workflow during your trial. Select a plan before it ends to keep uninterrupted access.</template><template v-else>Your subscription, invoices, payment method, and plan controls are all in one place.</template></p><div class="mt-7 flex flex-wrap gap-x-8 gap-y-4 border-t border-white/15 pt-5 text-sm"><div><p class="text-white/60">{{ dateLabel }}</p><p class="mt-1 font-semibold">{{ date(primaryDate) }}</p></div><div><p class="text-white/60">Payment method</p><p class="mt-1 font-semibold">{{ subscription.payment_method_type ? `${subscription.payment_method_type} •••• ${subscription.payment_method_last_four}` : 'Add at secure checkout' }}</p></div><div><p class="text-white/60">Access</p><p class="mt-1 font-semibold">{{ subscription.restriction_level === 'none' ? 'Full access' : subscription.restriction_level.replaceAll('_', ' ') }}</p></div></div></div>
            </article>
            <SurfaceCard :padding="false"><div class="p-6"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-[var(--status-success-soft)] text-[var(--status-success)]"><ShieldCheckIcon class="size-5" aria-hidden="true" /></span><div><h2 class="font-semibold text-[var(--text-strong)]">Billing with confidence</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Clear renewal, payment, and plan controls.</p></div></div><dl class="mt-6 space-y-4 text-sm"><div class="flex items-start gap-3"><CreditCardIcon class="mt-0.5 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><div><dt class="font-semibold text-[var(--text-strong)]">Embedded secure checkout</dt><dd class="mt-1 leading-5 text-[var(--text-muted)]">Enter billing and payment details in a protected Paddle form embedded inside Good Hours.</dd></div></div><div class="flex items-start gap-3"><ArrowPathIcon class="mt-0.5 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><div><dt class="font-semibold text-[var(--text-strong)]">Stay in control</dt><dd class="mt-1 leading-5 text-[var(--text-muted)]">Review your plan, manage payment details, or schedule a change from this page.</dd></div></div><div class="flex items-start gap-3"><DocumentTextIcon class="mt-0.5 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><div><dt class="font-semibold text-[var(--text-strong)]">Receipts and invoices</dt><dd class="mt-1 leading-5 text-[var(--text-muted)]">Subscription evidence remains available here after each billing event.</dd></div></div></dl></div><div class="border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-6 py-4"><AppButton v-if="subscription.provider_customer_id" :href="route('business.billing.portal', page.props.tenant.public_id)" variant="secondary" class="w-full">Manage payment method</AppButton><p v-else class="text-sm leading-5 text-[var(--text-muted)]">Your payment method is added only after you choose a plan.</p></div></SurfaceCard>
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

        <section id="plans" class="scroll-mt-6 mt-10"><div class="flex flex-col gap-4 border-b border-[var(--border-subtle)] pb-5 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--action-primary)]">Plans</p><h2 class="mt-1 text-2xl font-semibold tracking-tight text-[var(--text-strong)]">Pick the plan that fits your shop today.</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--text-muted)]">Upgrade when you need more capacity; historical invoices and subscription evidence remain intact.</p></div><div class="inline-flex w-fit rounded-xl border border-[var(--border-strong)] bg-[var(--surface-raised)] p-1" role="group" aria-label="Billing interval"><button :class="['rounded-lg px-4 py-2 text-sm font-semibold transition-colors', interval === 'monthly' ? 'bg-[var(--brand-pine)] text-white' : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']" @click="interval = 'monthly'">Monthly</button><button :class="['rounded-lg px-4 py-2 text-sm font-semibold transition-colors', interval === 'annual' ? 'bg-[var(--brand-pine)] text-white' : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']" @click="interval = 'annual'">Annual <span class="ml-1 text-xs" :class="interval === 'annual' ? 'text-white/75' : 'text-[var(--status-success)]'">Save up to 17%</span></button></div></div>
            <div v-if="planCards.length" class="mt-6 grid gap-5 lg:grid-cols-2"><article v-for="plan in planCards" :key="plan.id" :class="['relative rounded-2xl border bg-[var(--surface-raised)] p-6 sm:p-7', plan.code === 'pro' ? 'border-[var(--brand-pine)] shadow-[var(--shadow-raised)]' : 'border-[var(--border-subtle)]']"><span v-if="plan.code === 'pro'" class="absolute right-5 top-0 -translate-y-1/2 rounded-full bg-[var(--brand-pine)] px-3 py-1 text-xs font-semibold text-white">Most popular</span><div class="flex items-start justify-between gap-4"><div><h3 class="text-xl font-semibold text-[var(--text-strong)]">{{ planName(plan) }}</h3><p class="mt-2 min-h-10 text-sm leading-5 text-[var(--text-muted)]">{{ plan.description }}</p></div><span v-if="isCurrentPrice(plan)" class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusTone">Current</span></div><div class="mt-7 flex items-end gap-2"><span class="text-4xl font-semibold tracking-tight text-[var(--text-strong)]">{{ money(plan.price.amount_minor, plan.price.currency) }}</span><span class="mb-1 text-sm text-[var(--text-muted)]">/{{ interval === 'annual' ? 'year' : 'month' }}</span></div><p class="mt-2 min-h-5 text-sm text-[var(--status-success)]"><template v-if="annualSavings(plan)">Save {{ money(annualSavings(plan), plan.price.currency) }} compared with monthly</template><template v-else>&nbsp;</template></p><ul class="mt-6 space-y-3 border-t border-[var(--border-subtle)] pt-6 text-sm text-[var(--text-default)]"><li v-for="item in visibleFeatures(plan)" :key="item" class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />{{ item }}</li></ul><AppButton v-if="subscription.provider_subscription_id" class="mt-7 w-full" :variant="planChangeKind(plan) === 'upgrade' ? 'primary' : 'secondary'" :disabled="busy || ['current', 'unavailable', 'annual_to_monthly'].includes(planChangeKind(plan))" @click="openPlanChange(plan)">{{ planActionLabel(plan) }}</AppButton><AppButton v-else class="mt-7 w-full" :href="checkoutUrl(plan.price)" :variant="plan.code === 'pro' ? 'primary' : 'secondary'">Review and subscribe</AppButton></article></div>
            <SurfaceCard v-else class="mt-6" title="Plans are being prepared" description="No active Paddle prices are available yet for this environment."><p class="text-sm leading-6 text-[var(--text-muted)]">Your trial remains available while the billing catalog is synchronized.</p></SurfaceCard>
            <div class="mt-6 rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-5 py-4"><div class="flex items-start gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-lg bg-[var(--surface-raised)] text-[var(--action-primary)]"><LockClosedIcon class="size-4" aria-hidden="true" /></span><p class="text-sm leading-5 text-[var(--text-muted)]"><strong class="font-semibold text-[var(--text-strong)]">Before you pay:</strong> Review the plan on the next page, then enter payment details in Paddle’s embedded secure form. Good Hours never stores your card number.</p></div></div>
        </section>

        <section class="mt-10 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(19rem,0.75fr)]"><SurfaceCard title="Invoices & payment history" description="Every Good Hours subscription invoice is retained here."><div v-if="invoices.length" class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr class="border-b border-[var(--border-subtle)] text-[var(--text-muted)]"><th class="py-3 pr-5 font-medium">Invoice</th><th class="py-3 pr-5 font-medium">Issued</th><th class="py-3 pr-5 font-medium">Status</th><th class="py-3 pr-5 text-right font-medium">Total</th><th class="py-3 text-right font-medium">Document</th></tr></thead><tbody><tr v-for="invoice in invoices" :key="invoice.public_id" class="border-b border-[var(--border-subtle)] last:border-0"><td class="py-4 pr-5 font-semibold text-[var(--text-strong)]">{{ invoice.number || invoice.public_id }}</td><td class="py-4 pr-5">{{ date(invoice.issued_at) }}</td><td class="py-4 pr-5 capitalize">{{ invoice.status }}</td><td class="py-4 pr-5 text-right font-semibold">{{ money(invoice.total_minor, invoice.currency) }}</td><td class="py-4 text-right"><a v-if="invoice.hosted_url" class="font-semibold text-[var(--action-primary)] hover:underline" :href="invoice.hosted_url" target="_blank" rel="noopener">View</a><span v-else class="text-[var(--text-muted)]">Recorded</span></td></tr></tbody></table></div><div v-else class="flex min-h-32 items-center gap-4 rounded-xl bg-[var(--surface-subtle)] p-5"><DocumentTextIcon class="size-7 shrink-0 text-[var(--text-muted)]" aria-hidden="true" /><div><p class="font-semibold text-[var(--text-strong)]">No invoices yet</p><p class="mt-1 text-sm leading-5 text-[var(--text-muted)]">Your first invoice will appear here after Paddle confirms your subscription.</p></div></div></SurfaceCard><SurfaceCard title="Your current access" description="Capabilities available to this business today."><dl class="divide-y divide-[var(--border-subtle)] text-sm"><div v-for="(value, key) in entitlements" :key="key" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"><dt class="text-[var(--text-muted)]">{{ labels[key] || key.replaceAll('.', ' ') }}</dt><dd :class="['text-right font-semibold', typeof value === 'boolean' && !value ? 'text-[var(--text-muted)]' : 'text-[var(--text-strong)]']">{{ typeof value === 'boolean' ? (value ? 'Included' : 'Not included') : value }}</dd></div></dl><p class="mt-5 text-sm" :class="exportAvailable ? 'text-[var(--status-success)]' : 'text-[var(--status-danger)]'">Data export is {{ exportAvailable ? 'available' : 'outside the documented availability window' }}.</p></SurfaceCard></section>

        <AppDialog id="plan-change" ref="planDialog" :title="selectedChangeKind === 'downgrade' ? `Schedule ${selectedPlan?.name || 'plan'}?` : `Change to ${selectedPlan?.name || 'plan'}?`" :description="selectedChangeDescription" :confirm-label="selectedChangeKind === 'downgrade' ? 'Schedule downgrade' : 'Confirm upgrade'" @confirm="changePlan">
            <div v-if="selectedPlan" class="rounded-xl bg-[var(--surface-subtle)] p-4 text-sm"><div class="flex items-center gap-3"><ArrowUpCircleIcon class="size-5 text-[var(--action-primary)]" aria-hidden="true" /><div><p class="font-semibold text-[var(--text-strong)]">{{ selectedPlan.name }}</p><p class="text-[var(--text-muted)]">{{ money(selectedPlan.price.amount_minor, selectedPlan.price.currency) }} / {{ selectedPlan.price.billing_interval === 'annual' ? 'year' : 'month' }}</p></div></div></div>
        </AppDialog>
        <AppDialog id="cancel-subscription" ref="cancelDialog" title="Cancel at the end of this billing period?" :description="`Your ${subscription.plan.name} access continues until ${date(subscription.current_period_ends_at)}. Future renewals stop, and your business records are retained.`" confirm-label="Cancel at renewal" destructive @confirm="confirmCancellation">
            <label class="block text-sm font-semibold text-[var(--text-strong)]">Main reason <select v-model="cancellationReason" class="gh-input mt-2 block w-full"><option>No longer needed</option><option>Too expensive</option><option>Missing a feature</option><option>Closing the business</option><option>Switching to another product</option><option>Other</option></select></label>
        </AppDialog>
    </AppLayout>
</template>
