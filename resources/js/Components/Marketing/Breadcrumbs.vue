<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ items: { type: Array, required: true } });
</script>

<template>
    <nav aria-label="Breadcrumb" class="text-sm text-[var(--text-muted)]">
        <ol class="flex flex-wrap items-center gap-2">
            <li v-for="(item, index) in items" :key="item.label" class="flex items-center gap-2">
                <span v-if="index" aria-hidden="true">/</span>
                <Link v-if="item.href" :href="item.href" class="inline-flex min-h-11 items-center rounded-md font-semibold underline-offset-4 hover:text-[var(--brand-pine)] hover:underline">
                    {{ item.label }}
                </Link>
                <span v-else aria-current="page" class="font-semibold text-[var(--text-strong)]">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
