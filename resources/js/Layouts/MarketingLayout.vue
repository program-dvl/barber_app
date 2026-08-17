<script setup>
import MarketingFooter from '@/Components/Marketing/MarketingFooter.vue';
import MarketingHeader from '@/Components/Marketing/MarketingHeader.vue';
import Banner from '@/Components/Profile/Banner.vue';

defineProps({
    pageClass: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="gh-marketing min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
        <a class="gh-skip-link" href="#main-content">Skip to main content</a>
        <Banner />
        <MarketingHeader />
        <main id="main-content" :class="pageClass" tabindex="-1">
            <slot />
        </main>
        <MarketingFooter />
    </div>
</template>
