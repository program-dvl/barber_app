<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowPathIcon, ArrowRightIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import MarketplaceAccountLayout from '@/Layouts/MarketplaceAccountLayout.vue';
defineProps({ threads: Object });
const refreshing = ref(false);
const refresh = () => { if (!refreshing.value) router.reload({only:['threads'],onStart:()=>refreshing.value=true,onFinish:()=>refreshing.value=false}); };
const date = booking => new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short',timeZone:booking.time_zone}).format(new Date(booking.starts_at));
</script>
<template>
    <MarketplaceAccountLayout title="Messages" description="Conversations about your appointments." active="messages">
        <template #actions><button class="mp-button secondary" :disabled="refreshing" @click="refresh"><ArrowPathIcon aria-hidden="true"/>{{refreshing?'Refreshing…':'Refresh inbox'}}</button></template>
        <div class="mp-account-single"><section aria-label="Your conversations">
            <div v-if="threads.data.length" class="mp-account-surface"><header class="mp-account-section-head"><div><h2>Your conversations</h2><p>Each conversation belongs to one booking.</p></div></header><Link v-for="thread in threads.data" :key="thread.id" :href="`/marketplace/account/bookings/${thread.id}/messages`" class="mp-inbox-row"><span class="mp-inbox-avatar" aria-hidden="true">{{thread.salon.slice(0,1)}}</span><div><div class="mp-inbox-heading"><h2>{{thread.salon}}</h2><span v-if="thread.unread" class="mp-inbox-unread">{{thread.unread}} new</span></div><p>{{thread.services.join(' · ') || 'Your appointment'}}</p><small>{{date(thread)}} · {{thread.time_zone.replaceAll('_',' ')}}</small><small>Booking {{thread.reference}}</small></div><ArrowRightIcon aria-hidden="true"/></Link></div>
            <div v-else class="mp-account-empty"><span><ChatBubbleLeftRightIcon aria-hidden="true"/></span><h2>No conversations yet</h2><p>Book with your account email to message the business about your visit.</p><div class="mp-actions"><Link href="/marketplace/account" class="mp-button">My bookings</Link><Link href="/marketplace" class="mp-button secondary">Find an appointment</Link></div></div>
            <nav v-if="threads.prev_page_url || threads.next_page_url" class="mp-pagination" aria-label="Conversation pages"><Link v-if="threads.prev_page_url" :href="threads.prev_page_url">Previous</Link><span>Page {{threads.current_page}}</span><Link v-if="threads.next_page_url" :href="threads.next_page_url">Next</Link></nav>
        </section></div>
    </MarketplaceAccountLayout>
</template>
