<script setup>
import { computed } from 'vue';
import { sentenceLabel } from '@/Support/userLanguage';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

const props = defineProps({ health: Object });
const sections = computed(() => Object.fromEntries(Object.entries(props.health).filter(([key]) => key !== 'generated_at')));
const valueLabel = (key, value) => value == null ? 'Not available' : key.endsWith('_at') ? new Intl.DateTimeFormat(undefined, { timeZone: 'UTC', dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) + ' UTC' : typeof value === 'string' ? sentenceLabel(value) : value;
</script>

<template>
    <PlatformAdminLayout title="System health">
        <PageHeader title="System health" />
        <p class="cd-page-context mt-3">Updated {{ valueLabel('generated_at', health.generated_at) }}</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <SurfaceCard v-for="(values, area) in sections" :key="area" :title="sentenceLabel(area)" :description="area === 'backup' && values.status === 'not_configured' ? 'No verified backup integration is configured.' : undefined">
                <dl v-if="typeof values === 'object' && values !== null" class="space-y-3">
                    <div v-for="(value, label) in values" :key="label" class="flex items-start justify-between gap-4 border-b border-[var(--border-subtle)] pb-2 last:border-0">
                        <dt class="text-sm text-[var(--text-muted)]">{{ sentenceLabel(label) }}</dt>
                        <dd class="text-right text-sm font-semibold text-[var(--text-strong)]">{{ valueLabel(label, value) }}</dd>
                    </div>
                </dl>
                <p v-else class="text-sm font-semibold">{{ values }}</p>
            </SurfaceCard>
        </div>
    </PlatformAdminLayout>
</template>
