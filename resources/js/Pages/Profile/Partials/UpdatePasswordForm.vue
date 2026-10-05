<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/Profile/ActionMessage.vue';
import FormSection from '@/Components/Profile/FormSection.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <FormSection @submitted="updatePassword">
        <template #title>
            {{ $t('Change password') }}
        </template>

        <template #description>
            {{ $t('Use a unique password with at least 8 characters.') }}
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="current_password" :value="$t('Current password')" />
                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                 :aria-invalid="form.errors.current_password ? true : undefined" :aria-describedby="form.errors.current_password ? 'current_password-error' : undefined"/>
                <InputError :message="form.errors.current_password" class="mt-2"  id="current_password-error"/>
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="password" :value="$t('New password')" />
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                 :aria-invalid="form.errors.password ? true : undefined" :aria-describedby="form.errors.password ? 'password-error' : undefined"/>
                <InputError :message="form.errors.password" class="mt-2"  id="password-error"/>
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="password_confirmation" :value="$t('Confirm password')" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                 :aria-invalid="form.errors.password_confirmation ? true : undefined" :aria-describedby="form.errors.password_confirmation ? 'password_confirmation-error' : undefined"/>
                <InputError :message="form.errors.password_confirmation" class="mt-2"  id="password_confirmation-error"/>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                {{ $t('Saved.') }}
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ $t('Change password') }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>
