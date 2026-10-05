<script setup>
import '../../css/workspace-records.css';
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bars3Icon, ShieldCheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';

defineProps({ title: String });

const page = usePage();
const menuOpen = ref(false);
const menuButton = ref(null);
const mobileDrawer = ref(null);
const items = [
    ['overview', 'Platform overview'],
    ['businesses', 'Businesses'],
    ['subscriptions', 'Subscriptions'],
    ['plans-entitlements', 'Plans & entitlements'],
    ['payments-invoices', 'Payments & invoices'],
    ['coupons', 'Coupons'],
    ['support-access', 'Support access'],
    ['notification-logs', 'Failed operations'],
    ['system-health', 'System health'],
    ['feature-flags', 'Feature flags'],
    ['audit-logs', 'Audit log'],
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
provide('workspaceBreadcrumbs', computed(() => [{ label: 'Platform', href: platformHref('overview'), current: path.value === new URL(platformHref('overview')).pathname }, ...navigation.value.filter(item => active(item) && item.key !== 'overview').map(item => ({ label: item.label, current: true }))]));

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
    <div class="cd-workspace min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
        <Head :title="title" />
        <a :inert="menuOpen" href="#platform-main" class="fixed left-3 top-3 z-[60] -translate-y-24 rounded-lg bg-white px-4 py-3 font-semibold shadow-lg transition-transform focus:translate-y-0">Skip to main content</a>
        <aside :inert="menuOpen" class="fixed inset-y-0 left-0 cd-sidebar cd-navigation-panel hidden w-[15rem] flex-col border-r border-[var(--border-subtle)] text-[var(--text-default)] lg:flex" aria-label="Platform admin">
            <div class="border-b border-[var(--border-subtle)] p-4">
                <ProductMark inverse small :show-tagline="false" />
                <div class="mt-4 flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-[var(--brand-accent-soft)] text-[var(--brand-primary-strong)]"><ShieldCheckIcon class="size-6" aria-hidden="true" /></span><div><p class="font-semibold">Platform admin</p></div></div>
            </div>
            <nav class="min-h-0 flex-1 overflow-y-auto p-3" aria-label="Platform primary">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.key"><Link :href="item.href" :aria-current="active(item) ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-lg px-3 text-sm font-medium', active(item) ? 'bg-[var(--action-secondary-hover)] text-[var(--action-primary)]' : 'text-[var(--text-default)] hover:bg-[var(--surface-subtle)]']">{{ item.label }}</Link></li>
                </ul>
            </nav>
        </aside>

        <div :inert="menuOpen" class="lg:pl-[15rem]">
            <header class="cd-workspace-topbar sticky top-0 z-20 flex min-h-14 items-center justify-between gap-3 border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button ref="menuButton" type="button" class="grid size-11 shrink-0 place-items-center rounded-lg hover:bg-black/5 lg:hidden" aria-label="Open platform navigation" :aria-expanded="menuOpen" aria-controls="platform-mobile-navigation" @click="openMenu"><Bars3Icon class="size-6" aria-hidden="true" /></button>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--brand-accent-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--brand-primary)]"><ShieldCheckIcon class="size-4" aria-hidden="true" /> Platform operations</span>
                </div>
                <div class="flex min-w-0 items-center gap-2.5"><span class="cd-account-avatar" aria-hidden="true">{{ $page.props.auth.user.name?.charAt(0) }}</span><span class="truncate text-sm font-semibold">{{ $page.props.auth.user.name }}</span></div>
            </header>
            <div class="border-b border-[var(--status-danger)]/20 bg-[var(--surface-subtle)] px-4 py-2 text-sm text-[var(--text-muted)] sm:px-6 lg:px-8" role="note">Business records require an approved, time-limited support session.</div>
            <main id="platform-main" tabindex="-1" class="mx-auto max-w-[96rem] px-4 py-5 sm:px-6"><slot /></main>
        </div>

        <div v-if="menuOpen" class="fixed inset-0 z-50 lg:hidden">
            <button type="button" class="absolute inset-0 bg-black/55" aria-label="Close platform navigation" @click="closeMenu({ restoreFocus: true })" />
            <aside id="platform-mobile-navigation" ref="mobileDrawer" role="dialog" aria-modal="true" aria-label="Platform admin" class="cd-navigation-panel absolute inset-y-0 left-0 flex w-[min(22rem,90vw)] flex-col text-[var(--text-default)] shadow-[var(--shadow-overlay)]">
                <div class="flex min-h-16 items-center justify-between border-b border-[var(--border-subtle)] px-4"><div class="flex items-center gap-3"><ProductMark inverse small :show-tagline="false" /><p class="font-semibold">Platform admin</p></div><button type="button" class="grid size-11 place-items-center rounded-lg hover:bg-[var(--surface-subtle)]" aria-label="Close platform navigation" @click="closeMenu({ restoreFocus: true })"><XMarkIcon class="size-6" aria-hidden="true" /></button></div>
                <nav class="min-h-0 flex-1 overflow-y-auto p-3" aria-label="Platform mobile primary"><ul class="space-y-1"><li v-for="item in navigation" :key="item.key"><Link :href="item.href" :aria-current="active(item) ? 'page' : undefined" :class="['flex min-h-12 items-center rounded-lg px-3 text-sm font-medium', active(item) ? 'bg-[var(--action-secondary-hover)] text-[var(--action-primary)]' : 'text-[var(--text-default)] hover:bg-[var(--surface-subtle)]']" @click="closeMenu()">{{ item.label }}</Link></li></ul></nav>
            </aside>
        </div>
    </div>
</template>
