<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import CapacityCalculator from './CapacityCalculator.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import { billingMoney, billingDate } from '@/Support/billingWorkspace';

const props = defineProps({ catalog: Object, country: String, current: Object, usage: Object, businessId: String, request: Object, credits: Number, packs: Array, purchase: Object, ready: Boolean, timeZone: String });
const reviewDialog = ref(null), packDialog = ref(null), reviewed = ref(null), selectedPack = ref(null), busy = ref(''), error = ref(''), pending = ref(props.request), message = ref(''), recoveredCheckout = ref(null);
const blocking = computed(() => ['submitted', 'scheduled'].includes(pending.value?.status));
watch(() => props.request, value => { pending.value = value; });
const abort = new AbortController();
let timer, resolveWait, active = true;
const url = name => route(`business.billing.${name}`, { business: props.businessId });
const money = (value, currency) => billingMoney(value, currency);
const date = value => billingDate(value, props.timeZone);
const reload = () => router.reload({ only: ['subscription', 'usage', 'entitlements', 'capacityRequest', 'smsCredits', 'smsPurchase', 'smsPacks'], preserveScroll: true });
const fail = exception => { error.value = Object.values(exception.response?.data?.errors || {}).flat()[0] || exception.response?.data?.message || 'We could not confirm this request. Check billing status before trying again.'; };
async function review(estimate) {
    if (busy.value) return;
    error.value = ''; busy.value = 'review';
    try {
        const response = await axios.post(url('capacity.review'), { market: estimate.market, billing_interval: estimate.billing_interval, locations: estimate.locations, staff: estimate.staff }, { signal: abort.signal });
        reviewed.value = response.data; reviewDialog.value?.open();
    } catch (exception) { if (!axios.isCancel(exception)) fail(exception); }
    finally { busy.value = ''; }
}
async function confirm() {
    if (busy.value || !reviewed.value) return;
    busy.value = 'confirm'; error.value = '';
    try {
        const response = await axios.post(url('capacity.confirm'), { change_id: reviewed.value.change_id }, { signal: abort.signal });
        if (response.data.url) { window.location.assign(response.data.url); return; }
        pending.value = response.data; busy.value = ''; reviewDialog.value?.close(); await check();
    } catch (exception) { if (!axios.isCancel(exception)) { fail(exception); if (!exception.response || exception.response.status >= 500) pending.value = { ...reviewed.value, status: 'submitted' }; } }
    finally { busy.value = ''; }
}
async function check() {
    if (!pending.value) return;
    const response = await axios.post(url('capacity.status'), { change_id: pending.value.change_id }, { signal: abort.signal });
    pending.value = response.data; recoveredCheckout.value = response.data.checkout_url || null;
    if (['applied', 'scheduled'].includes(response.data.status)) {
        message.value = response.data.status === 'scheduled' ? `Change verified. Takes effect ${date(response.data.effective_at)}.` : 'Your new capacity is verified.';
        reload();
    }
}
async function refresh() {
    if (busy.value) return;
    busy.value = 'status'; error.value = '';
    try { await check(); } catch (exception) { if (!axios.isCancel(exception)) fail(exception); } finally { busy.value = ''; }
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
        message.value = response.data.status === 'paid' ? 'Text credit purchase verified.' : 'Text payment is awaiting verification. Credits are added after payment is confirmed.';
        if (response.data.status === 'paid') reload();
    } catch (exception) { if (!axios.isCancel(exception)) fail(exception); }
}
onMounted(async () => {
    if (!props.ready) return;
    if (props.purchase && props.purchase.status !== 'paid') await verifyPurchase();
    for (let attempt = 0; active && pending.value?.status === 'submitted' && attempt < 6; attempt++) {
        try { await check(); } catch (exception) { if (!axios.isCancel(exception)) fail(exception); break; }
        if (pending.value?.status !== 'submitted') break;
        await new Promise(resolve => { resolveWait = resolve; timer = window.setTimeout(resolve, 10000); });
    }
});
onUnmounted(() => { active = false; abort.abort(); window.clearTimeout(timer); resolveWait?.(); });
</script>

