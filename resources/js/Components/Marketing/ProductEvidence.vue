<script setup>
import HelpScreenshot from '@/Components/Help/HelpScreenshot.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import { Link } from '@inertiajs/vue3';
import '../../../css/help-center.css';

defineProps({ screenshots: { type: Array, default: () => [] }, scenario: Object, faqs: { type: Array, default: () => [] }, context: String });
</script>

<template>
    <section class="cd-public-section" aria-label="See the product in practice">
        <PublicContainer>
            <div class="grid items-start gap-10" :class="{ 'lg:grid-cols-2': screenshots.length }">
                <div>
                    <p class="cd-eyebrow">Inside ClipperDesk</p>
                    <h2 class="mt-4 font-display text-3xl font-semibold text-[var(--text-strong)]">See the workflow before you choose.</h2>
                    <template v-if="scenario">
                        <h3 class="mt-7 text-xl font-bold text-[var(--text-strong)]">{{ scenario.title }}</h3>
                        <p class="mt-4 leading-8 text-[var(--text-muted)]">{{ scenario.body }}</p>
                    </template>
                    <p v-if="screenshots.length" class="mt-5 leading-7 text-[var(--text-muted)]">These are real product screens from example businesses, with sample clients and prices.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <PublicCta :context="context" />
                        <Link :href="route('marketing.product-tour')" class="cd-button cd-button-secondary">Explore the product walkthrough</Link>
                    </div>
                </div>
                <div v-if="screenshots.length" class="min-w-0">
                    <HelpScreenshot v-for="media in screenshots" :key="media.key" :media="media" />
                </div>
            </div>
            <div v-if="faqs.length" class="mx-auto mt-14 max-w-4xl">
                <h2 class="mb-7 font-display text-3xl font-semibold text-[var(--text-strong)]">Questions before you start</h2>
                <FaqList :items="faqs" />
            </div>
        </PublicContainer>
    </section>
</template>
