<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDaysIcon, ChatBubbleLeftRightIcon, UserCircleIcon, ArrowRightStartOnRectangleIcon, LifebuoyIcon, HeartIcon } from '@heroicons/vue/24/outline';
import MarketplaceLayout from './MarketplaceLayout.vue';
import '../../css/marketplace-account.css';
const props = defineProps({ title: String, description: String, active: { type: String, default: 'bookings' }, standalone: Boolean });
const page = usePage();
const customer = computed(() => page.props.marketplaceCustomer || {});
const initials = computed(() => (customer.value.name || customer.value.email || 'You').split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase());
const logout = useForm({});
const items = [
    { id: 'bookings', label: 'My bookings', href: '/marketplace/account', icon: CalendarDaysIcon },
    { id: 'messages', label: 'Messages', href: '/marketplace/messages', icon: ChatBubbleLeftRightIcon },
    { id: 'profile', label: 'My profile', href: '/marketplace/profile', icon: UserCircleIcon },
];
</script>
<template>
    <component :is="standalone ? 'div' : MarketplaceLayout">
        <Head :title="`${title} | Clipperdesk Marketplace`" />
        <main class="mp-account-workspace" :class="{ 'mp-account-standalone': standalone }">
            <aside v-if="!standalone" class="mp-account-rail">
                <div class="mp-account-person"><span class="mp-account-avatar" aria-hidden="true"><img v-if="customer.photo_url" :src="customer.photo_url" alt="" width="48" height="48"/><template v-else>{{ initials }}</template></span><div><strong>{{ customer.name || 'Your account' }}</strong><span>Make a little time for you.</span></div></div>
                <nav class="mp-account-navigation" aria-label="Your account"><Link v-for="item in items" :key="item.id" :href="item.href" :aria-current="active===item.id ? 'page' : undefined"><component :is="item.icon" aria-hidden="true" />{{ item.label }}</Link><Link href="/marketplace/account#favourites"><HeartIcon aria-hidden="true" />Favourites</Link></nav>
                <div class="mp-account-rail-bottom"><a :href="`mailto:${page.props.brand?.support_email || 'support@clipperdesk.com'}`"><LifebuoyIcon aria-hidden="true"/>Contact support</a><button type="button" :disabled="logout.processing" @click="logout.post('/marketplace/sign-out',{preserveState:false})"><ArrowRightStartOnRectangleIcon aria-hidden="true"/>{{ logout.processing ? 'Logging out…' : 'Log out' }}</button></div>

            </aside>
            <div class="mp-account-content"><header class="mp-account-page-header"><div><h1>{{ title }}</h1><p v-if="description" class="mp-account-description">{{ description }}</p></div><div v-if="$slots.actions" class="mp-account-header-actions"><slot name="actions"/></div></header><slot /></div>
        </main>
    </component>
</template>
