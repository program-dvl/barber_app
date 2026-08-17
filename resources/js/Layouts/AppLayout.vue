<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArchiveBoxIcon,
    BanknotesIcon,
    Bars3Icon,
    CalendarDaysIcon,
    ChartBarIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    CreditCardIcon,
    HomeIcon,
    QueueListIcon,
    Squares2X2Icon,
    UserGroupIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';

const props = defineProps({
    title: String,
    businessLabel: {
        type: String,
        default: 'Your shop',
    },
    navigationVisibility: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const businessRouteParameter = computed(() => page.props.tenant?.public_id);
const isBusinessWorkspace = computed(() => Boolean(businessRouteParameter.value));
const accountWorkspaces = computed(() => page.props.account?.workspaces ?? []);
const primaryWorkspace = computed(() => accountWorkspaces.value.length === 1 ? accountWorkspaces.value[0] : null);
const billingWorkspaces = computed(() => accountWorkspaces.value.filter(workspace => workspace.can_manage_billing));
const tenantSubscription = computed(() => page.props.tenant?.subscription);
const subscriptionStatusLabel = computed(() => ({
    trialing: 'Trial',
    active: 'Active',
    past_due: 'Past due',
    grace: 'Payment retry',
    restricted: 'Restricted',
    cancel_scheduled: 'Cancels soon',
    canceled: 'Canceled',
    terminated: 'Closed',
}[tenantSubscription.value?.status] || tenantSubscription.value?.status?.replaceAll('_', ' ')));
const subscriptionStatusTone = computed(() => ['past_due', 'grace', 'restricted'].includes(tenantSubscription.value?.status)
    ? 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]'
    : ['canceled', 'terminated'].includes(tenantSubscription.value?.status)
        ? 'bg-[var(--status-danger-soft)] text-[var(--status-danger)]'
        : 'bg-[var(--status-success-soft)] text-[var(--status-success)]');
const navigationOpen = ref(false);
const menuButton = ref(null);
const drawer = ref(null);

const navigation = computed(() => {
    if (!businessRouteParameter.value) return [];

    return [
    { key: 'dashboard', label: 'Dashboard', href: route('business.dashboard', businessRouteParameter.value), icon: HomeIcon },
    { key: 'calendar', label: 'Calendar', href: route('business.calendar', businessRouteParameter.value), icon: CalendarDaysIcon },
    { key: 'walk-in-queue', label: 'Walk-in queue', href: route('business.walk-ins.index', businessRouteParameter.value), icon: QueueListIcon },
    { key: 'clients', label: 'Clients', href: route('business.clients.index', businessRouteParameter.value), icon: UsersIcon },
    { key: 'checkout-sales', label: 'Checkout & sales', href: route('shop.module', [businessRouteParameter.value, 'checkout-sales']), icon: BanknotesIcon },
    { key: 'staff', label: 'Staff', href: route('shop.module', [businessRouteParameter.value, 'staff']), icon: UserGroupIcon },
    { key: 'services', label: 'Services', href: route('shop.module', [businessRouteParameter.value, 'services']), icon: ClipboardDocumentListIcon },
    { key: 'inventory', label: 'Inventory', href: route('shop.module', [businessRouteParameter.value, 'inventory']), icon: ArchiveBoxIcon },
    { key: 'reports', label: 'Reports', href: route('shop.module', [businessRouteParameter.value, 'reports']), icon: ChartBarIcon },
    { key: 'settings', label: 'Settings', href: route('business.configuration.show', businessRouteParameter.value), icon: Cog6ToothIcon },
    { key: 'subscription-billing', label: 'Subscription & billing', href: route('business.billing.show', businessRouteParameter.value), icon: CreditCardIcon },
    ].filter(item => props.navigationVisibility[item.key] !== false);
});

const primaryMobileNavigation = computed(() => navigation.value.filter(item => ['dashboard', 'calendar', 'walk-in-queue', 'clients'].includes(item.key)));
const normalizedPath = computed(() => page.url.split('?')[0]);
const isActive = item => new URL(item.href, 'http://app.local').pathname === normalizedPath.value;
const currentLabel = computed(() => navigation.value.find(isActive)?.label || props.title || 'Shop application');

const openNavigation = async () => {
    navigationOpen.value = true;
    await nextTick();
    drawer.value?.querySelector('a')?.focus();
};

const closeNavigation = ({ restoreFocus = false } = {}) => {
    navigationOpen.value = false;
    if (restoreFocus) nextTick(() => menuButton.value?.focus());
};

const handleKeydown = event => {
    if (event.key === 'Escape' && navigationOpen.value) closeNavigation({ restoreFocus: true });
    if (event.key === 'Tab' && navigationOpen.value) {
        const focusable = [...(drawer.value?.querySelectorAll('a[href], button:not([disabled]), summary, [tabindex]:not([tabindex="-1"])') ?? [])];
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

const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
        <Head :title="title" />

        <a href="#main-content" class="fixed left-3 top-3 z-[70] -translate-y-24 rounded-lg bg-[var(--surface-raised)] px-4 py-3 font-semibold text-[var(--text-strong)] shadow-[var(--shadow-overlay)] transition-transform focus:translate-y-0">
            Skip to main content
        </a>

        <aside v-if="isBusinessWorkspace" class="fixed inset-y-0 left-0 z-30 hidden w-[17rem] flex-col border-r border-white/10 bg-[var(--surface-inverse)] text-white lg:flex" aria-label="Shop navigation">
            <div class="flex h-20 items-center px-5">
                <ProductMark inverse large />
            </div>
            <div class="mx-4 mb-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-white/55">Working in</p>
                <p class="mt-1 truncate text-sm font-semibold text-white">{{ businessLabel }}</p>
                <p v-if="tenantSubscription" class="mt-1 truncate text-xs text-white/60">{{ tenantSubscription.plan_name }} · {{ subscriptionStatusLabel }}</p>
            </div>
            <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-4" aria-label="Primary">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.key">
                        <Link
                            :href="item.href"
                            preserve-scroll
                            :aria-current="isActive(item) ? 'page' : undefined"
                            :class="[
                                'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                isActive(item) ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/10' : 'text-white/75 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <component :is="item.icon" class="size-5 shrink-0" aria-hidden="true" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </li>
                </ul>
            </nav>
            <div class="border-t border-white/10 p-3">
                <Link :href="route('profile.show')" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white">
                    <span class="grid size-8 place-items-center rounded-full bg-white/10 font-semibold" aria-hidden="true">{{ $page.props.auth.user.name?.charAt(0) }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $page.props.auth.user.name }}</span>
                    <span class="ds-sr-only">Open account profile</span>
                </Link>
            </div>
        </aside>

        <div :class="{ 'lg:pl-[17rem]': isBusinessWorkspace }">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button v-if="isBusinessWorkspace" ref="menuButton" type="button" class="grid size-11 shrink-0 place-items-center rounded-lg text-[var(--text-default)] hover:bg-[var(--surface-subtle)] lg:hidden" aria-label="Open navigation" aria-controls="mobile-navigation" :aria-expanded="navigationOpen" @click="openNavigation">
                        <Bars3Icon class="size-6" aria-hidden="true" />
                    </button>
                    <ProductMark v-else class="hidden shrink-0 sm:block" />
                    <p class="truncate text-sm font-semibold text-[var(--text-strong)]">{{ currentLabel }}</p>
                    <Link v-if="tenantSubscription && $page.props.tenant.can_manage_billing" :href="route('business.billing.show', businessRouteParameter)" :class="['hidden min-h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold md:inline-flex', subscriptionStatusTone]" :aria-label="`${tenantSubscription.plan_name} subscription: ${subscriptionStatusLabel}`">
                        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" /> {{ tenantSubscription.plan_name }} · {{ subscriptionStatusLabel }}
                    </Link>
                    <span v-else-if="tenantSubscription" :class="['hidden min-h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold md:inline-flex', subscriptionStatusTone]">
                        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" /> {{ tenantSubscription.plan_name }} · {{ subscriptionStatusLabel }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="primaryWorkspace" :href="route('business.dashboard', primaryWorkspace.public_id)" class="hidden min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)] sm:flex">
                        Open {{ primaryWorkspace.name }}
                    </Link>
                    <Link v-if="primaryWorkspace?.can_manage_billing" :href="route('business.billing.show', primaryWorkspace.public_id)" class="hidden min-h-11 items-center rounded-lg bg-[var(--brand-pine)] px-3 text-sm font-semibold text-white hover:opacity-90 sm:flex">
                        Subscription & billing
                    </Link>
                    <details class="relative">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-lg px-3 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]" aria-label="Account menu">
                            <span class="grid size-7 place-items-center rounded-full bg-[var(--surface-subtle)] text-xs" aria-hidden="true">{{ $page.props.auth.user.name?.charAt(0) }}</span>
                            <span class="hidden sm:inline">Account</span>
                        </summary>
                        <div class="absolute right-0 mt-2 w-64 overflow-hidden rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-1 shadow-[var(--shadow-overlay)]">
                            <Link :href="route('profile.show')" class="flex min-h-11 items-center rounded-lg px-3 text-sm hover:bg-[var(--surface-subtle)]">Profile & security</Link>
                            <template v-if="billingWorkspaces.length">
                                <p class="px-3 pt-3 text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">Subscription & billing</p>
                                <Link v-for="workspace in billingWorkspaces" :key="workspace.public_id" :href="route('business.billing.show', workspace.public_id)" class="flex min-h-11 items-center rounded-lg px-3 text-sm hover:bg-[var(--surface-subtle)]">
                                    {{ workspace.name }}
                                </Link>
                            </template>
                            <button type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm hover:bg-[var(--surface-subtle)]" @click="logout">Log out</button>
                        </div>
                    </details>
                </div>
            </header>

            <div v-for="session in $page.props.supportAccessBanner" :key="`${session.operator}-${session.expires_at}`" class="border-b border-amber-300 bg-amber-100 px-4 py-3 text-sm text-amber-950 sm:px-6 lg:px-8" role="status">
                <strong>{{ session.operator }} from Good Hours Support is viewing this account.</strong>
                Ticket {{ session.ticket_reference }} · {{ session.reason }} · access expires {{ new Date(session.expires_at).toLocaleString() }}.
            </div>
            <div v-for="notice in $page.props.platformNotices" :key="notice.public_id" class="border-b border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-4 py-3 text-sm sm:px-6 lg:px-8" role="status">
                <strong>{{ notice.title }}</strong> {{ notice.message }}
            </div>

            <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[96rem] px-4 py-6 pb-28 sm:px-6 sm:py-7 lg:px-8 lg:pb-8">
                <div v-if="$page.props.flash?.status" role="status" class="mb-5 rounded-xl border border-[var(--status-success)]/30 bg-[var(--status-success-soft)] p-4 text-sm">
                    <p class="font-semibold text-[var(--text-strong)]">{{ $page.props.flash.status }}</p>
                    <a v-if="$page.props.flash.secure_url" :href="$page.props.flash.secure_url" class="mt-2 inline-flex min-h-11 items-center font-semibold text-[var(--action-primary)]">Open secure link</a>
                </div>
                <slot />
            </main>
        </div>

        <div v-if="isBusinessWorkspace && navigationOpen" class="fixed inset-0 z-50 lg:hidden">
            <button type="button" class="absolute inset-0 bg-black/50" aria-label="Close navigation" @click="closeNavigation({ restoreFocus: true })" />
            <aside id="mobile-navigation" ref="drawer" role="dialog" aria-modal="true" aria-label="Shop navigation" class="absolute inset-y-0 left-0 flex w-[min(21rem,88vw)] flex-col bg-[var(--surface-raised)] shadow-[var(--shadow-overlay)]">
                <div class="flex h-16 items-center justify-between border-b border-[var(--border-subtle)] px-4">
                    <ProductMark />
                    <button type="button" class="grid size-11 place-items-center rounded-lg hover:bg-[var(--surface-subtle)]" aria-label="Close navigation" @click="closeNavigation({ restoreFocus: true })">
                        <XMarkIcon class="size-6" aria-hidden="true" />
                    </button>
                </div>
                <div class="border-b border-[var(--border-subtle)] px-4 py-3">
                    <p class="text-xs font-medium text-[var(--text-muted)]">Working in</p>
                    <p class="font-semibold text-[var(--text-strong)]">{{ businessLabel }}</p>
                </div>
                <nav class="min-h-0 flex-1 overflow-y-auto p-3" aria-label="Mobile primary">
                    <ul class="space-y-1">
                        <li v-for="item in navigation" :key="item.key">
                            <Link :href="item.href" preserve-scroll :aria-current="isActive(item) ? 'page' : undefined" :class="['flex min-h-12 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium', isActive(item) ? 'bg-[var(--status-success-soft)] text-[var(--action-primary)]' : 'hover:bg-[var(--surface-subtle)]']" @click="closeNavigation()">
                                <component :is="item.icon" class="size-5" aria-hidden="true" />
                                {{ item.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>
        </div>

        <nav v-if="isBusinessWorkspace" class="fixed inset-x-0 bottom-0 z-20 border-t border-[var(--border-subtle)] bg-[var(--surface-raised)] px-1 pb-[max(0.25rem,env(safe-area-inset-bottom))] lg:hidden" aria-label="Quick navigation">
            <ul class="grid grid-cols-5">
                <li v-for="item in primaryMobileNavigation" :key="item.key">
                    <Link :href="item.href" preserve-scroll :aria-current="isActive(item) ? 'page' : undefined" :class="['flex min-h-16 flex-col items-center justify-center gap-1 rounded-lg px-1 text-[0.6875rem] font-medium', isActive(item) ? 'text-[var(--action-primary)]' : 'text-[var(--text-muted)]']">
                        <component :is="item.icon" class="size-5" aria-hidden="true" />
                        <span class="max-w-full truncate">{{ item.key === 'walk-in-queue' ? 'Queue' : item.label }}</span>
                    </Link>
                </li>
                <li>
                    <button type="button" class="flex min-h-16 w-full flex-col items-center justify-center gap-1 rounded-lg px-1 text-[0.6875rem] font-medium text-[var(--text-muted)]" aria-label="Open all navigation" @click="openNavigation">
                        <Squares2X2Icon class="size-5" aria-hidden="true" />
                        More
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>
