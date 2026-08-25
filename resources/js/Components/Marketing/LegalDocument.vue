<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowDownTrayIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    document: {
        type: Object,
        required: true,
    },
    content: {
        type: String,
        required: true,
    },
});

const printDocument = () => window.print();
</script>

<template>
    <HomeLayout>
        <Head :title="document.page_title" />

        <div class="cd-legal-page">
            <section class="cd-public-section border-b border-[var(--border-subtle)] bg-[var(--surface-subtle)]">
                <PublicContainer>
                    <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: document.title }]" />
                    <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-end">
                        <div>
                            <p class="cd-eyebrow">Trust and legal</p>
                            <h1 class="mt-5 max-w-4xl font-display text-[clamp(2.75rem,6vw,4.75rem)] font-semibold leading-[1] tracking-[-0.045em] text-[var(--text-strong)] text-balance">{{ document.title }}</h1>
                            <p class="mt-6 max-w-3xl text-lg leading-8 text-[var(--text-muted)]">{{ document.summary }}</p>
                        </div>
                        <button type="button" class="cd-button cd-button-secondary print:hidden" @click="printDocument">
                            <ArrowDownTrayIcon class="size-5" aria-hidden="true" />
                            Print or save as PDF
                        </button>
                    </div>
                </PublicContainer>
            </section>

            <section class="cd-public-section">
                <PublicContainer>
                    <div class="cd-legal-review-banner rounded-[var(--radius-lg)] border border-[var(--status-warning)]/30 bg-[var(--brand-accent-soft)]/16 p-5 sm:p-6" role="status">
                        <div class="flex items-start gap-4">
                            <ExclamationTriangleIcon class="mt-0.5 size-6 shrink-0 text-[var(--status-warning)]" aria-hidden="true" />
                            <div>
                                <p class="font-extrabold text-[var(--text-strong)]">{{ document.status }}</p>
                                <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--text-muted)]">This document is a substantive operating draft for named-owner review. It does not become a binding promise, privacy notice, or payment policy until the missing operator, contact, jurisdiction and approval details are completed and the draft status is removed.</p>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-8 grid overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] sm:grid-cols-2 xl:grid-cols-4">
                        <div class="border-b border-[var(--border-subtle)] p-5 sm:border-r xl:border-b-0"><dt class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">Version</dt><dd class="mt-2 font-semibold text-[var(--text-strong)]">{{ document.version }}</dd></div>
                        <div class="border-b border-[var(--border-subtle)] p-5 xl:border-b-0 xl:border-r"><dt class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">Last reviewed</dt><dd class="mt-2 font-semibold text-[var(--text-strong)]">{{ document.reviewed_at }}</dd></div>
                        <div class="border-b border-[var(--border-subtle)] p-5 sm:border-b-0 sm:border-r"><dt class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">Effective date</dt><dd class="mt-2 font-semibold text-[var(--text-strong)]">{{ document.effective_at }}</dd></div>
                        <div class="p-5"><dt class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">Approval owner</dt><dd class="mt-2 font-semibold leading-6 text-[var(--text-strong)]">{{ document.owner }}</dd></div>
                    </dl>

                    <div class="mt-12 grid gap-12 lg:grid-cols-[15rem_minmax(0,48rem)] lg:justify-center xl:gap-16">
                        <aside class="cd-legal-sidebar print:hidden">
                            <nav class="lg:sticky lg:top-28" aria-label="On this page">
                                <h2 class="text-xs font-extrabold uppercase tracking-[0.14em] text-[var(--text-muted)]">On this page</h2>
                                <ol class="mt-4 space-y-1 border-l border-[var(--border-strong)]">
                                    <li v-for="section in document.sections" :key="section.id">
                                        <a :href="`#${section.id}`" class="block min-h-11 py-2.5 pl-4 text-sm font-semibold leading-6 text-[var(--text-muted)] underline-offset-4 hover:text-[var(--text-strong)] hover:underline focus-visible:text-[var(--text-strong)]">{{ section.label }}</a>
                                    </li>
                                </ol>
                            </nav>
                        </aside>

                        <article class="cd-legal-copy min-w-0 prose max-w-none prose-headings:font-display prose-headings:text-[var(--text-strong)] prose-p:leading-8 prose-li:leading-7" v-html="content" />
                    </div>

                    <section class="cd-legal-related mx-auto mt-14 max-w-4xl border-t border-[var(--border-subtle)] pt-8 print:hidden" aria-labelledby="related-policies-heading">
                        <h2 id="related-policies-heading" class="font-display text-2xl font-semibold text-[var(--text-strong)]">Related policies and trust information</h2>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <Link v-for="item in document.related" :key="item.href" :href="item.href" class="cd-button cd-button-secondary">{{ item.label }}</Link>
                        </div>
                    </section>
                </PublicContainer>
            </section>
        </div>
    </HomeLayout>
</template>

<style scoped>
.cd-legal-copy :deep(span[id]) {
    display: block;
    scroll-margin-top: 7rem;
}

.cd-legal-copy :deep(h2) {
    margin-top: 3.25rem;
    padding-top: 0.25rem;
    font-size: clamp(1.75rem, 4vw, 2.25rem);
    line-height: 1.16;
    letter-spacing: -0.025em;
}

.cd-legal-copy :deep(h3) {
    margin-top: 2rem;
    font-size: 1.25rem;
    line-height: 1.4;
}

.cd-legal-copy :deep(a) {
    color: var(--action-primary);
    font-weight: 700;
    overflow-wrap: anywhere;
}

.cd-legal-copy :deep(table) {
    display: block;
    max-width: 100%;
    overflow-x: auto;
    border-collapse: collapse;
    font-size: 0.925rem;
}

.cd-legal-copy :deep(th),
.cd-legal-copy :deep(td) {
    min-width: 10rem;
    border: 1px solid var(--border-subtle);
    padding: 0.8rem;
    text-align: left;
    vertical-align: top;
}

.cd-legal-copy :deep(blockquote) {
    border-left-color: var(--status-warning);
    background: var(--surface-subtle);
    padding: 1rem 1.25rem;
    color: var(--text-muted);
}
</style>
