<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ScissorsIcon, PencilSquareIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import FormField from '@/Components/Product/FormField.vue';
import SearchField from '@/Components/Product/SearchField.vue';
import DurationField from '@/Components/Services/DurationField.vue';
import { decimalToMinor, durationLabel } from '@/Support/serviceCatalog';
const props=defineProps({business:Object, services:Array, staff:Array});
const emit=defineEmits(['dirty','locked']);
const page=usePage(), search=ref(''), pageNumber=ref(1), selected=ref(null), editing=ref(false), editor=ref(null), removeDialog=ref(null), discard=ref(null), baseline=ref(''), pending=ref(null), uncertain=ref(false), notice=ref('');
const form=useForm({name:'',price:'',duration_minutes:30,staff_ids:[],online_visible:true,reason:''});
const dirty=computed(()=>editing.value && selected.value && JSON.stringify(form.data())!==baseline.value);
watch([dirty,uncertain],([value,unconfirmed])=>emit('dirty',Boolean(value||unconfirmed)));
watch(uncertain,value=>emit('locked',value));
const filtered=computed(()=>props.services.filter(s=>`${s.name} ${s.category||''}`.toLowerCase().includes(search.value.toLowerCase())));
const visible=computed(()=>filtered.value.slice((pageNumber.value-1)*20,pageNumber.value*20));
const storageKey=computed(()=>`setup-service:${page.props.auth?.user?.id}:${props.business.public_id}`);
const money=s=>new Intl.NumberFormat(props.business.locale||'en',{style:'currency',currency:s.currency_code||props.business.currency_code||'USD'}).format(s.price_minor/100);
const open=async s=>{selected.value=s; editing.value=true; form.defaults({name:s.name,price:(s.price_minor/100).toFixed(2),duration_minutes:s.duration_minutes,staff_ids:[...s.staff_ids],online_visible:s.online_visible,reason:''});form.reset();form.clearErrors();baseline.value=JSON.stringify(form.data());await nextTick();editor.value.open();};
const clearPending=()=>{pending.value=null;uncertain.value=false;try{sessionStorage.removeItem(storageKey.value);}catch{}};
const close=()=>{editor.value?.close();selected.value=null;editing.value=false;emit('dirty',false);};
const canClose=()=>{if(form.processing || uncertain.value)return false;if(dirty.value){discard.value.open();return false;}return true;};
const submit=()=>{
    if(!pending.value){const s=selected.value;const {public_id,currency_code,revision,impact_revision,upcoming_count,starter,bookable,...stored}=s;
        const price=decimalToMinor(form.price);if(price===null){form.setError('price','Enter a valid price, including 0 for a free service.');return;}
        pending.value={id:s.public_id,payload:{...stored,name:form.name,price_minor:price,duration_minutes:Number(form.duration_minutes),staff_ids:[...form.staff_ids],online_visible:form.online_visible,reason:form.reason,revision,impact_revision,command_key:crypto.randomUUID()}};
        try{sessionStorage.setItem(storageKey.value,JSON.stringify(pending.value));}catch{}
    }
    let definitive=false;
    form.transform(()=>pending.value.payload).put(route('business.services.update',[props.business.public_id,pending.value.id]),{
        preserveScroll:true,onSuccess:()=>{definitive=true;clearPending();form.defaults();notice.value='Service saved. Your setup reflects the current catalogue.';close();},
        onError:async errors=>{definitive=true;clearPending();if(errors.price_minor)form.setError('price',errors.price_minor);await nextTick();document.querySelector('#setup-service-editor [aria-invalid="true"]')?.focus();},
        onFinish:()=>{if(!definitive){uncertain.value=true;emit('dirty',true);}},
    });
};
const statusForm=useForm({active:false,revision:'',impact_revision:'',reason:''});
const remove=async s=>{selected.value=s;statusForm.defaults({active:false,revision:s.revision,impact_revision:s.impact_revision,reason:''});statusForm.reset();statusForm.clearErrors();await nextTick();removeDialog.value.open();};
const deactivate=()=>statusForm.patch(route('business.services.status',[props.business.public_id,selected.value.public_id]),{preserveScroll:true,onSuccess:()=>{notice.value='Service deactivated. Your history stays safe.';removeDialog.value.close();selected.value=null;}});
const beforeUnload=e=>{if(dirty.value||uncertain.value){e.preventDefault();e.returnValue='';}};
let stop;
onMounted(()=>{window.addEventListener('beforeunload',beforeUnload);try{const saved=sessionStorage.getItem(storageKey.value);if(saved){pending.value=JSON.parse(saved);uncertain.value=true;emit('dirty',true);}}catch{}});
onBeforeUnmount(()=>{window.removeEventListener('beforeunload',beforeUnload);stop?.();});
</script>
<template>
    <section class="bs-panel bs-services"><header class="bs-panel-heading"><div><h2>Review your services</h2><p>Starter suggestions are labeled. Check prices, duration and who can perform them.</p></div><AppButton :href="route('business.services.index',business.public_id)" variant="secondary" size="small">Full Services settings<ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" /></AppButton></header>
        <div class="bs-toolbar"><SearchField v-model="search" label="Find a service" placeholder="Find a service…" @update:model-value="pageNumber=1" /><span>{{ filtered.length }} services · {{ business.currency_code }}</span></div>
        <p v-if="notice" class="bs-inline-success" role="status">{{ notice }}</p><div v-if="uncertain" class="bs-callout" role="alert"><strong>We couldn’t confirm that save.</strong><p>Check the connection and retry the exact saved request. Your changes are retained.</p><AppButton :disabled="form.processing" @click="submit">{{form.processing?'Saving…':'Retry saved request'}}</AppButton></div>
        <div class="bs-service-list"><article v-for="service in visible" :key="service.public_id" class="bs-service-row"><div class="bs-service-identity"><span class="bs-task-icon"><ScissorsIcon class="size-5" aria-hidden="true" /></span><div><strong>{{ service.name }}</strong><span>{{ service.category || 'Uncategorized' }}<small v-if="service.starter">Starter suggestion</small></span></div></div><div class="bs-service-values"><strong>{{ money(service) }}</strong><span>{{ durationLabel(service.duration_minutes) }}<template v-if="service.processing_minutes || service.cleanup_minutes"> + {{ service.processing_minutes + service.cleanup_minutes }} min buffer</template></span></div><span :class="['bs-status',service.is_active?(service.bookable?'ready':'attention'):'neutral']">{{service.is_active ? (service.bookable?(service.online_visible?'Active · online':'Active · internal'):'Check eligibility & hours'):'Inactive'}}</span><div class="bs-row-actions"><button type="button" :disabled="uncertain" :aria-label="`Edit ${service.name}`" @click="open(service)"><PencilSquareIcon class="size-4" aria-hidden="true" /><span>Edit</span></button><button v-if="service.is_active" type="button" :disabled="uncertain" :aria-label="`Deactivate ${service.name}`" @click="remove(service)">Deactivate</button></div></article>
            <div v-if="!visible.length" class="bs-empty"><h3>{{ services.length ? 'No matching services' : 'Choose the services you want to offer' }}</h3><p>{{ services.length ? 'Try a different name or category.' : 'Your starter categories are retained. Add a service in the full catalogue whenever you’re ready.' }}</p><AppButton v-if="!services.length" :href="route('business.services.index',business.public_id)">Add a service</AppButton></div></div>
        <footer class="bs-panel-footer bs-pagination"><span>Duration reserves time in Calendar. Zero-price services are allowed.</span><div v-if="filtered.length>20"><AppButton size="small" variant="quiet" :disabled="pageNumber===1" @click="pageNumber--">Previous</AppButton><span>{{pageNumber}} / {{Math.ceil(filtered.length/20)}}</span><AppButton size="small" variant="quiet" :disabled="pageNumber*20>=filtered.length" @click="pageNumber++">Next</AppButton></div></footer>
    </section>
    <AppDialog id="setup-service-editor" ref="editor" drawer title="Personalize this service" description="Changes appear in Services and Calendar. Existing appointments keep their booked values." :close-on-confirm="false" :confirm-disabled="form.processing || uncertain" :can-close="canClose" @cancel="selected=null; editing=false" @confirm="submit" :confirm-label="form.processing?'Saving…':'Save service'">
        <form v-if="selected" class="bs-editor-fields" @submit.prevent="submit" ><FormField id="bs-service-name" label="Service name" required :error="form.errors.name"><input id="bs-service-name" v-model="form.name" class="cd-input" required :disabled="uncertain" /></FormField><div class="bs-two-fields"><FormField id="bs-service-price" :label="`Price (${business.currency_code})`" required :error="form.errors.price"><input id="bs-service-price" v-model="form.price" class="cd-input" inputmode="decimal" :disabled="uncertain" /></FormField><FormField id="bs-service-duration" label="Duration" required :error="form.errors.duration_minutes"><DurationField id="bs-service-duration" v-model="form.duration_minutes" :disabled="uncertain" /></FormField></div>
        <fieldset><legend>Who can perform this service?</legend><p class="bs-muted">Select qualified team members. Working hours are managed separately.</p><label v-for="person in staff" :key="person.public_id" class="bs-checkbox"><input v-model="form.staff_ids" :value="person.public_id" type="checkbox" :disabled="uncertain" /><span>{{person.display_name}}<small v-if="person.status!=='active'"> · {{person.status}}</small></span></label><p v-if="!staff.length" class="bs-muted">Add a team member in Team & availability.</p></fieldset>
        <label class="bs-checkbox"><input v-model="form.online_visible" type="checkbox" :disabled="uncertain" />Offer this service on your online booking page</label><FormField v-if="selected.upcoming_count" id="bs-service-reason" :label="`${selected.upcoming_count} upcoming appointments — review reason`" required :error="form.errors.reason"><textarea id="bs-service-reason" v-model="form.reason" class="cd-input" rows="2" :disabled="uncertain" /></FormField><div v-if="Object.keys(form.errors).length" role="alert" class="bs-callout attention">{{ Object.values(form.errors).join(' ') }}</div></form>
    </AppDialog>
    <AppDialog ref="removeDialog" title="Deactivate this service?" description="It will no longer accept new appointments. Existing visits, prices and sales history are retained." confirm-label="Deactivate service" :close-on-confirm="false" :confirm-disabled="statusForm.processing" @confirm="deactivate"><FormField v-if="selected?.upcoming_count" id="bs-deactivate-reason" label="Reason for keeping existing appointments" required :error="statusForm.errors.reason"><textarea id="bs-deactivate-reason" v-model="statusForm.reason" class="cd-input" rows="2" /></FormField><p v-if="Object.keys(statusForm.errors).length" role="alert">{{Object.values(statusForm.errors).join(' ')}}</p></AppDialog>
    <AppDialog ref="discard" title="Discard unsaved service changes?" confirm-label="Discard changes" @confirm="close"><p>Your saved catalogue stays as it is.</p></AppDialog>
</template>
