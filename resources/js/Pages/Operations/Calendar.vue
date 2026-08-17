<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon, ArrowRightIcon, ClockIcon, EllipsisVerticalIcon, PrinterIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import FormField from '@/Components/Product/FormField.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    businessLabel: String,
    calendar: Object,
    filters: Object,
    options: Object,
    permissions: Object,
    bookingRules: Object,
});

const createDialog = ref(null);
const changeDialog = ref(null);
const cancelDialog = ref(null);
const noteDialog = ref(null);
const blockDialog = ref(null);
const exceptionDialog = ref(null);
const closureDialog = ref(null);
const activeEvent = ref(null);
const inputClass = 'min-h-11 w-full rounded-lg border border-[var(--border-strong)] bg-white px-3 text-sm';
const views = [{ id: 'today', label: 'Today' }, { id: 'day', label: 'Day' }, { id: 'week', label: 'Week' }, { id: 'staff', label: 'Staff columns' }];
const appointments = computed(() => props.calendar.events.filter(event => event.type === 'appointment'));
const auxiliaryEvents = computed(() => props.calendar.events.filter(event => event.type !== 'appointment'));
const localDateLabel = computed(() => new Intl.DateTimeFormat(undefined, { dateStyle: props.filters.view === 'week' ? 'medium' : 'full', timeZone: props.calendar.timeZone }).format(new Date(props.calendar.range.startsAt)));
const localTime = value => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: props.calendar.timeZone }).format(new Date(value));
const localHour = value => Number(new Intl.DateTimeFormat('en-GB', { hour: '2-digit', hour12: false, timeZone: props.calendar.timeZone }).format(new Date(value)));
const statusStyle = tone => ({
    warning: 'border-amber-300 bg-amber-50', info: 'border-sky-300 bg-sky-50', success: 'border-emerald-300 bg-emerald-50',
    strong: 'border-[var(--brand-pine)] bg-[var(--status-success-soft)]', danger: 'border-red-300 bg-red-50', neutral: 'border-slate-300 bg-slate-50',
}[tone] || 'border-slate-300 bg-white');

const applyFilters = overrides => router.get(route('business.calendar', route().params.business), { ...props.filters, ...overrides }, { preserveState: true, replace: true });
const shiftDate = amount => {
    const date = new Date(`${props.filters.date}T12:00:00`);
    date.setDate(date.getDate() + amount * (props.filters.view === 'week' ? 7 : 1));
    applyFilters({ date: date.toISOString().slice(0, 10) });
};

