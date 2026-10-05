<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post(route('two-factor.login'));
};
</script>

<template>
    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>
        <Head title="Two-factor authentication" />
        <h1 class="cd-page-title mb-4">Two-factor authentication</h1>

        <div class="mb-4 text-sm text-[var(--text-muted)]">
            <template v-if="! recovery">
                {{ $t('Enter the code from your authenticator app.') }}
            </template>

            <template v-else>
                {{ $t('Enter one of your saved recovery codes.') }}
            </template>
        </div>

        <form @submit.prevent="submit">
            <div v-if="! recovery">
                <InputLabel for="code" :value="$t('Code')" />
                <TextInput
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 block w-full"
                    autofocus
                    autocomplete="one-time-code"
                 :aria-invalid="form.errors.code ? true : undefined" :aria-describedby="form.errors.code ? 'code-error' : undefined"/>
                <InputError class="mt-2" :message="form.errors.code"  id="code-error"/>
            </div>

            <div v-else>
                <InputLabel for="recovery_code" :value="$t('Recovery code')" />
                <TextInput
                    id="recovery_code"
                    ref="recoveryCodeInput"
                    v-model="form.recovery_code"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="one-time-code"
                 :aria-invalid="form.errors.recovery_code ? true : undefined" :aria-describedby="form.errors.recovery_code ? 'recovery_code-error' : undefined"/>
                <InputError class="mt-2" :message="form.errors.recovery_code"  id="recovery_code-error"/>
            </div>

            <div class="flex items-center justify-end mt-4">
                <button type="button" class="min-h-11 cursor-pointer text-sm text-[var(--text-muted)] underline hover:text-[var(--text-strong)]" @click.prevent="toggleRecovery">
                    <template v-if="! recovery">
                        {{ $t('Use a recovery code') }}
                    </template>

                    <template v-else>
                        {{ $t('Use an authentication code') }}
                    </template>
                </button>

                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Sign in') }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
