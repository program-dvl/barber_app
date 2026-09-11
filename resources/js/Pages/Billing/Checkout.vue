<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon, BuildingStorefrontIcon, CheckCircleIcon, CreditCardIcon, LockClosedIcon, ShieldCheckIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ businessLabel: String, billingContact: Object, price: Object, stripe: Object, checkoutAttempt: Object, termsUrl: String, privacyUrl: String });
const page = usePage();
const coupon = ref('');
const loading = ref(false);
const error = ref('');

const billingUrl = () => route('business.billing.show', page.props.tenant.public_id);
const intervalLabel = computed(() => props.price.billing_interval === 'annual' ? 'year' : 'month');
const money = (minor, currency) => new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(minor || 0) / 100);
const includedFeatures = computed(() => Object.entries(props.price.plan.entitlements || {})
    .filter(([, value]) => value === true || (typeof value === 'number' && value > 0))
    .map(([key, value]) => ({
        'locations.max': `${value} ${value === 1 ? 'location' : 'locations'}`,
        'staff.max': `Up to ${value} active team members`,
        'messaging.monthly_allowance': `${Number(value).toLocaleString()} included mobile messages`,
        'deposits.enabled': 'Appointment deposits',
        'inventory.enabled': 'Inventory management',
        'reporting.advanced': 'Advanced reporting',
        'branding.custom': 'Custom booking-page branding',
        'support.priority': 'Priority support',
        'exports.enabled': 'Business data exports',
    }[key] || key.replaceAll('.', ' '))));

const startCheckout = async () => {
    if (loading.value || !props.stripe.checkout_ready) return;
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.post(route('business.billing.checkout', page.props.tenant.public_id), {
            price_id: props.price.id,
            coupon: coupon.value || null,
        });
        if (!response.data?.url) throw new Error('Stripe Checkout URL is missing.');
        window.location.assign(response.data.url);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Stripe Checkout could not be prepared. Please try again.';
        loading.value = false;
    }
};
</script>

