<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionSection from '@/Components/Profile/ActionSection.vue';
import DangerButton from '@/Components/Profile/DangerButton.vue';
import DialogModal from '@/Components/Profile/DialogModal.vue';
import InputError from '@/Components/Profile/InputError.vue';
import SecondaryButton from '@/Components/Profile/SecondaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    setTimeout(() => passwordInput.value.focus(), 250);
};

const deleteUser = () => {
    form.delete(route('current-user.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.reset();
};
</script>

<template>
    <ActionSection>
        <template #title>
            {{ $t('Delete account') }}
        </template>

        <template #description>
            {{ $t('Permanently delete your account.') }}
        </template>

        <template #content>
            <div class="max-w-xl text-sm">
                {{ $t('This permanently removes your sign-in account, profile photo and access tokens. Download any information you need before continuing.') }}
            </div>

            <div class="mt-5">
                <DangerButton @click="confirmUserDeletion">
                    {{ $t('Delete account') }}
                </DangerButton>
            </div>

            <!-- Delete account Confirmation Modal -->
            <DialogModal :show="confirmingUserDeletion" @close="closeModal">
                <template #title>
                    {{ $t('Delete account') }}
                </template>

                <template #content>
                    {{ $t('This permanently deletes your sign-in account. You will lose access and cannot undo this action. Enter your password to confirm.') }}

                    <div class="mt-4">
                        <label for="field-deleteuserform-form-password" class="block text-sm font-semibold">{{ $t('Current password') }}</label>
                        <TextInput
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-3/4"
                            autocomplete="current-password"
                            @keyup.enter="deleteUser"
                         id="field-deleteuserform-form-password" :aria-invalid="form.errors.password ? true : undefined" :aria-describedby="form.errors.password ? 'field-deleteuserform-form-password-error' : undefined"/>

                        <InputError :message="form.errors.password" class="mt-2"  id="field-deleteuserform-form-password-error"/>
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="closeModal">
                        {{ $t('Cancel') }}
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        {{ $t('Delete account') }}
                    </DangerButton>
                </template>
            </DialogModal>
        </template>
    </ActionSection>
</template>
