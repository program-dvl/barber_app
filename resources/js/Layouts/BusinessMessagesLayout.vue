<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowPathIcon, ChatBubbleLeftRightIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import MarketplaceRecovery from '@/Components/Product/MarketplaceRecovery.vue';
const props = defineProps({businessLabel:String,inbox:Object,selectedId:String});
const search = ref(props.inbox?.q || '');
const refreshing = ref(false);
watch(()=>props.inbox?.q,value=>search.value=value || '');
const filter = view => router.get(props.inbox.url,{q:search.value.trim() || undefined,view},{preserveState:true,preserveScroll:true});
const refresh = () => {
    if(refreshing.value)return;
    router.reload({only:['inbox','messages','paused'],preserveScroll:true,onStart:()=>refreshing.value=true,onFinish:()=>refreshing.value=false});
};
</script>
<template>
    <AppLayout title="Messages" :business-label="businessLabel">
        <PageHeader title="Messages"><template #actions><AppButton variant="secondary" size="small" :disabled="refreshing" @click="refresh"><ArrowPathIcon class="size-4" aria-hidden="true"/>{{refreshing?'Refreshing…':'Refresh'}}</AppButton></template></PageHeader>
        <MarketplaceRecovery business>
            <div class="business-messages" :class="{'has-conversation':selectedId}">
                <aside class="business-inbox" aria-label="Conversations">
                    <form class="business-inbox-search" role="search" @submit.prevent="filter(inbox.view)"><label for="conversation-search" class="sr-only">Search customer or booking reference</label><input id="conversation-search" v-model="search" type="search" maxlength="100" placeholder="Search customer or booking"/><button type="submit" aria-label="Search conversations"><MagnifyingGlassIcon aria-hidden="true"/></button></form>
                    <div class="business-inbox-filters" aria-label="Conversation filter"><button type="button" :aria-pressed="inbox.view!=='unread'" @click="filter('all')">All</button><button type="button" :aria-pressed="inbox.view==='unread'" @click="filter('unread')">Unread</button></div>
                    <div class="business-inbox-list">
                        <AppNotice v-if="!inbox.canContact" title="Messages aren’t available for your role" description="Ask your owner if you need access to customer conversations."/>
                        <div v-else-if="!inbox.threads.data.length" class="business-inbox-empty"><ChatBubbleLeftRightIcon aria-hidden="true"/><h2>{{inbox.q?'No matching conversations':inbox.view==='unread'?'You’re all caught up':'No conversations yet'}}</h2><p>{{inbox.q?'Try another name or booking reference.':inbox.view==='unread'?'New customer messages will appear here.':'Messages from customers with a connected booking will appear here.'}}</p><button v-if="inbox.q || inbox.view==='unread'" type="button" @click="search='';filter('all')">Show all conversations</button></div>
                        <Link v-for="thread in inbox.threads.data" :key="thread.id" :href="thread.url" class="business-inbox-thread" :class="{selected:selectedId===thread.id,unread:thread.unread}" :aria-current="selectedId===thread.id?'page':undefined"><span class="business-inbox-avatar" aria-hidden="true">{{thread.name?.slice(0,1) || '?'}}</span><span class="business-inbox-summary"><span class="business-inbox-name">{{thread.name || 'Customer'}}<span v-if="thread.unread" class="business-inbox-count" :aria-label="`${thread.unread} unread messages`">{{thread.unread>99?'99+':thread.unread}}</span></span><span class="business-inbox-preview">{{thread.preview}}</span><span class="business-inbox-reference">{{thread.reference}} · {{thread.starts_at}}</span></span></Link>
                    </div>
                    <nav v-if="inbox.threads.prev_page_url || inbox.threads.next_page_url" class="business-inbox-pagination" aria-label="Conversation pages"><Link v-if="inbox.threads.prev_page_url" :href="inbox.threads.prev_page_url">Previous</Link><span>{{inbox.threads.current_page}}</span><Link v-if="inbox.threads.next_page_url" :href="inbox.threads.next_page_url">Next</Link></nav>
                </aside>
                <section class="business-conversation" aria-label="Selected conversation"><slot/></section>
            </div>
        </MarketplaceRecovery>
    </AppLayout>
