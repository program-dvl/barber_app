<script setup>
import ProductMark from '@/Components/Product/ProductMark.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRightIcon, Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage();
const menuOpen = ref(false);
const menuButton = ref(null);
const menuPanel = ref(null);

const navigation = computed(() => {
    const available = page.props.ziggy?.routes ?? {};
    const candidates = [
        { label: 'Product', route: 'marketing.features', fallback: 'marketing.home', prefix: '/features' },
        { label: 'Industries', route: 'marketing.solutions', prefix: '/solutions' },
        { label: 'Use cases', route: 'marketing.use-cases', prefix: '/use-cases' },
        { label: 'Pricing', route: 'marketing.pricing', prefix: '/pricing' },
        { label: 'Resources', route: 'marketing.resources', prefix: '/resources' },
        { label: 'Find a salon', route: 'marketplace.discover', prefix: '/marketplace' },
    ];

    return candidates
        .map((item) => {
            const routeName = available[item.route] ? item.route : item.fallback;
            return routeName && available[routeName] ? { ...item, route: routeName } : null;
        })
        .filter(Boolean)
        .filter((item, index, items) => items.findIndex((candidate) => candidate.route === item.route) === index);
});

const isCurrent = (item) => {
    const path = page.url.split('?')[0];
    return item.route === 'marketing.home' ? path === '/' : path === item.prefix || path.startsWith(`${item.prefix}/`);
};

const closeMenu = async ({ restoreFocus = false } = {}) => {
    if (!menuOpen.value) return;
    menuOpen.value = false;
    await nextTick();
    if (restoreFocus) menuButton.value?.focus();
};

const onKeydown = (event) => {
    if (event.key === 'Escape') closeMenu({ restoreFocus: true });
};

const onPointerDown = (event) => {
    if (!menuOpen.value || menuPanel.value?.contains(event.target) || menuButton.value?.contains(event.target)) return;
    closeMenu();
};

watch(() => page.url, () => closeMenu());
watch(menuOpen, (open) => document.body.classList.toggle('cd-menu-open', open));

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('pointerdown', onPointerDown);
});

onBeforeUnmount(() => {
    document.body.classList.remove('cd-menu-open');
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('pointerdown', onPointerDown);
});
</script>

<template>
    <header class="cd-marketing-header sticky top-0 z-40 backdrop-blur">
        <div class="cd-public-container py-2.5">
            <div class="cd-header-shell flex min-h-16 items-center justify-between gap-4 px-3 sm:px-4">
            <Link :href="route('marketing.home')" class="cd-business-brand" :aria-label="`${page.props.brand?.product_name || 'ClipperDesk'} Business home`">
                <ProductMark compact /><span class="cd-business-brand-copy"><strong>{{page.props.brand?.product_name || 'ClipperDesk'}}</strong><small>Business</small></span>
            </Link>

            <nav aria-label="Primary" class="cd-header-nav hidden items-center gap-0.5 xl:flex">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="route(item.route)"
                    class="cd-nav-link" :class="{'cd-marketplace-entry':item.route==='marketplace.discover'}"
                    :aria-current="isCurrent(item) ? 'page' : undefined"
                >
                    {{ item.label }}<ArrowUpRightIcon v-if="item.route==='marketplace.discover'" class="ml-1 inline-block size-3.5" aria-hidden="true"/>
                </Link>
            </nav>

            <div class="hidden items-center gap-2 xl:flex">
                <Link
                    v-if="!page.props.auth?.user"
                    :href="route('login')"
                    class="cd-button cd-button-quiet"
                >
                    Log in
                </Link>
                <PublicCta context="header" compact />
            </div>

            <button
                ref="menuButton"
                type="button"
                class="cd-icon-button xl:hidden"
                :aria-expanded="menuOpen"
                aria-controls="marketing-mobile-menu"
                :aria-label="menuOpen ? 'Close navigation' : 'Open navigation'"
                @click="menuOpen = !menuOpen"
            >
                <XMarkIcon v-if="menuOpen" class="size-6" aria-hidden="true" />
                <Bars3Icon v-else class="size-6" aria-hidden="true" />
            </button>
            </div>
        </div>

        <div
            v-if="menuOpen"
            id="marketing-mobile-menu"
            ref="menuPanel"
            class="cd-mobile-menu-stage max-h-[calc(100dvh-5.25rem)] overflow-y-auto xl:hidden"
        >
            <div class="cd-public-container pb-4">
                <div class="cd-mobile-menu-card">
                    <p class="cd-eyebrow px-2">Explore {{ page.props.brand?.product_name || 'ClipperDesk' }}</p>
                    <nav aria-label="Mobile primary" class="mt-3 grid gap-1 sm:grid-cols-2">
                        <Link
                            v-for="item in navigation"
                            :key="item.label"
                            :href="route(item.route)"
                            class="cd-mobile-nav-link group justify-between"
                            :aria-current="isCurrent(item) ? 'page' : undefined"
                        >
                            {{ item.label }}
                            <ArrowUpRightIcon class="size-4 text-[var(--text-muted)] transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5" aria-hidden="true" />
                        </Link>
                    </nav>
                    <div class="mt-4 flex flex-wrap gap-x-5 border-t border-[var(--border-subtle)] px-2 pt-4 text-sm font-bold">
                        <Link :href="route('marketing.company')" class="inline-flex min-h-11 items-center text-[var(--text-muted)] hover:text-[var(--brand-primary)]">Company</Link>
                        <Link :href="route('marketing.security')" class="inline-flex min-h-11 items-center text-[var(--text-muted)] hover:text-[var(--brand-primary)]">Security</Link>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <Link v-if="!page.props.auth?.user" :href="route('login')" class="cd-button cd-button-secondary">Log in</Link>
                        <PublicCta context="mobile_navigation" />
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<style scoped>
.cd-business-brand{display:inline-flex;align-items:center;gap:11px;min-height:48px;flex-shrink:0;border-radius:8px;color:var(--brand-primary);padding:5px 0}.cd-business-brand-copy{display:block;min-width:0}.cd-business-brand-copy strong{display:block;font-size:21px;letter-spacing:-.8px;font-weight:750;line-height:1.1}.cd-business-brand-copy small{display:block;font-size:9px;font-weight:650;letter-spacing:2.5px;line-height:1.2;margin-top:7px;text-transform:uppercase;color:#6a7892}@media(max-width:600px){.cd-business-brand{gap:9px}.cd-business-brand-copy strong{font-size:20px}.cd-business-brand-copy small{font-size:8px;letter-spacing:2px}.cd-business-brand :deep(img){height:35px}}
.cd-header-nav .cd-marketplace-entry{color:#405b35;background:#eff5e7}.cd-header-nav .cd-marketplace-entry:hover{background:#e5eed9}
</style>
