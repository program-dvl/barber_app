<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeftIcon, ClockIcon, MagnifyingGlassIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import DataTable from '@/Components/Product/DataTable.vue';
import SearchField from '@/Components/Product/SearchField.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ business: Object, filters: Object, events: Object });
const search = ref(props.filters.search || '');
const category = ref(props.filters.category || 'all');
let timer;
const refresh = () => router.get(route('business.activity.index', props.business.public_id), { search: search.value || undefined, category: category.value }, { preserveState: true, replace: true });
watch(search, () => { clearTimeout(timer); timer = setTimeout(refresh, 350); });
watch(category, refresh);
const formatDate = value => new Intl.DateTimeFormat(props.business.locale || undefined, { timeZone: props.business.time_zone, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const detail = event => event.summary || event.reason || '';
</script>

<template>
    <AppLayout title="Activity log" :business-label="business.name">
        <PageHeader title="Activity log">
            <template #actions><AppButton :href="route('business.team.index', business.public_id)" variant="secondary"><ArrowLeftIcon class="size-4" />Back to team</AppButton></template>
        </PageHeader>

        <SurfaceCard :padding="false">
            <div class="cd-record-toolbar">
                <SearchField v-model="search" label="Search activity" placeholder="Person, action or reason" />
                <label class="min-w-44"><span class="cd-filter-label">Category</span><AppSelect v-model="category" aria-label="Category"><option value="all">All activity</option><option value="access">Team & access</option><option value="appointments">Appointments</option><option value="clients">Clients</option><option value="payments">Payments</option><option value="configuration">Configuration</option></AppSelect></label>
                <p class="cd-record-count">{{ events.total }} {{ events.total === 1 ? 'event' : 'events' }}</p>
            </div>
            <div v-if="!events.data.length" class="cd-inline-empty"><ClockIcon class="mx-auto size-6 text-[var(--text-muted)]" /><p class="mt-3 font-semibold">No matching activity</p><p class="mt-1 text-sm text-[var(--text-muted)]">Try a broader search or another category.</p></div>
            <DataTable v-else caption="Activity log">
                <thead><tr><th scope="col">Event</th><th scope="col" class="cd-mobile-hidden">Person</th><th scope="col">Time</th></tr></thead>
                <tbody><tr v-for="event in events.data" :key="event.public_id">
                    <td class="cd-record-primary"><p class="cd-record-name">{{ event.label }}</p><p v-if="detail(event)" class="cd-record-meta">{{ detail(event) }}</p><p v-if="event.reason && event.reason !== detail(event)" class="cd-record-meta">Reason: {{ event.reason }}</p><p class="cd-record-meta md:hidden">{{ event.actor }}</p></td>
                    <td class="cd-mobile-hidden"><p class="font-medium">{{ event.actor }}</p><p v-if="event.actor_email" class="cd-record-meta">{{ event.actor_email }}</p></td>
                    <td><time class="text-xs text-[var(--text-muted)]" :datetime="event.occurred_at">{{ formatDate(event.occurred_at) }}</time></td>
                </tr></tbody>
            </DataTable>
            <nav v-if="events.links?.length > 3" class="cd-record-pagination" aria-label="Activity pages"><template v-for="link in events.links" :key="link.label"><AppButton v-if="link.url" :href="link.url" size="small" :variant="link.active ? 'primary' : 'secondary'" v-html="link.label" /><span v-else class="px-2 py-2 text-sm text-[var(--text-muted)]" v-html="link.label"></span></template></nav>
        </SurfaceCard>
    </AppLayout>
</template>
