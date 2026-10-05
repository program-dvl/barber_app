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
        <div class="bg-[var(--surface-raised)] px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
                <div class="mx-auto flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--status-danger-soft)] sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-[var(--status-danger)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ms-4 sm:text-start">
                    <h2 :id="titleId" class="cd-section-title">
                        <slot name="title" />
                    </h2>

                    <div class="mt-4 text-sm text-[var(--text-muted)]">
                        <slot name="content" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-row flex-wrap items-center justify-end gap-2 bg-[var(--surface-subtle)] px-6 py-4 text-end">
            <slot name="footer" />
        </div>
    </Modal>
</template>
