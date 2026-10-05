<script setup>
import { useId } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

defineOptions({ inheritAttrs: false });
defineProps({ label: { type: String, required: true }, placeholder: String });
const model = defineModel({ type: String, default: '' });
const inputId = `search-${useId()}`;
</script>

<template>
    <div class="cd-search-field">
        <label :for="inputId" class="cd-filter-label">{{ label }}</label>
        <div class="cd-search-control">
            <MagnifyingGlassIcon aria-hidden="true" />
            <input :id="inputId" v-model="model" v-bind="$attrs" type="search" class="cd-input" :placeholder="placeholder" autocomplete="off">
        </div>
    </div>
</template>
