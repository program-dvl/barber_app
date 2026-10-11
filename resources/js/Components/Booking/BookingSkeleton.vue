<script setup>
defineProps({
    variant: { type: String, default: 'appointment' },
    label: { type: String, default: 'Loading appointment details' },
});
</script>

<template>
    <div role="status" aria-busy="true" :aria-label="label">
        <span class="ds-sr-only">{{ label }}</span>
        <div aria-hidden="true" class="animate-pulse motion-reduce:animate-none">
            <template v-if="variant === 'times'">
                <div class="h-4 w-36 rounded bg-[var(--surface-subtle)]" />
                <div class="mt-4 flex gap-2">
                    <div v-for="item in 3" :key="item" class="h-11 w-24 rounded-lg bg-[var(--surface-subtle)]" />
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <div v-for="item in 12" :key="item" class="h-11 rounded-lg bg-[var(--surface-subtle)]" />
                </div>
            </template>
            <template v-else>
                <div class="h-6 w-2/3 rounded bg-[var(--surface-subtle)]" />
                <div class="mt-5 space-y-4 rounded-xl border border-[var(--border-subtle)] p-4">
                    <div class="h-4 w-3/4 rounded bg-[var(--surface-subtle)]" />
                    <div class="h-3 w-1/2 rounded bg-[var(--surface-subtle)]" />
                    <div v-for="item in 3" :key="item" class="flex justify-between gap-6 pt-2">
                        <div class="h-4 w-3/5 rounded bg-[var(--surface-subtle)]" />
                        <div class="h-4 w-16 rounded bg-[var(--surface-subtle)]" />
                    </div>
                </div>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div v-for="item in 3" :key="item" :class="item === 3 ? 'sm:col-span-2' : ''">
                        <div class="mb-2 h-3 w-20 rounded bg-[var(--surface-subtle)]" />
                        <div class="h-11 rounded-lg bg-[var(--surface-subtle)]" />
                    </div>
                </div>
                <div class="mt-6 h-11 rounded-lg bg-[var(--surface-subtle)]" />
            </template>
        </div>
    </div>
</template>
