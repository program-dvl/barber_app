<script setup>
import { computed, ref, onBeforeUnmount, nextTick } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { CheckBadgeIcon, CameraIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import MarketplaceAccountLayout from '@/Layouts/MarketplaceAccountLayout.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
const props = defineProps({profile:Object});
const page = usePage();
const form = useForm({name:props.profile.name || '',phone:props.profile.phone || ''});
const photoForm = useForm({photo:null});
const photoPreview = ref(''), photoInput = ref(null), photoPicker = ref(null), removeDialog = ref(null), confirmingRemoval = ref(false);
const clearPhotoDraft = () => {if(photoPreview.value) URL.revokeObjectURL(photoPreview.value);photoPreview.value='';photoForm.reset();photoForm.clearErrors();if(photoInput.value)photoInput.value.value='';};
const selectPhoto = event => {if(photoPreview.value)URL.revokeObjectURL(photoPreview.value);photoForm.photo=event.target.files?.[0] || null;photoPreview.value=photoForm.photo ? URL.createObjectURL(photoForm.photo) : '';photoForm.clearErrors();};
const uploadPhoto = () => {if(photoForm.processing || !photoForm.photo || photoForm.errors.photo)return;photoForm.post('/marketplace/profile/photo',{forceFormData:true,preserveScroll:true,onSuccess:clearPhotoDraft});};
const askToRemovePhoto = () => {if(photoForm.processing)return;photoForm.clearErrors();confirmingRemoval.value=true;removeDialog.value?.open();};
const removePhoto = () => {if(photoForm.processing)return;photoForm.delete('/marketplace/profile/photo',{preserveScroll:true,onSuccess:()=>{clearPhotoDraft();confirmingRemoval.value=false;removeDialog.value?.close();nextTick(()=>photoPicker.value?.focus());}});};
onBeforeUnmount(()=>{if(photoPreview.value)URL.revokeObjectURL(photoPreview.value);});
const initials = computed(() => (props.profile.name || props.profile.email).split(/\s+/).slice(0,2).map(part=>part[0]).join('').toUpperCase());
const save = () => form.patch('/marketplace/profile',{preserveScroll:true,onSuccess:()=>form.defaults()});
</script>
<template>
    <MarketplaceAccountLayout title="My profile" description="Your personal details and profile picture." active="profile">
        <section class="mp-account-surface" aria-labelledby="profile-details-title">
            <header class="mp-profile-hero">
                <span class="mp-profile-avatar-controls">
                <button ref="photoPicker" type="button" class="mp-avatar-picker" :aria-label="profile.photo_url ? 'Change profile picture' : 'Add profile picture'" aria-describedby="customer-photo-help" :disabled="photoForm.processing" @click="photoInput?.click()">
                    <span class="mp-account-avatar" aria-hidden="true"><img v-if="photoPreview || profile.photo_url" :src="photoPreview || profile.photo_url" alt="" width="64" height="64"/><template v-else>{{initials}}</template></span>
                    <span class="mp-avatar-camera" aria-hidden="true"><CameraIcon/></span>
                </button>
                <button v-if="profile.photo_url && !photoForm.photo" type="button" class="mp-avatar-remove" aria-label="Remove profile photo" aria-describedby="customer-photo-remove-tip" aria-haspopup="dialog" :disabled="photoForm.processing" @click="askToRemovePhoto"><span><XMarkIcon aria-hidden="true"/></span></button>
                <span v-if="profile.photo_url && !photoForm.photo" id="customer-photo-remove-tip" class="mp-avatar-remove-tooltip" role="tooltip">Remove photo</span>
                </span>
                <input id="customer-photo" ref="photoInput" type="file" hidden accept="image/jpeg,image/png,image/webp" :disabled="photoForm.processing" @change="selectPhoto"/>
                <div class="mp-profile-details">
                    <h2 id="profile-details-title">{{profile.name || 'Your personal details'}}</h2>
                    <span class="mp-profile-verified"><CheckBadgeIcon aria-hidden="true"/>Verified customer account</span>
                    <span id="customer-photo-help" class="sr-only">Select your profile picture to add or change a photo. JPEG, PNG or WebP, up to 5 MB. Visible only in your account.</span>
                    <form v-if="photoForm.photo || (photoForm.errors.photo && !confirmingRemoval)" class="mp-profile-photo-actions" :aria-busy="photoForm.processing" @submit.prevent="uploadPhoto">
                        <AppNotice v-if="photoForm.errors.photo && !confirmingRemoval" title="Photo couldn’t be updated" :description="photoForm.errors.photo" class="mp-profile-photo-error"><template #actions><button type="button" class="mp-button quiet" @click="photoInput?.click()">Choose another photo</button><a :href="`mailto:${page.props.brand?.support_email}`" class="mp-button quiet">Contact support</a></template></AppNotice>
                        <div class="mp-actions">
                            <button v-if="photoForm.photo && !photoForm.errors.photo" class="mp-button" :disabled="photoForm.processing">{{photoForm.processing ? 'Saving…' : 'Save photo'}}</button>
                            <button v-if="photoForm.photo" type="button" class="mp-button quiet" :disabled="photoForm.processing" @click="clearPhotoDraft">Cancel</button>
                        </div>
                    </form>
                </div>
            </header>
            <form class="mp-account-form" @submit.prevent="save"><fieldset :disabled="form.processing"><div class="mp-account-form-grid"><label class="mp-account-field"><span>Full name</span><input v-model="form.name" required maxlength="100" autocomplete="name" :aria-invalid="!!form.errors.name" :aria-describedby="form.errors.name ? 'profile-name-error' : undefined"/><span v-if="form.errors.name" id="profile-name-error" class="mp-account-error" role="alert">{{form.errors.name}}</span></label><div class="mp-account-field"><label for="profile-phone">Mobile number <small>Optional</small></label><PhoneInput id="profile-phone" v-model="form.phone" country="IN" :aria-invalid="!!form.errors.phone" :aria-describedby="form.errors.phone?'profile-phone-save-error':undefined"/><p v-if="form.errors.phone" id="profile-phone-save-error" class="mp-account-error" role="alert">{{form.errors.phone}}</p></div></div><label class="mp-account-field"><span>Email address</span><input :value="profile.email" readonly type="email" aria-describedby="profile-email-help"/><span class="mp-account-help" id="profile-email-help">This verified email connects your bookings across businesses. To change it, <a :href="`mailto:${page.props.brand?.support_email}`">contact support</a>.</span></label></fieldset><footer class="mp-account-form-footer"><div class="mp-actions"><button type="button" class="mp-button secondary" :disabled="form.processing || !form.isDirty" @click="form.reset()">Discard</button><button class="mp-button" :disabled="form.processing || !form.isDirty">{{form.processing?'Saving…':'Save changes'}}</button></div></footer></form>
        </section>
        <AppDialog ref="removeDialog" title="Remove your profile photo?" description="Your initials will appear instead. You can add another photo anytime." confirm-label="Remove photo" cancel-label="Keep photo" destructive :close-on-confirm="false" :confirm-disabled="photoForm.processing" :can-close="()=>!photoForm.processing" @cancel="confirmingRemoval=false;photoForm.clearErrors()" @confirm="removePhoto">
            <p v-if="photoForm.processing" role="status">Removing photo…</p>
            <AppNotice v-else-if="photoForm.errors.photo" title="Your photo is kept" :description="photoForm.errors.photo"><template #actions><a :href="`mailto:${page.props.brand?.support_email}`" class="mp-button quiet">Contact support</a></template></AppNotice>
        </AppDialog>
    </MarketplaceAccountLayout>
</template>
