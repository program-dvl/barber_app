<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { effectiveCatalogPreview, durationLabel } from '@/Support/serviceCatalog';
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
const fieldErrors = ref({});
const notice = ref('');
const details = ref({ client_name: '', client_mobile: '', client_email: '', client_date_of_birth: '', referral_source: '', special_request: '', communication_preferences: ['email'], marketing_opt_in: false, policy_accepted: false });
const waitlist = ref({ client_name: '', client_mobile: '', client_email: '', acceptable_from: '', acceptable_until: '', time_from: '09:00', time_until: '18:00', notification_method: 'email', notes: '' });
const currency = computed(() => props.catalog?.services?.find(item => selection.value.services.includes(item.public_id))?.currency_code || props.business?.currency_code || 'INR');
const selectedServices = computed(() => (props.catalog?.services || []).filter(item => selection.value.services.includes(item.public_id)));
const selectedLocation = computed(() => (props.catalog?.locations || []).find(item => item.public_id === selection.value.location));
const hasEligibleProfessional = item => (props.catalog?.staff || []).some(member => member.location_ids.includes(selection.value.location) && member.service_ids.includes(item.public_id));
const categories = computed(() => ['All', ...new Set((props.catalog?.services || []).filter(item => item.location_ids.includes(selection.value.location) && hasEligibleProfessional(item)).map(item => item.category || 'Services'))]);
const activeCategory = ref('All');
const allowedAddon = item => item.kind !== 'addon' || (props.catalog?.services || []).some(parent => selection.value.services.includes(parent.public_id) && parent.addon_ids?.includes(item.public_id));
const visibleServices = computed(() => (props.catalog?.services || []).filter(item => item.location_ids.includes(selection.value.location) && hasEligibleProfessional(item) && allowedAddon(item) && (activeCategory.value === 'All' || item.category === activeCategory.value)));
const serviceEstimate = item => effectiveCatalogPreview(item, props.catalog?.staff?.find(p => p.public_id === selection.value.staff)?.service_variants?.find(v => v.service === item.public_id), item.location_prices?.find(p => p.location === selection.value.location)?.price_minor);
const selectedDuration = computed(() => selectedServices.value.reduce((sum, item) => sum + serviceEstimate(item).visit_minutes, 0));
const selectedTotal = computed(() => selectedServices.value.reduce((sum, item) => sum + serviceEstimate(item).price_minor, 0));
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
    selection.value.services = selection.value.services.filter(value => { const service = props.catalog.services.find(item => item.public_id === value); return service && allowedAddon(service); });
    selection.value.staff = '';
};
const chooseLocation = id => {
    selection.value.location = id;
    selection.value.services = selection.value.services.filter(value => props.catalog.services.find(item => item.public_id === value)?.location_ids.includes(id));
    selection.value.services = selection.value.services.filter(value => allowedAddon(props.catalog.services.find(item => item.public_id === value)));
    selection.value.staff = ''; activeCategory.value = 'All';
};
const search = async () => {
    busy.value = true; error.value = ''; notice.value = ''; fieldErrors.value = {};
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
    busy.value = true; error.value = ''; notice.value = ''; fieldErrors.value = {};
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
    busy.value = true; error.value = ''; notice.value = ''; fieldErrors.value = {};
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
            fieldErrors.value = requestError.response?.data?.errors || {};
            error.value = Object.keys(fieldErrors.value).length ? 'Check the highlighted fields.' : requestError.response?.data?.message || 'The appointment could not be confirmed.';
            if (['client_name', 'client_mobile', 'client_email', 'client_date_of_birth', 'special_request'].some(key => fieldErrors.value[key])) await moveToStep(3);
        }
    }
    finally { busy.value = false; }
};
const joinWaitlist = async () => {
    busy.value = true; error.value = ''; notice.value = ''; fieldErrors.value = {};
    try {
        await axios.post(route('public.waitlist.store', props.business.booking_slug), {
            location: selection.value.location, service: selection.value.services[0], staff: selection.value.staff || null, ...waitlist.value,
        });
        notice.value = 'Waitlist request saved. We will notify you using your chosen method.';
    } catch (requestError) { fieldErrors.value = requestError.response?.data?.errors || {}; error.value = Object.keys(fieldErrors.value).length ? 'Check the highlighted fields.' : requestError.response?.data?.message || 'The waitlist request could not be saved.'; }
    finally { busy.value = false; }
};
</script>

