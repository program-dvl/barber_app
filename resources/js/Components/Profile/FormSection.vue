<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-5 md:col-span-2 md:mt-0">
            <form @submit.prevent="$emit('submitted')">
                <div class="overflow-hidden rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)]">
                    <div class="space-y-5 p-5">
                        <div class="grid grid-cols-6 gap-4">
                            <slot name="form" />
                        </div>
                    </div>

                    <div v-if="hasActions" class="flex items-center justify-end gap-3 border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-5 py-3 text-end">
                        <slot name="actions" />
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
