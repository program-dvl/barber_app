<script setup>
import '../../../css/clients.css';
import AppDialog from '@/Components/Product/AppDialog.vue';
import { clientInitials, clientDate, clientDay, clientMoney, clientInstant, clientCommunicationChannels } from '@/Support/clientWorkspace';
import { reportDateTime } from '@/Support/reportPresentation';
import FieldError from '@/Components/Product/FieldError.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onBeforeUnmount, ref } from 'vue';
import { ArrowLeftIcon, ArrowRightIcon, CalendarDaysIcon, EnvelopeIcon, PhoneIcon, PlusIcon, PencilSquareIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    businessLabel: String, client: Object, summary: Object, appointments: Array, notes: Array, consents: Array,
    forms: Array, formTemplates: Array, staffOptions: Array, serviceOptions: Array, attachments: Array,
    privacyRequests: Array, duplicates: Array, permissions: Object, communications: Array, communicationHistoryUrl: String,
    upcoming: Object, financial: Object, frequentServices: Array, walkIns: Array, visitPagination: Object, recentAppointments: Array, lastAppointment: String, section: String,
});
const page = usePage();
const formatTimestamp = value => reportDateTime(value, page.props.tenant?.regional?.time_zone || 'UTC', page.props.tenant?.regional?.locale || undefined);
const tenant = () => page.props.tenant.public_id;
const contactChannels = computed(() => clientCommunicationChannels(props.client.communication_preferences));
const profile = useForm({ name: props.client.name, email: props.client.email, mobile: props.client.mobile, date_of_birth: props.client.date_of_birth, referral_source: props.client.referral_source, preferred_staff: props.client.preferred_staff, preferred_services: props.client.preferred_services, preferences: props.client.preferences, communication_preferences: clientCommunicationChannels(props.client.communication_preferences), version: props.client.version, reason: '' });
const tags = ref([...props.client.tags]);
const tagDraft = ref('');
const preferenceText = ref(props.client.preferences?.notes ?? '');
const note = useForm({ kind: 'general', visibility: 'standard', content: '', important: false });
const attachment = useForm({ attachment: null, kind: 'file', visibility: 'standard' });
const formRequest = useForm({ template: props.formTemplates[0]?.public_id ?? '', appointment: '' });
const privacy = useForm({ type: 'export', details: { changes: { name: '', email: '', mobile: '' }, consent_type: 'marketing', reason: '' } });
const newBuilderField = () => ({ id: '', label: '', type: 'text', required: false, options_text: '' });
const formBuilder = useForm({ template: '', name: '', purpose: 'consultation', title: '', introduction: '', services: [], fields: [newBuilderField()] });
const mergePreview = ref(null);
const mergeReason = ref('');
const mergeError=ref('');const merging=ref(false);
const activeTab = ref(props.section || 'overview');
const editDialog=ref(null);const noteDialog=ref(null);const editForm=ref(null);const noteFormElement=ref(null);
const savingProfile=ref(false);
const zone=()=>page.props.tenant?.regional?.time_zone || 'UTC';
const date=(value,timeZone=zone())=>clientDate(value,timeZone);
const day=(value,timeZone=zone())=>clientDay(value,timeZone);
const money=(value,currency)=>clientMoney(value,currency,page.props.tenant?.regional?.locale);
const importantNotes=computed(()=>props.notes.filter(n=>n.important));
const bookUrl=(previous=null)=>route('business.calendar',{business:tenant(),client:props.client.public_id,create:1,...(previous ? {rebook:previous} : {})});
const calendarUrl=visit=>route('business.calendar',{business:tenant(),appointment:visit.public_id,location:visit.location_id,date:visit.local_date,view:'staff'});
const checkoutUrl=visit=>route('business.checkout.index',{business:tenant(),appointment:visit.public_id});
const month=value=>new Intl.DateTimeFormat(undefined,{month:'short',timeZone:props.upcoming?.time_zone || zone()}).format(clientInstant(value));
const monthDay=value=>new Intl.DateTimeFormat(undefined,{day:'numeric',timeZone:props.upcoming?.time_zone || zone()}).format(clientInstant(value));
const editDirty=computed(()=>profile.isDirty || JSON.stringify(tags.value)!==JSON.stringify(props.client.tags) || preferenceText.value!==(props.client.preferences?.notes ?? ''));
const discardEdit=()=>{if(!editDirty.value)return true;if(!window.confirm('Discard your unsaved client changes?'))return false;profile.reset();tags.value=[...props.client.tags];preferenceText.value=props.client.preferences?.notes || '';return true;};
const openEdit=async()=>{await editDialog.value?.open();await nextTick();document.getElementById('field-show-profile-name')?.focus();};
const openNote=async()=>{await noteDialog.value?.open();await nextTick();document.getElementById('field-show-note-content')?.focus();};
const leaveWarning=event=>{if(editDirty.value){event.preventDefault();event.returnValue='';}};
onMounted(()=>window.addEventListener('beforeunload',leaveWarning));
const stopNavigationGuard=router.on('before',()=>{if(editDirty.value && !savingProfile.value)return window.confirm('Leave this profile with unsaved changes?');});
onBeforeUnmount(()=>{window.removeEventListener('beforeunload',leaveWarning);stopNavigationGuard();});
const sectionLink=(url,section)=>url+`${url.includes('?')?'&':'?'}section=${section}`;
const tabs = [{id:'overview',label:'Overview'},{id:'visits',label:'Visits'},{id:'payments',label:'Payments'},{id:'notes',label:'Notes'},{id:'records',label:'Client records'}];
const visibleTabs = computed(()=>tabs.filter(tab=>tab.id!=='payments' || props.permissions.finance));
const tabList = ref(null);
async function moveTab(event) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const items = visibleTabs.value;
    const current = items.findIndex(tab => tab.id === activeTab.value);
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
        : (current + (event.key === 'ArrowRight' ? 1 : -1) + items.length) % items.length;
    activeTab.value = items[index].id;
    await nextTick();
    tabList.value?.querySelector('[aria-selected="true"]')?.focus();
}
const humanLabel = value => (value || 'Not recorded').replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
const addTag = () => {
    const value = tagDraft.value.trim();
    if (value && !tags.value.includes(value)) tags.value.push(value);
    tagDraft.value = '';
};