<template>
    <PublicBookingLayout title="Book an appointment" :current-step="step">
        <template v-if="!business">
            <section class="cd-panel mx-auto max-w-lg p-6"><h1 class="cd-page-title">Book an appointment</h1><p class="mt-2 text-sm text-[var(--text-muted)]">Ask the business for its ClipperDesk booking link.</p></section>
        </template>
        <template v-else>
            <div :style="brandStyle">
                <header class="relative overflow-hidden rounded-[2rem] bg-[var(--surface-inverse)] text-white shadow-[var(--shadow-raised)]">
                    <img v-if="step === 1" :src="business.cover_url" :alt="business.cover_alt" class="absolute inset-0 size-full object-cover opacity-75" />
                    <div v-if="step === 1" class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-black/15" aria-hidden="true" />
                    <div :class="['relative flex flex-col justify-end p-5 sm:p-6', step === 1 && 'min-h-[12rem]']">
                        <div v-if="step === 1 && business.logo_url" class="mb-5 grid size-16 place-items-center overflow-hidden rounded-2xl border border-white/20 bg-white shadow-lg"><img :src="business.logo_url" :alt="`${business.name} logo`" class="size-full object-contain" /></div>
                        <span v-else-if="step === 1" class="mb-5 grid size-14 place-items-center rounded-2xl border border-white/15 bg-white/12 text-xl font-bold backdrop-blur" aria-hidden="true">{{ business.name.charAt(0) }}</span>
                        <p v-if="step === 1" class="text-xs font-semibold text-white/80">Book an appointment</p>
                        <h1 class="cd-display mt-2 max-w-3xl text-2xl font-semibold leading-tight tracking-[-0.055em]">{{ business.name }}</h1>
                        <p v-if="business.description" class="mt-4 max-w-2xl text-sm leading-6 text-white/75 sm:text-base">{{ business.description }}</p>
                        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/75"><span class="inline-flex items-center gap-2"><MapPinIcon class="size-4" />{{ selectedLocation?.name || business.address }}</span><span class="inline-flex items-center gap-2"><ClockIcon class="size-4" />{{ selectedLocation?.time_zone?.replaceAll('_', ' ') }}</span></div>
                    </div>
                </header>
                <p v-if="error" class="mt-5 rounded-xl border border-[var(--status-danger)]/20 bg-[var(--status-danger-soft)] p-4 text-sm text-[var(--text-strong)]" role="alert">{{ error }}</p>
                <p v-if="notice" role="status" class="mt-4 rounded-lg bg-[var(--status-success-soft)] p-3 text-sm text-[var(--status-success)]">{{ notice }}</p>

                <div v-if="step === 1" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">
                    <section class="overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-sm" aria-labelledby="choose-services-title">
                        <div class="border-b border-[var(--border-subtle)] p-5 sm:p-4"><h2 id="choose-services-title" class="cd-section-title">Choose services</h2><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">Select one or more services.</p></div>
                        <div v-if="catalog.locations.length > 1" class="border-b border-[var(--border-subtle)] p-5 sm:p-4"><p class="text-sm font-semibold text-[var(--text-strong)]">Choose a location</p><div class="mt-3 grid gap-2 sm:grid-cols-2"><button v-for="location in catalog.locations" :key="location.public_id" type="button" :aria-pressed="selection.location === location.public_id" :class="['flex min-h-16 items-start gap-3 rounded-xl border p-3 text-left', selection.location === location.public_id ? 'bg-[var(--status-info-soft)] ring-1' : 'border-[var(--border-subtle)]']" :style="selection.location === location.public_id ? { borderColor: 'var(--booking-accent)', ringColor: 'var(--booking-accent)' } : {}" @click="chooseLocation(location.public_id)"><MapPinIcon class="mt-0.5 size-5 shrink-0" style="color:var(--booking-accent)" /><span><strong class="block text-sm">{{ location.name }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ location.address }}</span></span></button></div></div>
                        <div class="border-b border-[var(--border-subtle)] px-5 py-3 sm:px-6"><div class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Service categories"><button v-for="category in categories" :key="category" type="button" :aria-pressed="activeCategory === category" :class="['min-h-11 shrink-0 rounded-full px-4 text-sm font-semibold transition', activeCategory === category ? 'text-white' : 'bg-[var(--surface-subtle)] text-[var(--text-default)] hover:bg-[var(--border-subtle)]']" :style="activeCategory === category ? { backgroundColor: 'var(--booking-accent)' } : {}" @click="activeCategory = category">{{ category }}</button></div></div>
                        <div class="divide-y divide-[var(--border-subtle)]"><p v-if="!visibleServices.length" class="p-5 text-sm text-[var(--text-muted)]">No online services are available here. Contact the salon to book.</p><button v-for="service in visibleServices" :key="service.public_id" type="button" :aria-pressed="selection.services.includes(service.public_id)" :class="['group flex min-h-20 w-full items-start gap-4 p-5 text-left transition sm:p-4', selection.services.includes(service.public_id) ? 'bg-[var(--status-info-soft)]' : 'hover:bg-[var(--surface-subtle)]']" @click="chooseService(service.public_id)"><span :class="['mt-0.5 grid size-11 shrink-0 place-items-center rounded-xl transition', selection.services.includes(service.public_id) ? 'text-white' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']" :style="selection.services.includes(service.public_id) ? { backgroundColor: 'var(--booking-accent)' } : {}"><SparklesIcon class="size-5" aria-hidden="true" /></span><span class="min-w-0 flex-1"><span class="text-xs font-bold uppercase tracking-wide text-[var(--text-muted)]">{{ service.category }}</span><strong class="mt-1 block text-[var(--text-strong)]">{{ service.kind === 'addon' ? 'Add-on · ' : '' }}{{ service.name }}</strong><span v-if="service.description" class="mt-1 block text-sm leading-6 text-[var(--text-muted)]">{{ service.description }}</span><span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[var(--text-muted)]"><ClockIcon class="size-4" />{{ durationLabel(serviceEstimate(service).visit_minutes) }}</span></span><span class="flex shrink-0 items-center gap-2 font-semibold text-[var(--text-strong)]">{{ service.price_type === 'from' ? 'From ' : '' }}{{ money(serviceEstimate(service).price_minor) }}<CheckCircleIcon v-if="selection.services.includes(service.public_id)" class="size-5" style="color:var(--booking-accent)" aria-hidden="true" /></span></button></div>
                        <div class="border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-5 lg:hidden"><div class="mb-4 flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide text-[var(--text-muted)]">Your visit</p><p class="mt-1 font-semibold text-[var(--text-strong)]">{{ selectedServices.length ? `${selectedServices.length} selected · ${durationLabel(selectedDuration)}` : 'Choose a service above' }}</p></div><span class="text-right"><small class="block text-[var(--text-muted)]">Estimated price</small><strong class="text-lg text-[var(--text-strong)]">{{ money(selectedTotal) }}</strong></span></div><div class="grid gap-4 sm:grid-cols-2"><FormField id="client-kind-mobile" label="Client type"><select id="client-kind-mobile" v-model="selection.client_eligibility" class="cd-input"><option value="new">A new client</option><option value="existing">A returning client</option></select></FormField><FormField id="booking-date-mobile" label="Preferred date" required><input id="booking-date-mobile" v-model="selection.date" type="date" :min="earliestDate" class="cd-input" /></FormField><FormField v-if="catalog.policy.online_staff_preference !== 'any_only'" id="booking-staff-mobile" class="sm:col-span-2" label="Professional"><select id="booking-staff-mobile" v-model="selection.staff" class="cd-input"><option value="">Any available professional</option><option v-for="member in eligibleStaff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}{{ member.title ? ` · ${member.title}` : '' }}</option></select></FormField></div><button type="button" :disabled="busy || !selection.date || !selection.services.length" class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-45" style="background-color:var(--booking-accent)" @click="search">Find available times<ArrowRightIcon class="size-4" /></button></div>
                    </section>

                    <aside class="sticky top-24 hidden overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-sm lg:block" aria-label="Booking summary">
                        <div class="h-28 overflow-hidden"><img v-if="step === 1" :src="business.cover_url" alt="" class="size-full object-cover" /></div><div class="p-5"><p class="text-xs font-bold uppercase tracking-[0.13em] text-[var(--text-muted)]">Your visit</p><h2 class="mt-2 text-lg font-semibold text-[var(--text-strong)]">{{ selectedServices.length ? `${selectedServices.length} selected` : 'Choose a service' }}</h2><ul v-if="selectedServices.length" class="mt-4 space-y-3"><li v-for="service in selectedServices" :key="service.public_id" class="flex justify-between gap-3 text-sm"><span class="text-[var(--text-default)]">{{ service.name }}</span><span class="shrink-0 font-semibold">{{ money(serviceEstimate(service).price_minor) }}</span></li></ul><div class="mt-5 border-t border-[var(--border-subtle)] pt-4"><div class="flex justify-between text-sm"><span class="text-[var(--text-muted)]">Estimated time</span><strong>{{ durationLabel(selectedDuration) }}</strong></div><div class="mt-2 flex justify-between"><span class="font-semibold text-[var(--text-strong)]">Estimated price</span><strong class="text-lg">{{ money(selectedTotal) }}</strong></div></div>
                            <div class="mt-5 grid gap-4"><FormField id="client-kind" label="Client type"><select id="client-kind" v-model="selection.client_eligibility" class="cd-input"><option value="new">A new client</option><option value="existing">A returning client</option></select></FormField><FormField id="booking-date" label="Preferred date" required><input id="booking-date" v-model="selection.date" type="date" :min="earliestDate" class="cd-input" /></FormField><FormField v-if="catalog.policy.online_staff_preference !== 'any_only'" id="booking-staff" label="Professional"><select id="booking-staff" v-model="selection.staff" class="cd-input"><option value="">Any available professional</option><option v-for="member in eligibleStaff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}{{ member.title ? ` · ${member.title}` : '' }}</option></select></FormField></div>
                            <button type="button" :disabled="busy || !selection.date || !selection.services.length" class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-45" style="background-color:var(--booking-accent)" @click="search">Find available times<ArrowRightIcon class="size-4" /></button>
                        </div>
                    </aside>
                </div>

            <SurfaceCard v-else-if="step === 2" class="mt-6" title="Choose a time" :description="`${chosenDateLabel} · ${slots.length} available ${slots.length === 1 ? 'time' : 'times'}`">
                <div v-if="slots.length" class="grid grid-cols-3 gap-3 sm:grid-cols-4"><button v-for="slot in slots" :key="slot.starts_at_utc" type="button" class="min-h-11 rounded-lg border border-[var(--border-strong)] bg-white p-2 text-sm font-semibold hover:border-[var(--brand-primary)] hover:bg-[var(--status-success-soft)]" :disabled="busy" @click="hold(slot)">{{ localTime(slot.starts_at_local || slot.starts_at_utc) }}</button></div>
                <div v-else><StatePanel title="No available times" description="Try another date or join the waitlist." /><form class="cd-form-width mt-5 grid gap-3" @submit.prevent="joinWaitlist"><div class="grid gap-3 sm:grid-cols-2">
