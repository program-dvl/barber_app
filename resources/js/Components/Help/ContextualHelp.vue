<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { QuestionMarkCircleIcon, ArrowUpRightIcon } from '@heroicons/vue/24/outline';
const page = usePage();
</script>
<template>
    <details class="cd-action-menu cd-help-menu relative">
        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1.5 rounded-lg px-2 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]" aria-label="Help for this page"><QuestionMarkCircleIcon class="size-5" aria-hidden="true" /><span class="hidden sm:inline">Help</span></summary>
        <div class="cd-help-popover absolute right-0 mt-2 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-3 shadow-[var(--shadow-overlay)]">
            <p class="px-3 py-2 text-xs font-semibold text-[var(--text-muted)]">Help with {{ page.props.help?.category?.title || 'your workspace' }}</p>
            <Link v-for="article in page.props.help?.articles || []" :key="article.slug" :href="article.url || `/help/articles/${article.slug}`" class="cd-help-link">{{ article.title }}</Link>
            <Link v-if="page.props.help?.category" :href="`/help/category/${page.props.help.category.slug}`" class="cd-help-link font-semibold">All {{ page.props.help.category.title.toLowerCase() }} guides <ArrowUpRightIcon class="size-4 shrink-0" aria-hidden="true" /></Link>
            <Link href="/help" class="cd-help-link mt-2 border-t border-[var(--border-subtle)] font-semibold">Search the Help Center <ArrowUpRightIcon class="size-4 shrink-0" aria-hidden="true" /></Link>
        </div>
    </details>
</template>
<style scoped>
.cd-help-popover { width:min(360px,calc(100vw - 32px)); z-index:35; }
.cd-help-link { display:flex; gap:10px; align-items:center; justify-content:space-between; min-height:44px; padding:10px 12px; font-size:13px; line-height:1.6; color:var(--text-default); border-radius:8px; }
.cd-help-link:hover { background:var(--surface-subtle); color:var(--action-primary); }
@media(max-width:479px) { .cd-help-popover { position:fixed; top:60px; right:16px; max-height:75vh; overflow:auto; } }
</style>
