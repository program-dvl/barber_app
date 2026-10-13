<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { marketingFamilyVisuals } from '@/Support/marketingVisuals';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ controls: Array, seo: Object, legalIdentity: Object });
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
                        <h1 class="mt-5 max-w-4xl font-display cd-public-title font-semibold leading-[0.98] tracking-[-0.055em] text-balance">How we protect your business data.</h1>
                        <p class="mt-7 max-w-3xl text-lg leading-8 sm:text-xl">Control who can access your workspace, keep client attachments private and review important changes to your records.</p>
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
                <div class="mb-11 max-w-3xl"><p class="cd-eyebrow">Data and access</p><h2 class="cd-public-section-title">Safeguards for your daily work.</h2></div>
                <div class="cd-trust-grid">
                    <article v-for="control in controls" :key="control.title" class="cd-trust-card">
                        <span class="grid size-11 place-items-center rounded-full bg-indigo-50"><CheckCircleIcon class="size-6 text-[var(--action-primary)]" aria-hidden="true" /></span>
                        <div><h2 class="text-xl font-extrabold text-[var(--text-strong)]">{{ control.title }}</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">{{ control.body }}</p></div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section cd-dark-proof">
            <PublicContainer class="grid gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                <div><h2 class="font-display text-3xl">Keep access appropriate</h2><p class="mt-5 max-w-2xl leading-8 text-white/82">Use a separate account for each team member, review permissions and remove access when someone leaves. Collect only the client information your business needs. ClipperDesk does not claim an independent security certification or healthcare compliance.</p></div>
                <div class="cd-dark-proof-panel"><h2 class="text-xl font-extrabold">Reporting and support</h2><p class="mt-4 leading-7 text-white/78">Contact <a :href="`mailto:${legalIdentity.privacy_email}`" class="underline underline-offset-4">{{ legalIdentity.privacy_email }}</a> with a security or privacy concern. Include an account or business reference and a brief description. Do not send passwords or sensitive client records.</p></div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer class="flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">See how ClipperDesk fits your business.</h2><p class="mt-3 text-[var(--text-muted)]">Explore the features and pricing before starting your trial.</p></div><div class="flex flex-wrap gap-3"><Link :href="route('marketing.features')" class="cd-button cd-button-secondary">Explore features</Link><PublicCta context="security_final" /></div></PublicContainer>
        </section>
    </HomeLayout>
</template>