<FormField id="waitlist-name" label="Name" required :error="fieldErrors.client_name?.[0]"><input id="waitlist-name" v-model="waitlist.client_name" class="cd-input" autocomplete="name" /></FormField>
<FormField id="waitlist-mobile" label="Mobile" required :error="fieldErrors.client_mobile?.[0]"><PhoneInput id="waitlist-mobile" v-model="waitlist.client_mobile" :country="business?.country_code || 'US'" /></FormField>
<FormField id="waitlist-email" label="Email" :required="waitlist.notification_method === 'email'" :error="fieldErrors.client_email?.[0]"><input id="waitlist-email" v-model="waitlist.client_email" type="email" class="cd-input" autocomplete="email" /></FormField>
<FormField id="waitlist-method" label="Notify me by"><select id="waitlist-method" v-model="waitlist.notification_method" class="cd-input"><option value="email">Email</option><option value="sms">Text message (SMS)</option></select></FormField>
<FormField id="waitlist-from" label="From date" required :error="fieldErrors.acceptable_from?.[0]"><input id="waitlist-from" v-model="waitlist.acceptable_from" type="date" :min="earliestDate" class="cd-input" /></FormField>
<FormField id="waitlist-until" label="Until date" required :error="fieldErrors.acceptable_until?.[0]"><input id="waitlist-until" v-model="waitlist.acceptable_until" type="date" :min="waitlist.acceptable_from || earliestDate" class="cd-input" /></FormField>
</div><AppButton type="submit" variant="secondary" :loading="busy" class="justify-self-start">Join waitlist</AppButton></form></div>
                <p v-if="slots.length" class="mt-4 text-sm text-[var(--text-muted)]">Times use {{ catalog.locations.find(item => item.public_id === selection.location)?.time_zone.replaceAll('_', ' ') }} and are checked again when selected.</p><AppButton class="mt-5" variant="quiet" @click="moveToStep(1)">Back to choices</AppButton>
            </SurfaceCard>

            <SurfaceCard v-else-if="step === 3" class="mx-auto mt-6 max-w-2xl" title="Your details" :description="`Held until ${localDate(held.hold_expires_at)}`">
