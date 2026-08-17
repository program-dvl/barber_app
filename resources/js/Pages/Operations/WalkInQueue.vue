<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { BellAlertIcon, ClockIcon, QueueListIcon, UserPlusIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import FormField from '@/Components/Product/FormField.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ businessLabel: String, location: Object, locations: Array, services: Array, staff: Array, entries: Array, canReorder: Boolean, bookingIntervalMinutes: Number });
const addDialog = ref(null); const leaveDialog = ref(null); const reorderDialog = ref(null); const activeEntry = ref(null);
const inputClass = 'min-h-11 w-full rounded-lg border border-[var(--border-strong)] bg-white px-3 text-sm';
const waiting = computed(() => props.entries.filter(entry => entry.status !== 'in_service'));
const inService = computed(() => props.entries.filter(entry => entry.status === 'in_service'));
const localInputNow = () => {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
};
const alignedLocalInputNow = () => {
    const interval = Math.max(1, props.bookingIntervalMinutes || 15);
    const now = new Date();
    const remainder = now.getMinutes() % interval;
    const increment = remainder === 0 && now.getSeconds() === 0 ? 0 : interval - remainder;
    now.setMinutes(now.getMinutes() + increment, 0, 0);

    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
};
const localTime = value => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: props.location.time_zone }).format(new Date(value));
const addForm = useForm({ location: props.location.public_id, service: props.services[0]?.public_id || '', preferred_staff: '', client_name: '', client_mobile: '', arrived_at: localInputNow(), notes: '' });
const add = () => addForm.post(route('business.walk-ins.store', route().params.business), { preserveScroll: true, onSuccess: () => addForm.reset('client_name', 'client_mobile', 'notes') });
const assign = (entry, staffId) => router.patch(route('business.walk-ins.assign', [route().params.business, entry.public_id]), { staff: staffId, version: entry.version }, { preserveScroll: true });
const notify = entry => router.post(route('business.walk-ins.notify', [route().params.business, entry.public_id]), { version: entry.version }, { preserveScroll: true });
const start = entry => router.post(route('business.walk-ins.start', [route().params.business, entry.public_id]), { starts_at: alignedLocalInputNow(), staff: entry.assigned_staff_id || entry.preferred_staff_id, version: entry.version, idempotency_key: `walk-in-start-${entry.public_id}-${crypto.randomUUID()}` }, { preserveScroll: true });
const leaveForm = useForm({ version: 1, reason: '', confirmed: true });
const openLeave = entry => { activeEntry.value = entry; leaveForm.version = entry.version; leaveForm.reason = ''; leaveDialog.value?.open(); };
const leave = () => leaveForm.post(route('business.walk-ins.leave', [route().params.business, activeEntry.value.public_id]), { preserveScroll: true });
const reorderForm = useForm({ location: props.location.public_id, entries: waiting.value.map(entry => entry.public_id), reason: '', confirmed: true });
const move = (index, delta) => { const next = index + delta; if (next < 0 || next >= reorderForm.entries.length) return; [reorderForm.entries[index], reorderForm.entries[next]] = [reorderForm.entries[next], reorderForm.entries[index]]; };
const reorder = () => reorderForm.post(route('business.walk-ins.reorder', route().params.business), { preserveScroll: true });
</script>

