<script setup>
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
const destination = computed(() => authenticated.value ? route('dashboard') : route('register'));
const label = computed(() => authenticated.value ? 'Open dashboard' : 'Start your trial');
</script>

<template>
    <Link
        :href="destination"
        :class="['gh-button', secondary ? 'gh-button-secondary' : 'gh-button-primary', compact && 'gh-button-compact']"
        :data-cta-context="context"
        :data-cta-action="authenticated ? 'dashboard' : 'trial'"
    >
        <slot>{{ label }}</slot>
    </Link>
</template>
