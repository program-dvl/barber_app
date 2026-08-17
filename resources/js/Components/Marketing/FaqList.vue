<script setup>
defineProps({ items: { type: Array, required: true } });
</script>

<template>
    <div class="divide-y divide-[var(--border-subtle)] border-y border-[var(--border-subtle)]">
        <details v-for="item in items" :key="item.question" class="group py-2">
            <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-5 rounded-lg py-3 text-left font-bold text-[var(--text-strong)]">
                <span>{{ item.question }}</span>
                <span aria-hidden="true" class="text-xl text-[var(--action-primary)] transition-transform group-open:rotate-45">+</span>
            </summary>
            <p class="max-w-3xl pb-5 leading-7 text-[var(--text-muted)]">{{ item.answer }}</p>
        </details>
    </div>
</template>
