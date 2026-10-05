<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({ show: Boolean, maxWidth: { type: String, default: '2xl' }, closeable: { type: Boolean, default: true }, labelledby: String });
const emit = defineEmits(['close']);
const dialog = ref(null);
let trigger;
let previousOverflow;
const maxWidthClass = computed(() => ({ sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl', '2xl': 'max-w-2xl' }[props.maxWidth] || 'max-w-2xl'));
const close = () => { if (props.closeable) emit('close'); };
const sync = async () => {
    await nextTick();
    if (!dialog.value) return;
    if (props.show && !dialog.value.open) {
        trigger = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialog.value.showModal();
        // Native dialog provides focus containment and background inertness.
        dialog.value.querySelector('[autofocus], input:not([disabled]), button:not([disabled])')?.focus();
    } else if (!props.show && dialog.value.open) {
        dialog.value.close();
        document.body.style.overflow = previousOverflow || '';
        if (trigger?.isConnected) trigger.focus();
    }
};
watch(() => props.show, sync);
onMounted(sync);
onUnmounted(() => { if (dialog.value?.open) document.body.style.overflow = previousOverflow || ''; });
const backdrop = event => { if (event.target === dialog.value) { const rect = dialog.value.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close(); } };
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" :aria-labelledby="labelledby" class="cd-profile-dialog m-auto max-h-[calc(100dvh_-_2rem)] w-[calc(100%_-_2rem)] overflow-y-auto rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-0 text-[var(--text-strong)] shadow-[var(--shadow-overlay)]" :class="maxWidthClass" @cancel.prevent="close" @click="backdrop">
            <slot v-if="show" />
        </dialog>
    </Teleport>
</template>
