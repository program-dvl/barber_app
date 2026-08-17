<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Product/AppButton.vue';
import PublicBookingLayout from '@/Layouts/PublicBookingLayout.vue';

const props = defineProps({ token: String, title: String, introduction: String, fields: Array, appointmentReference: String, businessName: String, expiresAt: String });
const form = useForm({ answers: {}, signature: '' });
const submit = () => form.post(route('client-forms.submit', props.token));
</script>

<template>
    <PublicBookingLayout :title="title" mode="self-service">
        <div class="mx-auto max-w-2xl"><p class="text-sm font-semibold text-[var(--brand-pine)]">{{ businessName }}</p><h1 class="gh-display mt-2 text-3xl text-[var(--text-strong)] sm:text-4xl">{{ title }}</h1><p v-if="appointmentReference" class="mt-2 text-sm text-[var(--text-muted)]">For appointment {{ appointmentReference }}</p><p v-if="introduction" class="mt-4 whitespace-pre-wrap leading-7 text-[var(--text-muted)]">{{ introduction }}</p><p v-if="expiresAt" class="mt-3 text-sm text-[var(--text-muted)]">This secure form link expires {{ new Date(expiresAt).toLocaleString() }}.</p><div class="mt-6 rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-5 sm:p-8">
            <form class="mt-6 space-y-5" @submit.prevent="submit"><label v-for="field in fields" :key="field.id" class="block text-sm font-medium"><span>{{ field.label }} <span v-if="field.required" aria-hidden="true">*</span></span>
                <textarea v-if="field.type === 'text'" v-model="form.answers[field.id]" class="gh-input mt-2" :required="field.required" />
                <input v-else-if="field.type === 'number'" v-model="form.answers[field.id]" type="number" class="gh-input mt-2" :required="field.required">
                <input v-else-if="field.type === 'date'" v-model="form.answers[field.id]" type="date" class="gh-input mt-2" :required="field.required">
                <select v-else-if="field.type === 'yes_no'" v-model="form.answers[field.id]" class="gh-input mt-2" :required="field.required"><option value="">Choose</option><option value="yes">Yes</option><option value="no">No</option></select>
                <select v-else-if="field.type === 'multiple_choice'" v-model="form.answers[field.id]" class="gh-input mt-2" :required="field.required"><option value="">Choose</option><option v-for="option in field.options" :key="option" :value="option">{{ option }}</option></select>
                <input v-else-if="field.type === 'signature'" v-model="form.signature" class="gh-input mt-2" placeholder="Type your full name" :required="field.required" autocomplete="name">
                <span v-if="form.errors[field.id]" class="mt-1 block text-sm text-[var(--status-danger)]">{{ form.errors[field.id] }}</span>
            </label><AppButton class="w-full" type="submit" :disabled="form.processing">Submit securely</AppButton></form>
        </div></div>
    </PublicBookingLayout>
</template>
