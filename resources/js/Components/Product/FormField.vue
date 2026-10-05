<script setup>
import { cloneVNode, defineComponent, Fragment, h } from 'vue';

// Attach semantics to the control matching this field, including custom inputs.
const FieldControls = defineComponent({
    props: { id: String, required: Boolean, error: String, hint: String },
    setup(props, { slots }) {
        const connect = node => {
            if (node.props?.id === props.id) return cloneVNode(node, {
                required: props.required || node.props.required || undefined,
                'aria-invalid': props.error ? 'true' : undefined,
                'aria-describedby': [props.hint ? `${props.id}-hint` : null, props.error ? `${props.id}-error` : null, node.props['aria-describedby']].filter(Boolean).filter((v, i, a) => a.indexOf(v) === i).join(' ') || undefined,
            });
            if (Array.isArray(node.children)) return cloneVNode({ ...node, children: node.children.map(connect) });
            return node;
        };
        return () => h(Fragment, null, (slots.default?.() || []).map(connect));
    },
});
defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    hint: String,
    error: String,
    required: Boolean,
});
</script>

<template>
    <div class="cd-form-field min-w-0">
        <label :for="id" class="block text-sm font-semibold text-[var(--text-strong)]">
            {{ label }}
            <span v-if="required" aria-hidden="true" class="text-[var(--status-danger)]">*</span>
            <span v-if="required" class="ds-sr-only"> required</span>
        </label>
        <p v-if="hint" :id="`${id}-hint`" class="mt-1 text-sm text-[var(--text-muted)]">{{ hint }}</p>
        <div class="mt-1.5">
            <FieldControls :id="id" :required="required" :error="error" :hint="hint"><slot :describedby="[hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined" /></FieldControls>
        </div>
        <p v-if="error" :id="`${id}-error`" role="alert" class="mt-1.5 text-sm font-medium text-[var(--status-danger)]">{{ error }}</p>
    </div>
</template>
