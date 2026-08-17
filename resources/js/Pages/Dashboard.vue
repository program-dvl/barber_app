<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AdjustmentsHorizontalIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ChevronRightIcon,
    ExclamationTriangleIcon,
    QueueListIcon,
    Squares2X2Icon,
} from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    businessLabel: String,
    location: Object,
    locations: Array,
    date: String,
    calendar: Object,
    readiness: Object,
    permissions: Object,
    todayMetrics: Object,
});

const page = usePage();
const firstName = computed(() => page.props.auth.user.name?.trim().split(/\s+/)[0] || 'there');
const views = [
    { id: 'command', label: 'Command desk', icon: AdjustmentsHorizontalIcon },
    { id: 'rhythm', label: 'Rhythm board', icon: Squares2X2Icon },
    { id: 'guided', label: 'Guided front desk', icon: QueueListIcon },
];
const activeView = ref('command');
const preferenceKey = computed(() => `good-hours:dashboard-view:${page.props.auth.user.id}`);
const appointments = computed(() => (props.calendar.events || []).filter(event => event.type === 'appointment'));
const walkIns = computed(() => (props.calendar.events || []).filter(event => event.type === 'walk_in'));
const currentInstant = computed(() => new Date(props.calendar.currentTime || Date.now()));
const dateLabel = computed(() => new Intl.DateTimeFormat(undefined, {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: props.calendar.timeZone,
}).format(new Date(`${props.date}T12:00:00`)));
const timeLabel = value => new Intl.DateTimeFormat(undefined, {
    hour: '2-digit', minute: '2-digit', timeZone: props.calendar.timeZone,
}).format(new Date(value));
const durationLabel = event => `${Math.max(0, Math.round((new Date(event.endsAt) - new Date(event.startsAt)) / 60000))} min`;
const serviceLabel = event => event.services?.map(service => service.name).join(' + ') || 'Appointment';
const staffLabel = event => event.staff?.map(person => person.name).join(', ') || 'Staff not assigned';
const actionLabel = status => ({ confirmed: 'Mark arrived', late: 'Mark arrived', arrived: 'Check in', checked_in: 'Start service', in_service: 'Complete' }[status] || 'View details');
const calendarHref = (extra = {}) => route('business.calendar', {
    business: page.props.tenant.public_id,
    location: props.location?.public_id,
    date: props.date,
    ...extra,
});
const groupedByTime = computed(() => {
    const groups = { now: [], next: [], later: [] };
    appointments.value.forEach(event => {
        const start = new Date(event.startsAt);
        const end = new Date(event.endsAt);
        const minutesUntil = (start - currentInstant.value) / 60000;
        if (start <= currentInstant.value && end > currentInstant.value) groups.now.push(event);
        else if (minutesUntil >= 0 && minutesUntil <= 120) groups.next.push(event);
        else if (minutesUntil > 120) groups.later.push(event);
    });
    return groups;
});
const staffColumns = computed(() => {
    const people = new Map();
    appointments.value.forEach(event => {
        const eventStaff = event.staff?.length ? event.staff : [{ id: 'unassigned', name: 'Unassigned' }];
        eventStaff.forEach(person => {
            if (!people.has(person.id)) people.set(person.id, { ...person, events: [] });
            people.get(person.id).events.push(event);
        });
    });
    return [...people.values()];
});
const attention = computed(() => [
    ...appointments.value
        .filter(event => ['pending_confirmation', 'late'].includes(event.status) || event.forms?.pending)
        .map(event => ({ id: event.id, title: event.status === 'late' ? `${event.title} is running late` : event.forms?.pending ? `${event.title} has an incomplete form` : `${event.title} needs confirmation`, detail: `${timeLabel(event.startsAt)} · ${serviceLabel(event)}`, href: calendarHref() })),
    ...walkIns.value.map(event => ({ id: `walk-${event.id}`, title: `${event.title} is waiting`, detail: `Queue ${event.queuePosition} · about ${event.estimatedWaitMinutes || 0} min`, href: route('business.walk-ins.index', page.props.tenant.public_id) })),
]);

const setView = view => {
    activeView.value = view;
    localStorage.setItem(preferenceKey.value, view);
};
const changeLocation = event => router.get(route('business.dashboard', page.props.tenant.public_id), {
    location: event.target.value,
    date: props.date,
}, { preserveState: true, replace: true });

