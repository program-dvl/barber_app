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
            ['About Good Hours', 'marketing.company'],
            ['Privacy policy', 'policy.show'],
            ['Terms of service', 'terms.show'],
        ],
    },
].map((group) => ({
    ...group,
    links: group.links.filter(([, routeName]) => hasRoute(routeName)),
})).filter((group) => group.links.length));
</script>

<template>
    <footer class="border-t border-[var(--border-subtle)] bg-[var(--brand-pine-deep)] text-white">
        <div class="gh-public-container py-14 sm:py-18">
            <div class="grid gap-12 lg:grid-cols-[1.35fr_2fr]">
                <div>
                    <Link :href="route('marketing.home')" class="inline-flex min-h-11 items-center rounded-lg bg-white px-3 text-[var(--brand-pine)]" aria-label="Good Hours home">
                        <ProductMark />
                    </Link>
                    <p class="mt-6 max-w-md text-sm leading-7 text-white/78">
                        Good Hours is a calm operating system for salons and barbershops—from booking to checkout.
                    </p>
                    <p class="mt-3 font-display text-xl text-[var(--brand-apricot)]">Make every hour count.</p>
                </div>

                <div class="grid gap-9 sm:grid-cols-3">
                    <nav v-for="group in groups" :key="group.label" :aria-label="`${group.label} links`">
                        <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-white/60">{{ group.label }}</h2>
                        <ul class="mt-4 space-y-2">
                            <li v-for="[label, routeName] in group.links" :key="routeName">
                                <Link :href="route(routeName)" class="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-white/86 underline-offset-4 hover:text-white hover:underline">
                                    {{ label }}
                                </Link>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <div class="mt-12 flex flex-col gap-3 border-t border-white/15 pt-6 text-xs leading-6 text-white/60 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ new Date().getFullYear() }} Good Hours. Product identity remains subject to OPEN-11 clearance.</p>
                <p>Built for clear, accessible work on the web.</p>
            </div>
        </div>
    </footer>
</template>
