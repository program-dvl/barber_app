<script setup>
import {computed,nextTick,onBeforeUnmount,onMounted,ref,watch} from 'vue';
import {Head,Link,router,usePage} from '@inertiajs/vue3';
import axios from 'axios';
import {ArrowDownTrayIcon,ArrowPathIcon,ArrowRightIcon,ChartBarSquareIcon,ChevronDownIcon,ChevronLeftIcon,ChevronRightIcon,InformationCircleIcon,PrinterIcon,XMarkIcon} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import DataTable from '@/Components/Product/DataTable.vue';
import SearchField from '@/Components/Product/SearchField.vue';
import ReportTrend from '@/Components/Reports/ReportTrend.vue';
import {reportColumnLabel,reportDateTime} from '@/Support/reportPresentation';
import {reportMoney,reportDuration,reportChange,reportToday,reportPreset,reportPresets,reportPresetKey,validReportRange,reportNavigationFilters} from '@/Support/reportWorkspace';
import '../../../css/reports.css';

const props=defineProps({catalog:Array,reportCatalog:Object,metricDefinitions:Object,result:Object,canExport:Boolean,filterOptions:Object});
const page=usePage();
const business=computed(()=>page.props.tenant.public_id);
const locale=computed(()=>page.props.tenant?.regional?.locale || undefined);
const selected=computed(()=>props.result.report_key);
const meta=computed(()=>props.reportCatalog[selected.value]);
const label=value=>String(value).replaceAll('_',' ').replace(/^./,letter=>letter.toUpperCase());
const columnLabel=key=>({collected_minor:'Net receipts',net_minor:selected.value==='sales' || selected.value==='overview' || selected.value==='tax' ? 'Sales less returns' : 'After discount',gross_minor:'Recorded sales',average_ticket_minor:'Average sale value',row_count:selected.value==='overview' || selected.value==='sales' ? 'Sales' : selected.value==='utilisation' ? 'Team members' : 'Records',service:'Service',allocated_refund_minor:'Item returns',no_show_percent:'No-show rate',cancellation_percent:'Cancellation rate',average_duration_minutes:'Average duration',average_wait_minutes:'Average wait',longest_wait_minutes:'Longest wait',started_count:'Service started',new_count:'New paying clients',returning_count:'Returning paying clients',tax_basis:'Tax basis',deposit_applied_minor:'Deposits applied',amount_minor:selected.value==='tip' ? 'Net tips' : selected.value==='payroll' ? 'Entry amount' : 'Net commission',commission_minor:'Commission',tips_minor:'Tips',free_minutes:'Unused capacity',recorded_minutes:'Recorded service time',outside_windows_minutes:'Outside configured windows',booked_minutes:'Occupied within windows',available_minutes:'Configured capacity'}[key] || reportColumnLabel(key));
const format=(column,value,full=false)=>{
    if(value===null || value===undefined || value==='') return '—';
    if(column.endsWith('_minor')) return reportMoney(value,props.result.currency_code,locale.value);
    if(column.endsWith('_percent')) return `${new Intl.NumberFormat(locale.value,{maximumFractionDigits:1}).format(Number(value))}%`;
    if(column.endsWith('_minutes')) return reportDuration(value);
    if(column.endsWith('_at')) return reportDateTime(value,props.result.time_zone,locale.value);
    if(['sale','transaction','appointment','client','entry'].includes(column)) return full ? String(value) : `…${String(value).slice(-8)}`;
    if(typeof value==='number') return new Intl.NumberFormat(locale.value,{maximumFractionDigits:1}).format(value);
    return ['status','source','classification','method','kind','type','ledger'].includes(column) ? label(value) : String(value);
};
const changing=ref(false);const error=ref('');const exportBusy=ref(false);const exportMessage=ref('');const exportDownload=ref('');const exportError=ref(false);const record=ref(null);const detail=ref(null);const activeColumns=ref([]);const search=ref('');const advanced=ref(false);const recent=ref([]);
const start=ref('');const end=ref('');const location=ref('');const staff=ref('');const service=ref('');const status=ref('');const method=ref('');const compare=ref('previous');const currency=ref('');const granularity=ref('auto');
const today=computed(()=>reportToday(props.result.time_zone));
const preset=computed(()=>reportPresetKey(start.value,end.value,today.value));
const groups=computed(()=>Object.entries(props.reportCatalog).reduce((out,[key,item])=>{(out[item.group] ||= []).push({key,...item});return out;},{}));
const monetary=computed(()=>props.result.columns.some(key=>key.endsWith('_minor')));
const previousLabel=computed(()=>props.result.previous_period ? `${props.result.previous_period.from} – ${props.result.previous_period.to}` : '');
const metricKeys=computed(()=>meta.value.metrics.filter(key=>Object.hasOwn(props.result.totals,key)).slice(0,4));
const availableColumns=computed(()=>props.result.columns.filter(key=>!key.endsWith('_id') && !['source_id','drill','entry_kind','currency_code'].includes(key)));
const columns=computed(()=>activeColumns.value.filter(key=>availableColumns.value.includes(key)));
const detailColumns=computed(()=>availableColumns.value);
const rankMax=computed(()=>Math.max(1,...(props.result.ranking?.rows || []).map(row=>Math.abs(row.value))));
const hasFilters=computed(()=>!!(staff.value || service.value || status.value || method.value || search.value || props.result.filters.dimension_value));
const quickReports=computed(()=>['sales','service_revenue','appointments','utilisation','payment_activity','client_classification'].filter(key=>props.catalog.includes(key)).slice(0,4));
const comparison=key=>reportChange(props.result.totals[key],props.result.previous_period?.totals?.[key],value=>format(key,value));
const endpoint=()=>route('business.reports.index',business.value);
const query=()=>({...props.result.filters,report:selected.value,start_date:start.value,end_date:end.value,location_ids:location.value==='__selection' ? props.result.filters.location_ids : location.value ? [Number(location.value)] : [],staff_ids:staff.value==='__selection' ? props.result.filters.staff_ids : staff.value ? [Number(staff.value)] : [],service_ids:service.value==='__selection' ? props.result.filters.service_ids : service.value ? [Number(service.value)] : [],statuses:status.value==='__selection' ? props.result.filters.statuses : status.value ? [status.value] : [],method:method.value,compare:compare.value,currency_code:currency.value,search:search.value,granularity:granularity.value,page:1});
function remember() {
    try {const key=`reports-recent:${page.props.auth?.user?.id || page.props.user?.id}:${business.value}`;const entries=JSON.parse(sessionStorage.getItem(key) || '[]');recent.value=[selected.value,...entries.filter(item=>item!==selected.value && props.catalog.includes(item))].slice(0,4);sessionStorage.setItem(key,JSON.stringify(recent.value));} catch {recent.value=[];}
}
function sync() {
    const filters=props.result.filters;start.value=filters.start_date;end.value=filters.end_date;
    location.value=filters.location_ids.length===props.filterOptions.locations.length ? '' : (filters.location_ids.length>1 ? '__selection' : filters.location_ids[0] || '');
    staff.value=filters.staff_ids.length>1 ? '__selection' : filters.staff_ids[0] || '';service.value=filters.service_ids.length>1 ? '__selection' : filters.service_ids[0] || '';status.value=filters.statuses.length>1 ? '__selection' : filters.statuses[0] || '';method.value=filters.method || '';compare.value=filters.compare || 'previous';currency.value=filters.currency_code;search.value=filters.search || '';granularity.value=filters.granularity || 'auto';
    if(!activeColumns.value.length || lastReport!==selected.value) activeColumns.value=meta.value.columns.filter(key=>availableColumns.value.includes(key));
    lastReport=selected.value;
}
let lastReport;let searchTimer;let exportTimer;let cancelVisit;let exportRevision=0;
watch(()=>props.result,sync,{immediate:true});
watch(()=>props.result.report_key,()=>{advanced.value=false;nextTick(remember);});
onMounted(remember);
onBeforeUnmount(()=>{clearTimeout(searchTimer);clearTimeout(exportTimer);cancelVisit?.cancel();});
function navigate(overrides={},target=selected.value) {
    clearTimeout(searchTimer);
    const params={...reportNavigationFilters(query(),target,props.reportCatalog),...overrides,report:target};
    if(target===selected.value){
        params.dimension=props.result.filters.dimension;params.dimension_value=props.result.filters.dimension_value;
        if(!Object.hasOwn(overrides,'search')) params.search=search.value;
        if(Object.hasOwn(overrides,'dimension')) params.dimension=overrides.dimension;
        if(Object.hasOwn(overrides,'dimension_value')) params.dimension_value=overrides.dimension_value;
        params.sort=overrides.sort || props.result.pagination.sort;params.direction=overrides.direction || props.result.pagination.direction;
    }
    if(!validReportRange(params.start_date,params.end_date)){error.value='Choose an ordered date range of up to 367 days.';return;}
    cancelVisit?.cancel();clearTimeout(exportTimer);exportRevision++;exportBusy.value=false;exportMessage.value='';exportDownload.value='';exportId.value='';error.value='';changing.value=true;
    router.get(endpoint(),params,{preserveState:true,preserveScroll:true,onCancelToken:token=>cancelVisit=token,onError:errors=>{error.value=Object.values(errors).flat()[0] || 'The report could not be refreshed. Your last loaded result is still shown.';},onFinish:()=>changing.value=false});
}
function setPreset(key) {const range=reportPreset(key,today.value);if(range){start.value=range[0];end.value=range[1];navigate();}}
function clearFilters() {staff.value='';service.value='';status.value='';method.value='';search.value='';navigate({dimension:'',dimension_value:''});}
function searchChanged() {clearTimeout(searchTimer);searchTimer=setTimeout(()=>navigate({search:search.value}),350);}
function sort(column) {navigate({sort:column,direction:props.result.pagination.sort===column && props.result.pagination.direction==='desc' ? 'asc' : 'desc'});}
function inspect(row) {record.value=row;detail.value.open();}
function inspectMetric(key) {
    const targets={discount_minor:'discount',tax_minor:'tax'};
    if(targets[key] && props.catalog.includes(targets[key])) navigate({},targets[key]);
    else {document.querySelector('#report-records')?.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',block:'start'});document.querySelector('#report-records')?.focus({preventScroll:true});}
}
function inspectPoint(point) {navigate({start_date:point.from,end_date:point.to,search:''},selected.value==='overview' ? 'sales' : selected.value);}
function inspectRanking(row) {if(row.staff_id){navigate({staff_ids:[row.staff_id],search:'',dimension:'',dimension_value:''},'utilisation');return;}const target=props.result.ranking.report;const overrides={search:'',dimension:props.result.ranking.category,dimension_value:row.dimension_value};navigate(overrides,target);}
const printHref=computed(()=>route('business.reports.print',{business:business.value,report:selected.value,...props.result.filters}));
function printReport() {window.location.assign(printHref.value);}
async function pollExport(id,attempt=0,revision=exportRevision) {
    if(revision!==exportRevision)return;
    try {const {data}=await axios.get(route('business.report-exports.show',[business.value,id]));
        if(revision!==exportRevision)return;
        if(data.export.status==='completed'){exportBusy.value=false;exportMessage.value=`CSV ready · ${data.export.row_count.toLocaleString()} records`;exportDownload.value=route('business.report-exports.download',[business.value,id]);return;}
        if(data.export.status==='failed') throw new Error('Generation failed');
        if(attempt>=30){exportBusy.value=false;exportMessage.value='Still preparing. Check the export again.';exportId.value=id;return;}
        exportTimer=setTimeout(()=>pollExport(id,attempt+1,revision),2000);
    } catch {if(revision!==exportRevision)return;exportBusy.value=false;exportError.value=true;exportMessage.value='Export could not be checked. Try again.';exportId.value=id;}
}
const exportId=ref('');
async function exportReport() {
    if(exportBusy.value)return;
    const revision=exportRevision;
    exportBusy.value=true;exportError.value=false;exportMessage.value='Preparing your CSV…';exportDownload.value='';
    try {const {data}=await axios.post(route('business.report-exports.store',business.value),{report:selected.value,...props.result.filters});if(revision!==exportRevision)return;exportId.value=data.export.public_id;await pollExport(data.export.public_id,0,revision);}
    catch(exception){if(revision!==exportRevision)return;exportBusy.value=false;exportError.value=true;exportMessage.value=exception.response?.status===403 ? 'Export access is not available for this account.' : 'Export could not be prepared. Try again.';}
}
const sourceNote=computed(()=>selected.value==='utilisation' ? 'Capacity uses retained schedule configuration, including breaks, leave and closures. Past availability is not a frozen attendance record; inactive staff may have no reconstructable denominator. Recorded service time outside these windows remains visible and is excluded from utilisation. Comparison is unavailable.' : ['line_items','overview','sales','service_revenue','staff_revenue','popular_service','tax'].includes(selected.value) ? 'Recorded prices retain their original tax basis. After-discount values exclude tips and precede returns. Net receipts include applied deposits and deduct all recorded returns for these sales.' : selected.value==='payment_method' ? 'This is a sale-period view. Deposit collections and applications are separate from sale tenders; use Payment activity for transaction-date receipts.' : selected.value==='refund' || selected.value==='payment_activity' ? 'Transaction-date view. Includes recorded payments/returns on open or completed sales. Deposit collections are excluded.' : '');
</script>

<template>
<AppLayout title="Reports" :business-label="page.props.tenant.name">
    <Head title="Reports"/>
    <div class="ra-workspace">
        <PageHeader title="Reports" description="Understand the changes. Follow the detail.">
            <template #actions><AppButton variant="quiet" :disabled="changing" @click="navigate({page:result.pagination.current_page})" aria-label="Refresh report"><ArrowPathIcon class="size-4"/>Refresh</AppButton><AppButton variant="secondary" :disabled="changing" @click="printReport"><PrinterIcon class="size-4"/><span class="ra-print-label">Print</span></AppButton><AppButton v-if="canExport" variant="secondary" :disabled="changing" :loading="exportBusy" @click="exportReport"><ArrowDownTrayIcon v-if="!exportBusy" class="size-4"/>Export CSV</AppButton></template>
        </PageHeader>
        <div v-if="exportMessage" class="ra-export-feedback" role="status" :class="{'ra-error':exportError}"><span>{{ exportMessage }}</span><a v-if="exportDownload" :href="exportDownload" class="ra-text-link">Download CSV <ArrowDownTrayIcon class="size-4"/></a><button v-else-if="exportId && !exportBusy" type="button" @click="exportBusy=true;pollExport(exportId)">Check status</button><button v-if="!exportBusy" type="button" @click="exportMessage=''" aria-label="Dismiss export message"><XMarkIcon class="size-4"/></button></div>
        <form class="ra-controls" @submit.prevent="navigate()">
            <div class="ra-primary-controls">
                <label class="ra-report-select">Report<AppSelect :value="selected" searchable @change="navigate({},$event.target.value)"><optgroup v-for="(items,group) in groups" :key="group" :label="group"><option v-for="item in items" :key="item.key" :value="item.key">{{ item.title }}</option></optgroup></AppSelect></label>
                <label>Range<AppSelect :value="preset" @change="setPreset($event.target.value)"><option v-for="item in reportPresets" :key="item.key" :value="item.key">{{ item.label }}</option></AppSelect></label>
                <label class="ra-date-field">From<input v-model="start" type="date" class="cd-input" :max="end || undefined" required @change="compare=compare || 'previous'"/></label>
                <label class="ra-date-field">To<input v-model="end" type="date" class="cd-input" :min="start || undefined" required/></label>
                <label>Location<AppSelect v-model="location"><option value="" :disabled="new Set(filterOptions.locations.map(item=>item.time_zone)).size>1">All assigned locations</option><option v-if="result.filters.location_ids.length>1 && result.filters.location_ids.length<filterOptions.locations.length" value="__selection">{{ result.filters.location_ids.length }} selected locations</option><option v-for="item in filterOptions.locations" :key="item.id" :value="item.id">{{ item.name }}</option></AppSelect></label>
                <AppButton type="submit" :disabled="changing" size="small">Apply</AppButton>
            </div>
            <div class="ra-secondary-controls">
                <label class="ra-compare-control">Compare<span v-if="['utilisation','stock'].includes(selected)" class="ra-unavailable">Unavailable for this report</span><AppSelect v-else v-model="compare" @change="navigate()"><option value="previous">Previous equal period</option><option value="year">Same dates last year</option><option value="none">No comparison</option></AppSelect></label>
                <label v-if="monetary && filterOptions.currencies.length>1" class="ra-currency-control">Currency<AppSelect v-model="currency" @change="navigate()"><option v-for="code in filterOptions.currencies" :key="code" :value="code">{{ code }}</option></AppSelect></label>
                <button type="button" class="ra-filter-toggle" :aria-expanded="advanced" aria-controls="report-more-filters" @click="advanced=!advanced">More filters <span v-if="hasFilters" class="ra-filter-count">{{ [staff,service,status,method,search].filter(Boolean).length }}</span><ChevronDownIcon class="size-4"/></button>
                <button v-if="hasFilters" type="button" class="ra-clear" @click="clearFilters">Clear filters</button>
                <span class="ra-timezone">{{ result.time_zone }}<span v-if="monetary"> · {{ result.currency_code }}</span></span>
            </div>
            <div v-if="advanced" id="report-more-filters" class="ra-more-filters">
                <label v-if="meta.filters.includes('staff')">Staff<AppSelect v-model="staff"><option value="">All permitted staff</option><option v-if="result.filters.staff_ids.length>1" value="__selection">{{ result.filters.staff_ids.length }} selected team members</option><option v-for="item in filterOptions.staff" :key="item.id" :value="item.id">{{ item.display_name }}</option></AppSelect></label>
                <label v-if="meta.filters.includes('service')">Service<AppSelect v-model="service"><option value="">All services</option><option v-if="result.filters.service_ids.length>1" value="__selection">{{ result.filters.service_ids.length }} selected services</option><option v-for="item in filterOptions.services" :key="item.id" :value="item.id">{{ item.name }}</option></AppSelect></label>
                <label v-if="meta.filters.includes('status')">Status<AppSelect v-model="status"><option value="">{{ selected==='cancellation_no_show' ? 'All cancellation / no-show outcomes' : 'Default statuses' }}</option><option v-if="result.filters.statuses.length>1" value="__selection">{{ result.filters.statuses.map(label).join(', ') }}</option><option v-for="item in filterOptions.statuses.filter(item=>['overview','sales','payment_method'].includes(selected) ? ['open','completed'].includes(item) : !['open'].includes(item))" :key="item" :value="item">{{ label(item) }}</option></AppSelect></label>
                <label v-if="meta.filters.includes('method')">Payment method<AppSelect v-model="method"><option value="">All recorded methods</option><option v-for="item in filterOptions.methods || []" :key="item" :value="item">{{ label(item) }}</option></AppSelect></label>
            </div>
        </form>
        <p v-if="new Set(filterOptions.locations.map(item=>item.time_zone)).size>1" class="ra-source-note">These locations use different time zones. Choose one location for consistent local date boundaries.</p>
        <div v-if="error" class="ra-error" role="alert">{{ error }} <button type="button" class="ra-text-link" @click="navigate()">Retry</button></div>
        <div v-if="hasFilters" class="ra-active-filters" aria-label="Active report filters"><button v-if="result.filters.dimension_value" type="button" @click="navigate({dimension:'',dimension_value:''})">{{ label(result.filters.dimension) }}: {{ result.filters.dimension_value==='__unattributed' ? 'Unattributed' : label(result.filters.dimension_value) }} <XMarkIcon/></button><button v-if="staff" @click="staff='';navigate()" type="button">{{ filterOptions.staff.find(item=>String(item.id)===String(staff))?.display_name || (staff==='__selection' ? `${result.filters.staff_ids.length} selected team members` : 'Staff') }} <XMarkIcon/></button><button v-if="service" @click="service='';navigate()" type="button">{{ filterOptions.services.find(item=>String(item.id)===String(service))?.name || (service==='__selection' ? `${result.filters.service_ids.length} selected services` : 'Service') }} <XMarkIcon/></button><button v-if="status" @click="status='';navigate()" type="button">{{ status==='__selection' ? result.filters.statuses.map(label).join(', ') : label(status) }} <XMarkIcon/></button><button v-if="method" @click="method='';navigate()" type="button">{{ label(method) }} <XMarkIcon/></button></div>
        <section class="ra-report-heading"><div><p class="ra-eyebrow">{{ meta.group }} <span> / {{ result.filters.start_date }} – {{ result.filters.end_date }}</span></p><h2>{{ meta.title }}</h2><p>{{ meta.description }}</p></div><span v-if="result.previous_period" class="ra-comparison-range">Compared with<br/><strong>{{ previousLabel }}</strong></span></section>
        <section :style="{'--ra-metric-count':metricKeys.length}" class="ra-metrics" aria-label="Report summary" :aria-busy="changing">
            <button v-for="key in metricKeys" :key="key" type="button" class="ra-metric" :disabled="changing" @click="inspectMetric(key)">
                <span class="ra-metric-label">{{ columnLabel(key) }} <InformationCircleIcon v-if="meta.definitions[key]" class="size-3.5" :title="meta.definitions[key]"/></span>
                <span v-if="changing" class="ra-skeleton ra-skeleton-value" aria-hidden="true"/><strong v-else>{{ format(key,result.totals[key]) }}</strong>
                <span v-if="result.previous_period && !changing" class="ra-metric-comparison"><b>{{ comparison(key).absolute }}</b> · {{ comparison(key).text }}<span class="ds-sr-only">. Previous value {{ format(key,result.previous_period.totals[key]) }}</span></span><span v-else class="ra-metric-comparison">{{ key==='row_count' ? 'Matching the selected filters' : result.totals[key]===null ? 'No eligible source values' : 'Selected period' }}</span>
            </button>
        </section>
        <details class="ra-definitions"><summary><InformationCircleIcon class="size-4"/>How these numbers are calculated</summary><dl><div v-for="key in metricKeys" :key="key"><dt>{{ columnLabel(key) }}</dt><dd>{{ meta.definitions[key] || 'Sum of the corresponding recorded values across all matching records.' }}<span v-if="result.previous_period"> Previous: {{ format(key,result.previous_period.totals[key]) }}.</span></dd></div></dl></details>
        <div v-if="result.chart_error" class="ra-error" role="status">{{ result.chart_error }} <button type="button" @click="navigate()">Retry analysis</button></div>
        <div v-if="result.totals.row_count>0 || changing" class="ra-analysis-grid" :class="{'ra-analysis-single':!result.trend?.points?.length}" :aria-busy="changing">
            <section v-if="result.trend?.points?.length || changing" class="ra-panel ra-trend-panel"><div class="ra-panel-header"><div><h3>{{ columnLabel(result.trend?.metric || metricKeys[0]) }} over time</h3><p>{{ result.trend?.granularity==='hour' ? 'Select a point to inspect this day’s records.' : 'Select a point to inspect its date range.' }}</p></div><label>Group by<AppSelect v-model="granularity" :disabled="changing" @change="navigate()"><option value="auto">Auto</option><option value="day">Day</option><option value="week">Week</option><option value="month">Month</option><option v-if="result.filters.start_date===result.filters.end_date" value="hour">Hour</option></AppSelect></label></div><div v-if="changing" class="ra-skeleton ra-skeleton-chart"/><ReportTrend v-else :trend="result.trend" :format="value=>format(result.trend.metric,value)" :comparison-label="result.filters.compare==='year' ? 'Last year (same dates)' : 'Previous equal period'" @select="inspectPoint"/></section>
            <section v-if="result.ranking?.rows?.length || changing" class="ra-panel ra-ranking-panel"><div class="ra-panel-header"><div><h3>{{ selected==='overview' ? 'Service mix' : `${columnLabel(result.ranking?.metric || metricKeys[0])} by ${label(result.ranking?.category || 'category').toLowerCase()}` }}</h3><p>{{ selected==='overview' ? 'After discount · before returns and tips' : 'Up to six largest matching categories' }}</p></div></div><div v-if="changing" class="ra-skeleton ra-skeleton-chart"/><div v-else class="ra-ranking"><button v-for="(row,index) in result.ranking.rows" :key="index" type="button" @click="inspectRanking(row)"><span class="ra-rank-top"><span :title="label(row.label)">{{ label(row.label) }}</span><b>{{ format(result.ranking.metric,row.value) }}</b></span><span class="ra-rank-track"><i :style="{width:`${Math.abs(row.value)/rankMax*100}%`}"/></span></button></div></section>
        </div>
        <details v-if="result.patterns?.days?.length && result.totals.row_count" class="ra-panel ra-weekly"><summary><span><strong>Day-of-week pattern</strong><span>Aggregate totals for this period · {{ columnLabel(result.patterns.metric) }}</span></span><ChevronDownIcon class="size-4"/></summary><div class="ra-weekly-days"><div v-for="day in result.patterns.days" :key="day.label"><strong>{{ day.label }}</strong><b>{{ format(result.patterns.metric,day.value) }}</b><span>{{ day.records }} {{ selected==='overview' ? 'sales' : 'visits' }}</span></div></div></details>
        <div v-if="selected==='overview' && result.previous_period && result.totals.row_count" class="ra-insight"><ChartBarSquareIcon class="size-5"/><p><strong>Follow the change.</strong> {{ result.totals.row_count }} sales compared with {{ result.previous_period.totals.row_count }}. <span v-if="result.totals.average_ticket_minor!==null && result.previous_period.totals.average_ticket_minor!==null">Average sale value is {{ comparison('average_ticket_minor').text.toLowerCase() }}.</span> <button type="button" class="ra-text-link" @click="navigate({},'sales')">Investigate sales <ArrowRightIcon class="size-3.5"/></button></p></div>
        <section id="report-records" class="ra-panel ra-records" tabindex="-1" :aria-busy="changing">
            <div class="ra-records-header"><div><h3>{{ selected==='overview' ? 'Sales behind the summary' : 'Underlying records' }}</h3><p>Full filtered totals · {{ result.totals.row_count.toLocaleString() }} {{ result.totals.row_count===1 ? 'record' : 'records' }}</p></div><div class="ra-record-controls"><SearchField v-model="search" label="Search this report" placeholder="Search records…" @input="searchChanged"/><details class="ra-column-menu"><summary><ChartBarSquareIcon class="size-4"/>Columns<ChevronDownIcon class="size-3.5"/></summary><div class="ra-column-options"><label v-for="key in availableColumns" :key="key"><input v-model="activeColumns" :value="key" type="checkbox" :disabled="activeColumns.length===1 && activeColumns.includes(key)"/>{{ columnLabel(key) }}</label></div></details></div></div>
            <div v-if="changing" class="ra-table-skeleton"><div v-for="index in 5" :key="index" class="ra-skeleton"/></div>
            <div v-else-if="!result.rows.length" class="ra-empty"><ChartBarSquareIcon class="size-7"/><h3>{{ search ? 'No records match your search' : 'No records in this period' }}</h3><p>{{ search ? 'Try a different reference, name or category.' : 'Change the date range or clear filters to explore another period.' }}</p><div><AppButton v-if="hasFilters" variant="secondary" @click="clearFilters">Clear filters</AppButton><AppButton variant="quiet" @click="setPreset('30')">View last 30 days</AppButton></div></div>
            <template v-else>
                <DataTable class="ra-table" :caption="`${meta.title}: filtered underlying records`"><thead><tr><th v-for="column in columns" :key="column" scope="col" :class="{'ra-numeric':column.endsWith('_minor') || column.endsWith('_minutes') || column.endsWith('_count') || column.endsWith('_percent') || column==='quantity'}" :aria-sort="result.pagination.sort===column ? result.pagination.direction==='asc' ? 'ascending' : 'descending' : 'none'"><button type="button" @click="sort(column)">{{ columnLabel(column) }}<span v-if="result.pagination.sort===column" aria-hidden="true">{{ result.pagination.direction==='asc' ? '↑' : '↓' }}</span></button></th><th scope="col"><span class="ds-sr-only">Inspect record</span></th></tr></thead><tbody><tr v-for="(row,index) in result.rows" :key="`${row.ledger || ''}:${row.source_id || row.staff_id || row.client_id || index}`"><td v-for="column in columns" :key="column" :class="{'ra-numeric':column.endsWith('_minor') || column.endsWith('_minutes') || column.endsWith('_count') || column.endsWith('_percent') || column==='quantity'}"><span v-if="column==='status' || column==='classification'" class="ra-status" :class="{'ra-status-finished':row[column]==='completed','ra-status-exception':['no_show','cancelled_by_client','cancelled_by_shop'].includes(row[column])}">{{ format(column,row[column]) }}</span><span v-else :title="format(column,row[column],true)">{{ format(column,row[column]) }}</span></td><td><button class="ra-inspect" type="button" :aria-label="`Inspect ${row.service || row.staff || row.location || row.sale || row.appointment || 'record '+(index+1)}`" @click="inspect(row)">Details<ArrowRightIcon class="size-3.5"/></button></td></tr></tbody><tfoot v-if="columns.some(key=>Object.hasOwn(result.totals,key))"><tr><td v-for="(column,index) in columns" :key="column" :class="{'ra-numeric':column.endsWith('_minor') || column.endsWith('_minutes') || column.endsWith('_count') || column.endsWith('_percent') || column==='quantity'}">{{ Object.hasOwn(result.totals,column) ? format(column,result.totals[column]) : index===0 ? 'All filtered records' : '—' }}</td><td/></tr></tfoot></DataTable>
                <div class="ra-mobile-records"><button v-for="(row,index) in result.rows" :key="index" type="button" @click="inspect(row)"><span class="ra-mobile-record-heading"><strong>{{ row.service || row.staff || row.location || (row.sale ? format('sale',row.sale) : row.appointment ? format('appointment',row.appointment) : row.client ? format('client',row.client) : label(row.method || row.classification || row.kind || 'Record')) }}</strong><ArrowRightIcon class="size-4"/></span><span v-for="key in columns.filter(key=>!['service','staff','location'].includes(key)).slice(0,4)" :key="key"><span>{{ columnLabel(key) }}</span><b>{{ format(key,row[key]) }}</b></span></button></div>
                <div class="ra-pagination"><span>{{ result.pagination.from.toLocaleString() }}–{{ result.pagination.to.toLocaleString() }} of {{ result.pagination.total.toLocaleString() }}<span class="ra-pagination-note"> · Totals cover every matching record</span></span><div><AppButton variant="quiet" size="small" :disabled="result.pagination.current_page<=1" @click="navigate({page:result.pagination.current_page-1})" aria-label="Previous page"><ChevronLeftIcon class="size-4"/></AppButton><span>{{ result.pagination.current_page }} / {{ result.pagination.last_page }}</span><AppButton variant="quiet" size="small" :disabled="result.pagination.current_page>=result.pagination.last_page" @click="navigate({page:result.pagination.current_page+1})" aria-label="Next page"><ChevronRightIcon class="size-4"/></AppButton></div></div>
            </template>
        </section>
        <p v-if="sourceNote" class="ra-source-note">{{ sourceNote }}</p>
        <section v-if="selected==='overview'" class="ra-explore"><div><h3>Explore your business</h3><p>Keep this period and location as you investigate.</p></div><div class="ra-explore-grid"><button v-for="key in quickReports" :key="key" type="button" @click="navigate({},key)"><span>{{ reportCatalog[key].group }}</span><strong>{{ reportCatalog[key].title }}<ArrowRightIcon class="size-4"/></strong><p>{{ reportCatalog[key].description }}</p></button></div><p v-if="recent.filter(key=>key!=='overview').length" class="ra-recent">Recently viewed <button v-for="key in recent.filter(key=>key!=='overview')" :key="key" type="button" @click="navigate({},key)">{{ reportCatalog[key].title }}</button></p></section>
        <footer class="ra-footer"><span>{{ result.source }}</span><span>Loaded {{ reportDateTime(result.fresh_at,result.time_zone,locale) }} · Definitions v{{ result.metric_version }}</span></footer>
        <AppDialog ref="detail" title="Record detail" drawer description="Values from the selected report. Open the source to inspect the original operational record." @cancel="record=null"><template v-if="record"><p class="ra-detail-context">{{ meta.title }} · {{ result.filters.start_date }} – {{ result.filters.end_date }}</p><dl class="ra-detail-values"><div v-for="key in detailColumns" :key="key"><dt>{{ columnLabel(key) }}</dt><dd>{{ format(key,record[key],true) }}</dd></div></dl></template><template #footer><AppButton variant="secondary" @click="detail.close();record=null">Close</AppButton><AppButton v-if="record?.drill" :href="record.drill">Open source<ArrowRightIcon class="size-4"/></AppButton></template></AppDialog>
    </div>
</AppLayout>
</template>
