<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    businessLabel: String, client: Object, summary: Object, appointments: Array, notes: Array, consents: Array,
    forms: Array, formTemplates: Array, staffOptions: Array, serviceOptions: Array, attachments: Array,
    privacyRequests: Array, duplicates: Array, permissions: Object,
});
const page = usePage();
const tenant = () => page.props.tenant.public_id;
const profile = useForm({ name: props.client.name, email: props.client.email, mobile: props.client.mobile, date_of_birth: props.client.date_of_birth, referral_source: props.client.referral_source, preferred_staff: props.client.preferred_staff, preferred_services: props.client.preferred_services, preferences: props.client.preferences, communication_preferences: props.client.communication_preferences, version: props.client.version, reason: '' });
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
const activeTab = ref('overview');
const tabs = [
    { id: 'overview', label: 'Overview' }, { id: 'visits', label: 'Visits' },
    { id: 'forms', label: 'Forms & consent' }, { id: 'files', label: 'Files' }, { id: 'privacy', label: 'Privacy' },
];
const humanLabel = value => value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
const addTag = () => {
    const value = tagDraft.value.trim();
    if (value && !tags.value.includes(value)) tags.value.push(value);
    tagDraft.value = '';
};

const saveProfile = () => profile.transform(data => ({
    ...data,
    tags: tags.value,
    preferences: { ...(data.preferences ?? {}), notes: preferenceText.value.trim() || null },
})).patch(route('business.clients.update', [tenant(), props.client.public_id]), {
    onSuccess: () => {
        profile.version = page.props.client.version;
        profile.reason = '';
    },
});
const addNote = () => note.post(route('business.clients.notes.store', [tenant(), props.client.public_id]), { onSuccess: () => note.reset('content') });
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
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (response.ok) mergePreview.value = { candidate, evidence: await response.json() };
};
const confirmMerge = () => {
    const candidate = mergePreview.value.candidate;
    useForm({ survivor: props.client.public_id, survivor_version: props.client.version, duplicate_version: candidate.other.version, reason: mergeReason.value, confirmed: true })
        .post(route('business.clients.duplicates.merge', [tenant(), candidate.id]));
};
</script>

