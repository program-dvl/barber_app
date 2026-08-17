<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { initializePaddle } from '@paddle/paddle-js';
import { router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    BuildingStorefrontIcon,
    CheckCircleIcon,
    CreditCardIcon,
    LockClosedIcon,
    ShieldCheckIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    businessLabel: String,
    billingContact: Object,
    price: Object,
    paddle: Object,
    checkoutAttempt: Object,
    termsUrl: String,
    privacyUrl: String,
});

const page = usePage();
const coupon = ref('');
const loading = ref(false);
const checkoutStarted = ref(false);
const checkoutLoaded = ref(false);
const error = ref('');
const totals = ref(null);
const transactionId = ref(props.checkoutAttempt?.transaction_id || null);
const confirmationState = ref(props.checkoutAttempt?.status === 'confirmed' ? 'confirmed' : 'idle');
const checkoutAccepted = ref(props.checkoutAttempt?.status === 'confirmed');
const completed = computed(() => ['processing', 'confirmed'].includes(confirmationState.value));
let paddleInstance;
let confirmationRequest = false;
let confirmationTimer;

const entitlementLabels = {
    'locations.max': value => `${value} ${value === 1 ? 'location' : 'locations'}`,
    'staff.max': value => `${value} team members`,
    'messaging.monthly_allowance': value => `${Number(value).toLocaleString()} messages each month`,
    'deposits.enabled': () => 'Appointment deposits',
    'inventory.enabled': () => 'Inventory management',
    'reporting.advanced': () => 'Advanced reporting',
    'branding.custom': () => 'Custom booking branding',
    'support.priority': () => 'Priority support',
};

const includedFeatures = computed(() => Object.entries(props.price.plan.entitlements || {})
    .filter(([, value]) => value !== false && value !== null && value !== 0)
    .map(([key, value]) => entitlementLabels[key]?.(value))
    .filter(Boolean)
    .slice(0, 6));

const intervalLabel = computed(() => props.price.billing_interval === 'annual' ? 'year' : 'month');
const formatMoney = (amount, currency = props.price.currency) => new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: String(currency || 'USD').toUpperCase(),
}).format(Number(amount || 0));

const moneyFromMinor = (amount, currency = props.price.currency) => formatMoney(Number(amount || 0) / 100, currency);
const moneyFromPaddle = (amount, currency = props.price.currency) => formatMoney(amount, currency);

const displayTotal = computed(() => totals.value
    ? moneyFromPaddle(totals.value.total, totals.value.currency)
    : moneyFromMinor(props.price.amount_minor));

const billingUrl = () => route('business.billing.show', page.props.tenant.public_id);

const scheduleConfirmation = (showProcessing = checkoutAccepted.value) => {
    clearTimeout(confirmationTimer);
    confirmationTimer = setTimeout(() => confirmCheckout(showProcessing), 4000);
};

