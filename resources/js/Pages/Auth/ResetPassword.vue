<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>
        <Head title="Reset your password" />
        <h1 class="cd-page-title mb-4">Reset your password</h1>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" :value="$t('Email')" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                 :aria-invalid="form.errors.email ? true : undefined" :aria-describedby="form.errors.email ? 'email-error' : undefined"/>
                <InputError class="mt-2" :message="form.errors.email"  id="email-error"/>
            </div>

            <div class="mt-4">
                <InputLabel for="password" :value="$t('Password')" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                 :aria-invalid="form.errors.password ? true : undefined" :aria-describedby="form.errors.password ? 'password-error' : undefined"/>
                <InputError class="mt-2" :message="form.errors.password"  id="password-error"/>
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" :value="$t('Confirm password')" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                 :aria-invalid="form.errors.password_confirmation ? true : undefined" :aria-describedby="form.errors.password_confirmation ? 'password_confirmation-error' : undefined"/>
                <InputError class="mt-2" :message="form.errors.password_confirmation"  id="password_confirmation-error"/>
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Reset password') }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
