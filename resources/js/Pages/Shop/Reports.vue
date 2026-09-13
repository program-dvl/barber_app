<script setup>
import AppSelect from '@/Components/Product/AppSelect.vue';
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BanknotesIcon, CalendarDaysIcon, ChartBarSquareIcon, MapPinIcon, UserGroupIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import DataTable from '@/Components/Product/DataTable.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import { reportColumnLabel, reportDateTime } from '@/Support/reportPresentation';

const props = defineProps({ catalog: Array, metricDefinitions: Object, result: Object, canExport: Boolean, filterOptions: Object });
const page = usePage();
const label = value => value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
const reportMeta = {
    appointments: ['Appointments', 'Visit volume and status', CalendarDaysIcon], sales: ['Sales', 'Collected and outstanding value', BanknotesIcon], service_revenue: ['Service revenue', 'Revenue by service line', BanknotesIcon], staff_revenue: ['Staff revenue', 'Value delivered by team member', UserGroupIcon], payment_method: ['Payment methods', 'How payments were received', BanknotesIcon], location: ['Locations', 'Compare business locations', MapPinIcon], discount: ['Discounts', 'Approved reductions', BanknotesIcon], refund: ['Refunds', 'Returned and voided payments', BanknotesIcon], tip: ['Tips', 'Traceable team allocations', UserGroupIcon], commission: ['Commission', 'Earned and reversed commission', UserGroupIcon], payroll: ['Payroll export', 'Commission and tip source data', UserGroupIcon], client_classification: ['Client mix', 'New and returning clients', UserGroupIcon], cancellation_no_show: ['Cancellations & no-shows', 'Lost visits and rates', CalendarDaysIcon], utilisation: ['Utilisation', 'Booked time against availability', ChartBarSquareIcon], popular_service: ['Popular services', 'Most frequently sold services', ChartBarSquareIcon], visit_frequency: ['Visit frequency', 'Repeat-visit behaviour', UserGroupIcon], cash_close: ['Cash close', 'Daily cash reconciliation', BanknotesIcon],
};
const meta = key => reportMeta[key] || [label(key), 'Source-backed business report', ChartBarSquareIcon];
const showReferences = ref(false);
const displayColumns = computed(() => props.result.columns.filter(column => showReferences.value || !['source_id', 'count'].includes(column)));
const formatValue = (column, value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (column.endsWith('_at')) return reportDateTime(value, props.result.time_zone, page.props.tenant?.regional?.locale || undefined);
    const lookup = { location_id: ['locations', 'name'], staff_id: ['staff', 'display_name'], service_id: ['services', 'name'] }[column];
    if (lookup) return props.filterOptions[lookup[0]]?.find(item => String(item.id) === String(value))?.[lookup[1]] || `Reference ${value}`;
    if (['appointment', 'sale', 'transaction'].includes(column) && String(value).length > 20) return showReferences.value ? String(value) : `…${String(value).slice(-8)}`;
    if (column.endsWith('_minor')) return new Intl.NumberFormat(page.props.tenant?.regional?.locale || undefined, { style: 'currency', currency: page.props.tenant?.regional?.currency_code || 'INR' }).format(Number(value) / 100);
    if (column.endsWith('_percent')) return `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })}%`;
    if (typeof value === 'number') return value.toLocaleString();
    return String(value).replaceAll('_', ' ');
};
const changeReport = event => router.get(route('business.reports.index', page.props.tenant.public_id), { ...props.result.filters, report: event.target.value }, { preserveState: true });
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
        <PageHeader eyebrow="Business insights" title="Reports" description="Explore performance, compare periods and trace each total to its source.">
            <template #actions><Link v-if="canExport" as="button" method="post" :data="exportData" :href="route('business.report-exports.store', page.props.tenant.public_id)" class="cd-app-button border border-[var(--border-default)] bg-white font-semibold">Export CSV</Link><AppButton variant="quiet" :href="route('business.reports.print', { business: page.props.tenant.public_id, report: result.report_key, ...result.filters })">Print summary</AppButton></template>
        </PageHeader>
        <form class="grid gap-3 rounded-xl border border-[var(--border-subtle)] bg-white p-4 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
            <label class="text-sm font-semibold">Report<AppSelect :value="result.report_key" class="cd-input mt-1" @change="changeReport"><option v-for="key in catalog" :key="key" :value="key">{{ meta(key)[0] }}</option></AppSelect></label>
            <label class="text-sm font-semibold">From<input v-model="startDate" type="date" class="cd-input mt-1" /></label>
            <label class="text-sm font-semibold">To<input v-model="endDate" type="date" class="cd-input mt-1" /></label>
            <label class="text-sm font-semibold">Location<AppSelect v-model="locationId" class="cd-input mt-1"><option value="">All assigned</option><option v-for="item in filterOptions.locations" :key="item.id" :value="item.id">{{ item.name }}</option></AppSelect></label>
            <label class="text-sm font-semibold">Staff<AppSelect v-model="staffId" class="cd-input mt-1"><option value="">All permitted</option><option v-for="item in filterOptions.staff" :key="item.id" :value="item.id">{{ item.display_name }}</option></AppSelect></label>
            <label class="text-sm font-semibold">Service<AppSelect v-model="serviceId" class="cd-input mt-1"><option value="">All</option><option v-for="item in filterOptions.services" :key="item.id" :value="item.id">{{ item.name }}</option></AppSelect></label>
            <label class="text-sm font-semibold">Status<AppSelect v-model="status" class="cd-input mt-1"><option value="">All relevant</option><option v-for="item in filterOptions.statuses" :key="item" :value="item">{{ label(item) }}</option></AppSelect></label><div class="flex items-end"><AppButton type="submit" class="w-full">Apply filters</AppButton></div>
        </form>
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold text-[var(--text-strong)]">{{ meta(result.report_key)[0] }}</h2><p class="mt-1 text-sm text-[var(--text-muted)]">{{ meta(result.report_key)[1] }} · {{ result.filters.start_date }} to {{ result.filters.end_date }} · {{ result.time_zone }}</p></div><span class="cd-status bg-[var(--surface-subtle)] text-[var(--text-muted)]">{{ result.totals.row_count }} {{ Number(result.totals.row_count) === 1 ? 'record' : 'records' }}</span></div>
        <StatePanel v-if="!result.rows.length" class="mt-6" title="No results for this period" description="Change the date or relevant location, staff, service, or status filter." />
        <template v-else><label class="mt-3 flex min-h-11 items-center gap-2 text-sm text-[var(--text-muted)]"><input v-model="showReferences" type="checkbox">Show full source references</label><DataTable class="mt-4 rounded-xl border border-[var(--border-subtle)] bg-white" :caption="meta(result.report_key)[0]"><thead class="bg-[var(--surface-subtle)] text-xs uppercase tracking-wide text-[var(--text-muted)]"><tr><th scope="col" v-for="column in displayColumns" :key="column" class="p-3">{{ reportColumnLabel(column) }}</th></tr></thead><tbody class="divide-y divide-[var(--border-subtle)]"><tr v-for="(row, index) in result.rows" :key="row.source_id || index" class="hover:bg-[var(--surface-subtle)]"><td v-for="column in displayColumns" :key="column" class="max-w-xs truncate p-3" :title="String(row[column] ?? '')"><Link v-if="column === 'drill'" :href="row[column]" class="font-semibold text-[var(--action-primary)]">View source</Link><span v-else :class="column.endsWith('_minor') || column.endsWith('_percent') ? 'font-semibold tabular-nums' : ''">{{ formatValue(column, row[column]) }}</span></td></tr></tbody></DataTable></template>
        <section class="mt-5 grid gap-4 lg:grid-cols-2"><div class="rounded-xl border border-[var(--border-subtle)] bg-white p-5 text-[var(--text-strong)]"><h2 class="font-semibold">Reconciled totals</h2><dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div v-for="(value, key) in result.totals" :key="key"><dt class="text-[var(--text-muted)]">{{ reportColumnLabel(key) }}</dt><dd class="font-semibold">{{ formatValue(key, value) }}</dd></div></dl></div><div v-if="result.previous_period" class="rounded-xl border border-[var(--border-subtle)] p-5"><h2 class="font-semibold text-[var(--text-strong)]">Previous equivalent period</h2><p class="mt-1 text-xs text-[var(--text-muted)]">{{ result.previous_period.from }} to {{ result.previous_period.to }}</p><dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div v-for="(value, key) in result.previous_period.totals" :key="key"><dt class="text-[var(--text-muted)]">{{ reportColumnLabel(key) }}</dt><dd class="font-semibold">{{ formatValue(key, value) }}</dd></div></dl></div></section>
    </AppLayout>
</template>
