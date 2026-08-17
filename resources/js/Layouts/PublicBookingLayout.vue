<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { LockClosedIcon } from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';

const props = defineProps({
    title: String,
    currentStep: {
        type: Number,
        default: 0,
    },
    mode: {
        type: String,
        default: 'booking',
    },
});

const steps = ['Service', 'Staff & time', 'Your details', 'Review', 'Confirmation'];
const progress = computed(() => props.currentStep <= 0 ? 0 : Math.round((props.currentStep / steps.length) * 100));
</script>

<template>
    <div class="min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
        <Head :title="title" />
        <a href="#public-main" class="fixed left-3 top-3 z-50 -translate-y-24 rounded-lg bg-white px-4 py-3 font-semibold shadow-[var(--shadow-overlay)] transition-transform focus:translate-y-0">Skip to main content</a>

        <header class="border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]">
            <div class="mx-auto flex min-h-16 max-w-5xl items-center justify-between gap-3 px-4 sm:px-6">
                <Link :href="route('booking.welcome')" class="inline-flex min-h-11 items-center rounded-lg" aria-label="Good Hours booking home"><ProductMark /></Link>
                <Link v-if="mode === 'booking'" :href="route('booking.manage')" aria-label="Manage appointment" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]">
                    <LockClosedIcon class="size-4" aria-hidden="true" />
                    <span class="hidden sm:inline">Manage appointment</span><span class="sm:hidden">Manage</span>
                </Link>
                <span v-else class="inline-flex items-center gap-2 rounded-full bg-[var(--status-success-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--status-success)]">
                    <LockClosedIcon class="size-4" aria-hidden="true" /> Secure self-service
                </span>
            </div>
        </header>

        <div v-if="mode === 'booking'" class="border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]">
            <div class="mx-auto max-w-3xl px-4 py-3 sm:px-6">
                <div class="mb-2 flex items-center justify-between text-xs font-medium text-[var(--text-muted)]">
                    <span>{{ currentStep === 0 ? 'Ready to start' : `Step ${currentStep} of ${steps.length} · ${steps[currentStep - 1]}` }}</span>
                    <span v-if="currentStep > 0 && currentStep < steps.length">Next: {{ steps[currentStep] }}</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-[var(--surface-subtle)]" aria-hidden="true"><div class="h-full bg-[var(--action-primary)] transition-[width]" :style="{ width: `${progress}%` }" /></div>
                <ol class="ds-sr-only" aria-label="Booking steps">
                    <li v-for="(step, index) in steps" :key="step" :aria-current="currentStep === index + 1 ? 'step' : undefined">{{ step }}</li>
                </ol>
            </div>
        </div>

        <main id="public-main" tabindex="-1" class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
            <slot />
        </main>

        <footer class="mx-auto max-w-3xl px-4 pb-8 text-center text-xs leading-5 text-[var(--text-muted)] sm:px-6">
            Your information is used only to manage this booking and the choices you make here. Keep secure appointment links private on a shared device.
        </footer>
    </div>
</template>