<template>
    <AppLayout title="Walk-in queue" :business-label="businessLabel">
        <PageHeader eyebrow="Front desk" title="Walk-in queue" :description="`${location.name} · estimates include queued demand, current capacity, and future appointments`">
            <template #actions><AppButton v-if="canReorder && waiting.length > 1" variant="secondary" @click="reorderDialog?.open()">Reorder queue</AppButton><AppButton @click="addDialog?.open()"><UserPlusIcon class="size-5" aria-hidden="true" />Add walk-in</AppButton></template>
        </PageHeader>
        <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <SurfaceCard :padding="false" title="Waiting now" :description="`${waiting.length} ${waiting.length === 1 ? 'person' : 'people'} waiting`">
                <StatePanel v-if="waiting.length === 0" tone="success" title="The queue is clear" description="New arrivals will appear here with an evidence-based estimate." />
                <ol v-else class="divide-y divide-[var(--border-subtle)]">
                    <li v-for="entry in waiting" :key="entry.public_id" class="p-4 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="flex gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-full bg-[var(--brand-pine)] text-lg font-bold text-white" :aria-label="`Queue position ${entry.queue_position}`">{{ entry.queue_position }}</span><div><div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold text-[var(--text-strong)]">{{ entry.client_name }}</h2><span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-900">{{ entry.status }}</span></div><p class="mt-1 flex items-center gap-1 text-sm text-[var(--text-muted)]"><ClockIcon class="size-4" aria-hidden="true" />{{ entry.estimated_wait_minutes === null ? 'No safe slot found today' : `About ${entry.estimated_wait_minutes} min · expected ${localTime(entry.estimated_service_at)}` }}</p><details class="mt-2 text-xs text-[var(--text-muted)]"><summary class="min-h-11 cursor-pointer py-3 font-semibold">Why this estimate?</summary><p>{{ entry.estimate_evidence.queue_entries_ahead }} ahead · {{ entry.estimate_evidence.service_duration_minutes }}-minute service · future appointments and staff capacity checked.</p></details></div></div>
                            <div class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-56"><select :value="entry.assigned_staff_id || entry.preferred_staff_id || ''" :class="inputClass" aria-label="Assign staff" @change="assign(entry, $event.target.value)"><option value="" disabled>Assign staff</option><option v-for="member in staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></select><AppButton class="w-full" :disabled="!entry.assigned_staff_id && !entry.preferred_staff_id" @click="start(entry)">Start service</AppButton><div class="flex gap-2"><AppButton class="flex-1" variant="secondary" @click="notify(entry)"><BellAlertIcon class="size-5" aria-hidden="true" />Notify</AppButton><AppButton class="flex-1" variant="quiet" @click="openLeave(entry)">Remove</AppButton></div></div>
                        </div>
                    </li>
                </ol>
            </SurfaceCard>
            <div class="space-y-5"><SurfaceCard title="How estimates work" compact><p class="text-sm leading-6 text-[var(--text-muted)]">Wait times include the people ahead, the requested service, assigned staff, breaks, and upcoming bookings. Capacity is checked again before service starts.</p></SurfaceCard><SurfaceCard title="In service" compact><p v-if="inService.length === 0" class="text-sm text-[var(--text-muted)]">No walk-in service underway.</p><ul v-else class="divide-y divide-[var(--border-subtle)]"><li v-for="entry in inService" :key="entry.public_id" class="flex items-center gap-2 py-3 font-semibold"><span class="size-2 rounded-full bg-[var(--status-success)]" aria-hidden="true" />{{ entry.client_name }}</li></ul></SurfaceCard></div>
        </div>

        <AppDialog id="add-walk-in" ref="addDialog" title="Add walk-in" description="We will calculate a wait estimate before placing this person in the queue." confirm-label="Add to queue" @confirm="add"><div class="space-y-4"><FormField id="walkin-name" label="Name" required><input id="walkin-name" v-model="addForm.client_name" :class="inputClass" /></FormField><FormField id="walkin-mobile" label="Mobile number" required><input id="walkin-mobile" v-model="addForm.client_mobile" inputmode="tel" :class="inputClass" /></FormField><FormField id="walkin-service" label="Requested service" required><select id="walkin-service" v-model="addForm.service" :class="inputClass"><option v-for="service in services" :key="service.public_id" :value="service.public_id">{{ service.name }} · {{ service.duration_minutes }} min</option></select></FormField><FormField id="walkin-staff" label="Preferred staff"><select id="walkin-staff" v-model="addForm.preferred_staff" :class="inputClass"><option value="">First available</option><option v-for="member in staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></select></FormField><FormField id="walkin-arrival" label="Arrival time"><input id="walkin-arrival" v-model="addForm.arrived_at" type="datetime-local" :class="inputClass" /></FormField><FormField id="walkin-notes" label="Internal note"><textarea id="walkin-notes" v-model="addForm.notes" rows="3" :class="inputClass" /></FormField></div></AppDialog>
        <AppDialog id="leave-walk-in" ref="leaveDialog" title="Remove from the queue?" description="Actual wait and abandonment time will be preserved for reporting." confirm-label="Mark as left" destructive @confirm="leave"><FormField id="leave-reason" label="Reason" required><textarea id="leave-reason" v-model="leaveForm.reason" rows="3" :class="inputClass" /></FormField></AppDialog>
        <AppDialog id="reorder-walk-ins" ref="reorderDialog" title="Reorder the queue" description="Manager-controlled changes require a reason and remain visible in history." confirm-label="Save order" @confirm="reorder"><ol class="space-y-2"><li v-for="(publicId, index) in reorderForm.entries" :key="publicId" class="flex items-center justify-between rounded-lg bg-[var(--surface-subtle)] p-2"><span class="font-semibold">{{ index + 1 }}. {{ waiting.find(entry => entry.public_id === publicId)?.client_name }}</span><div><button type="button" class="size-11" aria-label="Move up" @click="move(index, -1)">↑</button><button type="button" class="size-11" aria-label="Move down" @click="move(index, 1)">↓</button></div></li></ol><FormField id="reorder-reason" class="mt-4" label="Reason" required><textarea id="reorder-reason" v-model="reorderForm.reason" rows="3" :class="inputClass" /></FormField></AppDialog>
    </AppLayout>
</template>
