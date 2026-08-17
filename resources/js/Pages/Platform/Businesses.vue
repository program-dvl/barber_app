<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

const props = defineProps({ businesses: Array, search: String });
const query = ref(props.search ?? '');
const submit = () => router.get(route('platform.businesses.index'), { search: query.value }, { preserveState: true, replace: true });
</script>

<template>
    <PlatformAdminLayout title="Businesses">
        <PageHeader eyebrow="Internal operations" title="Businesses" description="Search safe account, onboarding, plan, usage, subscription, and owner summaries. Tenant records remain unavailable without scoped support access." />
        <form class="mt-6 flex max-w-2xl gap-2" role="search" @submit.prevent="submit">
            <label class="ds-sr-only" for="platform-business-search">Search businesses</label>
            <input id="platform-business-search" v-model="query" class="min-h-11 flex-1 rounded-lg border border-[var(--border-default)] bg-white px-3" placeholder="Business name, slug, or exact public ID" />
            <button class="min-h-11 rounded-lg bg-[var(--brand-pine)] px-5 font-semibold text-white" type="submit">Search</button>
        </form>
        <SurfaceCard class="mt-6" :padding="false" title="Safe business summaries" description="No client notes, message bodies, authentication data, or provider secrets are included.">
            <ul class="divide-y divide-[var(--border-subtle)]">
                <li v-for="business in businesses" :key="business.public_id" class="grid gap-4 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_12rem_14rem]">
                    <div><p class="font-semibold text-[var(--text-strong)]">{{ business.name }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ business.public_id }} · {{ business.owner?.name ?? 'Owner unavailable' }} · {{ business.owner?.verified ? 'Verified' : 'Unverified' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">Plan</p><p class="mt-1 text-sm">{{ business.subscription?.plan ?? 'No plan' }} · {{ business.subscription?.status ?? 'No subscription' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">Usage</p><p class="mt-1 text-sm">{{ business.usage.locations }} locations · {{ business.usage.active_staff }} active staff</p></div>
                </li>
                <li v-if="!businesses.length" class="px-5 py-10 text-center text-sm text-[var(--text-muted)]">No businesses match this search.</li>
            </ul>
        </SurfaceCard>
    </PlatformAdminLayout>
</template>
