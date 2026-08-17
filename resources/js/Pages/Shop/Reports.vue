<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';

const props = defineProps({ catalog: Array, metricDefinitions: Object, result: Object, canExport: Boolean, filterOptions: Object });
const page = usePage();
const label = value => value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
const changeReport = event => router.get(route('business.reports.index', page.props.tenant.public_id), { report: event.target.value }, { preserveState: true });
const startDate = ref(props.result.filters.start_date);
const endDate = ref(props.result.filters.end_date);
const locationId = ref(props.result.filters.location_ids?.[0] || '');
const staffId = ref(props.result.filters.staff_ids?.[0] || '');
const serviceId = ref(props.result.filters.service_ids?.[0] || '');
const status = ref(props.result.filters.statuses?.[0] || '');
const applyFilters = () => router.get(route('business.reports.index', page.props.tenant.public_id), {
    report: props.result.report_key,
    start_date: startDate.value,
    end_date: endDate.value,
    location_ids: locationId.value ? [Number(locationId.value)] : [],
    staff_ids: staffId.value ? [Number(staffId.value)] : [],
    service_ids: serviceId.value ? [Number(serviceId.value)] : [],
    statuses: status.value ? [status.value] : [],
}, { preserveState: true });
const exportData = computed(() => ({ report: props.result.report_key, ...props.result.filters }));
</script>

<template>
    <AppLayout title="Reports" :business-label="page.props.tenant.name">
        <Head title="Reports" />
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--border-subtle)] pb-5">
            <div><p class="text-sm font-semibold text-[var(--brand-pine)]">FR-18 · Definition {{ result.metric_version }}</p><h1 class="gh-display mt-1 text-4xl text-[var(--text-strong)]">Trusted reports</h1><p class="mt-2 text-sm text-[var(--text-muted)]">{{ result.source }} · Fresh {{ new Date(result.fresh_at).toLocaleString() }} · {{ result.time_zone }}</p></div>
            <div class="flex flex-wrap gap-2"><Link v-if="canExport" as="button" method="post" :data="exportData" :href="route('business.report-exports.store', page.props.tenant.public_id)" class="inline-flex min-h-11 items-center rounded-lg bg-[var(--brand-pine)] px-4 text-sm font-semibold text-white">Queue CSV</Link><Link :href="route('business.reports.print', { business: page.props.tenant.public_id, report: result.report_key, ...result.filters })" class="inline-flex min-h-11 items-center rounded-lg border border-[var(--border-strong)] px-4 text-sm font-semibold">Printable summary</Link></div>
        </header>
        <form class="mt-5 grid gap-3 rounded-xl bg-[var(--surface-subtle)] p-4 sm:grid-cols-2 xl:grid-cols-7" @submit.prevent="applyFilters">
            <label class="text-sm font-semibold">Report<select :value="result.report_key" class="mt-1 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white px-3" @change="changeReport"><option v-for="key in catalog" :key="key" :value="key">{{ label(key) }}</option></select></label>
            <label class="text-sm font-semibold">From<input v-model="startDate" type="date" class="mt-1 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white px-3" /></label>
            <label class="text-sm font-semibold">To<input v-model="endDate" type="date" class="mt-1 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white px-3" /></label>
            <label class="text-sm font-semibold">Location<select v-model="locationId" class="mt-1 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white px-3"><option value="">All assigned</option><option v-for="item in filterOptions.locations" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
            <label class="text-sm font-semibold">Staff<select v-model="staffId" class="mt-1 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white px-3"><option value="">All permitted</option><option v-for="item in filterOptions.staff" :key="item.id" :value="item.id">{{ item.display_name }}</option></select></label>
            <label class="text-sm font-semibold">Service<select v-model="serviceId" class="mt-1 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white px-3"><option value="">All</option><option v-for="item in filterOptions.services" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
            <div><label class="text-sm font-semibold">Status<select v-model="status" class="mt-1 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white px-3"><option value="">All relevant</option><option v-for="item in filterOptions.statuses" :key="item" :value="item">{{ label(item) }}</option></select></label><button type="submit" class="mt-2 min-h-11 w-full rounded-lg bg-[var(--brand-pine)] px-4 text-sm font-semibold text-white">Apply</button></div>
        </form>
        <div class="mt-3 rounded-lg bg-[var(--surface-subtle)] px-4 py-2 text-sm"><strong>{{ result.totals.row_count }}</strong> source rows · {{ result.filters.start_date }} to {{ result.filters.end_date }}</div>
        <StatePanel v-if="!result.rows.length" class="mt-6" title="No source records match" description="Change the date or relevant location, staff, service, or status filter." />
        <div v-else class="mt-6 overflow-x-auto rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)]"><table class="min-w-full text-left text-sm"><thead class="bg-[var(--surface-subtle)] text-xs uppercase tracking-wide text-[var(--text-muted)]"><tr><th v-for="column in result.columns" :key="column" class="p-3">{{ label(column) }}</th></tr></thead><tbody class="divide-y divide-[var(--border-subtle)]"><tr v-for="(row, index) in result.rows" :key="row.source_id || index"><td v-for="column in result.columns" :key="column" class="max-w-xs truncate p-3"><Link v-if="column === 'drill'" :href="row[column]" class="font-semibold text-[var(--action-primary)]">Source record</Link><span v-else>{{ row[column] }}</span></td></tr></tbody></table></div>
        <section class="mt-5 grid gap-4 lg:grid-cols-2"><div class="rounded-xl bg-[var(--brand-pine)] p-5 text-white"><h2 class="font-semibold">Reconciled totals</h2><dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div v-for="(value, key) in result.totals" :key="key"><dt class="text-white/70">{{ label(key) }}</dt><dd class="font-semibold">{{ value }}</dd></div></dl></div><div v-if="result.previous_period" class="rounded-xl border border-[var(--border-subtle)] p-5"><h2 class="font-semibold text-[var(--text-strong)]">Previous equivalent period</h2><p class="mt-1 text-xs text-[var(--text-muted)]">{{ result.previous_period.from }} to {{ result.previous_period.to }}</p><dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div v-for="(value, key) in result.previous_period.totals" :key="key"><dt class="text-[var(--text-muted)]">{{ label(key) }}</dt><dd class="font-semibold">{{ value }}</dd></div></dl></div></section>
    </AppLayout>
</template>
