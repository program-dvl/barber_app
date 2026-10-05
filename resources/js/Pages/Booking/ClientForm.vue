<script setup>
import FieldError from '@/Components/Product/FieldError.vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Product/AppButton.vue';
import PublicBookingLayout from '@/Layouts/PublicBookingLayout.vue';

const props = defineProps({ token: String, title: String, introduction: String, fields: Array, appointmentReference: String, businessName: String, expiresAt: String, timeZone: String });
const form = useForm({ answers: Object.fromEntries(props.fields.map(field => [field.id, ''])), signature: '' });
const submit = () => form.post(route('client-forms.submit', props.token));
</script>

<template>
    <PublicBookingLayout :title="title" mode="self-service">
        <div class="mx-auto max-w-2xl"><p class="text-sm font-semibold text-[var(--brand-primary)]">{{ businessName }}</p><h1 class="cd-display mt-2 text-2xl text-[var(--text-strong)] ">{{ title }}</h1><p v-if="appointmentReference" class="mt-2 text-sm text-[var(--text-muted)]">For appointment {{ appointmentReference }}</p><p v-if="introduction" class="mt-4 whitespace-pre-wrap leading-7 text-[var(--text-muted)]">{{ introduction }}</p><p v-if="expiresAt" class="mt-3 text-sm text-[var(--text-muted)]">This secure form link expires {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short', timeZone: timeZone || 'UTC' }).format(new Date(expiresAt)) }}.</p><div class="mt-6 rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-5 sm:p-5">
            <form class="space-y-4" @submit.prevent="submit"><label v-for="field in fields" :key="field.id" :for="`answer-${field.id}`" class="block text-sm font-medium"><span>{{ field.label }} <span v-if="field.required" aria-hidden="true">*</span><span v-if="field.required" class="ds-sr-only"> required</span></span>
                <textarea v-if="field.type === 'text'" v-model="form.answers[field.id]" class="cd-input mt-2" :id="`answer-${field.id}`" :required="field.required" :aria-invalid="(field.type === 'signature' ? form.errors.signature : form.errors[`answers.${field.id}`]) ? true : undefined" :aria-describedby="`answer-${field.id}-error`" />
                <input v-else-if="field.type === 'number'" v-model="form.answers[field.id]" type="number" class="cd-input mt-2" :id="`answer-${field.id}`" :required="field.required" :aria-invalid="(field.type === 'signature' ? form.errors.signature : form.errors[`answers.${field.id}`]) ? true : undefined" :aria-describedby="`answer-${field.id}-error`">
                <input v-else-if="field.type === 'date'" v-model="form.answers[field.id]" type="date" class="cd-input mt-2" :id="`answer-${field.id}`" :required="field.required" :aria-invalid="(field.type === 'signature' ? form.errors.signature : form.errors[`answers.${field.id}`]) ? true : undefined" :aria-describedby="`answer-${field.id}-error`">
                <select v-else-if="field.type === 'yes_no'" v-model="form.answers[field.id]" class="cd-input mt-2" :id="`answer-${field.id}`" :required="field.required" :aria-invalid="(field.type === 'signature' ? form.errors.signature : form.errors[`answers.${field.id}`]) ? true : undefined" :aria-describedby="`answer-${field.id}-error`"><option value="">Choose</option><option value="yes">Yes</option><option value="no">No</option></select>
                <select v-else-if="field.type === 'multiple_choice'" v-model="form.answers[field.id]" class="cd-input mt-2" :id="`answer-${field.id}`" :required="field.required" :aria-invalid="(field.type === 'signature' ? form.errors.signature : form.errors[`answers.${field.id}`]) ? true : undefined" :aria-describedby="`answer-${field.id}-error`"><option value="">Choose</option><option v-for="option in field.options" :key="option" :value="option">{{ option }}</option></select>
                <input v-else-if="field.type === 'signature'" v-model="form.signature" class="cd-input mt-2" placeholder="Type your full name" :id="`answer-${field.id}`" :required="field.required" :aria-invalid="(field.type === 'signature' ? form.errors.signature : form.errors[`answers.${field.id}`]) ? true : undefined" :aria-describedby="`answer-${field.id}-error`" autocomplete="name">
                <FieldError :id="`answer-${field.id}-error`" :message="field.type === 'signature' ? form.errors.signature : form.errors[`answers.${field.id}`]" /></label><AppButton class="w-full" type="submit" :loading="form.processing">Submit form</AppButton></form>
        </div></div>
    </PublicBookingLayout>
</template>
