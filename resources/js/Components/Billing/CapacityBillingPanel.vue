<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import CapacityCalculator from './CapacityCalculator.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import { billingMoney, billingDate } from '@/Support/billingWorkspace';
import AppNotice from '@/Components/Product/AppNotice.vue';
import { billingRecoveryFailure, markBillingDeparture } from '@/Support/billingRecovery';
import { watchBillingPayment } from '@/Support/billingPaymentWatch';

const props = defineProps({ initialSelection: Object, catalog: Object, country: String, current: Object, usage: Object, businessId: String, request: Object, checkoutReturn: Object, credits: Number, packs: Array, purchase: Object, ready: Boolean, timeZone: String, hasProvider: Boolean });
function currentRequest(request, returned) {
    const confirmed = returned?.status === 'confirmed' && returned.request?.status === 'applied';
    return confirmed && (!request || request.change_id === returned.request.change_id) ? returned.request : request || returned?.request;
}
const reviewDialog = ref(null), packDialog = ref(null), reviewed = ref(null), selectedPack = ref(null), busy = ref(''), error = ref(''), pending = ref(currentRequest(props.request, props.checkoutReturn)), message = ref(''), recoveredCheckout = ref(null);
const messageTitle = ref(props.checkoutReturn?.status === 'confirmed' && props.checkoutReturn?.request?.status === 'applied' ? 'Your subscription is active' : ''), messageTone = ref('success');
const recoveryIssue = ref(null), clock = ref(Date.now());
const waitSeconds = computed(() => Math.max(0, Math.ceil(((recoveryIssue.value?.retryAt || 0) - clock.value) / 1000)));
const waiting = computed(() => waitSeconds.value > 0);
const blocking = computed(() => ['submitted', 'scheduled'].includes(pending.value?.status));
const checkingPayment = ref(pending.value?.kind === 'checkout' && pending.value?.status === 'submitted');
const paymentProcessing = ref(props.checkoutReturn?.status === 'processing');
const checkoutStatus = ref(null), checkoutOpen = ref(false);
let checkVersion = 0;
watch(() => [props.request, props.checkoutReturn], ([request, returned]) => {
    const value = currentRequest(request, returned);
    // A delayed partial response cannot reopen this exact completed purchase.
    if (pending.value?.status === 'applied' && value?.status === 'submitted' && pending.value.change_id === value.change_id) return;
    if (value?.change_id !== pending.value?.change_id || value?.status !== 'submitted') paymentWatch.stop();
    checkVersion++; pending.value = value; recoveredCheckout.value = null;
    if (value?.status === 'applied' && value.kind === 'checkout') {
        clearIssue(); messageTitle.value = 'Your subscription is active'; messageTone.value = 'success'; message.value = '';
    }
});
const abort = new AbortController();
let clockTimer;
const url = name => route(`business.billing.${name}`, { business: props.businessId });
const money = (value, currency) => billingMoney(value, currency);
const date = value => billingDate(value, props.timeZone);
const reload = () => router.reload({ only: ['tenant', 'subscription', 'trial', 'attention', 'invoices', 'pendingChange', 'usage', 'entitlements', 'capacityRequest', 'checkoutReturn', 'smsCredits', 'smsPurchase', 'smsPacks', 'billingReadiness', 'capacityPricing'], preserveScroll: true });
const fail = exception => {
    messageTitle.value = ''; message.value = '';
    recoveryIssue.value = billingRecoveryFailure(exception);
    clock.value = Date.now();
    error.value = recoveryIssue.value.message;
    recoveredCheckout.value = null;
};
const clearIssue = () => { error.value = ''; recoveryIssue.value = null; };
async function review(estimate) {
    if (busy.value || waiting.value || blocking.value || !props.ready) return;
    clearIssue(); busy.value = 'review';
    try {
        const response = await axios.post(url('capacity.review'), { market: estimate.market, billing_interval: estimate.billing_interval, locations: estimate.locations, staff: estimate.staff, use_current_rates: estimate.use_current_rates || false }, { signal: abort.signal });
        reviewed.value = response.data; reviewDialog.value?.open();
    } catch (exception) { if (!axios.isCancel(exception)) fail(exception); }
    finally { busy.value = ''; }
}
async function confirm() {
    if (busy.value || waiting.value || !reviewed.value) return;
    busy.value = 'confirm'; clearIssue();
    try {
        const response = await axios.post(url('capacity.confirm'), { change_id: reviewed.value.change_id }, { signal: abort.signal });
        if (response.data.url) { pending.value = { ...reviewed.value, status: 'submitted' }; message.value = ''; reviewDialog.value?.close(); reviewed.value = null; markBillingDeparture(props.businessId); window.location.assign(response.data.url); return; }
        pending.value = response.data; busy.value = ''; reviewDialog.value?.close();
        const result = await check();
        if (result?.kind === 'checkout') await followPayment(result);
    } catch (exception) {
        if (!axios.isCancel(exception)) {
            fail(exception);
            if (!exception.response || exception.response.status >= 500 || exception.response.data?.status === 'submitted') {
                pending.value = { ...reviewed.value, status: 'submitted' };
                reviewDialog.value?.close();
            }
        }
    }
    finally { busy.value = ''; }
}
async function check(verifyOnly = false) {
    if (!pending.value) return;
    const version = ++checkVersion;
    let response;
    try { response = await axios.post(url('capacity.status'), { change_id: pending.value.change_id, verify_only: verifyOnly }, { signal: abort.signal }); }
    catch (exception) { if (version !== checkVersion) return; throw exception; }
    if (version !== checkVersion) return;
    pending.value = response.data; recoveredCheckout.value = response.data.checkout_url || null;
    checkoutStatus.value = response.data.checkout_status || null;
    checkoutOpen.value = response.data.checkout_status === 'pending' && Boolean(response.data.checkout_url);
    paymentProcessing.value = response.data.checkout_status === 'processing';
    clearIssue();
    if (['expired', 'failed', 'cancelled'].includes(response.data.status)) {
        messageTone.value = 'success'; messageTitle.value = 'Ready to start again';
        message.value = 'Your selection is saved. Review it before continuing to payment.';
        reload();
    } else if (['applied', 'scheduled'].includes(response.data.status)) {
        messageTone.value = 'success';
        messageTitle.value = response.data.status === 'scheduled' ? 'Subscription change scheduled' : pending.value.kind === 'checkout' ? 'Your subscription is active' : 'Your subscription has been updated';
        message.value = response.data.status === 'scheduled' ? `Your updated subscription starts on ${date(response.data.effective_at)}.` : '';
        reload();
    }
    return response.data;
}
const paymentWatch = watchBillingPayment({
    check: () => check(true),
    onError: exception => { if (!axios.isCancel(exception)) fail(exception); },
    onStop: () => { checkingPayment.value = false; },
});
function followPayment(result) {
    checkingPayment.value = true;
    return paymentWatch.start(result);
}
async function refresh() {
    if (busy.value || waiting.value || checkingPayment.value) return;
    busy.value = 'status';
    try { const result = await check(); if (result?.kind === 'checkout') await followPayment(result); } catch (exception) { if (!axios.isCancel(exception)) fail(exception); } finally { busy.value = ''; }
}
async function continueCheckout() {
    if (busy.value || waiting.value || checkingPayment.value) return;
    busy.value = 'continue';
    try {
        const result = await check();
        if (recoveredCheckout.value && blocking.value) { markBillingDeparture(props.businessId); window.location.assign(recoveredCheckout.value); }
        else if (result?.kind === 'checkout') await followPayment(result);
    }
    catch (exception) { if (!axios.isCancel(exception)) fail(exception); }
    finally { busy.value = ''; }
}
async function restart() {
    if (busy.value || waiting.value || checkingPayment.value || recoveryIssue.value?.supportRequired || pending.value?.kind !== 'checkout') return;
    busy.value = 'restart'; checkVersion++; recoveredCheckout.value = null;
    try {
        const response = await axios.post(url('capacity.restart'), { change_id: pending.value.change_id }, { signal: abort.signal });
        checkVersion++;
        pending.value = response.data; clearIssue();
        if (['expired', 'failed', 'cancelled'].includes(response.data.status)) {
            messageTone.value = 'success'; messageTitle.value = 'Ready to start again';
            message.value = 'Your selection is saved. Review it before continuing to payment.';
            reload();
        } else {
            const result = await check();
            if (result?.kind === 'checkout') await followPayment(result);
        }
    } catch (exception) { if (!axios.isCancel(exception)) fail(exception); }
    finally { busy.value = ''; }
}
async function buy() {
    if (busy.value || !selectedPack.value) return;
    busy.value = 'topup'; error.value = '';
    try {
        const response = await axios.post(url('sms.topup'), { pack: selectedPack.value.key }, { signal: abort.signal });
        window.location.assign(response.data.url);
    } catch (exception) { if (!axios.isCancel(exception)) fail(exception); } finally { busy.value = ''; }
}
async function verifyPurchase() {
    try {
        const response = await axios.post(url('sms.status'), { purchase_id: props.purchase.public_id }, { signal: abort.signal });
        messageTone.value = response.data.status === 'paid' ? 'success' : 'info';
        messageTitle.value = response.data.status === 'paid' ? 'Your text credits have been added' : 'Your text payment is pending';
        message.value = response.data.status === 'paid' ? '' : 'Credits will be added when your payment is complete.';
        if (response.data.status === 'paid') reload();
    } catch (exception) { if (!axios.isCancel(exception)) fail(exception); }
}
onMounted(async () => {
    clockTimer = window.setInterval(() => { clock.value = Date.now(); }, 1000);
    if (props.purchase && props.purchase.status !== 'paid') await verifyPurchase();
    if (pending.value?.status === 'submitted' && !busy.value) {
        if (pending.value.kind === 'checkout') await followPayment();
        else if (props.ready) await refresh();
    } else checkingPayment.value = false;
});
onUnmounted(() => { paymentWatch.stop(); checkVersion++; abort.abort(); window.clearInterval(clockTimer); });
</script>

