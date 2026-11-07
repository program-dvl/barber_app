<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { guideVisuals, marketingFamilyVisuals, visualFor } from '@/Support/marketingVisuals';
import { CheckIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ guide: Object, seo: Object });
const example = computed(() => ({
    'booking-policy-basics': { title: 'Turn your decisions into clear wording', body: 'Use this outline after agreeing your own rules. Replace every bracketed field and review the finished policy before publishing.', lines: ['Your appointment reserves [service and duration] with [professional or team].', 'To change or cancel, use [working contact or appointment link] before [your deadline].', 'If you arrive late, [explain your actual options and who confirms any changes].', 'Any deposit, fee or refund follows [your reviewed terms]. Contact [business contact] if you need help.'] },
    'salon-opening-checklist': { title: 'Test one complete booking before sharing your link', body: 'Use fictional client details. This example is a test plan, not a suggested service duration or price.', lines: ['Add one service using your actual price, duration and qualified team member.', 'Set working hours and a break; confirm that the break cannot be booked.', 'Preview the page on a phone and complete an available appointment.', 'Check the confirmation, local time, location and instructions for changing the visit.', 'Reschedule the test appointment, then confirm the calendar and public availability agree.'] },
})[props.guide.slug]);
const printGuide = () => window.print();
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <article class="cd-printable-guide">
            <header class="cd-public-section cd-family-hero" data-tone="amber">
                <PublicContainer>
                    <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Resources', href: route('marketing.resources') }, { label: guide.title }]" />
                    <div class="cd-family-hero-grid mt-10">
                        <div class="cd-family-hero-copy">
                            <p class="cd-eyebrow">{{ guide.topic }}</p>
                            <h1 class="mt-5 max-w-4xl font-display cd-public-title font-semibold leading-[1] tracking-[-0.055em] text-balance">{{ guide.title }}</h1>
                            <p class="mt-7 max-w-3xl text-lg leading-8">{{ guide.intro }}</p>
                            <div class="mt-6 flex flex-wrap gap-3"><button type="button" class="cd-button cd-button-hero-secondary" @click="printGuide">Print or save as PDF</button><Link :href="route('marketing.resources')" class="cd-button cd-button-hero-secondary">Browse free resources</Link></div>
                            <p class="mt-6 text-sm font-semibold text-white/60">Published {{ guide.published_at }} · Updated {{ guide.reviewed_at }} · {{ guide.reviewed_by }}</p>
                        </div>
                        <figure class="cd-family-hero-media">
                            <img class="cd-family-hero-image" :src="visualFor(guideVisuals, guide.slug, marketingFamilyVisuals.resources).src" :alt="visualFor(guideVisuals, guide.slug, marketingFamilyVisuals.resources).alt" width="1536" height="1024" fetchpriority="high" />
                            <figcaption class="cd-family-hero-caption">A practical field guide for the working week.</figcaption>
                        </figure>
                    </div>
                </PublicContainer>
            </header>

            <section class="cd-public-section">
                <PublicContainer class="grid gap-12 lg:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <ol class="cd-story-list">
                            <li v-for="(section, index) in guide.sections" :key="section.title" class="cd-story-row">
                                <span class="cd-story-number" aria-hidden="true">0{{ index + 1 }}</span>
                                <h2 class="font-display text-2xl font-bold leading-tight text-[var(--text-strong)]">{{ section.title }}</h2>
                                <p class="leading-8 text-[var(--text-muted)]">{{ section.body }}</p>
                            </li>
                        </ol>
                        <section v-if="example" class="mt-10 rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-6 sm:p-8" aria-labelledby="guide-example-title">
                            <h2 id="guide-example-title" class="font-display text-2xl font-bold text-[var(--text-strong)]">{{ example.title }}</h2>
                            <p class="mt-4 leading-8 text-[var(--text-muted)]">{{ example.body }}</p>
                            <ol class="mt-6 list-decimal space-y-4 pl-5 leading-8 text-[var(--text-default)]"><li v-for="line in example.lines" :key="line">{{ line }}</li></ol>
                        </section>
                    </div>
                    <aside class="lg:sticky lg:top-28 lg:self-start">
                        <div class="cd-checklist-panel">
                            <p class="cd-eyebrow">Keep beside you</p>
                            <h2 class="mt-3 text-2xl font-extrabold text-[var(--text-strong)]">Working checklist</h2>
                            <ul class="mt-6 space-y-4">
                                <li v-for="item in guide.checklist" :key="item" class="flex gap-3 leading-7 text-[var(--text-muted)]"><span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-indigo-50"><CheckIcon class="size-4 text-[var(--action-primary)]" aria-hidden="true" /></span>{{ item }}</li>
                            </ul>
                        </div>
                        <AppNotice class="mt-5" title="Before you use this guide" :description="guide.limitation" />
                        <Link :href="route('marketing.features.show', guide.feature)" class="cd-button cd-button-secondary mt-6">Explore the supporting feature</Link>
                    </aside>
                </PublicContainer>
            </section>
        </article>
    </HomeLayout>
</template>

<style scoped>
@media print {
    .cd-printable-guide > header { display: block !important; padding: 0 0 1rem; background: white; color: black; }
    .cd-printable-guide > header figure, .cd-printable-guide nav, .cd-printable-guide .cd-eyebrow { display: none !important; }
    .cd-printable-guide :deep(h1) { font-size: 26pt; color: black; }
    .cd-printable-guide :deep(p), .cd-printable-guide :deep(li) { color: black; }
    .cd-printable-guide :deep(.cd-family-hero-grid) { display: block; margin-top: 0; }
    .cd-printable-guide :deep(.cd-story-row) { break-inside: avoid; }
}
</style>
