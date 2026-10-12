<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { CreditCardIcon, ArrowDownTrayIcon, ArrowTopRightOnSquareIcon, ExclamationCircleIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import CapacityBillingPanel from '@/Components/Billing/CapacityBillingPanel.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import { billingMoney, billingDate, billingLabels, subscriptionStatus, invoiceStatus, limitState, cardExpiryState } from '@/Support/billingWorkspace';
import '../../../css/billing.css';

const props = defineProps({ signupSelection: Object, capacityPricing: Object, billingCountry: String, capacityRequest: Object, smsCredits: Number, smsPacks: Array, smsPurchase: Object, businessLabel: String, subscription: Object, trial: Object, entitlements: Object, invoices: Object, usage: Object, attention: Array, billingTimeZone: String, billingLocale: String, exportAvailable: Boolean, checkoutStatus: String, checkoutAttempt: Object, pendingChange: Object, billingReadiness: Object, planChangeStatus: String, planChangeTargetPriceId: Number, portalReturned: Boolean });
const page = usePage();
const tabs = ['Overview', 'Plan', 'Invoices'];
const initialSection = new URLSearchParams(page.url.split('?')[1] || '').get('section');
const section = ref(tabs.map(item => item.toLowerCase()).includes(initialSection) ? initialSection : props.capacityRequest?.status === 'submitted' ? 'plan' : 'overview');
function selectSection(value) { section.value = value; const link = new URL(window.location.href); link.searchParams.set('section', value); window.history.replaceState(window.history.state, '', link); }
function tabKey(event, index) { const key = event.key; if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(key)) return; event.preventDefault(); const next = key === 'Home' ? 0 : key === 'End' ? tabs.length - 1 : (index + (key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length; selectSection(tabs[next].toLowerCase()); document.getElementById(`billing-tab-${section.value}`)?.focus(); }
const invoicePage = link => { if (!link) return undefined; const value = new URL(link, window.location.origin); value.searchParams.set('section', 'invoices'); return value.toString(); };
const hasCapacityPricing = computed(() => Boolean(props.capacityPricing?.available));
const recurringTotal = computed(() => props.subscription.capacity?.total_minor ?? props.subscription.price?.amount_minor);
const busy = ref('');
const error = ref('');
const notice = ref('');
const cancelDialog = ref(null);
const invoiceDialog = ref(null);
const selectedInvoice = ref(null);
const invoiceLoading = ref(false);
const invoiceError = ref('');
const cancellationReason = ref('');
const recovery = ref(!props.capacityRequest && (['pending', 'processing'].includes(props.checkoutAttempt?.status) || (props.planChangeStatus === 'success' && props.planChangeTargetPriceId)) ? 'checking' : '');
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
const limits = computed(() => ['staff.max', 'locations.max', 'messaging.monthly_allowance'].map(key => ({ key, label: billingLabels[key], used: props.usage?.[key] ?? 0, limit: props.entitlements?.[key], state: limitState(props.usage?.[key] ?? 0, props.entitlements?.[key]) })));
const nextLabel = computed(() => isTrial.value ? 'Trial ends' : ended.value ? 'Access ended' : props.subscription.cancel_at ? 'Access until' : 'Next renewal');
const nextDate = computed(() => isTrial.value ? props.trial.ends_at : ended.value ? props.subscription.ended_at : props.subscription.cancel_at || props.subscription.current_period_ends_at);
const cardLabel = computed(() => props.subscription.payment_method_last_four ? `${props.subscription.payment_method_type || 'Card'} •••• ${props.subscription.payment_method_last_four}` : props.subscription.has_billing_account ? 'Payment & billing' : 'No payment method added');
const expiry = computed(() => cardExpiryState(props.subscription.payment_method_expiry_month,props.subscription.payment_method_expiry_year));
const url = (routeName, extra = {}) => route(routeName, { business: page.props.tenant.public_id, ...extra });
const refresh = () => router.reload({ preserveScroll: true });
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
        <div class="billing-workspace billing-refined">
            <PageHeader title="Subscription & billing" description="Manage your plan and payments." />
            <nav class="billing-nav" role="tablist" aria-label="Billing sections"><button v-for="(tab, index) in tabs" :key="tab" :id="`billing-tab-${tab.toLowerCase()}`" role="tab" :aria-selected="section === tab.toLowerCase()" :aria-controls="`billing-panel-${tab.toLowerCase()}`" :tabindex="section === tab.toLowerCase() ? 0 : -1" @click="selectSection(tab.toLowerCase())" @keydown="tabKey($event, index)">{{ tab }}</button></nav>
            <p v-if="notice" class="billing-alert success" role="status">{{ notice }}</p>
            <p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p>
            <p v-if="recovery === 'checking'" class="billing-alert" role="status">Confirming your subscription… This page is checking the billing service. Please wait before starting another request.</p>
            <div v-if="recovery === 'pending'" class="billing-alert warning" role="status"><strong>Confirmation is taking longer than expected.</strong><p>Payment has not been marked as failed. Refresh billing to check the latest verified status before trying again.</p><AppButton variant="secondary" @click="refresh">Check billing status</AppButton></div>
            <p v-if="recovery === 'closed'" class="billing-alert warning" role="status">This checkout is no longer active. Review your current plan below before starting another checkout.</p>


            <div id="billing-panel-overview" v-show="section === 'overview'" role="tabpanel" aria-labelledby="billing-tab-overview" tabindex="0" class="billing-overview-stack">
                <section class="billing-summary billing-current" aria-labelledby="current-plan-title">
                    <div class="billing-current-main"><div><p class="billing-eyebrow">{{ 'Current subscription' }}</p><h2 id="current-plan-title">{{ isTrial ? 'Your ClipperDesk trial' : name(subscription.plan) }}</h2></div><span class="billing-status" :class="needsAttention ? 'warning' : ended ? 'neutral' : 'success'">{{ subscriptionStatus(subscription) }}</span></div>
                    <p v-if="isTrial" class="billing-current-copy">Explore ClipperDesk free until <strong>{{ date(trial.ends_at) }}</strong>. Choose a subscription when you’re ready.</p>
                    <div v-else-if="recurringTotal != null" class="billing-price"><strong>{{ money(recurringTotal, subscription.capacity?.currency || subscription.price?.currency) }}</strong><span>/ {{ unit(subscription.billing_interval) }} · {{ subscription.capacity?.currency || subscription.price?.currency }} before tax</span></div>
                    <p v-if="!isTrial" class="billing-current-copy">{{ nextLabel }} <strong>{{ date(nextDate) }}</strong><span v-if="subscription.capacity"> · {{ subscription.capacity.locations }} {{ subscription.capacity.locations === 1 ? 'location' : 'locations' }} · {{ subscription.capacity.staff }} bookable staff</span></p>
                    <p v-if="subscription.status === 'restricted' && !subscription.has_provider_subscription" class="billing-subtext">Choose a subscription to resume changes. Your existing records remain readable.</p>
                    <p v-if="subscription.cancel_at" class="billing-subtext">Renewal stops at the end of your paid term. Outstanding invoices still require payment.</p>
                    <div v-if="needsAttention" class="billing-attention" role="status"><ExclamationCircleIcon aria-hidden="true"/><div><strong>Payment needs attention</strong><p v-for="item in attention" :key="item.currency">{{ money(item.amount_minor, item.currency) }} outstanding<span v-if="item.next_retry_at"> · Next retry {{ date(item.next_retry_at) }}</span>.</p><p v-if="subscription.grace_ends_at">Resolve payment by {{ date(subscription.grace_ends_at) }} to keep your account active.</p></div></div>
                    <p v-if="pendingChange" class="billing-alert">{{ pendingChange.plan_name }} starts {{ date(pendingChange.effective_at) }}.</p>
                    <p v-if="subscription.customer_balance_minor < 0 && subscription.balance_currency" class="billing-credit">Billing credit: <strong>{{ money(-subscription.customer_balance_minor, subscription.balance_currency) }}</strong></p>
                    <div class="billing-summary-actions"><div><AppButton @click="selectSection('plan')">{{ isTrial || ended || !subscription.has_provider_subscription ? 'Choose subscription' : 'Change subscription' }}</AppButton><AppButton v-if="needsAttention && subscription.has_billing_account" :href="url('business.billing.portal')" variant="secondary" :disabled="Boolean(busy) || !billingReadiness.secret_configured">Resolve payment</AppButton><span v-if="isTrial" class="billing-meta">No automatic charge during your trial</span></div><AppButton v-if="subscription.status === 'cancel_scheduled'" variant="secondary" :disabled="Boolean(busy) || !billingReadiness.secret_configured" @click="subscriptionAction('reactivate')">Keep subscription</AppButton><AppButton v-else-if="subscription.status === 'active' && subscription.has_provider_subscription" variant="quiet" :disabled="Boolean(busy) || recovery === 'checking'" @click="error = ''; cancelDialog?.open()">Cancel subscription</AppButton></div>
                </section>
                <section class="billing-panel" aria-labelledby="usage-title"><header class="billing-section-heading"><div><h2 id="usage-title">Your usage</h2></div><button class="billing-link" @click="selectSection('plan')">Adjust capacity</button></header><div class="billing-metrics"><div v-for="item in limits" :key="item.key" class="billing-usage-item"><div><span>{{ item.key === 'messaging.monthly_allowance' ? 'Text credits' : subscription.capacity && item.key === 'staff.max' ? 'Bookable staff' : item.label }}</span></div><strong v-if="item.limit !== 0">{{ item.used.toLocaleString(billingLocale) }}<span v-if="typeof item.limit === 'number'"> / {{ item.limit.toLocaleString(billingLocale) }}</span></strong><strong v-else class="billing-not-included">Not included</strong><progress v-if="typeof item.limit === 'number' && item.limit > 0" :value="Math.min(item.used, item.limit)" :max="item.limit" :aria-label="`${item.label}: ${item.used} of ${item.limit}`" :class="{ over: item.state === 'over' }"/><p v-if="item.state === 'over' && item.limit > 0" class="billing-warning">Over your allowance. Adjust your capacity.</p><p v-else-if="item.key === 'messaging.monthly_allowance' && subscription.capacity">Resets monthly<span v-if="smsCredits"> · {{ smsCredits }} extra credits available</span></p></div></div></section>
                <section v-if="subscription.has_billing_account" class="billing-panel billing-payment-row"><div class="billing-payment-icon"><CreditCardIcon aria-hidden="true"/></div><div class="billing-payment-info"><h2>{{ cardLabel }}</h2><p v-if="subscription.payment_method_expiry_month">Expires {{ String(subscription.payment_method_expiry_month).padStart(2, '0') }}/{{ subscription.payment_method_expiry_year }}<span v-if="expiry"> · {{ expiry === 'expired' ? 'Card expired' : 'Expires soon' }}</span></p><p v-if="subscription.billing_email">{{ subscription.billing_email }}</p></div><AppButton variant="secondary" :href="url('business.billing.portal')" :disabled="Boolean(busy) || !billingReadiness.secret_configured">Manage billing<ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true"/></AppButton></section>
                <div v-if="invoices.total" class="billing-recent"><span>{{ invoices.total }} {{ invoices.total === 1 ? 'invoice' : 'invoices' }}</span><button class="billing-link" @click="selectSection('invoices')">View invoices →</button></div>
            </div>
            <div id="billing-panel-plan" v-show="section === 'plan'" role="tabpanel" aria-labelledby="billing-tab-plan" tabindex="0">
                <CapacityBillingPanel v-if="hasCapacityPricing || subscription.capacity" :catalog="capacityPricing" :country="billingCountry" :initial-selection="!subscription.has_provider_subscription && signupSelection?.selected_plan_code === 'capacity' ? signupSelection : null" :current="subscription.capacity" :usage="usage" :business-id="page.props.tenant.public_id" :request="capacityRequest" :credits="smsCredits" :packs="smsPacks" :purchase="smsPurchase" :ready="billingReadiness.checkout_ready" :has-provider="subscription.has_provider_subscription" :time-zone="billingTimeZone"/>
                <div v-else class="billing-panel billing-empty"><DocumentTextIcon aria-hidden="true"/><div><h3>Need help with your subscription?</h3><p>Contact our team to arrange your plan.</p><a v-if="page.props.brand?.support_email" class="billing-link" :href="`mailto:${page.props.brand.support_email}`">Contact support</a></div></div>
                <p v-if="hasCapacityPricing && !billingReadiness.checkout_ready" class="billing-alert warning" role="status">Checkout is temporarily unavailable. Please try again shortly or contact support.</p>
            </div>
                <section id="billing-panel-invoices" v-show="section === 'invoices'" role="tabpanel" aria-labelledby="billing-tab-invoices" tabindex="0" class="billing-panel"><header class="billing-section-heading"><div><h2 id="billing-history-title">Invoices</h2><p>Invoices for your ClipperDesk subscription.</p></div><span class="billing-meta">{{ invoices.total }} {{ invoices.total === 1 ? 'invoice' : 'invoices' }}</span></header>
                    <template v-if="invoices.data?.length"><table class="billing-invoice-table"><caption class="sr-only">Platform subscription invoices, page {{ invoices.current_page }}</caption><thead><tr><th scope="col">Invoice / issued</th><th scope="col">Status</th><th scope="col" class="amount">Total</th><th scope="col"><span class="sr-only">Documents</span></th></tr></thead><tbody><tr v-for="invoice in invoices.data" :key="invoice.public_id"><td><button class="billing-invoice-name" @click="openInvoice(invoice)">{{ invoice.number || 'Subscription invoice' }}</button><span class="billing-invoice-date">{{ date(invoice.issued_at) }}</span></td><td><span class="billing-status" :class="invoice.status === 'paid' ? 'success' : invoice.status === 'open' || invoice.status === 'uncollectible' ? 'warning' : 'neutral'">{{ invoiceStatus(invoice) }}</span></td><td class="amount">{{ money(invoice.total_minor, invoice.currency) }}<small>{{ invoice.currency }}</small></td><td class="billing-documents"><a v-if="invoice.pdf_url" :href="invoice.pdf_url" target="_blank" rel="noopener noreferrer" :aria-label="`Download invoice ${invoice.number || ''} (opens in a new tab)`"><ArrowDownTrayIcon aria-hidden="true"/></a><a v-else-if="invoice.hosted_url" :href="invoice.hosted_url" target="_blank" rel="noopener noreferrer" :aria-label="`View invoice ${invoice.number || ''} securely (opens in a new tab)`"><ArrowTopRightOnSquareIcon aria-hidden="true"/></a><button v-else @click="openInvoice(invoice)" :aria-label="`View details for invoice ${invoice.number || ''}`"><DocumentTextIcon aria-hidden="true"/></button></td></tr></tbody></table>
                    <footer class="billing-pagination" v-if="invoices.last_page > 1"><span>{{ invoices.from }}–{{ invoices.to }} of {{ invoices.total }}</span><div><AppButton variant="secondary" :href="invoicePage(invoices.prev_page_url)" :disabled="!invoices.prev_page_url" preserve-scroll>Previous</AppButton><AppButton variant="secondary" :href="invoicePage(invoices.next_page_url)" :disabled="!invoices.next_page_url" preserve-scroll>Next</AppButton></div></footer></template>
                    <div v-else class="billing-empty"><DocumentTextIcon aria-hidden="true"/><div><h3>No invoices yet</h3><p>Your subscription invoices will appear here once issued.</p></div></div>
                </section>
            <AppDialog id="billing-cancel" ref="cancelDialog" title="Cancel subscription" :description="`Your ${name(subscription.plan)} plan remains active until ${date(subscription.current_period_ends_at)}. Renewal charges stop after that date. Existing unpaid invoices still need to be paid.`" :confirm-label="busy ? 'Confirming cancellation…' : 'Cancel at renewal'" cancel-label="Keep subscription" :confirm-disabled="Boolean(busy) || !billingReadiness.secret_configured" :close-on-confirm="false" :can-close="()=>!busy" destructive @confirm="subscriptionAction('cancel')"><p class="billing-subtext">Your business records remain stored. You can keep this subscription before the end date; after it ends, a new subscription is needed. The existing export policy continues to apply.</p><label class="billing-reason">Reason <span>(optional)</span><AppSelect v-model="cancellationReason" class="cd-input"><option value="">Prefer not to say</option><option>Too expensive</option><option>No longer needed</option><option>Missing a feature</option><option>Closing the business</option><option>Other</option></AppSelect></label><p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p></AppDialog>
            <AppDialog id="billing-invoice" ref="invoiceDialog" :title="selectedInvoice?.number || 'Subscription invoice'" description="Invoice for your ClipperDesk account." @cancel="invoiceRequest++"><div v-if="invoiceLoading" class="billing-skeleton" role="status">Loading invoice details…</div><p v-else-if="invoiceError" class="billing-alert warning" role="alert">{{ invoiceError }}</p><div v-else-if="selectedInvoice" class="billing-invoice-detail"><p>{{ date(selectedInvoice.issued_at) }} · {{ invoiceStatus(selectedInvoice) }}</p><ul v-if="selectedInvoice.line_items?.length"><li v-for="(line,i) in selectedInvoice.line_items" :key="i"><span>{{ line.description }}<small v-if="line.period_started_at">{{ date(line.period_started_at*1000) }} – {{ date(line.period_ends_at*1000) }}</small></span><strong>{{ money(line.amount_minor,selectedInvoice.currency) }}</strong></li></ul><dl><div v-for="item in [['Subtotal','subtotal_minor'],['Discount','discount_minor'],['Tax','tax_minor'],['Total','total_minor'],['Amount paid','amount_paid_minor']]" :key="item[1]"><dt>{{ item[0] }}</dt><dd>{{ item[1]==='discount_minor' && selectedInvoice[item[1]]>0 ? '−' : '' }}{{ money(selectedInvoice[item[1]],selectedInvoice.currency) }}</dd></div><div><dt>Remaining</dt><dd>{{ money(selectedInvoice.amount_remaining_minor ?? Math.max(0,selectedInvoice.amount_due_minor-selectedInvoice.amount_paid_minor),selectedInvoice.currency) }}</dd></div></dl><p v-if="selectedInvoice.hosted_url">The secure invoice contains the complete tax, credit and payment breakdown.</p><p v-else>Document download is not available for this recorded invoice. Contact support if you need the complete billing document.</p></div><template #footer><AppButton variant="secondary" @click="invoiceRequest++; invoiceDialog?.close()">Close</AppButton><a v-if="selectedInvoice?.pdf_url || selectedInvoice?.hosted_url" :href="selectedInvoice.pdf_url || selectedInvoice.hosted_url" target="_blank" rel="noopener noreferrer" class="billing-link">{{ selectedInvoice.pdf_url ? 'Download PDF' : 'Open secure invoice' }}</a></template></AppDialog>
        </div>
    </AppLayout>
</template>
