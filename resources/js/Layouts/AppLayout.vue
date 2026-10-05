<script setup>
import '../../css/workspace-records.css';
import { computed, nextTick, provide, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRightOnRectangleIcon,
    BanknotesIcon,
    Bars3Icon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    ChartBarIcon,
    CheckCircleIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ClockIcon,
    ClipboardDocumentListIcon,
    ScissorsIcon,
    Cog6ToothIcon,
    CreditCardIcon,
    HomeIcon,
    QueueListIcon,
    Squares2X2Icon,
    SparklesIcon,
    UserGroupIcon,
    UserCircleIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';
import { useActionMenus } from '@/Support/useActionMenus';

useActionMenus();

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
const tenantAccess = computed(() => page.props.tenant?.access);
const tenantEntitlements = computed(() => page.props.tenant?.entitlements ?? {});
const tenantFeatures = computed(() => page.props.tenant?.features ?? {});
const setupProgress = computed(() => page.props.tenant?.setup ?? null);
const accessNotice = computed(() => {
    const access = tenantAccess.value;
    if (!access) return null;
    const trialExpired = access.status === 'trialing' && access.trial_ends_at && new Date(access.trial_ends_at).getTime() <= Date.now();
    if (trialExpired) return 'Your free trial has ended. Existing information remains readable, but protected changes require an active plan.';
    if (access.status === 'restricted') return 'This workspace is read-only until subscription billing is resolved.';
    if (['past_due', 'grace'].includes(access.status)) return `A subscription payment needs attention${access.grace_ends_at ? ` before ${new Date(access.grace_ends_at).toLocaleDateString()}` : ''}. Product access remains available during recovery.`;
    return null;
});
const subscriptionStatusLabel = computed(() => ({
    trialing: 'Free trial',
    active: 'Active',
    past_due: 'Payment due',
    grace: 'Payment due',
    restricted: 'Read-only access',
    cancel_scheduled: 'Cancellation scheduled',
    canceled: 'Canceled',
    terminated: 'Subscription ended',
}[tenantSubscription.value?.status] || tenantSubscription.value?.status?.replaceAll('_', ' ')));
const subscriptionStatusTone = computed(() => ['past_due', 'grace', 'restricted'].includes(tenantSubscription.value?.status)
    ? 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]'
    : ['canceled', 'terminated'].includes(tenantSubscription.value?.status)
        ? 'bg-[var(--status-danger-soft)] text-[var(--status-danger)]'
        : 'bg-[var(--status-success-soft)] text-[var(--status-success)]');
const navigationOpen = ref(false);
const menuButton = ref(null);
const drawer = ref(null);
const setupOpen = ref(false);
const setupButton = ref(null);
const setupDrawer = ref(null);