</template>
<style scoped>
.business-messages{display:grid;grid-template-columns:320px minmax(0,1fr);min-height:620px;border:1px solid var(--border-default);border-radius:12px;background:var(--surface-raised);overflow:hidden;margin-top:20px;color:var(--text-default)}.business-inbox{display:flex;flex-direction:column;min-width:0;border-right:1px solid var(--border-subtle)}.business-inbox-search{display:flex;align-items:center;gap:8px;margin:16px 16px 10px;border:1px solid var(--border-default);border-radius:8px;padding-left:10px}.business-inbox-search svg{width:17px;height:17px;color:var(--text-muted);flex-shrink:0}.business-inbox-search input{width:100%;min-width:0;border:0;background:transparent;box-shadow:none;padding:8px 0;font-size:13px;min-height:38px}.business-inbox-search button{display:grid;place-items:center;min-width:36px;min-height:36px}.business-inbox-filters{display:flex;gap:10px;padding:0 16px 12px;border-bottom:1px solid var(--border-subtle)}.business-inbox-filters button{padding:6px 12px;min-height:34px;border-radius:6px;font-size:13px;color:var(--text-muted)}.business-inbox-filters button[aria-pressed=true]{background:var(--action-primary-soft,var(--surface-subtle));color:var(--action-primary);font-weight:600}.business-inbox-list{flex:1;max-height:680px;overflow:auto}.business-inbox-thread{display:flex;gap:10px;padding:16px;border-bottom:1px solid var(--border-subtle);text-decoration:none;transition:background .15s}.business-inbox-thread:hover{background:var(--surface-subtle)}.business-inbox-thread.selected{background:var(--surface-subtle);box-shadow:inset 3px 0 var(--action-primary)}.business-inbox-avatar{display:grid;place-items:center;width:36px;height:36px;flex-shrink:0;background:var(--surface-subtle);border:1px solid var(--border-subtle);color:var(--text-muted);border-radius:50%;font-size:13px;font-weight:600}.business-inbox-summary{display:grid;gap:5px;min-width:0;flex:1}.business-inbox-name{display:flex;gap:8px;justify-content:space-between;align-items:center;font-size:14px;color:var(--text-strong);overflow-wrap:anywhere}.unread .business-inbox-name{font-weight:600}.business-inbox-count{font-size:10px;min-width:20px;min-height:20px;padding:2px 5px;text-align:center;border-radius:20px;background:var(--action-primary);color:white}.business-inbox-preview{font-size:12px;color:var(--text-default);overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.business-inbox-reference{font-size:10px;line-height:1.6;color:var(--text-muted)}.business-conversation{min-width:0;background:var(--surface-raised)}.business-inbox-empty{padding:40px 22px;text-align:center}.business-inbox-empty svg{width:28px;height:28px;margin:0 auto 14px;color:var(--text-muted)}.business-inbox-empty h2{font-size:14px;font-weight:600;margin-bottom:8px}.business-inbox-empty p{font-size:12px;line-height:1.7;color:var(--text-muted)}.business-inbox-empty button{font-size:13px;color:var(--action-primary);margin-top:14px;min-height:44px}.business-inbox-pagination{display:flex;gap:14px;align-items:center;justify-content:center;font-size:12px;border-top:1px solid var(--border-subtle);padding:8px}.business-inbox-pagination a{min-height:36px;display:flex;align-items:center}.business-messages :is(button,input,a):focus-visible{outline:2px solid var(--action-primary);outline-offset:2px}
@media(max-width:1100px){.business-messages{grid-template-columns:270px minmax(0,1fr)}}
@media(max-width:760px){.business-messages{grid-template-columns:1fr;min-height:520px;margin-top:16px}.business-inbox{border-right:0}.has-conversation .business-inbox{display:none}.business-messages:not(.has-conversation) .business-conversation{display:none}.business-inbox-search input{font-size:16px;min-height:44px}.business-inbox-search button,.business-inbox-filters button{min-height:44px;min-width:44px}.business-inbox-pagination a{min-height:44px}.business-inbox-list{max-height:none}}
</style>
