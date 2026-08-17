<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bars3Icon, ShieldCheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';

defineProps({ title: String });

const page = usePage();
const menuOpen = ref(false);
const menuButton = ref(null);
const mobileDrawer = ref(null);
const items = [
    ['overview', 'Operations overview'],
    ['businesses', 'Businesses'],
    ['subscriptions', 'Subscriptions'],
    ['plans-entitlements', 'Plans & entitlements'],
    ['payments-invoices', 'Payments & invoices'],
    ['coupons', 'Coupons'],
    ['support-access', 'Support access'],
    ['notification-logs', 'Notification logs'],
    ['system-health', 'System health'],
    ['feature-flags', 'Feature flags'],
    ['audit-logs', 'Audit logs'],
];
const routeNames = {
    overview: 'platform.overview',
    businesses: 'platform.businesses.index',
    'support-access': 'platform.support-access.index',
    'notification-logs': 'platform.failures.index',
    'system-health': 'platform.health',
    'feature-flags': 'platform.feature-flags.index',
    'audit-logs': 'platform.audit-events.index',
};
const platformHref = key => routeNames[key] ? route(routeNames[key]) : route('platform.module', key);
const navigation = computed(() => items.map(([key, label]) => ({ key, label, href: platformHref(key) })));
const path = computed(() => page.url.split('?')[0]);
const active = item => new URL(item.href, 'http://app.local').pathname === path.value;

const openMenu = async () => {
    menuOpen.value = true;
    await nextTick();
    mobileDrawer.value?.querySelector('a')?.focus();
};

const closeMenu = async ({ restoreFocus = false } = {}) => {
    menuOpen.value = false;
    if (restoreFocus) {
        await nextTick();
        menuButton.value?.focus();
    }
};

const handleKeydown = event => {
    if (event.key === 'Escape' && menuOpen.value) closeMenu({ restoreFocus: true });
    if (event.key === 'Tab' && menuOpen.value) {
        const focusable = [...(mobileDrawer.value?.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [])];
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    }
};

onMounted(() => document.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div class="min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
        <Head :title="title" />
        <a href="#platform-main" class="fixed left-3 top-3 z-[60] -translate-y-24 rounded-lg bg-white px-4 py-3 font-semibold shadow-lg transition-transform focus:translate-y-0">Skip to main content</a>
        <aside class="fixed inset-y-0 left-0 hidden w-72 flex-col bg-[#17221f] text-white lg:flex" aria-label="Platform administration">
            <div class="border-b border-white/10 p-5">
                <ProductMark inverse />
                <div class="mt-5 flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-amber-400 text-[#18201e]"><ShieldCheckIcon class="size-6" aria-hidden="true" /></span><div><p class="font-semibold">Platform administration</p><p class="text-xs text-white/60">Internal operations only</p></div></div>
            </div>
            <nav class="min-h-0 flex-1 overflow-y-auto p-3" aria-label="Platform primary">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.key"><Link :href="item.href" :aria-current="active(item) ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-lg px-3 text-sm font-medium', active(item) ? 'bg-amber-400 text-[#18201e]' : 'text-white/75 hover:bg-white/10 hover:text-white']">{{ item.label }}</Link></li>
                </ul>
            </nav>
            <div class="border-t border-white/10 p-4 text-xs leading-5 text-white/65">Platform access does not grant ordinary tenant record access.</div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 flex min-h-16 items-center justify-between gap-3 border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button ref="menuButton" type="button" class="grid size-11 shrink-0 place-items-center rounded-lg hover:bg-black/5 lg:hidden" aria-label="Open platform navigation" :aria-expanded="menuOpen" aria-controls="platform-mobile-navigation" @click="openMenu"><Bars3Icon class="size-6" aria-hidden="true" /></button>
                    <span class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.08em] text-amber-900"><ShieldCheckIcon class="size-4" aria-hidden="true" /> Platform operations</span>
                </div>
                <span class="truncate text-sm font-semibold">{{ $page.props.auth.user.name }}</span>
            </header>
            <div class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 sm:px-6 lg:px-8" role="note"><strong>Support access is off.</strong> Business records stay private unless a separate, time-limited support session is approved.</div>
            <main id="platform-main" tabindex="-1" class="mx-auto max-w-[100rem] px-4 py-6 sm:px-6 sm:py-8 lg:px-8"><slot /></main>
        </div>

        <div v-if="menuOpen" class="fixed inset-0 z-50 lg:hidden">
            <button type="button" class="absolute inset-0 bg-black/55" aria-label="Close platform navigation" @click="closeMenu({ restoreFocus: true })" />
            <aside id="platform-mobile-navigation" ref="mobileDrawer" role="dialog" aria-modal="true" aria-label="Platform administration" class="absolute inset-y-0 left-0 flex w-[min(22rem,90vw)] flex-col bg-[#17221f] text-white shadow-2xl">
                <div class="flex min-h-16 items-center justify-between border-b border-white/10 px-4"><div class="flex items-center gap-3"><ProductMark inverse compact /><p class="font-semibold">Platform administration</p></div><button type="button" class="grid size-11 place-items-center rounded-lg hover:bg-white/10" aria-label="Close platform navigation" @click="closeMenu({ restoreFocus: true })"><XMarkIcon class="size-6" aria-hidden="true" /></button></div>
                <nav class="min-h-0 flex-1 overflow-y-auto p-3" aria-label="Platform mobile primary"><ul class="space-y-1"><li v-for="item in navigation" :key="item.key"><Link :href="item.href" :aria-current="active(item) ? 'page' : undefined" :class="['flex min-h-12 items-center rounded-lg px-3 text-sm font-medium', active(item) ? 'bg-amber-400 text-[#18201e]' : 'text-white/75 hover:bg-white/10']" @click="closeMenu()">{{ item.label }}</Link></li></ul></nav>
            </aside>
        </div>
    </div>
</template>
