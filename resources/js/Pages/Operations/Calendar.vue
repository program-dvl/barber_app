<script setup>
import AppSelect from '@/Components/Product/AppSelect.vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon, ArrowRightIcon, ArrowsPointingOutIcon, BanknotesIcon, ClockIcon, DocumentDuplicateIcon, PencilSquareIcon, PrinterIcon, ScissorsIcon, TrashIcon, UserIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import FormField from '@/Components/Product/FormField.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
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
const copyDialog = ref(null);
const activeEvent = ref(null);
const detailsOpen = ref(false);
const detailsPanel = ref(null);
const detailsToggle = ref(null);
const attentionCount = computed(() => props.calendar.counts.walkInsWaiting + props.calendar.counts.unassigned + props.calendar.counts.blocks);
async function selectAppointment(event) {
    activeEvent.value = event;
    detailsOpen.value = true;
    await nextTick();
    detailsPanel.value?.focus({ preventScroll: true });
    detailsPanel.value?.scrollIntoView({ block: window.innerWidth < 1280 ? 'start' : 'nearest' });
}
function hideDetails() {
    detailsOpen.value = false;
    nextTick(() => detailsToggle.value?.focus());
}
watch(() => [props.filters.date, props.filters.location], () => { activeEvent.value = null; });
const inputClass = 'cd-input';
const views = [{ id: 'day', label: 'Day' }, { id: 'week', label: 'Week' }, { id: 'staff', label: 'Team' }];
const appointments = computed(() => props.calendar.events.filter(event => event.type === 'appointment'));
const auxiliaryEvents = computed(() => props.calendar.events.filter(event => event.type !== 'appointment'));
const localDateLabel = computed(() => {
    const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: props.filters.view === 'week' ? 'medium' : 'full', timeZone: props.calendar.timeZone });
    if (props.filters.view !== 'week') return formatter.format(new Date(props.calendar.range.startsAt));
    return formatter.formatRange(new Date(props.calendar.range.startsAt), new Date(new Date(props.calendar.range.endsAt).getTime() - 1));
});
const localTime = value => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: props.calendar.timeZone }).format(new Date(value));
const localDateKey = value => {
    const parts = zonedParts(new Date(value));
    return `${parts.year}-${parts.month}-${parts.day}`;
};
const statusStyle = tone => ({
    warning: 'border-[var(--status-warning)] bg-[var(--status-warning-soft)]', info: 'border-[var(--status-info)] bg-[var(--status-info-soft)]', success: 'border-[var(--status-success)] bg-[var(--status-success-soft)]',
    strong: 'border-[var(--brand-secondary)] bg-[var(--action-secondary-hover)]', danger: 'border-[var(--status-danger)] bg-[var(--status-danger-soft)]', neutral: 'border-[var(--border-default)] bg-[var(--surface-subtle)]',
}[tone] || 'border-[var(--border-default)] bg-[var(--surface-raised)]');

const applyFilters = overrides => router.get(route('business.calendar', route().params.business), { ...props.filters, ...overrides }, { preserveState: true, replace: true });
const shiftDate = amount => {
    const [year, month, day] = props.filters.date.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day + amount * (props.filters.view === 'week' ? 7 : 1)));
    applyFilters({ date: date.toISOString().slice(0, 10) });
};
const goToday = () => applyFilters({ view: 'day', date: localDateKey(props.bookingRules?.serverNow || new Date()) });

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
    location: props.filters.location, starts_at: '', source: 'reception', client_name: '', client_mobile: '', client_email: '', internal_notes: '',
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
    createForm.post(route('business.appointments.store', route().params.business), { preserveScroll: true, onSuccess: () => { createDialog.value?.close(); createForm.reset('client_name', 'client_mobile', 'client_email', 'internal_notes'); } });
};
const addCreateLine = () => createForm.lines.push({ service: props.options.services[0]?.public_id || '', staff: '', duration_minutes: null });

const changeForm = useForm({
    kind: 'reschedule', location: props.filters.location, starts_at: '', lines: [], version: 1, reason: '', confirmed: true,
    idempotency_key: '', override_rule_codes: [], override_reason: '', override_confirmed: false, client_name: '', client_mobile: '', client_email: '', internal_notes: '',
});
const openChange = (event, kind = 'reschedule', startsAt = null) => {
    activeEvent.value = event;
    changeForm.kind = kind;
    changeForm.location = props.filters.location;
    changeForm.starts_at = localInputInCalendarZone(new Date(startsAt || event.startsAt));
    changeForm.lines = event.services.map(service => ({ service: service.id, staff: service.staffId || event.staff[0]?.id || '', duration_minutes: kind === 'resize' ? service.durationMinutes : null }));
    changeForm.version = event.version;
    changeForm.reason = '';
    changeForm.client_name = event.clientName || '';
    changeForm.client_mobile = event.clientMobile || '';
    changeForm.client_email = event.clientEmail || '';
    changeForm.internal_notes = event.internalNotes || '';
    changeForm.idempotency_key = '';
    changeDialog.value?.open();
};
const addChangeLine = () => changeForm.lines.push({ service: props.options.services[0]?.public_id || '', staff: '', duration_minutes: null });
const submitChange = () => {
    changeForm.idempotency_key ||= `calendar-${changeForm.kind}-${crypto.randomUUID()}`;
    changeForm.override_rule_codes = changeForm.override_confirmed ? ['NOTICE_WINDOW'] : [];
    changeForm.override_reason = changeForm.override_confirmed ? changeForm.reason : '';
    changeForm.post(route('business.appointments.replace', [route().params.business, activeEvent.value.id]), { preserveScroll: true, onSuccess: () => { changeDialog.value?.close(); activeEvent.value = null; } });
};
const changeTitle = computed(() => ({ reschedule: 'Reschedule appointment', resize: 'Adjust appointment duration', reassign: 'Reassign team member', services_changed: 'Update appointment services' }[changeForm.kind]));
const changeDescription = computed(() => ({ reschedule: 'Choose the new start time. Availability is checked again before saving.', resize: 'Adjust only the time this visit needs; services and staff stay attached.', reassign: 'Choose who will deliver each service. Qualifications and availability are rechecked.', services_changed: 'Update this visit’s service list. The previous version stays safely in history.' }[changeForm.kind]));

