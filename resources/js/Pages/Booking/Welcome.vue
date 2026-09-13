<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import axios from 'axios';
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ClockIcon,
    LockClosedIcon,
    MapPinIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import FormField from '@/Components/Product/FormField.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PublicBookingLayout from '@/Layouts/PublicBookingLayout.vue';

const props = defineProps({ business: Object, catalog: Object });
const inputDate = offset => {
    const date = new Date();
    date.setHours(12, 0, 0, 0);
    date.setDate(date.getDate() + offset);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};
const earliestDate = inputDate(0);
const step = ref(props.business ? 1 : 0);
const flow = ref(null); const secret = ref(null); const flowExpiresAt = ref(null); const busy = ref(false); const error = ref('');
const selection = ref({ location: props.catalog?.locations?.[0]?.public_id || '', services: [], staff: '', date: inputDate(1), client_eligibility: 'new' });
const slots = ref([]); const selectedSlot = ref(null); const held = ref(null); const confirmation = ref(null);
const details = ref({ client_name: '', client_mobile: '', client_email: '', client_date_of_birth: '', referral_source: '', special_request: '', communication_preferences: ['email'], marketing_opt_in: false, policy_accepted: false });
const waitlist = ref({ client_name: '', client_mobile: '', client_email: '', acceptable_from: '', acceptable_until: '', time_from: '09:00', time_until: '18:00', notification_method: 'email', notes: '' });
const currency = computed(() => props.catalog?.services?.find(item => selection.value.services.includes(item.public_id))?.currency_code || props.business?.currency_code || 'INR');
const selectedServices = computed(() => (props.catalog?.services || []).filter(item => selection.value.services.includes(item.public_id)));
const selectedLocation = computed(() => (props.catalog?.locations || []).find(item => item.public_id === selection.value.location));
const categories = computed(() => ['All', ...new Set((props.catalog?.services || []).filter(item => item.location_ids.includes(selection.value.location)).map(item => item.category || 'Services'))]);
const activeCategory = ref('All');
const visibleServices = computed(() => (props.catalog?.services || []).filter(item => item.location_ids.includes(selection.value.location) && (activeCategory.value === 'All' || item.category === activeCategory.value)));
const selectedDuration = computed(() => selectedServices.value.reduce((sum, item) => sum + item.duration_minutes, 0));
const selectedTotal = computed(() => selectedServices.value.reduce((sum, item) => sum + item.price_minor, 0));
const brandStyle = computed(() => ({ '--booking-accent': props.business?.brand_color || '#6D4AFF' }));
const eligibleStaff = computed(() => (props.catalog?.staff || []).filter(member => member.location_ids.includes(selection.value.location) && selection.value.services.every(service => member.service_ids.includes(service))));
const localDate = value => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short', timeZone: props.catalog.locations.find(item => item.public_id === selection.value.location)?.time_zone }).format(new Date(value));
const localTime = value => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: props.catalog.locations.find(item => item.public_id === selection.value.location)?.time_zone }).format(new Date(value));
const chosenDateLabel = computed(() => selection.value.date ? new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${selection.value.date}T12:00:00`)) : 'your chosen date');
const money = minor => new Intl.NumberFormat(undefined, { style: 'currency', currency: currency.value }).format((minor || 0) / 100);
const sessionKey = computed(() => `clipperdesk-booking-${props.business?.booking_slug}`);
const moveToStep = async value => {
    step.value = value;
    await nextTick();
    document.querySelector('#public-main')?.focus({ preventScroll: true });
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const clearSavedFlow = () => sessionStorage.removeItem(sessionKey.value);
const readSavedFlow = () => {
    try {
        return JSON.parse(sessionStorage.getItem(sessionKey.value) || 'null');
    } catch {
        clearSavedFlow();
        return null;
    }
};
const isUsableFlow = candidate => candidate?.flow && candidate?.secret && candidate?.phase === 'started'
    && Number.isFinite(Date.parse(candidate.expires_at)) && Date.parse(candidate.expires_at) > Date.now() + 15000;
const rememberFlow = expiresAt => sessionStorage.setItem(sessionKey.value, JSON.stringify({
    flow: flow.value,
    secret: secret.value,
    expires_at: expiresAt,
    phase: 'started',
}));
const startFreshFlow = async () => {
    clearSavedFlow();
    const response = await axios.post(route('public.booking.start', props.business.booking_slug));
    flow.value = response.data.flow;
    secret.value = response.data.secret;
    flowExpiresAt.value = response.data.expires_at;
    rememberFlow(response.data.expires_at);
};
const ensureFlow = async (forceFresh = false) => {
    if (!props.business) return;
    if (!forceFresh && flow.value && secret.value && Date.parse(flowExpiresAt.value) > Date.now() + 15000) return;
    const saved = forceFresh ? null : readSavedFlow();
    if (isUsableFlow(saved)) {
        flow.value = saved.flow;
        secret.value = saved.secret;
        flowExpiresAt.value = saved.expires_at;
        return;
    }
    await startFreshFlow();
};
onMounted(() => ensureFlow().catch(() => { error.value = 'Booking could not start. Refresh and try again.'; }));

const requestCode = requestError => requestError.response?.data?.code;
const flowExpired = requestError => requestCode(requestError) === 'BOOKING_FLOW_EXPIRED';
const holdEnded = requestError => ['BOOKING_FLOW_EXPIRED', 'HOLD_EXPIRED', 'HOLD_NOT_FOUND', 'STALE_AVAILABILITY'].includes(requestCode(requestError));
const requestAvailability = () => axios.post(route('public.booking.search', props.business.booking_slug), {
    flow: flow.value, secret: secret.value, location: selection.value.location, services: selection.value.services,
    staff: selection.value.staff || null, from_date: selection.value.date, until_date: selection.value.date,
    client_eligibility: selection.value.client_eligibility,
});
const requestHold = slot => axios.post(route('public.booking.hold', props.business.booking_slug), {
    flow: flow.value, secret: secret.value, location: selection.value.location, services: selection.value.services,
    staff: selection.value.staff || null, starts_at: slot.starts_at_utc, client_eligibility: selection.value.client_eligibility,
    idempotency_key: `public-hold-${flow.value}-${slot.starts_at_utc}`,
});

const chooseService = id => {
    selection.value.services = selection.value.services.includes(id) ? selection.value.services.filter(value => value !== id) : [...selection.value.services, id];
    selection.value.staff = '';
};
const search = async () => {
    busy.value = true; error.value = '';
    try {
        await ensureFlow();
        let response;
        try {
            response = await requestAvailability();
        } catch (requestError) {
            if (!flowExpired(requestError)) throw requestError;
            await ensureFlow(true);
            response = await requestAvailability();
        }
        slots.value = response.data.slots; await moveToStep(2);
    } catch (requestError) { error.value = requestError.response?.data?.message || 'Availability changed. Check your choices and try again.'; }
    finally { busy.value = false; }
};
const hold = async slot => {
    busy.value = true; error.value = '';
    try {
        let response;
        try {
            response = await requestHold(slot);
        } catch (requestError) {
            if (!flowExpired(requestError)) throw requestError;
            await ensureFlow(true);
            response = await requestHold(slot);
        }
        selectedSlot.value = slot;
        held.value = response.data;
        flowExpiresAt.value = response.data.hold_expires_at;
        clearSavedFlow();
        await moveToStep(3);
    } catch (requestError) { error.value = requestError.response?.data?.message || 'That time is no longer available. Choose another.'; }
    finally { busy.value = false; }
};
const confirm = async () => {
    busy.value = true; error.value = '';
    try {
        const response = await axios.post(route('public.booking.confirm', props.business.booking_slug), {
            flow: flow.value, secret: secret.value, ...details.value,
            idempotency_key: `public-confirm-${flow.value}`,
        });
        confirmation.value = response.data; await moveToStep(5); sessionStorage.removeItem(sessionKey.value);
    } catch (requestError) {
        if (holdEnded(requestError)) {
            try {
                held.value = null;
                selectedSlot.value = null;
                details.value.policy_accepted = false;
                await ensureFlow(true);
                const response = await requestAvailability();
                slots.value = response.data.slots;
                await moveToStep(2);
                error.value = 'Your temporary hold ended, so we refreshed the latest availability. Please choose a time again.';
            } catch (refreshError) {
                error.value = refreshError.response?.data?.message || 'We could not refresh availability. Return to your choices and try again.';
            }
        } else {
            error.value = requestError.response?.data?.message || requestError.response?.data?.errors?.policy_accepted?.[0] || 'The booking could not be confirmed.';
        }
    }
    finally { busy.value = false; }
};
const joinWaitlist = async () => {
    busy.value = true; error.value = '';
    try {
        await axios.post(route('public.waitlist.store', props.business.booking_slug), {
            location: selection.value.location, service: selection.value.services[0], staff: selection.value.staff || null, ...waitlist.value,
        });
        error.value = 'Waitlist request saved. We will only use your chosen notification method for this request.';
    } catch (requestError) { error.value = requestError.response?.data?.message || 'The waitlist request could not be saved.'; }
    finally { busy.value = false; }
};
</script>

<template>
    <PublicBookingLayout title="Book an appointment" :current-step="step">
        <template v-if="!business">
            <div class="text-center"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--brand-primary)]">Book with confidence</p><h1 class="cd-display mt-2 text-4xl text-[var(--text-strong)] sm:text-5xl">Use your business’s booking link</h1><p class="mx-auto mt-3 max-w-xl leading-7 text-[var(--text-muted)]">Open the private booking address shared by your beauty, wellness, fitness, health, pet-care or independent service professional.</p></div>
            <SurfaceCard class="mt-8"><StatePanel tone="info" title="Looking for an appointment?" description="Ask the business for its ClipperDesk booking link. We do not publish a marketplace or expose client and staff contact data." /></SurfaceCard>
        </template>
        <template v-else>
            <div :style="brandStyle">
                <header class="relative overflow-hidden rounded-[2rem] bg-[var(--surface-inverse)] text-white shadow-[var(--shadow-raised)]">
                    <img :src="business.cover_url" :alt="business.cover_alt" class="absolute inset-0 size-full object-cover opacity-75" />
                    <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-black/15" aria-hidden="true" />
                    <div class="relative flex min-h-[19rem] flex-col justify-end p-6 sm:p-9">
                        <div v-if="business.logo_url" class="mb-5 grid size-16 place-items-center overflow-hidden rounded-2xl border border-white/20 bg-white shadow-lg"><img :src="business.logo_url" :alt="`${business.name} logo`" class="size-full object-contain" /></div>
                        <span v-else class="mb-5 grid size-14 place-items-center rounded-2xl border border-white/15 bg-white/12 text-xl font-bold backdrop-blur" aria-hidden="true">{{ business.name.charAt(0) }}</span>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-white/65">Book direct · Instant confirmation</p>
                        <h1 class="cd-display mt-2 max-w-3xl text-[clamp(2.4rem,7vw,4.75rem)] font-semibold leading-[0.95] tracking-[-0.055em]">{{ business.name }}</h1>
                        <p v-if="business.description" class="mt-4 max-w-2xl text-sm leading-6 text-white/75 sm:text-base">{{ business.description }}</p>
                        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/75"><span class="inline-flex items-center gap-2"><MapPinIcon class="size-4" />{{ selectedLocation?.name || business.address }}</span><span class="inline-flex items-center gap-2"><ClockIcon class="size-4" />Times shown locally</span></div>
                    </div>
                </header>
                <p v-if="error" class="mt-5 rounded-xl border border-[var(--status-info)]/20 bg-[var(--status-info-soft)] p-4 text-sm text-[var(--text-strong)]" role="status">{{ error }}</p>

                <div v-if="step === 1" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">
                    <section class="overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-sm" aria-labelledby="choose-services-title">
                        <div class="border-b border-[var(--border-subtle)] p-5 sm:p-6"><p class="text-xs font-bold uppercase tracking-[0.13em]" style="color:var(--booking-accent)">Your appointment</p><h2 id="choose-services-title" class="mt-2 text-2xl font-semibold text-[var(--text-strong)]">What would you like to book?</h2><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">Choose one or more services. You’ll see the full time and price before confirming.</p></div>
                        <div v-if="catalog.locations.length > 1" class="border-b border-[var(--border-subtle)] p-5 sm:p-6"><p class="text-sm font-semibold text-[var(--text-strong)]">Choose a location</p><div class="mt-3 grid gap-2 sm:grid-cols-2"><button v-for="location in catalog.locations" :key="location.public_id" type="button" :aria-pressed="selection.location === location.public_id" :class="['flex min-h-16 items-start gap-3 rounded-xl border p-3 text-left', selection.location === location.public_id ? 'bg-[var(--status-info-soft)] ring-1' : 'border-[var(--border-subtle)]']" :style="selection.location === location.public_id ? { borderColor: 'var(--booking-accent)', ringColor: 'var(--booking-accent)' } : {}" @click="selection.location = location.public_id; activeCategory = 'All'"><MapPinIcon class="mt-0.5 size-5 shrink-0" style="color:var(--booking-accent)" /><span><strong class="block text-sm">{{ location.name }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ location.address }}</span></span></button></div></div>
                        <div class="border-b border-[var(--border-subtle)] px-5 py-3 sm:px-6"><div class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Service categories"><button v-for="category in categories" :key="category" type="button" :aria-pressed="activeCategory === category" :class="['min-h-10 shrink-0 rounded-full px-4 text-sm font-semibold transition', activeCategory === category ? 'text-white' : 'bg-[var(--surface-subtle)] text-[var(--text-default)] hover:bg-[var(--border-subtle)]']" :style="activeCategory === category ? { backgroundColor: 'var(--booking-accent)' } : {}" @click="activeCategory = category">{{ category }}</button></div></div>
                        <div class="divide-y divide-[var(--border-subtle)]"><button v-for="service in visibleServices" :key="service.public_id" type="button" :aria-pressed="selection.services.includes(service.public_id)" :class="['group flex min-h-28 w-full items-start gap-4 p-5 text-left transition sm:p-6', selection.services.includes(service.public_id) ? 'bg-[var(--status-info-soft)]' : 'hover:bg-[var(--surface-subtle)]']" @click="chooseService(service.public_id)"><span :class="['mt-0.5 grid size-11 shrink-0 place-items-center rounded-xl transition', selection.services.includes(service.public_id) ? 'text-white' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']" :style="selection.services.includes(service.public_id) ? { backgroundColor: 'var(--booking-accent)' } : {}"><SparklesIcon class="size-5" aria-hidden="true" /></span><span class="min-w-0 flex-1"><span class="text-xs font-bold uppercase tracking-wide text-[var(--text-muted)]">{{ service.category }}</span><strong class="mt-1 block text-[var(--text-strong)]">{{ service.kind === 'addon' ? 'Add-on · ' : '' }}{{ service.name }}</strong><span class="mt-1 block text-sm leading-6 text-[var(--text-muted)]">{{ service.description || 'A professional service tailored to you.' }}</span><span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[var(--text-muted)]"><ClockIcon class="size-4" />{{ service.duration_minutes }} min</span></span><span class="flex shrink-0 items-center gap-2 font-semibold text-[var(--text-strong)]">{{ service.price_type === 'from' ? 'From ' : '' }}{{ money(service.price_minor) }}<CheckCircleIcon v-if="selection.services.includes(service.public_id)" class="size-5" style="color:var(--booking-accent)" aria-hidden="true" /></span></button></div>
                        <div class="border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-5 lg:hidden"><div class="mb-4 flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide text-[var(--text-muted)]">Your visit</p><p class="mt-1 font-semibold text-[var(--text-strong)]">{{ selectedServices.length ? `${selectedServices.length} selected · ${selectedDuration} min` : 'Choose a service above' }}</p></div><strong class="text-lg text-[var(--text-strong)]">{{ money(selectedTotal) }}</strong></div><div class="grid gap-4 sm:grid-cols-2"><FormField id="client-kind-mobile" label="I am"><select id="client-kind-mobile" v-model="selection.client_eligibility" class="cd-input"><option value="new">A new client</option><option value="existing">A returning client</option></select></FormField><FormField id="booking-date-mobile" label="Preferred date" required><input id="booking-date-mobile" v-model="selection.date" type="date" :min="earliestDate" class="cd-input" /></FormField><FormField v-if="catalog.policy.online_staff_preference !== 'any_only'" id="booking-staff-mobile" class="sm:col-span-2" label="Professional"><select id="booking-staff-mobile" v-model="selection.staff" class="cd-input"><option value="">Any available professional</option><option v-for="member in eligibleStaff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}{{ member.title ? ` · ${member.title}` : '' }}</option></select></FormField></div><button type="button" :disabled="busy || !selection.date || !selection.services.length" class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-45" style="background-color:var(--booking-accent)" @click="search">Find available times<ArrowRightIcon class="size-4" /></button></div>
                    </section>

                    <aside class="sticky top-24 hidden overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-sm lg:block" aria-label="Booking summary">
                        <div class="h-28 overflow-hidden"><img :src="business.cover_url" alt="" class="size-full object-cover" /></div><div class="p-5"><p class="text-xs font-bold uppercase tracking-[0.13em] text-[var(--text-muted)]">Your visit</p><h2 class="mt-2 text-lg font-semibold text-[var(--text-strong)]">{{ selectedServices.length ? `${selectedServices.length} selected` : 'Choose a service' }}</h2><ul v-if="selectedServices.length" class="mt-4 space-y-3"><li v-for="service in selectedServices" :key="service.public_id" class="flex justify-between gap-3 text-sm"><span class="text-[var(--text-default)]">{{ service.name }}</span><span class="shrink-0 font-semibold">{{ money(service.price_minor) }}</span></li></ul><div class="mt-5 border-t border-[var(--border-subtle)] pt-4"><div class="flex justify-between text-sm"><span class="text-[var(--text-muted)]">Estimated time</span><strong>{{ selectedDuration }} min</strong></div><div class="mt-2 flex justify-between"><span class="font-semibold text-[var(--text-strong)]">Total</span><strong class="text-lg">{{ money(selectedTotal) }}</strong></div></div>
                            <div class="mt-5 grid gap-4"><FormField id="client-kind" label="I am"><select id="client-kind" v-model="selection.client_eligibility" class="cd-input"><option value="new">A new client</option><option value="existing">A returning client</option></select></FormField><FormField id="booking-date" label="Preferred date" required><input id="booking-date" v-model="selection.date" type="date" :min="earliestDate" class="cd-input" /></FormField><FormField v-if="catalog.policy.online_staff_preference !== 'any_only'" id="booking-staff" label="Professional"><select id="booking-staff" v-model="selection.staff" class="cd-input"><option value="">Any available professional</option><option v-for="member in eligibleStaff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}{{ member.title ? ` · ${member.title}` : '' }}</option></select></FormField></div>
                            <button type="button" :disabled="busy || !selection.date || !selection.services.length" class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-45" style="background-color:var(--booking-accent)" @click="search">Find available times<ArrowRightIcon class="size-4" /></button><p class="mt-3 flex items-start gap-2 text-xs leading-5 text-[var(--text-muted)]"><LockClosedIcon class="mt-0.5 size-4 shrink-0" />No account needed. Nothing is charged until clearly shown.</p>
                        </div>
                    </aside>
                </div>

            <SurfaceCard v-else-if="step === 2" class="mt-6" title="Choose a time" :description="`${chosenDateLabel} · ${slots.length} available ${slots.length === 1 ? 'time' : 'times'}`">
                <div v-if="slots.length" class="grid grid-cols-3 gap-3 sm:grid-cols-4"><button v-for="slot in slots" :key="slot.starts_at_utc" type="button" class="min-h-14 rounded-xl border border-[var(--border-strong)] bg-white p-3 text-base font-semibold hover:border-[var(--brand-primary)] hover:bg-[var(--status-success-soft)]" @click="hold(slot)">{{ localTime(slot.starts_at_local || slot.starts_at_utc) }}</button></div>
                <div v-else><StatePanel title="No safe times found" description="Try another date, choose first available, or join the waitlist. Private staff schedules are never shown." /><form class="mt-5 grid gap-3" @submit.prevent="joinWaitlist"><div class="grid gap-3 sm:grid-cols-2"><input v-model="waitlist.client_name" required placeholder="Name" aria-label="Waitlist name" class="min-h-11 rounded-lg border px-3" /><PhoneInput id="waitlist-mobile" v-model="waitlist.client_mobile" required :country="business?.country_code || 'IN'" /><input v-model="waitlist.client_email" type="email" placeholder="Email (optional)" aria-label="Waitlist email" class="min-h-11 rounded-lg border px-3" /><select v-model="waitlist.notification_method" aria-label="Waitlist notification method" class="min-h-11 rounded-lg border px-3"><option value="email">Email</option><option value="whatsapp">WhatsApp</option></select><input v-model="waitlist.acceptable_from" required type="date" aria-label="Waitlist from date" class="min-h-11 rounded-lg border px-3" /><input v-model="waitlist.acceptable_until" required type="date" aria-label="Waitlist until date" class="min-h-11 rounded-lg border px-3" /></div><AppButton type="submit" variant="secondary">Join waitlist</AppButton></form></div>
                <p v-if="slots.length" class="mt-4 text-sm text-[var(--text-muted)]">Times use {{ catalog.locations.find(item => item.public_id === selection.location)?.time_zone.replaceAll('_', ' ') }} and are checked again when selected.</p><AppButton class="mt-5" variant="quiet" @click="moveToStep(1)">Back to choices</AppButton>
            </SurfaceCard>

            <SurfaceCard v-else-if="step === 3" class="mt-6" title="Your details" :description="`Held until ${localDate(held.hold_expires_at)}`">
                <div class="grid gap-4 sm:grid-cols-2"><FormField id="client-name" label="Name" required><input id="client-name" v-model="details.client_name" required autocomplete="name" class="cd-input" /></FormField><FormField id="client-mobile" label="Mobile" required hint="The country code defaults from this business; choose another country when needed."><PhoneInput id="client-mobile" v-model="details.client_mobile" required :country="business?.country_code || 'IN'" /></FormField><FormField id="client-email" label="Email" required><input id="client-email" v-model="details.client_email" required type="email" autocomplete="email" class="cd-input" /></FormField><FormField id="client-dob" label="Date of birth (optional)"><input id="client-dob" v-model="details.client_date_of_birth" type="date" class="cd-input" /></FormField></div>
                <FormField id="special-request" class="mt-4" label="Special request (optional)"><textarea id="special-request" v-model="details.special_request" rows="3" class="w-full rounded-lg border p-3" /></FormField>
                <p class="mt-4 text-sm leading-6 text-[var(--text-muted)]">These details are shared with {{ business.name }} only for this booking and future visit context.</p><AppButton class="mt-6 w-full" @click="moveToStep(4)">Review booking</AppButton>
            </SurfaceCard>

            <SurfaceCard v-else-if="step === 4" class="mt-6" title="Review and confirm" description="Price, duration, deposit, and current policy are frozen for this confirmation attempt.">
                <ul class="space-y-3"><li v-for="service in held.policy.services" :key="service.name" class="flex justify-between gap-4 border-b border-[var(--border-subtle)] pb-3"><span><strong>{{ service.name }}</strong><span class="block text-sm text-[var(--text-muted)]">{{ service.bookable_minutes }} minutes</span></span><span class="font-semibold">{{ service.price_type === 'from' ? 'From ' : '' }}{{ money(service.price_minor) }}</span></li></ul>
                <div class="mt-4 rounded-xl bg-[var(--surface-subtle)] p-4 text-sm"><p><strong>Deposit:</strong> {{ held.policy.deposit_status === 'not_required' ? 'Not required' : 'The business will contact you separately to collect the required deposit.' }}</p><p class="mt-2"><strong>Cancellation:</strong> {{ held.policy.cancellation_policy }}</p><p class="mt-2"><a :href="held.policy.terms_url" class="underline">Terms</a> · <a :href="held.policy.privacy_url" class="underline">Privacy</a></p></div>
                <label class="mt-4 flex min-h-11 items-start gap-3 text-sm"><input v-model="details.policy_accepted" type="checkbox" class="mt-1" /><span>I agree to the booking terms, privacy notice, and cancellation policy shown above.</span></label><label class="mt-2 flex min-h-11 items-start gap-3 text-sm"><input type="checkbox" :checked="details.communication_preferences.includes('whatsapp')" @change="$event.target.checked ? details.communication_preferences.push('whatsapp') : details.communication_preferences = details.communication_preferences.filter(channel => channel !== 'whatsapp')" class="mt-1" /><span>Send appointment and service updates to this mobile number on WhatsApp. I can opt out at any time.</span></label><label class="mt-2 flex min-h-11 items-start gap-3 text-sm"><input v-model="details.marketing_opt_in" type="checkbox" class="mt-1" /><span>Optional: send me marketing updates. Booking messages do not depend on this choice.</span></label>
                <AppButton class="mt-5 w-full" :disabled="busy || !details.policy_accepted" @click="confirm"><LockClosedIcon class="size-5" aria-hidden="true" />Confirm booking</AppButton>
            </SurfaceCard>

            <SurfaceCard v-else class="mt-6" title="You’re booked" :description="`Reference ${confirmation.reference}`"><StatePanel tone="success" title="Appointment confirmed" :description="`${localDate(confirmation.starts_at)} · Keep your secure link private.`"><template #actions><AppButton :href="confirmation.calendar_url" variant="secondary"><CalendarDaysIcon class="size-5" aria-hidden="true" />Add to calendar</AppButton><AppButton :href="confirmation.view_url">View appointment</AppButton></template></StatePanel></SurfaceCard>
            </div>
        </template>
    </PublicBookingLayout>
</template>