const navigation = computed(() => {
    if (!businessRouteParameter.value) return [];

    return [
    { key: 'dashboard', label: 'Dashboard', href: route('business.dashboard', businessRouteParameter.value), icon: HomeIcon },
    { key: 'calendar', label: 'Calendar', href: route('business.calendar', businessRouteParameter.value), icon: CalendarDaysIcon },
    { key: 'walk-in-queue', label: 'Walk-in queue', href: route('business.walk-ins.index', businessRouteParameter.value), icon: QueueListIcon },
    { key: 'clients', label: 'Clients', href: route('business.clients.index', businessRouteParameter.value), icon: UsersIcon },
    { key: 'checkout-sales', label: 'Checkout & sales', href: route('business.checkout.index', businessRouteParameter.value), icon: BanknotesIcon },
    { key: 'staff', label: 'Team & availability', href: route('business.team.index', businessRouteParameter.value), icon: UserGroupIcon },
    { key: 'activity', label: 'Activity log', href: route('business.activity.index', businessRouteParameter.value), icon: ClockIcon },
    { key: 'services', label: 'Services', href: route('business.services.index', businessRouteParameter.value), icon: ScissorsIcon },
    // Inventory remains implemented internally but is deferred from the menu.
    { key: 'reports', label: 'Reports', href: route('business.reports.index', businessRouteParameter.value), icon: ChartBarIcon },
    { key: 'communications', label: 'Client notifications', href: route('business.communications.page', businessRouteParameter.value), icon: ChatBubbleLeftRightIcon },
    { key: 'settings', label: 'Business setup', href: route('business.configuration.show', businessRouteParameter.value), icon: Cog6ToothIcon },
    { key: 'subscription-billing', label: 'Subscription & billing', href: route('business.billing.show', businessRouteParameter.value), icon: CreditCardIcon },
    ].filter(item => props.navigationVisibility[item.key] !== false && tenantFeatures.value[item.key]?.visible !== false).map(item => {
        const feature = tenantFeatures.value[item.key];
        if (feature?.status === 'upgrade_required' || (item.entitlement && !tenantEntitlements.value[item.entitlement])) {
            return {
                ...item,
                href: page.props.tenant.can_manage_billing
                    ? route('business.billing.show', businessRouteParameter.value)
                    : route('business.dashboard', businessRouteParameter.value),
                badge: 'Upgrade',
            };
        }

        if (feature?.status === 'setup_required') {
            return { ...item, badge: 'Setup' };
        }

        return item;
    });
});

const primaryMobileNavigation = computed(() => navigation.value.filter(item => ['dashboard', 'calendar', 'walk-in-queue', 'clients'].includes(item.key)));
const normalizedPath = computed(() => page.url.split('?')[0]);
const isActive = item => item.badge !== 'Upgrade' && (new URL(item.href, 'http://app.local').pathname === normalizedPath.value || (item.key === 'clients' && normalizedPath.value.startsWith(new URL(item.href, 'http://app.local').pathname + '/')) || (item.key === 'settings' && normalizedPath.value.endsWith('/app/locations')));
const currentLabel = computed(() => navigation.value.find(isActive)?.label || props.title || 'Shop application');
provide('workspaceBreadcrumbs', computed(() => {
    if (!businessRouteParameter.value) return [{ label: 'Account' }];
    const current = navigation.value.find(isActive);
    return [
        { label: 'Workspace', href: route('business.dashboard', businessRouteParameter.value) },
        { label: current?.label || props.title, href: current?.href, current: current ? new URL(current.href, 'http://app.local').pathname === normalizedPath.value : true },
    ];
}));

const openNavigation = async () => {
    navigationOpen.value = true;
    await nextTick();
    drawer.value?.querySelector('a')?.focus();
};

const closeNavigation = ({ restoreFocus = false } = {}) => {
    navigationOpen.value = false;
    if (restoreFocus) nextTick(() => menuButton.value?.focus());
};

const openSetup = async () => {
    setupOpen.value = true;
    await nextTick();
    setupDrawer.value?.querySelector('a[href], button:not([disabled])')?.focus();
};
const closeSetup = ({ restoreFocus = false } = {}) => {
    setupOpen.value = false;
    if (restoreFocus) nextTick(() => setupButton.value?.focus());
};

