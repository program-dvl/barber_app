<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    active: Boolean,
    href: String,
    as: String,
});

const classes = computed(() => {
    return props.active
        ? 'block min-h-11 w-full border-l-4 border-[var(--brand-secondary)] bg-[var(--action-secondary-hover)] py-2 pe-4 ps-3 text-start text-base font-medium text-[var(--brand-secondary)] transition duration-150 ease-in-out'
        : 'block min-h-11 w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-[var(--text-muted)] transition duration-150 ease-in-out hover:border-[var(--border-default)] hover:bg-[var(--surface-subtle)] hover:text-[var(--text-strong)]';
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" :class="classes" class="w-full text-start">
            <slot />
        </button>

        <Link v-else :href="href" :class="classes">
            <slot />
        </Link>
    </div>
</template>
