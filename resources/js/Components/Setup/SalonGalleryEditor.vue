<script setup>
import { computed, inject, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { ArrowLeftIcon, ArrowRightIcon, PhotoIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import FieldError from '@/Components/Product/FieldError.vue';

const route = inject('route');
const props = defineProps({ businessId: String, active: Boolean, disabled: Boolean });
const emit = defineEmits(['dirty', 'busy']);
const photos = ref([]), revision = ref(''), limit = ref(8), loaded = ref(false), busy = ref(false);
const file = ref(null), fileInput = ref(null), caption = ref(''), uploadKey = ref('');
const selectedPreview = ref(''), editing = ref(null), failedImages = ref([]);
const drafts = reactive({});
const message = ref(''), messageTone = ref('info'), messageTitle = ref('Gallery updated'), errors = ref({}), cooldownUntil = ref(0), now = ref(Date.now());
const blocked = ref(false), supportReference = ref(''), supportEmail = ref('support@clipperdesk.com');
const supportUrl = computed(() => `mailto:${supportEmail.value}?subject=${encodeURIComponent(`Salon photos${supportReference.value ? ` · ${supportReference.value}` : ''}`)}`);
const removal = ref(null), removeDialog = ref(null);
const remaining = computed(() => Math.max(0, Math.ceil((cooldownUntil.value - now.value) / 1000)));
const unavailable = computed(() => busy.value || blocked.value || props.disabled || remaining.value > 0 || !loaded.value);
const dirty = computed(() => !!file.value || !!caption.value || photos.value.some(photo => drafts[photo.public_id] !== (photo.caption || '')));
watch(dirty, value => emit('dirty', value), { immediate: true });
watch(busy, value => emit('busy', value), { immediate: true });
let timer;
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
        message.value = operation === 'load' ? 'Your photo gallery couldn’t open. Your other setup details are saved. You can continue setup and add photos later.' : 'This photo change could not be confirmed. Your selected image and descriptions are kept. Contact support before trying again.';
        const reference = requestError.response?.data?.support_reference || requestError.response?.headers?.['x-correlation-id'];
        supportReference.value = typeof reference === 'string' && /^[a-zA-Z0-9-]{8,80}$/.test(reference) ? reference : '';
        const email = requestError.response?.data?.support_email;
        if (typeof email === 'string' && /^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(email)) supportEmail.value = email;
    } else if ([401, 403, 404, 410, 419].includes(status)) {
        blocked.value = true;
        messageTitle.value = 'Salon photos are unavailable';
        message.value = [401, 419].includes(status) ? 'Your session ended. Sign in again to manage your photos. Any selected image and descriptions remain here until you leave this page.' : 'Your access to these photos changed. Contact your business owner or ClipperDesk support for help.';
    } else if (status === 429) {
        messageTitle.value = 'Please wait a moment';
        const wait = Number(requestError.response.headers?.['retry-after']);
        cooldownUntil.value = Date.now() + (Number.isFinite(wait) && wait > 0 ? wait : 30) * 1000;
        now.value = Date.now(); clearInterval(timer); timer = setInterval(() => { now.value = Date.now(); if (!remaining.value) clearInterval(timer); }, 1000);
        message.value = 'Please wait before changing your photos again. Your selected photo and captions are kept.';
    } else if (errors.value.revision) {
        messageTitle.value = 'Refresh your photo gallery';
        message.value = errors.value.revision[0];
    } else if (status === 422) {
        messageTitle.value = 'Check your photo';
        message.value = errors.value.photo?.[0] || errors.value.caption?.[0] || 'Check the photo details and try saving again.';
    } else {
        messageTitle.value = operation === 'load' ? 'Photos couldn’t load' : 'Photo change couldn’t be saved';
        message.value = operation === 'load' ? 'We couldn’t connect to your photo gallery. Check your connection, then load your photos again.' : 'Your photos could not be saved right now. Your selected image and captions are kept. Please try again in a moment.';
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
const choose = event => {
    const chosen = event.target.files?.[0];
    if (!chosen) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(chosen.type) || chosen.size > 5 * 1024 * 1024) {
        messageTone.value = 'error'; messageTitle.value = 'Check your photo'; message.value = 'Choose a PNG, JPEG or WebP photo smaller than 5 MB.';
        if (fileInput.value) fileInput.value.value = '';
        return;
    }
    if (selectedPreview.value) URL.revokeObjectURL(selectedPreview.value);
    file.value = chosen; selectedPreview.value = URL.createObjectURL(chosen);
    uploadKey.value = crypto.randomUUID(); errors.value = {}; message.value = '';
};
const discardSelected = () => {
    if (selectedPreview.value) URL.revokeObjectURL(selectedPreview.value);
    selectedPreview.value = ''; file.value = null; caption.value = ''; uploadKey.value = '';
    if (fileInput.value) fileInput.value.value = '';
    if (!blocked.value) { errors.value = {}; message.value = ''; }
};
const add = async () => {
    if (unavailable.value || !file.value || photos.value.length >= limit.value) return;
    const data = new FormData(); data.append('photo', file.value); data.append('caption', caption.value); data.append('revision', revision.value); data.append('idempotency_key', uploadKey.value);
    busy.value = true; message.value = ''; errors.value = {};
    try {
        receive((await axios.post(endpoint('store'), data)).data);
        discardSelected();
        messageTone.value = 'success'; messageTitle.value = 'Photo added'; message.value = 'Photo added to your booking page.';
    } catch (requestError) { failure(requestError); }
    finally { busy.value = false; }
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
onBeforeUnmount(() => { clearInterval(timer); if (selectedPreview.value) URL.revokeObjectURL(selectedPreview.value); emit('dirty', false); emit('busy', false); });
</script>

<template>
    <section class="salon-gallery-editor" aria-labelledby="salon-gallery-heading">
        <header><h3 id="salon-gallery-heading" class="font-semibold text-[var(--text-strong)]">Salon photos <span v-if="loaded" class="gallery-count">{{ photos.length }} / {{ limit }}</span></h3><p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">Show your space and your work. The first photo is featured on your booking page.</p></header>
        <AppNotice v-if="message" :tone="messageTone" :title="messageTitle">
            <p>{{ message }}</p>
            <p v-if="supportReference" class="mt-2 text-xs">Support reference: {{ supportReference }}</p>
            <template v-if="blocked || !loaded || errors.revision" #actions><a v-if="blocked" :href="supportUrl" class="gallery-support">Contact ClipperDesk support</a><AppButton v-else variant="secondary" :disabled="busy || remaining > 0" @click="load">{{ remaining ? `Wait ${remaining}s` : errors.revision ? 'Reload photos' : 'Load photos' }}</AppButton></template>
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
        <form v-if="loaded && photos.length < limit" class="gallery-upload" @submit.prevent="add">
            <div v-if="selectedPreview" class="gallery-selected"><img :src="selectedPreview" alt="Preview of your selected salon photo" /><div class="gallery-selected-copy"><strong>Ready to add</strong><p>{{ file.name }}</p><AppButton variant="quiet" :disabled="busy" @click="discardSelected">Remove selection</AppButton></div></div>
            <label for="salon-gallery-file" class="gallery-choose" :class="{ disabled: unavailable }"><PhotoIcon class="size-5" aria-hidden="true" />{{ file ? 'Choose a different photo' : 'Choose a photo' }}<input id="salon-gallery-file" ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" class="ds-sr-only" :disabled="unavailable" :aria-invalid="!!errors.photo || undefined" aria-describedby="gallery-upload-hint gallery-file-error" @change="choose" /></label>
            <p id="gallery-upload-hint" class="text-xs leading-5 text-[var(--text-muted)]">PNG, JPEG or WebP · up to 5 MB. Use photos you have permission to share.</p>
            <FieldError id="gallery-file-error" :message="errors.photo?.[0]" />
            <label v-if="file" for="salon-gallery-new-caption" class="text-sm font-medium mt-2">Description (optional)<input id="salon-gallery-new-caption" v-model="caption" maxlength="180" class="cd-input mt-2 w-full" :disabled="unavailable" placeholder="For example, our styling area" /></label>
            <div v-if="file" class="gallery-actions"><AppButton type="submit" :disabled="unavailable || !file" :loading="busy">{{ remaining ? `Wait ${remaining}s` : 'Add photo' }}</AppButton></div>
        </form>
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
.gallery-upload { display:grid; gap:8px; max-width:600px; }.gallery-choose { display:inline-flex; position:relative; justify-self:start; align-items:center; gap:8px; min-height:44px; padding:10px 14px; border:1px solid var(--border-default); border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }.gallery-choose:focus-within { outline:3px solid var(--focus-ring); outline-offset:3px; }.gallery-choose.disabled { opacity:.55; cursor:not-allowed; }
.gallery-selected { display:flex; align-items:center; gap:18px; padding:14px; border:1px solid var(--border-subtle); border-radius:10px; margin-bottom:6px; }.gallery-selected>img { width:140px; height:108px; object-fit:contain; flex-shrink:0; border-radius:6px; background:var(--surface-subtle); }.gallery-selected-copy { min-width:0; }.gallery-selected-copy strong { font-size:13px; color:var(--text-strong); }.gallery-selected-copy p { overflow-wrap:anywhere; margin-top:4px; font-size:12px; color:var(--text-muted); }.gallery-selected-copy button { margin-top:8px; }
.gallery-empty { display:flex; align-items:center; gap:12px; background:var(--surface-subtle); padding:18px; border-radius:12px; color:var(--text-muted); font-size:14px; line-height:1.6; }
@media(max-width:480px) { .gallery-photo-row { gap:10px; }.gallery-photo-row>img,.gallery-image-unavailable { width:64px; height:56px; }.gallery-photo-row .cd-app-button { padding-inline:8px; }.gallery-selected { gap:12px; }.gallery-selected>img { width:104px; height:92px; } }
</style>
