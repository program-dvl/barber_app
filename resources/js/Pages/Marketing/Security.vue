<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { marketingFamilyVisuals } from '@/Support/marketingVisuals';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ controls: Array, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="cd-public-section cd-family-hero" data-tone="cyan">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Security' }]" />
                <div class="cd-family-hero-grid mt-10">
                    <div class="cd-family-hero-copy">
                        <p class="cd-eyebrow">Security and trust</p>
                        <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-balance">Implemented safeguards, with the unfinished assurance work in view.</h1>
                        <p class="mt-7 max-w-3xl text-lg leading-8 sm:text-xl">ClipperDesk applies tenant, authorization, audit and transaction boundaries in product code. That does not equal a certification or a guarantee, and production operations still have critical gates.</p>
                    </div>
                    <figure class="cd-family-hero-media">
                        <img class="cd-family-hero-image" :src="marketingFamilyVisuals.security.src" :alt="marketingFamilyVisuals.security.alt" width="1536" height="1024" fetchpriority="high" />
                        <figcaption class="cd-family-hero-caption">Trust comes from careful stewardship and clear boundaries.</figcaption>
                    </figure>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <div class="mb-11 max-w-3xl"><p class="cd-eyebrow">Current controls</p><h2 class="cd-section-title">Specific safeguards, described without theatre.</h2></div>
                <div class="cd-trust-grid">
                    <article v-for="control in controls" :key="control.title" class="cd-trust-card">
                        <span class="grid size-11 place-items-center rounded-full bg-indigo-50"><CheckCircleIcon v-if="control.state.startsWith('Implemented')" class="size-6 text-[var(--action-primary)]" aria-hidden="true" /><ExclamationTriangleIcon v-else class="size-6 text-[var(--status-warning)]" aria-hidden="true" /></span>
                        <div><h2 class="text-xl font-extrabold text-[var(--text-strong)]">{{ control.title }}</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">{{ control.body }}</p><span class="cd-status-pill mt-5" :data-state="control.state.startsWith('Implemented') ? 'ready' : 'caution'">{{ control.state }}</span></div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section cd-dark-proof">
            <PublicContainer class="grid gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                <div><h2 class="font-display text-4xl">Customer responsibilities</h2><p class="mt-5 max-w-2xl leading-8 text-white/82">Business Owners must configure accurate staff/resource rules, permissions, legal policies, provider credentials and device/account access. They should collect only needed client data and route suspected incidents through an approved support process.</p></div>
                <div class="cd-dark-proof-panel"><h2 class="text-xl font-extrabold">Reporting and support status</h2><p class="mt-4 leading-7 text-white/78">No public security-report mailbox, contact form, support SLA or maintained status service has an approved owner yet. Publishing a fake intake or all-green status page would be less safe. Signed-in owners retain in-product billing recovery; public security/support intake is a launch action.</p></div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer class="flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">Evaluate the product with the limits visible.</h2><p class="mt-3 text-[var(--text-muted)]">Review features and pricing before starting a verified trial.</p></div><div class="flex flex-wrap gap-3"><Link :href="route('marketing.features')" class="cd-button cd-button-secondary">Review product</Link><PublicCta context="security_final" /></div></PublicContainer>
        </section>
    </HomeLayout>
</template>
