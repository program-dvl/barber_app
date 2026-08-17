<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    href: String,
    variant: {
        type: String,
        default: 'primary',
        validator: value => ['primary', 'secondary', 'quiet', 'danger'].includes(value),
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: Boolean,
});

const styles = {
    primary: 'border-transparent bg-[var(--action-primary)] text-white hover:bg-[var(--action-primary-hover)]',
    secondary: 'border-[var(--border-strong)] bg-[var(--surface-raised)] text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]',
    quiet: 'border-transparent bg-transparent text-[var(--text-default)] hover:bg-[var(--surface-subtle)]',
    danger: 'border-transparent bg-[var(--status-danger)] text-white hover:brightness-90',
};

const element = ref(null);
defineExpose({ focus: () => element.value?.$el?.focus?.() ?? element.value?.focus?.() });
</script>

<template>
    <component
        ref="element"
        :is="href ? Link : 'button'"
        :href="href"
        :type="href ? undefined : type"
        :disabled="disabled"
        :aria-disabled="disabled || undefined"
        :class="[
            styles[props.variant],
            'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition-colors',
            disabled ? 'cursor-not-allowed opacity-55' : '',
        ]"
    >
        <slot />
    </component>
</template>
