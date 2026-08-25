<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDaysIcon, CheckCircleIcon, LockClosedIcon, UsersIcon } from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';

defineProps({ title: { type: String, default: 'ClipperDesk' } });
</script>

<template>
    <div class="min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)] lg:grid lg:grid-cols-[minmax(26rem,0.88fr)_minmax(34rem,1.12fr)]">
        <Head :title="title" />
        <aside class="relative hidden min-h-screen overflow-hidden bg-[var(--navigation-background)] px-10 py-9 text-white lg:flex lg:flex-col xl:px-14 xl:py-11">
            <div class="absolute inset-0 opacity-[0.07]" aria-hidden="true" style="background-image: linear-gradient(rgb(255 255 255 / .4) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / .4) 1px, transparent 1px); background-size: 40px 40px;"></div>
            <div class="absolute -right-32 top-24 size-80 rounded-full bg-[var(--brand-accent)] opacity-15 blur-3xl" aria-hidden="true"></div>
            <Link href="/" class="relative inline-flex w-fit min-h-11 items-center" aria-label="ClipperDesk home"><ProductMark inverse large /></Link>

            <div class="relative my-auto max-w-lg py-12">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--navigation-accent)]">The operating system for modern service teams</p>
                <h1 class="cd-display mt-5 max-w-md text-[2.65rem] font-bold leading-[1.08] text-white">One workspace for every busy day.</h1>
                <p class="mt-5 max-w-md text-[0.95rem] leading-7 text-white/70">Bring appointments, staff, clients, and daily performance into a calm, secure command centre.</p>

                <div class="mt-9 overflow-hidden rounded-2xl border border-white/12 bg-white/[0.07] p-4 shadow-2xl backdrop-blur-sm">
                    <div class="flex items-center justify-between border-b border-white/10 pb-4">
                        <div><p class="text-xs font-semibold text-white/55">TODAY · FRONT DESK</p><p class="mt-1 text-sm font-bold text-white">A clear view before doors open</p></div>
                        <span class="rounded-full bg-emerald-400/15 px-2.5 py-1 text-xs font-bold text-emerald-200">On track</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-4">
                        <div class="rounded-xl bg-black/15 p-3"><CalendarDaysIcon class="size-4 text-cyan-200" aria-hidden="true"/><p class="mt-3 text-xl font-bold">18</p><p class="mt-1 text-[0.68rem] text-white/55">Appointments</p></div>
                        <div class="rounded-xl bg-black/15 p-3"><UsersIcon class="size-4 text-cyan-200" aria-hidden="true"/><p class="mt-3 text-xl font-bold">6</p><p class="mt-1 text-[0.68rem] text-white/55">Team active</p></div>
                        <div class="rounded-xl bg-black/15 p-3"><CheckCircleIcon class="size-4 text-cyan-200" aria-hidden="true"/><p class="mt-3 text-xl font-bold">82%</p><p class="mt-1 text-[0.68rem] text-white/55">Utilisation</p></div>
                    </div>
                    <div class="flex items-center gap-2 rounded-xl border border-white/8 bg-black/10 px-3 py-2.5 text-xs text-white/65"><span class="size-2 rounded-full bg-emerald-300" aria-hidden="true"></span>Schedule, client notes, and permissions are in sync.</div>
                </div>
            </div>

            <div class="relative flex items-center gap-2 text-xs text-white/55"><LockClosedIcon class="size-4" aria-hidden="true" />Secure, role-aware access for your business</div>
        </aside>

        <main class="relative flex min-h-screen items-center justify-center px-4 py-7 sm:px-7 lg:px-10 lg:py-10">
            <div class="absolute inset-x-0 top-0 h-1 bg-[var(--brand-accent)] lg:hidden" aria-hidden="true"></div>
            <div class="w-full max-w-2xl">
                <Link href="/" class="mb-7 inline-flex min-h-11 items-center lg:hidden" aria-label="ClipperDesk home"><ProductMark large /></Link>
                <slot />
                <p class="mx-auto mt-6 max-w-md text-center text-xs leading-5 text-[var(--text-muted)]">Protected by encrypted sessions, rate-limited access, and verified identities.</p>
            </div>
        </main>
    </div>
</template>
