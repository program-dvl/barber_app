<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon, ShieldCheckIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import { billingMoney, billingDate } from '@/Support/billingWorkspace';
import '../../../css/billing.css';
const props = defineProps({ businessLabel: String, billingContact: Object, price: Object, stripe: Object, checkoutAttempt: Object, termsUrl: String, privacyUrl: String, trialEndsAt: String, billingTimeZone: String, billingLocale: String });
const page = usePage();
const loading = ref(false);
const error = ref('');
const money = (minor, currency) => billingMoney(minor,currency,props.billingLocale);
async function startCheckout() {
    if (loading.value || !props.stripe.checkout_ready) return;
    loading.value = true; error.value = '';
    try {
        const response = await axios.post(route('business.billing.checkout',page.props.tenant.public_id), { price_id:props.price.id });
        if (!response.data.url) throw new Error('Missing checkout URL');
        window.location.assign(response.data.url);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Checkout could not be confirmed. Return to billing to check for an existing checkout before trying again.';
        loading.value = false;
    }
}
</script>
<template>
    <AppLayout title="Review subscription" :business-label="businessLabel"><div class="billing-workspace" style="max-width:800px">
        <AppButton :href="route('business.billing.show',page.props.tenant.public_id)" variant="quiet"><ArrowLeftIcon class="size-4" aria-hidden="true"/>Back to billing</AppButton>
        <section class="billing-summary mt-4"><div class="billing-plan-heading"><div><p class="billing-eyebrow">Review subscription</p><h1 class="cd-page-title">{{ price.plan.name.replace(/^ClipperDesk\s+/i,'') }}</h1></div><ShieldCheckIcon class="size-6 text-[var(--text-muted)]" aria-hidden="true"/></div>
            <div class="billing-price"><strong>{{ money(price.amount_minor,price.currency) }}</strong><span>/ {{ price.billing_interval==='annual' ? 'year' : 'month' }} <small>{{ price.currency }}</small></span></div>
            <p class="billing-subtext">{{ price.billing_interval==='annual' ? 'Paid yearly, upfront.' : 'Paid monthly.' }} This is the base subscription price. Review the final charge, any discount and applicable tax on the secure checkout page.</p>
            <div v-if="trialEndsAt" class="billing-alert mt-5" role="status"><strong>Your free trial ends {{ billingDate(trialEndsAt,billingTimeZone,billingLocale) }}.</strong><p>Confirming this checkout starts paid billing immediately. You can return to billing and continue the remaining trial instead.</p></div>
            <dl class="billing-summary-facts" style="grid-template-columns:1fr 1fr"><div><dt>Business</dt><dd>{{ businessLabel }}</dd></div><div><dt>Billing details</dt><dd>Confirm securely at checkout</dd></div></dl>
            <p class="billing-subtext">Payment information, legal name, billing email and any promotion code are entered securely at checkout. A promotion is only applied once its effect appears in the final total.</p>
            <p v-if="error" class="billing-alert warning mt-4" role="alert">{{ error }}</p>
            <div class="mt-6"><AppButton :disabled="loading || !stripe.checkout_ready" :aria-busy="loading" @click="startCheckout">{{ loading ? 'Opening secure checkout…' : 'Continue to secure checkout' }}<ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true"/></AppButton></div>
            <p v-if="!stripe.checkout_ready" class="billing-availability" role="status">Secure checkout is temporarily unavailable. Your existing trial or access stays as shown in billing.</p>
            <p class="billing-subtext mt-5 text-xs">Review the <a :href="termsUrl" target="_blank" rel="noopener noreferrer" class="text-[var(--action-primary)] underline">Terms</a>, <a :href="privacyUrl" target="_blank" rel="noopener noreferrer" class="text-[var(--action-primary)] underline">Privacy Policy</a> and <a :href="route('refund.show')" target="_blank" rel="noopener noreferrer" class="text-[var(--action-primary)] underline">Refund Policy</a> before subscribing. You can schedule cancellation at the end of your paid billing period.</p>
        </section><p class="billing-trust mt-4 rounded-xl"><ShieldCheckIcon aria-hidden="true"/>Stripe securely handles payment. ClipperDesk does not collect your card number. A checkout redirect alone never activates a subscription.</p>
    </div></AppLayout>
</template>