const saveProfile = () => {if(profile.processing || !editForm.value?.reportValidity())return;savingProfile.value=true;profile.transform(data => ({
    ...data,
    tags: tags.value,
    preferences: { ...(data.preferences ?? {}), notes: preferenceText.value.trim() || null },
})).patch(route('business.clients.update', [tenant(), props.client.public_id]), {
    onSuccess: () => {
        profile.version = page.props.client.version;
        profile.reason = '';profile.defaults();tags.value=[...page.props.client.tags];preferenceText.value=page.props.client.preferences?.notes || '';editDialog.value?.close();
    },
    onFinish:()=>{savingProfile.value=false;},
});};
const addNote = () => {if(note.processing || !noteFormElement.value?.reportValidity())return;note.post(route('business.clients.notes.store', [tenant(), props.client.public_id]), { preserveScroll:true,onSuccess: () => {note.reset('content','important');noteDialog.value?.close();} });};
const upload = () => attachment.post(route('business.clients.attachments.store', [tenant(), props.client.public_id]), { forceFormData: true, onSuccess: () => attachment.reset('attachment') });
const requestForm = () => formRequest.post(route('business.clients.forms.request', [tenant(), props.client.public_id]));
const loadTemplate = () => {
    const selected = props.formTemplates.find(template => template.public_id === formBuilder.template);
    if (!selected) {
        formBuilder.name = '';
        formBuilder.purpose = 'consultation';
        formBuilder.title = '';
        formBuilder.introduction = '';
        formBuilder.services = [];
        formBuilder.fields = [newBuilderField()];
        return;
    }
    formBuilder.name = selected.name;
    formBuilder.purpose = selected.purpose;
    formBuilder.title = selected.title;
    formBuilder.introduction = selected.introduction ?? '';
    formBuilder.services = [...selected.services];
    formBuilder.fields = selected.fields.map(field => ({ ...field, options_text: (field.options ?? []).join('\n') }));
};
const publishTemplate = () => formBuilder.transform(data => ({
    ...data,
    template: data.template || null,
    fields: data.fields.map(field => ({
        id: field.id || undefined,
        label: field.label,
        type: field.type,
        required: field.required,
        options: field.type === 'multiple_choice' ? field.options_text.split('\n').map(option => option.trim()).filter(Boolean) : [],
    })),
})).post(route('business.clients.forms.publish', tenant()), {
    onSuccess: () => {
        const published = page.props.formTemplates.find(template => template.name === formBuilder.name);
        if (published) {
            formBuilder.template = published.public_id;
            loadTemplate();
        }
    },
});
const submitPrivacy = () => privacy.transform(data => {
    if (data.type === 'correction') {
        return { type: data.type, details: { changes: Object.fromEntries(Object.entries(data.details.changes).filter(([, value]) => value?.trim())) } };
    }
    if (data.type === 'consent_withdrawal') return { type: data.type, details: { consent_type: data.details.consent_type } };
    if (data.type === 'deletion_anonymization') return { type: data.type, details: { reason: data.details.reason } };
    return { type: data.type, details: {} };
}).post(route('business.clients.privacy.store', [tenant(), props.client.public_id]));
const processPrivacy = item => useForm({}).post(route('business.clients.privacy.process', [tenant(), props.client.public_id, item.public_id]));
const issueAttachment = item => useForm({}).post(route('business.clients.attachments.link', [tenant(), props.client.public_id, item.public_id]));
const previewMerge = async candidate => {
    const url = route('business.clients.duplicates.preview', [tenant(), candidate.id]) + `?survivor=${encodeURIComponent(props.client.public_id)}`;
    mergeError.value='';merging.value=true;
    try {const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });if(!response.ok)throw new Error();mergePreview.value={candidate,evidence:await response.json()};}
    catch {mergeError.value='Could not load the merge preview. Try again.';}finally{merging.value=false;}
};
const confirmMerge = () => {
    if(!window.confirm(`Merge ${mergePreview.value.candidate.other.name} into ${props.client.name}? The other profile will be retired and its history retained here.`))return;
    const candidate = mergePreview.value.candidate;
    useForm({ survivor: props.client.public_id, survivor_version: props.client.version, duplicate_version: candidate.other.version, reason: mergeReason.value, confirmed: true })
        .post(route('business.clients.duplicates.merge', [tenant(), candidate.id]));
};
</script>

