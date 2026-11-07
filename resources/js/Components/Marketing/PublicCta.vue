<script setup>
import { inject as injectRoute } from 'vue';
const route = injectRoute('route');
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BoltIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    context: {
        type: String,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    secondary: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const authenticated = computed(() => Boolean(page.props.auth?.user));
const pricingWorkspace = computed(() => page.url.split('?')[0] === '/pricing' ? page.props.account?.workspaces?.find(item => item.can_manage_billing) : null);
const destination = computed(() => pricingWorkspace.value ? route('business.billing.show', {business: pricingWorkspace.value.public_id, section: 'plan'}) : authenticated.value ? route('dashboard') : route('register'));
const label = computed(() => pricingWorkspace.value ? 'Manage subscription' : authenticated.value ? 'Open dashboard' : 'Start your trial');
</script>

<template>
    <Link
        :href="destination"
        :class="['cd-button', secondary ? 'cd-button-secondary' : 'cd-button-primary', compact && 'cd-button-compact', !authenticated && ['header', 'mobile_navigation'].includes(context) && 'public-trial-cta']"
        :data-cta-context="context"
        :data-cta-action="authenticated ? 'dashboard' : 'trial'"
    >
        <span v-if="!authenticated && ['header', 'mobile_navigation'].includes(context)" class="public-trial-badge"><BoltIcon aria-hidden="true" />3 months free</span>
        <slot>{{ label }}</slot>
    </Link>
</template>

<style scoped>
.public-trial-cta { position:relative; overflow:visible; }
.public-trial-badge { position:absolute; top:-13px; right:8px; display:inline-flex; align-items:center; gap:4px; padding:4px 8px; border:1px solid #c7d2fe; border-radius:999px; background:#eef2ff; color:#253363; font-size:10px; font-weight:700; line-height:1.2; white-space:nowrap; transform:rotate(3deg); box-shadow:0 2px 5px #17255418; }
.public-trial-badge svg { width:13px; height:13px; color:#a66b08; fill:#fbbf24; stroke-width:1.5; }
</style>
