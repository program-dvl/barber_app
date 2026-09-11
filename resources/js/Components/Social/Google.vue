<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    intent: { type: String, default: 'login', validator: value => ['login', 'register'].includes(value) },
    plan: { type: String, default: null },
    interval: { type: String, default: null },
});

const page = usePage();
const enabled = computed(() => Boolean(page.props.googleAuth?.enabled));
const href = computed(() => route('auth.google.redirect', {
    intent: props.intent,
    ...(props.plan ? { plan: props.plan } : {}),
    ...(props.interval ? { interval: props.interval } : {}),
}));
</script>

<template>
    <a v-if="enabled" :href="href" class="cd-google-button group flex min-h-12 w-full items-center justify-center gap-3 rounded-[var(--radius-md)] border px-4 py-3 text-sm font-bold text-[var(--text-strong)] transition">
        <svg class="size-5 shrink-0" viewBox="0 0 18 18" aria-hidden="true">
            <path fill="#4285F4" d="M17.64 9.205c0-.639-.057-1.253-.164-1.842H9v3.482h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.874 2.684-6.615Z"/>
            <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A9 9 0 0 0 9 18Z"/>
            <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A9 9 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332Z"/>
            <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.453 3.44 1.345l2.582-2.581C13.463.892 11.426 0 9 0A9 9 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58Z"/>
        </svg>
        <span>{{ intent === 'register' ? 'Sign up with Google' : 'Continue with Google' }}</span>
    </a>
</template>
