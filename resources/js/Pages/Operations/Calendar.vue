<script setup>
import { deliveryLabel } from '@/Support/notificationWorkspace';
import AppSelect from '@/Components/Product/AppSelect.vue';
import '../../../css/calendar.css';
import AppointmentCard from '@/Components/Calendar/AppointmentCard.vue';
import { attentionItems } from '@/Support/dailyWorkspace';
import { canMove, clockInput, dateKey, dayInterval, layoutEntries, matchesSearch, staffEntries, subtractBusy, terminalStatuses, wallMinute } from '@/Support/calendarWorkspace';
import { serviceVariantAt, calendarServiceChoices } from '@/Support/serviceCatalog';
import axios from 'axios';
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowsPointingOutIcon, BanknotesIcon, ClockIcon, DocumentDuplicateIcon, PencilSquareIcon, PrinterIcon, ScissorsIcon, TrashIcon, UserIcon, ArrowPathIcon, AdjustmentsHorizontalIcon, CalendarDaysIcon, ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon, CheckCircleIcon, ExclamationTriangleIcon, MagnifyingGlassIcon, PlusIcon, QueueListIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import FormField from '@/Components/Product/FormField.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    businessLabel: String,
    calendar: Object,
    schedule: Object,
    filters: Object,
    options: Object,
    permissions: Object,
    bookingRules: Object,
    clientPrefill: Object,
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
const detailDialog = ref(null);
const noShowDialog = ref(null);
const attentionOpen = ref(false);
const filterMenu = ref(null);
function closeFilters(event) {
    if(filterMenu.value?.open && !filterMenu.value.contains(event.target) && !event.target.closest('.cd-select-panel')) filterMenu.value.open=false;
}
const search = ref('');
const scheduleScroll = ref(null);
const refreshBusy = ref(false);
const navigationError = ref('');
const mobileStaff = ref('');
const page = usePage();
const now = ref(Date.parse(props.calendar.currentTime));
const elapsedAnchor = ref(Date.now());
const serverAnchor = ref(now.value);
const views = [{ id: 'staff', label: 'Team' }, { id: 'day', label: 'Day' }, { id: 'week', label: 'Week' }, { id: 'agenda', label: 'Agenda' }];
const appointments = computed(() => props.calendar.events.filter(event => event.type === 'appointment'));
const visibleAppointments = computed(() => appointments.value.filter(event => matchesSearch(event, search.value)));
const auxiliaryEvents = computed(() => props.calendar.events.filter(event => event.type === 'walk_in'));
const inputClass = 'cd-input';
const activeFilters = computed(() => ['staff','service','status'].reduce((n,key)=>n+(props.filters[key]?.length || 0),0));
const issues = computed(() => attentionItems(appointments.value,now.value,props.filters.date === currentDateKey.value).concat(
    appointments.value.filter(e=>e.checkoutReady).map(event=>({event,reasons:['Awaiting checkout'],priority:4})),
).sort((a,b)=>a.priority-b.priority));
const attentionCount = computed(() => issues.value.length + props.calendar.counts.walkInsWaiting);
async function selectAppointment(event) {
    activeEvent.value = appointments.value.find(e=>e.id===event.id) || event;
    statusForm.clearErrors();
    await detailDialog.value?.open();
}
function reviewAction(action) {
    detailDialog.value?.close();
    nextTick(action);
}
watch(() => props.calendar.events, () => {
    if(activeEvent.value) activeEvent.value = appointments.value.find(e=>e.id===activeEvent.value.id) || null;
});
watch(() => [props.filters.date,props.filters.location], () => {
    activeEvent.value=null; detailDialog.value?.close(); mobileStaff.value=''; drag.value=null;
});
const localDateLabel = computed(() => {
    const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: props.filters.view === 'week' ? 'medium' : 'full', timeZone: props.calendar.timeZone });
    if (props.filters.view !== 'week') return formatter.format(new Date(props.calendar.range.startsAt));
    return formatter.formatRange(new Date(props.calendar.range.startsAt), new Date(new Date(props.calendar.range.endsAt).getTime() - 1));
});
const timeZoneShort = computed(()=>new Intl.DateTimeFormat(undefined,{timeZone:props.calendar.timeZone,timeZoneName:'short'}).formatToParts(new Date(now.value)).find(p=>p.type==='timeZoneName')?.value);
const initials = name => name.split(/\s+/).filter(Boolean).slice(0,2).map(n=>n[0]).join('').toUpperCase();
const agendaDate = date => new Intl.DateTimeFormat(undefined,{weekday:'short',day:'numeric',month:'short',timeZone:'UTC'}).format(new Date(`${date}T12:00:00Z`));
const sourceLabel = source => ({online:'Online booking',reception:'Reception',phone:'Phone',walk_in:'Walk-in',waitlist:'Waitlist',recurring:'Recurring',consultation:'Consultation',self_service:'Client self-service'}[source] || 'Reception');
const localTime = value => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: props.calendar.timeZone }).format(new Date(value));
const localDateKey = value => {
    const parts = zonedParts(new Date(value));
    return `${parts.year}-${parts.month}-${parts.day}`;
};
const preferenceKey = () => `clipperdesk.calendar.${page.props.auth?.user?.id || 'local'}.${route().params.business}`;
const applyFilters = overrides => {
    const next = {...props.filters,...overrides};
    const resetScroll = ['date','location','view'].some(key=>next[key]!==props.filters[key]);
    navigationError.value='';
    router.get(route('business.calendar',route().params.business),next,{
        preserveState:true, preserveScroll:true,
        onStart:()=>refreshBusy.value=true,
        onError:()=>navigationError.value='The schedule could not be updated. Try again.',
        onFinish:()=>refreshBusy.value=false,
        onSuccess:()=>{try {localStorage.setItem(preferenceKey(),JSON.stringify({view:next.view,staff:next.staff,service:next.service,status:next.status}));} catch { /* Device storage is optional. */ } if(resetScroll)nextTick(scrollToUsefulTime);},
    });
};
const shiftDate = amount => {
    const [year,month,day]=props.filters.date.split('-').map(Number);
    const date=new Date(Date.UTC(year,month-1,day+amount*(props.filters.view==='week'?7:1)));
    applyFilters({date:date.toISOString().slice(0,10)});
};
const goToday = () => applyFilters({date:dateKey(now.value,props.calendar.timeZone)});
function refreshSchedule() {
    if(refreshBusy.value || document.visibilityState!=='visible' || document.querySelector('dialog[open],details[open]') || document.activeElement?.closest('input,select,textarea,[role=combobox]') || [createForm,changeForm,statusForm,cancelForm,noteForm,blockForm,exceptionForm,closureForm,copyForm].some(form=>form.processing)) return;
    router.reload({only:['calendar','schedule','options','permissions','bookingRules'],onStart:()=>refreshBusy.value=true,onError:()=>navigationError.value='The schedule could not be refreshed. Try again.',onFinish:()=>refreshBusy.value=false});
}
const clearFilters = () => {search.value=''; applyFilters({staff:[],service:[],status:[]});};
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
const selectedServiceNotice = () => Math.max(0,...createForm.lines.map(line=>Number(props.options.services.find(service=>service.public_id===line.service)?.minimum_notice_minutes || 0)));
const firstAllowedStart = () => {
    const interval=Math.max(1,Number(props.bookingRules?.intervalMinutes || 15));
    const earliest=new Date(now.value+selectedServiceNotice()*60_000+59_999);
    const localMinutes=wallMinute(earliest,props.calendar.timeZone);
    earliest.setTime(earliest.getTime()+((interval-localMinutes%interval)%interval)*60_000);
    earliest.setSeconds(0,0);
    return localInputInCalendarZone(earliest);
};
const totalDuration = computed(() => createForm.lines.reduce((total,line) => {
    const service=props.options.services.find(s=>s.public_id===line.service);
    const variant = serviceVariantAt(service, line.staff, createForm.starts_at);
    return total+Number(line.duration_minutes || (Number(variant?.duration_minutes ?? service?.duration_minutes ?? 0)+Number(variant?.processing_minutes ?? service?.processing_minutes ?? 0)+Number(variant?.cleanup_minutes ?? service?.cleanup_minutes ?? 0)));
},0));
const clientSearch = ref('');
const clientResults = ref([]);
const clientSearchBusy = ref(false);
const clientSearchError = ref('');
const selectedClientMobile = ref('');
const normalizeMobile = value => (value || '').replace(/[\s().-]+/g, '');
const selectedClientNeedsMobile = computed(() => !!createForm.client && props.permissions.contact && (!!createForm.errors.client_mobile || !/^\+[1-9]\d{6,14}$/.test(normalizeMobile(selectedClientMobile.value))));
let clientTimer, clientRequest;
watch(clientSearch,value=>{
    clearTimeout(clientTimer);clientRequest?.abort();clientResults.value=[];clientSearchError.value='';clientSearchBusy.value=false;
    if(value.trim().length<2) return;
    clientSearchBusy.value=true;
    clientTimer=setTimeout(async()=>{
        const request=new AbortController(); clientRequest=request;clientSearchBusy.value=true;
        try { const result=await axios.get(route('business.calendar.clients',route().params.business),{params:{search:value.trim()},signal:request.signal}); if(!request.signal.aborted) clientResults.value=result.data.clients; }
        catch(error){if(!request.signal.aborted) clientSearchError.value='Client search is unavailable. You can enter client details below.';}
        finally {if(clientRequest===request)clientSearchBusy.value=false;}
    },250);
});
const chooseClient = client => {
    selectedClientMobile.value=client.mobile || '';
    createForm.client=client.id;createForm.client_name=client.name;createForm.client_mobile=normalizeMobile(client.mobile);createForm.client_email=client.email || '';
    createForm.clearErrors('client_name','client_mobile','client_email');
    clientSearch.value='';clientResults.value=[];
};
const clearClient = () => {selectedClientMobile.value='';createForm.client='';createForm.client_name='';createForm.client_mobile='';createForm.client_email='';createForm.clearErrors('client_name','client_mobile','client_email');};