<template>
    <AppLayout title="Secure subscription checkout" :business-label="businessLabel">
        <div class="mx-auto max-w-6xl">
            <button class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-[var(--text-muted)] hover:text-[var(--text-strong)]" type="button" @click="router.visit(billingUrl())">
                <ArrowLeftIcon class="size-4" aria-hidden="true" /> Back to plans
            </button>

            <div class="mt-3 flex flex-col gap-4 border-b border-[var(--border-subtle)] pb-7 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--action-primary)]">Subscription checkout</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-strong)] sm:text-4xl">Review before continuing to Stripe</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--text-muted)]">Confirm the billing account and plan here. Stripe securely collects payment and billing details on the next step.</p>
                </div>
                <div class="flex items-center gap-2 rounded-full border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-4 py-2 text-xs font-semibold text-[var(--text-muted)] shadow-sm">
                    <ShieldCheckIcon class="size-4 text-[var(--status-success)]" aria-hidden="true" /> Secure checkout by Stripe
                </div>
            </div>

            <ol class="mt-6 grid gap-3 text-sm sm:grid-cols-3" aria-label="Checkout progress">
                <li class="flex items-center gap-3 rounded-xl border border-[var(--status-success)] bg-[var(--status-success-soft)] px-4 py-3 font-semibold"><span class="grid size-7 place-items-center rounded-full bg-[var(--status-success)] text-xs text-white">1</span> Plan selected</li>
                <li class="flex items-center gap-3 rounded-xl border border-[var(--border-strong)] bg-[var(--surface-raised)] px-4 py-3 font-semibold"><span class="grid size-7 place-items-center rounded-full bg-[var(--brand-primary)] text-xs text-white">2</span> Review account</li>
                <li class="flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-4 py-3 font-semibold text-[var(--text-muted)]"><span class="grid size-7 place-items-center rounded-full bg-[var(--surface-raised)] text-xs">3</span> Pay with Stripe</li>
            </ol>

            <p v-if="error" class="mt-6 rounded-xl border border-[var(--status-danger)] bg-[var(--status-danger-soft)] p-4 text-sm text-[var(--status-danger)]" role="alert">{{ error }}</p>

            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
                <section class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-6 shadow-[var(--shadow-raised)] sm:p-7">
                    <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-[var(--surface-subtle)] text-[var(--action-primary)]"><CreditCardIcon class="size-5" aria-hidden="true" /></span><div><h2 class="font-semibold text-[var(--text-strong)]">Billing account</h2><p class="mt-0.5 text-sm text-[var(--text-muted)]">Only a billing-capable owner can continue.</p></div></div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-semibold">Account owner<input :value="billingContact.name" disabled class="cd-input mt-2 block w-full disabled:bg-[var(--surface-subtle)]" /></label>
                        <label class="text-sm font-semibold">Billing email<input :value="billingContact.email" disabled type="email" class="cd-input mt-2 block w-full disabled:bg-[var(--surface-subtle)]" /></label>
                    </div>
                    <label class="mt-5 block text-sm font-semibold">Promotion code <span class="font-normal text-[var(--text-muted)]">(optional)</span><input v-model="coupon" class="cd-input mt-2 block w-full" maxlength="64" autocomplete="off" placeholder="Enter a valid code" /></label>

                    <div class="mt-6 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-4 text-sm leading-6 text-[var(--text-muted)]"><div class="flex gap-3"><LockClosedIcon class="mt-0.5 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><p>ClipperDesk never receives or stores your card number. Stripe shows the final tax and total before confirmation.</p></div></div>

                    <button class="mt-6 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[var(--brand-primary)] px-5 py-3 text-sm font-semibold text-white hover:bg-[var(--action-primary-hover)] disabled:cursor-wait disabled:opacity-65" type="button" :disabled="loading || !stripe.checkout_ready" :aria-busy="loading" @click="startCheckout">
                        <CreditCardIcon class="size-5" aria-hidden="true" /> {{ loading ? 'Opening secure checkout…' : 'Continue to Stripe' }}
                    </button>
                    <p v-if="!stripe.checkout_ready" class="mt-3 text-center text-sm text-[var(--status-danger)]">Secure subscription activation is temporarily unavailable. No payment can be started until Stripe event verification is ready.</p>
                    <p class="mt-4 text-center text-xs leading-5 text-[var(--text-muted)]">By continuing, you agree to the <a :href="termsUrl" target="_blank" rel="noopener" class="font-semibold text-[var(--action-primary)] hover:underline">Terms</a>, acknowledge the <a :href="privacyUrl" target="_blank" rel="noopener" class="font-semibold text-[var(--action-primary)] hover:underline">Privacy Policy</a>, and confirm you reviewed the <a :href="route('refund.show')" target="_blank" rel="noopener" class="font-semibold text-[var(--action-primary)] hover:underline">Refund Policy</a>.</p>
                </section>

                <aside class="space-y-5 lg:sticky lg:top-6">
                    <section class="overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-[var(--shadow-raised)]">
                        <div class="bg-[var(--brand-primary)] px-6 py-6 text-white"><div class="flex items-center gap-2 text-sm font-semibold text-white/75"><SparklesIcon class="size-4" aria-hidden="true" /> Selected plan</div><h2 class="mt-3 text-2xl font-semibold">{{ price.plan.name }}</h2><p class="mt-2 text-sm leading-5 text-white/75">{{ price.plan.description }}</p></div>
                        <div class="p-6"><div class="flex items-end justify-between gap-4"><span class="text-sm text-[var(--text-muted)]">Subscription</span><div class="text-right"><strong class="text-2xl font-semibold text-[var(--text-strong)]">{{ money(price.amount_minor, price.currency) }}</strong><span class="block text-xs text-[var(--text-muted)]">per {{ intervalLabel }}</span></div></div><ul class="mt-5 space-y-3 border-t border-[var(--border-subtle)] pt-5 text-sm"><li v-for="feature in includedFeatures" :key="feature" class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />{{ feature }}</li></ul></div>
                    </section>
                    <section class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-5"><div class="flex gap-3"><BuildingStorefrontIcon class="mt-0.5 size-5 text-[var(--action-primary)]" aria-hidden="true" /><div><h2 class="text-sm font-semibold">Subscription owner</h2><p class="mt-1 text-sm text-[var(--text-muted)]">{{ businessLabel }}</p></div></div></section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
