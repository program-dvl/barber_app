<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ controls: Array });
</script>

<template>
    <HomeLayout>
        <Head title="Good Hours security approach and current limits" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Security' }]" />
                <p class="gh-eyebrow mt-10">Security and trust</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">Implemented safeguards, with the unfinished assurance work in view.</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">Good Hours applies tenant, authorization, audit and transaction boundaries in product code. That does not equal a certification or a guarantee, and production operations still have critical gates.</p>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <div class="grid gap-5 lg:grid-cols-2">
                    <article v-for="control in controls" :key="control.title" class="gh-marketing-card">
                        <div class="flex items-start gap-3"><CheckCircleIcon v-if="control.state.startsWith('Implemented')" class="mt-1 size-6 shrink-0 text-[var(--action-primary)]" aria-hidden="true" /><ExclamationTriangleIcon v-else class="mt-1 size-6 shrink-0 text-[var(--brand-rust)]" aria-hidden="true" /><div><h2 class="text-xl font-extrabold text-[var(--text-strong)]">{{ control.title }}</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">{{ control.body }}</p><p class="mt-4 text-sm font-extrabold text-[var(--text-strong)]">Status: {{ control.state }}</p></div></div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <section class="gh-public-section bg-[var(--brand-pine)] text-white">
            <PublicContainer class="grid gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                <div><h2 class="font-display text-4xl">Customer responsibilities</h2><p class="mt-5 max-w-2xl leading-8 text-white/82">Business Owners must configure accurate staff/resource rules, permissions, legal policies, provider credentials and device/account access. They should collect only needed client data and route suspected incidents through an approved support process.</p></div>
                <div class="rounded-[var(--radius-xl)] border border-white/20 p-7"><h2 class="text-xl font-extrabold">Reporting and support status</h2><p class="mt-4 leading-7 text-white/78">No public security-report mailbox, contact form, support SLA or maintained status service has an approved owner yet. Publishing a fake intake or all-green status page would be less safe. Signed-in owners retain in-product billing recovery; public security/support intake is a launch action.</p></div>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer class="flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">Evaluate the product with the limits visible.</h2><p class="mt-3 text-[var(--text-muted)]">Review features and pricing before starting a verified trial.</p></div><div class="flex flex-wrap gap-3"><Link :href="route('marketing.features')" class="gh-button gh-button-secondary">Review product</Link><PublicCta context="security_final" /></div></PublicContainer>
        </section>
    </HomeLayout>
</template>
