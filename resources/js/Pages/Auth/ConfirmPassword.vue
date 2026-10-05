<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

const form = useForm({
    password: '',
});

const passwordInput = ref(null);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();

            passwordInput.value.focus();
        },
    });
};
</script>

<template>
    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>
        <Head title="Confirm your password" />
        <h1 class="cd-page-title mb-4">Confirm your password</h1>

        <div class="mb-4 text-sm text-[var(--text-muted)]">
            {{ $t('Enter your password to continue.') }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" :value="$t('Password')" />
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                 :aria-invalid="form.errors.password ? true : undefined" :aria-describedby="form.errors.password ? 'password-error' : undefined"/>
                <InputError class="mt-2" :message="form.errors.password"  id="password-error"/>
            </div>

            <div class="flex justify-end mt-4">
                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Confirm password') }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