<template>
    <AppLayout :title="client.name" :business-label="businessLabel">
        <div class="crm-workspace">
        <Link :href="route('business.clients.index', tenant())" class="crm-back"><ArrowLeftIcon aria-hidden="true" />All clients</Link>
        <header>
            <div class="crm-profile-header"><span class="crm-avatar" aria-hidden="true">{{ clientInitials(client.name) }}</span><div class="crm-profile-identity"><h1>{{ client.name }}</h1><div class="crm-profile-meta"><span>{{ summary.visit_count ? 'Returning client' : 'No completed visits' }}</span><span>Client since {{ date(client.created_at) }}</span><span v-if="client.status!=='active'">{{ humanLabel(client.status) }}</span></div></div><div class="crm-profile-actions"><AppButton v-if="permissions.update" variant="secondary" @click="openEdit"><PencilSquareIcon class="size-4" aria-hidden="true" />Edit profile</AppButton><AppButton v-if="permissions.book" :href="bookUrl()"><PlusIcon class="size-4" aria-hidden="true" />Book appointment</AppButton></div></div>
            <div v-if="permissions.contact" class="crm-contacts"><a v-if="client.mobile" :href="`tel:${client.mobile.replace(/[\s().-]/g,'')}`"><PhoneIcon aria-hidden="true" />{{ client.mobile }}</a><a v-if="client.email" :href="`mailto:${client.email}`"><EnvelopeIcon aria-hidden="true" />{{ client.email }}</a><span v-if="!client.mobile && !client.email" class="crm-muted">No contact details saved</span></div>
            <dl class="crm-stats"><div><dt>Completed visits</dt><dd>{{ summary.visit_count }}</dd></div><div><dt>Last visit</dt><dd>{{ summary.last_visit ? date(summary.last_visit) : 'No visits yet' }}</dd></div><div v-if="financial"><dt>Net spend</dt><dd v-for="total in financial.totals" :key="total.currency_code">{{ money(total.net_minor,total.currency_code) }}</dd><dd v-if="!financial.totals.length">No completed sales</dd></div><div><dt>Cancellations / no-shows</dt><dd>{{ summary.cancellations }} <small>/</small> {{ summary.no_shows }}</dd></div></dl>
        </header>
        <div ref="tabList" class="crm-tabs" role="tablist" aria-label="Client profile sections" @keydown="moveTab"><button v-for="tab in visibleTabs" :id="`client-tab-${tab.id}`" :key="tab.id" type="button" role="tab" :tabindex="activeTab === tab.id ? 0 : -1" :aria-selected="activeTab === tab.id" aria-controls="client-section-panel" @click="activeTab = tab.id">{{ tab.label }}</button></div>

        <div id="client-section-panel" role="tabpanel" :aria-labelledby="`client-tab-${activeTab}`" tabindex="0">

        <div v-show="activeTab==='overview'" class="crm-overview">
            <div class="crm-overview-main">
                <section class="crm-panel"><div class="crm-panel-header"><h2>{{ upcoming && ['in_service','checked_in','arrived','late'].includes(upcoming.status) ? 'Current visit' : 'Next appointment' }}</h2><span v-if="upcoming" class="crm-status" :data-status="upcoming.status">{{ humanLabel(upcoming.status) }}</span></div>
                    <template v-if="upcoming"><div class="crm-next-visit"><div class="crm-date-tile" aria-hidden="true"><small>{{ month(upcoming.starts_at) }}</small><strong>{{ monthDay(upcoming.starts_at) }}</strong></div><div class="crm-next-info"><h3>{{ upcoming.services.map(s=>s.name).join(' · ') || 'Appointment' }}</h3><p>{{ date(upcoming.starts_at,upcoming.time_zone) }} · {{ day(upcoming.starts_at,upcoming.time_zone) }} · {{ upcoming.duration }} min</p><p>{{ upcoming.services.flatMap(s=>s.performers).filter((v,i,a)=>a.indexOf(v)===i).join(', ') || 'Staff not assigned' }} · {{ upcoming.location }}</p><small class="crm-muted">{{ upcoming.time_zone }}</small></div></div><div class="crm-next-footer"><span class="crm-muted">{{ upcoming.reference }}</span><Link v-if="upcoming.calendar" class="crm-link" :href="calendarUrl(upcoming)">View in Calendar →</Link></div></template>
                    <div v-else class="crm-quiet-empty"><p>No upcoming appointment.</p><Link v-if="permissions.book && lastAppointment" class="crm-rebook" :href="bookUrl(lastAppointment)">Rebook their last visit →</Link><p v-else>Ready when they are. Book their next visit from this profile.</p></div>
                </section>
                <section class="crm-panel"><div class="crm-panel-header"><h2>Recent visits</h2><button type="button" @click="activeTab='visits'">All visits</button></div><p v-if="!recentAppointments.length" class="crm-quiet-empty">No appointments yet.</p><div v-for="visit in recentAppointments" :key="visit.public_id" class="crm-recent-visit"><time>{{ date(visit.starts_at,visit.time_zone) }}</time><div class="crm-recent-info"><strong>{{ visit.services.map(s=>s.name).join(' · ') || 'Appointment' }}</strong><span class="crm-status" :data-status="visit.status">{{ humanLabel(visit.status) }}<span v-if="visit.source==='walk_in'"> · Walk-in</span></span><small>{{ visit.services.flatMap(s=>s.performers).filter((v,i,a)=>a.indexOf(v)===i).join(', ') }}</small></div><Link v-if="permissions.book && ['completed','no_show','cancelled_by_client','cancelled_by_shop'].includes(visit.status)" :href="bookUrl(visit.public_id)" class="crm-rebook">Rebook</Link><Link v-else-if="visit.calendar" :href="calendarUrl(visit)" class="crm-rebook">View</Link></div></section>
                <section v-if="financial && (financial.awaiting_checkout || financial.totals.some(t=>Number(t.outstanding_minor)>0))" class="crm-panel"><div class="crm-panel-header"><h2>Payment follow-up</h2><button type="button" @click="activeTab='payments'">View payments</button></div><div class="crm-panel-body"><p v-for="total in financial.totals.filter(t=>Number(t.outstanding_minor)>0)" :key="total.currency_code"><strong>{{ money(total.outstanding_minor,total.currency_code) }}</strong> outstanding on open checkouts</p><p v-if="financial.awaiting_checkout" class="crm-muted">{{ financial.awaiting_checkout }} completed visit{{ financial.awaiting_checkout===1?'':'s' }} awaiting checkout.</p><button v-if="permissions.finance" class="crm-rebook" type="button" @click="activeTab='visits'">Review completed visits →</button></div></section>
                <div v-if="permissions.merge && duplicates.length" class="crm-panel-body crm-panel"><p class="text-sm">{{ duplicates.length }} possible duplicate profile{{ duplicates.length===1?'':'s' }} to review.</p><button type="button" class="crm-rebook" @click="activeTab='records'">Compare client records →</button></div>
                <Link v-if="permissions.walkIn" :href="route('business.walk-ins.index',{business:tenant(),client:client.public_id})" class="crm-rebook">Add {{ client.name.split(' ')[0] }} to Walk-in Queue →</Link>
            </div>
            <aside class="crm-overview-aside">
                <section v-if="importantNotes.length" class="crm-panel crm-important-panel"><div class="crm-panel-header"><h2>Before their service</h2><button type="button" @click="activeTab='notes'">All notes</button></div><div v-for="item in importantNotes" :key="item.id" class="crm-important"><strong>{{ humanLabel(item.kind) }}{{ item.visibility==='sensitive' ? ' · Sensitive' : '' }}</strong><p class="whitespace-pre-wrap">{{ item.content }}</p><small class="crm-muted">{{ item.author }} · {{ date(item.created_at) }}</small></div></section>
                <section class="crm-panel"><div class="crm-panel-header"><h2>Preferences & context</h2><button v-if="permissions.update" type="button" @click="openEdit">Edit</button></div><div class="crm-panel-body"><dl class="crm-preference-list"><div><dt>Preferred staff</dt><dd>{{ client.preferred_staff_name || 'No preference recorded' }}</dd></div><div v-if="client.preferred_service_names.length"><dt>Preferred services</dt><dd>{{ client.preferred_service_names.join(', ') }}</dd></div><div v-if="client.preferences?.notes"><dt>Service preferences</dt><dd class="whitespace-pre-wrap">{{ client.preferences.notes }}</dd></div><div v-if="contactChannels.length"><dt>Contact preference</dt><dd>{{ contactChannels.map(c=>c==='sms'?'Text message':humanLabel(c)).join(', ') }}</dd></div><div v-if="client.date_of_birth"><dt>Birthday</dt><dd>{{ client.date_of_birth }}</dd></div></dl><div v-if="client.tags.length" class="crm-tags"><span v-for="tag in client.tags" :key="tag" class="crm-tag">{{ tag }}</span></div></div></section>
                <section class="crm-panel crm-services-panel"><div class="crm-panel-header"><h2>Services they return for</h2></div><div class="crm-panel-body"><p v-if="!frequentServices.length" class="crm-muted">Service patterns appear after completed visits.</p><div v-for="service in frequentServices" :key="service.name" class="crm-service-row"><span>{{ service.name }}</span><small>{{ service.count }} visit{{ Number(service.count)===1?'':'s' }}</small></div><p v-if="frequentServices.length" class="crm-muted mt-2">From completed visits · not a saved preference</p></div></section>
            </aside>
        </div>
        <AppDialog v-if="permissions.update" id="edit-client" ref="editDialog" title="Edit client profile" drawer :close-on-confirm="false" :can-close="discardEdit" :confirm-disabled="profile.processing" confirm-label="Save profile" @confirm="saveProfile">
            <div class="crm-form">
                <form ref="editForm" class="crm-edit-layout" @submit.prevent="saveProfile">
                    <label class="text-sm font-medium"><span>Name<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span></span><input v-model="profile.name" class="cd-input mt-1 w-full" required id="field-show-profile-name" :aria-invalid="profile.errors.name ? true : undefined" :aria-describedby="profile.errors.name ? 'field-show-profile-name-error' : undefined"><FieldError id="field-show-profile-name-error" :message="profile.errors.name" /></label>
                    <label v-if="permissions.contact" class="text-sm font-medium sm:col-span-2" for="client-profile-mobile">Mobile<PhoneInput id="client-profile-mobile" v-model="profile.mobile" class="mt-1" :country="page.props.tenant?.regional?.country_code || 'IN'"  :aria-invalid="profile.errors.mobile ? true : undefined" :aria-describedby="profile.errors.mobile ? 'client-profile-mobile-error' : undefined"/><FieldError id="client-profile-mobile-error" :message="profile.errors.mobile" /></label>
                    <label v-if="permissions.contact" class="text-sm font-medium">Email<input v-model="profile.email" type="email" class="cd-input mt-1 w-full" id="field-show-profile-email" :aria-invalid="profile.errors.email ? true : undefined" :aria-describedby="profile.errors.email ? 'field-show-profile-email-error' : undefined"><FieldError id="field-show-profile-email-error" :message="profile.errors.email" /></label>
                    <label class="text-sm font-medium">Birthday<input v-model="profile.date_of_birth" type="date" class="cd-input mt-1 w-full" id="field-show-profile-date-of-birth" :aria-invalid="profile.errors.date_of_birth ? true : undefined" :aria-describedby="profile.errors.date_of_birth ? 'field-show-profile-date-of-birth-error' : undefined"><FieldError id="field-show-profile-date-of-birth-error" :message="profile.errors.date_of_birth" /></label>
                    <label class="text-sm font-medium">Preferred staff member<AppSelect v-model="profile.preferred_staff" class="cd-input mt-1 w-full" id="field-show-profile-preferred-staff" :aria-invalid="profile.errors.preferred_staff ? true : undefined" :aria-describedby="profile.errors.preferred_staff ? 'field-show-profile-preferred-staff-error' : undefined"><option :value="null">No preference</option><option v-for="staff in staffOptions" :key="staff.public_id" :value="staff.public_id">{{ staff.display_name }}</option></AppSelect><FieldError id="field-show-profile-preferred-staff-error" :message="profile.errors.preferred_staff" /></label>
                    <label class="text-sm font-medium">Preferred services<AppSelect v-model="profile.preferred_services" class="cd-input mt-1 min-h-24 w-full" multiple id="field-show-profile-preferred-services" :aria-invalid="profile.errors.preferred_services ? true : undefined" :aria-describedby="profile.errors.preferred_services ? 'field-show-profile-preferred-services-error' : undefined"><option v-for="service in serviceOptions" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect><FieldError id="field-show-profile-preferred-services-error" :message="profile.errors.preferred_services" /></label>
                    <fieldset class="sm:col-span-2"><legend class="text-sm font-medium">Tags</legend><div v-if="tags.length" class="mt-2 flex flex-wrap gap-2"><button v-for="tag in tags" :key="tag" type="button" class="cd-status gap-1 bg-[var(--surface-subtle)] text-[var(--text-strong)]" :aria-label="`Remove ${tag} tag`" @click="tags = tags.filter(item => item !== tag)">{{ tag }} <XMarkIcon class="size-3.5" aria-hidden="true" /></button></div><div class="mt-2 flex gap-2"><input v-model="tagDraft" class="cd-input" placeholder="Add a tag" @keydown.enter.prevent="addTag"><AppButton type="button" variant="secondary" class="crm-add-tag" @click="addTag">Add</AppButton></div></fieldset>
                    <label class="text-sm font-medium sm:col-span-2">Service preferences<textarea v-model="preferenceText" class="cd-input mt-1 min-h-24 w-full" placeholder="Preferred finish, refreshments, accessibility needs, or other service context" /></label>
                    <fieldset class="sm:col-span-2"><legend class="text-sm font-medium">Communication preferences</legend><div class="mt-1 flex flex-wrap gap-4"><label v-for="channel in ['email', 'sms']" :key="channel" class="flex min-h-11 items-center gap-2 text-sm capitalize"><input v-model="profile.communication_preferences" type="checkbox" :value="channel"> {{ channel === 'sms' ? 'Text message (SMS)' : 'Email' }}</label></div><p class="crm-muted mt-1">SMS also requires recorded consent. Clearing a channel stops queued messages.</p></fieldset>
                    <label class="text-sm font-medium sm:col-span-2">How they found you<AppSelect v-model="profile.referral_source" class="cd-input mt-1" id="field-show-profile-referral-source" :aria-invalid="profile.errors.referral_source ? true : undefined" :aria-describedby="profile.errors.referral_source ? 'field-show-profile-referral-source-error' : undefined"><option :value="null">Not recorded</option><option v-if="profile.referral_source && !['walk_in','friend_or_family','google','instagram','facebook','other'].includes(profile.referral_source)" :value="profile.referral_source">{{ profile.referral_source }}</option><option value="walk_in">Walk-in</option><option value="friend_or_family">Friend or family</option><option value="google">Google</option><option value="instagram">Instagram</option><option value="facebook">Facebook</option><option value="other">Other</option></AppSelect><FieldError id="field-show-profile-referral-source-error" :message="profile.errors.referral_source" /></label>
                    <label class="text-sm font-medium sm:col-span-2">Why are you updating this profile?<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="profile.reason" class="cd-input mt-1" required id="field-show-profile-reason" :aria-invalid="profile.errors.reason ? true : undefined" :aria-describedby="profile.errors.reason ? 'field-show-profile-reason-error' : undefined"><option value="" disabled>Choose a reason</option><option value="Client asked us to correct their details.">Client requested a correction</option><option value="Updated during an appointment or visit.">Updated during a visit</option><option value="Administrative data quality correction.">Administrative correction</option></AppSelect><FieldError id="field-show-profile-reason-error" :message="profile.errors.reason" /></label>
                    <FieldError class="sm:col-span-2" :message="profile.errors.version" />
                </form>

            </div>
        </AppDialog>

        <section v-show="activeTab==='notes'" class="crm-profile-section"><div class="crm-note-heading"><h2>Notes & service context</h2><AppButton v-if="permissions.addNote" @click="openNote"><PlusIcon class="size-4" aria-hidden="true" />Add note</AppButton></div><div class="crm-panel">
                <p v-if="!notes.length" class="crm-quiet-empty">No visible notes added.</p><ul v-else class="crm-note-list"><li v-for="item in notes" :key="item.id" :data-important="item.important"><div><strong>{{ humanLabel(item.kind) }}{{ item.important ? ' · Important' : '' }}{{ item.visibility==='sensitive' ? ' · Sensitive' : '' }}</strong><span>{{ item.author }} · {{ formatTimestamp(item.created_at) }}</span></div><p>{{ item.content }}</p></li></ul>
            </div></section>
            <AppDialog v-if="permissions.addNote" id="client-note" ref="noteDialog" title="Add client note" :confirm-label="note.processing ? 'Saving…' : 'Add note'" :confirm-disabled="note.processing" :close-on-confirm="false" @confirm="addNote">
                <form ref="noteFormElement" class="crm-form" @submit.prevent="addNote">
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-medium">Note type<AppSelect v-model="note.kind" class="cd-input mt-1 w-full" id="field-show-note-kind" :aria-invalid="note.errors.kind ? true : undefined" :aria-describedby="note.errors.kind ? 'field-show-note-kind-error' : undefined"><option v-for="kind in ['general','allergy','sensitivity','formula','hair','skin','treatment','patch_test','preference','warning']" :key="kind" :value="kind">{{ humanLabel(kind) }}</option></AppSelect><FieldError id="field-show-note-kind-error" :message="note.errors.kind" /></label><label class="text-sm font-medium">Visibility<AppSelect v-model="note.visibility" class="cd-input mt-1 w-full" id="field-show-note-visibility" :aria-invalid="note.errors.visibility ? true : undefined" :aria-describedby="note.errors.visibility ? 'field-show-note-visibility-error' : undefined"><option value="standard">Standard</option><option v-if="permissions.sensitive" value="sensitive">Sensitive</option></AppSelect><FieldError id="field-show-note-visibility-error" :message="note.errors.visibility" /></label></div>
                    <label class="text-sm font-medium"><span>Note content<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span></span><textarea v-model="note.content" class="cd-input mt-1 min-h-28 w-full" placeholder="Add client context" required  id="field-show-note-content" :aria-invalid="note.errors.content ? true : undefined" :aria-describedby="note.errors.content ? 'field-show-note-content-error' : undefined"/><FieldError id="field-show-note-content-error" :message="note.errors.content" /></label>
                    <label class="flex min-h-11 items-center gap-2"><input v-model="note.important" type="checkbox"> Mark as important</label>
                </form>
            </AppDialog>
        <section v-show="activeTab==='visits'" class="crm-profile-section crm-panel"><div class="crm-panel-header"><h2>Appointments & walk-in visits</h2><span class="crm-muted">{{ visitPagination.total }} appointments · authorized history</span></div><p v-if="!appointments.length" class="crm-quiet-empty">No appointments yet.</p><div v-else class="crm-history-wrap"><table class="crm-history"><caption class="sr-only">Client appointment history</caption><thead><tr><th scope="col">Date & time</th><th scope="col">Service & staff</th><th scope="col">Status</th><th class="crm-secondary" scope="col">Source</th><th v-if="permissions.finance" scope="col" class="crm-number">Booked price</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody><tr v-for="visit in appointments" :key="visit.public_id"><td>{{ date(visit.starts_at,visit.time_zone) }}<small>{{ day(visit.starts_at,visit.time_zone) }}</small><small class="crm-secondary">{{ visit.location }} · {{ visit.time_zone }}</small></td><td><div v-for="service in visit.services" :key="service.name"><strong>{{ service.name }}</strong><small>{{ service.performers.join(', ') }}</small></div><small>{{ visit.duration }} min · {{ visit.reference }}</small></td><td><span class="crm-status" :data-status="visit.status">{{ humanLabel(visit.status) }}</span></td><td class="crm-secondary">{{ humanLabel(visit.source) }}</td><td v-if="permissions.finance" class="crm-number">{{ money(visit.price_minor,visit.currency) }}</td><td><div class="crm-history-actions"><Link v-if="visit.calendar" :href="calendarUrl(visit)" class="crm-rebook">View</Link><Link v-if="permissions.book" :href="bookUrl(visit.public_id)" class="crm-rebook">Rebook</Link><Link v-if="visit.checkout" :href="checkoutUrl(visit)" class="crm-rebook">Checkout</Link></div></td></tr></tbody></table></div><nav v-if="visitPagination.last_page>1" class="crm-pagination" aria-label="Visit history pages"><span>{{ visitPagination.from }}–{{ visitPagination.to }} of {{ visitPagination.total }}</span><div><component v-for="link in visitPagination.links" :key="link.label" :is="link.url?Link:'span'" :href="link.url?sectionLink(link.url,'visits'):undefined" :aria-current="link.active?'page':undefined" :class="{'crm-page-active':link.active}" v-html="link.label" /></div></nav><div v-if="walkIns.length" class="crm-panel-body"><h3 class="text-sm font-semibold">Queue activity without an appointment</h3><p class="crm-muted mt-1">Converted walk-ins appear above. Queue entries do not count as completed visits.</p><div v-for="entry in walkIns" :key="entry.public_id" class="crm-service-row"><span>{{ date(entry.arrived_at) }} · {{ entry.service }}</span><span class="crm-status" :data-status="entry.status">{{ humanLabel(entry.status) }}</span></div><p class="crm-muted">Latest {{ walkIns.length }} entries</p></div></section>
        <section v-if="financial" v-show="activeTab==='payments'" class="crm-profile-section"><dl v-for="total in financial.totals" :key="total.currency_code" class="crm-finance-totals"><div><dt>Net spend · {{ total.currency_code }}</dt><dd>{{ money(total.net_minor,total.currency_code) }}</dd></div><div><dt>Average completed sale</dt><dd>{{ Number(total.sale_count) ? money(Math.round(Number(total.net_minor)/Number(total.sale_count)),total.currency_code) : '—' }}</dd></div><div><dt>Outstanding · open checkouts</dt><dd>{{ money(total.outstanding_minor,total.currency_code) }}</dd></div><div><dt>Refunds / tips</dt><dd>{{ money(total.refunded_minor,total.currency_code) }} / {{ money(total.tips_minor,total.currency_code) }}</dd></div></dl><p class="crm-muted mb-3">Net spend includes completed sales and applied deposits, less refunds. Currencies are kept separate.</p><p v-if="financial.awaiting_checkout" class="crm-muted mb-4">{{ financial.awaiting_checkout }} completed visit{{ financial.awaiting_checkout===1 ? '' : 's' }} awaiting checkout. A missing checkout is not a recorded zero balance.</p><div class="crm-panel"><div class="crm-panel-header"><h2>Payment history</h2><span class="crm-muted">Recorded payments & refunds</span></div><p v-if="!financial.payments.data.length" class="crm-quiet-empty">No recorded payments yet.</p><div v-else class="crm-history-wrap"><table class="crm-history"><caption class="sr-only">Client payments</caption><thead><tr><th scope="col">Date</th><th scope="col">Reference</th><th scope="col">Type / method</th><th scope="col">Status</th><th scope="col" class="crm-number">Amount</th></tr></thead><tbody><tr v-for="payment in financial.payments.data" :key="payment.public_id"><td>{{ date(payment.at) }}<small>{{ day(payment.at) }}</small></td><td>{{ payment.reference }}</td><td>{{ humanLabel(payment.kind) }}<small>{{ humanLabel(payment.method) }}</small></td><td>{{ humanLabel(payment.status) }}</td><td class="crm-number">{{ money(payment.amount_minor,payment.currency) }}</td></tr></tbody></table></div><nav v-if="financial.payments.last_page>1" class="crm-pagination" aria-label="Payment history pages"><span>{{ financial.payments.from }}–{{ financial.payments.to }} of {{ financial.payments.total }}</span><div><component v-for="link in financial.payments.links" :key="link.label" :is="link.url?Link:'span'" :href="link.url?sectionLink(link.url,'payments'):undefined" :aria-current="link.active?'page':undefined" :class="{'crm-page-active':link.active}" v-html="link.label" /></div></nav></div></section>
        <details v-if="permissions.forms" v-show="activeTab === 'records'" class="mt-5">
            <summary class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-sm font-semibold text-[var(--action-primary)]">Manage form templates</summary>
        <SurfaceCard v-if="permissions.forms" class="mt-3" title="Form templates" description="When wording changes, ClipperDesk publishes a new version while completed forms keep exactly what the client saw.">
            <form class="cd-form-width grid gap-4" @submit.prevent="publishTemplate">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium">Start or revise<AppSelect v-model="formBuilder.template" class="cd-input mt-1 w-full" @change="loadTemplate" id="field-show-formbuilder-template" :aria-invalid="formBuilder.errors.template ? true : undefined" :aria-describedby="formBuilder.errors.template ? 'field-show-formbuilder-template-error' : undefined"><option value="">New template</option><option v-for="template in formTemplates" :key="template.public_id" :value="template.public_id">{{ template.name }} · v{{ template.current_version }}</option></AppSelect><FieldError id="field-show-formbuilder-template-error" :message="formBuilder.errors.template" /></label>
                    <label class="text-sm font-medium">Internal name<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><input v-model="formBuilder.name" class="cd-input mt-1 w-full" required id="field-show-formbuilder-name" :aria-invalid="formBuilder.errors.name ? true : undefined" :aria-describedby="formBuilder.errors.name ? 'field-show-formbuilder-name-error' : undefined"><FieldError id="field-show-formbuilder-name-error" :message="formBuilder.errors.name" /></label>
                    <label class="text-sm font-medium">Purpose<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="formBuilder.purpose" class="cd-input mt-1" required id="field-show-formbuilder-purpose" :aria-invalid="formBuilder.errors.purpose ? true : undefined" :aria-describedby="formBuilder.errors.purpose ? 'field-show-formbuilder-purpose-error' : undefined"><option value="consultation">Consultation</option><option value="treatment">Treatment</option><option value="allergy">Allergy</option><option value="consent">Consent</option><option value="intake">Client intake</option><option value="other">Other</option></AppSelect><FieldError id="field-show-formbuilder-purpose-error" :message="formBuilder.errors.purpose" /></label>
                    <label class="text-sm font-medium">Title shown to clients<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><input v-model="formBuilder.title" class="cd-input mt-1 w-full" required id="field-show-formbuilder-title" :aria-invalid="formBuilder.errors.title ? true : undefined" :aria-describedby="formBuilder.errors.title ? 'field-show-formbuilder-title-error' : undefined"><FieldError id="field-show-formbuilder-title-error" :message="formBuilder.errors.title" /></label>
                    <label class="text-sm font-medium sm:col-span-2">Introduction<textarea v-model="formBuilder.introduction" class="cd-input mt-1 min-h-24 w-full"  id="field-show-formbuilder-introduction" :aria-invalid="formBuilder.errors.introduction ? true : undefined" :aria-describedby="formBuilder.errors.introduction ? 'field-show-formbuilder-introduction-error' : undefined"/><FieldError id="field-show-formbuilder-introduction-error" :message="formBuilder.errors.introduction" /></label>
                    <label class="text-sm font-medium sm:col-span-2">Associated services<AppSelect v-model="formBuilder.services" class="cd-input mt-1 min-h-24 w-full" multiple id="field-show-formbuilder-services" :aria-invalid="formBuilder.errors.services ? true : undefined" :aria-describedby="formBuilder.errors.services ? 'field-show-formbuilder-services-error' : undefined"><option v-for="service in serviceOptions" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></AppSelect><FieldError id="field-show-formbuilder-services-error" :message="formBuilder.errors.services" /></label>
                </div>
                <fieldset><legend class="font-semibold">Fields</legend><div class="mt-3 space-y-3">
                    <div v-for="(field, index) in formBuilder.fields" :key="index" class="grid gap-3 rounded-xl border border-[var(--border-subtle)] p-3 sm:grid-cols-2">
                        <label class="text-sm font-medium">Question or wording<input v-model="field.label" class="cd-input mt-1 w-full" required></label>
                        <label class="text-sm font-medium">Answer type<AppSelect v-model="field.type" class="cd-input mt-1 w-full"><option v-for="type in ['text','number','date','yes_no','multiple_choice','signature']" :key="type" :value="type">{{ humanLabel(type) }}</option></AppSelect></label>
                        <details class="text-sm"><summary class="min-h-11 cursor-pointer py-3 font-medium text-[var(--text-muted)]">Advanced field settings</summary><label class="mt-1 block text-sm font-medium">Field reference <span class="font-normal text-[var(--text-muted)]">(optional)</span><input v-model="field.id" pattern="[a-z0-9_-]+" class="cd-input mt-1" placeholder="Generated automatically"></label></details>
                        <label v-if="field.type === 'multiple_choice'" class="text-sm font-medium">Choices <span class="font-normal text-[var(--text-muted)]">(one per line)</span><textarea v-model="field.options_text" class="cd-input mt-1 min-h-24 w-full" required /></label>
                        <label class="flex min-h-11 items-center gap-2 text-sm font-medium"><input v-model="field.required" type="checkbox"> Required answer</label>
                        <button v-if="formBuilder.fields.length > 1" type="button" class="min-h-11 justify-self-start font-semibold text-[var(--status-danger)]" @click="formBuilder.fields.splice(index, 1)">Remove field</button>
                    </div>
                </div></fieldset>
                <div class="flex flex-wrap gap-3"><AppButton type="button" variant="secondary" @click="formBuilder.fields.push(newBuilderField())">Add field</AppButton><AppButton type="submit" :disabled="formBuilder.processing">Publish new version</AppButton></div>
            </form>
        </SurfaceCard>
        </details>

        <div v-show="activeTab === 'records'" class="mt-6 grid gap-6 xl:grid-cols-2">
            <SurfaceCard v-show="activeTab === 'records'" title="Forms and consent" description="Review requested and completed forms, or send a secure form link for this client.">
