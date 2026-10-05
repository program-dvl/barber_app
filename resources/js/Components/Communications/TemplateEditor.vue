<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import FieldError from '@/Components/Product/FieldError.vue';
import { variableLabel, previewText, invalidVariables, smsEstimate } from '@/Support/notificationWorkspace';
import { ArrowUturnLeftIcon, EyeIcon, EnvelopeIcon, ChatBubbleLeftIcon } from '@heroicons/vue/24/outline';
const props = defineProps({business:Object, defaults:Object, templates:Array, samples:Object, variables:Object, locale:String});
const emit = defineEmits(['saved']);
const dialog = ref(null), discard = ref(null), reset = ref(null), bodyInput = ref(null), subjectInput = ref(null);
const selected = ref(null), channel = ref('email'), raw = ref(false), busy = ref(false), errors = ref({}), notice = ref(''), insertTarget = ref('body');
const form = reactive({subject:'',body:''});
const baseline = ref(''), draft = ref(null), liveVersion = ref(null);
const dirty = computed(() => selected.value && JSON.stringify(form) !== baseline.value);
const allowed = computed(() => props.variables[selected.value?.type] || []);
const invalid = computed(() => invalidVariables(form.subject+'\n'+form.body,allowed.value));
const rendered = computed(() => previewText(form.body,props.samples,raw.value));
const estimate = computed(() => smsEstimate(previewText(form.body,props.samples)));
const canPublish = computed(() => draft.value?.status === 'draft' && !dirty.value && !busy.value);
const validate = () => {
    errors.value = {};
    if (!form.body.trim()) errors.value.body = 'Write a message before saving.';
    else if (invalid.value.length) errors.value.body = 'Check these variables: '+invalid.value.map(variableLabel).join(', ')+'.';
    if (channel.value==='email' && !form.subject.trim()) errors.value.subject='Add an email subject.';
    return !Object.keys(errors.value).length;
};
function load() {
    const records=props.templates.filter(t=>t.intent_type===selected.value.type && t.channel===channel.value && t.locale===props.locale);
    draft.value=records.sort((a,b)=>b.version-a.version)[0] || null;
    liveVersion.value=records.filter(t=>t.status==='published').sort((a,b)=>b.version-a.version)[0]?.version || null;
    form.subject=draft.value?.subject ?? props.defaults[selected.value.type].subject;
    form.body=draft.value?.body ?? props.defaults[selected.value.type].body;
    baseline.value=JSON.stringify(form); errors.value={}; notice.value=''; raw.value=false; insertTarget.value='body';
}
async function open(automation, nextChannel='email') { selected.value=automation; channel.value=nextChannel; load(); await dialog.value.open(); }
const pendingChannel=ref(null);
function closeAllowed() { if(busy.value) return false; if(dirty.value) {pendingChannel.value=null; discard.value.open(); return false;} return true; }
function switchChannel(value) { if(channel.value===value) return; if(dirty.value) {pendingChannel.value=value; discard.value.open();} else { channel.value=value; load(); } }
function discardChanges() { if(pendingChannel.value) {channel.value=pendingChannel.value; load();} else {dialog.value.close(); selected.value=null;} }
async function insert(name) {
    const field = channel.value==='email' ? insertTarget.value : 'body';
    const input=field==='subject'?subjectInput.value:bodyInput.value;
    const start=input?.selectionStart ?? form[field].length, end=input?.selectionEnd ?? start;
    const token='{{'+name+'}}'; form[field]=form[field].slice(0,start)+token+form[field].slice(end);
    await nextTick(); input?.focus(); input?.setSelectionRange(start+token.length,start+token.length);
}
function restore() { form.subject=props.defaults[selected.value.type].subject; form.body=props.defaults[selected.value.type].body; draft.value=null; notice.value='Default text restored in this editor. Save and publish to use it.'; }
async function errorFocus(error) { errors.value=error.response?.data?.errors || {body:error.response?.data?.message || 'The result could not be confirmed. Keep this editor open and try again after checking your connection.'}; await nextTick(); (errors.value.subject?subjectInput.value:bodyInput.value)?.focus(); }
async function saveDraft() {
    if(busy.value) return;
    if(!validate()) {await nextTick(); (errors.value.subject?subjectInput.value:bodyInput.value)?.focus(); return;}
    busy.value=true;
    try {
        const records=props.templates.filter(t=>t.intent_type===selected.value.type && t.channel===channel.value && t.locale===props.locale);
        const response=await axios.post(route('business.communications.templates.store',props.business.public_id), {intent_type:selected.value.type, channel:channel.value, locale:props.locale, subject:channel.value==='email'?form.subject:null, body:form.body, base_version:Math.max(0,...records.map(t=>t.version))});
        draft.value=response.data.template; baseline.value=JSON.stringify(form); emit('saved',draft.value); notice.value='Draft saved. Publish when the preview looks right.';
    } catch(error) {await errorFocus(error);} finally {busy.value=false;}
}
async function publish() {
    if(!canPublish.value) return; busy.value=true; errors.value={};
    try {const response=await axios.post(route('business.communications.templates.publish',[props.business.public_id,draft.value.public_id])); draft.value=response.data.template; liveVersion.value=draft.value.version; emit('saved',draft.value); notice.value='Published. New notifications will use this version; already queued messages keep their original template.';}
    catch(error) {await errorFocus(error);} finally {busy.value=false;}
}
const stop=router.on('before',event=>{ if(dirty.value || busy.value) {event.preventDefault(); notice.value='Save or discard your draft before leaving this page.';} });
const unload=e=>{if(dirty.value || busy.value){e.preventDefault();e.returnValue='';}};
if(typeof window!=='undefined') window.addEventListener('beforeunload',unload);
onBeforeUnmount(()=>{stop();if(typeof window!=='undefined')window.removeEventListener('beforeunload',unload);});
defineExpose({open});
</script>
<template>
    <AppDialog ref="dialog" class="notify-editor-dialog" :title="selected?.name || 'Message template'" description="Review the client experience, save a draft, then publish when ready." drawer :can-close="closeAllowed" :close-on-confirm="false">
        <template v-if="selected">
            <div class="notify-editor-meta"><div class="notify-channel-tabs" aria-label="Message channel"><button v-for="c in ['email','sms']" :key="c" type="button" :aria-pressed="channel===c" @click="switchChannel(c)"><EnvelopeIcon v-if="c==='email'"/><ChatBubbleLeftIcon v-else/>{{ c==='email'?'Email':'SMS' }}</button></div><span>{{ locale }} · {{ draft?.status==='draft'?'Draft v'+draft.version:liveVersion?'Published v'+liveVersion:'System default' }}</span></div>
            <div class="notify-rule-strip"><span>{{ selected.trigger }}</span><span aria-hidden="true">→</span><strong>{{ selected.timing }}</strong><span aria-hidden="true">→</span><span>Eligible client</span></div>
            <div class="notify-editor-grid">
                <section class="notify-editor-content">
                    <label v-if="channel==='email'" class="notify-label" for="notification-subject">Email subject<input id="notification-subject" ref="subjectInput" v-model="form.subject" class="cd-input" maxlength="255" :aria-invalid="!!errors.subject" aria-describedby="notification-subject-error" @focus="insertTarget='subject'"><FieldError id="notification-subject-error" :message="errors.subject"/></label>
                    <label class="notify-label" for="notification-body">{{ channel==='email'?'Message body':'Text message' }}<textarea id="notification-body" ref="bodyInput" v-model="form.body" class="cd-input notify-body-input" maxlength="5000" :aria-invalid="!!errors.body" aria-describedby="notification-body-error notification-content-hint" @focus="insertTarget='body'"/><FieldError id="notification-body-error" :message="errors.body"/></label>
                    <p id="notification-content-hint" class="notify-muted">Plain text. Variables are replaced with each client’s appointment details. Secure links are generated at sending.</p>
                    <details class="notify-variables" open><summary>Insert {{ channel==='email' && insertTarget==='subject'?'in subject':'in message' }}</summary><div class="notify-variable-grid"><button v-for="name in allowed" :key="name" type="button" :title="`Insert ${variableLabel(name)}: ${samples[name]}`" @click="insert(name)"><strong>{{ variableLabel(name) }}</strong><small>{{ samples[name] }}</small></button></div></details>
                    <button type="button" class="notify-text-action" @click="reset.open()"><ArrowUturnLeftIcon/>Restore system default</button>
                </section>
                <aside class="notify-preview-panel"><div class="notify-preview-heading"><h3><EyeIcon/>Client preview</h3><label><input v-model="raw" type="checkbox">Show variables</label></div><p class="notify-muted">Illustrative sample · no message will be sent.</p><div :class="['notify-message-preview',channel]">
                    <template v-if="channel==='email'"><div class="notify-email-envelope"><span>To: Sarah · sarah@example.test</span><strong>{{ previewText(form.subject,samples,raw) }}</strong></div><div class="notify-email-content"><p class="notify-preview-copy">{{ rendered }}</p></div></template>
                    <template v-else><p class="notify-sms-from">{{ business.name }}</p><div class="notify-sms-bubble notify-preview-copy">{{ rendered }}</div><p class="notify-sms-caption">Text message · SMS</p></template>
                </div><p v-if="channel==='sms'" class="notify-sms-estimate">{{ estimate.units }} {{ estimate.encoding==='Unicode'?'UTF-16 units':'characters' }} · ~{{ estimate.segments }} {{ estimate.segments===1?'segment':'segments' }} (sample)</p><p class="notify-muted">{{ channel==='sms'?'Actual segment count depends on client values and carrier rules.':'Email uses this plain text layout. No custom HTML or image branding is applied.' }}</p></aside>
            </div>
            <p v-if="notice" role="status" class="notify-editor-notice">{{ notice }}</p><p v-for="(error,field) in errors" v-show="!['body','subject'].includes(field)" :key="field" role="alert" class="notify-error">{{ error }}</p>
        </template>
        <template #footer><AppButton variant="secondary" :disabled="busy" @click="closeAllowed() && dialog.close()">Close</AppButton><AppButton variant="secondary" :disabled="busy || (!dirty && !!draft)" @click="saveDraft">{{ busy?'Working…':'Save draft' }}</AppButton><AppButton :disabled="!canPublish" @click="publish">Publish draft</AppButton></template>
    </AppDialog>
    <AppDialog ref="discard" title="Discard unsaved text?" description="Your published message stays in place. Changes made since the last draft save will be lost." confirm-label="Discard changes" @confirm="discardChanges"/>
    <AppDialog ref="reset" title="Restore the system default?" description="This replaces the text in the editor. Your published version stays in place until you save and publish." confirm-label="Restore default" @confirm="restore"/>
</template>
