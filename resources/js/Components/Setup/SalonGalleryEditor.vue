<script setup>
import { computed, inject, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { ArrowLeftIcon, ArrowRightIcon, PhotoIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';

const route = inject('route');
const props = defineProps({ businessId: String, active: Boolean, disabled: Boolean });
const emit = defineEmits(['dirty', 'busy']);
const photos = ref([]), revision = ref(''), limit = ref(8), loaded = ref(false), busy = ref(false);
const selection = ref([]), fileInput = ref(null), dragging = ref(false);
const uploadProgress = ref(null), editing = ref(null), failedImages = ref([]);
const spaces = computed(() => Math.max(0, limit.value - photos.value.length - selection.value.length));
const uploadLabel = computed(() => `${selection.value.some(item => item.attempted) ? 'Continue adding' : 'Add'} ${selection.value.length === 1 ? 'photo' : `${selection.value.length} photos`}`);
const canAdd = computed(() => !unavailable.value && !errors.value.revision && selection.value.length > 0 && !selection.value[0].rejected && (selection.value[0].attempted || photos.value.length + selection.value.length <= limit.value));
const rejectedPhoto = computed(() => selection.value.find(item => item.rejected));
const drafts = reactive({});
const message = ref(''), messageTone = ref('info'), messageTitle = ref('Gallery updated'), errors = ref({}), cooldownUntil = ref(0), now = ref(Date.now());
const blocked = ref(false), supportReference = ref(''), supportEmail = ref('support@clipperdesk.com');
const supportUrl = computed(() => `mailto:${supportEmail.value}?subject=${encodeURIComponent(`Salon photos${supportReference.value ? ` · ${supportReference.value}` : ''}`)}`);
const removal = ref(null), removeDialog = ref(null);
const remaining = computed(() => Math.max(0, Math.ceil((cooldownUntil.value - now.value) / 1000)));
const unavailable = computed(() => busy.value || blocked.value || props.disabled || remaining.value > 0 || !loaded.value);
const dirty = computed(() => selection.value.length > 0 || photos.value.some(photo => drafts[photo.public_id] !== (photo.caption || '')));
watch(dirty, value => emit('dirty', value), { immediate: true });
watch(busy, value => emit('busy', value), { immediate: true });
let timer, disposed = false;
const endpoint = (name, id) => route(`business.configuration.photos.${name}`, id ? [props.businessId, id] : props.businessId);
const receive = data => {
    const previous = new Map(photos.value.map(photo => [photo.public_id, photo.caption || '']));
    photos.value = data.photos; revision.value = data.revision; limit.value = data.limit; loaded.value = true;
    for (const photo of photos.value) {
        if (drafts[photo.public_id] === undefined || drafts[photo.public_id] === previous.get(photo.public_id)) drafts[photo.public_id] = photo.caption || '';
    }
};
const failure = (requestError, operation = 'save') => {
    errors.value = requestError.response?.data?.errors || {};
    messageTone.value = 'error';
    const status = requestError.response?.status;
    if (requestError.response?.data?.code === 'GALLERY_UNAVAILABLE' || status >= 500) {
        blocked.value = true;
        messageTitle.value = 'Salon photos are temporarily unavailable';
        message.value = operation === 'load' ? 'Your photo gallery couldn’t open. Your other setup details are saved. You can continue setup and add photos later.' : 'This photo change could not be confirmed. Your remaining photos and descriptions are kept. Contact support before trying again.';
        const reference = requestError.response?.data?.support_reference || requestError.response?.headers?.['x-correlation-id'];
        supportReference.value = typeof reference === 'string' && /^[a-zA-Z0-9-]{8,80}$/.test(reference) ? reference : '';
        const email = requestError.response?.data?.support_email;
        if (typeof email === 'string' && /^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(email)) supportEmail.value = email;
    } else if ([401, 403, 404, 410, 419].includes(status)) {
        blocked.value = true;
        messageTitle.value = 'Salon photos are unavailable';
        message.value = [401, 419].includes(status) ? 'Your session ended. Sign in again to manage your photos. Any selected photos and descriptions remain here until you leave this page.' : 'Your access to these photos changed. Contact your business owner or ClipperDesk support for help.';
    } else if (status === 429) {
        messageTitle.value = 'Please wait a moment';
        const wait = Number(requestError.response.headers?.['retry-after']);
        cooldownUntil.value = Date.now() + (Number.isFinite(wait) && wait > 0 ? wait : 30) * 1000;
        now.value = Date.now(); clearInterval(timer); timer = setInterval(() => { now.value = Date.now(); if (!remaining.value) clearInterval(timer); }, 1000);
        message.value = 'Please wait before changing your photos again. Your remaining photos and descriptions are kept.';
    } else if (errors.value.revision) {
        messageTitle.value = 'Refresh your photo gallery';
        message.value = errors.value.revision[0];
    } else if (status === 422) {
        messageTitle.value = 'Check this photo';
        message.value = errors.value.photo?.[0] || errors.value.caption?.[0] || 'Check the photo details and try saving again.';
    } else {
        messageTitle.value = operation === 'load' ? 'Photos couldn’t load' : 'Photo change couldn’t be confirmed';
        message.value = operation === 'load' ? 'We couldn’t connect to your photo gallery. Check your connection, then load your photos again.' : 'We couldn’t confirm this photo change. Your remaining photos and descriptions are kept. Continue adding to check and finish.';
    }
};
const load = async () => {
    if (busy.value || blocked.value || remaining.value) return;
    busy.value = true; message.value = ''; errors.value = {};
    try { receive((await axios.get(endpoint('index'))).data); }
    catch (requestError) { failure(requestError, 'load'); }
    finally { busy.value = false; }
};
watch(() => props.active, active => { if (active && !loaded.value) load(); }, { immediate: true });
const selectFiles = files => {
    dragging.value = false;
    if (unavailable.value) return;
    const chosen = Array.from(files || []);
    if (!chosen.length) return;
    const issues = [];
    for (const candidate of chosen) {
        const fingerprint = [candidate.name, candidate.size, candidate.type, candidate.lastModified].join(':');
        if (selection.value.some(item => item.fingerprint === fingerprint)) {
            issues.push(`${candidate.name}: already selected.`);
            continue;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(candidate.type) || !candidate.size || candidate.size > 5 * 1024 * 1024) {
            issues.push(`${candidate.name}: choose a PNG, JPEG or WebP up to 5 MB.`);
            continue;
        }
        if (!spaces.value) {
            issues.push(`${candidate.name}: your gallery holds up to ${limit.value} photos.`);
            continue;
        }
        selection.value.push({ key: crypto.randomUUID(), file: candidate, fingerprint, preview: URL.createObjectURL(candidate), caption: '', expanded: false, attempted: false, rejected: false, requestCaption: null });
    }
    if (errors.value.revision || rejectedPhoto.value) return;
    errors.value = {};
    message.value = issues.length ? `${issues.slice(0, 3).join(' ')}${issues.length > 3 ? ` ${issues.length - 3} more files were not selected.` : ''} Review the selected photos below.` : '';
    if (issues.length) { messageTone.value = 'warning'; messageTitle.value = 'Some photos weren’t selected'; }
};
const choose = event => {
    selectFiles(event.target.files);
    event.target.value = '';
};
const removeSelected = item => {
    if (busy.value) return;
    URL.revokeObjectURL(item.preview);
    selection.value = selection.value.filter(candidate => candidate.key !== item.key);
    // Retain a revision/support notice until its actual recovery action succeeds.
    if (!blocked.value && !errors.value.revision && !rejectedPhoto.value && !remaining.value) { errors.value = {}; message.value = ''; }
};
const add = async () => {
    if (!canAdd.value) return;
    busy.value = true; message.value = ''; errors.value = {};
    uploadProgress.value = { completed: 0, total: selection.value.length };
    try {
        while (!disposed && selection.value.length) {
            const item = selection.value[0];
            // A response may have been lost after saving: replay its exact file/caption/key.
            if (props.disabled) {
                messageTone.value = 'info'; messageTitle.value = 'Image uploads are unavailable for this account';
                message.value = 'Your saved photos and remaining selection are kept. Review your subscription or contact support to continue.';
                break;
            }
            if (!item.attempted && photos.value.length >= limit.value) {
                messageTone.value = 'info'; messageTitle.value = 'Your gallery is full';
                message.value = `Your gallery holds up to ${limit.value} photos. Remove a saved photo before adding the remaining selection.`;
                break;
            }
            if (!item.attempted) { item.requestCaption = item.caption; item.attempted = true; }
            const data = new FormData(); data.append('photo', item.file); data.append('caption', item.requestCaption); data.append('revision', revision.value); data.append('idempotency_key', item.key);
            try {
                const response = await axios.post(endpoint('store'), data);
                if (disposed) break;
                receive(response.data);
                URL.revokeObjectURL(item.preview);
                selection.value = selection.value.filter(candidate => candidate.key !== item.key);
                uploadProgress.value.completed++;
            } catch (requestError) {
                if (disposed) break;
                failure(requestError);
                item.rejected = requestError.response?.status === 422 && !errors.value.revision;
                message.value = `Adding “${item.file.name}” stopped. ${message.value}`;
                if (uploadProgress.value.completed) message.value = `${uploadProgress.value.completed} ${uploadProgress.value.completed === 1 ? 'photo added' : 'photos added'}. ${message.value}`;
                // No later file is sent after an uncertain result, stale gallery or cooldown.
                break;
            }
        }
        if (!selection.value.length) {
            messageTone.value = 'success'; messageTitle.value = `${uploadProgress.value.completed === 1 ? 'Photo' : `${uploadProgress.value.completed} photos`} added`;
            message.value = 'You can change their order or add descriptions in your gallery.';
        }
    } finally { busy.value = false; uploadProgress.value = null; }
};
const save = async (photo, position, saveDescription = true) => {
    if (unavailable.value) return;
    busy.value = true; message.value = ''; errors.value = {};
    try {
        receive((await axios.put(endpoint('update', photo.public_id), { caption: saveDescription ? drafts[photo.public_id] : photo.caption, position, revision: revision.value })).data);
        messageTone.value = 'success'; messageTitle.value = 'Gallery updated'; message.value = 'Your photo gallery is updated.';
    } catch (requestError) { failure(requestError); }
    finally { busy.value = false; }
};
const askRemove = async photo => { removal.value = photo; await nextTick(); removeDialog.value.open(); };
const remove = async () => {
    if (unavailable.value || !removal.value) return;
    busy.value = true; message.value = ''; errors.value = {};
    try {
        receive((await axios.delete(endpoint('destroy', removal.value.public_id), { data: { revision: revision.value } })).data);
        messageTone.value = 'success'; messageTitle.value = 'Photo removed'; message.value = 'Photo removed from your booking page.';
    } catch (requestError) { failure(requestError); }
    finally { busy.value = false; removal.value = null; removeDialog.value.close(); }
};
onBeforeUnmount(() => { disposed = true; clearInterval(timer); selection.value.forEach(item => URL.revokeObjectURL(item.preview)); emit('dirty', false); emit('busy', false); });
</script>

<template>
    <section class="salon-gallery-editor" aria-labelledby="salon-gallery-heading">
        <header><h3 id="salon-gallery-heading" class="font-semibold text-[var(--text-strong)]">Salon photos <span v-if="loaded" class="gallery-count">{{ photos.length }} / {{ limit }}</span></h3><p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">Show your space and your work. The first photo is featured on your booking page.</p></header>
        <AppNotice v-if="message" :tone="messageTone" :title="messageTitle">
            <p>{{ message }}</p>
            <p v-if="supportReference" class="mt-2 text-xs">Support reference: {{ supportReference }}</p>
            <template v-if="blocked || !loaded || errors.revision || rejectedPhoto" #actions><a v-if="blocked" :href="supportUrl" class="gallery-support">Contact ClipperDesk support</a><AppButton v-else-if="rejectedPhoto && !errors.revision" variant="secondary" :disabled="busy" @click="removeSelected(rejectedPhoto)">Remove this photo</AppButton><AppButton v-else variant="secondary" :disabled="busy || remaining > 0" @click="load">{{ remaining ? `Wait ${remaining}s` : errors.revision ? 'Reload photos' : 'Load photos' }}</AppButton></template>
        </AppNotice>
        <p v-if="busy && !loaded" role="status" class="text-sm text-[var(--text-muted)]">Loading your photos…</p>
        <p v-else-if="loaded && !photos.length" class="gallery-empty"><PhotoIcon class="size-6" aria-hidden="true" /><span>Photos are optional. Clients can book without them.</span></p>
        <ol v-if="photos.length" class="salon-gallery-grid">
            <li v-for="(photo, index) in photos" :key="photo.public_id" class="salon-gallery-item">
                <div class="gallery-photo-row">
                    <img v-if="!failedImages.includes(photo.url)" :src="photo.url" :alt="photo.caption || `Salon photo ${index + 1}`" loading="lazy" @error="failedImages.push(photo.url)" />
                    <span v-else class="gallery-image-unavailable"><PhotoIcon class="size-6" aria-hidden="true" /><span class="ds-sr-only">Photo preview unavailable</span></span>
                    <div class="gallery-photo-copy"><strong>{{ index === 0 ? 'Featured photo' : `Photo ${index + 1}` }}</strong><p>{{ photo.caption || 'No description' }}</p><small v-if="drafts[photo.public_id] !== (photo.caption || '')">Unsaved description</small></div>
                    <AppButton variant="quiet" :disabled="busy" :aria-expanded="editing === photo.public_id" :aria-controls="`gallery-edit-${photo.public_id}`" :aria-label="`Edit photo ${index + 1}`" @click="editing = editing === photo.public_id ? null : photo.public_id">{{ editing === photo.public_id ? 'Close' : 'Edit photo' }}</AppButton>
                </div>
                <div v-if="editing === photo.public_id" :id="`gallery-edit-${photo.public_id}`" class="gallery-edit-panel">
                    <label :for="`gallery-caption-${photo.public_id}`" class="text-sm font-medium">Description (optional)</label>
                    <input :id="`gallery-caption-${photo.public_id}`" v-model="drafts[photo.public_id]" maxlength="180" class="cd-input mt-2 w-full" :disabled="unavailable" />
                    <div class="gallery-actions">
                        <AppButton variant="secondary" :disabled="unavailable || drafts[photo.public_id] === (photo.caption || '')" @click="save(photo, index)">Save description</AppButton>
                        <AppButton variant="quiet" :disabled="unavailable || index === 0" :aria-label="`Move photo ${index + 1} earlier`" @click="save(photo, index - 1, false)"><ArrowLeftIcon class="size-4" aria-hidden="true" />Move earlier</AppButton>
                        <AppButton variant="quiet" :disabled="unavailable || index === photos.length - 1" :aria-label="`Move photo ${index + 1} later`" @click="save(photo, index + 1, false)">Move later<ArrowRightIcon class="size-4" aria-hidden="true" /></AppButton>
                        <AppButton variant="quiet" :disabled="unavailable" @click="askRemove(photo)">Remove photo</AppButton>
                    </div>
                </div>
            </li>
        </ol>
        <form v-if="loaded && (photos.length < limit || selection.length)" class="gallery-upload" @submit.prevent="add">
            <div v-if="spaces > 0" class="gallery-dropzone" :class="{ 'is-dragging': dragging, disabled: unavailable }" @dragover.prevent="dragging = !unavailable" @dragleave.self="dragging = false" @drop.prevent="selectFiles($event.dataTransfer?.files)">
                <PhotoIcon class="size-7 text-[var(--text-muted)]" aria-hidden="true" />
                <div class="gallery-drop-copy"><strong>Bring your salon to life</strong><p>Select several photos at once<span class="gallery-drop-desktop">, or drop them here</span>.</p></div>
                <label for="salon-gallery-file" class="gallery-choose" :class="{ disabled: unavailable }">{{ selection.length ? 'Choose more photos' : 'Choose photos' }}<input id="salon-gallery-file" ref="fileInput" type="file" multiple accept="image/png,image/jpeg,image/webp" class="ds-sr-only" :disabled="unavailable" aria-describedby="gallery-upload-hint" @change="choose" /></label>
            </div>
            <p id="gallery-upload-hint" class="text-xs leading-5 text-[var(--text-muted)]">PNG, JPEG or WebP · up to 5 MB each · {{ spaces }} {{ spaces === 1 ? 'space' : 'spaces' }} available.</p>
            <template v-if="selection.length">
                <div class="gallery-selection-heading"><strong>{{ selection.length }} {{ selection.length === 1 ? 'photo selected' : 'photos selected' }}</strong><span v-if="!selection.some(item => item.attempted)">Not added yet</span></div>
                <ol class="gallery-selection-grid" aria-label="Selected photos">
                    <li v-for="(item, index) in selection" :key="item.key" class="gallery-selected">
                        <div class="gallery-selected-image"><img :src="item.preview" :alt="`Preview of ${item.file.name}`" /><AppButton variant="secondary" :disabled="busy" class="gallery-remove-selection" :aria-label="`Remove ${item.file.name} from selection`" @click="removeSelected(item)"><XMarkIcon class="size-4" aria-hidden="true" /></AppButton><span v-if="!photos.length && index === 0" class="gallery-featured-label">Featured photo</span></div>
                        <div class="gallery-selected-copy"><p :title="item.file.name">{{ item.file.name }}</p><AppButton variant="quiet" class="gallery-description-toggle" :disabled="busy" :aria-expanded="item.expanded" :aria-controls="`selected-caption-${item.key}`" @click="item.expanded = !item.expanded">{{ item.caption ? 'Edit description' : 'Add description' }}</AppButton><div v-if="item.expanded" :id="`selected-caption-${item.key}`"><label :for="`gallery-new-caption-${item.key}`" class="ds-sr-only">Description for {{ item.file.name }} (optional)</label><input :id="`gallery-new-caption-${item.key}`" v-model="item.caption" maxlength="180" class="cd-input w-full" :disabled="unavailable || item.attempted" placeholder="Describe this photo (optional)" /><p v-if="item.attempted" class="gallery-caption-help">You can edit this description after the photo is added.</p></div></div>
                    </li>
                </ol>
                <div v-if="uploadProgress" class="gallery-progress" role="status" aria-live="polite"><p>Adding photo {{ Math.min(uploadProgress.completed + 1, uploadProgress.total) }} of {{ uploadProgress.total }}…</p><progress :value="uploadProgress.completed" :max="uploadProgress.total" :aria-label="`${uploadProgress.completed} of ${uploadProgress.total} photos added`" /></div>
                <div class="gallery-actions gallery-upload-actions"><AppButton type="submit" :disabled="!canAdd" :loading="busy">{{ remaining ? `Wait ${remaining}s` : uploadProgress ? 'Adding photos…' : uploadLabel }}</AppButton><p v-if="!busy" class="text-xs text-[var(--text-muted)]">Use photos you have permission to share.</p></div>
            </template>
        </form>
        <p v-else-if="loaded && photos.length >= limit" class="text-sm text-[var(--text-muted)]">All {{ limit }} photo spaces are filled. Remove a photo to add another.</p>
        <AppDialog ref="removeDialog" title="Remove this photo?" description="It will disappear from your booking page. You can upload it again later." confirm-label="Remove photo" :close-on-confirm="false" :confirm-disabled="busy" :can-close="() => !busy" @confirm="remove"><img v-if="removal" :src="removal.url" :alt="removal.caption || 'Photo to remove'" class="max-h-48 w-full rounded-lg object-contain" /></AppDialog>
    </section>
</template>

<style scoped>
.salon-gallery-editor { display:grid; gap:18px; border-top:1px solid var(--border-subtle); margin-top:24px; padding-top:24px; }
.gallery-count { margin-left:8px; font-weight:400; font-size:12px; color:var(--text-muted); }
.salon-gallery-grid { display:grid; gap:0; list-style:none; padding:0; border-block:1px solid var(--border-subtle); }
.salon-gallery-item { min-width:0; border-bottom:1px solid var(--border-subtle); }.salon-gallery-item:last-child { border-bottom:0; }
.gallery-photo-row { display:flex; align-items:center; gap:14px; padding-block:14px; min-width:0; }
.gallery-photo-row>img,.gallery-image-unavailable { width:88px; height:68px; flex-shrink:0; object-fit:cover; border-radius:8px; background:var(--surface-subtle); }
.gallery-image-unavailable { display:grid; place-items:center; color:var(--text-muted); }
.gallery-photo-copy { min-width:0; flex:1; }.gallery-photo-copy strong { font-size:13px; color:var(--text-strong); }.gallery-photo-copy p { margin-top:4px; font-size:12px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.gallery-photo-copy small { display:block; margin-top:4px; color:var(--text-muted); font-size:11px; }
.gallery-edit-panel { padding:4px 0 18px; }
.gallery-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; align-items:center; }
.gallery-support { display:inline-flex; align-items:center; min-height:44px; font-size:13px; font-weight:600; color:var(--action-primary); text-decoration:underline; }.gallery-support:focus-visible { outline:3px solid var(--focus-ring); outline-offset:3px; }
.gallery-upload { display:grid; gap:10px; min-width:0; }
.gallery-dropzone { display:flex; align-items:center; gap:16px; padding:24px; border:1px dashed var(--border-default); border-radius:12px; background:var(--surface-raised); transition:border-color .15s,background .15s; }
.gallery-dropzone.is-dragging { background:var(--surface-subtle); border-color:var(--action-primary); }.gallery-dropzone.disabled { opacity:.55; }
.gallery-drop-copy { flex:1; min-width:0; }.gallery-drop-copy strong { font-size:14px; color:var(--text-strong); }.gallery-drop-copy p { margin-top:4px; font-size:13px; color:var(--text-muted); line-height:1.6; }
.gallery-choose { display:inline-flex; position:relative; flex-shrink:0; align-items:center; justify-content:center; min-height:44px; padding:10px 16px; border:1px solid var(--border-default); border-radius:8px; font-size:13px; font-weight:600; color:var(--text-strong); background:var(--surface-raised); cursor:pointer; }.gallery-choose:focus-within { outline:3px solid var(--focus-ring); outline-offset:3px; }.gallery-choose.disabled { cursor:not-allowed; }
.gallery-selection-heading { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-top:12px; font-size:13px; }.gallery-selection-heading span { color:var(--text-muted); font-size:12px; }
.gallery-selection-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; padding:0; list-style:none; }
.gallery-selected { min-width:0; overflow:hidden; border:1px solid var(--border-subtle); border-radius:10px; background:var(--surface-raised); }
.gallery-selected-image { position:relative; aspect-ratio:3/2; background:var(--surface-subtle); }.gallery-selected-image>img { width:100%; height:100%; object-fit:cover; }
.gallery-remove-selection { position:absolute; top:8px; right:8px; width:44px; height:44px; padding:0; border-radius:50%; box-shadow:var(--shadow-sm); }
.gallery-featured-label { position:absolute; left:8px; bottom:8px; padding:4px 8px; border-radius:5px; background:var(--surface-raised); color:var(--text-strong); font-size:11px; font-weight:600; }
.gallery-selected-copy { padding:10px 12px; }.gallery-selected-copy>p { font-size:12px; color:var(--text-muted); overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
.gallery-description-toggle { min-height:44px; padding:0; font-size:12px; }.gallery-caption-help { margin-top:6px; font-size:11px; line-height:1.5; color:var(--text-muted); }
.gallery-upload-actions { margin-top:4px; }.gallery-progress { display:grid; gap:8px; font-size:13px; color:var(--text-muted); margin-top:6px; }.gallery-progress progress { width:100%; height:6px; accent-color:var(--action-primary); }
.gallery-empty { display:flex; align-items:center; gap:12px; background:var(--surface-subtle); padding:18px; border-radius:12px; color:var(--text-muted); font-size:14px; line-height:1.6; }
@media(max-width:760px) { .gallery-selection-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }.gallery-dropzone { flex-wrap:wrap; padding:18px; }.gallery-choose { flex-basis:100%; }.gallery-drop-desktop { display:none; } }
@media(max-width:480px) { .gallery-photo-row { gap:10px; }.gallery-photo-row>img,.gallery-image-unavailable { width:64px; height:56px; }.gallery-photo-row .cd-app-button { padding-inline:8px; }.gallery-selected-copy { padding:8px; }.gallery-remove-selection { top:6px; right:6px; }.gallery-upload-actions>.cd-app-button { width:100%; } }
</style>
