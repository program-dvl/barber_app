<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/Components/Product/AppButton.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PublicBookingLayout from '@/Layouts/PublicBookingLayout.vue';

const props = defineProps({ token: String, action_url: String, status: String, expires_at: String, business: String, location: String, service: String, starts_at: String });
const page = usePage();
const form = useForm({});
const claim = () => form.post(props.action_url || route('public.waitlist.claim', props.token));
const localTime = new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'short' }).format(new Date(props.starts_at));
</script>

<template>
    <PublicBookingLayout title="Waitlist opening" mode="self-service">
        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--action-primary)]">Time-limited waitlist offer</p><h1 class="gh-display mt-2 text-4xl text-[var(--text-strong)]">An opening is available</h1><p class="mt-3 text-[var(--text-muted)]">{{ business }} · {{ location }}</p>
        <SurfaceCard class="mt-6" :title="service" :description="localTime"><p class="text-sm text-[var(--text-muted)]">This offer expires {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(expires_at)) }}. Openings are confirmed in the order they are claimed, so this time may no longer be available if someone responds first.</p><p v-if="page.props.errors?.waitlist" class="mt-4 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ page.props.errors.waitlist }}</p><AppButton v-if="status === 'offered'" class="mt-5 w-full" :disabled="form.processing" @click="claim">Claim this appointment</AppButton><StatePanel v-else class="mt-5" tone="info" title="This offer is no longer active" description="It may have expired or another client may already have claimed the opening." /></SurfaceCard>
    </PublicBookingLayout>
</template>
