<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDaysIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';

defineProps({ title: { type: String, default: 'Good Hours' } });
</script>

<template>
    <div class="min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)] lg:grid lg:grid-cols-[minmax(22rem,0.8fr)_minmax(32rem,1.2fr)]">
        <Head :title="title" />
        <aside class="hidden min-h-screen flex-col justify-between bg-[var(--surface-inverse)] p-10 text-white lg:flex xl:p-14">
            <Link href="/" class="inline-flex w-fit min-h-11 items-center"><ProductMark inverse large /></Link>
            <div class="max-w-md py-12">
                <p class="text-sm font-semibold uppercase tracking-[0.12em] text-white/60">Salon operations, made calm</p>
                <h1 class="gh-display mt-4 text-5xl leading-[1.05]">Make every hour count.</h1>
                <p class="mt-5 text-base leading-7 text-white/70">Plan the day, look after clients, and keep the front desk moving without losing the details that matter.</p>
                <ul class="mt-8 space-y-4 text-sm text-white/80"><li class="flex gap-3"><CalendarDaysIcon class="size-5 shrink-0 text-[var(--brand-apricot)]" aria-hidden="true" />One clear view of appointments, staff, and walk-ins.</li><li class="flex gap-3"><LockClosedIcon class="size-5 shrink-0 text-[var(--brand-apricot)]" aria-hidden="true" />Private, role-aware records for your business and clients.</li></ul>
            </div>
            <p class="text-xs text-white/50">Good Hours · Secure salon management</p>
        </aside>
        <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-10">
            <div class="w-full max-w-md">
                <Link href="/" class="mb-8 inline-flex min-h-11 items-center lg:hidden"><ProductMark large /></Link>
                <slot />
            </div>
        </main>
    </div>
</template>
