<script setup>
import { computed, watch, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppSelect from '@/Components/Product/AppSelect.vue';
import AppButton from '@/Components/Product/AppButton.vue';
const props=defineProps({person:Object,business:Object,locations:Array,roles:Array,modules:Array,grantable:Array,canManageOwners:Boolean,invitation:Object});
const emit=defineEmits(['saved','dirty','busy']);
const preset=computed(()=>props.roles.find(r=>r.name===props.person?.membership?.role) || props.roles.find(r=>r.id===props.invitation?.role_id));
const form=useForm({access_role_id:null,custom_access:false,permission_names:[],location_ids:[],reason:''});
const revoke=useForm({reason:''}); const restore=useForm({reason:''}); const revokeOpen=ref(false);
const selectedRole=computed(()=>props.roles.find(r=>String(r.id)===String(form.access_role_id)));
const immutable=computed(()=>props.person?.membership?.is_current || (props.person?.membership?.role==='owner' && !props.canManageOwners));
const permissionLabels={
 'calendar.view.all':'View the team calendar','calendar.view.own':'View own calendar','appointments.manage.all':'Manage team appointments','appointments.manage.own':'Manage own appointments','walk_ins.manage':'Manage walk-ins','schedule.override':'Override booking notice with reason','appointments.delete':'Delete appointments',
 'clients.contact.view':'View client contact details','clients.view':'View client profiles','clients.manage':'Edit clients','clients.notes.manage':'Manage notes','clients.forms.manage':'Manage client forms','clients.attachments.view':'View private attachments','clients.notes.sensitive.view':'View sensitive notes','clients.merge':'Merge clients','clients.privacy.manage':'Manage privacy requests',
 'checkout.manage':'Manage checkout','discounts.apply':'Apply discounts','refunds.issue':'Issue refunds','cash_close.manage':'Close the cash day','revenue.view':'View revenue','commissions.view.all':'View team commissions','commissions.view.own':'View own commissions','audit.view':'View activity log','exports.create':'Export records','staff.manage':'Manage team & availability','settings.manage':'Manage business settings','billing.manage':'Manage subscription','inventory.manage':'Manage inventory',
};
const groups=computed(()=>[
 ...props.modules.map(m=>({...m,permissions:m.permissions.filter(p=>props.grantable.includes(p))})),
 {key:'additional',label:'Additional capabilities',description:'Own-calendar access, sensitive records and other specific responsibilities.',permissions:props.grantable.filter(p=>!props.modules.some(m=>m.permissions.includes(p)))}
].filter(m=>m.permissions.length));
watch(()=>props.person?.public_id,()=>{
 const member=props.person?.membership; form.defaults({access_role_id:preset.value?.id || props.roles.find(r=>r.name==='barber_stylist')?.id,custom_access:!!(member || props.invitation) && !preset.value,permission_names:[...(member?.permission_names || props.invitation?.permission_names || [])],location_ids:[...(member?.location_ids || props.invitation?.location_ids || props.person?.locations?.map(l=>l.public_id) || [])],reason:''});form.reset();form.clearErrors();revoke.reset();restore.reset();revokeOpen.value=false;
},{immediate:true});
watch(()=>form.isDirty || revoke.isDirty || restore.isDirty,v=>emit('dirty',v));
watch(()=>form.processing || revoke.processing || restore.processing,v=>emit('busy',v));
const toggleCustom=()=>{if(form.custom_access && (!props.person.membership || preset.value))form.permission_names=[...(selectedRole.value?.permission_names || [])];};
const save=()=>{
 const options={preserveScroll:true,onSuccess:()=>{form.defaults();emit('dirty',false);emit('saved');}};
 if(props.person.membership)form.patch(route('business.team.memberships.access.update',[props.business.public_id,props.person.membership.public_id]),options);
 else form.post(route('business.team.staff.invite',[props.business.public_id,props.person.public_id]),options);
};
const remove=()=>revoke.delete(route('business.team.memberships.access.destroy',[props.business.public_id,props.person.membership.public_id]),{preserveScroll:true,onSuccess:()=>{emit('dirty',false);emit('saved');}});
const reactivate=()=>restore.post(route('business.team.memberships.access.restore',[props.business.public_id,props.person.membership.public_id]),{preserveScroll:true,onSuccess:()=>emit('saved')});
</script>
<template>
 <div class="tm-access-panel">
  <h3>Workspace access <span class="tm-meta">{{ person.membership ? person.has_login ? 'Active' : 'Disabled' : invitation ? invitation.expired ? 'Invitation expired' : 'Invited' : 'No login' }}</span></h3>
  <p class="tm-meta mb-4">{{ person.membership?.email || person.email || 'Add an email to their profile before inviting them.' }}</p>
  <p v-if="immutable" class="tm-meta">{{ person.membership?.is_current ? 'Manage your own security from Profile & security.' : 'Only an owner may change another owner’s access.' }}</p>
  <form v-else @submit.prevent="save" class="space-y-4">
   <p class="tm-meta">Login permissions and access locations are separate from working hours and service capabilities.</p>
   <template v-if="person.membership && !person.has_login">
    <label class="tm-block-label">Reason to restore access<textarea v-model="restore.reason" class="cd-input" rows="2" placeholder="Why is access being restored?" /></label>
    <AppButton variant="secondary" size="small" :disabled="!restore.reason.trim() || restore.processing" @click="reactivate">Reactivate login</AppButton>
    <p v-for="(e,k) in restore.errors" :key="k" class="tm-error" role="alert">{{ e }}</p>
   </template>
   <label class="tm-block-label">Workspace role<AppSelect v-model="form.access_role_id" :disabled="form.custom_access" class="cd-input"><option v-for="r in roles" :key="r.id" :value="r.id" :disabled="(r.name==='owner' && !canManageOwners) || r.permission_names.some(p=>!grantable.includes(p))">{{ r.label }}</option></AppSelect></label>
   <p v-if="!form.custom_access" class="tm-meta">{{ selectedRole?.description }}</p>
   <label class="tm-check"><input v-model="form.custom_access" type="checkbox" @change="toggleCustom" />Customize capabilities</label>
   <div v-if="form.custom_access">
    <div v-for="group in groups" :key="group.key" class="tm-access-module">
     <strong>{{ group.label }}</strong>
     <div class="tm-permission-grid"><label v-for="p in group.permissions" :key="p" class="tm-check"><input v-model="form.permission_names" type="checkbox" :value="p" />{{ permissionLabels[p] || p.split('.').join(' ') }}</label></div>
    </div>
   </div>
   <fieldset><legend class="tm-block-label">Locations they can access</legend><label v-for="l in locations" :key="l.public_id" class="tm-check"><input v-model="form.location_ids" type="checkbox" :value="l.public_id" />{{ l.name }}</label></fieldset>
   <label class="tm-block-label">Reason <span class="tm-meta">optional</span><textarea v-model="form.reason" class="cd-input" rows="2" placeholder="Change in responsibilities…" /></label>
   <div v-if="Object.keys(form.errors).length" class="tm-error" role="alert"><p v-for="(e,k) in form.errors" :key="k">{{ e }}</p></div>
   <AppButton type="submit" :disabled="form.processing || !form.location_ids.length || (!person.membership && !person.email)">{{ form.processing ? 'Saving…' : person.membership ? 'Save access' : invitation ? 'Resend secure invitation' : 'Send secure invitation' }}</AppButton>
   <div v-if="person.has_login" class="tm-profile-section">
    <button v-if="!revokeOpen" type="button" class="text-xs font-semibold text-[var(--status-danger)]" @click="revokeOpen=true">Disable login…</button>
    <div v-else class="tm-error"><strong>Disable this login immediately?</strong><p>Their sessions end. Working profile and appointment history remain.</p><label class="tm-block-label mt-2">Required reason<textarea v-model="revoke.reason" rows="2" class="cd-input" /></label><p v-for="(e,k) in revoke.errors" :key="k">{{ e }}</p><div class="flex gap-2 mt-3"><AppButton variant="secondary" size="small" @click="revokeOpen=false">Keep access</AppButton><AppButton variant="danger" size="small" :disabled="!revoke.reason.trim() || revoke.processing" @click="remove">Disable login</AppButton></div></div>
   </div>
  </form>
 </div>
</template>
