<script setup>
import { nextTick, onUnmounted, ref, useId } from 'vue';
import { router } from '@inertiajs/vue3';
import AppButton from '@/Components/Product/AppButton.vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: String,
    confirmLabel: {
        type: String,
        default: 'Confirm',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
    destructive: Boolean,
    closeOnConfirm: { type: Boolean, default: true },
    confirmDisabled: Boolean,
    drawer: Boolean,
    canClose: Function,
});

const emit = defineEmits(['confirm', 'cancel']);
const uid = useId();
const dialog = ref(null);
const cancelButton = ref(null);
const opener = ref(null);

const open = async () => {
    opener.value = document.activeElement;
    dialog.value?.showModal();
    await nextTick();
    if (cancelButton.value) cancelButton.value.focus();
    else dialog.value?.querySelector('.cd-dialog-footer button:not([disabled])')?.focus();
};

const close = () => {
    dialog.value?.close();
    nextTick(() => opener.value?.focus?.());
};

const containFocus = event => {
    if (event.key !== 'Tab') return;
    const controls = [...dialog.value.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
        .filter(element => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length);
    const first = controls[0];
    const last = controls.at(-1);
    if (!first) { event.preventDefault(); return; }
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
    }
};

const cancel = () => {
    if (props.canClose && !props.canClose()) return;
    emit('cancel');
    close();
};

const confirm = () => {
    if (props.confirmDisabled) return;
    emit('confirm');
    if (props.closeOnConfirm) close();
};

const stopErrorFocus = router.on('error', async () => {
    await nextTick();
    await nextTick();
    if (!dialog.value?.open) return;
    const target = dialog.value.querySelector('[aria-invalid="true"]');
    (target || dialog.value.querySelector('.cd-dialog-footer button:not([disabled])'))?.focus();
});
onUnmounted(stopErrorFocus);

defineExpose({ open, close });
</script>

<template>
    <dialog
        ref="dialog"
        class="cd-dialog m-auto w-[calc(100%_-_2rem)] max-w-lg rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-0 text-[var(--text-default)] shadow-[var(--shadow-overlay)] backdrop:bg-slate-950/40 backdrop:backdrop-blur-[2px]"
        :class="{ 'cd-dialog-drawer': drawer }"
        :aria-labelledby="`${$attrs.id || uid}-title`"
        :aria-describedby="description ? `${$attrs.id || uid}-description` : undefined"
        @cancel.prevent="cancel"
        @keydown.esc.stop.prevent="cancel"
        @keydown="containFocus"
        @click.self="cancel"
    >
        <div class="cd-dialog-header">
            <div v-if="destructive" class="mb-4 grid size-10 place-items-center rounded-full bg-[var(--status-danger-soft)] text-[var(--status-danger)]" aria-hidden="true">!</div>
            <h2 :id="`${$attrs.id || uid}-title`" class="text-lg font-semibold text-[var(--text-strong)]">{{ title }}</h2>
            <p v-if="description" :id="`${$attrs.id || uid}-description`" class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ description }}</p>
            </div>
            <div class="cd-dialog-body"><slot /></div>
            <div class="cd-dialog-footer">
                <slot name="footer">
                <AppButton ref="cancelButton" variant="secondary" @click="cancel">{{ cancelLabel }}</AppButton>
                <AppButton :variant="destructive ? 'danger' : 'primary'" :disabled="confirmDisabled" @click="confirm">{{ confirmLabel }}</AppButton>
                </slot>
            </div>
    </dialog>
</template>
