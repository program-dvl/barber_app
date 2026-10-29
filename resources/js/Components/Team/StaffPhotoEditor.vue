<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import StaffAvatar from './StaffAvatar.vue';

const props = defineProps({ business: Object, person: Object, disabled: Boolean, supportEmail: { type: String, default: 'support@clipperdesk.com' } });
const emit = defineEmits(['dirty', 'busy', 'saved', 'refresh']);
const form = useForm({ photo: null, profile_revision: '' });
const previewUrl = ref('');
const input = ref(null);
const failure = ref('');
const errors = computed(() => failure.value || Object.values(form.errors).join(' '));
const supportUrl = computed(() => `mailto:${props.supportEmail}?subject=Team%20photo%20help&body=${encodeURIComponent(`Business reference: ${props.business.public_id}`)}`);
const clear = () => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
    form.reset('photo');
    if (input.value) input.value.value = '';
};
defineExpose({ discard: clear });
watch(() => props.person.public_id, () => { clear(); form.clearErrors(); failure.value = ''; });
watch(() => Boolean(form.photo), value => emit('dirty', value));
watch(() => form.processing, value => emit('busy', value));
onBeforeUnmount(() => { if (previewUrl.value) URL.revokeObjectURL(previewUrl.value); });
const choose = event => {
    const file = event.target.files?.[0];
    if (!file) return;
    form.clearErrors(); failure.value = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        failure.value = 'Choose a JPEG, PNG or WebP photo smaller than 5 MB.';
        event.target.value = '';
        return;
    }
    clear(); form.photo = file; previewUrl.value = URL.createObjectURL(file);
};
const options = {
    preserveScroll: true,
    onSuccess: () => { clear(); emit('saved'); },
    onNetworkError: () => { failure.value = 'The connection was interrupted. Your chosen photo is still here. Refresh the profile to check whether it saved before trying again.'; return false; },
};
const save = () => {
    failure.value = ''; form.clearErrors(); form.profile_revision = props.person.profile_revision;
    form.post(route('business.team.photo.store', [props.business.public_id, props.person.public_id]), options);
};
const remove = () => {
    failure.value = ''; form.clearErrors(); form.profile_revision = props.person.profile_revision;
    form.transform(data => ({ profile_revision: data.profile_revision })).delete(route('business.team.photo.destroy', [props.business.public_id, props.person.public_id]), { ...options, onFinish: () => form.transform(data => data) });
};
</script>

<template>
    <section class="staff-photo-editor" aria-labelledby="staff-photo-heading">
        <div class="staff-photo-heading">
            <StaffAvatar :name="person.display_name" :photo-url="previewUrl || person.photo_url" />
            <div><h3 id="staff-photo-heading">Profile photo <span>optional</span></h3><p>A clear portrait helps clients recognize their professional. Initials appear when no photo is added.</p></div>
        </div>
        <p class="staff-photo-help">JPEG, PNG or WebP · up to 5 MB · at least 100 × 100 pixels.</p>
        <p v-if="disabled && !form.processing" class="staff-photo-help">Save or discard other edits before changing the photo.</p>
        <div class="staff-photo-actions">
            <AppButton variant="secondary" :disabled="disabled || form.processing" @click="input?.click()">{{ person.photo_url ? 'Choose another photo' : 'Choose photo' }}</AppButton>
            <AppButton v-if="form.photo" :disabled="disabled || form.processing" @click="save">{{ form.processing ? 'Saving…' : 'Save photo' }}</AppButton>
            <AppButton v-if="form.photo" variant="quiet" :disabled="form.processing" @click="clear">Discard photo</AppButton>
            <AppButton v-else-if="person.photo_url" variant="quiet" :disabled="disabled || form.processing" @click="remove">{{ form.processing ? 'Removing…' : 'Remove photo' }}</AppButton>
        </div>
        <input ref="input" class="ds-sr-only" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Choose a team member profile photo" :disabled="disabled || form.processing" @change="choose" />
        <AppNotice v-if="errors" class="mt-4" tone="warning" title="Your photo has not been updated" :description="errors" urgent>
            <template #actions><AppButton v-if="form.errors.profile_revision || failure" variant="secondary" :disabled="form.processing" @click="emit('refresh')">Refresh profile</AppButton><AppButton variant="quiet" :href="supportUrl">Contact support</AppButton></template>
        </AppNotice>
    </section>
</template>

<style scoped>
.staff-photo-editor { padding: 0 0 20px; margin-bottom: 20px; border-bottom: 1px solid var(--border-subtle); }
.staff-photo-heading { display: flex; align-items: center; gap: 12px; }
.staff-photo-heading :deep(.staff-avatar) { width: 64px; height: 64px; }
.staff-photo-heading h3 { margin: 0; font-size: 14px; }
.staff-photo-heading h3 span { font-weight: 400; color: var(--text-muted); font-size: 12px; }
.staff-photo-heading p, .staff-photo-help { margin-top: 6px; font-size: 12px; line-height: 1.55; color: var(--text-muted); }
.staff-photo-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
.staff-photo-actions :deep(button) { min-height: 44px; }
</style>