const confirmCheckout = async (showProcessing = false) => {
    if (confirmationRequest || confirmationState.value === 'confirmed') return;
    confirmationRequest = true;
    if (showProcessing) confirmationState.value = 'processing';

    try {
        const response = await axios.post(route('business.billing.checkout.confirm', page.props.tenant.public_id), {
            transaction_id: transactionId.value,
        });
        if (response.data.status === 'confirmed') {
            confirmationState.value = 'confirmed';
            checkoutAccepted.value = true;
            error.value = '';
            clearTimeout(confirmationTimer);
            paddleInstance?.Checkout.close();
            nextTick(() => document.getElementById('checkout-complete')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        } else if (response.data.status === 'failed') {
            confirmationState.value = 'idle';
            error.value = response.data.message || 'The payment was not completed. No plan change was made.';
        } else if (checkoutAccepted.value) {
            confirmationState.value = 'processing';
            scheduleConfirmation();
        } else {
            confirmationState.value = 'idle';
            if (checkoutStarted.value) scheduleConfirmation(false);
        }
    } catch (exception) {
        if (checkoutAccepted.value) {
            confirmationState.value = 'processing';
            error.value = exception.response?.data?.message || 'Paddle accepted the payment, but confirmation is taking longer than expected. This page will keep checking safely.';
            scheduleConfirmation();
        } else {
            confirmationState.value = 'idle';
        }
    } finally {
        confirmationRequest = false;
    }
};

const handlePaddleEvent = event => {
    if (event.name === 'checkout.loaded') checkoutLoaded.value = true;

    if (['checkout.loaded', 'checkout.updated', 'checkout.completed'].includes(event.name)) {
        totals.value = event.data?.totals ? {
            ...event.data.totals,
            currency: event.data.currency_code,
        } : totals.value;
    }

    if (event.name === 'checkout.completed') {
        transactionId.value = event.data?.transaction_id || transactionId.value;
        checkoutAccepted.value = true;
        confirmationState.value = 'processing';
        error.value = '';
        confirmCheckout(true);
        nextTick(() => document.getElementById('checkout-complete')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    }

    if (['checkout.error', 'checkout.failed', 'checkout.payment.error', 'checkout.payment.failed'].includes(event.name)) {
        error.value = 'Paddle could not complete the payment. Review the payment details and try again.';
    }
};

const startCheckout = async () => {
    if (loading.value || checkoutStarted.value) return;

    loading.value = true;
    error.value = '';

    try {
        const response = await axios.post(route('business.billing.checkout', page.props.tenant.public_id), {
            price_id: props.price.id,
            coupon: coupon.value.trim() || null,
        });
        transactionId.value = response.data.transaction_id;

        paddleInstance = await initializePaddle({
            environment: response.data.environment,
            token: response.data.client_side_token,
            eventCallback: handlePaddleEvent,
            checkout: {
                settings: {
                    displayMode: 'inline',
                    frameTarget: 'paddle-checkout',
                    frameInitialHeight: 560,
                    frameStyle: 'width: 100%; min-width: 312px; background-color: transparent; border: none;',
                    theme: 'light',
                    locale: 'en',
                    allowLogout: false,
                    showAddDiscounts: false,
                    showAddTaxId: true,
                },
            },
        });

        if (!paddleInstance) throw new Error('Paddle did not initialize.');

        checkoutStarted.value = true;
        paddleInstance.Checkout.open({
            transactionId: response.data.transaction_id,
            settings: {
                displayMode: 'inline',
                frameTarget: 'paddle-checkout',
                frameInitialHeight: 560,
                frameStyle: 'width: 100%; min-width: 312px; background-color: transparent; border: none;',
                theme: 'light',
                locale: 'en',
                allowLogout: false,
                showAddDiscounts: false,
                showAddTaxId: true,
            },
        });
        scheduleConfirmation(false);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Secure checkout could not be loaded. Please try again.';
    } finally {
        loading.value = false;
    }
};

onBeforeUnmount(() => {
    clearTimeout(confirmationTimer);
    if (checkoutStarted.value) paddleInstance?.Checkout.close();
});

onMounted(() => {
    if (transactionId.value && confirmationState.value !== 'confirmed') confirmCheckout(false);
});
</script>

<template>
    <AppLayout title="Secure subscription checkout" :business-label="businessLabel">
        <div class="mx-auto max-w-6xl">
            <button class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-[var(--text-muted)] transition-colors hover:text-[var(--text-strong)]" type="button" @click="router.visit(billingUrl())">
                <ArrowLeftIcon class="size-4" aria-hidden="true" /> Back to plans
            </button>

            <div class="mt-3 flex flex-col gap-4 border-b border-[var(--border-subtle)] pb-7 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--action-primary)]">Secure subscription checkout</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-strong)] sm:text-4xl">Complete your Good Hours subscription</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--text-muted)]">Review the account and plan, then pay in the protected Paddle form embedded below. You will not be sent to another website.</p>
                </div>
                <div class="flex items-center gap-2 rounded-full border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-4 py-2 text-xs font-semibold text-[var(--text-muted)] shadow-sm">
                    <ShieldCheckIcon class="size-4 text-[var(--status-success)]" aria-hidden="true" /> {{ paddle.environment === 'sandbox' ? 'Paddle sandbox · test payment only' : 'Secure payment by Paddle' }}
                </div>
            </div>

            <ol class="mt-6 grid gap-3 text-sm sm:grid-cols-3" aria-label="Checkout progress">
                <li class="flex items-center gap-3 rounded-xl border border-[var(--status-success)] bg-[var(--status-success-soft)] px-4 py-3 font-semibold text-[var(--text-strong)]"><span class="grid size-7 place-items-center rounded-full bg-[var(--status-success)] text-xs text-white">1</span> Plan selected</li>
                <li class="flex items-center gap-3 rounded-xl border border-[var(--status-success)] bg-[var(--status-success-soft)] px-4 py-3 font-semibold text-[var(--text-strong)]"><span class="grid size-7 place-items-center rounded-full bg-[var(--status-success)] text-xs text-white">2</span> Account confirmed</li>
                <li class="flex items-center gap-3 rounded-xl border border-[var(--border-strong)] bg-[var(--surface-raised)] px-4 py-3 font-semibold text-[var(--text-strong)]"><span class="grid size-7 place-items-center rounded-full bg-[var(--brand-pine)] text-xs text-white">3</span> Secure payment</li>
            </ol>

            <p v-if="error && !completed" class="mt-6 rounded-xl border border-[var(--status-danger)] bg-[var(--status-danger-soft)] p-4 text-sm text-[var(--status-danger)]" role="alert">{{ error }}</p>
            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
                <section aria-labelledby="payment-details-heading" class="overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-[var(--shadow-raised)]">
                    <div class="border-b border-[var(--border-subtle)] px-5 py-5 sm:px-7">
                        <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-[var(--surface-subtle)] text-[var(--action-primary)]"><CreditCardIcon class="size-5" aria-hidden="true" /></span><div><h2 id="payment-details-heading" class="font-semibold text-[var(--text-strong)]">Billing and payment details</h2><p class="mt-0.5 text-sm text-[var(--text-muted)]">Encrypted and processed by Paddle, our merchant of record.</p></div></div>
                    </div>

                    <div v-if="completed" id="checkout-complete" class="grid min-h-[35rem] place-items-center p-6 text-center sm:p-10" role="status" aria-live="polite">
                        <div class="max-w-lg">
                            <span class="mx-auto grid size-16 place-items-center rounded-full" :class="confirmationState === 'confirmed' ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--surface-subtle)] text-[var(--action-primary)]'">
                                <CheckCircleIcon v-if="confirmationState === 'confirmed'" class="size-9" aria-hidden="true" />
                                <svg v-else class="size-8 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" /><path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                            </span>
                            <p class="mt-6 text-xs font-semibold uppercase tracking-[0.14em] text-[var(--action-primary)]">{{ confirmationState === 'confirmed' ? 'Purchase confirmed' : 'Payment accepted' }}</p>
                            <h3 class="mt-2 text-2xl font-semibold tracking-tight text-[var(--text-strong)]">{{ confirmationState === 'confirmed' ? `${price.plan.name} is now active` : `Confirming your ${price.plan.name} subscription` }}</h3>
                            <p class="mt-3 text-sm leading-6 text-[var(--text-muted)]">{{ confirmationState === 'confirmed' ? 'Your plan, renewal details, and Paddle invoice are now recorded in Good Hours.' : 'Paddle accepted the checkout. Good Hours is securely verifying the transaction and subscription; you can safely keep this page open.' }}</p>
                            <p v-if="error && confirmationState === 'processing'" class="mt-4 rounded-xl bg-[var(--status-warning-soft)] p-3 text-sm text-[var(--status-warning)]">{{ error }}</p>
                            <AppButton v-if="confirmationState === 'confirmed'" :href="`${billingUrl()}?checkout=success`" class="mt-7">View active plan and invoice</AppButton>
                        </div>
                    </div>

                    <div v-else-if="!checkoutStarted" class="p-5 sm:p-7">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-semibold text-[var(--text-strong)]">Account owner<input :value="billingContact.name" disabled class="gh-input mt-2 block w-full disabled:cursor-not-allowed disabled:bg-[var(--surface-subtle)] disabled:text-[var(--text-muted)]" /></label>
                            <label class="text-sm font-semibold text-[var(--text-strong)]">Billing email<input :value="billingContact.email" disabled type="email" class="gh-input mt-2 block w-full disabled:cursor-not-allowed disabled:bg-[var(--surface-subtle)] disabled:text-[var(--text-muted)]" /></label>
                        </div>
                        <label class="mt-5 block text-sm font-semibold text-[var(--text-strong)]">Promotion code <span class="font-normal text-[var(--text-muted)]">(optional)</span><input v-model="coupon" class="gh-input mt-2 block w-full" maxlength="64" autocomplete="off" placeholder="Enter a valid code" /></label>

                        <div class="mt-6 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-4 text-sm leading-6 text-[var(--text-muted)]">
                            <div class="flex gap-3"><LockClosedIcon class="mt-0.5 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><p>Card information never touches Good Hours servers. Paddle calculates applicable tax and shows the final total before you confirm.</p></div>
                        </div>

                        <button class="mt-6 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[var(--brand-pine)] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[var(--action-primary-hover)] disabled:cursor-wait disabled:opacity-65" type="button" :disabled="loading || !paddle.configured" :aria-busy="loading" @click="startCheckout">
                            <CreditCardIcon class="size-5" aria-hidden="true" /> {{ loading ? 'Preparing secure form…' : 'Continue to payment details' }}
                        </button>
                        <p v-if="!paddle.configured" class="mt-3 text-center text-sm text-[var(--status-danger)]">Paddle checkout is not configured for this environment.</p>
                        <p class="mt-4 text-center text-xs leading-5 text-[var(--text-muted)]">By continuing, you agree to the <a :href="termsUrl" target="_blank" rel="noopener" class="font-semibold text-[var(--action-primary)] hover:underline">Terms</a> and acknowledge the <a :href="privacyUrl" target="_blank" rel="noopener" class="font-semibold text-[var(--action-primary)] hover:underline">Privacy Policy</a>.</p>
                    </div>

                    <div v-else class="relative min-h-[35rem] px-2 py-4 sm:px-5">
                        <div v-if="!checkoutLoaded" class="absolute inset-x-5 top-5 z-10 space-y-4 rounded-xl bg-[var(--surface-raised)] p-5" aria-live="polite"><div class="h-5 w-40 animate-pulse rounded bg-[var(--surface-subtle)]" /><div class="h-12 animate-pulse rounded-lg bg-[var(--surface-subtle)]" /><div class="h-12 animate-pulse rounded-lg bg-[var(--surface-subtle)]" /><p class="text-sm text-[var(--text-muted)]">Loading Paddle’s encrypted payment form…</p></div>
                        <div class="paddle-checkout min-h-[34rem]" />
                    </div>
                </section>

                <aside class="space-y-5 lg:sticky lg:top-6">
                    <section class="overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-[var(--shadow-raised)]">
                        <div class="bg-[var(--brand-pine)] px-6 py-6 text-white"><div class="flex items-center gap-2 text-sm font-semibold text-white/75"><SparklesIcon class="size-4" aria-hidden="true" /> Selected plan</div><h2 class="mt-3 text-2xl font-semibold">{{ price.plan.name }}</h2><p class="mt-2 text-sm leading-5 text-white/75">{{ price.plan.description }}</p></div>
                        <div class="p-6"><div class="flex items-end justify-between gap-4"><span class="text-sm text-[var(--text-muted)]">Subscription</span><div class="text-right"><strong class="text-2xl font-semibold text-[var(--text-strong)]">{{ displayTotal }}</strong><span class="block text-xs text-[var(--text-muted)]">per {{ intervalLabel }}</span></div></div><div v-if="totals" class="mt-5 space-y-2 border-t border-[var(--border-subtle)] pt-4 text-sm"><div class="flex justify-between"><span class="text-[var(--text-muted)]">Subtotal</span><span>{{ moneyFromPaddle(totals.subtotal, totals.currency) }}</span></div><div class="flex justify-between"><span class="text-[var(--text-muted)]">Tax</span><span>{{ moneyFromPaddle(totals.tax, totals.currency) }}</span></div><div v-if="Number(totals.discount) > 0" class="flex justify-between text-[var(--status-success)]"><span>Discount</span><span>−{{ moneyFromPaddle(totals.discount, totals.currency) }}</span></div></div><ul class="mt-5 space-y-3 border-t border-[var(--border-subtle)] pt-5 text-sm"><li v-for="feature in includedFeatures" :key="feature" class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />{{ feature }}</li></ul></div>
                    </section>

                    <section class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-5"><div class="flex gap-3"><BuildingStorefrontIcon class="mt-0.5 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><div><h2 class="text-sm font-semibold text-[var(--text-strong)]">Billing account</h2><p class="mt-1 text-sm text-[var(--text-muted)]">{{ businessLabel }}</p><p class="mt-0.5 break-all text-xs text-[var(--text-muted)]">{{ billingContact.email }}</p></div></div></section>

                    <p v-if="paddle.environment === 'sandbox'" class="px-2 text-xs leading-5 text-[var(--text-muted)]">This sandbox checkout uses test cards only. No real payment is collected. Live checkout requires an approved production domain and separate live Paddle credentials.</p>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
