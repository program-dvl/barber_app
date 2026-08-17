<script setup>
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
</script>

<template>
    <PlatformAdminLayout title="Platform operations overview">
        <PageHeader eyebrow="Internal operations" title="Platform operations" description="Review which administrative capabilities are available before taking action across businesses or providers." />
        <SurfaceCard class="mt-6" :padding="false" title="Operations readiness" description="Platform access never grants ordinary access to a business’s client or appointment records.">
            <ul class="divide-y divide-[var(--border-subtle)]"><li class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-center"><div><h2 class="font-semibold text-[var(--text-strong)]">Business operations</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Safe search, lifecycle, subscription, notices, flags, notes, and single-tenant export initiation are role controlled.</p></div><span class="gh-status w-fit bg-[var(--status-success-soft)] text-[var(--status-success)]">Available</span></li><li class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-center"><div><h2 class="font-semibold text-[var(--text-strong)]">System health</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Queue, webhook, communication, and reconciliation summaries are available; backup remains honestly unconfigured.</p></div><span class="gh-status w-fit bg-[var(--status-warning-soft)] text-[var(--status-warning)]">Partial external evidence</span></li><li class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-center"><div><h2 class="font-semibold text-[var(--text-strong)]">Support access</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Dual-approved, ticketed, scoped, expiring sessions retain operator identity and show a tenant banner.</p></div><span class="gh-status w-fit bg-[var(--status-success-soft)] text-[var(--status-success)]">Available</span></li></ul>
        </SurfaceCard>
    </PlatformAdminLayout>
</template>
