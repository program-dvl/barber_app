<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const breadcrumbs = inject('workspaceBreadcrumbs', null);
defineProps({
    eyebrow: String,
    title: {
        type: String,
        required: true,
    },
    description: String,
});
</script>

<template>
    <header class="cd-page-header flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0 flex-[1_1_12rem]">
            <nav v-if="breadcrumbs?.length" class="cd-breadcrumbs" aria-label="Breadcrumb">
                <ol>
                    <li v-for="(item, index) in breadcrumbs" :key="`${item.label}-${index}`">
                        <span v-if="index" class="cd-breadcrumb-separator" aria-hidden="true">/</span>
                        <Link v-if="item.href && !item.current" :href="item.href">{{ item.label }}</Link>
                        <span v-else :aria-current="item.current ? 'page' : undefined">{{ item.label }}</span>
                    </li>
                </ol>
            </nav>
            <p v-if="eyebrow" class="cd-page-eyebrow mb-1 text-xs font-medium text-[var(--text-muted)]">
                {{ eyebrow }}
            </p>
            <h1 class="cd-page-title">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-1.5 max-w-3xl text-sm leading-relaxed text-[var(--text-muted)]">
                {{ description }}
            </p>
            <div v-if="$slots.context" class="cd-page-context"><slot name="context" /></div>
        </div>
        <div v-if="$slots.actions" class="flex max-w-full shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
