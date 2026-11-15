<script setup>
import { Link } from '@inertiajs/vue3';
import PublicContainer from './PublicContainer.vue';
defineProps({ items: { type: Array, default: () => [] } });
</script>

<template>
    <section v-if="items.length" class="cd-public-section bg-[var(--surface-subtle)]" aria-labelledby="related-reading-title">
        <PublicContainer>
            <p class="cd-eyebrow">Put it into practice</p>
            <h2 id="related-reading-title" class="mt-4 font-display text-3xl font-semibold text-[var(--text-strong)]">Plan the workflow with your team</h2>
            <div class="cd-visual-card-grid mt-8">
                <article v-for="item in items" :key="item.href" class="cd-visual-card">
                    <div class="cd-visual-card-body min-h-0">
                        <h3 class="text-xl font-extrabold text-[var(--text-strong)]">{{ item.title }}</h3>
                        <p class="mt-4 leading-7 text-[var(--text-muted)]">{{ item.description }}</p>
                        <Link :href="item.href" class="cd-arrow-link mt-5">Read the guide<span class="sr-only">: {{ item.title }}</span></Link>
                    </div>
                </article>
            </div>
        </PublicContainer>
    </section>
</template>
