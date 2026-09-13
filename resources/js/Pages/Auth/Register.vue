<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { CheckBadgeIcon, ExclamationTriangleIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import Checkbox from '@/Components/Profile/Checkbox.vue';
import Google from '@/Components/Social/Google.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const page = usePage();
const googleRegistration = computed(() => page.props.googleAuth?.pending_registration ?? null);
const selection = googleRegistration.value ? { plan: googleRegistration.value.selected_plan, interval: googleRegistration.value.selected_interval } : page.props.signupIntent ?? null;
const form = useForm({
    name: googleRegistration.value?.name ?? '', business_name: '', email: googleRegistration.value?.email ?? '',
    password: '', password_confirmation: '', terms: false,
    selected_plan: selection?.plan ?? null, selected_interval: selection?.interval ?? null,
});
const submit = () => form.post(googleRegistration.value ? route('auth.google.register') : route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthLayout
        title="Create your workspace"
        eyebrow="A considered way to begin"
        heading="Build the workspace around the way your business works."
        description="Set up the owner account first, then shape services, people, hours and booking rules before anything goes live."
        image="/images/marketing/editorial/company-story.webp"
        image-alt="A group of beauty, wellness and service professionals sharing a natural moment in their studio."
        image-caption="For independent owners and growing teams alike."
    >
        <AuthenticationCard embedded wide>
            <div class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--brand-accent)]">Owner workspace</p>
                    <h2 class="cd-display mt-3 text-[clamp(2.15rem,4vw,3rem)] font-semibold leading-[1.02] tracking-[-0.05em] text-[var(--text-strong)]">{{ googleRegistration ? 'Finish setting up your workspace.' : 'Start with a stronger front desk.' }}</h2>
                    <p class="mt-3 max-w-xl text-base leading-7 text-[var(--text-muted)]">{{ googleRegistration ? 'Google verified your identity. Add the business details ClipperDesk needs to create your secure workspace.' : 'Create the owner account for your appointment-based business. No payment details are required to begin.' }}</p>
                </div>
                <div class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full bg-[var(--status-success-soft)] px-3 py-1.5 text-xs font-bold text-[var(--status-success)]"><ShieldCheckIcon class="size-4" aria-hidden="true" /> Secure setup</div>
            </div>

            <div v-if="$page.props.errors?.google" class="mb-5 flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--status-danger)]/20 bg-[var(--status-danger-soft)] p-3.5 text-sm text-[var(--status-danger)]" role="alert"><ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0" aria-hidden="true" /><span>{{ $page.props.errors.google }}</span></div>

            <template v-if="!googleRegistration">
                <Google intent="register" :plan="selection?.plan" :interval="selection?.interval" />
                <div v-if="$page.props.googleAuth?.enabled" class="my-6 flex items-center gap-3" aria-hidden="true"><div class="h-px flex-1 bg-[var(--border-subtle)]"></div><span class="text-[0.7rem] font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">or create with email</span><div class="h-px flex-1 bg-[var(--border-subtle)]"></div></div>
            </template>
            <div v-else class="mb-6 flex items-center gap-3 rounded-[var(--radius-lg)] border border-[var(--status-success)]/20 bg-[var(--status-success-soft)] p-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--surface-raised)] text-[var(--status-success)] shadow-sm"><CheckBadgeIcon class="size-6" aria-hidden="true" /></span>
                <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-[0.1em] text-[var(--status-success)]">Verified by Google</p><p class="mt-0.5 truncate text-sm font-semibold text-[var(--text-strong)]">{{ googleRegistration.email }}</p></div>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div v-if="selection?.plan && selection?.interval" class="rounded-[var(--radius-md)] border border-[var(--status-info)]/20 bg-[var(--status-info-soft)] p-3.5 text-sm leading-6 text-[var(--text-default)]" role="status">Your <strong class="capitalize">{{ selection.plan }}</strong> {{ selection.interval }} preference is saved for review after the trial starts. You will not be charged now.</div>
                <InputError :message="form.errors.selected_plan" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <div><InputLabel for="business_name" :value="$t('Business name')" /><TextInput id="business_name" v-model="form.business_name" type="text" class="mt-2 block w-full" required autofocus autocomplete="organization" placeholder="North & Main Studio" /><InputError class="mt-2" :message="form.errors.business_name" /></div>
                    <div><InputLabel for="name" :value="$t('Your full name')" /><TextInput id="name" v-model="form.name" type="text" class="mt-2 block w-full" required autocomplete="name" placeholder="Alex Morgan" /><InputError class="mt-2" :message="form.errors.name" /></div>
                </div>

                <div><InputLabel for="email" :value="$t('Work email')" /><TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full" :readonly="Boolean(googleRegistration)" required autocomplete="username" placeholder="name@business.com" /><p v-if="googleRegistration" class="mt-1.5 text-xs text-[var(--text-muted)]">This verified Google email will be used for account access.</p><InputError class="mt-2" :message="form.errors.email" /></div>

                <div v-if="!googleRegistration" class="grid gap-5 sm:grid-cols-2">
                    <div><InputLabel for="password" :value="$t('Password')" /><TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full" required autocomplete="new-password" placeholder="At least 8 characters" /><InputError class="mt-2" :message="form.errors.password" /></div>
                    <div><InputLabel for="password_confirmation" :value="$t('Confirm password')" /><TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-2 block w-full" required autocomplete="new-password" placeholder="Repeat your password" /><InputError class="mt-2" :message="form.errors.password_confirmation" /></div>
                </div>

                <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-4">
                    <label for="terms" class="flex cursor-pointer items-start gap-3"><Checkbox id="terms" v-model:checked="form.terms" name="terms" required class="mt-0.5" /><span class="text-sm leading-6 text-[var(--text-default)]" v-html="$t('checkbox.terms', { terms: route('terms.show'), policy: route('policy.show') })"></span></label><InputError class="mt-2" :message="form.errors.terms" />
                </div>

                <PrimaryButton class="w-full justify-center" :class="{ 'opacity-75': form.processing }" :disabled="form.processing">{{ form.processing ? $t('Creating your workspace…') : (googleRegistration ? $t('Create workspace and continue') : $t('Create owner account')) }}</PrimaryButton>
            </form>

            <p class="mt-6 text-center text-sm text-[var(--text-muted)]">Already have a workspace? <Link :href="route('login')" class="font-bold text-[var(--brand-secondary)] hover:underline">Sign in</Link></p>
        </AuthenticationCard>
    </AuthLayout>
</template>
