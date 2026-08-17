<script setup>
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

defineProps({ health: Object });
</script>

<template>
    <PlatformAdminLayout title="System health">
        <PageHeader eyebrow="Internal operations" title="System health" description="A safe operational summary for queues, provider callbacks, communications, reconciliation, and backup evidence." />
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <SurfaceCard v-for="(values, area) in health" :key="area" :title="String(area).replaceAll('_', ' ')" :description="area === 'backup' && values.status === 'not_configured' ? 'No verified backup integration is configured.' : undefined">
                <dl v-if="typeof values === 'object' && values !== null" class="space-y-3">
                    <div v-for="(value, label) in values" :key="label" class="flex items-start justify-between gap-4 border-b border-[var(--border-subtle)] pb-2 last:border-0">
                        <dt class="text-sm capitalize text-[var(--text-muted)]">{{ String(label).replaceAll('_', ' ') }}</dt>
                        <dd class="text-right text-sm font-semibold text-[var(--text-strong)]">{{ value ?? 'Not available' }}</dd>
                    </div>
                </dl>
                <p v-else class="text-sm font-semibold">{{ values }}</p>
            </SurfaceCard>
        </div>
    </PlatformAdminLayout>
</template>
