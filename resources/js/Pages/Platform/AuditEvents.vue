<script setup>
import { sentenceLabel } from '@/Support/userLanguage';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

defineProps({ events: Array });
</script>

<template>
    <PlatformAdminLayout title="Audit log">
        <PageHeader title="Audit log" />
        <SurfaceCard class="mt-6" :padding="false" title="Latest events">
            <ul class="divide-y divide-[var(--border-subtle)]">
                <li v-for="event in events" :key="event.public_id" class="grid gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1fr)_12rem_14rem]">
                    <div><p class="font-semibold text-[var(--text-strong)]">{{ event.label }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ event.summary || event.reason || '' }}</p></div>
                    <p class="text-sm">{{ event.actor_name }}<br>{{ sentenceLabel(event.actor_platform_role ?? event.source) }}</p>
                    <p class="text-sm text-[var(--text-muted)]">{{ event.business_name }}<br>{{ new Intl.DateTimeFormat(undefined, { timeZone: event.time_zone, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(event.occurred_at)) }}</p>
                </li>
                <li v-if="!events.length" class="px-5 py-6 text-center text-sm text-[var(--text-muted)]">No audit events are available.</li>
            </ul>
        </SurfaceCard>
    </PlatformAdminLayout>
</template>
