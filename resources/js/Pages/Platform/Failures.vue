<script setup>
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

defineProps({ failures: Object, businessId: String });
</script>

<template>
    <PlatformAdminLayout title="Failure recovery">
        <PageHeader eyebrow="Internal operations" title="Failure recovery" description="Inspect redacted provider and delivery failures. Only reviewed, idempotent adapters can be replayed; generic failed jobs remain inspection-only." />
        <p v-if="businessId" class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">Filtered to business {{ businessId }}.</p>
        <div class="mt-6 grid gap-4 xl:grid-cols-2">
            <SurfaceCard v-for="(rows, type) in failures" :key="type" :padding="false" :title="String(type).replaceAll('_', ' ')" description="Sensitive payloads, message bodies, destinations, and exceptions are withheld.">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    <li v-for="row in rows" :key="row.id ?? row.uuid" class="px-5 py-4">
                        <p class="font-semibold text-[var(--text-strong)]">{{ row.event_type ?? row.job_class ?? row.channel ?? 'Failed operation' }}</p>
                        <p class="mt-1 text-sm text-[var(--text-muted)]">ID {{ row.id ?? row.uuid }} · {{ row.provider ?? row.queue ?? row.status ?? 'failure' }}<span v-if="row.attempts !== undefined"> · {{ row.attempts }} attempts</span></p>
                    </li>
                    <li v-if="!rows.length" class="px-5 py-8 text-center text-sm text-[var(--text-muted)]">No failures in this category.</li>
                </ul>
            </SurfaceCard>
        </div>
    </PlatformAdminLayout>
</template>