const nextStatus = status => ({ confirmed: 'arrived', late: 'arrived', arrived: 'checked_in', checked_in: 'in_service', in_service: 'completed' }[status]);
const statusLabel = status => ({ arrived: 'Mark arrived', checked_in: 'Check in', in_service: 'Start service', completed: 'Complete' }[status]);
const transition = (event, status, reason = null, confirmed = false) => router.patch(route('business.appointments.status', [route().params.business, event.id]), {
    status, version: event.version, reason, confirmed, idempotency_key: `status-${event.id}-${status}-${crypto.randomUUID()}`,
}, { preserveScroll: true, onSuccess: () => { activeEvent.value = null; } });
const cancelForm = useForm({ status: 'cancelled_by_shop', version: 1, reason_code: '', other_reason: '', reason: '', confirmed: true, idempotency_key: '' });
const selectedCancellationReason = computed(() => (props.options.cancellationReasons || []).find(item => item.value === cancelForm.reason_code));
const openCancel = event => {
    activeEvent.value = event;
    cancelForm.reason_code = '';
    cancelForm.other_reason = '';
    cancelForm.reason = '';
    cancelForm.idempotency_key = '';
    cancelForm.clearErrors();
    cancelDialog.value?.open();
};
const submitCancel = () => {
    cancelForm.version = activeEvent.value.version;
    cancelForm.idempotency_key ||= `cancel-${activeEvent.value.id}-${crypto.randomUUID()}`;
    if (!selectedCancellationReason.value) { cancelForm.setError('reason_code', 'Choose the reason for cancellation.'); return; }
    if (cancelForm.reason_code === 'other' && !cancelForm.other_reason.trim()) { cancelForm.setError('other_reason', 'Briefly describe the other cancellation reason.'); return; }
    cancelForm.status = selectedCancellationReason.value.status;
    cancelForm.reason = cancelForm.reason_code === 'other' ? `Other cancellation reason: ${cancelForm.other_reason.trim()}` : selectedCancellationReason.value.reason;
    cancelForm.patch(route('business.appointments.status', [route().params.business, activeEvent.value.id]), { preserveScroll: true, onSuccess: () => { cancelDialog.value?.close(); activeEvent.value = null; } });
};
const noteForm = useForm({ notes: '', version: 1, idempotency_key: '' });
const openNotes = event => { activeEvent.value = event; noteForm.notes = event.internalNotes || ''; noteForm.version = event.version; noteForm.idempotency_key = ''; noteDialog.value?.open(); };
const submitNotes = () => { noteForm.idempotency_key ||= `notes-${activeEvent.value.id}-${crypto.randomUUID()}`; noteForm.patch(route('business.appointments.notes', [route().params.business, activeEvent.value.id]), { preserveScroll: true, onSuccess: () => { noteDialog.value?.close(); activeEvent.value = null; } }); };
const blockForm = useForm({ location: props.filters.location, staff: props.options.staff[0]?.public_id || '', kind: 'personal_block', label: '', reason: '', starts_at: `${props.filters.date}T12:00`, ends_at: `${props.filters.date}T12:30`, confirmed: true });
const submitBlock = () => blockForm.post(route('business.schedule-blocks.store', route().params.business), { preserveScroll: true, onSuccess: () => { blockDialog.value?.close(); blockForm.reset('label', 'reason'); } });
const exceptionForm = useForm({ kind: 'service_overrun', reason: '', projected_end: '' });
const openException = event => { activeEvent.value = event; exceptionForm.reason = ''; exceptionForm.projected_end = localInputInCalendarZone(new Date(event.endsAt)); exceptionDialog.value?.open(); };
const submitException = () => exceptionForm.post(route('business.appointments.exceptions', [route().params.business, activeEvent.value.id]), { preserveScroll: true, onSuccess: () => { exceptionDialog.value?.close(); activeEvent.value = null; } });
const closureForm = useForm({ location: props.filters.location, starts_at: `${props.filters.date}T09:00`, ends_at: `${props.filters.date}T18:00`, reason: '', confirmed: true });
const submitClosure = () => closureForm.post(route('business.operational-exceptions.closure', route().params.business), { preserveScroll: true, onSuccess: () => { closureDialog.value?.close(); closureForm.reset('reason'); } });
const copyForm = useForm({ kind: 'duplicate', starts_at: '', confirmed: true, idempotency_key: '' });
const openCopy = (event, kind = 'duplicate') => {
    activeEvent.value = event; copyForm.kind = kind; copyForm.clearErrors(); copyForm.idempotency_key = '';
    const next = new Date(event.startsAt); next.setDate(next.getDate() + (kind === 'rebook' ? 28 : 1));
    copyForm.starts_at = localInputInCalendarZone(next); copyDialog.value?.open();
};
const submitCopy = () => {
    copyForm.idempotency_key ||= `${copyForm.kind}-${activeEvent.value.id}-${crypto.randomUUID()}`;
    copyForm.post(route('business.appointments.copy', [route().params.business, activeEvent.value.id]), { preserveScroll: true, onSuccess: () => { copyDialog.value?.close(); activeEvent.value = null; } });
};
const calendarStartHour = 7;
const calendarEndHour = 20;
const hourHeight = 96;
const hours = Array.from({ length: calendarEndHour - calendarStartHour + 1 }, (_, index) => index + calendarStartHour);
const timelineHeight = (calendarEndHour - calendarStartHour) * hourHeight;
const weekDays = computed(() => Array.from({ length: 7 }, (_, index) => {
    const date = new Date(new Date(props.calendar.range.startsAt).getTime() + index * 86_400_000);
    return { id: localDateKey(date), label: new Intl.DateTimeFormat(undefined, { weekday: 'short', day: 'numeric', month: 'short', timeZone: props.calendar.timeZone }).format(date), type: 'date' };
}));
const scheduleColumns = computed(() => props.filters.view === 'staff'
    ? props.options.staff.map(member => ({ id: member.public_id, label: member.display_name, type: 'staff' }))
    : (props.filters.view === 'week' ? weekDays.value : [{ id: props.filters.date, label: 'All appointments', type: 'date' }]));
