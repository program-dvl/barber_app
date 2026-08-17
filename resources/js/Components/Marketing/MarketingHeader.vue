<script setup>
import ProductMark from '@/Components/Product/ProductMark.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage();
const menuOpen = ref(false);
const menuButton = ref(null);
const menuPanel = ref(null);

const navigation = computed(() => {
    const available = page.props.ziggy?.routes ?? {};
    const candidates = [
        { label: 'Product', route: 'marketing.features', fallback: 'marketing.home', prefix: '/features' },
        { label: 'Solutions', route: 'marketing.solutions', prefix: '/solutions' },
        { label: 'Use cases', route: 'marketing.use-cases', prefix: '/use-cases' },
        { label: 'Pricing', route: 'marketing.pricing', prefix: '/pricing' },
        { label: 'Resources', route: 'marketing.resources', prefix: '/resources' },
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
watch(menuOpen, (open) => document.body.classList.toggle('gh-menu-open', open));

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('pointerdown', onPointerDown);
});

onBeforeUnmount(() => {
    document.body.classList.remove('gh-menu-open');
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('pointerdown', onPointerDown);
});
</script>

<template>
    <header class="gh-marketing-header sticky top-0 z-40 border-b backdrop-blur">
        <div class="gh-public-container flex min-h-18 items-center justify-between gap-4 py-3">
            <Link :href="route('marketing.home')" class="inline-flex min-h-11 items-center rounded-lg" aria-label="Good Hours home">
                <ProductMark />
            </Link>

            <nav aria-label="Primary" class="hidden items-center gap-1 lg:flex">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="route(item.route)"
                    class="gh-nav-link"
                    :aria-current="isCurrent(item) ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="hidden items-center gap-2 lg:flex">
                <Link
                    v-if="!page.props.auth?.user"
                    :href="route('login')"
                    class="gh-button gh-button-quiet"
                >
                    Log in
                </Link>
                <PublicCta context="header" compact />
            </div>

            <button
                ref="menuButton"
                type="button"
                class="gh-icon-button lg:hidden"
                :aria-expanded="menuOpen"
                aria-controls="marketing-mobile-menu"
                :aria-label="menuOpen ? 'Close navigation' : 'Open navigation'"
                @click="menuOpen = !menuOpen"
            >
                <XMarkIcon v-if="menuOpen" class="size-6" aria-hidden="true" />
                <Bars3Icon v-else class="size-6" aria-hidden="true" />
            </button>
        </div>

        <div
            v-if="menuOpen"
            id="marketing-mobile-menu"
            ref="menuPanel"
            class="max-h-[calc(100dvh-4.5rem)] overflow-y-auto border-t border-[var(--border-subtle)] bg-[var(--surface-raised)] lg:hidden"
        >
            <nav aria-label="Mobile primary" class="gh-public-container flex flex-col gap-1 py-5">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="route(item.route)"
                    class="gh-mobile-nav-link"
                    :aria-current="isCurrent(item) ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
                <div class="mt-4 grid gap-2 border-t border-[var(--border-subtle)] pt-4">
                    <Link v-if="!page.props.auth?.user" :href="route('login')" class="gh-button gh-button-secondary">Log in</Link>
                    <PublicCta context="mobile_navigation" />
                </div>
            </nav>
        </div>
    </header>
</template>
