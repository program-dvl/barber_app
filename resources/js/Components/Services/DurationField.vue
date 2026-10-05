<script setup>
import { computed, ref } from 'vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import { durationLabel } from '@/Support/serviceCatalog';
defineOptions({ inheritAttrs: false });
const props = defineProps({ modelValue: [Number, String], id: String, allowZero: Boolean, allowDefault: Boolean, defaultMinutes: Number, max: { type: Number, default: 1440 }, error: String });
const emit = defineEmits(['update:modelValue']);
const custom = ref(false);
const options = computed(() => [...new Set([...(props.allowZero ? [0] : []), 5, 10, 15, 20, 25, 30, 40, 45, 50, 60, 75, 90, 120, 150, 180, 240, 360, Number(props.modelValue || 0)])].filter(n => props.allowZero || n > 0).sort((a,b) => a-b));
const select = value => { if (value === 'custom') custom.value = true; else emit('update:modelValue', value === '' ? null : Number(value)); };
</script>
<template>
    <AppSelect v-if="!custom" :id="id" :model-value="allowDefault && modelValue == null ? '' : Number(modelValue || 0)" @update:model-value="select" v-bind="$attrs" :aria-invalid="error ? true : undefined">
        <option v-if="allowDefault" value="">Default: {{ durationLabel(defaultMinutes) }}</option><option v-for="minutes in options" :key="minutes" :value="minutes">{{ minutes === 0 ? 'None' : durationLabel(minutes) }}</option><option value="custom">Custom duration…</option>
    </AppSelect>
    <div v-else class="svc-custom-duration"><input :id="id" class="cd-input" type="number" :min="allowZero ? 0 : 1" :max="max" :value="modelValue" @input="emit('update:modelValue', $event.target.value === '' && allowDefault ? null : $event.target.value)" v-bind="$attrs" :aria-invalid="error ? true : undefined" /><span>min</span><button type="button" @click="custom = false" aria-label="Use duration presets">Done</button></div>
</template>
