<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
defineProps({ article: Object });
</script>
<template>
    <Link :href="article.url || `/help/articles/${article.slug}`" class="help-article-card">
        <DocumentTextIcon class="size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" />
        <span class="min-w-0 flex-1"><strong>{{ article.title }}</strong><span>{{ article.summary }}</span></span>
        <ArrowRightIcon class="size-4 shrink-0" aria-hidden="true" />
    </Link>
</template>
