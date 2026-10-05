<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { CreditCardIcon, ArrowDownTrayIcon, ArrowTopRightOnSquareIcon, CheckCircleIcon, ExclamationCircleIcon, ShieldCheckIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import CapacityBillingPanel from '@/Components/Billing/CapacityBillingPanel.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import { billingMoney, billingDate, billingLabels, subscriptionStatus, invoiceStatus, yearlySavings, planAction, limitState, differingFeatures, cardExpiryState } from '@/Support/billingWorkspace';
import '../../../css/billing.css';

const props = defineProps({ capacityPricing: Object, billingCountry: String, capacityRequest: Object, smsCredits: Number, smsPacks: Array, smsPurchase: Object, businessLabel: String, subscription: Object, trial: Object, plans: Array, planRanks: Object, entitlements: Object, invoices: Object, usage: Object, attention: Array, billingTimeZone: String, billingLocale: String, exportAvailable: Boolean, checkoutStatus: String, checkoutAttempt: Object, pendingChange: Object, billingReadiness: Object, planChangeStatus: String, planChangeTargetPriceId: Number, portalReturned: Boolean });
const page = usePage();
const interval = ref(props.subscription.billing_interval || 'monthly');
const busy = ref('');
const error = ref('');
const notice = ref('');
const planDialog = ref(null);
const cancelDialog = ref(null);
const invoiceDialog = ref(null);
const selectedPlan = ref(null);
const selectedInvoice = ref(null);
const invoiceLoading = ref(false);
const invoiceError = ref('');
const cancellationReason = ref('');
const recovery = ref(['pending', 'processing'].includes(props.checkoutAttempt?.status) || (props.planChangeStatus === 'success' && props.planChangeTargetPriceId) ? 'checking' : '');
const controller = new AbortController();
let mounted = true;
let invoiceRequest = 0;
let timer;
const money = (minor, currency) => billingMoney(minor, currency, props.billingLocale);
const date = value => billingDate(value, props.billingTimeZone, props.billingLocale);
const name = plan => plan?.name?.replace(/^ClipperDesk\s+/i, '') || 'Your plan';
const unit = value => value === 'annual' ? 'year' : 'month';
const isTrial = computed(() => props.subscription.status === 'trialing');
const ended = computed(() => ['terminated', 'canceled'].includes(props.subscription.status));
const needsAttention = computed(() => ['past_due', 'grace'].includes(props.subscription.status) || props.attention?.length || (props.subscription.status === 'restricted' && props.subscription.has_provider_subscription));
const cards = computed(() => (props.plans || []).map(plan => ({ ...plan, price: plan.prices.find(price => price.billing_interval === interval.value) })).filter(plan => plan.price));
const differenceKeys = computed(() => differingFeatures(props.plans || []));
const limits = computed(() => ['staff.max', 'locations.max', 'messaging.monthly_allowance'].map(key => ({ key, label: billingLabels[key], used: props.usage?.[key] ?? 0, limit: props.entitlements?.[key], state: limitState(props.usage?.[key] ?? 0, props.entitlements?.[key]) })));
const nextLabel = computed(() => isTrial.value ? 'Trial ends' : ended.value ? 'Access ended' : props.subscription.cancel_at ? 'Access until' : 'Next renewal');
const nextDate = computed(() => isTrial.value ? props.trial.ends_at : ended.value ? props.subscription.ended_at : props.subscription.cancel_at || props.subscription.current_period_ends_at);
const cardLabel = computed(() => props.subscription.payment_method_last_four ? `${props.subscription.payment_method_type || 'Card'} •••• ${props.subscription.payment_method_last_four}` : props.subscription.has_billing_account ? 'Check saved payment details' : 'No payment method added');
const expiry = computed(() => cardExpiryState(props.subscription.payment_method_expiry_month,props.subscription.payment_method_expiry_year));
const action = plan => planAction(props.subscription, plan, plan.price, props.planRanks || {}, Boolean(props.pendingChange));
const actionLabel = plan => ({ current: 'Current plan', subscribe: 'Review subscription', support: 'Review downgrade', unavailable: props.pendingChange ? 'Change already scheduled' : 'Resolve billing first', interval_switch: `Switch to ${interval.value === 'annual' ? 'yearly' : 'monthly'}`, upgrade: `Upgrade to ${name(plan)}` }[action(plan)]);
const selectedAction = computed(() => selectedPlan.value ? action(selectedPlan.value) : '');
const valueFor = (plan, key) => plan.entitlements.find(item => item.definition.key === key)?.value;
const featureValue = value => value === true ? 'Included' : value === false || value == null ? 'Not included' : Number(value).toLocaleString(props.billingLocale);
const exceeded = computed(() => selectedPlan.value ? limits.value.filter(item => typeof valueFor(selectedPlan.value, item.key) === 'number' && item.used > valueFor(selectedPlan.value, item.key)) : []);
const lostFeatures = computed(() => selectedPlan.value ? Object.entries(props.entitlements).filter(([key, value]) => value === true && valueFor(selectedPlan.value, key) === false).map(([key]) => billingLabels[key]).filter(Boolean) : []);
const url = (routeName, extra = {}) => route(routeName, { business: page.props.tenant.public_id, ...extra });
const checkoutUrl = plan => url('business.billing.checkout.form', { price_id: plan.price.id });
const refresh = () => router.reload({ preserveScroll: true });
function openPlan(plan) { selectedPlan.value = plan; error.value = ''; planDialog.value?.open(); }
async function changePlan() {
    if (!props.billingReadiness.checkout_ready || busy.value || !selectedPlan.value || selectedAction.value === 'support') return;
    busy.value = 'plan'; error.value = '';
    try {
        const response = await axios.post(url('business.billing.plan-change'), { price_id: selectedPlan.value.price.id, reason: 'Owner reviewed subscription change.' }, { signal: controller.signal });
        if (!response.data.url) throw new Error('Missing confirmation URL');
        window.location.assign(response.data.url);
    } catch (exception) {
        if (!axios.isCancel(exception)) error.value = exception.response?.data?.message || 'We could not confirm the request. Refresh billing to check its status before trying again.';
        busy.value = '';
    }
}
async function subscriptionAction(kind) {
    if (busy.value) return;
    busy.value = kind; error.value = '';
    try {
        await axios.post(url(`business.billing.${kind}`), { reason: cancellationReason.value || null, version: props.subscription.version }, { signal: controller.signal });
        cancelDialog.value?.close();
        notice.value = kind === 'cancel' ? 'Cancellation scheduled. Your paid access continues until the date shown below.' : 'Your subscription will continue renewing.';
        refresh();
    } catch (exception) {
        if (!axios.isCancel(exception)) error.value = exception.response?.data?.message || 'We could not confirm this request. Refresh billing to check its status before trying again.';
    } finally { busy.value = ''; }
}
async function openInvoice(invoice) {
    const request = ++invoiceRequest;
    selectedInvoice.value = invoice; invoiceLoading.value = true; invoiceError.value = ''; invoiceDialog.value?.open();
    try {
        const response = await axios.get(url('business.billing.invoices.show', { invoice: invoice.public_id }), { signal: controller.signal });
        if (request === invoiceRequest) selectedInvoice.value = response.data;
    } catch (exception) {
        if (request === invoiceRequest && !axios.isCancel(exception)) invoiceError.value = 'Invoice details could not be loaded. Close and reopen to try again.';
    } finally { if (request === invoiceRequest) invoiceLoading.value = false; }
}
async function reconcile() {
    if (recovery.value !== 'checking') return;
    const checkout = ['pending', 'processing'].includes(props.checkoutAttempt?.status);
    for (let attempt = 0; attempt < 8 && mounted; attempt++) {
        try {
            const response = await axios.post(url(checkout ? 'business.billing.checkout.status' : 'business.billing.plan-change.status'), checkout ? { attempt_id: props.checkoutAttempt.attempt_id } : { price_id: props.planChangeTargetPriceId }, { signal: controller.signal });
            if (['confirmed', 'scheduled'].includes(response.data.status)) {
                recovery.value = 'confirmed';
                notice.value = response.data.status === 'scheduled' ? `Billing change verified. Takes effect ${date(response.data.effective_at)}.` : 'Subscription verified. Your billing details are up to date.';
                router.visit(url('business.billing.show'), { preserveScroll: true });
                return;
            }
            if (['failed', 'expired', 'superseded'].includes(response.data.status)) { recovery.value = 'closed'; return; }
        } catch (exception) {
            if (axios.isCancel(exception)) return;
            if (exception.response && exception.response.status !== 202 && exception.response.status !== 503 && exception.response.status !== 429) break;
        }
        await new Promise(resolve => { timer = window.setTimeout(resolve, 2000); });
    }
    if (mounted) recovery.value = 'pending';
}
async function refreshAccount() {
    if (busy.value || !props.billingReadiness.secret_configured) return;
    busy.value = 'refresh'; error.value = '';
    try { await axios.post(url('business.billing.refresh'), {}, { signal: controller.signal }); refresh(); }
    catch (exception) { if (!axios.isCancel(exception)) error.value = exception.response?.data?.message || 'Billing details could not be verified yet. Please check again shortly.'; }
    finally { busy.value = ''; }
}
onMounted(() => { reconcile(); if (props.portalReturned) refreshAccount(); });
onUnmounted(() => { mounted = false; controller.abort(); window.clearTimeout(timer); });
</script>

<template>
    <AppLayout title="Subscription & billing" :business-label="businessLabel">
        <div class="billing-workspace">
            <PageHeader title="Subscription & billing" description="Your ClipperDesk plan, payments and account billing."><template #actions><AppButton v-if="subscription.has_billing_account" variant="secondary" :disabled="Boolean(busy) || !billingReadiness.secret_configured" @click="refreshAccount">{{ busy==='refresh' ? 'Checking billing…' : 'Refresh billing' }}</AppButton></template></PageHeader>
            <nav class="billing-nav" aria-label="Billing sections"><a href="#overview">Overview</a><a href="#history">Billing history</a><a :href="capacityPricing ? '#capacity' : '#plans'">Plans</a><a href="#usage">Usage & limits</a></nav>
            <p v-if="notice" class="billing-alert success" role="status">{{ notice }}</p>
            <p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p>
            <p v-if="recovery === 'checking'" class="billing-alert" role="status">Confirming your subscription… This page is checking the billing service. Please wait before starting another request.</p>
            <div v-if="recovery === 'pending'" class="billing-alert warning" role="status"><strong>Confirmation is taking longer than expected.</strong><p>Payment has not been marked as failed. Refresh billing to check the latest verified status before trying again.</p><AppButton variant="secondary" @click="refresh">Check billing status</AppButton></div>
            <p v-if="recovery === 'closed'" class="billing-alert warning" role="status">This checkout is no longer active. Review your current plan below before starting another checkout.</p>
            <p v-if="checkoutStatus === 'canceled' || planChangeStatus === 'canceled'" class="billing-alert" role="status">You returned from secure billing. Your latest recorded plan is shown below; closing that page alone does not confirm a change.</p>

            <section id="overview" class="billing-summary" aria-labelledby="current-plan-title">
                <div class="billing-plan-heading"><div><p class="billing-eyebrow">Current plan</p><h2 id="current-plan-title">{{ name(subscription.plan) }}</h2></div><span class="billing-status" :class="needsAttention ? 'warning' : ended ? 'neutral' : 'success'">{{ subscriptionStatus(subscription) }}</span></div>
                <div class="billing-price" v-if="isTrial"><strong>Free</strong><span>during your trial</span></div>
                <div class="billing-price" v-else-if="subscription.price"><strong>{{ money(subscription.price.amount_minor, subscription.price.currency) }}</strong><span>/ {{ unit(subscription.billing_interval) }} <small>{{ subscription.price.currency }}</small></span></div>
                <p v-else class="billing-subtext">Your subscription price needs verification. Check secure billing for the recorded amount.</p>
                <p class="billing-subtext" v-if="isTrial">Trial ends {{ date(trial.ends_at) }}. No automatic charge is scheduled. Choose a paid plan when you are ready; after the trial, existing records remain readable and changes pause.</p>
                <p class="billing-subtext" v-else-if="subscription.status==='restricted' && !subscription.has_provider_subscription">Your trial has ended. Existing records remain readable. Choose a plan to resume changes.</p>
                <p class="billing-subtext" v-else-if="ended">Your subscription has ended. Business records are retained. Choose a plan to start a new paid subscription.</p>
                <p class="billing-subtext" v-else-if="subscription.cancel_at">Renewals stop after {{ date(subscription.cancel_at) }}. Existing unpaid invoices still require payment.</p>
                <p class="billing-subtext" v-else>Subscription price. Taxes, discounts and credits on a charge are shown on its invoice.</p>
                <dl class="billing-summary-facts"><div><dt>{{ nextLabel }}</dt><dd>{{ date(nextDate) }}</dd></div><div><dt>Billing cycle</dt><dd>{{ isTrial ? 'Free trial' : subscription.billing_interval === 'annual' ? 'Yearly · paid upfront' : subscription.billing_interval === 'monthly' ? 'Monthly' : 'Not set' }}</dd></div><div><dt>Payment method</dt><dd>{{ cardLabel }}<small v-if="subscription.payment_method_expiry_month" class="billing-card-expiry">Expires {{ String(subscription.payment_method_expiry_month).padStart(2,'0') }}/{{ subscription.payment_method_expiry_year }}</small></dd></div><div><dt>Business access</dt><dd>{{ subscription.restriction_level === 'closed' ? 'Billing & available exports' : subscription.restriction_level === 'read_only' || ended ? 'Read-only' : 'Full access' }}</dd></div></dl>
                <p v-if="expiry" class="billing-availability">{{ expiry==='expired' ? 'Your saved card has expired.' : 'Your saved card expires soon.' }} Update the payment method before the next payment.</p>
                <div v-if="attention?.length || needsAttention" class="billing-attention" role="status"><ExclamationCircleIcon aria-hidden="true"/><div><strong>Payment needs attention</strong><p v-for="item in attention" :key="item.currency">{{ money(item.amount_minor, item.currency) }} outstanding<span v-if="item.failed_at"> · Last failed attempt {{ date(item.failed_at) }}</span><span v-if="item.next_retry_at"> · Next retry {{ date(item.next_retry_at) }}</span>.</p><p v-if="subscription.grace_ends_at && subscription.restriction_level !== 'read_only'">Your account remains active until {{ date(subscription.grace_ends_at) }} while payment recovery continues.</p><p v-else-if="subscription.restriction_level === 'read_only'">Existing records remain readable. Update payment details to resolve the billing issue.</p><p v-else>Review secure billing for the amount due and payment options.</p></div></div>
                <p v-if="subscription.customer_balance_minor < 0 && subscription.balance_currency" class="billing-credit">Available billing credit: <strong>{{ money(-subscription.customer_balance_minor,subscription.balance_currency) }}</strong>. Applied to future invoices in {{ subscription.balance_currency }}.</p>
                <div v-if="pendingChange" class="billing-alert">{{ pendingChange.plan_name }} is scheduled for {{ date(pendingChange.effective_at) }}. Current access continues until then.</div>
                <div class="billing-summary-actions"><div><AppButton v-if="subscription.has_billing_account" :href="url('business.billing.portal')" variant="secondary" :disabled="Boolean(busy) || !billingReadiness.secret_configured"><CreditCardIcon class="size-4" aria-hidden="true"/>{{ needsAttention ? 'Update payment method' : 'Manage payment method' }}</AppButton><a :href="capacityPricing ? '#capacity' : '#plans'" class="billing-link">{{ ended || isTrial ? 'Choose a plan' : 'Change plan' }}</a></div><AppButton v-if="subscription.status === 'cancel_scheduled'" :disabled="Boolean(busy) || !billingReadiness.secret_configured" @click="subscriptionAction('reactivate')">{{ busy === 'reactivate' ? 'Confirming…' : 'Keep my subscription' }}</AppButton><AppButton v-else-if="subscription.status === 'active' && subscription.has_provider_subscription" variant="quiet" :disabled="Boolean(busy) || recovery==='checking'" @click="error=''; cancelDialog?.open()">Cancel subscription</AppButton></div>
            </section>
            <p v-if="!billingReadiness.checkout_ready" class="billing-availability" role="status">Secure billing changes are temporarily unavailable. Your recorded plan and invoices remain available. Contact support if you need help.</p>

            <div class="billing-content-grid">
                <section id="history" class="billing-panel" aria-labelledby="billing-history-title"><header class="billing-section-heading"><div><h2 id="billing-history-title">Billing history</h2><p>Invoices for your ClipperDesk subscription.</p></div><span class="billing-meta">{{ invoices.total }} {{ invoices.total === 1 ? 'invoice' : 'invoices' }}</span></header>
                    <template v-if="invoices.data?.length"><table class="billing-invoice-table"><caption class="sr-only">Platform subscription invoices, page {{ invoices.current_page }}</caption><thead><tr><th scope="col">Invoice / issued</th><th scope="col">Status</th><th scope="col" class="amount">Total</th><th scope="col"><span class="sr-only">Documents</span></th></tr></thead><tbody><tr v-for="invoice in invoices.data" :key="invoice.public_id"><td><button class="billing-invoice-name" @click="openInvoice(invoice)">{{ invoice.number || 'Subscription invoice' }}</button><span class="billing-invoice-date">{{ date(invoice.issued_at) }}</span></td><td><span class="billing-status" :class="invoice.status === 'paid' ? 'success' : invoice.status === 'open' || invoice.status === 'uncollectible' ? 'warning' : 'neutral'">{{ invoiceStatus(invoice) }}</span></td><td class="amount">{{ money(invoice.total_minor, invoice.currency) }}<small>{{ invoice.currency }}</small></td><td class="billing-documents"><a v-if="invoice.pdf_url" :href="invoice.pdf_url" target="_blank" rel="noopener noreferrer" :aria-label="`Download invoice ${invoice.number || ''} (opens in a new tab)`"><ArrowDownTrayIcon aria-hidden="true"/></a><a v-else-if="invoice.hosted_url" :href="invoice.hosted_url" target="_blank" rel="noopener noreferrer" :aria-label="`View invoice ${invoice.number || ''} securely (opens in a new tab)`"><ArrowTopRightOnSquareIcon aria-hidden="true"/></a><button v-else @click="openInvoice(invoice)" :aria-label="`View details for invoice ${invoice.number || ''}`"><DocumentTextIcon aria-hidden="true"/></button></td></tr></tbody></table>
                    <footer class="billing-pagination" v-if="invoices.last_page > 1"><span>{{ invoices.from }}–{{ invoices.to }} of {{ invoices.total }}</span><div><AppButton variant="secondary" :href="invoices.prev_page_url || undefined" :disabled="!invoices.prev_page_url" preserve-scroll>Previous</AppButton><AppButton variant="secondary" :href="invoices.next_page_url || undefined" :disabled="!invoices.next_page_url" preserve-scroll>Next</AppButton></div></footer></template>
                    <div v-else class="billing-empty"><DocumentTextIcon aria-hidden="true"/><div><h3>No invoices yet</h3><p>Your subscription invoices will appear here once issued.</p></div></div>
                </section>
                <aside class="billing-sidebar"><section id="usage" class="billing-panel" aria-labelledby="usage-title"><header class="billing-section-heading"><div><h2 id="usage-title">Usage & limits</h2><p>Current business-wide usage.</p></div></header><div class="billing-usage"><div v-for="item in limits" :key="item.key" class="billing-usage-item"><div><span>{{ item.label }}</span><strong>{{ item.used.toLocaleString(billingLocale) }}<span v-if="typeof item.limit === 'number'"> / {{ item.limit.toLocaleString(billingLocale) }}</span></strong></div><progress v-if="typeof item.limit === 'number' && item.limit > 0" :value="Math.min(item.used, item.limit)" :max="item.limit" :aria-label="`${item.label}: ${item.used} of ${item.limit}`" :class="item.state"/><p v-if="item.state === 'over'" class="billing-warning">Over the plan limit. Review {{ item.key === 'staff.max' ? 'Team & availability' : item.key === 'locations.max' ? 'Business setup' : 'Client notifications' }} or choose a larger plan.</p><p v-else-if="item.state === 'full' || item.state === 'near'" class="billing-warning">{{ item.state === 'full' ? 'Plan limit reached.' : 'Approaching your plan limit.' }}</p><p v-if="item.key === 'messaging.monthly_allowance'">{{ subscription.capacity ? 'Included credits reset monthly. Prepaid credits are shown below.' : 'Usage follows your existing subscription allowance period.' }}</p></div></div></section>
                    <section class="billing-panel billing-details"><h2>Billing details</h2><dl v-if="subscription.billing_name || subscription.billing_email" class="billing-contact"><div v-if="subscription.billing_name"><dt>Legal name</dt><dd>{{ subscription.billing_name }}</dd></div><div v-if="subscription.billing_email"><dt>Billing email</dt><dd>{{ subscription.billing_email }}</dd></div></dl><p>Manage your legal name, billing email, address and tax ID securely. These details are separate from your public salon profile.</p><AppButton v-if="subscription.has_billing_account" variant="secondary" :href="url('business.billing.portal')" :disabled="Boolean(busy) || !billingReadiness.secret_configured">Update billing details<ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true"/></AppButton><p v-else>Set up billing details when you subscribe.</p></section>
                </aside>
            </div>

            <CapacityBillingPanel v-if="capacityPricing" :catalog="capacityPricing" :country="billingCountry" :current="subscription.capacity" :usage="usage" :business-id="page.props.tenant.public_id" :request="capacityRequest" :credits="smsCredits" :packs="smsPacks" :purchase="smsPurchase" :ready="billingReadiness.checkout_ready" :time-zone="billingTimeZone"/>
            <section v-if="!subscription.capacity && !(isTrial && capacityPricing?.markets?.some(market => Object.values(market.intervals).some(rate => rate.ready)))" id="plans" class="billing-panel billing-plans" aria-labelledby="plans-title"><header class="billing-section-heading"><div><h2 id="plans-title">Compare plans</h2><p>Choose the capacity and features your business needs.</p></div><div class="cd-segmented" role="group" aria-label="Billing cycle"><button :aria-pressed="interval === 'monthly'" :class="{selected: interval==='monthly'}" @click="interval='monthly'">Monthly</button><button :aria-pressed="interval === 'annual'" :class="{selected: interval==='annual'}" @click="interval='annual'">Yearly</button></div></header>
                <div class="billing-plan-grid" v-if="cards.length"><article v-for="plan in cards" :key="plan.id" :class="{current: action(plan)==='current'}"><div class="billing-card-heading"><h3>{{ name(plan) }}</h3><span v-if="subscription.billing_plan_id === plan.id && !isTrial && !ended" class="billing-status neutral">Your plan</span></div><div class="billing-price"><strong>{{ money(plan.price.amount_minor, plan.price.currency) }}</strong><span>/ {{ unit(interval) }}<small>{{ plan.price.currency }}</small></span></div><p class="billing-equivalent" v-if="interval === 'annual'">About {{ money(Math.round(plan.price.amount_minor / 12), plan.price.currency) }} / month · billed yearly<br/><span v-if="yearlySavings(plan, plan.price)>0">Save {{ money(yearlySavings(plan, plan.price), plan.price.currency) }} each year compared with monthly.</span></p><p class="billing-equivalent" v-else>Paid monthly. Review the final total before confirming.</p><ul><li v-for="key in ['locations.max','staff.max','messaging.monthly_allowance']" :key="key"><CheckCircleIcon aria-hidden="true"/><span>{{ featureValue(valueFor(plan,key)) }} {{ key === 'staff.max' ? 'active team members' : key === 'locations.max' ? (valueFor(plan,key)===1 ? 'location' : 'locations') : 'SMS per allowance period' }}</span></li></ul><AppButton v-if="action(plan)==='subscribe'" :href="checkoutUrl(plan)" :disabled="recovery==='checking' || Boolean(busy)" variant="secondary">{{ actionLabel(plan) }}</AppButton><AppButton v-else :variant="action(plan)==='upgrade' ? 'primary' : 'secondary'" :disabled="Boolean(busy) || ['current','unavailable'].includes(action(plan)) || recovery==='checking'" @click="openPlan(plan)">{{ actionLabel(plan) }}</AppButton></article></div><p v-else class="billing-empty">No plans are currently available. Your existing access is shown above.</p>
                <details v-if="differenceKeys.length" class="billing-comparison"><summary>Compare included features</summary><table><caption class="sr-only">Features and limits that differ between plans</caption><thead><tr><th scope="col">Feature</th><th v-for="plan in plans" :key="plan.id" scope="col">{{ name(plan) }}</th></tr></thead><tbody><tr v-for="key in differenceKeys" :key="key"><th scope="row">{{ billingLabels[key] }}</th><td v-for="plan in plans" :key="plan.id">{{ featureValue(valueFor(plan,key)) }}</td></tr></tbody></table></details>
                <p class="billing-trust"><ShieldCheckIcon aria-hidden="true"/>Exact taxes, discounts, credits and prorations are reviewed on the secure confirmation page before a payment or plan change.</p>
            </section>
            <p class="billing-retention"><span v-if="subscription.account_checked_at">Billing details checked {{ date(subscription.account_checked_at) }}. </span>Business records are retained when billing changes. Data export is {{ exportAvailable ? 'available' : 'outside the available export window' }}<span v-if="subscription.export_available_until"> through {{ date(subscription.export_available_until) }}</span>. Dates use {{ billingTimeZone?.replaceAll('_',' ') }}.</p>

            <AppDialog id="billing-plan-change" ref="planDialog" :title="selectedAction === 'support' ? 'Review a downgrade' : selectedAction === 'interval_switch' ? 'Change billing cycle' : 'Review plan change'" :confirm-label="busy ? 'Opening secure billing…' : 'Review exact charge'" :confirm-disabled="Boolean(busy) || !billingReadiness.checkout_ready" :close-on-confirm="false" :can-close="()=>!busy" @confirm="changePlan">
                <div v-if="selectedPlan" class="billing-change"><dl><div><dt>Current plan</dt><dd>{{ name(subscription.plan) }}<span v-if="subscription.price">{{ money(subscription.price.amount_minor,subscription.price.currency) }} / {{ unit(subscription.billing_interval) }}</span></dd></div><div><dt>Selected plan</dt><dd>{{ name(selectedPlan) }}<span>{{ money(selectedPlan.price.amount_minor,selectedPlan.price.currency) }} / {{ unit(selectedPlan.price.billing_interval) }}</span></dd></div></dl><p v-if="selectedAction==='support'">Downgrades currently require support review and take effect at renewal. Your current plan stays active while limits and access are reviewed.</p><template v-else><p v-if="subscription.billing_interval==='annual' && selectedPlan.price.billing_interval==='monthly'">Monthly billing starts at the next renewal. Your paid yearly term is preserved.</p><p v-else>The secure confirmation page shows the effective date, unused-plan credit, new-plan cost and net charge. Your access changes only after the billing service confirms.</p><p class="billing-subtext">Opening the confirmation page does not apply this change.</p></template><p v-for="item in exceeded" :key="item.key" class="billing-alert warning">{{ name(selectedPlan) }} allows {{ valueFor(selectedPlan,item.key) }} {{ item.label.toLowerCase() }}. You currently use {{ item.used }}. Review usage with support before switching.</p><p v-if="lostFeatures.length">Features removed: {{ lostFeatures.join(', ') }}. Existing business records remain stored.</p><p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p></div>
                <template #footer v-if="selectedAction==='support'"><AppButton variant="secondary" @click="planDialog?.close()">Close review</AppButton><a v-if="page.props.brand?.support_email" :href="`mailto:${page.props.brand.support_email}`" class="billing-link">Contact billing support</a></template>
            </AppDialog>
            <AppDialog id="billing-cancel" ref="cancelDialog" title="Cancel subscription" :description="`Your ${name(subscription.plan)} plan remains active until ${date(subscription.current_period_ends_at)}. Renewal charges stop after that date. Existing unpaid invoices still need to be paid.`" :confirm-label="busy ? 'Confirming cancellation…' : 'Cancel at renewal'" cancel-label="Keep subscription" :confirm-disabled="Boolean(busy) || !billingReadiness.secret_configured" :close-on-confirm="false" :can-close="()=>!busy" destructive @confirm="subscriptionAction('cancel')"><p class="billing-subtext">Your business records remain stored. You can keep this subscription before the end date; after it ends, a new subscription is needed. The existing export policy continues to apply.</p><label class="billing-reason">Reason <span>(optional)</span><AppSelect v-model="cancellationReason" class="cd-input"><option value="">Prefer not to say</option><option>Too expensive</option><option>No longer needed</option><option>Missing a feature</option><option>Closing the business</option><option>Other</option></AppSelect></label><p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p></AppDialog>
            <AppDialog id="billing-invoice" ref="invoiceDialog" :title="selectedInvoice?.number || 'Subscription invoice'" description="Invoice for your ClipperDesk account." @cancel="invoiceRequest++"><div v-if="invoiceLoading" class="billing-skeleton" role="status">Loading invoice details…</div><p v-else-if="invoiceError" class="billing-alert warning" role="alert">{{ invoiceError }}</p><div v-else-if="selectedInvoice" class="billing-invoice-detail"><p>{{ date(selectedInvoice.issued_at) }} · {{ invoiceStatus(selectedInvoice) }}</p><ul v-if="selectedInvoice.line_items?.length"><li v-for="(line,i) in selectedInvoice.line_items" :key="i"><span>{{ line.description }}<small v-if="line.period_started_at">{{ date(line.period_started_at*1000) }} – {{ date(line.period_ends_at*1000) }}</small></span><strong>{{ money(line.amount_minor,selectedInvoice.currency) }}</strong></li></ul><dl><div v-for="item in [['Subtotal','subtotal_minor'],['Discount','discount_minor'],['Tax','tax_minor'],['Total','total_minor'],['Amount paid','amount_paid_minor']]" :key="item[1]"><dt>{{ item[0] }}</dt><dd>{{ item[1]==='discount_minor' && selectedInvoice[item[1]]>0 ? '−' : '' }}{{ money(selectedInvoice[item[1]],selectedInvoice.currency) }}</dd></div><div><dt>Remaining</dt><dd>{{ money(selectedInvoice.amount_remaining_minor ?? Math.max(0,selectedInvoice.amount_due_minor-selectedInvoice.amount_paid_minor),selectedInvoice.currency) }}</dd></div></dl><p v-if="selectedInvoice.hosted_url">The secure invoice contains the complete tax, credit and payment breakdown.</p><p v-else>Document download is not available for this recorded invoice. Contact support if you need the complete billing document.</p></div><template #footer><AppButton variant="secondary" @click="invoiceRequest++; invoiceDialog?.close()">Close</AppButton><a v-if="selectedInvoice?.pdf_url || selectedInvoice?.hosted_url" :href="selectedInvoice.pdf_url || selectedInvoice.hosted_url" target="_blank" rel="noopener noreferrer" class="billing-link">{{ selectedInvoice.pdf_url ? 'Download PDF' : 'Open secure invoice' }}</a></template></AppDialog>
        </div>
    </AppLayout>
</template>