const handleKeydown = event => {
    if (event.key === 'Escape' && setupOpen.value) closeSetup({ restoreFocus: true });
    else if (event.key === 'Escape' && navigationOpen.value) closeNavigation({ restoreFocus: true });
    const activeDrawer = setupOpen.value ? setupDrawer.value : navigationOpen.value ? drawer.value : null;
    if (event.key === 'Tab' && activeDrawer) {
        const focusable = [...(activeDrawer.querySelectorAll('a[href], button:not([disabled]), summary, [tabindex]:not([tabindex="-1"])') ?? [])];
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

let previousOverflow = '';
watch(() => navigationOpen.value || setupOpen.value, open => {
    if (open) { previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; }
    else document.body.style.overflow = previousOverflow;
});
onBeforeUnmount(() => { if (navigationOpen.value || setupOpen.value) document.body.style.overflow = previousOverflow; });
const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="cd-workspace min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
        <Head :title="title" />

        <a :inert="navigationOpen || setupOpen" href="#main-content" class="fixed left-3 top-3 z-[70] -translate-y-24 rounded-lg bg-[var(--surface-raised)] px-4 py-3 font-semibold text-[var(--text-strong)] shadow-[var(--shadow-overlay)] transition-transform focus:translate-y-0">
            Skip to main content
        </a>

        <aside v-if="isBusinessWorkspace" :inert="navigationOpen || setupOpen" class="cd-sidebar cd-navigation-panel fixed inset-y-0 left-0 z-30 hidden w-[15rem] flex-col border-r border-[var(--border-subtle)] text-[var(--text-default)] lg:flex" aria-label="Shop navigation">
            <div class="flex h-16 items-center px-4">
                <ProductMark inverse small :show-tagline="false" />
            </div>
            <p class="cd-navigation-heading">Workspace</p>
            <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-4" aria-label="Primary">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.key" :class="{ 'cd-navigation-divider': ['staff', 'settings'].includes(item.key) }">
                        <Link
                            :href="item.href"
                            preserve-scroll
                            :aria-current="isActive(item) ? 'page' : undefined"
                            :class="[
                                'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                isActive(item) ? 'bg-[var(--action-secondary-hover)] text-[var(--action-primary)]' : 'text-[var(--text-default)] hover:bg-[var(--surface-subtle)] hover:text-[var(--text-strong)]',
                            ]"
                        >
                            <component :is="item.icon" class="size-5 shrink-0" aria-hidden="true" />
                            <span>{{ item.label }}</span>
                            <span v-if="item.badge" class="ml-auto rounded-full bg-[var(--surface-subtle)] px-2 py-0.5 text-[0.65rem] font-semibold text-[var(--text-muted)]">{{ item.badge }}</span>
                        </Link>
                    </li>
                </ul>
            </nav>
            <div class="border-t border-[var(--border-subtle)] p-3">
                <Link :href="route('profile.show')" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[var(--text-default)] hover:bg-[var(--surface-subtle)] hover:text-[var(--text-strong)]">
                    <span class="grid size-8 place-items-center rounded-full bg-[var(--surface-subtle)] font-semibold" aria-hidden="true">{{ $page.props.auth.user.name?.charAt(0) }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $page.props.auth.user.name }}</span>
                    <span class="ds-sr-only">Open account profile</span>
                </Link>
            </div>
        </aside>

        <div :inert="navigationOpen || setupOpen" :class="{ 'lg:pl-[15rem]': isBusinessWorkspace }">
            <header class="cd-workspace-topbar sticky top-0 z-20 flex h-16 items-center justify-between border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <button v-if="isBusinessWorkspace" ref="menuButton" type="button" class="grid size-11 shrink-0 place-items-center rounded-lg text-[var(--text-default)] hover:bg-[var(--surface-subtle)] lg:hidden" aria-label="Open navigation" aria-controls="mobile-navigation" :aria-expanded="navigationOpen" @click="openNavigation">
                        <Bars3Icon class="size-6" aria-hidden="true" />
                    </button>
                    <ProductMark v-else :show-tagline="false" class="hidden shrink-0 sm:inline-flex" />
                    <span v-if="isBusinessWorkspace" class="cd-workspace-identity" aria-hidden="true">{{ businessLabel?.charAt(0) }}</span>
                    <p class="truncate text-sm font-semibold text-[var(--text-strong)]">{{ businessLabel || $page.props.tenant?.name || 'ClipperDesk' }}</p>
                    <span v-if="tenantSubscription && subscriptionStatusLabel" :class="['cd-status hidden shrink-0 text-xs sm:inline-flex', subscriptionStatusTone]">{{ subscriptionStatusLabel }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button v-if="setupProgress" ref="setupButton" type="button" class="group hidden min-h-11 items-center gap-2 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-3 text-sm font-semibold text-[var(--text-strong)] shadow-sm hover:border-[var(--brand-primary)] sm:inline-flex" aria-controls="business-setup-drawer" :aria-expanded="setupOpen" @click="openSetup">
                        <span :class="['grid size-7 place-items-center rounded-full text-xs font-bold', setupProgress.required_complete ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--brand-primary)] text-white']">
                            <CheckCircleIcon v-if="setupProgress.required_complete" class="size-4" aria-hidden="true" />
                            <span v-else>{{ setupProgress.completed }}/{{ setupProgress.total }}</span>
                        </span>
                        {{ setupProgress.required_complete ? 'Setup' : 'Finish setup' }}
                    </button>
                    <Link v-if="!isBusinessWorkspace && primaryWorkspace" :href="route('business.dashboard', primaryWorkspace.public_id)" class="hidden min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)] 2xl:flex">
                        Open {{ primaryWorkspace.name }}
                    </Link>
                    <Link v-if="!isBusinessWorkspace && primaryWorkspace?.can_manage_billing" :href="route('business.billing.show', primaryWorkspace.public_id)" class="hidden min-h-11 items-center rounded-lg bg-[var(--brand-primary)] px-3 text-sm font-semibold text-white hover:opacity-90 2xl:flex">
                        Subscription & billing
                    </Link>
                    <details class="cd-action-menu relative">
                        <summary class="cd-account-trigger flex min-h-11 cursor-pointer list-none items-center rounded-lg px-3 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]" aria-label="Account menu">
                            <span class="cd-account-avatar" aria-hidden="true">{{ $page.props.auth.user.name?.charAt(0) }}</span>
                            <span class="hidden sm:inline">Account</span>
                            <ChevronDownIcon aria-hidden="true" />
                        </summary>
                        <div class="cd-account-popover absolute right-0 mt-2">
                            <div class="cd-account-person">
                                <span class="cd-account-avatar" aria-hidden="true">{{ $page.props.auth.user.name?.charAt(0) }}</span>
                                <div><p class="cd-account-name">{{ $page.props.auth.user.name }}</p><p v-if="$page.props.auth.user.email" class="cd-account-email">{{ $page.props.auth.user.email }}</p></div>
                            </div>
                            <Link :href="route('profile.show')" class="cd-account-item"><UserCircleIcon aria-hidden="true" /><span>Profile & security</span></Link>
                            <Link v-if="billingWorkspaces.length === 1" :href="route('business.billing.show', billingWorkspaces[0].public_id)" class="cd-account-item"><CreditCardIcon aria-hidden="true" /><span>Subscription & billing</span></Link>
                            <template v-else-if="billingWorkspaces.length > 1">
                                <p class="cd-account-group-label">Business billing</p>
                                <Link v-for="workspace in billingWorkspaces" :key="workspace.public_id" :href="route('business.billing.show', workspace.public_id)" class="cd-account-item">
                                    <CreditCardIcon aria-hidden="true" /><span>{{ workspace.name }}</span>
                                </Link>
                            </template>
                            <div class="cd-account-signout"><button type="button" class="cd-account-item" @click="logout"><ArrowRightOnRectangleIcon aria-hidden="true" /><span>Sign out</span></button></div>
                        </div>
                    </details>
                </div>
            </header>

            <div v-for="session in $page.props.supportAccessBanner" :key="`${session.operator}-${session.expires_at}`" class="border-b border-[var(--status-warning)]/30 bg-[var(--status-warning-soft)] px-4 py-3 text-sm text-[var(--status-warning)] sm:px-6 lg:px-8" role="status">
                <strong>{{ session.operator }} from ClipperDesk Support is viewing this account.</strong>
                Ticket {{ session.ticket_reference }} · {{ session.reason }} · access expires {{ new Date(session.expires_at).toLocaleString() }}.
            </div>
            <div v-for="notice in $page.props.platformNotices" :key="notice.public_id" class="border-b border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-4 py-3 text-sm sm:px-6 lg:px-8" role="status">
                <strong>{{ notice.title }}</strong> {{ notice.message }}
            </div>
            <div v-if="accessNotice" class="border-b border-[var(--status-warning)]/30 bg-[var(--status-warning-soft)] px-4 py-3 text-sm text-[var(--status-warning)] sm:px-6 lg:px-8" role="status">
                <strong>Subscription notice.</strong> {{ accessNotice }}
                <Link v-if="$page.props.tenant?.can_manage_billing" :href="route('business.billing.show', businessRouteParameter)" class="ml-2 font-semibold underline underline-offset-2">Review billing</Link>
            </div>

            <main id="main-content" tabindex="-1" class="cd-workspace-main mx-auto w-full max-w-[96rem] px-4 py-5 pb-28 sm:px-6 lg:pb-8">
                <div v-if="$page.props.flash?.status" role="status" class="mb-5 rounded-xl border border-[var(--status-success)]/30 bg-[var(--status-success-soft)] p-4 text-sm">
                    <p class="font-semibold text-[var(--text-strong)]">{{ $page.props.flash.status }}</p>
                    <a v-if="$page.props.flash.secure_url" :href="$page.props.flash.secure_url" class="mt-2 inline-flex min-h-11 items-center font-semibold text-[var(--action-primary)]">Open secure link</a>
                </div>
                <slot />
            </main>
        </div>

        <div v-if="isBusinessWorkspace && navigationOpen" class="fixed inset-0 z-50 lg:hidden">
            <button type="button" class="absolute inset-0 bg-black/50" aria-label="Close navigation" @click="closeNavigation({ restoreFocus: true })" />
            <aside id="mobile-navigation" ref="drawer" role="dialog" aria-modal="true" aria-label="Shop navigation" class="cd-navigation-panel absolute inset-y-0 left-0 flex w-[min(21rem,88vw)] flex-col shadow-[var(--shadow-overlay)]">
                <div class="flex h-16 items-center justify-between border-b border-[var(--border-subtle)] px-4">
                    <ProductMark inverse small :show-tagline="false" />
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
                            <Link :href="item.href" preserve-scroll :aria-current="isActive(item) ? 'page' : undefined" :class="['flex min-h-12 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium', isActive(item) ? 'bg-[var(--action-secondary-hover)] text-[var(--action-primary)]' : 'hover:bg-[var(--surface-subtle)]']" @click="closeNavigation()">
                                <component :is="item.icon" class="size-5" aria-hidden="true" />
                                <span>{{ item.label }}</span>
                                <span v-if="item.badge" class="ml-auto rounded-full bg-[var(--surface-subtle)] px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ item.badge }}</span>
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>
        </div>

        <div v-if="isBusinessWorkspace && setupOpen" class="fixed inset-0 z-[60]">
            <button type="button" class="absolute inset-0 bg-[var(--surface-inverse)]/45 backdrop-blur-[2px]" aria-label="Close business setup" @click="closeSetup({ restoreFocus: true })" />
            <aside id="business-setup-drawer" ref="setupDrawer" role="dialog" aria-modal="true" aria-labelledby="business-setup-title" class="absolute inset-y-0 right-0 flex w-[min(29rem,94vw)] flex-col overflow-hidden bg-[var(--surface-raised)] shadow-[var(--shadow-overlay)]">
                <div class="relative overflow-hidden border-b border-[var(--border-subtle)] bg-[var(--surface-inverse)] px-5 pb-6 pt-5 text-white">
                    <div class="absolute -right-10 -top-16 size-44 rounded-full bg-[var(--brand-secondary)]/30 blur-2xl" aria-hidden="true" />
                    <div class="relative flex items-start justify-between gap-4">
                        <div><p class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-white/60"><SparklesIcon class="size-4" aria-hidden="true" />Booking page setup</p><h2 id="business-setup-title" class="mt-2 text-2xl font-semibold">Business setup</h2></div>
                        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full bg-white/10 hover:bg-white/20" aria-label="Close business setup" @click="closeSetup({ restoreFocus: true })"><XMarkIcon class="size-5" aria-hidden="true" /></button>
                    </div>
                    <div class="relative mt-5 h-2 overflow-hidden rounded-full bg-white/15"><div class="h-full rounded-full bg-[var(--brand-accent)] transition-[width]" :style="{ width: `${setupProgress.percent}%` }" /></div>
                    <p class="relative mt-2 text-xs text-white/60">{{ setupProgress.completed }} of {{ setupProgress.total }} essentials ready</p>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <section aria-labelledby="essentials-title"><div class="flex items-center justify-between"><h3 id="essentials-title" class="font-semibold text-[var(--text-strong)]">Essentials</h3><span class="text-xs font-semibold text-[var(--text-muted)]">Required to take bookings</span></div><ul class="mt-3 space-y-2"><li v-for="item in setupProgress.required" :key="item.id"><Link :href="item.href" class="group flex min-h-16 items-center gap-3 rounded-xl border border-[var(--border-subtle)] p-3 hover:border-[var(--brand-primary)] hover:bg-[var(--surface-subtle)]" @click="closeSetup()"><span :class="['grid size-9 shrink-0 place-items-center rounded-full', item.complete ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]']"><CheckCircleIcon v-if="item.complete" class="size-5" aria-hidden="true" /><span v-else class="size-2 rounded-full bg-current" /></span><span class="min-w-0 flex-1"><strong class="block text-sm text-[var(--text-strong)]">{{ item.label }}</strong><span class="mt-0.5 block text-xs leading-5 text-[var(--text-muted)]">{{ item.description }}</span></span><ChevronRightIcon class="size-4 text-[var(--text-muted)] group-hover:text-[var(--brand-primary)]" aria-hidden="true" /></Link></li></ul></section>
                    <section class="mt-7" aria-labelledby="recommended-title"><h3 id="recommended-title" class="font-semibold text-[var(--text-strong)]">Optional improvements</h3><ul class="mt-3 space-y-2"><li v-for="item in setupProgress.recommended" :key="item.id"><Link :href="item.href" class="group flex min-h-14 items-center gap-3 rounded-xl px-3 hover:bg-[var(--surface-subtle)]" @click="closeSetup()"><CheckCircleIcon :class="['size-5 shrink-0', item.complete ? 'text-[var(--status-success)]' : 'text-[var(--text-muted)]']" aria-hidden="true" /><span class="min-w-0 flex-1"><strong class="block text-sm text-[var(--text-strong)]">{{ item.label }}</strong><span class="block text-xs text-[var(--text-muted)]">{{ item.description }}</span></span><ChevronRightIcon class="size-4 text-[var(--text-muted)]" aria-hidden="true" /></Link></li></ul></section>
                </div>
                <div v-if="setupProgress.next" class="border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-4"><Link :href="setupProgress.next.href" class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[var(--brand-primary)] px-4 text-sm font-bold text-white hover:opacity-90" @click="closeSetup()">Continue with {{ setupProgress.next.label }}<ChevronRightIcon class="size-4" aria-hidden="true" /></Link></div>
            </aside>
        </div>

        <nav v-if="isBusinessWorkspace" :inert="navigationOpen || setupOpen" class="fixed inset-x-0 bottom-0 z-20 border-t border-[var(--border-subtle)] bg-[var(--surface-raised)] px-1 pb-[max(0.25rem,env(safe-area-inset-bottom))] lg:hidden" aria-label="Quick navigation">
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
