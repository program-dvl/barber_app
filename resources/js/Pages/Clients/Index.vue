<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ businessLabel: String, clients: Object, filters: Object, duplicateCount: Number, canContact: Boolean, canCreate: Boolean, countries: Object });
const page = usePage();
const search = ref(props.filters.search ?? '');
const addDialog = ref(null);
const createForm = useForm({ name: '', mobile: '', email: '', referral_source: 'Front desk' });
const runSearch = () => router.get(route('business.clients.index', page.props.tenant.public_id), { search: search.value }, { preserveState: true, replace: true });
const createClient = () => createForm.post(route('business.clients.store', page.props.tenant.public_id), {
    preserveScroll: true,
    onSuccess: () => { addDialog.value?.close(); createForm.reset(); },
});
</script>

<template>
    <AppLayout title="Clients" :business-label="businessLabel">
        <PageHeader eyebrow="Client records" title="Clients" description="Find a client, prepare for their visit and keep their history close.">
            <template #actions><AppButton v-if="canCreate" @click="addDialog?.open()">Add client</AppButton></template>
        </PageHeader>

        <SurfaceCard class="mt-6" title="Client directory" :description="`${clients.total} active client ${clients.total === 1 ? 'record' : 'records'}`">
                <form class="mb-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="runSearch">
                    <label class="min-w-0 flex-1">
                        <span class="ds-sr-only">Search clients</span>
                        <input v-model="search" type="search" class="cd-input" :placeholder="canContact ? 'Name, mobile number, or email' : 'Client name'" autocomplete="off">
                    </label>
                    <AppButton type="submit" variant="secondary">Search</AppButton>
                </form>
                <p v-if="duplicateCount" class="mt-4 rounded-lg bg-[var(--status-warning-soft)] px-3 py-2 text-sm text-[var(--status-warning)]"><strong>{{ duplicateCount }}</strong> possible duplicate{{ duplicateCount === 1 ? '' : 's' }} need review. ClipperDesk never merges client records automatically.</p>

            <StatePanel v-if="clients.data.length === 0" :title="search ? 'No matching clients' : 'Your client list starts here'" :description="search ? 'Try another name or clear the search to see all clients.' : canCreate ? 'Add a walk-in, phone-booking, or migrated customer now. Online bookings also create client records automatically.' : 'Bookings create client records automatically using conservative identity rules.'"><template #actions><AppButton v-if="search" variant="secondary" @click="search = ''; runSearch()">Clear search</AppButton><AppButton v-else-if="canCreate" @click="addDialog?.open()">Add your first client</AppButton></template></StatePanel>
            <ul v-else class="divide-y divide-[var(--border-subtle)]">
                <li v-for="client in clients.data" :key="client.public_id">
                    <Link :href="route('business.clients.show', [$page.props.tenant.public_id, client.public_id])" class="grid min-h-16 gap-1 px-1 py-3 hover:bg-[var(--surface-subtle)] sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.6fr)_8rem] sm:items-center sm:px-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-[var(--text-strong)]">{{ client.name }}</p>
                            <p v-if="canContact" class="truncate text-sm text-[var(--text-muted)]">{{ client.mobile || client.email || 'No contact details yet' }}</p>
                        </div>
                        <p class="text-sm text-[var(--text-muted)]"><span v-if="client.marketing_status !== 'unknown'" class="cd-status bg-[var(--surface-subtle)] text-[var(--text-default)]">Marketing {{ client.marketing_status.replace('_', ' ') }}</span></p>
                        <p class="text-sm font-medium sm:text-right">{{ client.visit_count }} {{ client.visit_count === 1 ? 'visit' : 'visits' }}</p>
                    </Link>
                </li>
            </ul>
            <nav v-if="clients.last_page > 1" class="mt-4 flex flex-wrap gap-2" aria-label="Client pages">
                <component v-for="link in clients.links" :key="link.label" :is="link.url ? Link : 'span'" :href="link.url || undefined" :aria-current="link.active ? 'page' : undefined" :aria-disabled="!link.url || undefined" preserve-state :class="['min-h-11 rounded-lg px-4 py-3 text-sm', link.active ? 'bg-[var(--action-primary)] text-white' : 'bg-[var(--surface-subtle)]', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" />
            </nav>
        </SurfaceCard>

        <AppDialog id="add-client" ref="addDialog" title="Add a client" description="Create the reusable client record first; booking, preferences and service notes can be added next." :confirm-label="createForm.processing ? 'Adding client…' : 'Add client'" :confirm-disabled="createForm.processing" :close-on-confirm="false" @confirm="createClient">
            <form class="space-y-4" @submit.prevent="createClient">
                <label class="block text-sm font-semibold">Full name<input v-model="createForm.name" required autocomplete="name" class="cd-input mt-2"><span v-if="createForm.errors.name" class="mt-1 block text-xs text-[var(--status-danger)]">{{ createForm.errors.name }}</span></label>
                <label class="block text-sm font-semibold">Mobile number <span class="font-normal text-[var(--text-muted)]">(or provide email)</span><PhoneInput id="new-client-mobile" v-model="createForm.mobile" class="mt-2" :country="page.props.tenant?.regional?.country_code || 'IN'" :countries="countries" /><span v-if="createForm.errors.mobile" class="mt-1 block text-xs text-[var(--status-danger)]">{{ createForm.errors.mobile }}</span></label>
                <label class="block text-sm font-semibold">Email <span class="font-normal text-[var(--text-muted)]">(or provide mobile)</span><input v-model="createForm.email" type="email" autocomplete="email" class="cd-input mt-2"><span v-if="createForm.errors.email" class="mt-1 block text-xs text-[var(--status-danger)]">{{ createForm.errors.email }}</span></label>
                <label class="block text-sm font-semibold">How they found you <span class="font-normal text-[var(--text-muted)]">(optional)</span><input v-model="createForm.referral_source" class="cd-input mt-2" placeholder="Walk-in, phone booking, imported list…"></label>
                <p class="rounded-lg bg-[var(--surface-subtle)] p-3 text-xs leading-5 text-[var(--text-muted)]">If the same name and contact already exist, ClipperDesk opens that record instead of creating a duplicate.</p>
            </form>
        </AppDialog>
    </AppLayout>
</template>