const createForm = useForm({
    client: '', location: props.filters.location, starts_at: '', source: 'reception', client_name: '', client_mobile: '', client_email: '', internal_notes: '',
    lines: [{ service: props.options.services.find(s => s.kind !== 'addon')?.public_id || '', staff: '', duration_minutes: null }],
    idempotency_key: '', override_rule_codes: [], override_reason: '', override_confirmed: false,
});
const eligibleStaff = (line, at) => props.options.bookableStaff.filter(member => serviceVariantAt(props.options.services.find(s => s.public_id === line.service), member.public_id, at));
const bookingServiceChoices = (lines, at) => calendarServiceChoices(props.options.services, lines, at).filter(s => props.options.bookableStaff.some(p => serviceVariantAt(s,p.public_id,at)));
const createPriceLabel = computed(() => {
    let minimum = 0, maximum = 0;
    for (const line of createForm.lines) {
        const service = props.options.services.find(s => s.public_id === line.service);
        if (!service) continue;
        const candidates = line.staff ? [line.staff] : eligibleStaff(line, createForm.starts_at).map(p => p.public_id);
        if (candidates.some(id => !serviceVariantAt(service, id, createForm.starts_at))) return 'Assign eligible staff to confirm price';
        const prices = candidates.map(id => serviceVariantAt(service, id, createForm.starts_at)?.price_minor ?? service.location_price_minor ?? service.price_minor);
        if (!prices.length) return 'Assign eligible staff to confirm price';
        minimum += Math.min(...prices); maximum += Math.max(...prices);
    }
    const currency = props.options.services.find(s => s.public_id === createForm.lines[0]?.service)?.currency_code || 'INR';
    const format = minor => new Intl.NumberFormat(undefined,{style:'currency', currency}).format(minor / 100);
    return `${minimum === maximum ? format(minimum) : `${format(minimum)}–${format(maximum)}`} estimated service price`;
});
watch(() => [createForm.starts_at, ...createForm.lines.map(l => l.service)], () => {
    for (const line of createForm.lines) if (line.staff && !eligibleStaff(line, createForm.starts_at).some(p => p.public_id === line.staff)) line.staff = '';
});
const submitCreate = () => {
    const interval = Math.max(1, Number(props.bookingRules?.intervalMinutes || 15));
    const [selectedHour,selectedMinute]=createForm.starts_at.slice(11,16).split(':').map(Number);
    if (!createForm.starts_at || (selectedHour*60+selectedMinute) % interval !== 0) {
        createForm.setError('booking', `Choose a time in ${interval}-minute booking intervals.`);
        return;
    }
    if (!createForm.override_confirmed && createForm.starts_at < firstAllowedStart()) {
        createForm.setError('booking', 'Choose a time at or after the minimum booking notice, or select the manager notice override below.');
        return;
    }
    if(createForm.override_confirmed && !createForm.override_reason.trim()) {createForm.setError('booking','Explain why the booking notice is being overridden.');return;}
    createForm.override_rule_codes=createForm.override_confirmed ? ['NOTICE_WINDOW'] : [];
    createForm.clearErrors('booking');
    createForm.idempotency_key ||= `calendar-create-${crypto.randomUUID()}`;
    createForm.post(route('business.appointments.store', route().params.business), { preserveScroll: true, onSuccess: () => { createDialog.value?.close(); createForm.reset('client','client_name', 'client_mobile', 'client_email', 'internal_notes','idempotency_key'); } });
};
const addCreateLine = () => createForm.lines.push({ service: bookingServiceChoices(createForm.lines, createForm.starts_at).find(s => s.kind !== 'addon')?.public_id || '', staff: '', duration_minutes: null });