<template>
    <section id="capacity" class="billing-panel capacity-panel" aria-labelledby="capacity-title">
        <header class="billing-section-heading"><div><h2 id="capacity-title">{{ hasProvider ? 'Change your subscription' : 'Choose your subscription' }}</h2><p>All your locations. One simple subscription.</p></div></header>

        <AppNotice v-if="messageTitle && !blocking && !error" :tone="messageTone" :title="messageTitle" :description="message" />
        <AppNotice v-if="error && !blocking" tone="warning" :title="recoveryIssue?.title" urgent><p>{{ error }}</p><p v-if="waiting" class="capacity-retry-time" aria-live="off">You can try again in {{ waitSeconds }} {{ waitSeconds === 1 ? 'second' : 'seconds' }}.</p></AppNotice>
        <AppNotice v-if="blocking" :aria-busy="checkingPayment" :tone="error ? 'warning' : 'info'" :urgent="Boolean(error)" :title="error ? recoveryIssue?.title : checkingPayment ? 'Checking your payment' : paymentProcessing ? 'Your payment is pending' : pending.status === 'scheduled' ? 'Your change is scheduled' : pending.kind === 'checkout' ? checkoutOpen ? 'Complete your subscription' : 'Your subscription isn’t confirmed yet' : 'Your subscription change is pending'">
            <p v-if="error">{{ error }}</p>
            <template v-else>
                <p v-if="checkingPayment">Your subscription will appear here once payment is confirmed.</p>
                <p v-else-if="paymentProcessing">{{ recoveredCheckout ? 'Complete your payment to activate your subscription.' : 'Your subscription will activate when payment is confirmed.' }}</p>
                <p v-else-if="pending.status === 'scheduled'">Your updated subscription starts {{ date(pending.effective_at) }}.</p>
                <p v-else-if="pending.kind === 'checkout'">{{ checkoutOpen ? 'Continue to payment, or start again to change your selection.' : 'Your selection is saved. Check its status before starting another payment.' }}</p>
                <p v-else>{{ pending.kind === 'renewal' ? 'Your current subscription stays the same while we confirm this change.' : 'We’re checking your payment. Your current subscription stays the same.' }}</p>
            </template>
            <p v-if="pending.kind === 'checkout'" class="capacity-checkout-selection">{{ pending.quote.locations }} {{ pending.quote.locations === 1 ? 'location' : 'locations' }} · {{ pending.quote.staff }} bookable staff · {{ money(pending.quote.total_minor, pending.quote.currency) }}/{{ pending.quote.billing_interval === 'annual' ? 'year' : 'month' }} before tax</p>
            <p v-if="recoveryIssue?.supportRequired && recoveryIssue.reference" class="capacity-recovery-reference">Support reference: {{ recoveryIssue.reference }}</p>
            <p v-if="waiting" class="capacity-retry-time" aria-live="off">You can check again in {{ waitSeconds }} {{ waitSeconds === 1 ? 'second' : 'seconds' }}.</p>
            <template v-if="!checkingPayment" #actions>
                <AppButton v-if="recoveryIssue?.paymentRequired" :href="route('business.billing.show', { business: businessId, section: 'invoices' })">Open invoices</AppButton>
                <a v-if="recoveryIssue?.supportUrl && recoveryIssue.supportRequired" :href="recoveryIssue.supportUrl" class="capacity-support-link cd-app-button">Contact support</a>
                <AppButton v-if="recoveredCheckout && !recoveryIssue?.supportRequired" :disabled="Boolean(busy) || waiting || checkingPayment" :loading="busy === 'continue'" @click="continueCheckout">Continue secure checkout</AppButton>
                <AppButton v-if="pending.kind === 'checkout' && pending.status === 'submitted' && checkoutStatus !== 'confirmed' && !paymentProcessing && !checkingPayment && !recoveryIssue?.supportRequired" variant="secondary" :disabled="Boolean(busy) || waiting || checkingPayment" :loading="busy === 'restart'" @click="restart">{{ busy === 'restart' ? 'Closing checkout…' : 'Start again' }}</AppButton>
                <AppButton v-if="!checkingPayment" variant="quiet" :disabled="Boolean(busy) || waiting" :loading="busy === 'status'" @click="refresh">{{ error ? 'Check again' : 'Check billing status' }}</AppButton>
            </template>
        </AppNotice>
        <CapacityCalculator :initial-selection="initialSelection" :catalog="catalog" :country="country" :current="blocking ? pending.quote : current" :usage="usage" :disabled="Boolean(busy) || checkingPayment" :review-disabled="blocking || !ready || waiting" @review="review" />
        <div v-if="packs?.length || purchase" class="capacity-texts"><h3>Text allowance & extra credits</h3><p>{{ usage?.['messaging.monthly_allowance'] || 0 }} included credits used this month · {{ credits || 0 }} prepaid credits available.</p><p>Included credits reset monthly, including on yearly subscriptions. Unused extra credits stay available in your account. More credits are only purchased when you choose to buy them.</p><AppButton v-for="pack in packs || []" :key="pack.key" variant="secondary" :disabled="Boolean(busy) || !ready" @click="selectedPack = pack; error = ''; packDialog?.open()">Add {{ pack.credits }} credits · {{ money(pack.amount_minor, pack.currency) }}</AppButton><p v-if="purchase?.status === 'pending'"><AppButton variant="quiet" :disabled="Boolean(busy) || !ready" @click="verifyPurchase">Check text payment</AppButton></p></div>
        <AppDialog id="capacity-review" ref="reviewDialog" title="Review your subscription" :confirm-label="busy ? 'Confirming…' : reviewed?.kind === 'checkout' ? 'Continue to secure checkout' : 'Confirm billing change'" :confirm-disabled="Boolean(busy) || waiting || !ready" :close-on-confirm="false" :can-close="() => !busy" @confirm="confirm">
            <div v-if="reviewed" class="billing-change"><p>{{ reviewed.quote.locations }} {{ reviewed.quote.locations === 1 ? 'location' : 'locations' }} · {{ reviewed.quote.staff }} bookable staff</p><p><strong>{{ money(reviewed.quote.total_minor, reviewed.quote.currency) }} / {{ reviewed.quote.billing_interval === 'annual' ? 'year' : 'month' }}</strong> before tax.</p><p v-if="reviewed.kind === 'checkout'">Secure checkout shows the final tax and total before you pay. Starting a paid subscription ends your free trial.</p><template v-else><p><strong>Due today: {{ money(reviewed.quote.due_today_minor, reviewed.quote.currency) }}</strong></p><p>{{ reviewed.kind === 'renewal' ? `Your updated subscription starts on ${date(reviewed.effective_at)}. Your current subscription continues until then.` : 'Today’s payment covers the added locations and staff until your next renewal. Your subscription updates when payment is complete; your renewal date stays the same.' }}</p></template><p>Includes {{ reviewed.quote.sms_monthly_allowance }} text credits each month and email notifications.</p><AppNotice v-if="error" tone="warning" :title="recoveryIssue?.title" :description="error" urgent /></div>
        </AppDialog>
        <AppDialog id="sms-pack-review" ref="packDialog" title="Add text credits" :confirm-label="busy ? 'Opening…' : 'Continue to secure payment'" :confirm-disabled="Boolean(busy) || waiting || !ready" :close-on-confirm="false" :can-close="() => !busy" @confirm="buy"><p v-if="selectedPack">One payment of {{ money(selectedPack.amount_minor, selectedPack.currency) }} before tax adds {{ selectedPack.credits }} text credits when payment is complete. The final price, including tax, is shown before you pay. Your subscription stays the same.</p><AppNotice v-if="error" tone="warning" :title="recoveryIssue?.title" :description="error" urgent /></AppDialog>
    </section>
</template>
