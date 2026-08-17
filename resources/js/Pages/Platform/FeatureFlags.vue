<script setup>
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

defineProps({ flags: Array });
</script>

<template>
    <PlatformAdminLayout title="Feature flags">
        <PageHeader eyebrow="Platform administrator" title="Feature flags" description="Application-owned flags with explicit global or single-business scope and an audited reason." />
        <SurfaceCard class="mt-6" :padding="false" title="Configured flags" description="Mutations use the protected platform API and require an administrator role.">
            <ul class="divide-y divide-[var(--border-subtle)]">
                <li v-for="flag in flags" :key="flag.public_id" class="grid gap-3 px-5 py-4 md:grid-cols-[minmax(0,1fr)_12rem_8rem] md:items-center">
                    <div><p class="font-semibold text-[var(--text-strong)]">{{ flag.key }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ flag.description }}</p></div>
                    <p class="text-sm">{{ flag.scope_type }}<span v-if="flag.scope_id"> · {{ flag.scope_id }}</span></p>
                    <span :class="['gh-status w-fit', flag.enabled ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--surface-muted)] text-[var(--text-muted)]']">{{ flag.enabled ? 'Enabled' : 'Disabled' }}</span>
                </li>
                <li v-if="!flags.length" class="px-5 py-10 text-center text-sm text-[var(--text-muted)]">No feature flags are configured.</li>
            </ul>
        </SurfaceCard>
    </PlatformAdminLayout>
</template>
