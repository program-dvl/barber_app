<script setup>
import Modal from './Modal.vue';
import { useId } from 'vue';
const titleId = useId();

const emit = defineEmits(['close']);

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        :labelledby="titleId"
        @close="close"
    >
        <div class="px-6 py-4">
            <h2 :id="titleId" class="cd-section-title">
                <slot name="title" />
            </h2>

            <div class="mt-4 text-sm text-[var(--text-muted)]">
                <slot name="content" />
            </div>
        </div>

        <div class="flex flex-row flex-wrap items-center justify-end gap-2 bg-[var(--surface-subtle)] px-6 py-4 text-end">
            <slot name="footer" />
        </div>
    </Modal>
</template>
