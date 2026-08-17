<script setup>
defineProps({ caption: { type: String, required: true }, columns: { type: Array, required: true }, rows: { type: Array, required: true } });
</script>

<template>
    <div class="overflow-x-auto rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-raised)]" tabindex="0" role="region" :aria-label="`${caption}. Scroll horizontally on small screens.`">
        <table class="min-w-[42rem] w-full border-collapse text-left text-sm">
            <caption class="sr-only">{{ caption }}</caption>
            <thead class="bg-[var(--surface-subtle)] text-[var(--text-strong)]">
                <tr><th v-for="column in columns" :key="column" scope="col" class="px-5 py-4 font-bold">{{ column }}</th></tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row[0]" class="border-t border-[var(--border-subtle)]">
                    <th scope="row" class="px-5 py-4 font-bold text-[var(--text-strong)]">{{ row[0] }}</th>
                    <td v-for="(cell, index) in row.slice(1)" :key="index" class="px-5 py-4 text-[var(--text-default)]">{{ cell }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