<template>
    <AppLayout :title="client.name" :business-label="businessLabel">
        <Link :href="route('business.clients.index', tenant())" class="inline-flex min-h-11 items-center text-sm font-semibold text-[var(--action-primary)]">← Client directory</Link>
        <PageHeader class="mt-3" eyebrow="Client profile" :title="client.name" :description="client.mobile || client.email || 'Contact details are hidden or unavailable.'" />

        <nav class="gh-section-nav mt-5" aria-label="Client profile sections"><button v-for="tab in tabs" :key="tab.id" v-show="tab.id !== 'privacy' || permissions.privacy" type="button" class="gh-section-nav-item" role="tab" :aria-selected="activeTab === tab.id" @click="activeTab = tab.id">{{ tab.label }}</button></nav>

        <div v-show="activeTab === 'overview'" class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div v-for="metric in [['Visits', summary.visit_count], ['Spend', summary.financial_history_status === 'awaiting_checkout_ledger' ? '—' : summary.lifetime_spend_minor], ['Cancellations', summary.cancellations], ['No-shows', summary.no_shows], ['Last visit', summary.last_visit ? new Date(summary.last_visit).toLocaleDateString() : '—'], ['Next visit', summary.next_appointment ? new Date(summary.next_appointment).toLocaleDateString() : '—']]" :key="metric[0]" class="rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ metric[0] }}</p><p class="mt-1 font-semibold text-[var(--text-strong)]">{{ metric[1] }}</p>
            </div>
        </div>

        <div v-show="activeTab === 'overview'" class="mt-6 grid gap-6 xl:grid-cols-2">
            <SurfaceCard title="Profile and preferences" description="Contact details, service preferences, and helpful context for future visits.">
                <form v-if="permissions.update" class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveProfile">
                    <label class="text-sm font-medium">Name<input v-model="profile.name" class="ds-input mt-1 w-full" required></label>
                    <label class="text-sm font-medium">Mobile<input v-model="profile.mobile" class="ds-input mt-1 w-full"></label>
                    <label class="text-sm font-medium">Email<input v-model="profile.email" type="email" class="ds-input mt-1 w-full"></label>
                    <label class="text-sm font-medium">Birthday<input v-model="profile.date_of_birth" type="date" class="ds-input mt-1 w-full"></label>
                    <label class="text-sm font-medium">Preferred employee<select v-model="profile.preferred_staff" class="ds-input mt-1 w-full"><option :value="null">No preference</option><option v-for="staff in staffOptions" :key="staff.public_id" :value="staff.public_id">{{ staff.display_name }}</option></select></label>
                    <label class="text-sm font-medium">Preferred services<select v-model="profile.preferred_services" class="ds-input mt-1 min-h-24 w-full" multiple><option v-for="service in serviceOptions" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></select></label>
                    <fieldset class="sm:col-span-2"><legend class="text-sm font-medium">Tags</legend><div v-if="tags.length" class="mt-2 flex flex-wrap gap-2"><button v-for="tag in tags" :key="tag" type="button" class="gh-status gap-1 bg-[var(--surface-subtle)] text-[var(--text-strong)]" :aria-label="`Remove ${tag} tag`" @click="tags = tags.filter(item => item !== tag)">{{ tag }} <XMarkIcon class="size-3.5" aria-hidden="true" /></button></div><div class="mt-2 flex gap-2"><input v-model="tagDraft" class="gh-input" placeholder="Add a tag" @keydown.enter.prevent="addTag"><AppButton type="button" variant="secondary" @click="addTag">Add</AppButton></div></fieldset>
                    <label class="text-sm font-medium sm:col-span-2">Service preferences<textarea v-model="preferenceText" class="ds-input mt-1 min-h-24 w-full" placeholder="Preferred finish, refreshments, accessibility needs, or other service context" /></label>
                    <fieldset class="sm:col-span-2"><legend class="text-sm font-medium">Communication preferences</legend><div class="mt-1 flex flex-wrap gap-4"><label v-for="channel in ['email', 'whatsapp']" :key="channel" class="flex min-h-11 items-center gap-2 text-sm capitalize"><input v-model="profile.communication_preferences" type="checkbox" :value="channel"> {{ channel }}</label></div></fieldset>
                    <label class="text-sm font-medium sm:col-span-2">How they found you<select v-model="profile.referral_source" class="gh-input mt-1"><option :value="null">Not recorded</option><option v-if="profile.referral_source && !['walk_in','friend_or_family','google','instagram','facebook','other'].includes(profile.referral_source)" :value="profile.referral_source">{{ profile.referral_source }}</option><option value="walk_in">Walk-in</option><option value="friend_or_family">Friend or family</option><option value="google">Google</option><option value="instagram">Instagram</option><option value="facebook">Facebook</option><option value="other">Other</option></select></label>
                    <label class="text-sm font-medium sm:col-span-2">Why are you updating this profile?<select v-model="profile.reason" class="gh-input mt-1" required><option value="" disabled>Choose a reason</option><option value="Client asked us to correct their details.">Client requested a correction</option><option value="Updated during an appointment or visit.">Updated during a visit</option><option value="Administrative data quality correction.">Administrative correction</option></select></label>
                    <div class="sm:col-span-2 flex justify-end"><AppButton type="submit" :disabled="profile.processing">Save profile</AppButton></div>
                </form>
                <p v-else class="text-sm text-[var(--text-muted)]">Your role can read this profile but cannot change identity details.</p>
            </SurfaceCard>

            <SurfaceCard title="Safety notes" description="Allergies, formulas, patch tests, treatment context, preferences, and important warnings retain their author.">
                <StatePanel v-if="notes.length === 0" title="No visible notes" description="Sensitive entries may be hidden by your role." />
                <ul v-else class="space-y-3">
                    <li v-for="item in notes" :key="item.id" :class="['rounded-xl border p-3', item.important ? 'border-[var(--status-warning)] bg-[var(--status-warning-soft)]' : 'border-[var(--border-subtle)]']">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide"><span>{{ humanLabel(item.kind) }}</span><span v-if="item.visibility === 'sensitive'">Sensitive</span></div>
                        <p class="mt-2 whitespace-pre-wrap text-sm">{{ item.content }}</p><p class="mt-2 text-xs text-[var(--text-muted)]">{{ item.author }} · {{ new Date(item.created_at).toLocaleString() }}</p>
                    </li>
                </ul>
                <form v-if="permissions.addNote" class="mt-4 grid gap-3" @submit.prevent="addNote">
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-medium">Note type<select v-model="note.kind" class="ds-input mt-1 w-full"><option v-for="kind in ['general','allergy','sensitivity','formula','hair','skin','treatment','patch_test','preference','warning']" :key="kind" :value="kind">{{ humanLabel(kind) }}</option></select></label><label class="text-sm font-medium">Visibility<select v-model="note.visibility" class="ds-input mt-1 w-full"><option value="standard">Standard</option><option v-if="permissions.sensitive" value="sensitive">Sensitive</option></select></label></div>
                    <label class="text-sm font-medium">Note content<textarea v-model="note.content" class="ds-input mt-1 min-h-28 w-full" placeholder="Add client context" required /></label>
                    <label class="flex min-h-11 items-center gap-2"><input v-model="note.important" type="checkbox"> Mark as important</label><AppButton type="submit">Add note</AppButton>
                </form>
            </SurfaceCard>
        </div>

        <SurfaceCard v-show="activeTab === 'visits'" class="mt-6" title="Visit history" description="Past services and the staff who performed them remain available for reliable future context.">
            <StatePanel v-if="appointments.length === 0" title="No linked appointments" description="New bookings link here automatically." />
            <ul v-else class="divide-y divide-[var(--border-subtle)]"><li v-for="visit in appointments" :key="visit.public_id" class="py-4"><div class="flex flex-wrap justify-between gap-2"><p class="font-semibold">{{ visit.reference }} · {{ visit.status.replaceAll('_', ' ') }}</p><time class="text-sm text-[var(--text-muted)]">{{ new Date(visit.starts_at).toLocaleString() }}</time></div><p v-for="service in visit.services" :key="service.name" class="mt-1 text-sm">{{ service.name }}<span v-if="service.performers.length" class="text-[var(--text-muted)]"> · {{ service.performers.join(', ') }}</span></p></li></ul>
        </SurfaceCard>

        <SurfaceCard v-if="permissions.forms" v-show="activeTab === 'forms'" class="mt-6" title="Form template builder" description="When wording changes, Good Hours publishes a new version while completed forms keep exactly what the client saw.">
            <form class="grid gap-4" @submit.prevent="publishTemplate">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium">Start or revise<select v-model="formBuilder.template" class="ds-input mt-1 w-full" @change="loadTemplate"><option value="">New template</option><option v-for="template in formTemplates" :key="template.public_id" :value="template.public_id">{{ template.name }} · v{{ template.current_version }}</option></select></label>
                    <label class="text-sm font-medium">Internal name<input v-model="formBuilder.name" class="ds-input mt-1 w-full" required></label>
                    <label class="text-sm font-medium">Purpose<select v-model="formBuilder.purpose" class="gh-input mt-1" required><option value="consultation">Consultation</option><option value="treatment">Treatment</option><option value="allergy">Allergy</option><option value="consent">Consent</option><option value="intake">Client intake</option><option value="other">Other</option></select></label>
                    <label class="text-sm font-medium">Client-facing title<input v-model="formBuilder.title" class="ds-input mt-1 w-full" required></label>
                    <label class="text-sm font-medium sm:col-span-2">Introduction<textarea v-model="formBuilder.introduction" class="ds-input mt-1 min-h-24 w-full" /></label>
                    <label class="text-sm font-medium sm:col-span-2">Associated services<select v-model="formBuilder.services" class="ds-input mt-1 min-h-24 w-full" multiple><option v-for="service in serviceOptions" :key="service.public_id" :value="service.public_id">{{ service.name }}</option></select></label>
                </div>
                <fieldset><legend class="font-semibold">Fields</legend><div class="mt-3 space-y-3">
                    <div v-for="(field, index) in formBuilder.fields" :key="index" class="grid gap-3 rounded-xl border border-[var(--border-subtle)] p-3 sm:grid-cols-2">
                        <label class="text-sm font-medium">Question or wording<input v-model="field.label" class="ds-input mt-1 w-full" required></label>
                        <label class="text-sm font-medium">Answer type<select v-model="field.type" class="ds-input mt-1 w-full"><option v-for="type in ['text','number','date','yes_no','multiple_choice','signature']" :key="type" :value="type">{{ humanLabel(type) }}</option></select></label>
                        <details class="text-sm"><summary class="min-h-11 cursor-pointer py-3 font-medium text-[var(--text-muted)]">Advanced field settings</summary><label class="mt-1 block text-sm font-medium">Field reference <span class="font-normal text-[var(--text-muted)]">(optional)</span><input v-model="field.id" pattern="[a-z0-9_-]+" class="gh-input mt-1" placeholder="Generated automatically"></label></details>
                        <label v-if="field.type === 'multiple_choice'" class="text-sm font-medium">Choices <span class="font-normal text-[var(--text-muted)]">(one per line)</span><textarea v-model="field.options_text" class="ds-input mt-1 min-h-24 w-full" required /></label>
                        <label class="flex min-h-11 items-center gap-2 text-sm font-medium"><input v-model="field.required" type="checkbox"> Required answer</label>
                        <button v-if="formBuilder.fields.length > 1" type="button" class="min-h-11 justify-self-start font-semibold text-[var(--status-danger)]" @click="formBuilder.fields.splice(index, 1)">Remove field</button>
                    </div>
                </div></fieldset>
                <div class="flex flex-wrap gap-3"><AppButton type="button" variant="secondary" @click="formBuilder.fields.push(newBuilderField())">Add field</AppButton><AppButton type="submit" :disabled="formBuilder.processing">Publish new version</AppButton></div>
            </form>
        </SurfaceCard>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <SurfaceCard v-show="activeTab === 'forms'" title="Forms and consent" description="Review requested and completed forms, or send a secure form link for this client.">
                <ul v-if="forms.length" class="mb-4 space-y-2"><li v-for="item in forms" :key="item.public_id" class="rounded-lg bg-[var(--surface-subtle)] p-3 text-sm"><strong>{{ item.title }} v{{ item.version }}</strong> · {{ item.status }}</li></ul>
                <form v-if="permissions.forms && formTemplates.length" class="grid gap-3" @submit.prevent="requestForm"><label class="text-sm font-medium">Form template<select v-model="formRequest.template" class="ds-input mt-1 w-full"><option v-for="template in formTemplates" :key="template.public_id" :value="template.public_id">{{ template.name }} · v{{ template.current_version }}</option></select></label><label class="text-sm font-medium">Originating appointment<select v-model="formRequest.appointment" class="ds-input mt-1 w-full"><option value="">No appointment link</option><option v-for="visit in appointments" :key="visit.public_id" :value="visit.public_id">{{ visit.reference }}</option></select></label><AppButton type="submit">Create secure form request</AppButton></form>
                <div v-if="consents.length" class="mt-5"><h3 class="font-semibold">Consent history</h3><ul class="mt-2 space-y-2 text-sm"><li v-for="(consent, index) in consents" :key="index">{{ humanLabel(consent.type) }} · {{ humanLabel(consent.status) }} · {{ new Date(consent.occurred_at).toLocaleString() }}</li></ul></div>
            </SurfaceCard>

            <SurfaceCard v-show="activeTab === 'files'" title="Private files" description="Store visit photos and documents privately. Download links expire automatically.">
                <ul v-if="attachments.length" class="mb-4 space-y-2"><li v-for="item in attachments" :key="item.public_id" class="flex items-center justify-between gap-3 rounded-lg bg-[var(--surface-subtle)] p-3 text-sm"><span class="truncate">{{ item.kind }} · {{ item.original_name }}</span><button type="button" class="min-h-11 font-semibold text-[var(--action-primary)]" @click="issueAttachment(item)">Get link</button></li></ul>
                <form v-if="permissions.attachments" class="grid gap-3" @submit.prevent="upload"><label class="text-sm font-medium">Choose attachment<input class="mt-1 block min-h-11 w-full" type="file" accept="image/jpeg,image/png,application/pdf" required @change="attachment.attachment = $event.target.files[0]"></label><div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-medium">Attachment type<select v-model="attachment.kind" class="ds-input mt-1 w-full"><option value="file">File</option><option value="before">Before photo</option><option value="after">After photo</option><option value="profile_photo">Profile photo</option></select></label><label class="text-sm font-medium">Attachment visibility<select v-model="attachment.visibility" class="ds-input mt-1 w-full"><option value="standard">Standard</option><option v-if="permissions.sensitive" value="sensitive">Sensitive</option></select></label></div><AppButton type="submit">Store private attachment</AppButton></form>
            </SurfaceCard>
        </div>

        <div v-if="permissions.merge && duplicates.length" v-show="activeTab === 'overview'" class="mt-6">
            <SurfaceCard title="Possible duplicates" description="Compare the evidence before choosing which profile to keep. Good Hours never merges a possible match automatically.">
                <ul class="space-y-3"><li v-for="candidate in duplicates" :key="candidate.id" class="rounded-xl border border-[var(--border-subtle)] p-4"><p class="font-semibold">{{ candidate.other.name }} · {{ candidate.confidence }}% candidate</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ candidate.reasons.join(', ').replaceAll('_', ' ') }}</p><AppButton class="mt-3" variant="secondary" @click="previewMerge(candidate)">Preview merge</AppButton></li></ul>
                <div v-if="mergePreview" class="mt-4 rounded-xl border border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-4"><h3 class="font-semibold">Merge preview</h3><ul class="mt-2 text-sm"><li v-for="(count, relation) in mergePreview.evidence.relationship_counts" :key="relation">{{ relation.replaceAll('_', ' ') }}: {{ count }}</li></ul><label class="mt-3 block text-sm font-medium">Required merge reason<input v-model="mergeReason" class="ds-input mt-1 w-full"></label><AppButton class="mt-3" :disabled="!mergeReason.trim()" @click="confirmMerge">Confirm survivor and merge</AppButton></div>
            </SurfaceCard>
        </div>

        <SurfaceCard v-if="permissions.privacy" v-show="activeTab === 'privacy'" class="mt-6" title="Privacy requests" description="Record and track exports, corrections, consent withdrawals, and deletion or anonymisation reviews.">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submitPrivacy">
                <label class="text-sm font-medium">Request type<select v-model="privacy.type" class="ds-input mt-1 w-full"><option value="export">Data export</option><option value="correction">Correction</option><option value="consent_withdrawal">Consent withdrawal</option><option value="deletion_anonymization">Deletion / anonymisation review</option></select></label>
                <label v-if="privacy.type === 'consent_withdrawal'" class="text-sm font-medium">Consent to withdraw<select v-model="privacy.details.consent_type" class="ds-input mt-1 w-full" required><option value="marketing">Marketing</option><option value="photography">Photography</option><option value="treatment">Treatment</option></select></label>
                <template v-if="privacy.type === 'correction'">
                    <label class="text-sm font-medium">Corrected name<input v-model="privacy.details.changes.name" class="ds-input mt-1 w-full"></label>
                    <label class="text-sm font-medium">Corrected email<input v-model="privacy.details.changes.email" type="email" class="ds-input mt-1 w-full"></label>
                    <label class="text-sm font-medium">Corrected mobile<input v-model="privacy.details.changes.mobile" class="ds-input mt-1 w-full"></label>
                </template>
                <label v-if="privacy.type === 'deletion_anonymization'" class="text-sm font-medium sm:col-span-2">Request context<textarea v-model="privacy.details.reason" class="ds-input mt-1 min-h-24 w-full" required /></label>
                <AppButton class="sm:col-span-2" type="submit">Log request</AppButton>
            </form>
            <ul v-if="privacyRequests.length" class="mt-4 divide-y divide-[var(--border-subtle)]"><li v-for="item in privacyRequests" :key="item.public_id" class="flex flex-wrap items-center justify-between gap-3 py-3"><span class="text-sm"><strong>{{ item.type.replaceAll('_', ' ') }}</strong> · {{ item.status.replaceAll('_', ' ') }}</span><AppButton v-if="item.status === 'submitted'" variant="secondary" @click="processPrivacy(item)">Process</AppButton></li></ul>
        </SurfaceCard>
    </AppLayout>
</template>
