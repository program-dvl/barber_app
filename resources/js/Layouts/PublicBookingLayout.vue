<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { LockClosedIcon } from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';
import BookingSkeleton from '@/Components/Booking/BookingSkeleton.vue';
import MarketplaceLayout from '@/Layouts/MarketplaceLayout.vue';
import { bookingTheme } from '@/Support/bookingTheme';

const props = defineProps({
    title: String,
    preview: Boolean,
    marketplace: Boolean,
    brandColor: String,
    steps: { type: Array, default: () => ['Service', 'Staff & time', 'Your details', 'Review', 'Confirmation'] },
    currentStep: {
        type: Number,
        default: 0,
    },
    mode: {
        type: String,
        default: 'booking',
    },
});

const navigating = ref(false);
let stopStart, stopFinish;
onMounted(() => {
    stopStart = router.on('start', event => { navigating.value = event.detail.visit.method.toLowerCase() === 'get'; });
    stopFinish = router.on('finish', () => { navigating.value = false; });
});
onBeforeUnmount(() => { stopStart?.(); stopFinish?.(); });

const progress = computed(() => props.currentStep <= 0 ? 0 : Math.round((props.currentStep / props.steps.length) * 100));
const theme = computed(() => bookingTheme(props.brandColor));
const marketplaceShell = computed(() => (props.marketplace || props.mode === 'self-service') && !props.preview);
</script>

<template>
    <component :is="marketplaceShell ? MarketplaceLayout : 'div'">
    <div :class="marketplaceShell ? 'mp-booking-flow' : 'cd-product-flow min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]'" :style="theme">
        <Head :title="title" />
        <a v-if="!marketplaceShell" href="#public-main" class="fixed left-3 top-3 z-50 -translate-y-24 rounded-lg bg-white px-4 py-3 font-semibold shadow-[var(--shadow-overlay)] transition-transform focus:translate-y-0">Skip to main content</a>

        <header v-if="!marketplaceShell" class="border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]/95 backdrop-blur">
            <div class="mx-auto flex min-h-16 max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-6">
                <component :is="preview ? 'span' : Link" :href="preview ? undefined : marketplace ? '/marketplace' : route('booking.welcome')" class="inline-flex min-h-11 max-w-full items-center rounded-lg" aria-label="ClipperDesk booking home"><ProductMark :show-tagline="false" /><span v-if="marketplace" class="ml-2 text-xs tracking-widest uppercase">Marketplace</span></component>
                <component :is="preview ? 'span' : Link" v-if="mode === 'booking'" :href="preview ? undefined : marketplace ? '/marketplace/account' : route('booking.manage')" aria-label="Manage appointment" class="inline-flex min-h-11 max-w-full shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]">
                    <LockClosedIcon class="size-4 shrink-0" aria-hidden="true" />
                    <span class="hidden sm:inline">Manage appointment</span><span class="sm:hidden">Manage</span>
                </component>
                <span v-else class="inline-flex items-center gap-2 rounded-full bg-[var(--status-success-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--status-success)]">
                    <LockClosedIcon class="size-4 shrink-0" aria-hidden="true" /> Appointment link
                </span>
            </div>
        </header>

        <div v-if="mode === 'booking'" :class="marketplaceShell ? 'mp-booking-progress' : 'border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]'">
            <div :class="marketplaceShell ? 'mp-booking-width' : 'mx-auto max-w-6xl px-4 py-3 sm:px-6'">
                <div class="mb-2 flex items-center justify-between text-xs font-medium text-[var(--text-muted)]">
                    <span>{{ currentStep === 0 ? 'Ready to start' : `Step ${currentStep} of ${steps.length} · ${steps[currentStep - 1]}` }}</span>
                    <span v-if="currentStep > 0 && currentStep < steps.length">Next: {{ steps[currentStep] }}</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-[var(--surface-subtle)]" aria-hidden="true"><div class="h-full bg-[var(--booking-accent,var(--action-primary))] transition-[width]" :style="{ width: `${progress}%` }" /></div>
                <ol class="ds-sr-only" aria-label="Booking steps">
                    <li v-for="(step, index) in steps" :key="step" :aria-current="currentStep === index + 1 ? 'step' : undefined">{{ step }}</li>
                </ol>
            </div>
        </div>

        <main id="public-main" tabindex="-1" :class="marketplaceShell ? ['mp-booking-width mp-booking-main', { 'mp-self-service-main': mode === 'self-service' }] : ['mx-auto w-full px-4 py-5 sm:px-6 sm:py-6 [overflow-wrap:anywhere]', mode === 'booking' ? 'max-w-6xl' : 'max-w-3xl']">
            <div v-if="navigating" class="rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-5"><BookingSkeleton /></div>
            <slot v-else />
        </main>

        <footer v-if="!marketplaceShell" class="mx-auto max-w-6xl px-4 pb-8 text-center text-xs leading-5 text-[var(--text-muted)] sm:px-6">
            Keep your appointment links private. Your information is used for this appointment and the preferences you choose.
        </footer>
    </div>
    </component>
</template>