const changeForm = useForm({
    kind: 'reschedule', location: props.filters.location, starts_at: '', lines: [], version: 1, reason: '', confirmed: true,
    idempotency_key: '', override_rule_codes: [], override_reason: '', override_confirmed: false, client_name: '', client_mobile: '', client_email: '', internal_notes: '',
});
const openChange = (event, kind = 'reschedule', startsAt = null) => {
    activeEvent.value = event;
    changeForm.clearErrors();
    changeForm.override_confirmed = false;
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
const addChangeLine = () => changeForm.lines.push({ service: bookingServiceChoices(changeForm.lines, changeForm.starts_at).find(s => s.kind !== 'addon')?.public_id || '', staff: '', duration_minutes: null });
const submitChange = () => {
    changeForm.idempotency_key ||= `calendar-${changeForm.kind}-${crypto.randomUUID()}`;
    changeForm.override_rule_codes = changeForm.override_confirmed ? ['NOTICE_WINDOW'] : [];
    changeForm.override_reason = changeForm.override_confirmed ? changeForm.reason : '';
    changeForm.post(route('business.appointments.replace', [route().params.business, activeEvent.value.id]), { preserveScroll: true, onSuccess: () => { changeDialog.value?.close(); activeEvent.value = null; } });
};
const changeTitle = computed(() => ({ reschedule: 'Reschedule appointment', resize: 'Adjust appointment duration', reassign: 'Reassign team member', services_changed: 'Update appointment services' }[changeForm.kind]));
const changeDescription = computed(() => ({ reschedule: 'Choose the new start time. Availability is checked again before saving.', resize: 'Adjust only the time this visit needs; services and staff stay attached.', reassign: 'Choose who will deliver each service. Qualifications and availability are rechecked.', services_changed: 'Update this visit’s service list. The previous version stays safely in history.' }[changeForm.kind]));

const statusForm = useForm({status:'',version:1,reason:null,confirmed:false,idempotency_key:''});
const transition = (event, status, reason=null, confirmed=false) => {
    if(statusForm.processing) return;
    statusForm.clearErrors();statusForm.status=status;statusForm.version=event.version;statusForm.reason=reason;statusForm.confirmed=confirmed;
    statusForm.idempotency_key=`status-${event.id}-${status}-${crypto.randomUUID()}`;
    statusForm.patch(route('business.appointments.status',[route().params.business,event.id]),{preserveScroll:true,onSuccess:()=>noShowDialog.value?.close()});
};
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
const blockForm = useForm({ location: props.filters.location, staff: props.options.bookableStaff[0]?.public_id || '', kind: 'personal_block', label: '', reason: '', starts_at: `${props.filters.date}T12:00`, ends_at: `${props.filters.date}T12:30`, confirmed: true });
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
const openCreate = (slot = null) => {
    createForm.location = props.filters.location;
    createForm.starts_at = slot ? clockInput(slot.date,slot.minute) : suggestedStart();
    const choices = bookingServiceChoices(createForm.lines, createForm.starts_at);
    for (const line of createForm.lines) if (!choices.some(service => service.public_id === line.service)) {
        line.service = choices.find(service => service.kind !== 'addon')?.public_id || '';
        line.staff = '';
    }
    if (slot?.staff && props.options.bookableStaff.some(m => m.public_id === slot.staff)) {
        const first = createForm.lines[0];
        if (!eligibleStaff(first, createForm.starts_at).some(m => m.public_id === slot.staff)) {
            const compatible = bookingServiceChoices(createForm.lines, createForm.starts_at).find(s => serviceVariantAt(s,slot.staff,createForm.starts_at));
            if (compatible) first.service = compatible.public_id;
        }
        first.staff = eligibleStaff(first,createForm.starts_at).some(m => m.public_id === slot.staff) ? slot.staff : '';
    }
    for (const line of createForm.lines) if (!eligibleStaff(line,createForm.starts_at).some(m => m.public_id === line.staff)) line.staff = '';
    createForm.clearErrors();
    createForm.override_confirmed=false;createForm.override_reason='';createForm.idempotency_key='';
    createDialog.value?.open();
};
watch(() => [props.filters.date, props.filters.location], () => {
    blockForm.location = closureForm.location = props.filters.location;
    blockForm.starts_at = `${props.filters.date}T12:00`;
    blockForm.ends_at = `${props.filters.date}T12:30`;
    closureForm.starts_at = `${props.filters.date}T09:00`;
    closureForm.ends_at = `${props.filters.date}T18:00`;
});
const hourHeight = 64;
const interval = computed(()=>Math.max(1,Number(props.bookingRules?.intervalMinutes || 15)));
const currentDateKey = computed(()=>dateKey(now.value,props.calendar.timeZone));
const selectedDay = computed(()=>props.schedule.days.find(d=>d.date===props.filters.date) || props.schedule.days[0]);
const dayTeam = computed(()=>selectedDay.value?.staff || []);
const allIntervals = computed(()=>[...props.schedule.days.flatMap(d=>[...d.windows,...d.staff.flatMap(m=>m.busy)].map(w=>dayInterval(w,d.date,props.calendar.timeZone))),...appointments.value.flatMap(e=>props.schedule.days.map(d=>dayInterval(e,d.date,props.calendar.timeZone)))].filter(Boolean));
const calendarStartMinute = computed(()=>allIntervals.value.length ? Math.max(0,Math.floor(Math.min(...allIntervals.value.map(i=>i.start))/60)*60) : 8*60);
const calendarEndMinute = computed(()=>allIntervals.value.length ? Math.min(1440,Math.ceil(Math.max(...allIntervals.value.map(i=>i.end))/60)*60) : 19*60);
const timelineHeight = computed(()=>(calendarEndMinute.value-calendarStartMinute.value)*hourHeight/60);
const hours = computed(()=>Array.from({length:Math.ceil((calendarEndMinute.value-calendarStartMinute.value)/60)},(_,i)=>calendarStartMinute.value/60+i));
const clockLabel = minute => `${String(Math.floor(minute/60)).padStart(2,'0')}:${String(minute%60).padStart(2,'0')}`;
const rangePosition = (range,date) => {
    const i=dayInterval(range,date,props.calendar.timeZone);
    if(!i) return {display:'none'};
    const from=Math.max(calendarStartMinute.value,i.start), until=Math.min(calendarEndMinute.value,i.end);
    return {top:`${(from-calendarStartMinute.value)*hourHeight/60}px`,height:`${Math.max(0,until-from)*hourHeight/60}px`};
};
const todayTeam = computed(()=>props.schedule.days.find(d=>d.date===currentDateKey.value)?.staff || []);
const staffState = member => {
    if(member.status && member.status!=='active') return 'Inactive';
    if(!member.windows.length) return member.unavailable?.some(w=>['leave','sick_leave','holiday'].includes(w.kind)) ? 'Time off' : 'Off';
    if(props.filters.date !== currentDateKey.value) return 'Scheduled';
    if(member.busy.some(w=>w.status==='in_service')) return 'In service';
    if(member.busy.some(w=>Date.parse(w.startsAt)<=now.value && Date.parse(w.endsAt)>now.value)) return 'Busy';
    return member.windows.some(w=>Date.parse(w.startsAt)<=now.value && Date.parse(w.endsAt)>now.value) ? 'Available' : 'Off shift';
};
const nextGap = member => {
    if(props.filters.date<currentDateKey.value || selectedDay.value?.clockChanges || !props.options.bookableStaff.some(m=>m.public_id===member.id)) return null;
    const earliest=firstAllowedStart();
    return subtractBusy(member.windows,member.busy).map(range=>{
        let from=Date.parse(range.startsAt);
        if(props.filters.date===currentDateKey.value) {
            const parts=earliest.slice(11).split(':').map(Number);
            const desired=parts[0]*60+parts[1];
            if(earliest.slice(0,10)!==props.filters.date) return null;
            from+=Math.max(0,desired-wallMinute(from,props.calendar.timeZone))*60_000;
        }
        const minutes=wallMinute(from,props.calendar.timeZone);
        from+=((interval.value-minutes%interval.value)%interval.value)*60_000;
        return Date.parse(range.endsAt)-from>=interval.value*60_000 ? {...range,startsAt:new Date(from).toISOString()} : null;
    }).find(Boolean);
};
const operational = computed(()=>({
    waiting:appointments.value.filter(e=>['arrived','checked_in'].includes(e.status)).length,
    underway:appointments.value.filter(e=>e.status==='in_service').length,
    available:todayTeam.value.filter(m=>staffState(m)==='Available').length,
    scheduled:dayTeam.value.filter(m=>m.windows.length).length,
}));
const nextAppointment = computed(()=>appointments.value.find(e=>!terminalStatuses.has(e.status) && Date.parse(e.startsAt)>=now.value));
const scheduleColumns = computed(()=>{
    if(props.filters.view==='week') return props.schedule.days.map(day=>({id:day.date,date:day.date,label:new Intl.DateTimeFormat(undefined,{weekday:'short',day:'numeric',timeZone:'UTC'}).format(new Date(`${day.date}T12:00:00Z`)),type:'date',windows:day.windows,team:day.staff}));
    if(props.filters.view==='day') return [{id:props.filters.date,date:props.filters.date,label:'All appointments',type:'date',windows:selectedDay.value?.windows || [],team:dayTeam.value}];
    return [...dayTeam.value.map(member=>({...member,date:props.filters.date,label:member.name,type:'staff'})),...(appointments.value.some(e=>e.unassigned)?[{id:'unassigned',date:props.filters.date,label:'Unassigned',type:'staff',windows:[],busy:[],unavailable:[]}]:[])];
});
const gridStyle = computed(()=>({gridTemplateColumns:`60px repeat(${Math.max(1,scheduleColumns.value.length)},minmax(${props.filters.view==='week'?155:190}px,1fr))`, minWidth:`${60+Math.max(1,scheduleColumns.value.length)*(props.filters.view==='week'?155:190)}px`}));
const columnData = computed(()=>scheduleColumns.value.map(column=>{
    const entries=column.type==='staff'?staffEntries(visibleAppointments.value,column.id):visibleAppointments.value;
    const cards=layoutEntries(entries,column.date,props.calendar.timeZone,calendarStartMinute.value,calendarEndMinute.value,hourHeight);
    const gaps=column.type==='staff' && !selectedDay.value?.clockChanges && props.options.bookableStaff.some(m=>m.public_id===column.id)?subtractBusy(column.windows,column.busy):[];
    const slots=gaps.flatMap(range=>{
        const i=dayInterval(range,column.date,props.calendar.timeZone);
        if(!i) return [];
        const from=Math.ceil(Math.max(i.start,calendarStartMinute.value)/interval.value)*interval.value;
        return Array.from({length:Math.max(0,Math.floor((Math.min(i.end,calendarEndMinute.value)-from)/interval.value))},(_,n)=>({minute:from+n*interval.value,date:column.date,staff:column.id}));
    }).filter(slot=>column.date>currentDateKey.value || (column.date===currentDateKey.value && slot.minute>=wallMinute(now.value,props.calendar.timeZone)+selectedServiceNotice()));
    const visibleIds=new Set(cards.map(e=>e.id));
    const busy=(column.busy || []).filter(b=>!b.appointmentId || !visibleIds.has(b.appointmentId));
    return {...column,cards,gaps,slots,busy};
}));
const agendaGroups = computed(()=>props.schedule.days.map(day=>({...day,events:visibleAppointments.value.filter(e=>dayInterval(e,day.date,props.calendar.timeZone) && (!mobileStaff.value || e.staff.some(s=>s.id===mobileStaff.value)))})));
const currentLineTop = computed(()=>{
    const minute=wallMinute(now.value,props.calendar.timeZone);
    return minute>=calendarStartMinute.value && minute<calendarEndMinute.value ? `${(minute-calendarStartMinute.value)*hourHeight/60}px` : null;
});
const showCurrentLine = column => currentLineTop.value && column.date===currentDateKey.value;
const suggestedStart = () => {
    const earliest=firstAllowedStart();
    if(props.filters.date<currentDateKey.value) return `${props.filters.date}T${clockLabel(calendarStartMinute.value)}`;
    const gaps=dayTeam.value.flatMap(m=>subtractBusy(m.windows,m.busy));
    const first=gaps.sort((a,b)=>Date.parse(a.startsAt)-Date.parse(b.startsAt)).find(w=>localInputInCalendarZone(new Date(w.endsAt))>earliest && dateKey(w.startsAt,props.calendar.timeZone)===props.filters.date);
    if(first) {const start=localInputInCalendarZone(new Date(first.startsAt));return start>earliest?start:earliest;}
    return props.filters.date>currentDateKey.value ? `${props.filters.date}T${clockLabel(calendarStartMinute.value)}` : earliest;
};
function scrollToUsefulTime() {
    if(!scheduleScroll.value) return;
    const target=props.filters.date===currentDateKey.value?wallMinute(now.value,props.calendar.timeZone)-60:calendarStartMinute.value;
    scheduleScroll.value.scrollTop=Math.max(0,(target-calendarStartMinute.value)*hourHeight/60);
}
const drag = ref(null);
const dropPreview = ref(null);
const dragError = ref('');
function startDrag(domEvent, event, resize=false) {
    const original=appointments.value.find(e=>e.id===event.id);
    if(!canMove(original)) {domEvent.preventDefault();return;}
    drag.value={event:original,resize};domEvent.dataTransfer.effectAllowed='move';domEvent.dataTransfer.setData('text/plain',original.id);dragError.value='';
}
function previewDrop(domEvent,column) {
    if(!drag.value) return;
    domEvent.preventDefault();
    const y=domEvent.clientY-domEvent.currentTarget.getBoundingClientRect().top;
    const minute=Math.max(calendarStartMinute.value,Math.min(calendarEndMinute.value-interval.value,Math.round((calendarStartMinute.value+y*60/hourHeight)/interval.value)*interval.value));
    const event=drag.value.event;
    const duration=drag.value.resize?minute-wallMinute(event.startsAt,props.calendar.timeZone):Math.round((Date.parse(event.endsAt)-Date.parse(event.startsAt))/60000);
    const members=column.type==='staff' ? [column] : column.team.filter(m=>event.staff.some(s=>s.id===m.id));
    const from=drag.value.resize?wallMinute(event.startsAt,props.calendar.timeZone):minute;
    const reassign=column.type==='staff' && !event.staff.some(s=>s.id===column.id);
    const valid=!props.schedule.days.find(day=>day.date===column.date)?.clockChanges && duration>=5 && members.length>0 && column.id!=='unassigned' && (!reassign || (props.permissions.reassign && event.staff.length===1)) && (!drag.value.resize || (event.services.length===1 && column.date===dateKey(event.startsAt,props.calendar.timeZone))) && members.every(m=>subtractBusy(m.windows,m.busy,event.id).some(w=>{
        const i=dayInterval(w,column.date,props.calendar.timeZone);return i && from>=i.start && from+duration<=i.end;
    }));
    dropPreview.value={column:column.id,date:column.date,minute,from,duration,valid,reassign};domEvent.dataTransfer.dropEffect=valid?'move':'none';
}
function finishDrop(domEvent,column) {
    domEvent.preventDefault();const preview=dropPreview.value;const moving=drag.value;
    drag.value=null;dropPreview.value=null;
    if(!preview?.valid || !moving) {dragError.value='This time is unavailable. Choose another gap or reschedule from appointment details.';return;}
    if(moving.resize) {
        openChange(moving.event,'resize');changeForm.lines[0].duration_minutes=preview.duration;
    } else {
        openChange(moving.event,'reschedule');changeForm.starts_at=clockInput(preview.date,preview.minute);
        if(preview.reassign) changeForm.lines.forEach(line=>line.staff=column.id);
    }
}
function clearDrag(){drag.value=null;dropPreview.value=null;}
function slotKey(event,column,index) {
    const buttons=[...event.currentTarget.parentElement.querySelectorAll('.cal-slot')];
    let target;
    if(event.key==='ArrowDown') target=buttons[index+1];
    else if(event.key==='ArrowUp') target=buttons[index-1];
    else if(event.key==='Home') target=buttons[0];
    else if(event.key==='End') target=buttons.at(-1);
    else if(['ArrowLeft','ArrowRight'].includes(event.key)) {
        const lanes=[...scheduleScroll.value.querySelectorAll('.cal-lane')];
        const lane=lanes[lanes.indexOf(event.currentTarget.parentElement)+(event.key==='ArrowRight'?1:-1)];
        target=lane?.querySelector(`[data-minute="${event.currentTarget.dataset.minute}"]`);
    } else return;
    event.preventDefault();target?.focus();
}
function keyboard(event) {
    if(event.key==='Escape' && filterMenu.value?.open){filterMenu.value.open=false;filterMenu.value.querySelector('summary')?.focus();return;}
    if(event.altKey || event.metaKey || event.ctrlKey || event.target.closest('input,textarea,select,[role="combobox"],[contenteditable],dialog[open]'))return;
    if(event.key==='t'){event.preventDefault();goToday();}
    if(event.key==='n' && props.permissions.manage && props.options.services.length && props.options.bookableStaff.length){event.preventDefault();openCreate();}
    if(event.key==='/'){event.preventDefault();document.getElementById('calendar-search')?.focus();}
}
let ticker, refresher;
onMounted(()=>{
    createForm.starts_at=suggestedStart();
    ticker=setInterval(()=>now.value=serverAnchor.value+Date.now()-elapsedAnchor.value,30_000);
    refresher=setInterval(refreshSchedule,60_000);
    document.addEventListener('keydown',keyboard);
    document.addEventListener('click',closeFilters);
    const params=new URL(window.location.href).searchParams;
    const linked=appointments.value.find(e=>e.id===params.get('appointment'));
    if(props.clientPrefill) {
        chooseClient(props.clientPrefill);
        if(props.clientPrefill.lines?.length) createForm.lines=props.clientPrefill.lines;
        else if(props.clientPrefill.rebooking) createForm.lines=[{service:'',staff:'',duration_minutes:null}];
        openCreate();
    } else if(params.get('create')==='1')openCreate();
    else if(linked)selectAppointment(linked);
    else if(!params.has('view') && !params.has('date') && ![...params.keys()].some(key=>/^(status|staff|service)(\[|$)/.test(key))) {
        try {const saved=JSON.parse(localStorage.getItem(preferenceKey()) || 'null');if(saved && views.some(v=>v.id===saved.view))applyFilters(saved);}catch{ /* Invalid preferences never block the schedule. */ }
    }
    nextTick(scrollToUsefulTime);
});
watch(()=>props.calendar.currentTime,value=>{now.value=serverAnchor.value=Date.parse(value);elapsedAnchor.value=Date.now();});
onBeforeUnmount(()=>{clearInterval(ticker);clearInterval(refresher);clearTimeout(clientTimer);clientRequest?.abort();document.removeEventListener('keydown',keyboard);document.removeEventListener('click',closeFilters);});
</script>

<template>
    <AppLayout title="Calendar" :business-label="businessLabel">
      <div class="cal-workspace">
        <PageHeader title="Calendar">
            <template #actions>
                <div class="cal-location"><label for="calendar-location" class="sr-only">Location</label><AppSelect id="calendar-location" :value="filters.location" class="cd-input" @change="applyFilters({location:$event.target.value,staff:[]})"><option v-for="location in options.locations" :key="location.public_id" :value="location.public_id">{{ location.name }}</option></AppSelect></div>
                <details class="cd-action-menu cal-more">
                    <summary class="cal-control" aria-label="More calendar actions" title="More calendar actions"><span aria-hidden="true">•••</span></summary>
                    <div class="cal-menu">
                        <AppButton :href="route('business.calendar.print',{business:route().params.business,location:filters.location,date:filters.date})" variant="quiet"><PrinterIcon aria-hidden="true" />Print day</AppButton>
                        <button v-if="permissions.manage" type="button" @click="blockDialog?.open()"><ClockIcon aria-hidden="true" />Block staff time</button>
                        <button v-if="permissions.override" type="button" class="cal-danger" @click="closureDialog?.open()">Record unexpected closure</button>
                        <p class="cal-shortcuts">N · New appointment<br>T · Today &nbsp; / · Search</p>
                    </div>
                </details>
                <AppButton v-if="permissions.manage" :disabled="!options.services.length || !options.bookableStaff.length" @click="openCreate()"><PlusIcon class="size-4" aria-hidden="true" /><span class="cal-add-label">Add appointment</span><span class="cal-add-mobile">Add</span></AppButton>
            </template>
        </PageHeader>

        <section class="cal-board" aria-label="Salon calendar">
            <div class="cal-toolbar">
                <div class="cal-date-controls">
                    <button type="button" class="cal-control cal-today" title="Go to today (T)" @click="goToday">Today</button>
                    <div class="cal-arrows"><button type="button" class="cal-control" aria-label="Previous date" @click="shiftDate(-1)"><ChevronLeftIcon aria-hidden="true" /></button><button type="button" class="cal-control" aria-label="Next date" @click="shiftDate(1)"><ChevronRightIcon aria-hidden="true" /></button></div>
                    <details class="cal-date-picker cd-action-menu">
                        <summary class="cal-date-title" aria-label="Choose calendar date">{{ localDateLabel }}<ChevronDownIcon aria-hidden="true" /></summary>
                        <div class="cal-menu"><label for="calendar-date">Jump to date</label><input id="calendar-date" type="date" :value="filters.date" class="cd-input" @change="$event.target.closest('details').open=false;applyFilters({date:$event.target.value})" /></div>
                    </details>
                </div>
                <div class="cal-toolbar-tools">
                    <div class="cal-search"><MagnifyingGlassIcon aria-hidden="true" /><label for="calendar-search" class="sr-only">Search this {{ filters.view==='week' ? 'week' : 'day' }}</label><input id="calendar-search" v-model="search" type="search" placeholder="Search appointments" autocomplete="off" title="Search client, phone, reference, service or staff in this date range (/)" /></div>
                    <div class="cal-views" role="group" aria-label="Calendar view"><button v-for="view in views" :key="view.id" type="button" :aria-pressed="filters.view===view.id" @click="applyFilters({view:view.id})">{{ view.label }}</button></div>
                    <details ref="filterMenu" class="cal-filters"><summary class="cal-control"><AdjustmentsHorizontalIcon aria-hidden="true" /><span>Filters</span><span v-if="activeFilters" class="cal-filter-count">{{ activeFilters }}</span></summary>
                        <div class="cal-filter-panel">
                            <div class="cal-filter-heading"><strong>Filter appointments</strong><button type="button" class="cal-text-button" @click="clearFilters">Reset</button></div>
                            <FormField id="calendar-staff" label="Staff"><AppSelect id="calendar-staff" placeholder="All staff" :value="filters.staff || []" multiple class="cd-input" @change="applyFilters({staff:[...$event.target.selectedOptions].map(o=>o.value)})"><option v-for="member in options.staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField>
                            <FormField id="calendar-service" label="Service"><AppSelect id="calendar-service" placeholder="All services" :value="filters.service || []" multiple class="cd-input" @change="applyFilters({service:[...$event.target.selectedOptions].map(o=>o.value)})"><option v-for="service in options.services" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect></FormField>
                            <FormField id="calendar-status" label="Status"><AppSelect id="calendar-status" placeholder="Active and finished visits" :value="filters.status || []" multiple class="cd-input" @change="applyFilters({status:[...$event.target.selectedOptions].map(o=>o.value)})"><option v-for="status in options.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></AppSelect></FormField>
                            <p>Availability always includes reserved time.</p>
                        </div>
                    </details>
                </div>
            </div>
            <div class="cal-operations">
                <span class="cal-booking-total"><CalendarDaysIcon aria-hidden="true" /><strong>{{ calendar.counts.appointments }}</strong> appointments</span>
                <button v-if="operational.waiting" type="button" class="cal-stat" @click="applyFilters({status:['arrived','checked_in']})"><span class="cal-dot cal-dot-green" /><strong>{{ operational.waiting }}</strong> waiting</button>
                <button v-if="operational.underway" type="button" class="cal-stat" @click="applyFilters({status:['in_service']})"><span class="cal-dot cal-dot-indigo" /><strong>{{ operational.underway }}</strong> in service</button>
                <span class="cal-team-summary"><span class="cal-dot" :class="operational.available ? 'cal-dot-green' : ''" /><template v-if="filters.date===currentDateKey && operational.available"><strong>{{ operational.available }}</strong> staff available</template><template v-else><strong>{{ operational.scheduled }}</strong> staff scheduled</template></span>
                <span v-if="nextAppointment && filters.date===currentDateKey" class="cal-next">Next · {{ localTime(nextAppointment.startsAt) }} <strong>{{ nextAppointment.title }}</strong></span>
                <span class="cal-refresh" role="status"><template v-if="refreshBusy">Updating…</template><template v-else>{{ calendar.timeZone }}</template><button type="button" :disabled="refreshBusy" aria-label="Refresh schedule" title="Refresh schedule" @click="refreshSchedule"><ArrowPathIcon aria-hidden="true" /></button></span>
                <button type="button" class="cal-attention-toggle" :class="{'has-attention':attentionCount}" :aria-expanded="attentionOpen" aria-controls="calendar-attention" @click="attentionOpen=!attentionOpen"><ExclamationTriangleIcon v-if="attentionCount" aria-hidden="true" /><CheckCircleIcon v-else aria-hidden="true" />{{ attentionCount ? `${attentionCount} need attention` : 'No flags in view' }}<ChevronDownIcon aria-hidden="true" /></button>
            </div>
            <div v-if="activeFilters || search" class="cal-filter-chips"><span>Showing {{ visibleAppointments.length }} appointments</span><button v-for="member in options.staff.filter(m=>filters.staff?.includes(m.public_id))" :key="member.public_id" type="button" @click="applyFilters({staff:filters.staff.filter(s=>s!==member.public_id)})">{{ member.display_name }}<XMarkIcon aria-hidden="true" /></button><button v-for="status in options.statuses.filter(s=>filters.status?.includes(s.value))" :key="status.value" type="button" @click="applyFilters({status:filters.status.filter(s=>s!==status.value)})">{{ status.label }}<XMarkIcon aria-hidden="true" /></button><button v-for="service in options.services.filter(s=>filters.service?.includes(s.public_id))" :key="service.public_id" type="button" @click="applyFilters({service:filters.service.filter(s=>s!==service.public_id)})">{{ service.name }}<XMarkIcon aria-hidden="true" /></button><button type="button" class="cal-text-button" @click="clearFilters">Clear all</button></div>
            <div v-if="navigationError || dragError" class="cal-inline-error" role="alert">{{ navigationError || dragError }}<button type="button" @click="navigationError='';dragError=''" aria-label="Dismiss message"><XMarkIcon aria-hidden="true" /></button></div>
            <div v-if="selectedDay?.clockChanges" class="cal-inline-error" role="status">The clocks change on this date. Use the booking drawer to review exact times; drag and slot booking are paused.</div>
            <div v-if="calendar.truncated" class="cal-inline-error" role="status">This range has more than 1,000 appointments. Narrow the date, staff or service filters. Reserved time remains protected.</div>
            <section v-show="attentionOpen" id="calendar-attention" class="cal-attention" aria-label="Appointments needing attention">
                <div class="cal-attention-heading"><strong>Needs attention</strong><button type="button" class="cal-text-button" @click="attentionOpen=false">Close</button></div>
                <p v-if="!attentionCount" class="cal-muted">No outstanding items in this schedule.</p>
                <div class="cal-attention-list"><button v-for="item in issues" :key="`${item.event.id}-${item.priority}`" type="button" @click="selectAppointment(item.event)"><span class="cal-attention-time">{{ localTime(item.event.startsAt) }}</span><span><strong>{{ item.event.title }}</strong><small>{{ item.reasons.join(' · ') }}</small></span><ChevronRightIcon aria-hidden="true" /></button>
                    <AppButton v-if="auxiliaryEvents.length && permissions.walkIns" variant="quiet" :href="route('business.walk-ins.index',{business:route().params.business,location:filters.location})"><QueueListIcon aria-hidden="true" />{{ auxiliaryEvents.length }} walk-ins waiting now<ChevronRightIcon aria-hidden="true" /></AppButton>
                    <p v-else-if="auxiliaryEvents.length" class="cal-muted">{{ auxiliaryEvents.length }} walk-ins waiting now</p>
                </div>
            </section>

            <div class="cal-mobile-staff"><label for="calendar-mobile-staff" class="sr-only">Show staff appointments</label><AppSelect id="calendar-mobile-staff" v-model="mobileStaff" class="cd-input"><option value="">All staff</option><option v-for="member in dayTeam" :key="member.id" :value="member.id">{{ member.name }} · {{ staffState(member) }}</option></AppSelect><span>{{ visibleAppointments.length }} visits</span></div>
            <div v-if="!options.staff.length" class="cal-no-team"><UserIcon aria-hidden="true" /><strong>No staff at this location</strong><p>Choose another location or add a team member in Team & availability.</p></div>
            <div v-else-if="filters.view!=='agenda'" ref="scheduleScroll" class="cal-schedule-scroll" tabindex="0" aria-label="Scrollable appointment schedule" :aria-busy="refreshBusy" @dragend="clearDrag">
                <div class="cal-grid-head" :style="gridStyle"><div class="cal-time-corner"><ClockIcon aria-hidden="true" /><span>{{ timeZoneShort }}</span></div>
                    <div v-for="column in columnData" :key="column.id" class="cal-staff-head" :class="{'is-today':column.date===currentDateKey}">
                        <span v-if="column.type==='staff'" class="cal-avatar" aria-hidden="true">{{ initials(column.label) }}</span>
                        <div class="cal-staff-info"><strong :title="column.label">{{ column.label }}</strong><span v-if="column.type==='staff' && column.id!=='unassigned'" :title="column.title || column.label"><i class="cal-dot" :class="{'cal-dot-green':staffState(column)==='Available','cal-dot-indigo':['Busy','In service'].includes(staffState(column))}" />{{ staffState(column) }}<span v-if="column.windows.length"> · {{ localTime(column.windows[0].startsAt) }}–{{ localTime(column.windows.at(-1).endsAt) }}</span></span><span v-else>{{ column.type==='date' && !column.windows.length ? 'Location closed' : column.id==='unassigned' ? 'Assign a team member' : `${column.team?.filter(m=>m.windows.length).length || 0} staff scheduled` }}</span></div>
                        <span class="cal-staff-count" :title="`${new Set(column.cards.map(e=>e.id)).size} appointments`">{{ new Set(column.cards.map(e=>e.id)).size }}</span>
                    </div>
                </div>
                <div class="cal-grid-body" :style="gridStyle">
                    <div class="cal-time-axis" :style="{height:`${timelineHeight}px`}"><span v-for="hour in hours" :key="hour" :style="{top:`${(hour*60-calendarStartMinute)*hourHeight/60}px`}" :class="{'is-first':hour*60===calendarStartMinute}">{{ clockLabel(hour*60) }}</span><span v-if="schedule.days.some(d=>d.date===currentDateKey) && currentLineTop" class="cal-now-label" :style="{top:currentLineTop}">{{ localTime(now) }}</span></div>
                    <section v-for="column in columnData" :key="column.id" class="cal-lane" :style="{height:`${timelineHeight}px`}" :aria-label="`${column.label} schedule`" @dragover="previewDrop($event,column)" @drop="finishDrop($event,column)">
                        <div v-for="(window,index) in column.windows" :key="`window-${index}`" class="cal-working-window" :style="rangePosition(window,column.date)" />
                        <div v-for="(range,index) in column.unavailable || []" :key="`off-${index}`" class="cal-unavailable" :style="rangePosition(range,column.date)" :title="`${range.label} · ${localTime(range.startsAt)}–${localTime(range.endsAt)}`"><span>{{ range.label }}</span></div>
                        <div v-for="(range,index) in column.busy" :key="`busy-${index}`" class="cal-reserved" :style="rangePosition(range,column.date)" :title="`${range.label} · ${localTime(range.startsAt)}–${localTime(range.endsAt)}`"><ClockIcon aria-hidden="true" /><span>{{ range.label }}</span></div>
                        <button v-for="(slot,index) in permissions.manage && options.services.length ? column.slots : []" :key="`slot-${slot.minute}`" type="button" class="cal-slot" :tabindex="index===0 ? 0 : -1" :data-minute="slot.minute" :style="{top:`${(slot.minute-calendarStartMinute)*hourHeight/60}px`,height:`${interval*hourHeight/60}px`}" :aria-label="`Add appointment with ${column.label} at ${clockLabel(slot.minute)} on ${column.date}`" @click="openCreate(slot)" @keydown="slotKey($event,column,index)"><PlusIcon aria-hidden="true" /><span>{{ clockLabel(slot.minute) }}</span></button>
                        <AppointmentCard v-for="event in column.cards" :key="event.renderId || event.id" :event="event" :height="event.height" :time-zone="calendar.timeZone" :style="event.style" :selected="activeEvent?.id===event.id" :draggable="canMove(event) && !event.processing" @open="selectAppointment" @dragstart="startDrag" @dragend="clearDrag" />
                        <button v-for="event in column.cards.filter(e=>canMove(e) && e.services.length===1 && !e.processing && e.height>=35)" :key="`resize-${event.renderId || event.id}`" type="button" class="cal-resize-handle" draggable="true" :style="{top:`${parseFloat(event.style.top)+event.height-6}px`,left:event.style.left,width:event.style.width}" :aria-label="`Adjust duration for ${event.title}`" title="Drag to adjust duration, or click to edit" @dragstart.stop="startDrag($event,event,true)" @dragend="clearDrag" @click="openChange(appointments.find(e=>e.id===event.id),'resize')"><span /></button>
                        <div v-if="showCurrentLine(column)" class="cal-now-line" :style="{top:currentLineTop}" aria-hidden="true" />
                        <div v-if="dropPreview?.column===column.id" class="cal-drop-preview" :class="{'is-invalid':!dropPreview.valid}" :style="{top:`${(dropPreview.from-calendarStartMinute)*hourHeight/60}px`,height:`${Math.max(16,dropPreview.duration*hourHeight/60)}px`}"><strong>{{ dropPreview.valid ? clockLabel(dropPreview.from) : 'Unavailable' }}</strong><span v-if="dropPreview.valid">Release to review change</span></div>
                        <span v-if="!column.windows.length && !column.cards.length" class="cal-off-label">{{ column.id==='unassigned' ? 'No assigned staff' : 'Not scheduled' }}</span>
                    </section>
                </div>
            </div>

            <section class="cal-agenda" :class="{'is-active':filters.view==='agenda'}" aria-label="Appointment agenda">
                <div v-for="day in agendaGroups" :key="day.date" class="cal-agenda-day"><div class="cal-agenda-day-title"><h2>{{ agendaDate(day.date) }}</h2><span>{{ day.events.length }} appointments</span></div>
                    <p v-if="!day.events.length" class="cal-agenda-empty">{{ search || activeFilters || mobileStaff ? 'No appointments match your filters.' : day.windows.length ? 'No appointments yet. Your schedule is open.' : 'No appointments. This location is closed.' }}</p>
                    <article v-for="event in day.events" :key="event.id" class="cal-agenda-row" :class="`cal-tone-${event.tone}`"><div class="cal-agenda-time"><time :datetime="event.startsAt">{{ localTime(event.startsAt) }}</time><span>{{ Math.round((Date.parse(event.endsAt)-Date.parse(event.startsAt))/60000) }} min</span></div><button type="button" class="cal-agenda-info" @click="selectAppointment(event)"><strong>{{ event.title }}</strong><span>{{ event.services.map(s=>s.name).join(' + ') }}</span><small>{{ event.staff.map(s=>s.name).join(', ') || 'Staff not assigned' }}</small></button><div class="cal-agenda-status"><span>{{ event.statusLabel }}</span><button v-if="event.action" type="button" :disabled="statusForm.processing" @click="transition(event,event.action.status)">{{ event.action.label }}</button><AppButton v-else-if="event.checkoutReady" variant="quiet" size="small" :href="route('business.checkout.index',{business:route().params.business,appointment:event.id})">Checkout</AppButton><button v-else type="button" @click="selectAppointment(event)">Details<ChevronRightIcon aria-hidden="true" /></button></div></article>
                </div>
                <div v-if="permissions.manage && dayTeam.some(m=>nextGap(m))" class="cal-agenda-gaps"><strong>Open time</strong><p>Choose a staff member to add an appointment.</p><button v-for="member in dayTeam.filter(m=>nextGap(m))" :key="member.id" type="button" @click="openCreate({date:filters.date,minute:Math.ceil(Math.max(wallMinute(nextGap(member).startsAt,calendar.timeZone),filters.date===currentDateKey?wallMinute(now,calendar.timeZone)+1:0)/interval)*interval,staff:member.id})"><span class="cal-avatar">{{ initials(member.name) }}</span><span><strong>{{ member.name }}</strong><small>{{ filters.date===currentDateKey ? 'Next gap' : 'First gap' }} · {{ localTime(nextGap(member).startsAt) }}–{{ localTime(nextGap(member).endsAt) }}</small></span><PlusIcon aria-hidden="true" /></button></div>
            </section>
            <footer class="cal-legend"><span><i class="cal-dot cal-dot-blue" />Confirmed</span><span><i class="cal-dot cal-dot-green" />Arrived</span><span><i class="cal-dot cal-dot-indigo" />In service</span><span><i class="cal-dot cal-dot-amber" />Needs review</span><span><i class="cal-hatch-key" />Unavailable</span><span class="cal-legend-hint">{{ permissions.manage ? 'Click open time to book · Drag a visit to reschedule' : 'Select a visit for details' }}</span></footer>
        </section>
      </div>

      <AppDialog id="calendar-details" ref="detailDialog" :title="activeEvent?.title || 'Appointment'" :description="activeEvent ? `${localTime(activeEvent.startsAt)}–${localTime(activeEvent.endsAt)} · ${agendaDate(dateKey(activeEvent.startsAt,calendar.timeZone))}` : ''" drawer>
        <div v-if="activeEvent" class="cal-detail">
            <div class="cal-detail-status" :class="`cal-tone-${activeEvent.tone}`"><span>{{ activeEvent.statusLabel }}</span><small>{{ activeEvent.reference }}</small></div>
            <p v-if="Object.keys(statusForm.errors).length" class="cal-inline-error" role="alert">{{ Object.values(statusForm.errors)[0] }}</p>
            <div v-if="issues.find(i=>i.event.id===activeEvent.id)" class="cal-detail-alert"><ExclamationTriangleIcon aria-hidden="true" /><span>{{ issues.filter(i=>i.event.id===activeEvent.id).flatMap(i=>i.reasons).join(' · ') }}</span></div>
            <div class="cal-detail-services"><div v-for="(service,index) in activeEvent.services" :key="index"><span class="cal-service-icon"><ScissorsIcon aria-hidden="true" /></span><span><strong>{{ service.name }}</strong><small>{{ activeEvent.staff.find(m=>m.id===service.staffId)?.name || activeEvent.staff.map(m=>m.name).join(', ') || 'Staff not assigned' }}</small></span><span>{{ service.durationMinutes }} min</span></div></div>
            <dl class="cal-detail-facts"><div><dt>Booked via</dt><dd>{{ sourceLabel(activeEvent.source) }}</dd></div><div v-if="activeEvent.clientMobile"><dt>Phone</dt><dd><a :href="`tel:${activeEvent.clientMobile}`">{{ activeEvent.clientMobile }}</a></dd></div><div v-if="activeEvent.clientEmail"><dt>Email</dt><dd><a :href="`mailto:${activeEvent.clientEmail}`">{{ activeEvent.clientEmail }}</a></dd></div><div v-if="activeEvent.forms?.requested"><dt>Client forms</dt><dd>{{ activeEvent.forms.completed }} of {{ activeEvent.forms.requested }} complete</dd></div><div v-if="activeEvent.paymentLabel"><dt>Checkout</dt><dd>{{ activeEvent.paymentLabel }}</dd></div></dl>
            <div v-if="activeEvent.internalNotes" class="cal-detail-note"><strong>Internal note</strong><p>{{ activeEvent.internalNotes }}</p></div>
            <AppButton v-if="activeEvent.clientId && permissions.client" variant="secondary" class="w-full" :href="route('business.clients.show',[route().params.business,activeEvent.clientId])"><UserIcon class="size-4" aria-hidden="true" />View client</AppButton>
            <section v-if="activeEvent.notifications?.length || activeEvent.communicationHistoryUrl" class="cal-notification-context"><div><h3>Client communication</h3><Link v-if="activeEvent.communicationHistoryUrl" :href="activeEvent.communicationHistoryUrl">Delivery history</Link></div><p v-if="!activeEvent.notifications?.length">No recorded appointment notifications.</p><ul v-else><li v-for="(message,index) in activeEvent.notifications" :key="index"><span>{{ message.name }} · {{ message.channel==='sms'?'SMS':'Email' }}</span><strong>{{ deliveryLabel(message) }}</strong></li></ul></section>
            <div v-if="activeEvent.canManage" class="cal-quick-actions">
                <template v-if="!terminalStatuses.has(activeEvent.status)"><button type="button" @click="reviewAction(()=>openChange(activeEvent))"><ClockIcon aria-hidden="true" />Reschedule</button><button type="button" @click="reviewAction(()=>openChange(activeEvent,'resize'))"><ArrowsPointingOutIcon aria-hidden="true" />Duration</button><button v-if="permissions.reassign" type="button" @click="reviewAction(()=>openChange(activeEvent,'reassign'))"><UserIcon aria-hidden="true" />Change staff</button><button type="button" @click="reviewAction(()=>openChange(activeEvent,'services_changed'))"><ScissorsIcon aria-hidden="true" />Edit services</button></template>
                <button v-if="permissions.notes" type="button" @click="reviewAction(()=>openNotes(activeEvent))"><PencilSquareIcon aria-hidden="true" />Add note</button><button type="button" @click="reviewAction(()=>openCopy(activeEvent,terminalStatuses.has(activeEvent.status)?'rebook':'duplicate'))"><DocumentDuplicateIcon aria-hidden="true" />{{ terminalStatuses.has(activeEvent.status)?'Rebook':'Duplicate' }}</button>
                <button v-if="!terminalStatuses.has(activeEvent.status)" type="button" @click="reviewAction(()=>openException(activeEvent))"><ExclamationTriangleIcon aria-hidden="true" />Record a delay</button><button v-if="['confirmed','late'].includes(activeEvent.status)" type="button" @click="reviewAction(()=>noShowDialog?.open())">Mark no-show</button><button v-if="!terminalStatuses.has(activeEvent.status)" type="button" class="cal-danger" @click="reviewAction(()=>openCancel(activeEvent))"><TrashIcon aria-hidden="true" />Cancel appointment</button>
            </div>
        </div>
        <template #footer><AppButton variant="secondary" @click="detailDialog?.close()">Close</AppButton><AppButton v-if="activeEvent?.action" :loading="statusForm.processing" @click="transition(activeEvent,activeEvent.action.status)">{{ activeEvent.action.label }}</AppButton><AppButton v-else-if="activeEvent?.checkoutReady" :href="route('business.checkout.index',{business:route().params.business,appointment:activeEvent.id})"><BanknotesIcon class="size-4" aria-hidden="true" />Checkout</AppButton></template>
      </AppDialog>
      <AppDialog id="calendar-no-show" ref="noShowDialog" title="Mark this client as a no-show?" description="This releases the reserved time and records the no-show in appointment history." confirm-label="Mark no-show" destructive :close-on-confirm="false" :confirm-disabled="statusForm.processing" @confirm="transition(activeEvent,'no_show','Client did not attend.',true)"><p v-if="Object.keys(statusForm.errors).length" class="cal-inline-error" role="alert">{{ Object.values(statusForm.errors)[0] }}</p><p class="font-semibold">{{ activeEvent?.title }} · {{ activeEvent ? localTime(activeEvent.startsAt) : '' }}</p></AppDialog>
        <AppDialog id="create-appointment" ref="createDialog" title="Add appointment" :description="`Booking at ${options.locations.find(l=>l.public_id===filters.location)?.name || 'this location'} · ${calendar.timeZone}`" drawer confirm-label="Add appointment" :close-on-confirm="false" :confirm-disabled="createForm.processing" @confirm="submitCreate">
            <div class="space-y-4">
                <div class="cal-create-summary"><CalendarDaysIcon aria-hidden="true" /><span><strong>{{ createForm.starts_at ? agendaDate(createForm.starts_at.slice(0,10)) : 'Choose a date' }}</strong><small>{{ createForm.starts_at.slice(11) }} · {{ totalDuration }} min {{ createForm.lines.some(l=>!l.staff) ? 'estimated duration' : 'configured duration' }} · {{ createPriceLabel }}</small></span></div>
                <p v-if="clientPrefill?.rebooking" class="cal-muted">Rebooking {{ clientPrefill.name }}. Review current services, staff and availability; prices and durations use today’s catalogue. <span v-if="clientPrefill.skippedServices">{{ clientPrefill.skippedServices }} previous service{{ clientPrefill.skippedServices===1 ? ' is' : 's are' }} no longer available here. Choose a current service below.</span></p><div v-if="permissions.client" class="cal-client-picker"><FormField id="calendar-client-search" label="Find an existing client"><input id="calendar-client-search" v-model="clientSearch" type="search" class="cd-input" :placeholder="permissions.contact ? 'Search name or phone' : 'Search client name'" autocomplete="off" /></FormField><p v-if="clientSearchBusy" class="cal-muted" role="status">Searching clients…</p><p v-if="clientSearchError" class="cal-muted" role="status">{{ clientSearchError }}</p><ul v-if="clientResults.length" class="cal-client-results"><li v-for="client in clientResults" :key="client.id"><button type="button" @click="chooseClient(client)"><strong>{{ client.name }}</strong><small v-if="client.mobile">{{ client.mobile }}</small></button></li></ul><p v-else-if="clientSearch.length>=2 && !clientSearchBusy && !clientSearchError" class="cal-muted">No clients found. Enter new client details below.</p><div v-if="createForm.client" class="cal-selected-client"><CheckCircleIcon aria-hidden="true" /><span><strong>{{ createForm.client_name }}</strong><small v-if="!permissions.contact">Saved contact details will be used</small><small v-else-if="selectedClientMobile && !selectedClientNeedsMobile">Saved mobile · {{ selectedClientMobile }}</small><small v-else>{{ selectedClientMobile ? 'Mobile needs updating' : 'No mobile saved' }}</small></span><button type="button" class="cal-text-button" @click="clearClient">Change</button></div></div>
                <p v-if="Object.keys(createForm.errors).length" class="rounded-lg bg-[var(--status-warning-soft)] p-3 text-sm text-[var(--status-warning)]" role="alert">{{ Object.values(createForm.errors)[0] }}</p><FormField v-if="!createForm.client" id="create-client" label="Client name" :error="createForm.errors.client_name"><input id="create-client" v-model="createForm.client_name" :readonly="!!createForm.client" :class="inputClass" autocomplete="name"  :aria-invalid="createForm.errors.client_name ? true : undefined" :aria-describedby="createForm.errors.client_name ? 'create-client-error' : undefined"/></FormField><FormField v-if="!createForm.client || selectedClientNeedsMobile" id="create-mobile" label="Mobile (optional)" :error="createForm.errors.client_mobile"><PhoneInput id="create-mobile" v-model="createForm.client_mobile" :country="$page.props.tenant?.regional?.country_code || 'IN'"  :aria-invalid="createForm.errors.client_mobile ? true : undefined" :aria-describedby="[createForm.errors.client_mobile ? 'create-mobile-error' : null, selectedClientNeedsMobile ? 'create-mobile-help' : null].filter(Boolean).join(' ') || undefined"/><p v-if="selectedClientNeedsMobile" id="create-mobile-help" class="cal-mobile-help">Used for this appointment. <a :href="route('business.clients.show',[route().params.business,createForm.client])">Update the client’s profile</a> to save it for future visits.</p></FormField><FormField v-if="!createForm.client" id="create-email" label="Email (optional)" :error="createForm.errors.client_email"><input id="create-email" v-model="createForm.client_email" :readonly="!!createForm.client" type="email" :class="inputClass" autocomplete="email"  :aria-invalid="createForm.errors.client_email ? true : undefined" :aria-describedby="createForm.errors.client_email ? 'create-email-error' : undefined"/></FormField><FormField id="create-start" label="Starts" :error="createForm.errors.starts_at"><input id="create-start" v-model="createForm.starts_at" type="datetime-local" :step="Math.max(1, bookingRules?.intervalMinutes || 15) * 60" :class="inputClass"  :aria-invalid="createForm.errors.starts_at ? true : undefined" :aria-describedby="createForm.errors.starts_at ? 'create-start-error' : undefined"/></FormField><p class="-mt-2 text-xs text-[var(--text-muted)]">Service, staff and resource availability are checked before saving.</p><div v-for="(line, index) in createForm.lines" :key="index" class="rounded-lg bg-[var(--surface-subtle)] p-3"><FormField :id="`create-service-${index}`" :label="`Service ${index + 1}`" required><AppSelect :id="`create-service-${index}`" v-model="line.service" :class="inputClass" ><option v-for="service in bookingServiceChoices(createForm.lines, createForm.starts_at)" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect></FormField><FormField :id="`create-staff-${index}`" class="mt-3" label="Staff"><AppSelect :id="`create-staff-${index}`" v-model="line.staff" :class="inputClass"><option value="">First available</option><option v-for="member in eligibleStaff(line, createForm.starts_at)" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField><button v-if="createForm.lines.length > 1" type="button" class="mt-2 min-h-11 text-sm font-semibold text-[var(--status-danger)]" @click="createForm.lines.splice(index, 1)">Remove service</button></div><AppButton variant="quiet" @click="addCreateLine"><PlusIcon class="size-4" aria-hidden="true" />Add service</AppButton><FormField id="create-source" label="Booked via"><AppSelect id="create-source" v-model="createForm.source" class="cd-input"><option value="reception">Reception</option><option value="phone">Phone</option><option value="consultation">Consultation</option><option value="recurring">Recurring</option></AppSelect></FormField><div v-if="permissions.override && createForm.starts_at<firstAllowedStart()" class="cal-override"><label><input v-model="createForm.override_confirmed" type="checkbox" />Override booking notice</label><FormField v-if="createForm.override_confirmed" id="create-override-reason" label="Reason for override" required><textarea id="create-override-reason" v-model="createForm.override_reason" class="cd-input" rows="2" /></FormField><p>Working hours and capacity conflicts cannot be overridden.</p></div><details class="cal-create-extra"><summary>Internal note</summary><textarea v-model="createForm.internal_notes" class="cd-input" rows="3" aria-label="Internal appointment note" /></details></div>
        </AppDialog>

        <AppDialog id="change-appointment" ref="changeDialog" drawer :title="changeTitle" :description="changeDescription" :confirm-label="changeForm.kind === 'services_changed' ? 'Update this visit' : 'Save changes'" :close-on-confirm="false" :confirm-disabled="changeForm.processing" @confirm="submitChange">
            <div class="space-y-4"><div class="cal-create-summary"><ClockIcon aria-hidden="true" /><span><strong>{{ activeEvent?.title }}</strong><small>{{ changeForm.lines.map(line=>options.staff.find(m=>m.public_id===line.staff)?.display_name || 'First available').join(' + ') }} · {{ changeForm.starts_at.replace('T',' · ') }}</small></span></div><p v-if="Object.keys(changeForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(changeForm.errors)[0] }}</p><FormField v-if="changeForm.kind === 'reschedule'" id="change-start" label="New start" required :error="changeForm.errors.starts_at"><input id="change-start" v-model="changeForm.starts_at" type="datetime-local" :class="inputClass"  :aria-invalid="changeForm.errors.starts_at ? true : undefined" :aria-describedby="changeForm.errors.starts_at ? 'change-start-error' : undefined"/></FormField><div v-for="(line, index) in changeForm.lines" :key="index" class="rounded-lg bg-[var(--surface-subtle)] p-3"><FormField v-if="changeForm.kind === 'services_changed'" :id="`change-service-${index}`" :label="`Service ${index + 1}`"><AppSelect :id="`change-service-${index}`" v-model="line.service" :class="inputClass"><option v-for="service in bookingServiceChoices(changeForm.lines, changeForm.starts_at)" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect></FormField><FormField v-if="['reassign','services_changed'].includes(changeForm.kind)" :id="`change-staff-${index}`" :class="changeForm.kind === 'services_changed' ? 'mt-3' : ''" label="Staff"><AppSelect :id="`change-staff-${index}`" v-model="line.staff" :class="inputClass"><option value="">First available</option><option v-for="member in eligibleStaff(line, changeForm.starts_at)" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField><FormField v-if="changeForm.kind === 'resize'" :id="`change-duration-${index}`" label="Total service minutes"><input :id="`change-duration-${index}`" v-model.number="line.duration_minutes" type="number" min="5" max="720" step="5" :class="inputClass" /></FormField><button v-if="changeForm.kind === 'services_changed' && changeForm.lines.length > 1" type="button" class="mt-2 min-h-11 text-sm font-semibold text-[var(--status-danger)]" @click="changeForm.lines.splice(index, 1)">Remove service</button></div><AppButton v-if="changeForm.kind === 'services_changed'" variant="quiet" @click="addChangeLine">Add service</AppButton><FormField id="change-reason" label="Reason" required :error="changeForm.errors.reason"><textarea id="change-reason" v-model="changeForm.reason" rows="3" :class="inputClass"  :aria-invalid="changeForm.errors.reason ? true : undefined" :aria-describedby="changeForm.errors.reason ? 'change-reason-error' : undefined"/></FormField><label v-if="permissions.override && changeForm.kind === 'reschedule'" class="flex gap-3 text-sm"><input v-model="changeForm.override_confirmed" type="checkbox" class="mt-1 size-5" /><span><strong>Manager policy override</strong><br><span class="text-[var(--text-muted)]">Only notice/advance policy may be overridden. Capacity and integrity conflicts never can.</span></span></label></div>
        </AppDialog>

        <AppDialog id="copy-appointment" ref="copyDialog" :title="copyForm.kind === 'rebook' ? 'Rebook this client' : 'Create a separate appointment'" description="Choose a new time. This creates a separate visit and never overwrites the current one." :confirm-label="copyForm.kind === 'rebook' ? 'Book again' : 'Duplicate appointment'" :close-on-confirm="false" :confirm-disabled="copyForm.processing" @confirm="submitCopy"><div class="space-y-4"><p v-if="Object.keys(copyForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(copyForm.errors)[0] }}</p><FormField id="copy-start" label="New start" required :error="copyForm.errors.starts_at"><input id="copy-start" v-model="copyForm.starts_at" type="datetime-local" :class="inputClass"  :aria-invalid="copyForm.errors.starts_at ? true : undefined" :aria-describedby="copyForm.errors.starts_at ? 'copy-start-error' : undefined"/></FormField></div></AppDialog>
        <AppDialog id="cancel-appointment" ref="cancelDialog" title="Cancel this appointment?" description="This frees the time immediately. The reason is kept in appointment history." confirm-label="Cancel appointment" cancel-label="Keep appointment" destructive :close-on-confirm="false" :confirm-disabled="cancelForm.processing || !cancelForm.reason_code || (cancelForm.reason_code === 'other' && !cancelForm.other_reason.trim())" @confirm="submitCancel"><p v-if="cancelForm.errors.reason_code || cancelForm.errors.other_reason || cancelForm.errors.reason || cancelForm.errors.booking" class="mb-3 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ cancelForm.errors.reason_code || cancelForm.errors.other_reason || cancelForm.errors.reason || cancelForm.errors.booking }}</p><FormField id="cancel-reason" label="Why is this appointment being cancelled?" required :error="cancelForm.errors.reason_code"><AppSelect id="cancel-reason" v-model="cancelForm.reason_code" :class="inputClass" @change="cancelForm.clearErrors('reason_code'); if (cancelForm.reason_code !== 'other') cancelForm.other_reason = ''" :aria-invalid="cancelForm.errors.reason_code ? true : undefined" :aria-describedby="cancelForm.errors.reason_code ? 'cancel-reason-error' : undefined"><option value="" disabled>Select a reason</option><option v-for="reason in options.cancellationReasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option></AppSelect></FormField><FormField v-if="cancelForm.reason_code === 'other'" id="cancel-other-reason" class="mt-4" label="Other reason" required :error="cancelForm.errors.other_reason"><textarea id="cancel-other-reason" v-model="cancelForm.other_reason" rows="3" :class="inputClass" placeholder="Briefly explain why this appointment is being cancelled"  :aria-invalid="cancelForm.errors.other_reason ? true : undefined" :aria-describedby="cancelForm.errors.other_reason ? 'cancel-other-reason-error' : undefined"/></FormField><p class="mt-3 text-xs leading-5 text-[var(--text-muted)]">Client-requested reasons are reported separately from business cancellations.</p></AppDialog>
        <AppDialog id="appointment-note" ref="noteDialog" title="Internal appointment note" description="Visible to authorised staff only." confirm-label="Save note" :close-on-confirm="false" :confirm-disabled="noteForm.processing" @confirm="submitNotes"><p v-if="Object.keys(noteForm.errors).length" class="mb-3 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(noteForm.errors)[0] }}</p><FormField id="appointment-note-text" label="Note" :error="noteForm.errors.notes"><textarea id="appointment-note-text" v-model="noteForm.notes" rows="5" :class="inputClass"  :aria-invalid="noteForm.errors.notes ? true : undefined" :aria-describedby="noteForm.errors.notes ? 'appointment-note-text-error' : undefined"/></FormField></AppDialog>
        <AppDialog id="schedule-block" ref="blockDialog" title="Block staff time" description="Choose a time without an existing appointment or reserved slot." confirm-label="Create block" :close-on-confirm="false" :confirm-disabled="blockForm.processing" @confirm="submitBlock"><div class="space-y-4"><p v-if="Object.keys(blockForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(blockForm.errors)[0] }}</p><FormField id="block-staff" label="Staff" required :error="blockForm.errors.staff"><AppSelect id="block-staff" v-model="blockForm.staff" :class="inputClass" :aria-invalid="blockForm.errors.staff ? true : undefined" :aria-describedby="blockForm.errors.staff ? 'block-staff-error' : undefined"><option v-for="member in options.bookableStaff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></FormField><FormField id="block-kind" label="Type" :error="blockForm.errors.kind"><AppSelect id="block-kind" v-model="blockForm.kind" :class="inputClass" :aria-invalid="blockForm.errors.kind ? true : undefined" :aria-describedby="blockForm.errors.kind ? 'block-kind-error' : undefined"><option value="personal_block">Personal block</option><option value="staff_break">Staff break</option></AppSelect></FormField><FormField id="block-label" label="Calendar label" required :error="blockForm.errors.label"><input id="block-label" v-model="blockForm.label" :class="inputClass"  :aria-invalid="blockForm.errors.label ? true : undefined" :aria-describedby="blockForm.errors.label ? 'block-label-error' : undefined"/></FormField><div class="grid grid-cols-2 gap-3"><FormField id="block-start" label="Starts" :error="blockForm.errors.starts_at"><input id="block-start" v-model="blockForm.starts_at" type="datetime-local" :class="inputClass"  :aria-invalid="blockForm.errors.starts_at ? true : undefined" :aria-describedby="blockForm.errors.starts_at ? 'block-start-error' : undefined"/></FormField><FormField id="block-end" label="Ends" :error="blockForm.errors.ends_at"><input id="block-end" v-model="blockForm.ends_at" type="datetime-local" :class="inputClass"  :aria-invalid="blockForm.errors.ends_at ? true : undefined" :aria-describedby="blockForm.errors.ends_at ? 'block-end-error' : undefined"/></FormField></div><FormField id="block-reason" label="Private reason" required :error="blockForm.errors.reason"><textarea id="block-reason" v-model="blockForm.reason" rows="3" :class="inputClass"  :aria-invalid="blockForm.errors.reason ? true : undefined" :aria-describedby="blockForm.errors.reason ? 'block-reason-error' : undefined"/></FormField></div></AppDialog>
        <AppDialog id="operational-impact" ref="exceptionDialog" title="Record operational impact" description="Lists affected appointments for follow-up. Their times remain unchanged." confirm-label="Record impact" :close-on-confirm="false" :confirm-disabled="exceptionForm.processing" @confirm="submitException"><div class="space-y-4"><p v-if="Object.keys(exceptionForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(exceptionForm.errors)[0] }}</p><FormField id="impact-kind" label="Exception" :error="exceptionForm.errors.kind"><AppSelect id="impact-kind" v-model="exceptionForm.kind" :class="inputClass" :aria-invalid="exceptionForm.errors.kind ? true : undefined" :aria-describedby="exceptionForm.errors.kind ? 'impact-kind-error' : undefined"><option value="late_arrival">Late arrival</option><option value="service_overrun">Service overrun</option><option value="staff_unavailable">Staff unavailable</option></AppSelect></FormField><FormField id="impact-end" label="Projected end" :error="exceptionForm.errors.projected_end"><input id="impact-end" v-model="exceptionForm.projected_end" type="datetime-local" :class="inputClass"  :aria-invalid="exceptionForm.errors.projected_end ? true : undefined" :aria-describedby="exceptionForm.errors.projected_end ? 'impact-end-error' : undefined"/></FormField><FormField id="impact-reason" label="Reason" required :error="exceptionForm.errors.reason"><textarea id="impact-reason" v-model="exceptionForm.reason" rows="3" :class="inputClass"  :aria-invalid="exceptionForm.errors.reason ? true : undefined" :aria-describedby="exceptionForm.errors.reason ? 'impact-reason-error' : undefined"/></FormField></div></AppDialog>
        <AppDialog id="unexpected-closure" ref="closureDialog" title="Record unexpected closure?" description="Every affected appointment will be listed for contact, reschedule, or cancellation. Nothing is changed automatically." confirm-label="Record closure" cancel-label="Keep schedule open" destructive :close-on-confirm="false" :confirm-disabled="closureForm.processing" @confirm="submitClosure"><div class="space-y-4"><p v-if="Object.keys(closureForm.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(closureForm.errors)[0] }}</p><div class="grid grid-cols-2 gap-3"><FormField id="closure-start" label="Starts" :error="closureForm.errors.starts_at"><input id="closure-start" v-model="closureForm.starts_at" type="datetime-local" :class="inputClass"  :aria-invalid="closureForm.errors.starts_at ? true : undefined" :aria-describedby="closureForm.errors.starts_at ? 'closure-start-error' : undefined"/></FormField><FormField id="closure-end" label="Ends" :error="closureForm.errors.ends_at"><input id="closure-end" v-model="closureForm.ends_at" type="datetime-local" :class="inputClass"  :aria-invalid="closureForm.errors.ends_at ? true : undefined" :aria-describedby="closureForm.errors.ends_at ? 'closure-end-error' : undefined"/></FormField></div><FormField id="closure-reason" label="Reason" required :error="closureForm.errors.reason"><textarea id="closure-reason" v-model="closureForm.reason" rows="3" :class="inputClass"  :aria-invalid="closureForm.errors.reason ? true : undefined" :aria-describedby="closureForm.errors.reason ? 'closure-reason-error' : undefined"/></FormField></div></AppDialog>
    </AppLayout>
</template>
