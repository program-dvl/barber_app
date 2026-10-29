<script setup>
import { computed, inject, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { PhotoIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';

const route = inject('route');
const props = defineProps({ businessId: String, kind: String, title: String, help: String, savedPath: String, disabled: Boolean });
const emit = defineEmits(['dirty', 'busy']);
const form = useForm({ kind: props.kind, asset: null, existing_image: null });
const input = ref(null), localPreview = ref(''), failedImage = ref(false), failure = ref(''), savedNotice = ref(''), removeDialog = ref(null), refreshing = ref(false), currentPath = ref(props.savedPath);
const imageUrl = computed(() => localPreview.value || (currentPath.value ? `${route('business.configuration.booking-preview.media', [props.businessId, props.kind])}?v=${encodeURIComponent(currentPath.value)}` : ''));
const problem = computed(() => failure.value || Object.values(form.errors).join(' '));
const clear = () => { if (localPreview.value) URL.revokeObjectURL(localPreview.value); localPreview.value = ''; form.reset('asset'); if (input.value) input.value.value = ''; failedImage.value = false; };
watch(() => props.savedPath, value => { currentPath.value = value; failedImage.value = false; });
watch(() => Boolean(form.asset), value => emit('dirty', value));
watch(() => form.processing, value => emit('busy', value));
onBeforeUnmount(() => { if (localPreview.value) URL.revokeObjectURL(localPreview.value); emit('dirty', false); emit('busy', false); });
const choose = event => {
    const file = event.target.files?.[0]; if (!file) return;
    form.clearErrors(); failure.value = ''; savedNotice.value = '';
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { failure.value = 'Choose a PNG, JPEG or WebP image smaller than 5 MB.'; event.target.value = ''; return; }
    clear(); form.asset = file; form.existing_image = currentPath.value || null; localPreview.value = URL.createObjectURL(file);
};
const options = {
    preserveScroll: true,
    onSuccess: () => { clear(); savedNotice.value = `${props.title} saved.`; removeDialog.value?.close(); },
    onNetworkError: () => { failure.value = 'The connection was interrupted. Your chosen image is still here. Check the saved image before trying again.'; return false; },
};
const save = () => { failure.value = ''; savedNotice.value = ''; form.clearErrors(); form.post(route('business.configuration.branding.store', props.businessId), { ...options, forceFormData: true }); };
const refresh = async () => {
    if (refreshing.value || form.processing) return;
    refreshing.value = true;
    try {
        const { data } = await axios.get(route('business.configuration.show', props.businessId), { headers: { 'X-Inertia': 'true', 'X-Inertia-Partial-Component': 'Configuration/Onboarding', 'X-Inertia-Partial-Data': 'business' } });
        currentPath.value = data.props.business[props.kind === 'logo' ? 'logo_path' : 'cover_image_path'] || null;
        form.existing_image = currentPath.value; form.clearErrors(); failure.value = ''; failedImage.value = false;
        savedNotice.value = form.asset ? 'The saved image is refreshed. Your chosen preview is still here; save it when you’re ready.' : 'The saved image is refreshed.';
    } catch { failure.value = 'The saved image could not be refreshed. Your chosen preview is kept. Please try again when the connection is restored.'; }
    finally { refreshing.value = false; }
};
const remove = () => {
    failure.value = ''; savedNotice.value = ''; form.clearErrors(); form.existing_image = currentPath.value || null;
    form.transform(data => ({ existing_image: data.existing_image })).delete(route('business.configuration.branding.destroy', [props.businessId, props.kind]), { ...options,
        onSuccess: () => { clear(); savedNotice.value = `${props.title} removed.`; removeDialog.value?.close(); },
        onFinish: () => form.transform(data => data),
    });
};
</script>

<template>
    <section class="brand-image-editor" :aria-labelledby="`brand-${kind}-heading`">
        <div class="brand-image-preview" :class="{ logo: kind === 'logo', empty: !imageUrl || failedImage }">
            <img v-if="imageUrl && !failedImage" :src="imageUrl" :alt="`${title}${localPreview ? ' preview' : ''}`" @error="failedImage = true" />
            <div v-else><PhotoIcon class="size-7" aria-hidden="true" /><span>{{ currentPath ? 'Image preview unavailable' : `No ${kind === 'logo' ? 'logo' : 'cover photo'} yet` }}</span></div>
        </div>
        <h3 :id="`brand-${kind}-heading`">{{ title }}</h3>
        <p>{{ help }}</p>
        <p v-if="localPreview || currentPath" class="brand-image-note">{{ localPreview ? 'Preview only. Save when you’re happy with it.' : 'Your saved image appears above.' }}</p>
        <div class="brand-image-actions">
            <AppButton variant="secondary" :disabled="disabled || form.processing || refreshing" @click="input?.click()">{{ currentPath ? 'Replace image' : 'Choose image' }}</AppButton>
            <AppButton v-if="form.asset" :disabled="disabled || form.processing || refreshing" @click="save">{{ form.processing ? 'Saving…' : 'Save image' }}</AppButton>
            <AppButton v-if="form.asset" variant="quiet" :disabled="form.processing" @click="clear">Discard</AppButton>
            <AppButton v-else-if="currentPath" variant="quiet" :disabled="disabled || form.processing || refreshing" @click="removeDialog.open()">Remove image</AppButton>
        </div>
        <input :id="`brand-${kind}-file`" ref="input" type="file" accept="image/png,image/jpeg,image/webp" class="ds-sr-only" :aria-label="`Choose ${title.toLowerCase()}`" :disabled="disabled || form.processing" @change="choose" />
        <AppNotice v-if="problem" tone="warning" title="Your image has not changed" :description="problem" urgent><template v-if="form.errors.existing_image || failure" #actions><AppButton variant="secondary" :disabled="refreshing || form.processing" @click="refresh">{{ refreshing ? 'Refreshing…' : 'Refresh saved image' }}</AppButton></template></AppNotice>
        <AppNotice v-else-if="savedNotice" tone="success" :description="savedNotice" />
        <AppDialog ref="removeDialog" :title="`Remove ${title.toLowerCase()}?`" description="Your page will use your business name and details. You can add another image whenever you’re ready." confirm-label="Remove image" :close-on-confirm="false" :confirm-disabled="form.processing" :can-close="() => !form.processing" @confirm="remove" />
    </section>
</template>

<style scoped>
.brand-image-editor { display:flex; flex-direction:column; gap:8px; min-width:0; }
.brand-image-preview { display:grid; place-items:center; width:100%; height:120px; border:1px solid var(--border-subtle); border-radius:12px; overflow:hidden; background:var(--surface-subtle); }
.brand-image-preview img { width:100%; height:100%; object-fit:cover; }.brand-image-preview.logo img { object-fit:contain; padding:20px; }
.brand-image-preview>div { display:grid; justify-items:center; gap:8px; font-size:12px; color:var(--text-muted); padding:16px; text-align:center; }
h3 { font-size:14px; font-weight:650; color:var(--text-strong); margin-top:4px; }p { font-size:12px; line-height:1.6; color:var(--text-muted); }.brand-image-note { font-size:11px; }
.brand-image-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:6px; }.brand-image-actions :deep(button) { min-height:44px; }
@media(max-width:639px) { .brand-image-preview { height:108px; }.brand-image-preview.logo img { padding:14px; } }
</style>
