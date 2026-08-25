<script setup>
import ProductMark from '@/Components/Product/ProductMark.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const hasRoute = (name) => Boolean(page.props.ziggy?.routes?.[name]);

const groups = computed(() => [
    {
        label: 'Product',
        links: [
            ['Features', 'marketing.features'],
            ['Pricing', 'marketing.pricing'],
            ['Security', 'marketing.security'],
        ],
    },
    {
        label: 'Explore',
        links: [
            ['Solutions', 'marketing.solutions'],
            ['Use cases', 'marketing.use-cases'],
            ['Resources', 'marketing.resources'],
        ],
    },
    {
        label: 'Company',
        links: [
            ['About ClipperDesk', 'marketing.company'],
            ['Privacy policy', 'policy.show'],
            ['Terms of service', 'terms.show'],
            ['Refund policy', 'refund.show'],
        ],
    },
].map((group) => ({
    ...group,
    links: group.links.filter(([, routeName]) => hasRoute(routeName)),
})).filter((group) => group.links.length));
</script>

<template>
    <footer class="cd-marketing-footer border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] text-[var(--text-default)]">
        <div class="cd-public-container py-14 sm:py-18">
            <div class="grid gap-12 lg:grid-cols-[1.35fr_2fr]">
                <div>
                    <Link :href="route('marketing.home')" class="inline-flex min-h-11 items-center rounded-lg" aria-label="ClipperDesk home">
                        <ProductMark />
                    </Link>
                    <p class="mt-6 max-w-md text-sm leading-7 text-[var(--text-muted)]">
                        ClipperDesk is a calm operating system for salons and barbershops—from booking to checkout.
                    </p>
                    <p class="mt-3 font-display text-xl font-semibold text-[var(--brand-primary)]">Run the day. Grow the business.</p>
                </div>

                <div class="grid gap-9 sm:grid-cols-3">
                    <nav v-for="group in groups" :key="group.label" :aria-label="`${group.label} links`">
                        <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--text-muted)]">{{ group.label }}</h2>
                        <ul class="mt-4 space-y-2">
                            <li v-for="[label, routeName] in group.links" :key="routeName">
                                <Link :href="route(routeName)" class="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-[var(--text-default)] underline-offset-4 hover:text-[var(--brand-secondary)] hover:underline">
                                    {{ label }}
                                </Link>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <div class="mt-12 flex flex-col gap-3 border-t border-[var(--border-default)] pt-6 text-xs leading-6 text-[var(--text-muted)] sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ new Date().getFullYear() }} ClipperDesk. Product identity remains subject to OPEN-11 clearance.</p>
                <p>Built for clear, accessible work on the web.</p>
            </div>
        </div>
    </footer>
</template>
