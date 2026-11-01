<script setup>
import { inject as injectRoute } from 'vue';
const route = injectRoute('route');
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
        :class="['cd-button', secondary ? 'cd-button-secondary' : 'cd-button-primary', compact && 'cd-button-compact']"
        :data-cta-context="context"
        :data-cta-action="authenticated ? 'dashboard' : 'trial'"
    >
        <slot>{{ label }}</slot>
    </Link>
</template>