<p v-if="!forms.length" class="crm-muted mb-4">No forms requested yet.</p>
                <ul v-if="forms.length" class="mb-4 space-y-2"><li v-for="item in forms" :key="item.public_id" class="rounded-lg bg-[var(--surface-subtle)] p-3 text-sm"><strong>{{ item.title }} v{{ item.version }}</strong> · {{ item.status }}</li></ul>
                <form v-if="permissions.forms && formTemplates.length" class="grid gap-3" @submit.prevent="requestForm"><label class="text-sm font-medium">Form template<AppSelect v-model="formRequest.template" class="cd-input mt-1 w-full" id="field-show-formrequest-template" :aria-invalid="formRequest.errors.template ? true : undefined" :aria-describedby="formRequest.errors.template ? 'field-show-formrequest-template-error' : undefined"><option v-for="template in formTemplates" :key="template.public_id" :value="template.public_id">{{ template.name }} · v{{ template.current_version }}</option></AppSelect><FieldError id="field-show-formrequest-template-error" :message="formRequest.errors.template" /></label><label class="text-sm font-medium">Originating appointment<AppSelect v-model="formRequest.appointment" class="cd-input mt-1 w-full" id="field-show-formrequest-appointment" :aria-invalid="formRequest.errors.appointment ? true : undefined" :aria-describedby="formRequest.errors.appointment ? 'field-show-formrequest-appointment-error' : undefined"><option value="">No appointment link</option><option v-for="visit in appointments" :key="visit.public_id" :value="visit.public_id">{{ visit.reference }}</option></AppSelect><FieldError id="field-show-formrequest-appointment-error" :message="formRequest.errors.appointment" /></label><AppButton type="submit" :disabled="formRequest.processing">Create form link</AppButton></form>
                <div v-if="consents.length" class="mt-5"><h3 class="font-semibold">Consent history</h3><ul class="mt-2 space-y-2 text-sm"><li v-for="(consent, index) in consents" :key="index">{{ humanLabel(consent.type) }} · {{ humanLabel(consent.status) }} · {{ formatTimestamp(consent.occurred_at) }}</li></ul></div>
            </SurfaceCard>

            <SurfaceCard v-if="permissions.attachments" v-show="activeTab === 'records'" title="Private files" description="Store visit photos and documents privately. Download links expire automatically.">
