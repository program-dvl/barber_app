<script setup>
import MarketplaceLayout from '@/Layouts/MarketplaceLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppNotice from '@/Components/Product/AppNotice.vue';
import { Link } from '@inertiajs/vue3';
defineProps({localPage:Object,results:Object,bounded:Boolean});
const money = c => new Intl.NumberFormat(c.locale || 'en', {style:'currency',currency:c.currency}).format(c.price_minor / 100);
</script>
<template>
<MarketplaceLayout><Head :title="$page.props.seo.title" /><main class="mp-container local-page">
    <nav v-if="localPage.breadcrumbs.length" class="mp-actions" aria-label="Breadcrumbs"><Link href="/marketplace">Marketplace</Link><Link v-for="crumb in localPage.breadcrumbs" :key="crumb.url" :href="crumb.url">{{ crumb.name }}</Link></nav>
    <header><h1 class="mp-title">{{ localPage.title }}</h1><p class="mp-lead">{{ localPage.description }}</p></header>
    <AppNotice v-if="bounded" title="Explore a more specific service or area" description="This page shows a limited selection. Search for what you need to see more relevant choices."><template #actions><Link href="/marketplace" class="mp-button secondary">Search appointments</Link></template></AppNotice>
    <div v-if="results.data.length" class="local-results"><article v-for="card in results.data" :key="card.url" class="mp-card"><img v-if="card.image" :src="card.image" :alt="card.name" loading="lazy" width="400" height="240" /><h2><Link :href="card.url">{{ card.name }}</Link></h2><p>{{ card.address }}</p><p>{{ card.service }} · From {{ money(card) }}</p><p v-if="card.reviews.count">{{ card.reviews.average }} out of 5 · {{ card.reviews.count }} verified visits</p><p v-if="card.new_on_marketplace">New on Marketplace</p><p v-if="card.client_eligibility === 'existing' || card.new_client_rule === 'existing_only'">Returning customers only</p><div class="mp-actions"><Link class="mp-button" :href="card.url">Explore & book</Link></div></article></div>
    <AppNotice v-else title="No appointments to show in this area yet" description="Try another area or contact a business you know."><template #actions><Link class="mp-button secondary" href="/marketplace">Search another area</Link></template></AppNotice>
    <nav v-if="results.prev_page_url || results.next_page_url" class="mp-actions" aria-label="Result pages"><Link v-if="results.prev_page_url" class="mp-button secondary" :href="results.prev_page_url">Previous</Link><span>Page {{ results.current_page }}</span><Link v-if="results.next_page_url" class="mp-button secondary" :href="results.next_page_url">Next</Link></nav>
    <nav v-if="localPage.links.length" class="local-links" aria-label="Explore local appointments"><h2>Explore local appointments</h2><div class="mp-actions"><Link v-for="link in localPage.links" :key="link.url" :href="link.url" class="mp-button quiet">{{ link.name }}</Link></div></nav>
</main></MarketplaceLayout>
</template>
<style scoped>.local-page{padding-top:36px;padding-bottom:60px}.local-page header{max-width:850px;margin:32px 0}.local-results{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.local-results img{width:100%;height:200px;object-fit:cover;border-radius:10px}.local-results h2{font-size:24px;margin:16px 0 12px}.local-results p{margin-top:10px;overflow-wrap:anywhere}.local-links{margin-top:40px}.mp-actions{flex-wrap:wrap;gap:10px;margin-top:14px}.mp-title{overflow-wrap:anywhere}@media(max-width:900px){.local-results{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.local-results{grid-template-columns:1fr}.mp-title{font-size:34px}.local-page header{margin:24px 0}}</style>