const zonedParts = value => Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
        timeZone: props.calendar.timeZone,
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false,
    }).formatToParts(value).filter(part => part.type !== 'literal').map(part => [part.type, part.value]),
);
const localInputInCalendarZone = value => {
    const parts = zonedParts(value);
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour === '24' ? '00' : parts.hour}:${parts.minute}`;
};
const selectedServiceNotice = () => Number(props.options.services.find(service => service.public_id === createForm.lines[0]?.service)?.minimum_notice_minutes || 0);
const firstAllowedStart = () => {
    const interval = Math.max(1, Number(props.bookingRules?.intervalMinutes || 15));
    const now = new Date(props.bookingRules?.serverNow || Date.now());
    const earliest = new Date(now.getTime() + selectedServiceNotice() * 60_000);
    const minutesToNextInterval = (interval - (earliest.getMinutes() % interval)) % interval;
    earliest.setMinutes(earliest.getMinutes() + minutesToNextInterval, 0, 0);
    const earliestInput = localInputInCalendarZone(earliest);
    const requestedMorning = `${props.filters.date}T09:00`;

    return earliestInput.slice(0, 10) > props.filters.date ? earliestInput : (earliestInput > requestedMorning ? earliestInput : requestedMorning);
};

const createForm = useForm({
    location: props.filters.location, starts_at: '', source: 'reception', client_name: '', client_mobile: '', internal_notes: '',
    lines: [{ service: props.options.services[0]?.public_id || '', staff: props.options.staff[0]?.public_id || '', duration_minutes: null }],
    idempotency_key: '', override_rule_codes: [], override_reason: '', override_confirmed: false,
});
const submitCreate = () => {
    const interval = Math.max(1, Number(props.bookingRules?.intervalMinutes || 15));
    const selectedMinute = Number(createForm.starts_at.slice(-2));
    if (!createForm.starts_at || selectedMinute % interval !== 0) {
        createForm.setError('booking', `Choose a time in ${interval}-minute booking intervals.`);
        return;
    }
    if (!createForm.override_confirmed && createForm.starts_at < firstAllowedStart()) {
        createForm.setError('booking', 'Choose a time at or after the minimum booking notice, or use the manager override when permitted.');
        return;
    }
    createForm.clearErrors('booking');
    createForm.idempotency_key ||= `calendar-create-${crypto.randomUUID()}`;
    createForm.post(route('business.appointments.store', route().params.business), { preserveScroll: true, onSuccess: () => createForm.reset('client_name', 'client_mobile', 'internal_notes') });
};
const addCreateLine = () => createForm.lines.push({ service: props.options.services[0]?.public_id || '', staff: '', duration_minutes: null });

const changeForm = useForm({
    kind: 'reschedule', location: props.filters.location, starts_at: '', lines: [], version: 1, reason: '', confirmed: true,
    idempotency_key: '', override_rule_codes: [], override_reason: '', override_confirmed: false, client_name: '', client_mobile: '', internal_notes: '',
});
const openChange = (event, kind = 'reschedule', startsAt = null) => {
    activeEvent.value = event;
    changeForm.kind = kind;
    changeForm.location = props.filters.location;
    changeForm.starts_at = (startsAt || event.startsAt).slice(0, 16);
    changeForm.lines = event.services.map(service => ({ service: service.id, staff: service.staffId || event.staff[0]?.id || '', duration_minutes: kind === 'resize' ? service.durationMinutes : null }));
    changeForm.version = event.version;
    changeForm.reason = '';
    changeForm.client_name = event.clientName || '';
    changeForm.client_mobile = event.clientMobile || '';
    changeForm.internal_notes = event.internalNotes || '';
    changeForm.idempotency_key = '';
    changeDialog.value?.open();
};
const addChangeLine = () => changeForm.lines.push({ service: props.options.services[0]?.public_id || '', staff: '', duration_minutes: null });
const submitChange = () => {
    changeForm.idempotency_key ||= `calendar-${changeForm.kind}-${crypto.randomUUID()}`;
    changeForm.override_rule_codes = changeForm.override_confirmed ? ['NOTICE_WINDOW'] : [];
    changeForm.override_reason = changeForm.override_confirmed ? changeForm.reason : '';
    changeForm.post(route('business.appointments.replace', [route().params.business, activeEvent.value.id]), { preserveScroll: true });
};

const nextStatus = status => ({ confirmed: 'arrived', late: 'arrived', arrived: 'checked_in', checked_in: 'in_service', in_service: 'completed' }[status]);
const statusLabel = status => ({ arrived: 'Mark arrived', checked_in: 'Check in', in_service: 'Start service', completed: 'Complete' }[status]);
const transition = (event, status, reason = null, confirmed = false) => router.patch(route('business.appointments.status', [route().params.business, event.id]), {
    status, version: event.version, reason, confirmed, idempotency_key: `status-${event.id}-${status}-${crypto.randomUUID()}`,
}, { preserveScroll: true });
const openCancel = event => { activeEvent.value = event; cancelForm.reason = ''; cancelDialog.value?.open(); };
const cancelForm = useForm({ status: 'cancelled_by_shop', version: 1, reason: '', confirmed: true, idempotency_key: '' });
const submitCancel = () => {
    cancelForm.version = activeEvent.value.version;
    cancelForm.idempotency_key = `cancel-${activeEvent.value.id}-${crypto.randomUUID()}`;
    cancelForm.patch(route('business.appointments.status', [route().params.business, activeEvent.value.id]), { preserveScroll: true });
};
const noteForm = useForm({ notes: '', version: 1, idempotency_key: '' });
const openNotes = event => { activeEvent.value = event; noteForm.notes = event.internalNotes || ''; noteForm.version = event.version; noteForm.idempotency_key = ''; noteDialog.value?.open(); };
const submitNotes = () => { noteForm.idempotency_key ||= `notes-${activeEvent.value.id}-${crypto.randomUUID()}`; noteForm.patch(route('business.appointments.notes', [route().params.business, activeEvent.value.id]), { preserveScroll: true }); };
const blockForm = useForm({ location: props.filters.location, staff: props.options.staff[0]?.public_id || '', kind: 'personal_block', label: '', reason: '', starts_at: `${props.filters.date}T12:00`, ends_at: `${props.filters.date}T12:30`, confirmed: true });
const submitBlock = () => blockForm.post(route('business.schedule-blocks.store', route().params.business), { preserveScroll: true });
const exceptionForm = useForm({ kind: 'service_overrun', reason: '', projected_end: '' });
const openException = event => { activeEvent.value = event; exceptionForm.reason = ''; exceptionForm.projected_end = event.endsAt.slice(0, 16); exceptionDialog.value?.open(); };
const submitException = () => exceptionForm.post(route('business.appointments.exceptions', [route().params.business, activeEvent.value.id]), { preserveScroll: true });
const closureForm = useForm({ location: props.filters.location, starts_at: `${props.filters.date}T09:00`, ends_at: `${props.filters.date}T18:00`, reason: '', confirmed: true });
const submitClosure = () => closureForm.post(route('business.operational-exceptions.closure', route().params.business), { preserveScroll: true });
const copyEvent = (event, kind) => router.post(route('business.appointments.copy', [route().params.business, event.id]), {
    kind, starts_at: event.startsAt, confirmed: true, idempotency_key: `${kind}-${event.id}-${crypto.randomUUID()}`,
}, { preserveScroll: true });
const onDrop = (event, hour) => {
    const id = event.dataTransfer.getData('text/calendar-event');
    const appointment = appointments.value.find(item => item.id === id);
    if (!appointment) return;
    const startsAt = `${props.filters.date}T${String(hour).padStart(2, '0')}:00`;
    openChange(appointment, 'reschedule', startsAt);
};
const hours = Array.from({ length: 13 }, (_, index) => index + 7);
onMounted(() => {
    createForm.starts_at = firstAllowedStart();
    if (new URL(window.location.href).searchParams.get('create') === '1') createDialog.value?.open();
});
</script>

<template>
    <AppLayout title="Calendar" :business-label="businessLabel">
        <PageHeader eyebrow="Front desk" title="Operational calendar" description="Run appointments, walk-ins, blocked time, and recoverable exceptions in the location’s local time.">
            <template #actions>
                <AppButton :href="route('business.calendar.print', { business: route().params.business, location: filters.location, date: filters.date })" variant="secondary"><PrinterIcon class="size-5" aria-hidden="true" />Print day</AppButton>
                <details v-if="permissions.manage || permissions.override" class="relative">
                    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center justify-center rounded-lg border border-[var(--border-strong)] bg-[var(--surface-raised)] px-4 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]">More</summary>
                    <div class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-1 shadow-[var(--shadow-overlay)]">
                        <button v-if="permissions.manage" type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="blockDialog?.open()">Block staff time</button>
                        <button v-if="permissions.override" type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold text-[var(--status-danger)] hover:bg-[var(--status-danger-soft)]" @click="closureDialog?.open()">Record unexpected closure</button>
                    </div>
                </details>
                <AppButton v-if="permissions.manage" @click="createDialog?.open()">New appointment</AppButton>
            </template>
        </PageHeader>

        <SurfaceCard class="mt-6" :description="`${calendar.timeZone} · Times shown in location time`" compact>
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <FormField id="calendar-location" label="Location"><select id="calendar-location" :value="filters.location" :class="inputClass" @change="applyFilters({ location: $event.target.value })"><option v-for="location in options.locations" :key="location.public_id" :value="location.public_id">{{ location.name }}</option></select></FormField>
                    <FormField id="calendar-date" label="Date"><input id="calendar-date" type="date" :value="filters.date" :class="inputClass" @change="applyFilters({ date: $event.target.value })" /></FormField>
                    <FormField id="calendar-staff" label="Staff"><select id="calendar-staff" :class="inputClass" :value="filters.staff?.[0] || ''" @change="applyFilters({ staff: $event.target.value ? [$event.target.value] : [] })"><option value="">All staff</option><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></select></FormField>
                    <FormField id="calendar-service" label="Service"><select id="calendar-service" :class="inputClass" :value="filters.service?.[0] || ''" @change="applyFilters({ service: $event.target.value ? [$event.target.value] : [] })"><option value="">All services</option><option v-for="service in options.services" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></select></FormField>
                    <FormField id="calendar-status" label="Status"><select id="calendar-status" :class="inputClass" :value="filters.status?.[0] || ''" @change="applyFilters({ status: $event.target.value ? [$event.target.value] : [] })"><option value="">All statuses</option><option v-for="status in ['confirmed','arrived','checked_in','in_service','completed','late','cancelled_by_client','cancelled_by_shop','no_show','rescheduled']" :key="status" :value="status">{{ status.replaceAll('_', ' ') }}</option></select></FormField>
                </div>
                <div class="flex gap-1 rounded-xl bg-[var(--surface-subtle)] p-1" aria-label="Calendar view">
                    <button v-for="view in views" :key="view.id" type="button" :aria-pressed="filters.view === view.id" :class="['min-h-11 rounded-lg px-3 text-sm font-semibold', filters.view === view.id ? 'bg-white text-[var(--action-primary)] shadow-sm' : 'text-[var(--text-muted)]']" @click="applyFilters({ view: view.id })">{{ view.label }}</button>
                </div>
            </div>
        </SurfaceCard>

        <div class="mt-5 flex items-center justify-between gap-3">
            <AppButton variant="quiet" aria-label="Previous date" @click="shiftDate(-1)"><ArrowLeftIcon class="size-5" aria-hidden="true" />Previous</AppButton>
            <div class="text-center"><p class="font-semibold text-[var(--text-strong)]">{{ localDateLabel }}</p><p class="text-xs text-[var(--text-muted)]">{{ calendar.counts.appointments }} appointments · {{ calendar.counts.walkInsWaiting }} waiting</p></div>
            <AppButton variant="quiet" aria-label="Next date" @click="shiftDate(1)">Next<ArrowRightIcon class="size-5" aria-hidden="true" /></AppButton>
        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <SurfaceCard :padding="false" title="Schedule" :description="`${calendar.counts.appointments} appointments · ${calendar.counts.walkInsWaiting} walk-ins waiting`">
                <StatePanel v-if="appointments.length === 0" tone="info" title="No appointments in this view" description="Change the filters or create a visit." />
                <div v-else-if="filters.view === 'staff'" class="overflow-x-auto p-4">
                    <div class="grid min-w-[52rem] gap-3" :style="{ gridTemplateColumns: `repeat(${Math.max(options.staff.length, 1)}, minmax(15rem, 1fr))` }">
                        <section v-for="member in options.staff" :key="member.public_id" class="rounded-xl bg-[var(--surface-subtle)] p-3" :aria-label="`${member.display_name} schedule`">
                            <h2 class="mb-3 font-semibold">{{ member.display_name }}</h2>
                            <article v-for="event in appointments.filter(item => item.staff.some(person => person.id === member.public_id))" :key="event.id" tabindex="0" draggable="true" :class="['mb-2 cursor-pointer rounded-xl border-l-4 p-3 shadow-sm', statusStyle(event.tone), activeEvent?.id === event.id ? 'ring-2 ring-[var(--focus-ring)] ring-offset-2' : '']" @click="activeEvent = event" @focus="activeEvent = event" @dragstart="$event.dataTransfer.setData('text/calendar-event', event.id)">
                                <p class="text-xs font-bold uppercase">{{ event.statusCue }}</p><h3 class="mt-1 font-semibold">{{ event.title }}</h3><p class="text-sm">{{ localTime(event.startsAt) }} · {{ event.services.map(item => item.name).join(', ') }}</p><p v-if="event.forms.requested" class="mt-1 text-xs font-semibold">Forms {{ event.forms.completed }}/{{ event.forms.requested }} complete</p>
                            </article>
                        </section>
                    </div>
                </div>
                <div v-else class="divide-y divide-[var(--border-subtle)]">
                    <div v-for="hour in hours" :key="hour" class="grid min-h-20 grid-cols-[4.5rem_1fr]" @dragover.prevent @drop="onDrop($event, hour)">
                        <div class="border-r border-[var(--border-subtle)] p-3 text-xs font-semibold text-[var(--text-muted)]">{{ String(hour).padStart(2, '0') }}:00</div>
                        <div class="grid gap-2 p-2 sm:grid-cols-2 lg:grid-cols-3">
                            <article v-for="event in appointments.filter(item => localHour(item.startsAt) === hour)" :key="event.id" tabindex="0" draggable="true" :class="['cursor-pointer rounded-xl border-l-4 p-3 shadow-sm', statusStyle(event.tone), activeEvent?.id === event.id ? 'ring-2 ring-[var(--focus-ring)] ring-offset-2' : '']" @click="activeEvent = event" @focus="activeEvent = event" @dragstart="$event.dataTransfer.setData('text/calendar-event', event.id)">
                                <div class="flex items-start justify-between gap-2"><div><p class="text-xs font-bold uppercase tracking-wide">{{ event.statusCue }}</p><h3 class="mt-1 font-semibold text-[var(--text-strong)]">{{ event.title }}</h3></div><span class="gh-status bg-white/70 text-[var(--text-default)]">{{ event.statusLabel }}</span></div>
                                <p class="mt-2 text-sm">{{ event.services.map(item => item.name).join(', ') }}</p><p class="mt-1 flex items-center gap-1 text-xs text-[var(--text-muted)]"><ClockIcon class="size-4" aria-hidden="true" />{{ localTime(event.startsAt) }}–{{ localTime(event.endsAt) }}</p>
                                <p v-if="event.forms.requested" :class="['mt-2 rounded-md px-2 py-1 text-xs font-semibold', event.forms.pending ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900']">Forms {{ event.forms.completed }}/{{ event.forms.requested }} complete</p>
                                <div v-if="permissions.manage" class="mt-3 flex items-start gap-2" @click.stop>
                                    <button v-if="nextStatus(event.status)" type="button" class="min-h-11 flex-1 rounded-lg bg-[var(--brand-pine)] px-3 text-xs font-semibold text-white hover:bg-[var(--brand-pine-deep)]" @click="transition(event, nextStatus(event.status))">{{ statusLabel(nextStatus(event.status)) }}</button>
                                    <button v-else type="button" class="min-h-11 flex-1 rounded-lg border border-[var(--border-strong)] bg-white px-3 text-xs font-semibold" @click="activeEvent = event">View details</button>
                                    <details class="min-w-0">
                                        <summary class="grid size-11 cursor-pointer list-none place-items-center rounded-lg border border-[var(--border-strong)] bg-white" aria-label="More appointment actions"><EllipsisVerticalIcon class="size-5" aria-hidden="true" /></summary>
                                        <div class="mt-2 grid grid-cols-2 gap-1 rounded-lg bg-white/80 p-1 text-left">
                                            <button type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="openChange(event)">Move</button><button type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="openChange(event, 'resize')">Resize</button><button type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="openChange(event, 'reassign')">Reassign</button><button type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="openChange(event, 'services_changed')">Services</button><button type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="openNotes(event)">Add note</button><button type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="copyEvent(event, 'duplicate')">Duplicate</button><button v-if="['completed','cancelled_by_client','cancelled_by_shop','no_show'].includes(event.status)" type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="copyEvent(event, 'rebook')">Rebook</button><button v-if="event.status === 'confirmed'" type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="transition(event, 'late', 'Client arrival delayed.')">Mark late</button><button v-if="['confirmed','late'].includes(event.status)" type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold text-[var(--status-danger)] hover:bg-[var(--status-danger-soft)]" @click="transition(event, 'no_show', 'Client did not attend.', true)">No-show</button><button v-if="['late','in_service'].includes(event.status)" type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold hover:bg-white" @click="openException(event)">Record impact</button><button type="button" class="min-h-10 rounded-md px-2 text-xs font-semibold text-[var(--status-danger)] hover:bg-[var(--status-danger-soft)]" @click="openCancel(event)">Cancel</button>
                                        </div>
                                    </details>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </SurfaceCard>

            <div class="space-y-5">
                <SurfaceCard v-if="activeEvent" title="Selected appointment" compact>
                    <p class="text-lg font-semibold text-[var(--text-strong)]">{{ activeEvent.title }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ localTime(activeEvent.startsAt) }}–{{ localTime(activeEvent.endsAt) }} · {{ activeEvent.services.map(item => item.name).join(', ') }}</p><p class="mt-3 text-sm"><strong>Staff:</strong> {{ activeEvent.staff.map(item => item.name).join(', ') || 'Not assigned' }}</p><p v-if="activeEvent.forms.requested" class="mt-2 text-sm"><strong>Client forms:</strong> {{ activeEvent.forms.completed }} of {{ activeEvent.forms.requested }} complete</p><AppButton v-if="permissions.manage && nextStatus(activeEvent.status)" class="mt-4 w-full" @click="transition(activeEvent, nextStatus(activeEvent.status))">{{ statusLabel(nextStatus(activeEvent.status)) }}</AppButton>
                </SurfaceCard>
                <SurfaceCard v-else title="Choose an appointment" compact><p class="text-sm leading-6 text-[var(--text-muted)]">Select a schedule card to see the client, service, staff, form status, and recommended next action.</p></SurfaceCard>
                <SurfaceCard title="Needs attention" description="No schedule changes happen automatically.">
                    <ul class="space-y-3 text-sm"><li><strong>{{ calendar.counts.walkInsWaiting }}</strong> walk-ins waiting</li><li><strong>{{ calendar.counts.unassigned }}</strong> unassigned visits</li><li><strong>{{ calendar.counts.blocks }}</strong> blocked periods</li></ul>
                </SurfaceCard>
                <SurfaceCard v-if="auxiliaryEvents.length" title="Queue & blocked time">
                    <ul class="space-y-3"><li v-for="event in auxiliaryEvents" :key="`${event.type}-${event.id}`" class="rounded-lg bg-[var(--surface-subtle)] p-3"><p class="text-xs font-bold uppercase">{{ event.statusCue }}</p><p class="font-semibold">{{ event.title }}</p><p class="text-xs text-[var(--text-muted)]">{{ event.statusLabel }}</p></li></ul>
                </SurfaceCard>
            </div>
        </div>

        <AppDialog id="create-appointment" ref="createDialog" title="Create appointment" description="Capacity is checked again when you save." confirm-label="Create appointment" @confirm="submitCreate">
            <div class="space-y-4"><p v-if="createForm.errors.booking" class="rounded-lg bg-[var(--status-warning-soft)] p-3 text-sm text-[var(--status-warning)]" role="alert">{{ createForm.errors.booking }}</p><FormField id="create-client" label="Client name"><input id="create-client" v-model="createForm.client_name" :class="inputClass" /></FormField><FormField id="create-mobile" label="Mobile"><input id="create-mobile" v-model="createForm.client_mobile" inputmode="tel" :class="inputClass" /></FormField><FormField id="create-start" label="Starts"><input id="create-start" v-model="createForm.starts_at" type="datetime-local" :step="Math.max(1, bookingRules?.intervalMinutes || 15) * 60" :class="inputClass" /></FormField><p class="-mt-2 text-xs text-[var(--text-muted)]">Times use {{ calendar.timeZone }} and follow {{ bookingRules?.intervalMinutes || 15 }}-minute intervals. The first valid time already respects the selected service’s booking notice.</p><div v-for="(line, index) in createForm.lines" :key="index" class="rounded-lg bg-[var(--surface-subtle)] p-3"><FormField :id="`create-service-${index}`" :label="`Service ${index + 1}`" required><select :id="`create-service-${index}`" v-model="line.service" :class="inputClass" @change="index === 0 && (createForm.starts_at = firstAllowedStart())"><option v-for="service in options.services" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></select></FormField><FormField :id="`create-staff-${index}`" class="mt-3" label="Staff"><select :id="`create-staff-${index}`" v-model="line.staff" :class="inputClass"><option value="">First available</option><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></select></FormField><button v-if="createForm.lines.length > 1" type="button" class="mt-2 min-h-11 text-sm font-semibold text-[var(--status-danger)]" @click="createForm.lines.splice(index, 1)">Remove service</button></div><AppButton variant="quiet" @click="addCreateLine">Add another service</AppButton></div>
        </AppDialog>

        <AppDialog id="change-appointment" ref="changeDialog" :title="`${changeForm.kind.replaceAll('_', ' ')} appointment`" description="The current record will become a linked Rescheduled history item. Capacity and qualifications are revalidated before commit." confirm-label="Confirm change" @confirm="submitChange">
            <div class="space-y-4"><FormField id="change-start" label="New start" required><input id="change-start" v-model="changeForm.starts_at" type="datetime-local" :class="inputClass" /></FormField><div v-for="(line, index) in changeForm.lines" :key="index" class="rounded-lg bg-[var(--surface-subtle)] p-3"><FormField :id="`change-service-${index}`" :label="`Service ${index + 1}`"><select :id="`change-service-${index}`" v-model="line.service" :class="inputClass"><option v-for="service in options.services" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></select></FormField><FormField :id="`change-staff-${index}`" class="mt-3" label="Staff"><select :id="`change-staff-${index}`" v-model="line.staff" :class="inputClass"><option value="">First available</option><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></select></FormField><FormField v-if="changeForm.kind === 'resize'" :id="`change-duration-${index}`" class="mt-3" label="Total service minutes"><input :id="`change-duration-${index}`" v-model.number="line.duration_minutes" type="number" min="5" max="720" step="5" :class="inputClass" /></FormField><button v-if="changeForm.lines.length > 1" type="button" class="mt-2 min-h-11 text-sm font-semibold text-[var(--status-danger)]" @click="changeForm.lines.splice(index, 1)">Remove service</button></div><AppButton v-if="changeForm.kind === 'services_changed'" variant="quiet" @click="addChangeLine">Add service</AppButton><FormField id="change-reason" label="Reason" required><textarea id="change-reason" v-model="changeForm.reason" rows="3" :class="inputClass" /></FormField><label v-if="permissions.override" class="flex gap-3 text-sm"><input v-model="changeForm.override_confirmed" type="checkbox" class="mt-1 size-5" /><span><strong>Manager policy override</strong><br><span class="text-[var(--text-muted)]">Only notice/advance policy may be overridden. Capacity and integrity conflicts never can.</span></span></label></div>
        </AppDialog>

        <AppDialog id="cancel-appointment" ref="cancelDialog" title="Cancel this appointment?" description="Capacity will be released and the reason preserved. This cannot erase history." confirm-label="Cancel appointment" destructive @confirm="submitCancel"><FormField id="cancel-reason" label="Reason" required><textarea id="cancel-reason" v-model="cancelForm.reason" rows="3" :class="inputClass" /></FormField></AppDialog>
        <AppDialog id="appointment-note" ref="noteDialog" title="Internal appointment note" description="Note changes preserve a content hash without copying sensitive text into audit summaries." confirm-label="Save note" @confirm="submitNotes"><FormField id="appointment-note-text" label="Note"><textarea id="appointment-note-text" v-model="noteForm.notes" rows="5" :class="inputClass" /></FormField></AppDialog>
        <AppDialog id="schedule-block" ref="blockDialog" title="Block staff time" description="Existing appointments and active holds cannot be overridden." confirm-label="Create block" @confirm="submitBlock"><div class="space-y-4"><FormField id="block-staff" label="Staff" required><select id="block-staff" v-model="blockForm.staff" :class="inputClass"><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></select></FormField><FormField id="block-kind" label="Type"><select id="block-kind" v-model="blockForm.kind" :class="inputClass"><option value="personal_block">Personal block</option><option value="staff_break">Staff break</option></select></FormField><FormField id="block-label" label="Calendar label" required><input id="block-label" v-model="blockForm.label" :class="inputClass" /></FormField><div class="grid grid-cols-2 gap-3"><FormField id="block-start" label="Starts"><input id="block-start" v-model="blockForm.starts_at" type="datetime-local" :class="inputClass" /></FormField><FormField id="block-end" label="Ends"><input id="block-end" v-model="blockForm.ends_at" type="datetime-local" :class="inputClass" /></FormField></div><FormField id="block-reason" label="Private reason" required><textarea id="block-reason" v-model="blockForm.reason" rows="3" :class="inputClass" /></FormField></div></AppDialog>
        <AppDialog id="operational-impact" ref="exceptionDialog" title="Record operational impact" description="This does not move future appointments. It creates an explicit recovery list and notification event." confirm-label="Record impact" @confirm="submitException"><div class="space-y-4"><FormField id="impact-kind" label="Exception"><select id="impact-kind" v-model="exceptionForm.kind" :class="inputClass"><option value="late_arrival">Late arrival</option><option value="service_overrun">Service overrun</option><option value="staff_unavailable">Staff unavailable</option></select></FormField><FormField id="impact-end" label="Projected end"><input id="impact-end" v-model="exceptionForm.projected_end" type="datetime-local" :class="inputClass" /></FormField><FormField id="impact-reason" label="Reason" required><textarea id="impact-reason" v-model="exceptionForm.reason" rows="3" :class="inputClass" /></FormField></div></AppDialog>
        <AppDialog id="unexpected-closure" ref="closureDialog" title="Record unexpected closure?" description="Every affected appointment will be listed for contact, reschedule, or cancellation. Nothing is changed automatically." confirm-label="Record closure" destructive @confirm="submitClosure"><div class="space-y-4"><div class="grid grid-cols-2 gap-3"><FormField id="closure-start" label="Starts"><input id="closure-start" v-model="closureForm.starts_at" type="datetime-local" :class="inputClass" /></FormField><FormField id="closure-end" label="Ends"><input id="closure-end" v-model="closureForm.ends_at" type="datetime-local" :class="inputClass" /></FormField></div><FormField id="closure-reason" label="Reason" required><textarea id="closure-reason" v-model="closureForm.reason" rows="3" :class="inputClass" /></FormField></div></AppDialog>
    </AppLayout>
</template>
