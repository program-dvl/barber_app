<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { CheckCircleIcon, ExclamationTriangleIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import Checkbox from '@/Components/Profile/Checkbox.vue';
import Google from '@/Components/Social/Google.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps({ canResetPassword: Boolean, status: String });
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.transform(data => ({ ...data, remember: form.remember ? 'on' : '' })).post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <AuthLayout
        title="Sign in"
        eyebrow="Welcome back to your working day"
        heading="Pick up with the whole day in view."
        description="Appointments, client context and team decisions stay connected, ready for the next person through the door."
        image="/images/marketing/editorial/security-trust.webp"
        image-alt="A service business owner reviewing the day at reception in the evening."
        image-caption="A calm close makes tomorrow easier to open."
    >
        <AuthenticationCard embedded>
            <div class="mb-7">
                <h1 class="cd-display text-2xl font-semibold leading-tight tracking-[-0.05em] text-[var(--text-strong)]">Sign in</h1>

            </div>

            <div v-if="status" class="mb-5 flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--status-success)]/20 bg-[var(--status-success-soft)] p-3.5 text-sm text-[var(--status-success)]" role="status"><CheckCircleIcon class="mt-0.5 size-5 shrink-0" aria-hidden="true" /><span>{{ status }}</span></div>
            <div v-if="$page.props.errors?.google" class="mb-5 flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--status-danger)]/20 bg-[var(--status-danger-soft)] p-3.5 text-sm text-[var(--status-danger)]" role="alert"><ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0" aria-hidden="true" /><span>{{ $page.props.errors.google }}</span></div>

            <Google />
            <div v-if="$page.props.googleAuth?.enabled" class="my-6 flex items-center gap-3" aria-hidden="true"><div class="h-px flex-1 bg-[var(--border-subtle)]"></div><span class="text-[0.7rem] font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">or use email</span><div class="h-px flex-1 bg-[var(--border-subtle)]"></div></div>

            <form @submit.prevent="submit" class="space-y-5">
                <div><InputLabel for="email" :value="$t('Work email')" /><TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full" required autofocus autocomplete="username" placeholder="name@business.com"  :aria-invalid="form.errors.email ? true : undefined" :aria-describedby="form.errors.email ? 'email-error' : undefined"/><InputError class="mt-2" :message="form.errors.email"  id="email-error"/></div>
                <div>
                    <div class="flex items-center justify-between gap-4"><InputLabel for="password" :value="$t('Password')" /><Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-bold text-[var(--brand-secondary)] hover:underline">{{ $t('Forgot password?') }}</Link></div>
                    <TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full" required autocomplete="current-password" placeholder="Enter your password"  :aria-invalid="form.errors.password ? true : undefined" :aria-describedby="form.errors.password ? 'password-error' : undefined"/><InputError class="mt-2" :message="form.errors.password"  id="password-error"/>
                </div>
                <label for="remember" class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-[var(--text-default)]"><Checkbox id="remember" v-model:checked="form.remember" name="remember" /><span>{{ $t('Keep me signed in on this device') }}</span></label>
                <PrimaryButton class="w-full justify-center" :class="{ 'opacity-75': form.processing }" :disabled="form.processing">{{ form.processing ? $t('Signing in…') : $t('Sign in') }}</PrimaryButton>
            </form>

            <p class="mt-6 text-center text-sm text-[var(--text-muted)]">New to ClipperDesk? <Link :href="route('register')" class="font-bold text-[var(--brand-secondary)] hover:underline">Create an account</Link></p>
        </AuthenticationCard>
    </AuthLayout>
</template>