onMounted(() => {
    const saved = localStorage.getItem(preferenceKey.value);
    if (views.some(view => view.id === saved)) activeView.value = saved;
});
</script>

<template>
    <AppLayout title="Dashboard" :business-label="businessLabel">
        <header class="flex flex-col gap-5 border-b border-[var(--border-subtle)] pb-5 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-[var(--brand-pine)]">{{ dateLabel }}</p>
                <h1 class="gh-display mt-1 text-3xl leading-tight text-[var(--text-strong)] sm:text-4xl">Good {{ new Date().getHours() < 12 ? 'morning' : new Date().getHours() < 18 ? 'afternoon' : 'evening' }}, {{ firstName }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-[var(--text-muted)]">
                    <label v-if="locations.length > 1" class="inline-flex items-center gap-2"><span class="font-semibold text-[var(--text-strong)]">Location</span><select class="rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-3 py-2" :value="location?.public_id" @change="changeLocation"><option v-for="item in locations" :key="item.public_id" :value="item.public_id">{{ item.name }}</option></select></label>
                    <span v-else>{{ location?.name || 'No active location' }}</span><span v-if="location" aria-hidden="true">·</span><span v-if="location">{{ location.time_zone.replace('_', ' ') }}</span>
                </div>
            </div>
            <div v-if="permissions.calendar" class="flex flex-wrap gap-2"><AppButton variant="secondary" :href="calendarHref()"><CalendarDaysIcon class="size-5" aria-hidden="true" />Open calendar</AppButton><AppButton v-if="permissions.createAppointment" :href="calendarHref({ create: 1 })">New booking</AppButton></div>
        </header>

        <section v-if="todayMetrics" class="mt-5" aria-labelledby="today-metrics-title">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div><h2 id="today-metrics-title" class="font-semibold text-[var(--text-strong)]">Today’s trusted totals</h2><p class="mt-1 text-xs text-[var(--text-muted)]">Fresh {{ new Date(todayMetrics.fresh_at).toLocaleTimeString() }} · {{ todayMetrics.time_zone }}</p></div>
                <Link :href="route('business.reports.index', page.props.tenant.public_id)" class="text-sm font-semibold text-[var(--action-primary)]">Open reports</Link>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link v-for="(card, key) in todayMetrics.cards" v-show="card.visible" :key="key" :href="card.drill" class="rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-4 hover:border-[var(--brand-pine)]">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ key.replaceAll('_', ' ').replace(' minor', '') }}</p>
                    <p class="mt-2 text-2xl font-bold text-[var(--text-strong)]">{{ key.endsWith('_minor') ? new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format((card.value || 0) / 100) : (card.value ?? 0) }}</p>
                </Link>
            </div>
        </section>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="font-semibold text-[var(--text-strong)]">Choose your working view</h2><p class="mt-1 text-sm text-[var(--text-muted)]">Good Hours remembers this choice on this device.</p></div>
            <div class="inline-flex max-w-full gap-1 overflow-x-auto rounded-xl bg-[var(--surface-subtle)] p-1" role="group" aria-label="Dashboard view">
                <button v-for="view in views" :key="view.id" type="button" :aria-pressed="activeView === view.id" :class="['inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-semibold', activeView === view.id ? 'bg-[var(--surface-raised)] text-[var(--brand-pine)] shadow-sm' : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']" @click="setView(view.id)"><component :is="view.icon" class="size-4" aria-hidden="true" />{{ view.label }}</button>
            </div>
        </div>

        <section v-if="activeView === 'command'" class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]" aria-labelledby="command-title">
            <SurfaceCard :padding="false"><div class="border-b border-[var(--border-subtle)] px-5 py-4"><div class="flex items-center justify-between gap-3"><h2 id="command-title" class="font-semibold text-[var(--text-strong)]">Today’s appointments</h2><span class="text-sm text-[var(--text-muted)]">{{ appointments.length }} scheduled</span></div></div>
                <StatePanel v-if="!appointments.length" compact title="Nothing scheduled yet" :description="permissions.createAppointment ? 'Create a booking or move to another date in Calendar.' : 'Your access does not include the calendar. Ask an owner to update your role if you need scheduling access.'"><template v-if="permissions.calendar && permissions.createAppointment" #actions><AppButton :href="calendarHref({ create: 1 })">New booking</AppButton></template></StatePanel>
                <div v-else class="divide-y divide-[var(--border-subtle)]"><article v-for="event in appointments" :key="event.id" class="grid gap-3 px-5 py-4 sm:grid-cols-[5rem_minmax(0,1fr)_10rem_auto] sm:items-center"><div><p class="font-semibold text-[var(--text-strong)]">{{ timeLabel(event.startsAt) }}</p><p class="mt-0.5 text-xs text-[var(--text-muted)]">{{ durationLabel(event) }}</p></div><div class="min-w-0"><p class="truncate font-semibold text-[var(--text-strong)]">{{ event.title }}</p><p class="mt-0.5 truncate text-sm text-[var(--text-muted)]">{{ serviceLabel(event) }}</p></div><div class="text-sm"><p class="truncate text-[var(--text-strong)]">{{ staffLabel(event) }}</p><span :class="['gh-status mt-1', event.tone === 'danger' ? 'bg-[var(--status-danger-soft)] text-[var(--status-danger)]' : event.tone === 'warning' ? 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]' : 'bg-[var(--status-info-soft)] text-[var(--status-info)]']">{{ event.statusLabel }}</span></div><Link :href="calendarHref()" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--border-strong)] px-3 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-subtle)]">{{ actionLabel(event.status) }}</Link></article></div>
            </SurfaceCard>
            <aside class="space-y-5"><SurfaceCard title="Attention" compact><ul v-if="attention.length" class="divide-y divide-[var(--border-subtle)]"><li v-for="item in attention.slice(0, 5)" :key="item.id" class="py-3 first:pt-0 last:pb-0"><Link :href="item.href" class="group block"><p class="font-semibold text-[var(--text-strong)] group-hover:text-[var(--action-primary)]">{{ item.title }}</p><p class="mt-1 text-xs leading-5 text-[var(--text-muted)]">{{ item.detail }}</p></Link></li></ul><p v-else class="flex gap-2 text-sm text-[var(--text-muted)]"><CheckCircleIcon class="size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />No urgent items right now.</p></SurfaceCard><SurfaceCard title="At a glance" compact><dl class="divide-y divide-[var(--border-subtle)] text-sm"><div class="flex justify-between gap-4 py-2 first:pt-0"><dt>Appointments</dt><dd class="font-semibold text-[var(--text-strong)]">{{ calendar.counts.appointments }}</dd></div><div class="flex justify-between gap-4 py-2"><dt>Walk-ins waiting</dt><dd class="font-semibold text-[var(--text-strong)]">{{ calendar.counts.walkInsWaiting }}</dd></div><div class="flex justify-between gap-4 py-2 last:pb-0"><dt>Unassigned</dt><dd class="font-semibold text-[var(--text-strong)]">{{ calendar.counts.unassigned }}</dd></div></dl></SurfaceCard></aside>
        </section>

        <section v-else-if="activeView === 'rhythm'" class="mt-5" aria-labelledby="rhythm-title">
            <SurfaceCard :padding="false"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-subtle)] px-5 py-4"><div><h2 id="rhythm-title" class="font-semibold text-[var(--text-strong)]">Studio rhythm board</h2><p class="mt-1 text-sm text-[var(--text-muted)]">See each person’s day and where capacity remains.</p></div><span class="gh-status bg-[var(--status-success-soft)] text-[var(--status-success)]">{{ staffColumns.length }} staff lanes</span></div><StatePanel v-if="!staffColumns.length" compact title="No staff schedule to show" description="Appointments assigned to staff will appear here." /><div v-else class="overflow-x-auto p-4"><div class="grid min-w-[56rem] gap-3" :style="{ gridTemplateColumns: `repeat(${staffColumns.length}, minmax(15rem, 1fr))` }"><section v-for="person in staffColumns" :key="person.id" class="rounded-xl bg-[var(--surface-subtle)] p-3"><div class="mb-3 flex items-center gap-2"><span class="grid size-8 place-items-center rounded-full bg-[var(--brand-pine)] text-xs font-bold text-white">{{ person.name.split(' ').map(value => value[0]).join('').slice(0, 2) }}</span><div><h3 class="font-semibold text-[var(--text-strong)]">{{ person.name }}</h3><p class="text-xs text-[var(--text-muted)]">{{ person.events.length }} appointment{{ person.events.length === 1 ? '' : 's' }}</p></div></div><div class="space-y-2"><Link v-for="event in person.events" :key="event.id" :href="calendarHref({ view: 'staff' })" class="block rounded-lg border-l-4 border-[var(--brand-pine)] bg-[var(--surface-raised)] p-3 hover:border-[var(--action-primary)]"><p class="text-xs font-semibold text-[var(--text-muted)]">{{ timeLabel(event.startsAt) }}–{{ timeLabel(event.endsAt) }}</p><p class="mt-1 font-semibold text-[var(--text-strong)]">{{ event.title }}</p><p class="mt-1 text-xs text-[var(--text-muted)]">{{ serviceLabel(event) }}</p></Link></div></section></div></div></SurfaceCard>
        </section>

        <section v-else class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]" aria-labelledby="guided-title">
            <SurfaceCard :padding="false"><div class="border-b border-[var(--border-subtle)] px-5 py-4"><h2 id="guided-title" class="font-semibold text-[var(--text-strong)]">Your next hours</h2><p class="mt-1 text-sm text-[var(--text-muted)]">One recommended action for each visit.</p></div><StatePanel v-if="!appointments.length" compact title="Your day is clear" description="New appointments will be organized into now, next, and later." /><div v-else class="p-5"><section v-for="group in [{ id: 'now', label: 'Now' }, { id: 'next', label: 'Next' }, { id: 'later', label: 'Later' }]" :key="group.id" class="mb-6 last:mb-0"><div class="flex items-center justify-between border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">{{ group.label }}</h3><span class="text-xs text-[var(--text-muted)]">{{ groupedByTime[group.id].length }}</span></div><p v-if="!groupedByTime[group.id].length" class="py-3 text-sm text-[var(--text-muted)]">Nothing {{ group.id === 'now' ? 'in progress' : group.id === 'next' ? 'starting soon' : 'else scheduled' }}.</p><article v-for="event in groupedByTime[group.id]" :key="event.id" class="gh-list-row"><div class="w-16 shrink-0"><p class="font-semibold text-[var(--text-strong)]">{{ timeLabel(event.startsAt) }}</p><p class="text-xs text-[var(--text-muted)]">{{ durationLabel(event) }}</p></div><div class="min-w-0 flex-1"><p class="truncate font-semibold text-[var(--text-strong)]">{{ event.title }}</p><p class="truncate text-sm text-[var(--text-muted)]">{{ serviceLabel(event) }} · {{ staffLabel(event) }}</p></div><Link :href="calendarHref()" class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-[var(--border-strong)] px-3 text-sm font-semibold">{{ actionLabel(event.status) }}</Link></article></section></div></SurfaceCard>
            <aside class="space-y-5"><SurfaceCard v-if="!readiness.publishable" compact><div class="flex gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-full bg-[var(--status-warning-soft)] text-[var(--status-warning)]"><ExclamationTriangleIcon class="size-5" aria-hidden="true" /></span><div><h2 class="font-semibold text-[var(--text-strong)]">Finish your setup</h2><p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">{{ readiness.blockers[0]?.message }}</p></div></div><AppButton class="mt-4 w-full" :href="route('business.configuration.show', page.props.tenant.public_id)">Continue setup<ChevronRightIcon class="size-4" aria-hidden="true" /></AppButton></SurfaceCard><SurfaceCard title="Today at a glance" compact><dl class="divide-y divide-[var(--border-subtle)] text-sm"><div class="flex justify-between py-2 first:pt-0"><dt>Appointments</dt><dd class="font-semibold">{{ calendar.counts.appointments }}</dd></div><div class="flex justify-between py-2"><dt>Waiting</dt><dd class="font-semibold">{{ calendar.counts.walkInsWaiting }}</dd></div><div class="flex justify-between py-2 last:pb-0"><dt>Blocked periods</dt><dd class="font-semibold">{{ calendar.counts.blocks }}</dd></div></dl></SurfaceCard></aside>
        </section>
    </AppLayout>
</template>
