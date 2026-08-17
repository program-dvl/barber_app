<script setup>
import { nextTick, ref } from 'vue';
import AppButton from '@/Components/Product/AppButton.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: String,
    confirmLabel: {
        type: String,
        default: 'Confirm',
    },
    destructive: Boolean,
});

const emit = defineEmits(['confirm', 'cancel']);
const dialog = ref(null);
const cancelButton = ref(null);
const opener = ref(null);

const open = async () => {
    opener.value = document.activeElement;
    dialog.value?.showModal();
    await nextTick();
    cancelButton.value?.focus();
};

const close = () => {
    dialog.value?.close();
    nextTick(() => opener.value?.focus?.());
};

const cancel = () => {
    emit('cancel');
    close();
};

const confirm = () => {
    emit('confirm');
    close();
};

defineExpose({ open, close });
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-0 text-[var(--text-default)] shadow-[var(--shadow-overlay)] backdrop:bg-black/45"
        :aria-labelledby="`${$attrs.id || 'app-dialog'}-title`"
        :aria-describedby="description ? `${$attrs.id || 'app-dialog'}-description` : undefined"
        @cancel.prevent="cancel"
        @keydown.esc.stop.prevent="cancel"
        @click.self="cancel"
    >
        <div class="p-5 sm:p-6">
            <div v-if="destructive" class="mb-4 grid size-10 place-items-center rounded-full bg-[var(--status-danger-soft)] text-[var(--status-danger)]" aria-hidden="true">!</div>
            <h2 :id="`${$attrs.id || 'app-dialog'}-title`" class="text-lg font-semibold text-[var(--text-strong)]">{{ title }}</h2>
            <p v-if="description" :id="`${$attrs.id || 'app-dialog'}-description`" class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ description }}</p>
            <div class="mt-4"><slot /></div>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton ref="cancelButton" variant="secondary" @click="cancel">Cancel</AppButton>
                <AppButton :variant="destructive ? 'danger' : 'primary'" @click="confirm">{{ confirmLabel }}</AppButton>
            </div>
        </div>
    </dialog>
</template>