const entriesFor = column => appointments.value.filter(event => column.type === 'staff'
    ? event.staff.some(person => person.id === column.id)
    : localDateKey(event.startsAt) === column.id).sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt));
const eventPosition = event => {
    const start = zonedParts(new Date(event.startsAt));
    const startMinutes = Number(start.hour === '24' ? 0 : start.hour) * 60 + Number(start.minute);
    const duration = Math.max(5, (new Date(event.endsAt) - new Date(event.startsAt)) / 60000);
    const visibleStart = Math.max(calendarStartHour * 60, startMinutes);
    const visibleEnd = Math.min(calendarEndHour * 60, startMinutes + duration);
    return { top: `${(visibleStart - calendarStartHour * 60) * hourHeight / 60}px`, height: `${Math.max(8, visibleEnd - visibleStart) * hourHeight / 60}px` };
};
const eventLayout = (event, column) => {
    const sorted = entriesFor(column);
    const groups = [];
    let group = [];
    let groupEnd = 0;
    sorted.forEach(item => {
        const startsAt = new Date(item.startsAt).getTime();
        const endsAt = new Date(item.endsAt).getTime();
        if (group.length && startsAt >= groupEnd) {
            groups.push(group);
            group = [];
            groupEnd = 0;
        }
        group.push(item);
        groupEnd = Math.max(groupEnd, endsAt);
    });
    if (group.length) groups.push(group);
    const overlapGroup = groups.find(items => items.some(item => item.id === event.id)) || [event];
    const laneEnds = [];
    const lanes = new Map();
    overlapGroup.forEach(item => {
        const startsAt = new Date(item.startsAt).getTime();
        let lane = laneEnds.findIndex(endsAt => endsAt <= startsAt);
        if (lane === -1) lane = laneEnds.length;
        laneEnds[lane] = new Date(item.endsAt).getTime();
        lanes.set(item.id, lane);
    });
    const laneCount = Math.max(1, laneEnds.length);
    const lane = lanes.get(event.id) || 0;
    return {
        ...eventPosition(event),
        left: `calc(${lane * 100 / laneCount}% + .25rem)`,
        width: `calc(${100 / laneCount}% - .5rem)`,
    };
};
const currentDateKey = computed(() => localDateKey(props.calendar.currentTime));
const currentLineTop = computed(() => {
    const now = zonedParts(new Date(props.calendar.currentTime));
    const minutes = Number(now.hour === '24' ? 0 : now.hour) * 60 + Number(now.minute);
    return minutes >= calendarStartHour * 60 && minutes <= calendarEndHour * 60 ? `${(minutes - calendarStartHour * 60) * hourHeight / 60}px` : null;
});
const showCurrentLine = column => currentLineTop.value && (column.type === 'staff' || column.id === currentDateKey.value);
onMounted(() => {
    createForm.starts_at = firstAllowedStart();
    if (new URL(window.location.href).searchParams.get('create') === '1') createDialog.value?.open();
});
</script>

