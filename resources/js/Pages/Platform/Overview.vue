<script setup>
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
</script>

<template>
    <PlatformAdminLayout title="Platform operations overview">
        <PageHeader title="Platform overview" />
        <SurfaceCard class="mt-6" :padding="false" title="Operations readiness">
            <ul class="divide-y divide-[var(--border-subtle)]"><li class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-center"><div><h2 class="font-semibold text-[var(--text-strong)]">Business operations</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Search businesses and manage their accounts, subscriptions and exports according to your role.</p></div><span class="cd-status w-fit bg-[var(--status-success-soft)] text-[var(--status-success)]">Available</span></li><li class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-center"><div><h2 class="font-semibold text-[var(--text-strong)]">System health</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Queue, webhook, communication, and reconciliation summaries are available; backups are not configured.</p></div><span class="cd-status w-fit bg-[var(--status-warning-soft)] text-[var(--status-warning)]">Live checks pending</span></li><li class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-center"><div><h2 class="font-semibold text-[var(--text-strong)]">Support access</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Support sessions require business and platform approval, a ticket and an expiry time.</p></div><span class="cd-status w-fit bg-[var(--status-success-soft)] text-[var(--status-success)]">Available</span></li></ul>
        </SurfaceCard>
    </PlatformAdminLayout>
</template>
