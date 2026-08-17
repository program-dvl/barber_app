<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ businessLabel: String, clients: Object, filters: Object, duplicateCount: Number, canContact: Boolean });
const page = usePage();
const search = ref(props.filters.search ?? '');
const runSearch = () => router.get(route('business.clients.index', page.props.tenant.public_id), { search: search.value }, { preserveState: true, replace: true });
</script>

<template>
    <AppLayout title="Clients" :business-label="businessLabel">
        <PageHeader eyebrow="Client records" title="Clients" description="Find the right person quickly, then review visit context, preferences, forms, and consent in one place." />

        <div class="mt-6">
            <SurfaceCard title="Find a client" :description="canContact ? 'Search by name, mobile number, or email.' : 'Search by client name.'">
                <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="runSearch">
                    <label class="min-w-0 flex-1">
                        <span class="ds-sr-only">Search clients</span>
                        <input v-model="search" type="search" class="gh-input" :placeholder="canContact ? 'Name, mobile number, or email' : 'Client name'" autocomplete="off">
                    </label>
                    <AppButton type="submit">Search</AppButton>
                </form>
                <p v-if="duplicateCount" class="mt-4 rounded-lg bg-[var(--status-warning-soft)] px-3 py-2 text-sm text-[var(--status-warning)]"><strong>{{ duplicateCount }}</strong> possible duplicate{{ duplicateCount === 1 ? '' : 's' }} need review. Good Hours never merges client records automatically.</p>
            </SurfaceCard>
        </div>

        <SurfaceCard class="mt-6" title="Client directory" :description="`${clients.total} active client records`">
            <StatePanel v-if="clients.data.length === 0" title="No matching clients" description="Bookings create client records automatically using conservative identity rules." />
            <ul v-else class="divide-y divide-[var(--border-subtle)]">
                <li v-for="client in clients.data" :key="client.public_id">
                    <Link :href="route('business.clients.show', [$page.props.tenant.public_id, client.public_id])" class="grid min-h-16 gap-1 px-1 py-4 hover:bg-[var(--surface-subtle)] sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.6fr)_8rem] sm:items-center sm:px-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-[var(--text-strong)]">{{ client.name }}</p>
                            <p v-if="canContact" class="truncate text-sm text-[var(--text-muted)]">{{ client.mobile || client.email || 'No contact details yet' }}</p>
                        </div>
                        <p class="text-sm text-[var(--text-muted)]"><span v-if="client.marketing_status !== 'unknown'" class="gh-status bg-[var(--surface-subtle)] text-[var(--text-default)]">Marketing {{ client.marketing_status.replace('_', ' ') }}</span></p>
                        <p class="text-sm font-medium sm:text-right">{{ client.visit_count }} {{ client.visit_count === 1 ? 'visit' : 'visits' }}</p>
                    </Link>
                </li>
            </ul>
            <nav v-if="clients.last_page > 1" class="mt-4 flex flex-wrap gap-2" aria-label="Client pages">
                <Link v-for="link in clients.links" :key="link.label" :href="link.url || '#'" preserve-state :class="['min-h-11 rounded-lg px-4 py-3 text-sm', link.active ? 'bg-[var(--action-primary)] text-white' : 'bg-[var(--surface-subtle)]', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" />
            </nav>
        </SurfaceCard>
    </AppLayout>
</template>
