<script setup>
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

defineProps({ events: Array });
</script>

<template>
    <PlatformAdminLayout title="Audit events">
        <PageHeader eyebrow="Immutable evidence" title="Platform audit events" description="Review operator identity, role, business lineage, action, reason, correlation, and occurrence time without exposing protected payloads." />
        <SurfaceCard class="mt-6" :padding="false" title="Latest events">
            <ul class="divide-y divide-[var(--border-subtle)]">
                <li v-for="event in events" :key="event.public_id" class="grid gap-3 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_12rem_14rem]">
                    <div><p class="font-semibold text-[var(--text-strong)]">{{ event.action }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ event.reason ?? 'No operator reason recorded' }}</p></div>
                    <p class="text-sm">Actor {{ event.actor_user_id ?? 'system' }}<br>{{ event.actor_platform_role ?? event.source }}</p>
                    <p class="text-sm text-[var(--text-muted)]">Business {{ event.business_id ?? 'global' }}<br>{{ event.occurred_at }}</p>
                </li>
                <li v-if="!events.length" class="px-5 py-10 text-center text-sm text-[var(--text-muted)]">No audit events are available.</li>
            </ul>
        </SurfaceCard>
    </PlatformAdminLayout>
</template>