<form @submit.prevent="moveToStep(4)">                <div class="grid gap-4 sm:grid-cols-2"><FormField :error="fieldErrors.client_name?.[0]" id="client-name" label="Name" required><input id="client-name" v-model="details.client_name" required autocomplete="name" class="cd-input" /></FormField><FormField :error="fieldErrors.client_mobile?.[0]" id="client-mobile" label="Mobile" required ><PhoneInput id="client-mobile" v-model="details.client_mobile" required :country="business?.country_code || 'IN'" /></FormField><FormField :error="fieldErrors.client_email?.[0]" id="client-email" label="Email" required><input id="client-email" v-model="details.client_email" required type="email" autocomplete="email" class="cd-input" /></FormField><FormField :error="fieldErrors.client_date_of_birth?.[0]" id="client-dob" label="Date of birth (optional)"><input id="client-dob" v-model="details.client_date_of_birth" type="date" class="cd-input" /></FormField></div>
                <FormField :error="fieldErrors.special_request?.[0]" id="special-request" class="mt-4" label="Special request (optional)"><textarea id="special-request" v-model="details.special_request" rows="3" class="cd-input" /></FormField>
                <AppButton type="submit" class="mt-6 w-full">Review booking</AppButton>
</form>
            </SurfaceCard>

            <SurfaceCard v-else-if="step === 4" class="mx-auto mt-6 max-w-2xl" title="Review and confirm">
                <p class="mb-4 text-sm"><strong>{{ localDate(selectedSlot.starts_at_utc) }}</strong><span class="block text-[var(--text-muted)]">{{ details.client_name }} · {{ selectedLocation?.name }}</span></p><ul class="space-y-3"><li v-for="service in held.policy.services" :key="service.name" class="flex justify-between gap-4 border-b border-[var(--border-subtle)] pb-3"><span><strong>{{ service.name }}</strong><span class="block text-sm text-[var(--text-muted)]">{{ service.bookable_minutes }} minutes</span></span><span class="font-semibold">{{ service.price_type === 'from' ? 'From ' : '' }}{{ money(serviceEstimate(service).price_minor) }}</span></li></ul>
                <div class="mt-4 rounded-xl bg-[var(--surface-subtle)] p-4 text-sm"><p><strong>Deposit:</strong> {{ held.policy.deposit_status === 'not_required' ? 'Not required' : `${money(held.policy.deposit.amount_minor)} required before confirmation` }}</p><p class="mt-2"><strong>Cancellation:</strong> {{ held.policy.cancellation_policy }}</p><p class="mt-2"><a :href="held.policy.terms_url" class="underline">Terms</a> · <a :href="held.policy.privacy_url" class="underline">Privacy</a></p></div>
                <label v-if="held.policy.deposit_status === 'not_required'" class="mt-4 flex min-h-11 items-start gap-3 text-sm"><input v-model="details.policy_accepted" type="checkbox" class="mt-1" /><span>I agree to the booking terms, privacy notice, and cancellation policy shown above.</span></label><label v-if="held.policy.deposit_status === 'not_required'" class="mt-2 flex min-h-11 items-start gap-3 text-sm"><input type="checkbox" :checked="details.communication_preferences.includes('sms')" @change="$event.target.checked ? details.communication_preferences.push('sms') : details.communication_preferences = details.communication_preferences.filter(channel => channel !== 'sms')" class="mt-1" /><span>Send essential appointment texts, including confirmation, important changes, cancellation, and one reminder. Message and data rates may apply. Reply STOP to opt out.</span></label>
                <p v-if="held.policy.deposit_status === 'payment_required'" class="mt-4 rounded-lg bg-[var(--status-warning-soft)] p-3 text-sm text-[var(--status-warning)]" role="status">Online deposit payment is not available on this page. Contact the business to arrange your appointment. Your appointment has not been confirmed.</p><AppButton v-if="held.policy.deposit_status === 'not_required'" class="mt-5 w-full" :loading="busy" :disabled="!details.policy_accepted" @click="confirm"><LockClosedIcon class="size-5" aria-hidden="true" />Confirm appointment</AppButton>
            <AppButton class="mt-3" variant="quiet" @click="moveToStep(3)">Edit details</AppButton></SurfaceCard>

            <SurfaceCard v-else class="mx-auto mt-6 max-w-2xl" title="Appointment confirmed" :description="`Reference ${confirmation.reference}`"><StatePanel tone="success" title="Your appointment" :description="`${localDate(confirmation.starts_at)} · Keep your secure link private.`"><template #actions><AppButton :href="confirmation.calendar_url" variant="secondary"><CalendarDaysIcon class="size-5" aria-hidden="true" />Add to calendar</AppButton><AppButton :href="confirmation.view_url">View appointment</AppButton></template></StatePanel></SurfaceCard>
            </div>
        </template>
    </PublicBookingLayout>
</template>
