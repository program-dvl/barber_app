<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import axios from 'axios';
import { CalendarDaysIcon, CheckCircleIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import FormField from '@/Components/Product/FormField.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PublicBookingLayout from '@/Layouts/PublicBookingLayout.vue';

const props = defineProps({ business: Object, catalog: Object });
const step = ref(props.business ? 1 : 0);
const flow = ref(null); const secret = ref(null); const busy = ref(false); const error = ref('');
const selection = ref({ location: props.catalog?.locations?.[0]?.public_id || '', services: [], staff: '', date: '', client_eligibility: 'new' });
const slots = ref([]); const selectedSlot = ref(null); const held = ref(null); const confirmation = ref(null);
const details = ref({ client_name: '', client_mobile: '', client_email: '', client_date_of_birth: '', referral_source: '', special_request: '', communication_preferences: ['email'], marketing_opt_in: false, policy_accepted: false });
const waitlist = ref({ client_name: '', client_mobile: '', client_email: '', acceptable_from: '', acceptable_until: '', time_from: '09:00', time_until: '18:00', notification_method: 'email', notes: '' });
const currency = computed(() => props.catalog?.services?.find(item => selection.value.services.includes(item.public_id))?.currency_code || 'INR');
const selectedServices = computed(() => (props.catalog?.services || []).filter(item => selection.value.services.includes(item.public_id)));
const eligibleStaff = computed(() => (props.catalog?.staff || []).filter(member => member.location_ids.includes(selection.value.location) && selection.value.services.every(service => member.service_ids.includes(service))));
const localDate = value => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short', timeZone: props.catalog.locations.find(item => item.public_id === selection.value.location)?.time_zone }).format(new Date(value));
const localTime = value => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: props.catalog.locations.find(item => item.public_id === selection.value.location)?.time_zone }).format(new Date(value));
const chosenDateLabel = computed(() => selection.value.date ? new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${selection.value.date}T12:00:00`)) : 'your chosen date');
const money = minor => new Intl.NumberFormat(undefined, { style: 'currency', currency: currency.value }).format((minor || 0) / 100);
const sessionKey = computed(() => `good-hours-booking-${props.business?.booking_slug}`);
const moveToStep = async value => {
    step.value = value;
    await nextTick();
    document.querySelector('#public-main')?.focus({ preventScroll: true });
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const ensureFlow = async () => {
    if (!props.business) return;
    const saved = JSON.parse(sessionStorage.getItem(sessionKey.value) || 'null');
    if (saved?.flow && saved?.secret) { flow.value = saved.flow; secret.value = saved.secret; return; }
    const response = await axios.post(route('public.booking.start', props.business.booking_slug));
    flow.value = response.data.flow; secret.value = response.data.secret;
    sessionStorage.setItem(sessionKey.value, JSON.stringify({ flow: flow.value, secret: secret.value }));
};
onMounted(() => ensureFlow().catch(() => { error.value = 'Booking could not start. Refresh and try again.'; }));

const chooseService = id => {
    selection.value.services = selection.value.services.includes(id) ? selection.value.services.filter(value => value !== id) : [...selection.value.services, id];
    selection.value.staff = '';
};
const search = async () => {
    busy.value = true; error.value = '';
    try {
        await ensureFlow();
        const response = await axios.post(route('public.booking.search', props.business.booking_slug), {
            flow: flow.value, secret: secret.value, location: selection.value.location, services: selection.value.services,
            staff: selection.value.staff || null, from_date: selection.value.date, until_date: selection.value.date,
            client_eligibility: selection.value.client_eligibility,
        });
        slots.value = response.data.slots; await moveToStep(2);
    } catch (requestError) { error.value = requestError.response?.data?.message || 'Availability changed. Check your choices and try again.'; }
    finally { busy.value = false; }
};
const hold = async slot => {
    busy.value = true; error.value = '';
    try {
        const response = await axios.post(route('public.booking.hold', props.business.booking_slug), {
            flow: flow.value, secret: secret.value, location: selection.value.location, services: selection.value.services,
            staff: selection.value.staff || null, starts_at: slot.starts_at_utc, client_eligibility: selection.value.client_eligibility,
            idempotency_key: `public-hold-${flow.value}-${slot.starts_at_utc}`,
        });
        selectedSlot.value = slot; held.value = response.data; await moveToStep(3);
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
    } catch (requestError) { error.value = requestError.response?.data?.message || requestError.response?.data?.errors?.policy_accepted?.[0] || 'The booking could not be confirmed.'; }
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
            <div class="text-center"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--brand-pine)]">Book with confidence</p><h1 class="gh-display mt-2 text-4xl text-[var(--text-strong)] sm:text-5xl">Use your business’s booking link</h1><p class="mx-auto mt-3 max-w-xl leading-7 text-[var(--text-muted)]">Open the private booking address shared by your barber, salon, spa, or independent professional.</p></div>
            <SurfaceCard class="mt-8"><StatePanel tone="info" title="Looking for an appointment?" description="Ask the business for its Good Hours booking link. We do not publish a marketplace or expose client and staff contact data." /></SurfaceCard>
        </template>
        <template v-else>
            <header><p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--brand-pine)]">{{ business.name }}</p><h1 class="gh-display mt-2 text-4xl leading-tight text-[var(--text-strong)]">Book an appointment</h1><p class="mt-2 text-sm text-[var(--text-muted)]">Times are shown in the selected location’s local time. No account or app needed.</p></header>
            <p v-if="error" class="mt-5 rounded-xl bg-[var(--status-info-soft)] p-4 text-sm text-[var(--text-strong)]" role="status">{{ error }}</p>

            <SurfaceCard v-if="step === 1" class="mt-6" title="Choose services" description="Add-ons stay visible as separate items so price and time are clear.">
                <FormField id="booking-location" label="Location" required><select id="booking-location" v-model="selection.location" class="min-h-11 w-full rounded-lg border px-3"><option v-for="location in catalog.locations" :key="location.public_id" :value="location.public_id">{{ location.name }}</option></select></FormField>
                <div class="mt-5 space-y-3"><button v-for="service in catalog.services.filter(item => item.location_ids.includes(selection.location))" :key="service.public_id" type="button" :aria-pressed="selection.services.includes(service.public_id)" :class="['flex min-h-16 w-full items-start justify-between gap-3 rounded-xl border p-4 text-left', selection.services.includes(service.public_id) ? 'border-[var(--brand-pine)] bg-[var(--status-success-soft)] ring-1 ring-[var(--brand-pine)]' : 'border-[var(--border-subtle)] bg-white']" @click="chooseService(service.public_id)"><span class="min-w-0 flex-1"><strong class="block">{{ service.kind === 'addon' ? 'Add-on · ' : '' }}{{ service.name }}</strong><span class="mt-1 block text-sm text-[var(--text-muted)]">{{ service.description || `${service.duration_minutes} minutes` }}</span></span><span class="flex shrink-0 items-center gap-2 font-semibold">{{ service.price_type === 'from' ? 'From ' : '' }}{{ money(service.price_minor) }}<CheckCircleIcon v-if="selection.services.includes(service.public_id)" class="size-5 text-[var(--status-success)]" aria-hidden="true" /></span></button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2"><FormField id="client-kind" label="I am"><select id="client-kind" v-model="selection.client_eligibility" class="min-h-11 w-full rounded-lg border px-3"><option value="new">A new client</option><option value="existing">A returning client</option></select></FormField><FormField id="booking-date" label="Preferred date" required><input id="booking-date" v-model="selection.date" type="date" class="min-h-11 w-full rounded-lg border px-3" /></FormField></div>
                <FormField v-if="catalog.policy.online_staff_preference !== 'any_only'" id="booking-staff" class="mt-4" label="Staff preference"><select id="booking-staff" v-model="selection.staff" class="min-h-11 w-full rounded-lg border px-3"><option value="">Any qualified professional</option><option v-for="member in eligibleStaff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}{{ member.title ? ` · ${member.title}` : '' }}</option></select></FormField>
                <AppButton class="mt-6 w-full" :disabled="busy || !selection.date || !selection.services.length" @click="search">Find available times</AppButton>
            </SurfaceCard>

            <SurfaceCard v-else-if="step === 2" class="mt-6" title="Choose a time" :description="`${chosenDateLabel} · ${slots.length} available ${slots.length === 1 ? 'time' : 'times'}`">
                <div v-if="slots.length" class="grid grid-cols-3 gap-3 sm:grid-cols-4"><button v-for="slot in slots" :key="slot.starts_at_utc" type="button" class="min-h-14 rounded-xl border border-[var(--border-strong)] bg-white p-3 text-base font-semibold hover:border-[var(--brand-pine)] hover:bg-[var(--status-success-soft)]" @click="hold(slot)">{{ localTime(slot.starts_at_local || slot.starts_at_utc) }}</button></div>
                <div v-else><StatePanel title="No safe times found" description="Try another date, choose first available, or join the waitlist. Private staff schedules are never shown." /><form class="mt-5 grid gap-3" @submit.prevent="joinWaitlist"><div class="grid gap-3 sm:grid-cols-2"><input v-model="waitlist.client_name" required placeholder="Name" aria-label="Waitlist name" class="min-h-11 rounded-lg border px-3" /><input v-model="waitlist.client_mobile" required placeholder="Mobile" aria-label="Waitlist mobile" class="min-h-11 rounded-lg border px-3" /><input v-model="waitlist.client_email" type="email" placeholder="Email (optional)" aria-label="Waitlist email" class="min-h-11 rounded-lg border px-3" /><select v-model="waitlist.notification_method" aria-label="Waitlist notification method" class="min-h-11 rounded-lg border px-3"><option value="email">Email</option><option value="whatsapp">WhatsApp</option></select><input v-model="waitlist.acceptable_from" required type="date" aria-label="Waitlist from date" class="min-h-11 rounded-lg border px-3" /><input v-model="waitlist.acceptable_until" required type="date" aria-label="Waitlist until date" class="min-h-11 rounded-lg border px-3" /></div><AppButton type="submit" variant="secondary">Join waitlist</AppButton></form></div>
                <p v-if="slots.length" class="mt-4 text-sm text-[var(--text-muted)]">Times use {{ catalog.locations.find(item => item.public_id === selection.location)?.time_zone.replaceAll('_', ' ') }} and are checked again when selected.</p><AppButton class="mt-5" variant="quiet" @click="moveToStep(1)">Back to choices</AppButton>
            </SurfaceCard>

            <SurfaceCard v-else-if="step === 3" class="mt-6" title="Your details" :description="`Held until ${localDate(held.hold_expires_at)}`">
                <div class="grid gap-4 sm:grid-cols-2"><FormField id="client-name" label="Name" required><input id="client-name" v-model="details.client_name" required autocomplete="name" class="gh-input" /></FormField><FormField id="client-mobile" label="Mobile" required hint="Include your country code so the business can reach you."><input id="client-mobile" v-model="details.client_mobile" required inputmode="tel" autocomplete="tel" class="gh-input" /></FormField><FormField id="client-email" label="Email" required><input id="client-email" v-model="details.client_email" required type="email" autocomplete="email" class="gh-input" /></FormField><FormField id="client-dob" label="Date of birth (optional)"><input id="client-dob" v-model="details.client_date_of_birth" type="date" class="gh-input" /></FormField></div>
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
        </template>
    </PublicBookingLayout>
</template>