<template>
    <section id="capacity" class="billing-panel capacity-panel" aria-labelledby="capacity-title">
        <header class="billing-section-heading"><div><h2 id="capacity-title">Your locations & bookable staff</h2><p>One subscription that grows with your business.</p></div></header>
        <p v-if="!current" class="billing-subtext">Your existing plan keeps its current terms. Switching requires a review and explicit confirmation.</p>
        <p v-if="message" class="billing-alert success" role="status">{{ message }}</p>
        <p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p>
        <div v-if="blocking" class="billing-alert neutral" role="status"><p>{{ pending.status === 'scheduled' ? `Verified change scheduled for ${date(pending.effective_at)}.` : 'A billing request is awaiting verification. Your displayed capacity remains in effect until confirmed.' }}</p><AppButton variant="secondary" :disabled="Boolean(busy) || !ready" @click="refresh">Check billing status</AppButton><AppButton v-if="recoveredCheckout" :href="recoveredCheckout" variant="secondary">Continue secure checkout</AppButton></div>
        <CapacityCalculator :catalog="catalog" :country="country" :current="current" :usage="usage" :disabled="Boolean(busy) || blocking || !ready" @review="review" />
        <div v-if="current" class="capacity-texts"><h3>Text allowance & extra credits</h3><p>{{ usage?.['messaging.monthly_allowance'] || 0 }} included credits used this month · {{ credits || 0 }} prepaid credits available.</p><p>Included credits reset monthly, including on yearly subscriptions. Prepaid credits carry over while this billing account is retained. Automatic purchases are off.</p><AppButton v-for="pack in packs || []" :key="pack.key" variant="secondary" :disabled="Boolean(busy) || !ready" @click="selectedPack = pack; error = ''; packDialog?.open()">Add {{ pack.credits }} credits · {{ money(pack.amount_minor, pack.currency) }}</AppButton><p v-if="purchase?.status === 'pending'"><AppButton variant="quiet" :disabled="Boolean(busy) || !ready" @click="verifyPurchase">Check text payment</AppButton></p></div>
        <AppDialog id="capacity-review" ref="reviewDialog" title="Review your subscription" :confirm-label="busy ? 'Confirming…' : reviewed?.kind === 'checkout' ? 'Continue to secure checkout' : 'Confirm billing change'" :confirm-disabled="Boolean(busy) || !ready" :close-on-confirm="false" :can-close="() => !busy" @confirm="confirm">
            <div v-if="reviewed" class="billing-change"><p>{{ reviewed.quote.locations }} locations · {{ reviewed.quote.staff }} bookable staff</p><p><strong>{{ money(reviewed.quote.total_minor, reviewed.quote.currency) }} / {{ reviewed.quote.billing_interval === 'annual' ? 'year' : 'month' }}</strong> before tax.</p><p v-if="reviewed.kind === 'checkout'">Secure checkout shows the final tax and total before you pay. Starting a paid subscription ends your free trial.</p><template v-else><p><strong>Due today: {{ money(reviewed.quote.due_today_minor, reviewed.quote.currency) }}</strong></p><p>{{ reviewed.kind === 'renewal' ? `Changes take effect at renewal on ${date(reviewed.effective_at)}. Current access remains until then.` : 'The displayed adjustment covers the remaining paid period. Capacity changes after payment is verified; your renewal date stays the same.' }}</p></template><p>Includes {{ reviewed.quote.sms_monthly_allowance }} text credits each month and routine email notifications. Text top-ups are optional.</p><p>Review expires {{ date(reviewed.expires_at) }}. No automatic text purchases.</p><p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p></div>
        </AppDialog>
        <AppDialog id="sms-pack-review" ref="packDialog" title="Add text credits" :confirm-label="busy ? 'Opening…' : 'Continue to secure payment'" :confirm-disabled="Boolean(busy) || !ready" :close-on-confirm="false" :can-close="() => !busy" @confirm="buy"><p v-if="selectedPack">One payment of {{ money(selectedPack.amount_minor, selectedPack.currency) }} before tax adds {{ selectedPack.credits }} text credits after verification. Secure checkout shows the final tax and total. This does not change your subscription or enable automatic purchases.</p><p v-if="error" class="billing-alert warning" role="alert">{{ error }}</p></AppDialog>
    </section>
</template>
