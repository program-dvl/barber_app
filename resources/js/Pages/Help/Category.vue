<script setup>
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import HelpSearch from '@/Components/Help/HelpSearch.vue';
import HelpArticleCard from '@/Components/Help/HelpArticleCard.vue';
import { Head, Link } from '@inertiajs/vue3';
import '../../../css/help-center.css';
defineProps({ category: Object, articles: Array, seo: Object });
</script>
<template><MarketingLayout page-class="help-center"><Head :title="seo?.title || category.title" /><div class="help-container help-main"><nav class="help-breadcrumbs" aria-label="Breadcrumb"><Link href="/help">Help Center</Link><span aria-hidden="true">/</span><span aria-current="page">{{ category.title }}</span></nav><header class="help-category-header"><p class="help-eyebrow">Explore a topic</p><h1>{{ category.title }}</h1><p>{{ category.description }}</p><HelpSearch compact /></header><section class="help-result-list" :aria-label="`${category.title} guides`"><HelpArticleCard v-for="article in articles" :key="article.slug" :article="article" /></section></div></MarketingLayout></template>