<template>
    <AppLayout title="Calendar" :business-label="businessLabel">
        <PageHeader eyebrow="Front desk" title="Calendar" :description="`Appointments and availability · ${calendar.timeZone}`">
            <template #actions>
                <AppButton :href="route('business.calendar.print', { business: route().params.business, location: filters.location, date: filters.date })" variant="secondary"><PrinterIcon class="size-5" aria-hidden="true" />Print day</AppButton>
                <details v-if="permissions.manage || permissions.override" class="cd-action-menu relative">
                    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center justify-center rounded-lg border border-[var(--border-strong)] bg-[var(--surface-raised)] px-4 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]">More</summary>
                    <div class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-1 shadow-[var(--shadow-overlay)]">
                        <button v-if="permissions.manage" type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="blockDialog?.open()">Block staff time</button>
                        <button v-if="permissions.override" type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold text-[var(--status-danger)] hover:bg-[var(--status-danger-soft)]" @click="closureDialog?.open()">Record unexpected closure</button>
                    </div>
                </details>
                <AppButton v-if="permissions.manage" @click="createDialog?.open()">New appointment</AppButton>
            </template>
        </PageHeader>

        <SurfaceCard class="mt-6" compact>
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="grid grid-cols-2 gap-3">
                    <FormField id="calendar-location" label="Location"><AppSelect id="calendar-location" :value="filters.location" :class="inputClass" @change="applyFilters({ location: $event.target.value })"><option v-for="location in options.locations" :key="location.public_id" :value="location.public_id">{{ location.name }}</option></AppSelect></FormField>
                    <FormField id="calendar-date" label="Date"><input id="calendar-date" type="date" :value="filters.date" :class="inputClass" @change="applyFilters({ date: $event.target.value })" /></FormField>
                </div>
                <div class="cd-segmented" role="group" aria-label="Calendar view">
                    <button v-for="view in views" :key="view.id" type="button" :aria-pressed="filters.view === view.id" @click="applyFilters({ view: view.id })">{{ view.label }}</button>
                </div>
            </div>
            <details class="mt-3 border-t border-[var(--border-subtle)] pt-1">
                <summary class="flex min-h-10 cursor-pointer items-center justify-between gap-2 text-sm font-medium text-[var(--text-muted)]">Filter by staff, service or status<span v-if="filters.staff?.length || filters.service?.length || filters.status?.length" class="cd-status bg-[var(--action-secondary-hover)] text-[var(--action-primary)]">{{ (filters.staff?.length || 0) + (filters.service?.length || 0) + (filters.status?.length || 0) }} active</span><span v-else aria-hidden="true">+</span></summary>
                <div class="grid gap-3 pb-1 pt-2 sm:grid-cols-3">
                    <FormField id="calendar-staff" label="Staff"><AppSelect id="calendar-staff" :class="inputClass" :value="filters.staff?.[0] || ''" @change="applyFilters({ staff: $event.target.value ? [$event.target.value] : [] })"><option value="">All staff</option><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField>
                    <FormField id="calendar-service" label="Service"><AppSelect id="calendar-service" :class="inputClass" :value="filters.service?.[0] || ''" @change="applyFilters({ service: $event.target.value ? [$event.target.value] : [] })"><option value="">All services</option><option v-for="service in options.services" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect></FormField>
                    <FormField id="calendar-status" label="Status"><AppSelect id="calendar-status" :class="inputClass" :value="filters.status?.[0] || ''" @change="applyFilters({ status: $event.target.value ? [$event.target.value] : [] })"><option value="">All statuses</option><option v-for="status in ['confirmed','arrived','checked_in','in_service','completed','late','cancelled_by_client','cancelled_by_shop','no_show','rescheduled']" :key="status" :value="status">{{ status.replaceAll('_', ' ').replace(/^./, value => value.toUpperCase()) }}</option></AppSelect></FormField>
                </div>
            </details>
        </SurfaceCard>

        <div class="cd-calendar-datebar mt-4 flex flex-wrap items-center justify-between gap-2">
            <div class="flex gap-1"><AppButton variant="quiet" aria-label="Previous date" @click="shiftDate(-1)"><ArrowLeftIcon class="size-5" aria-hidden="true" />Previous</AppButton><AppButton variant="quiet" @click="goToday">Today</AppButton></div>
            <div class="cd-calendar-date-label text-center"><p class="font-semibold text-[var(--text-strong)]">{{ localDateLabel }}</p><p class="text-xs text-[var(--text-muted)]">{{ calendar.counts.appointments }} {{ calendar.counts.appointments === 1 ? 'appointment' : 'appointments' }} · {{ calendar.counts.walkInsWaiting }} waiting</p></div>
            <AppButton variant="quiet" aria-label="Next date" @click="shiftDate(1)">Next<ArrowRightIcon class="size-5" aria-hidden="true" /></AppButton>
        </div>

        <div class="mt-5 grid gap-5" :class="detailsOpen ? 'xl:grid-cols-[minmax(0,1fr)_18rem]' : 'grid-cols-1'">
            <SurfaceCard :padding="false" title="Schedule" :description="`${calendar.counts.appointments} ${calendar.counts.appointments === 1 ? 'appointment' : 'appointments'} · ${calendar.counts.walkInsWaiting} walk-ins waiting`">
                <template #actions><AppButton ref="detailsToggle" variant="secondary" size="small" :aria-expanded="detailsOpen" aria-controls="calendar-details" @click="detailsOpen = !detailsOpen">{{ detailsOpen ? 'Hide details' : 'Details & attention' }}<span v-if="attentionCount" class="cd-status bg-[var(--status-warning-soft)] text-[var(--status-warning)]">{{ attentionCount }}</span></AppButton></template>
                <div v-if="appointments.length === 0" class="border-b border-[var(--border-subtle)] bg-[var(--status-info-soft)] px-5 py-3 text-sm text-[var(--text-default)]"><strong>Clear schedule.</strong> Select New appointment when you are ready to add a visit.</div>
                <div class="overflow-x-auto">
                    <div :class="filters.view === 'week' ? 'min-w-[76rem]' : filters.view === 'staff' ? 'min-w-[46rem]' : 'min-w-0'">
                        <div class="sticky top-0 z-10 grid border-b border-[var(--border-subtle)] bg-[var(--surface-raised)]" :style="{ gridTemplateColumns: `4.5rem repeat(${Math.max(scheduleColumns.length, 1)}, minmax(11rem, 1fr))` }"><div class="border-r border-[var(--border-subtle)]" /><div v-for="column in scheduleColumns" :key="column.id" class="border-r border-[var(--border-subtle)] px-3 py-3 text-center text-sm font-semibold last:border-r-0">{{ column.label }}<span class="mt-0.5 block text-xs font-normal text-[var(--text-muted)]">{{ entriesFor(column).length }} booked</span></div></div>
                        <div class="grid" :style="{ gridTemplateColumns: `4.5rem repeat(${Math.max(scheduleColumns.length, 1)}, minmax(11rem, 1fr))` }">
                            <div class="relative border-r border-[var(--border-subtle)]" :style="{ height: `${timelineHeight}px` }"><span v-for="hour in hours.slice(0, -1)" :key="hour" class="cd-calendar-hour absolute right-3 text-xs font-semibold tabular-nums text-[var(--text-muted)]" :class="hour === calendarStartHour ? 'translate-y-1' : '-translate-y-1/2'" :style="{ top: `${(hour - calendarStartHour) * hourHeight}px` }">{{ String(hour).padStart(2, '0') }}:00</span></div>
                            <section v-for="column in scheduleColumns" :key="column.id" class="relative border-r border-[var(--border-subtle)] last:border-r-0" :style="{ height: `${timelineHeight}px`, backgroundImage: `repeating-linear-gradient(to bottom, transparent 0, transparent ${hourHeight / 2 - 1}px, color-mix(in srgb, var(--border-subtle) 45%, transparent) ${hourHeight / 2}px, transparent ${hourHeight / 2 + 1}px, transparent ${hourHeight - 1}px, var(--border-subtle) ${hourHeight}px)` }" :aria-label="`${column.label} schedule`">
                                <div v-if="showCurrentLine(column)" class="pointer-events-none absolute inset-x-0 z-[4] border-t-2 border-[var(--status-danger)]" :style="{ top: currentLineTop }" aria-hidden="true"><span class="absolute -left-1 -top-1.5 size-3 rounded-full bg-[var(--status-danger)]" /></div>
                                <article v-for="event in entriesFor(column)" :key="event.id" role="button" tabindex="0" aria-controls="calendar-details" :aria-expanded="detailsOpen && activeEvent?.id === event.id" :style="eventLayout(event, column)" :class="['absolute z-[1] cursor-pointer overflow-hidden rounded-xl border-l-4 px-3 py-2 shadow-sm transition hover:z-[2] hover:shadow-md', statusStyle(event.tone), activeEvent?.id === event.id ? 'z-[3] ring-2 ring-[var(--focus-ring)] ring-offset-1' : '']" @click="selectAppointment(event)" @keydown.enter.prevent="selectAppointment(event)" @keydown.space.prevent="selectAppointment(event)">
                                    <div class="flex items-center justify-between gap-2"><h3 class="truncate text-sm font-bold text-[var(--text-strong)]">{{ event.title }}</h3><span class="shrink-0 text-[11px] font-bold tabular-nums">{{ localTime(event.startsAt) }}</span></div><p class="truncate text-xs text-[var(--text-default)]">{{ event.services.map(item => item.name).join(', ') }}</p><p class="mt-0.5 truncate text-[11px] text-[var(--text-muted)]">{{ event.staff.map(item => item.name).join(', ') || 'Unassigned' }} · {{ localTime(event.startsAt) }}–{{ localTime(event.endsAt) }}</p>
                                </article>
                            </section>
                        </div>
                    </div>
                </div>
            </SurfaceCard>

            <aside v-show="detailsOpen" id="calendar-details" ref="detailsPanel" tabindex="-1" aria-label="Appointment details and attention" class="order-first min-w-0 scroll-mt-20 space-y-4 xl:order-last" @keydown.esc.stop.prevent="hideDetails">
                <div class="flex justify-end"><AppButton variant="quiet" size="small" @click="hideDetails">Close panel</AppButton></div>
                <SurfaceCard v-if="activeEvent" title="Appointment actions" compact>
                    <div class="flex items-start justify-between gap-3"><div><p class="text-lg font-semibold text-[var(--text-strong)]">{{ activeEvent.title }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ localTime(activeEvent.startsAt) }}–{{ localTime(activeEvent.endsAt) }}</p></div><span class="cd-status bg-[var(--surface-subtle)]">{{ activeEvent.statusLabel }}</span></div><p class="mt-3 text-sm">{{ activeEvent.services.map(item => item.name).join(', ') }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ activeEvent.staff.map(item => item.name).join(', ') || 'Not assigned' }}</p><AppButton v-if="permissions.manage && nextStatus(activeEvent.status)" class="mt-4 w-full" @click="transition(activeEvent, nextStatus(activeEvent.status))">{{ statusLabel(nextStatus(activeEvent.status)) }}</AppButton><AppButton v-if="activeEvent.status === 'completed'" class="mt-2 w-full" :href="route('business.checkout.index', { business: route().params.business, appointment: activeEvent.id })" variant="secondary"><BanknotesIcon class="size-5" />Quick checkout</AppButton>
                    <div v-if="permissions.manage" class="mt-4 grid grid-cols-2 gap-2 border-t border-[var(--border-subtle)] pt-4"><button type="button" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="openChange(activeEvent)"><ClockIcon class="size-4" />Reschedule</button><button type="button" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="openChange(activeEvent, 'resize')"><ArrowsPointingOutIcon class="size-4" />Duration</button><button type="button" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="openChange(activeEvent, 'reassign')"><UserIcon class="size-4" />Reassign</button><button type="button" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="openChange(activeEvent, 'services_changed')"><ScissorsIcon class="size-4" />Services</button><button type="button" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="openNotes(activeEvent)"><PencilSquareIcon class="size-4" />Note</button><button type="button" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="openCopy(activeEvent, ['completed','cancelled_by_client','cancelled_by_shop','no_show'].includes(activeEvent.status) ? 'rebook' : 'duplicate')"><DocumentDuplicateIcon class="size-4" />{{ ['completed','cancelled_by_client','cancelled_by_shop','no_show'].includes(activeEvent.status) ? 'Rebook' : 'Duplicate' }}</button><button v-if="['confirmed','late'].includes(activeEvent.status)" type="button" class="min-h-11 rounded-lg px-2 text-left text-sm font-semibold hover:bg-[var(--surface-subtle)]" @click="transition(activeEvent, 'no_show', 'Client did not attend.', true)">Mark no-show</button><button v-if="!['completed','cancelled_by_client','cancelled_by_shop','no_show','rescheduled'].includes(activeEvent.status)" type="button" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-left text-sm font-semibold text-[var(--status-danger)] hover:bg-[var(--status-danger-soft)]" @click="openCancel(activeEvent)"><TrashIcon class="size-4" />Cancel visit</button></div>
                </SurfaceCard>
                <SurfaceCard v-else title="Choose an appointment" compact><p class="text-sm leading-6 text-[var(--text-muted)]">Select a schedule card to see the client, service, staff, form status, and recommended next action.</p></SurfaceCard>
                <SurfaceCard title="Needs attention" description="No schedule changes happen automatically.">
                    <ul class="space-y-3 text-sm"><li><strong>{{ calendar.counts.walkInsWaiting }}</strong> walk-ins waiting</li><li><strong>{{ calendar.counts.unassigned }}</strong> unassigned visits</li><li><strong>{{ calendar.counts.blocks }}</strong> blocked periods</li></ul>
                </SurfaceCard>
                <SurfaceCard v-if="auxiliaryEvents.length" title="Queue & blocked time">
                    <ul class="space-y-3"><li v-for="event in auxiliaryEvents" :key="`${event.type}-${event.id}`" class="rounded-lg bg-[var(--surface-subtle)] p-3"><p class="text-xs font-bold uppercase">{{ event.statusCue }}</p><p class="font-semibold">{{ event.title }}</p><p class="text-xs text-[var(--text-muted)]">{{ event.statusLabel }}</p></li></ul>
                </SurfaceCard>
            </aside>
        </div>

        <AppDialog id="create-appointment" ref="createDialog" title="Create appointment" description="Capacity is checked again when you save. Email updates are included unless the client has opted out." confirm-label="Create appointment" :close-on-confirm="false" :confirm-disabled="createForm.processing" @confirm="submitCreate">
            <div class="space-y-4"><p v-if="Object.keys(createForm.errors).length" class="rounded-lg bg-[var(--status-warning-soft)] p-3 text-sm text-[var(--status-warning)]" role="alert">{{ Object.values(createForm.errors)[0] }}</p><FormField id="create-client" label="Client name"><input id="create-client" v-model="createForm.client_name" :class="inputClass" autocomplete="name" /></FormField><FormField id="create-mobile" label="Mobile"><PhoneInput id="create-mobile" v-model="createForm.client_mobile" :country="$page.props.tenant?.regional?.country_code || 'IN'" /></FormField><FormField id="create-email" label="Email for appointment updates"><input id="create-email" v-model="createForm.client_email" type="email" :class="inputClass" autocomplete="email" /></FormField><FormField id="create-start" label="Starts"><input id="create-start" v-model="createForm.starts_at" type="datetime-local" :step="Math.max(1, bookingRules?.intervalMinutes || 15) * 60" :class="inputClass" /></FormField><p class="-mt-2 text-xs text-[var(--text-muted)]">Times use {{ calendar.timeZone }} and follow {{ bookingRules?.intervalMinutes || 15 }}-minute intervals.</p><div v-for="(line, index) in createForm.lines" :key="index" class="rounded-lg bg-[var(--surface-subtle)] p-3"><FormField :id="`create-service-${index}`" :label="`Service ${index + 1}`" required><AppSelect :id="`create-service-${index}`" v-model="line.service" :class="inputClass" @change="index === 0 && (createForm.starts_at = firstAllowedStart())"><option v-for="service in options.services" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect></FormField><FormField :id="`create-staff-${index}`" class="mt-3" label="Staff"><AppSelect :id="`create-staff-${index}`" v-model="line.staff" :class="inputClass"><option value="">First available</option><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField><button v-if="createForm.lines.length > 1" type="button" class="mt-2 min-h-11 text-sm font-semibold text-[var(--status-danger)]" @click="createForm.lines.splice(index, 1)">Remove service</button></div><AppButton variant="quiet" @click="addCreateLine">Add another service</AppButton></div>
        </AppDialog>

        <AppDialog id="change-appointment" ref="changeDialog" :title="changeTitle" :description="changeDescription" :confirm-label="changeForm.kind === 'services_changed' ? 'Update this visit' : 'Save change'" :close-on-confirm="false" :confirm-disabled="changeForm.processing" @confirm="submitChange">
            <div class="space-y-4"><p v-if="Object.keys(changeForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(changeForm.errors)[0] }}</p><FormField v-if="changeForm.kind === 'reschedule'" id="change-start" label="New start" required><input id="change-start" v-model="changeForm.starts_at" type="datetime-local" :class="inputClass" /></FormField><div v-for="(line, index) in changeForm.lines" :key="index" class="rounded-lg bg-[var(--surface-subtle)] p-3"><FormField v-if="changeForm.kind === 'services_changed'" :id="`change-service-${index}`" :label="`Service ${index + 1}`"><AppSelect :id="`change-service-${index}`" v-model="line.service" :class="inputClass"><option v-for="service in options.services" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect></FormField><FormField v-if="['reassign','services_changed'].includes(changeForm.kind)" :id="`change-staff-${index}`" :class="changeForm.kind === 'services_changed' ? 'mt-3' : ''" label="Staff"><AppSelect :id="`change-staff-${index}`" v-model="line.staff" :class="inputClass"><option value="">First available</option><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField><FormField v-if="changeForm.kind === 'resize'" :id="`change-duration-${index}`" label="Total service minutes"><input :id="`change-duration-${index}`" v-model.number="line.duration_minutes" type="number" min="5" max="720" step="5" :class="inputClass" /></FormField><button v-if="changeForm.kind === 'services_changed' && changeForm.lines.length > 1" type="button" class="mt-2 min-h-11 text-sm font-semibold text-[var(--status-danger)]" @click="changeForm.lines.splice(index, 1)">Remove service</button></div><AppButton v-if="changeForm.kind === 'services_changed'" variant="quiet" @click="addChangeLine">Add service</AppButton><FormField id="change-reason" label="Reason" required><textarea id="change-reason" v-model="changeForm.reason" rows="3" :class="inputClass" /></FormField><label v-if="permissions.override && changeForm.kind === 'reschedule'" class="flex gap-3 text-sm"><input v-model="changeForm.override_confirmed" type="checkbox" class="mt-1 size-5" /><span><strong>Manager policy override</strong><br><span class="text-[var(--text-muted)]">Only notice/advance policy may be overridden. Capacity and integrity conflicts never can.</span></span></label></div>
        </AppDialog>

        <AppDialog id="copy-appointment" ref="copyDialog" :title="copyForm.kind === 'rebook' ? 'Rebook this client' : 'Create a separate appointment'" description="Choose a new time. This creates a separate visit and never overwrites the current one." :confirm-label="copyForm.kind === 'rebook' ? 'Create rebooking' : 'Create duplicate'" :close-on-confirm="false" :confirm-disabled="copyForm.processing" @confirm="submitCopy"><div class="space-y-4"><p v-if="Object.keys(copyForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(copyForm.errors)[0] }}</p><FormField id="copy-start" label="New start" required><input id="copy-start" v-model="copyForm.starts_at" type="datetime-local" :class="inputClass" /></FormField></div></AppDialog>
        <AppDialog id="cancel-appointment" ref="cancelDialog" title="Cancel this appointment?" description="Capacity is released immediately. The reason remains in history and helps keep cancellation reporting accurate." confirm-label="Cancel appointment" cancel-label="Keep appointment" destructive :close-on-confirm="false" :confirm-disabled="cancelForm.processing || !cancelForm.reason_code || (cancelForm.reason_code === 'other' && !cancelForm.other_reason.trim())" @confirm="submitCancel"><p v-if="cancelForm.errors.reason_code || cancelForm.errors.other_reason || cancelForm.errors.reason || cancelForm.errors.booking" class="mb-3 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ cancelForm.errors.reason_code || cancelForm.errors.other_reason || cancelForm.errors.reason || cancelForm.errors.booking }}</p><FormField id="cancel-reason" label="Why is this appointment being cancelled?" required><AppSelect id="cancel-reason" v-model="cancelForm.reason_code" :class="inputClass" @change="cancelForm.clearErrors('reason_code'); if (cancelForm.reason_code !== 'other') cancelForm.other_reason = ''"><option value="" disabled>Select a reason</option><option v-for="reason in options.cancellationReasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option></AppSelect></FormField><FormField v-if="cancelForm.reason_code === 'other'" id="cancel-other-reason" class="mt-4" label="Other reason" required><textarea id="cancel-other-reason" v-model="cancelForm.other_reason" rows="3" :class="inputClass" placeholder="Briefly explain why this appointment is being cancelled" /></FormField><p class="mt-3 text-xs leading-5 text-[var(--text-muted)]">Client-requested reasons are reported separately from business cancellations.</p></AppDialog>
        <AppDialog id="appointment-note" ref="noteDialog" title="Internal appointment note" description="Note changes preserve a content hash without copying sensitive text into audit summaries." confirm-label="Save note" :close-on-confirm="false" :confirm-disabled="noteForm.processing" @confirm="submitNotes"><p v-if="Object.keys(noteForm.errors).length" class="mb-3 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(noteForm.errors)[0] }}</p><FormField id="appointment-note-text" label="Note"><textarea id="appointment-note-text" v-model="noteForm.notes" rows="5" :class="inputClass" /></FormField></AppDialog>
        <AppDialog id="schedule-block" ref="blockDialog" title="Block staff time" description="Existing appointments and active holds cannot be overridden." confirm-label="Create block" :close-on-confirm="false" :confirm-disabled="blockForm.processing" @confirm="submitBlock"><div class="space-y-4"><p v-if="Object.keys(blockForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(blockForm.errors)[0] }}</p><FormField id="block-staff" label="Staff" required><AppSelect id="block-staff" v-model="blockForm.staff" :class="inputClass"><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField><FormField id="block-kind" label="Type"><AppSelect id="block-kind" v-model="blockForm.kind" :class="inputClass"><option value="personal_block">Personal block</option><option value="staff_break">Staff break</option></AppSelect></FormField><FormField id="block-label" label="Calendar label" required><input id="block-label" v-model="blockForm.label" :class="inputClass" /></FormField><div class="grid grid-cols-2 gap-3"><FormField id="block-start" label="Starts"><input id="block-start" v-model="blockForm.starts_at" type="datetime-local" :class="inputClass" /></FormField><FormField id="block-end" label="Ends"><input id="block-end" v-model="blockForm.ends_at" type="datetime-local" :class="inputClass" /></FormField></div><FormField id="block-reason" label="Private reason" required><textarea id="block-reason" v-model="blockForm.reason" rows="3" :class="inputClass" /></FormField></div></AppDialog>
        <AppDialog id="operational-impact" ref="exceptionDialog" title="Record operational impact" description="This does not move future appointments. It creates an explicit recovery list and notification event." confirm-label="Record impact" :close-on-confirm="false" :confirm-disabled="exceptionForm.processing" @confirm="submitException"><div class="space-y-4"><p v-if="Object.keys(exceptionForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(exceptionForm.errors)[0] }}</p><FormField id="impact-kind" label="Exception"><AppSelect id="impact-kind" v-model="exceptionForm.kind" :class="inputClass"><option value="late_arrival">Late arrival</option><option value="service_overrun">Service overrun</option><option value="staff_unavailable">Staff unavailable</option></AppSelect></FormField><FormField id="impact-end" label="Projected end"><input id="impact-end" v-model="exceptionForm.projected_end" type="datetime-local" :class="inputClass" /></FormField><FormField id="impact-reason" label="Reason" required><textarea id="impact-reason" v-model="exceptionForm.reason" rows="3" :class="inputClass" /></FormField></div></AppDialog>
        <AppDialog id="unexpected-closure" ref="closureDialog" title="Record unexpected closure?" description="Every affected appointment will be listed for contact, reschedule, or cancellation. Nothing is changed automatically." confirm-label="Record closure" cancel-label="Keep schedule open" destructive :close-on-confirm="false" :confirm-disabled="closureForm.processing" @confirm="submitClosure"><div class="space-y-4"><p v-if="Object.keys(closureForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(closureForm.errors)[0] }}</p><div class="grid grid-cols-2 gap-3"><FormField id="closure-start" label="Starts"><input id="closure-start" v-model="closureForm.starts_at" type="datetime-local" :class="inputClass" /></FormField><FormField id="closure-end" label="Ends"><input id="closure-end" v-model="closureForm.ends_at" type="datetime-local" :class="inputClass" /></FormField></div><FormField id="closure-reason" label="Reason" required><textarea id="closure-reason" v-model="closureForm.reason" rows="3" :class="inputClass" /></FormField></div></AppDialog>
    </AppLayout>
</template>