<p v-if="!attachments.length" class="crm-muted mb-4">No files added.</p>
                <ul v-if="attachments.length" class="mb-4 space-y-2"><li v-for="item in attachments" :key="item.public_id" class="flex items-center justify-between gap-3 rounded-lg bg-[var(--surface-subtle)] p-3 text-sm"><span class="truncate">{{ item.kind }} · {{ item.original_name }}</span><button type="button" class="min-h-11 font-semibold text-[var(--action-primary)]" @click="issueAttachment(item)">Get link</button></li></ul>
                <form v-if="permissions.attachments" class="grid gap-3" @submit.prevent="upload"><label class="text-sm font-medium">Choose attachment<input class="crm-file-input mt-1 block min-h-11 w-full" type="file" accept="image/jpeg,image/png,application/pdf" required @change="attachment.attachment = $event.target.files[0]"></label><div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-medium">Attachment type<AppSelect v-model="attachment.kind" class="cd-input mt-1 w-full" id="field-show-attachment-kind" :aria-invalid="attachment.errors.kind ? true : undefined" :aria-describedby="attachment.errors.kind ? 'field-show-attachment-kind-error' : undefined"><option value="file">File</option><option value="before">Before photo</option><option value="after">After photo</option><option value="profile_photo">Profile photo</option></AppSelect><FieldError id="field-show-attachment-kind-error" :message="attachment.errors.kind" /></label><label class="text-sm font-medium">Attachment visibility<AppSelect v-model="attachment.visibility" class="cd-input mt-1 w-full" id="field-show-attachment-visibility" :aria-invalid="attachment.errors.visibility ? true : undefined" :aria-describedby="attachment.errors.visibility ? 'field-show-attachment-visibility-error' : undefined"><option value="standard">Standard</option><option v-if="permissions.sensitive" value="sensitive">Sensitive</option></AppSelect><FieldError id="field-show-attachment-visibility-error" :message="attachment.errors.visibility" /></label></div><FieldError :message="attachment.errors.attachment" /><AppButton class="justify-self-start" type="submit" :disabled="attachment.processing || !attachment.attachment">{{ attachment.processing ? 'Uploading…' : 'Upload file' }}</AppButton></form>
            </SurfaceCard>
        </div>

        <div v-if="permissions.merge && duplicates.length" v-show="activeTab === 'records'" class="mt-6">
            <SurfaceCard title="Possible duplicates" description="Compare the evidence before choosing which profile to keep. ClipperDesk never merges a possible match automatically.">
                <p v-if="mergeError" role="alert" class="crm-inline-error">{{ mergeError }}</p><ul class="space-y-3"><li v-for="candidate in duplicates" :key="candidate.id" class="rounded-xl border border-[var(--border-subtle)] p-4"><p class="font-semibold">{{ candidate.other.name }} · {{ candidate.confidence }}% candidate</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ candidate.reasons.join(', ').replaceAll('_', ' ') }}</p><AppButton class="mt-3" :disabled="merging" variant="secondary" @click="previewMerge(candidate)">Preview merge</AppButton></li></ul>
                <div v-if="mergePreview" class="mt-4 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4"><h3 class="font-semibold">Keep {{ mergePreview.evidence.survivor.name }}</h3><p class="mt-1 text-sm">Merge {{ mergePreview.evidence.duplicate.name }} into this profile. Their history will be preserved; the other profile will be retired.</p><p v-if="mergePreview.evidence.fields_filled_from_duplicate.length" class="mt-2 text-xs">Missing fields to fill: {{ mergePreview.evidence.fields_filled_from_duplicate.map(humanLabel).join(', ') }}</p><ul class="mt-2 text-sm"><li v-for="(count, relation) in mergePreview.evidence.relationship_counts" :key="relation">{{ relation.replaceAll('_', ' ') }}: {{ count }}</li></ul><label class="mt-3 block text-sm font-medium">Required merge reason<input v-model="mergeReason" class="cd-input mt-1 w-full"></label><AppButton class="mt-3" :disabled="!mergeReason.trim() || merging" @click="confirmMerge">Merge into this profile</AppButton></div>
            </SurfaceCard>
        </div>

        <SurfaceCard v-if="permissions.privacy" v-show="activeTab === 'records'" class="mt-6" title="Privacy requests" description="Record and track exports, corrections, consent withdrawals, and deletion or anonymisation reviews.">
            <form class="cd-form-width grid gap-3 sm:grid-cols-2" @submit.prevent="submitPrivacy">
                <label class="text-sm font-medium">Request type<AppSelect v-model="privacy.type" class="cd-input mt-1 w-full" id="field-show-privacy-type" :aria-invalid="privacy.errors.type ? true : undefined" :aria-describedby="privacy.errors.type ? 'field-show-privacy-type-error' : undefined"><option value="export">Data export</option><option value="correction">Correction</option><option value="consent_withdrawal">Consent withdrawal</option><option value="deletion_anonymization">Deletion / anonymisation review</option></AppSelect><FieldError id="field-show-privacy-type-error" :message="privacy.errors.type" /></label>
                <label v-if="privacy.type === 'consent_withdrawal'" class="text-sm font-medium">Consent to withdraw<AppSelect v-model="privacy.details.consent_type" class="cd-input mt-1 w-full" required><option value="marketing">Marketing</option><option value="photography">Photography</option><option value="treatment">Treatment</option></AppSelect></label>
                <template v-if="privacy.type === 'correction'">
                    <label class="text-sm font-medium">Corrected name<input v-model="privacy.details.changes.name" class="cd-input mt-1 w-full"></label>
                    <label class="text-sm font-medium">Corrected email<input v-model="privacy.details.changes.email" type="email" class="cd-input mt-1 w-full"></label>
                    <label class="text-sm font-medium" for="privacy-corrected-mobile">Corrected mobile<PhoneInput id="privacy-corrected-mobile" v-model="privacy.details.changes.mobile" class="mt-1" :country="page.props.tenant?.regional?.country_code || 'IN'" /></label>
                </template>
                <label v-if="privacy.type === 'deletion_anonymization'" class="text-sm font-medium sm:col-span-2">Request context<textarea v-model="privacy.details.reason" class="cd-input mt-1 min-h-24 w-full" required /></label>
                <AppButton class="sm:col-span-2 justify-self-start" type="submit" :disabled="privacy.processing">{{ privacy.processing ? 'Saving…' : 'Log request' }}</AppButton>
            </form>
            <ul v-if="privacyRequests.length" class="mt-4 divide-y divide-[var(--border-subtle)]"><li v-for="item in privacyRequests" :key="item.public_id" class="flex flex-wrap items-center justify-between gap-3 py-3"><span class="text-sm"><strong>{{ item.type.replaceAll('_', ' ') }}</strong> · {{ item.status.replaceAll('_', ' ') }}</span><AppButton v-if="item.status === 'submitted'" variant="secondary" @click="processPrivacy(item)">Process</AppButton></li></ul>
        </SurfaceCard>
        <section v-if="permissions.contact" v-show="activeTab==='records'" class="crm-profile-section crm-panel"><div class="crm-panel-header"><h2>Recent client notifications</h2><Link v-if="communicationHistoryUrl" :href="communicationHistoryUrl" class="crm-text-action">View delivery history</Link><span v-else class="crm-muted">Latest 6 notifications</span></div><p v-if="!communications.length" class="crm-quiet-empty">No recorded communications.</p><div v-for="(message,index) in communications" :key="index" class="crm-recent-visit"><time>{{ date(message.queued_at) }}</time><div class="crm-recent-info"><strong>{{ humanLabel(message.intent) }}</strong><small>{{ humanLabel(message.channel) }} · {{ message.simulated && ['sent','delivered'].includes(message.status) ? 'Simulated' : message.status==='suppressed'?'Not sent':humanLabel(message.status) }}</small></div><span class="crm-muted">{{ message.simulated && ['sent','delivered'].includes(message.status) ? 'No client delivery' : message.delivered_at ? 'Delivered '+date(message.delivered_at) : 'Delivery not confirmed' }}</span></div></section>
        </div>
        </div>
    </AppLayout>
</template>
