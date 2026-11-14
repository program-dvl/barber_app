<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import marketplaceMark from '../../images/brand/clipperdesk-marketplace-mark.svg';
import { openAcquisitionPreferences } from '@/Support/marketplaceAttribution';
import {useActionMenus} from '@/Support/useActionMenus';
useActionMenus();
import '../../css/marketplace.css';
const page = usePage();
const customer = computed(() => page.props.marketplaceCustomer);
const remaining=ref(0);let cooldown;
const updateCooldown=()=>{remaining.value=Math.max(0,Math.ceil(((page.props.marketplaceRetryAt||0)-Date.now())/1000));};
watch(()=>page.props.marketplaceRetryAt,updateCooldown,{immediate:true});
onMounted(()=>{cooldown=setInterval(updateCooldown,1000);});
onBeforeUnmount(()=>clearInterval(cooldown));
const logout=()=>router.post('/marketplace/sign-out',{}, {preserveState:false});
const status = computed(() => page.props.marketplaceStatus);
</script>
<template>
<div class="market">
    <a class="mp-skip" href="#marketplace-main">Skip to content</a>
    <header class="mp-header">
        <Link href="/marketplace" class="mp-wordmark" aria-label="Clipperdesk Marketplace home"><span class="mp-symbol"><img :src="marketplaceMark" alt="" width="44" height="44" aria-hidden="true"/><span aria-hidden="true">✳</span></span><span>Clipperdesk<small>marketplace</small></span></Link>
        <nav aria-label="Marketplace navigation"><Link href="/marketplace">Explore</Link><Link href="/marketplace/guides">Guides</Link><Link v-if="customer" href="/marketplace/account">My bookings</Link><Link v-if="customer" href="/marketplace/messages">Messages</Link><details v-if="customer" class="mp-account-menu cd-action-menu"><summary aria-label="Your account"><span class="mp-avatar"><img v-if="customer.photo_url" :src="customer.photo_url" alt="" width="33" height="33"/><template v-else>{{ (customer.name || customer.email).slice(0,1).toUpperCase() }}</template></span><span class="mp-account-caption">My account</span><ChevronDownIcon class="mp-account-chevron" aria-hidden="true"/></summary><div class="mp-account-panel"><p>{{customer.name || 'Your account'}}</p><small>{{customer.email}}</small><Link href="/marketplace/profile">My profile</Link><Link href="/marketplace/account">My bookings & favourites</Link><Link href="/marketplace/messages">Messages</Link><button type="button" @click="logout">Log out</button></div></details><Link v-else href="/marketplace/sign-in">Log in / Sign up</Link><a href="/" class="mp-business-link">For businesses ↗</a></nav>
    </header>
    <div v-if="status" class="mp-status"><AppNotice tone="success" :title="status" /></div>
    <div v-if="page.props.errors?.marketplace" class="mp-status"><AppNotice tone="warning" title="Let’s help you continue" :description="page.props.errors.marketplace"><template #actions><span v-if="remaining">Try again in {{remaining}} seconds</span><a class="mp-button secondary" :href="`mailto:${page.props.brand?.support_email}`">Contact support</a></template></AppNotice></div>
    <fieldset id="marketplace-main" tabindex="-1" :disabled="remaining>0 || page.props.marketplaceBlocked" style="min-width:0;border:0;padding:0;margin:0"><slot /></fieldset>
    <footer class="mp-footer"><Link href="/marketplace" class="mp-footer-brand">Clipperdesk<small>marketplace</small></Link><p>A little time for you.</p><nav aria-label="Footer"><a href="/help">Help</a><a :href="`mailto:${page.props.brand?.support_email}`">Contact support</a><a href="/privacy-policy">Privacy</a><a href="/marketplace/terms">Terms</a><a href="/">Clipperdesk Business ↗</a><button v-if="page.props.acquisitionPublic" type="button" class="mp-cookie-settings" @click="openAcquisitionPreferences">Cookie settings</button></nav></footer>
</div>
</template>
