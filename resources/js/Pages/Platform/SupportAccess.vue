<script setup>
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

defineProps({ grants: Array });
</script>

<template>
    <PlatformAdminLayout title="Support access">
        <PageHeader eyebrow="Identity retained" title="Support access" description="Ticketed, dual-approved, single-business grants. Every session has explicit scopes and expiry and remains visible to the business." />
        <SurfaceCard class="mt-6" :padding="false" title="Recent grants" description="A grant never creates a tenant membership or hides the operator’s identity.">
            <ul class="divide-y divide-[var(--border-subtle)]">
                <li v-for="grant in grants" :key="grant.public_id" class="grid gap-3 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_14rem_10rem]">
                    <div><p class="font-semibold text-[var(--text-strong)]">{{ grant.business.name }} · {{ grant.ticket_reference }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ grant.operator.name }} · {{ grant.reason }}</p><p class="mt-2 text-xs text-[var(--text-muted)]">Scopes: {{ grant.scopes.join(', ') }}</p></div>
                    <p class="text-sm">Expires<br><span class="font-semibold">{{ grant.expires_at }}</span></p>
                    <span :class="['gh-status h-fit w-fit', grant.active ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--surface-muted)] text-[var(--text-muted)]']">{{ grant.active ? 'Active' : 'Inactive' }}</span>
                </li>
                <li v-if="!grants.length" class="px-5 py-10 text-center text-sm text-[var(--text-muted)]">No support access grants have been issued.</li>
            </ul>
        </SurfaceCard>
    </PlatformAdminLayout>
</template>
