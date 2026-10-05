<script setup>
import '../../../css/clients.css';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { AdjustmentsHorizontalIcon, ArrowRightIcon, MagnifyingGlassIcon, PlusIcon, XMarkIcon, ExclamationCircleIcon, UserGroupIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import FieldError from '@/Components/Product/FieldError.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { clientInitials, clientDate, clientDay } from '@/Support/clientWorkspace';

const props = defineProps({ businessLabel:String, clients:Object, filters:Object, duplicateCount:Number, canContact:Boolean, canCreate:Boolean, canMerge:Boolean, countries:Object, staffOptions:Array });
const page=usePage();
const tenant=()=>page.props.tenant.public_id;
const zone=()=>page.props.tenant?.regional?.time_zone || 'UTC';
const date=value=>clientDate(value,zone());
const day=value=>clientDay(value,zone());
const search=ref(props.filters.search || '');
const relationship=ref(props.filters.relationship || '');
const staff=ref(props.filters.staff || '');
const sort=ref(props.filters.sort || 'name');
const busy=ref(false); const searchError=ref(''); const filterOpen=ref(false);
const searchInput=ref(null); const addDialog=ref(null); const formElement=ref(null);
const filterCount=computed(()=>Number(!!relationship.value)+Number(!!staff.value));
const relationships=[['','All clients'],['upcoming','Upcoming booking'],['unbooked','No upcoming booking'],['returning','Returning clients'],['new','No completed visits'],['lapsed','Last visit over 90 days ago'],['no_shows','No-show history'],...(props.canMerge ? [['duplicates','Possible duplicates']] : [])];
const createForm=useForm({name:'',mobile:'',email:'',referral_source:'Front desk',duplicate_confirmed:false});
const duplicateMatches=ref([]); const duplicateBusy=ref(false); const duplicateError=ref('');
let searchTimer, cancelSearch, duplicateTimer, duplicateRequest, generation=0;
const runSearch=()=>{
    clearTimeout(searchTimer);cancelSearch?.cancel();const requestGeneration=++generation;
    router.get(route('business.clients.index',tenant()),{search:search.value,relationship:relationship.value,staff:staff.value,sort:sort.value},{preserveState:true,preserveScroll:true,replace:true,only:['clients','filters','duplicateCount'],onCancelToken:token=>cancelSearch=token,onStart:()=>busy.value=true,onSuccess:()=>searchError.value='',onNetworkError:()=>{searchError.value='Could not refresh clients. Try again.';return false;},onError:()=>searchError.value='Could not refresh clients. Try again.',onFinish:()=>{if(requestGeneration===generation)busy.value=false;}});
};
watch(search,()=>{clearTimeout(searchTimer);cancelSearch?.cancel();generation++;searchTimer=setTimeout(runSearch,250);});
watch(()=>props.filters,filters=>{search.value=filters.search || '';relationship.value=filters.relationship || '';staff.value=filters.staff || '';sort.value=filters.sort || 'name';});
const clearFilters=()=>{search.value='';relationship.value='';staff.value='';runSearch();};
const profileUrl=client=>route('business.clients.show',[tenant(),client.public_id]);
const openAdd=async()=>{createForm.clearErrors();await addDialog.value?.open();await nextTick();document.getElementById('client-name')?.focus();};
watch(()=>[createForm.mobile,createForm.email],()=>{
    clearTimeout(duplicateTimer);duplicateRequest?.abort();duplicateMatches.value=[];duplicateError.value='';createForm.duplicate_confirmed=false;
    duplicateBusy.value=false;
    if(!props.canContact || (!createForm.mobile && !createForm.email))return;
    duplicateBusy.value=true;
    duplicateTimer=setTimeout(async()=>{
        const request=new AbortController();duplicateRequest=request;
        try{const response=await fetch(route('business.clients.matches',{business:tenant(),mobile:createForm.mobile,email:createForm.email}),{signal:request.signal,headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const result=await response.json();if(!request.signal.aborted)duplicateMatches.value=result.clients;}
        catch(error){if(!request.signal.aborted)duplicateError.value='Duplicate check unavailable. Saving will check again.';}
        finally{if(duplicateRequest===request)duplicateBusy.value=false;}
    },300);
});
const createClient=()=>{if(createForm.processing || !formElement.value?.reportValidity())return;createForm.post(route('business.clients.store',tenant()),{preserveScroll:true,onSuccess:()=>{addDialog.value?.close();createForm.reset();}});};
const keyboard=event=>{if(event.metaKey || event.ctrlKey || event.altKey || document.querySelector('dialog[open]') || event.target.closest('input,textarea,select,[contenteditable]'))return;if(event.key==='/'){event.preventDefault();searchInput.value?.focus();}if(event.key.toLowerCase()==='n' && props.canCreate){event.preventDefault();openAdd();}};
onMounted(()=>document.addEventListener('keydown',keyboard));
onBeforeUnmount(()=>{clearTimeout(searchTimer);clearTimeout(duplicateTimer);duplicateRequest?.abort();cancelSearch?.cancel();document.removeEventListener('keydown',keyboard);});
</script>

<template>
    <AppLayout title="Clients" :business-label="businessLabel">
        <div class="crm-workspace">
            <PageHeader title="Clients" description="Know your clients. Make their next visit effortless."><template #actions><AppButton v-if="canCreate" @click="openAdd"><PlusIcon class="size-4" aria-hidden="true" />Add client</AppButton></template></PageHeader>
            <div class="crm-directory">
                <div class="crm-toolbar">
                    <form class="crm-search" role="search" @submit.prevent="runSearch"><MagnifyingGlassIcon aria-hidden="true" /><label for="clients-search" class="sr-only">Search clients</label><input id="clients-search" ref="searchInput" v-model="search" type="search" autocomplete="off" maxlength="100" :placeholder="canContact ? 'Search name, phone or email' : 'Search client name'" /><kbd v-if="!search" aria-hidden="true">/</kbd><button v-else type="button" aria-label="Clear search" @click="search='';runSearch()"><XMarkIcon aria-hidden="true" /></button></form>
                    <button type="button" class="crm-filter-button" :aria-expanded="filterOpen" aria-controls="client-filters" @click="filterOpen=!filterOpen"><AdjustmentsHorizontalIcon aria-hidden="true" />Filters<span v-if="filterCount" class="crm-filter-count">{{ filterCount }}</span></button>
                    <label class="crm-sort"><span class="sr-only">Sort clients</span><AppSelect v-model="sort" aria-label="Sort clients" class="cd-input" @update:model-value="runSearch"><option value="name">Name A–Z</option><option value="recent">Recently added</option><option value="last_visit">Last visit</option><option value="next_appointment">Next appointment</option><option value="visits">Most visits</option></AppSelect></label>
                </div>
                <div v-if="filterOpen" id="client-filters" class="crm-filters"><label>Relationship<AppSelect v-model="relationship" class="cd-input" @update:model-value="runSearch"><option v-for="[value,label] in relationships" :key="value" :value="value">{{ label }}</option></AppSelect></label><label>Preferred staff<AppSelect v-model="staff" class="cd-input" @update:model-value="runSearch"><option value="">Any staff member</option><option v-for="member in staffOptions" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option></AppSelect></label></div>
                <div class="crm-results-meta" role="status" aria-live="polite"><span>{{ busy ? 'Searching…' : `${clients.total} ${clients.total===1?'client':'clients'}` }}<span v-if="filterCount || search"> · filtered</span></span><button v-if="filterCount || search" type="button" @click="clearFilters">Clear all</button><button v-if="duplicateCount" type="button" @click="relationship='duplicates';runSearch()">{{ duplicateCount }} duplicate review{{ duplicateCount===1?'':'s' }}</button><span v-else-if="!filterCount && !search" class="crm-muted">One profile. Every visit.</span></div>
                <div v-if="filterCount" class="crm-active-filters" aria-label="Active filters"><button v-if="relationship" type="button" @click="relationship='';runSearch()">{{ relationships.find(r=>r[0]===relationship)?.[1] }}<XMarkIcon aria-hidden="true" /></button><button v-if="staff" type="button" @click="staff='';runSearch()">Preferred · {{ staffOptions.find(s=>s.public_id===staff)?.display_name }}<XMarkIcon aria-hidden="true" /></button></div>
                <p v-if="searchError" class="crm-inline-error" role="alert">{{ searchError }} <button type="button" @click="runSearch">Retry</button></p>
                <div class="crm-directory-body" :aria-busy="busy">
                    <div v-if="!clients.data.length" class="crm-empty"><UserGroupIcon aria-hidden="true" /><h2>{{ search || filterCount ? 'No matching clients' : 'Your client relationships start here' }}</h2><p>{{ search || filterCount ? 'Try another name, phone or filter.' : 'Add a client here, or create one when booking an appointment.' }}</p><AppButton v-if="search || filterCount" variant="secondary" @click="clearFilters">Clear search & filters</AppButton><AppButton v-else-if="canCreate" @click="openAdd">Add your first client</AppButton></div>
                    <template v-else>
                        <div class="crm-list-head" aria-hidden="true"><span>Client</span><span>Last visit</span><span>Next appointment</span><span>Preferred staff</span><span class="crm-number">Visits</span><span /></div>
                        <ul class="crm-client-list" aria-label="Client directory">
                            <li v-for="client in clients.data" :key="client.public_id">
                                <Link :href="profileUrl(client)" class="crm-client-row">
                                    <span class="crm-identity"><span class="crm-avatar" aria-hidden="true">{{ clientInitials(client.name) }}</span><span class="crm-client-label"><strong>{{ client.name }}</strong><span v-if="canContact" class="crm-muted">{{ client.mobile || client.email || 'No contact details' }}</span><span v-else class="crm-muted">{{ client.visit_count ? 'Returning client' : 'No completed visits' }}</span><span class="crm-mobile-context">{{ client.next_appointment ? `Next · ${date(client.next_appointment)}` : client.last_visit ? `Last · ${date(client.last_visit)}` : 'No upcoming appointment' }}</span></span><span v-if="client.important_notes" class="crm-note-marker" :title="`${client.important_notes} important visible note${Number(client.important_notes)===1?'':'s'}`"><ExclamationCircleIcon aria-hidden="true" /><span class="sr-only">Important notes</span></span></span>
                                    <span class="crm-last"><span>{{ client.last_visit ? date(client.last_visit) : 'No visits yet' }}</span><small v-if="client.no_show_count">{{ client.no_show_count }} no-show{{ client.no_show_count===1?'':'s' }}</small></span>
                                    <span class="crm-next"><span :class="{'crm-booked':client.next_appointment}">{{ client.next_appointment ? date(client.next_appointment) : 'Not booked' }}</span><small v-if="client.next_appointment">{{ day(client.next_appointment) }}</small></span>
                                    <span class="crm-preferred">{{ client.preferred_staff || 'No preference' }}</span>
                                    <strong class="crm-number crm-visits">{{ client.visit_count }}<small class="crm-mobile-context">visit{{ Number(client.visit_count)===1?'':'s' }}</small></strong><ArrowRightIcon class="crm-row-arrow" aria-hidden="true" />
                                </Link>
                            </li>
                        </ul>
                    </template>
                </div>
                <nav v-if="clients.last_page>1" class="crm-pagination" aria-label="Client pages"><span>{{ clients.from }}–{{ clients.to }} of {{ clients.total }}</span><div><component v-for="link in clients.links" :key="link.label" :is="link.url ? Link : 'span'" :href="link.url || undefined" :aria-current="link.active ? 'page' : undefined" :aria-disabled="!link.url || undefined" :class="{'crm-page-active':link.active}" v-html="link.label" /></div></nav>
            </div>
        </div>
        <AppDialog id="add-client" ref="addDialog" title="Add client" description="Start with the essentials. Preferences and notes can follow." :confirm-label="createForm.processing ? 'Adding…' : 'Add client'" :confirm-disabled="createForm.processing || duplicateBusy" :close-on-confirm="false" @confirm="createClient">
            <form ref="formElement" class="crm-form" @submit.prevent="createClient">
                <label for="client-name">Full name <span class="crm-required">Required</span><input id="client-name" v-model="createForm.name" required maxlength="255" autocomplete="name" class="cd-input" :aria-invalid="!!createForm.errors.name" aria-describedby="client-name-error" /><FieldError id="client-name-error" :message="createForm.errors.name" /></label>
                <label for="new-client-mobile">Mobile number<PhoneInput id="new-client-mobile" v-model="createForm.mobile" :country="page.props.tenant?.regional?.country_code || 'IN'" :countries="countries" :aria-invalid="!!createForm.errors.mobile" aria-describedby="client-mobile-error" /><FieldError id="client-mobile-error" :message="createForm.errors.mobile" /></label>
                <label for="client-email">Email<input id="client-email" v-model="createForm.email" type="email" maxlength="255" autocomplete="email" class="cd-input" :aria-invalid="!!createForm.errors.email" aria-describedby="client-email-error" /><FieldError id="client-email-error" :message="createForm.errors.email" /><small class="crm-muted">Provide a mobile number or email so this record is easy to identify.</small></label>
                <div v-if="duplicateMatches.length || createForm.errors.duplicate_confirmed" class="crm-duplicate-alert"><strong>Review an existing client first</strong><p>This contact may already be in your client directory.</p><Link v-for="match in duplicateMatches" :key="match.public_id" :href="profileUrl(match)">{{ match.name }} <span>{{ match.mobile || match.email }}</span><ArrowRightIcon aria-hidden="true" /></Link><label class="crm-checkbox"><input v-model="createForm.duplicate_confirmed" type="checkbox" />This is a different person sharing these contact details.</label><FieldError :message="createForm.errors.duplicate_confirmed" /></div>
                <p v-if="duplicateBusy || duplicateError" class="crm-muted" role="status">{{ duplicateBusy ? 'Checking for existing clients…' : duplicateError }}</p>
                <details><summary>Referral source <span class="crm-muted">Optional</span></summary><label for="client-referral">How they found you<input id="client-referral" v-model="createForm.referral_source" maxlength="255" class="cd-input" /></label></details>
                <p class="crm-muted">An exact name and contact match opens the existing profile.</p>
            </form>
        </AppDialog>
    </AppLayout>
</template>
