<script setup>
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import HelpSearch from '@/Components/Help/HelpSearch.vue';
import HelpArticleCard from '@/Components/Help/HelpArticleCard.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpenIcon, ArrowRightIcon, ScissorsIcon, UsersIcon, CalendarDaysIcon, BuildingStorefrontIcon, CreditCardIcon, ChartBarIcon, QueueListIcon, UserCircleIcon, CubeIcon, ChatBubbleLeftRightIcon, ShieldCheckIcon, RocketLaunchIcon, BanknotesIcon } from '@heroicons/vue/24/outline';
import '../../../css/help-center.css';
const props = defineProps({ categories: Array, featured: Array, articles: Array, query: String, results: Array, seo: Object });
const page = usePage();
const icons = { 'getting-started': RocketLaunchIcon, business: BuildingStorefrontIcon, team: UsersIcon, services: ScissorsIcon, appointments: BookOpenIcon, calendar: CalendarDaysIcon, 'walk-ins': QueueListIcon, clients: UserCircleIcon, checkout: BanknotesIcon, inventory: CubeIcon, reports: ChartBarIcon, notifications: ChatBubbleLeftRightIcon, billing: CreditCardIcon, account: ShieldCheckIcon };
</script>
<template>
    <MarketingLayout page-class="help-center">
        <Head :title="seo?.title || 'Help Center'" />
        <header class="help-hero"><div class="help-container"><p class="help-eyebrow"><BookOpenIcon class="size-4" aria-hidden="true" />ClipperDesk Help Center</p><h1>Good answers.<br />A smoother working day.</h1><p class="help-hero-copy">From your first setup to the last appointment. Clear, practical guides for you and your team.</p><HelpSearch :initial-query="query || ''" /><div class="help-quick-search"><span>Try:</span><a href="/help?q=add+team+member">Add a team member</a><a href="/help?q=booking+availability">Booking availability</a><a href="/help?q=subscription">Subscription</a></div></div></header>
        <div class="help-container help-main">
            <section v-if="query" aria-labelledby="results-heading" class="help-section"><div class="help-section-heading"><div><p class="help-eyebrow">Find your answer</p><h2 id="results-heading">Results for “{{ query }}”</h2></div><Link href="/help" class="cd-button cd-button-quiet">Clear search</Link></div><p class="help-muted mb-5" role="status">{{ results?.length || 0 }} matching {{ results?.length === 1 ? 'guide' : 'guides' }}</p><div v-if="results?.length" class="help-result-list"><HelpArticleCard v-for="article in results" :key="article.slug" :article="article" /></div><AppNotice v-else title="Let’s find another way" description="Try a shorter question, a different word, or browse the topics below. For access problems, ask your business owner to check your permissions."><template #actions><a href="/help/category/account" class="cd-button cd-button-secondary">Account & access help</a><a v-if="page.props.brand?.support_email" :href="`mailto:${page.props.brand.support_email}`" class="cd-button cd-button-quiet">Contact support</a></template></AppNotice></section>
            <section v-else class="help-section" aria-labelledby="start-heading"><div class="help-section-heading"><div><p class="help-eyebrow">A confident start</p><h2 id="start-heading">Start here</h2></div><Link href="/help/category/getting-started" class="help-text-link">All getting started guides <ArrowRightIcon class="size-4" aria-hidden="true" /></Link></div><div class="help-featured"><HelpArticleCard v-for="article in featured" :key="article.slug" :article="article" /></div></section>
            <section class="help-section" aria-labelledby="topics-heading"><div class="help-section-heading"><div><p class="help-eyebrow">Your whole workspace, explained</p><h2 id="topics-heading">Browse by topic</h2></div></div><div class="help-category-grid"><Link v-for="category in categories" :key="category.slug" :href="`/help/category/${category.slug}`" class="help-category-card"><span class="help-topic-icon"><component :is="icons[category.slug] || BookOpenIcon" class="size-6" aria-hidden="true" /></span><h3>{{ category.title }}</h3><p>{{ category.description }}</p><span class="help-topic-count">{{ category.article_count ?? category.count ?? 0 }} guides <ArrowRightIcon class="size-4" aria-hidden="true" /></span></Link></div></section>
            <aside class="help-support"><div><h2>A little extra help?</h2><p>Tell us which screen you are on and what you wanted to do. Include the safe reference shown with an error, if there is one.</p></div><a v-if="page.props.brand?.support_email" :href="`mailto:${page.props.brand.support_email}`" class="cd-button cd-button-secondary">Contact support</a></aside>
        </div>
    </MarketingLayout>
</template>
