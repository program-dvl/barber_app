<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { BuildingOffice2Icon, CheckCircleIcon, KeyIcon, MapPinIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import Checkbox from '@/Components/Profile/Checkbox.vue';
import InputError from '@/Components/Profile/InputError.vue';
import ProductMark from '@/Components/Product/ProductMark.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

const props = defineProps({
    businessName: { type: String, required: true },
    personName: String,
    email: { type: String, required: true },
    role: { type: String, required: true },
    locations: { type: Array, default: () => [] },
    expiresAt: { type: String, required: true },
    acceptUrl: { type: String, required: true },
    registerUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
    authenticated: Boolean,
    identityMatches: Boolean,
    existingAccount: Boolean,
    hasTerms: Boolean,
});
const accepting = ref(false);
const form = useForm({ name: props.personName || '', password: '', password_confirmation: '', terms: false });
const accept = () => {
    accepting.value = true;
    router.post(props.acceptUrl, {}, { onFinish: () => { accepting.value = false; } });
};
const register = () => form.post(props.registerUrl, { onFinish: () => form.reset('password', 'password_confirmation') });
const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="min-h-screen bg-[var(--surface-inverse)] px-4 py-8 text-[var(--text-default)] sm:py-14">
        <Head title="Staff invitation" />
        <main class="mx-auto grid max-w-5xl overflow-hidden rounded-[2rem] bg-[var(--surface-raised)] shadow-[var(--shadow-overlay)] lg:grid-cols-[0.88fr_1.12fr]">
            <section class="relative overflow-hidden bg-[linear-gradient(145deg,#111c3d,#29368a)] p-7 text-white sm:p-10">
                <div class="absolute -right-20 -top-20 size-64 rounded-full bg-violet-400/20 blur-3xl" aria-hidden="true"></div>
                <ProductMark inverse large />
                <p class="mt-14 text-xs font-bold uppercase tracking-[0.16em] text-cyan-200">A place on the team</p>
                <h1 class="cd-display mt-4 text-4xl font-semibold leading-tight tracking-[-0.04em]">{{ businessName }} has invited you in.</h1>
                <p class="mt-4 max-w-md leading-7 text-white/70">Your calendar, clients and everyday tools will be ready according to the access chosen for you.</p>
                <div class="mt-10 space-y-3">
                    <div class="flex items-start gap-3 rounded-2xl bg-white/10 p-4"><ShieldCheckIcon class="mt-0.5 size-5 shrink-0 text-cyan-200" /><div><p class="font-semibold">{{ role }}</p><p class="mt-1 text-sm text-white/65">Role and module access are managed by the business owner.</p></div></div>
                    <div class="flex items-start gap-3 rounded-2xl bg-white/10 p-4"><MapPinIcon class="mt-0.5 size-5 shrink-0 text-cyan-200" /><div><p class="font-semibold">{{ locations.length ? locations.join(', ') : 'Business workspace' }}</p><p class="mt-1 text-sm text-white/65">Your assigned working location{{ locations.length === 1 ? '' : 's' }}.</p></div></div>
                </div>
            </section>

            <section class="p-7 sm:p-10 lg:p-12">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-[var(--brand-primary-soft)] text-[var(--brand-secondary)]"><KeyIcon class="size-6" /></div>
                <p class="mt-6 text-xs font-bold uppercase tracking-[0.14em] text-[var(--brand-secondary)]">Secure invitation</p>
                <h2 class="cd-display mt-3 text-3xl font-semibold tracking-[-0.04em] text-[var(--text-strong)]">Set up your own login</h2>
                <p class="mt-3 text-sm leading-6 text-[var(--text-muted)]">Access is tied to <strong class="text-[var(--text-strong)]">{{ email }}</strong>. This single-use invitation expires {{ new Date(expiresAt).toLocaleString() }}.</p>

                <div v-if="authenticated && !identityMatches" class="mt-6 rounded-2xl bg-[var(--status-warning-soft)] p-4 text-sm leading-6 text-[var(--status-warning)]"><p>You are signed in with a different email. Log out and sign in as {{ email }} to continue.</p><AppButton class="mt-4" variant="secondary" @click="logout">Log out safely</AppButton></div>

                <div v-else-if="authenticated" class="mt-8">
                    <div class="mb-5 flex items-center gap-3 rounded-2xl bg-[var(--status-success-soft)] p-4 text-sm text-[var(--status-success)]"><CheckCircleIcon class="size-5 shrink-0" /><span>Your identity matches this invitation. You can join now.</span></div>
                    <AppButton type="button" class="w-full" :disabled="accepting" @click="accept">{{ accepting ? 'Joining…' : `Join ${businessName}` }}</AppButton>
                </div>

                <div v-else-if="existingAccount" class="mt-8">
                    <p class="rounded-2xl bg-[var(--surface-subtle)] p-4 text-sm leading-6 text-[var(--text-default)]">You already have a ClipperDesk account. Sign in with {{ email }} and you will return here to approve access.</p>
                    <AppButton :href="loginUrl" class="mt-5 w-full">Sign in to accept</AppButton>
                </div>

                <form v-else class="mt-7 space-y-5" @submit.prevent="register">
                    <div><label for="invite-name" class="text-sm font-semibold">Your name</label><TextInput id="invite-name" v-model="form.name" class="mt-2 block w-full" required autocomplete="name" autofocus /><InputError class="mt-2" :message="form.errors.name" /></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="invite-password" class="text-sm font-semibold">Create password</label><TextInput id="invite-password" v-model="form.password" type="password" class="mt-2 block w-full" required autocomplete="new-password" /><InputError class="mt-2" :message="form.errors.password" /></div>
                        <div><label for="invite-password-confirmation" class="text-sm font-semibold">Confirm password</label><TextInput id="invite-password-confirmation" v-model="form.password_confirmation" type="password" class="mt-2 block w-full" required autocomplete="new-password" /></div>
                    </div>
                    <label v-if="hasTerms" class="flex items-start gap-3 rounded-2xl bg-[var(--surface-subtle)] p-4 text-sm leading-6"><Checkbox v-model:checked="form.terms" required class="mt-1" /><span>I agree to the <Link :href="route('terms.show')" class="font-semibold text-[var(--brand-secondary)]">Terms of Service</Link> and <Link :href="route('policy.show')" class="font-semibold text-[var(--brand-secondary)]">Privacy Policy</Link>.</span></label>
                    <InputError :message="form.errors.terms || form.errors.email || form.errors.invitation" />
                    <AppButton type="submit" class="w-full" :disabled="form.processing">{{ form.processing ? 'Creating secure access…' : `Create account and join ${businessName}` }}</AppButton>
                    <p class="text-center text-xs leading-5 text-[var(--text-muted)]"><BuildingOffice2Icon class="mr-1 inline size-4" />This creates a team login—not a separate business workspace.</p>
                </form>
            </section>
        </main>
    </div>
</template>
