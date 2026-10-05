<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>
        <Head title="Verify your email" />
        <h1 class="cd-page-title mb-4">Verify your email</h1>

        <div class="mb-4 text-sm text-[var(--text-muted)]">
            {{ $t("Open the verification link in your email to continue.") }}
        </div>

        <div v-if="verificationLinkSent" class="mb-4 text-sm font-medium text-[var(--status-success)]">
            {{ $t('A new verification link has been sent.') }}
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex flex-wrap gap-3 items-center justify-between">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Resend verification email') }}
                </PrimaryButton>

                <div>
                    <Link
                        :href="route('profile.show')"
                        class="link"
                    >
                        {{ $t('Edit email') }}
                    </Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="link ms-2"
                    >
                        {{ $t('Sign out') }}
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>
