<script setup>
import { inject as injectRoute } from 'vue';
const route = injectRoute('route');
import { retainBranchServices } from '@/Support/locationIntelligence';
import LocationCard from '@/Components/Locations/LocationCard.vue';
import BranchPicker from '@/Components/Locations/BranchPicker.vue';
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { ArrowLeftIcon, ArrowRightIcon, CalendarDaysIcon, CheckCircleIcon, ClockIcon, MapPinIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { effectiveCatalogPreview, durationLabel } from '@/Support/serviceCatalog';
import { shiftDate, slotGroups, eligibleService, safeRead, safeWrite } from '@/Support/publicBooking';
import AppButton from '@/Components/Product/AppButton.vue';
import FormField from '@/Components/Product/FormField.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import BookingSkeleton from '@/Components/Booking/BookingSkeleton.vue';
import PublicBookingLayout from '@/Layouts/PublicBookingLayout.vue';

const props = defineProps({ business: Object, catalog: Object, preview: Boolean });
const step = ref(props.business ? 1 : 0);
const busy = ref(false), loadingSlots = ref(false), restoring = ref(false), error = ref(''), notice = ref(''), fieldErrors = ref({});
const flow = ref(null), secret = ref(null), expiresAt = ref(null), held = ref(null), confirmation = ref(null);
const selection = ref({ location: props.catalog?.locations?.[0]?.public_id || '', services: [], staff: '', date: props.catalog?.locations?.[0]?.today || '', client_eligibility: 'auto' });
const searchText = ref(''), category = ref('All'), slots = ref([]), waitlistOpen = ref(false), waitlistSaved = ref(false);
const detailsChecked = ref(false);
const details = ref({ client_name: '', client_mobile: '', client_email: '', client_date_of_birth: '', referral_source: '', special_request: '', communication_preferences: ['email'], marketing_opt_in: false, policy_accepted: false });
const waitlist = ref({ client_name: '', client_mobile: '', client_email: '', acceptable_from: '', acceptable_until: '', time_from: '09:00', time_until: '18:00', notification_method: 'email', notes: '' });
const location = computed(() => props.catalog?.locations.find(item => item.public_id === selection.value.location));
const zone = computed(() => location.value?.time_zone || 'UTC');
const selectedServices = computed(() => selection.value.services.map(id => props.catalog?.services.find(item => item.public_id === id)).filter(Boolean));
const currency = computed(() => selectedServices.value[0]?.currency_code || props.business?.currency_code || 'USD');
const today = computed(() => location.value?.today || new Date().toISOString().slice(0, 10));
const lastDate = computed(() => shiftDate(today.value, Math.min(...selectedServices.value.map(item => item.maximum_advance_days || 60), 730)));
const dateLabel = value => new Intl.DateTimeFormat(undefined, { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));
const timeLabel = value => new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', timeZone: zone.value, timeZoneName: 'short' }).format(new Date(value));
const fullTime = value => new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'short', timeZone: zone.value }).format(new Date(value));
const money = (minor, code = currency.value) => new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format((minor || 0) / 100);
const eligibleStaff = computed(() => (props.catalog?.staff || []).filter(member => member.location_ids.includes(selection.value.location) && selection.value.services.every(id => member.service_ids.includes(id))));
const availableServices = computed(() => (props.catalog?.services || []).filter(item => ['new', 'existing'].some(kind => eligibleService(item, kind, props.catalog.policy)) && item.location_ids.includes(selection.value.location) && props.catalog.staff.some(member => member.location_ids.includes(selection.value.location) && member.service_ids.includes(item.public_id))));
const allowedAddon = item => item.kind !== 'addon' || selectedServices.value.some(parent => parent.addon_ids?.includes(item.public_id));
const categories = computed(() => ['All', ...new Set(availableServices.value.filter(allowedAddon).map(item => item.category))]);
const visibleServices = computed(() => availableServices.value.filter(item => allowedAddon(item) && (category.value === 'All' || item.category === category.value) && `${item.name} ${item.category} ${item.description || ''}`.toLowerCase().includes(searchText.value.trim().toLowerCase())));
const estimate = item => effectiveCatalogPreview(item, props.catalog?.staff.find(member => member.public_id === selection.value.staff)?.service_variants?.find(v => v.service === item.public_id), item.location_prices?.find(price => price.location === selection.value.location)?.price_minor);
const priceLabel = item => {
    if (selection.value.staff) return `${item.price_type === 'from' ? 'From ' : ''}${money(estimate(item).price_minor, item.currency_code)}`;
    const amounts = props.catalog.staff.filter(member => member.location_ids.includes(selection.value.location) && member.service_ids.includes(item.public_id)).map(member => effectiveCatalogPreview(item, member.service_variants?.find(v => v.service === item.public_id), item.location_prices?.find(price => price.location === selection.value.location)?.price_minor).price_minor);
    const low = amounts.length ? Math.min(...amounts) : item.price_minor;
    return `${item.price_type === 'from' || amounts.some(amount => amount !== low) ? 'From ' : ''}${money(low, item.currency_code)}`;
};
const duration = computed(() => selectedServices.value.reduce((sum, item) => sum + estimate(item).visit_minutes, 0));
const canContinue = computed(() => !props.preview && selection.value.services.length > 0 && !busy.value && !restoring.value);
const needsStaff = computed(() => props.catalog?.policy.online_staff_preference === 'preferred_required');
const canSearch = computed(() => canContinue.value && selection.value.date >= today.value && selection.value.date <= lastDate.value && (!needsStaff.value || selection.value.staff));
const groups = computed(() => slotGroups(slots.value, zone.value));
const period = ref('Morning');
const visibleGroup = computed(() => groups.value.find(group => group.label === period.value) || groups.value[0]);
const timeFormatter = computed(() => new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', timeZone: zone.value }));
const slotLabels = computed(() => {
    const labels = new Map(slots.value.map(slot => [slot.starts_at_utc, timeFormatter.value.format(new Date(slot.starts_at_utc))]));
    const counts = new Map();
    for (const label of labels.values()) counts.set(label, (counts.get(label) || 0) + 1);
    for (const [start, label] of labels) if (counts.get(label) > 1) labels.set(start, timeLabel(start));
    return labels;
});
const slotLabel = slot => slotLabels.value.get(slot.starts_at_utc);
const dateChoices = computed(() => Array.from({ length: 7 }, (_, index) => shiftDate(selection.value.date || today.value, index)).filter(date => date <= lastDate.value));
const storageKey = computed(() => `clipperdesk-booking-${props.business?.booking_slug}`);
const api = name => route(name, props.business.booking_slug, false);
const credentials = () => ({ flow: flow.value, secret: secret.value });
const remember = () => safeWrite(storageKey.value, { ...credentials(), expires_at: expiresAt.value, confirmed: !!confirmation.value, selection: selection.value }, sessionStorage);
const go = async value => { step.value = value; error.value = ''; fieldErrors.value = {}; await nextTick(); document.querySelector('#booking-heading')?.focus(); window.scrollTo({ top: 0 }); };
const reportError = async (requestError, fallback) => {
    fieldErrors.value = requestError.response?.data?.errors || {};
    error.value = fieldErrors.value.booking?.[0] || fieldErrors.value.services?.[0] || (Object.keys(fieldErrors.value).length ? 'Check the highlighted fields.' : requestError.response?.status === 429 ? 'Please wait a moment, then try again.' : requestError.response?.data?.message || fallback);
    await nextTick(); document.querySelector('[aria-invalid="true"]')?.focus();
};
let startingFlow;
const ensureFlow = async () => {
    if (props.preview) return;
    if (flow.value && Date.parse(expiresAt.value) > Date.now() + 5000) return;
    if (!startingFlow) startingFlow = axios.post(api('public.booking.start')).then(response => {
        flow.value = response.data.flow; secret.value = response.data.secret; expiresAt.value = response.data.expires_at; held.value = null; remember();
    }).finally(() => { startingFlow = null; });
    await startingFlow;
};
const restore = async () => {
    if (props.preview) return;
    const saved = safeRead(storageKey.value, sessionStorage);
    if (!saved?.flow || !saved.secret) return;
    if (saved.selection) {
        selection.value = { ...selection.value, ...saved.selection, client_eligibility: 'auto' };
        if (!props.catalog.locations.some(item => item.public_id === selection.value.location)) selection.value.location = props.catalog.locations[0]?.public_id || '';
        pruneServices();
    }
    if (!saved.confirmed && Date.parse(saved.expires_at) <= Date.now()) return;
    restoring.value = true;
    try {
        flow.value = saved.flow; secret.value = saved.secret;
        const { data } = await axios.post(api('public.booking.resume'), credentials());
        expiresAt.value = data.expires_at || saved.expires_at;
        if (data.status === 'confirmed') { confirmation.value = data.confirmation; await go(4); }
        else if (data.status === 'held') {
            selection.value = { ...selection.value, location: data.state.location, services: data.state.services, staff: data.state.staff || '', client_eligibility: 'auto', date: new Intl.DateTimeFormat('en-CA', { timeZone: props.catalog.locations.find(item => item.public_id === data.state.location)?.time_zone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(data.state.starts_at)) };
            held.value = { policy: data.state.policy, state: data.state, hold_expires_at: data.expires_at }; await go(3);
            notice.value = 'Your time is still reserved. Enter your details to finish.';
        }
    } catch (requestError) {
        flow.value = null; secret.value = null; safeWrite(storageKey.value, null, sessionStorage);
        if (!requestError.response || requestError.response.status >= 500) error.value = 'We could not restore your booking. Please try again.';
    } finally { restoring.value = false; }
};
const pruneServices = () => {
    selection.value.services = retainBranchServices(selection.value.services, availableServices.value);
    if (!eligibleStaff.value.some(member => member.public_id === selection.value.staff)) selection.value.staff = '';
    if (!categories.value.includes(category.value)) category.value = 'All';
    if (selection.value.date < today.value || selection.value.date > lastDate.value) selection.value.date = today.value;
};
const chooseService = id => {
    const next = selection.value.services.includes(id) ? selection.value.services.filter(value => value !== id) : [...selection.value.services, id];
    const services = next.map(value => props.catalog.services.find(item => item.public_id === value));
    if (!['new', 'existing'].some(kind => services.every(item => eligibleService(item, kind, props.catalog.policy)))) {
        notice.value = 'First-visit and returning-customer services need separate appointments.';
        return;
    }
    if (needsStaff.value && !props.catalog.staff.some(member => member.location_ids.includes(selection.value.location) && next.every(value => member.service_ids.includes(value)))) {
        notice.value = 'These services need different professionals. Book separately or choose fewer services.';
        return;
    }
    notice.value = ''; selection.value.services = next; pruneServices();
};
let searchController, searchVersion = 0, searchTimer;
const search = async () => {
    searchController?.abort(); const version = ++searchVersion;
    slots.value = []; error.value = ''; waitlistOpen.value = false; waitlistSaved.value = false;
    if (!canSearch.value) { loadingSlots.value = false; return; }
    loadingSlots.value = true;
    searchController = new AbortController();
    try {
        await ensureFlow();
        const { data } = await axios.post(api('public.booking.search'), { ...credentials(), ...selection.value, staff: selection.value.staff || null, from_date: selection.value.date, until_date: selection.value.date }, { signal: searchController.signal });
        if (version === searchVersion) { slots.value = data.slots; remember(); }
    } catch (requestError) {
        if (requestError.code !== 'ERR_CANCELED' && version === searchVersion) {
            if (requestError.response?.data?.code === 'BOOKING_FLOW_EXPIRED') { flow.value = null; return search(); }
            await reportError(requestError, 'Times could not load. Try again.');
        }
    } finally { if (version === searchVersion) loadingSlots.value = false; }
};
const continueToTimes = async () => { if (!canContinue.value) return; await go(2); await search(); };
watch(() => selection.value.location, () => {
    const before = selection.value.services.length;
    searchController?.abort(); ++searchVersion; slots.value = []; selection.value.date = today.value;
    pruneServices();
    notice.value = before > selection.value.services.length ? 'Some selected services are unavailable at this branch. Your other services are kept.' : '';
});
watch(() => selection.value.client_eligibility, pruneServices);
watch(() => [selection.value.date, selection.value.staff], () => { if (step.value === 2 && !busy.value) { clearTimeout(searchTimer); slots.value = []; loadingSlots.value = true; searchTimer = setTimeout(search, 200); } });
const hold = async slot => {
    if (busy.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        const { data } = await axios.post(api('public.booking.hold'), { ...credentials(), ...selection.value, staff: selection.value.staff || null, starts_at: slot.starts_at_utc, idempotency_key: `hold-${flow.value}-${crypto.randomUUID()}` });
        detailsChecked.value = false; held.value = data; clock.value = Date.now(); expiresAt.value = data.hold_expires_at; details.value.policy_accepted = false; remember(); await go(3);
    } catch (requestError) { await reportError(requestError, 'That time was just taken. Choose another.'); slots.value = slots.value.filter(item => item.starts_at_utc !== slot.starts_at_utc); }
    finally { busy.value = false; }
};
const changeTime = async () => {
    if (busy.value || uncertainConfirm.value) return;
    busy.value = true;
    try { const { data } = await axios.post(api('public.booking.release'), credentials()); expiresAt.value = data.expires_at; held.value = null; remember(); await go(2); }
    catch (requestError) { if (requestError.response?.data?.code === 'BOOKING_FLOW_EXPIRED') { flow.value = null; held.value = null; await go(2); } else { await reportError(requestError, 'Could not change your time. Try again.'); return; } }
    finally { busy.value = false; }
    await search();
};
const clock = ref(Date.now()); let clockTimer;
const remaining = computed(() => Math.max(0, Math.ceil((Date.parse(held.value?.hold_expires_at) - clock.value) / 1000)));
const remainingLabel = computed(() => `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`);
const uncertainConfirm = ref(false); let confirmPayload;
const checkDetails = async () => {
    if (busy.value) return; busy.value = true; error.value = ''; notice.value = ''; fieldErrors.value = {};
    try { const { data } = await axios.post(api('public.booking.details'), { ...credentials(), client_name: details.value.client_name, client_mobile: details.value.client_mobile, client_email: details.value.client_email }); held.value.policy = data.policy; details.value.policy_accepted = false; detailsChecked.value = true; await nextTick(); document.querySelector('#booking-policy')?.focus(); }
    catch (requestError) { await reportError(requestError, 'Your details could not be checked. Try again.'); }
    finally { busy.value = false; }
};
const editDetails = () => { if (!busy.value && !uncertainConfirm.value) { detailsChecked.value = false; details.value.policy_accepted = false; confirmPayload = null; } };
const confirm = async () => {
    if (busy.value) return;
    busy.value = true; error.value = ''; fieldErrors.value = {}; notice.value = '';
    confirmPayload ||= { ...credentials(), ...details.value, communication_preferences: [...details.value.communication_preferences], idempotency_key: `confirm-${flow.value}` };
    try {
        const { data } = await axios.post(api('public.booking.confirm'), confirmPayload);
        confirmation.value = data; uncertainConfirm.value = false; await go(4); remember();
    } catch (requestError) {
        const code = requestError.response?.data?.code;
        if (code === 'DETAILS_REVIEW_REQUIRED') { detailsChecked.value = false; details.value.policy_accepted = false; confirmPayload = null; await reportError(requestError, 'Review your details again.'); }
        else if (['BOOKING_FLOW_EXPIRED', 'HOLD_EXPIRED', 'HOLD_NOT_FOUND', 'STALE_AVAILABILITY', 'POLICY_CHANGED'].includes(code)) {
            confirmPayload = null; uncertainConfirm.value = false; busy.value = false; await changeTime(); notice.value = code === 'POLICY_CHANGED' ? 'Booking policies changed. Choose a time to review the latest details.' : 'Your reserved time ended or changed. Please choose another.';
        } else {
            uncertainConfirm.value = !requestError.response || requestError.response.status >= 500;
            if (!uncertainConfirm.value) confirmPayload = null;
            await reportError(requestError, 'We could not check your confirmation. Retry to safely check the same booking.');
        }
    } finally { busy.value = false; }
};
const joinWaitlist = async () => {
    if (busy.value || waitlistSaved.value) return; busy.value = true; error.value = ''; fieldErrors.value = {};
    try { const { data } = await axios.post(api('public.waitlist.store'), { location: selection.value.location, service: selection.value.services[0], staff: selection.value.staff || null, ...waitlist.value }); notice.value = data.message; waitlistSaved.value = true; }
    catch (requestError) { await reportError(requestError, 'Your request could not be saved. Try again.'); }
    finally { busy.value = false; }
};
const openWaitlist = () => { waitlist.value.acceptable_from = selection.value.date; waitlist.value.acceptable_until = selection.value.date; waitlistOpen.value = true; };
onMounted(() => { if (props.preview) return; if (props.business) restore(); clockTimer = setInterval(() => { clock.value = Date.now(); }, 1000); });
onBeforeUnmount(() => { clearInterval(clockTimer); clearTimeout(searchTimer); searchController?.abort(); });
</script>

<template>
    <PublicBookingLayout
        :title="business ? `Book with ${business.name}` : 'Book an appointment'"
        :current-step="step"
        :preview="preview"
        :steps="['Services', 'Staff & time', 'Confirm', 'Booked']"
    >
        <section v-if="!business" class="booking-panel mx-auto max-w-lg">
            <h1 class="cd-page-title">Book an appointment</h1>
            <p class="mt-3 text-[var(--text-muted)]">Open the booking link shared by your business.</p>
        </section>
        <template v-else>
            <p v-if="preview" class="booking-message" role="status">Private preview · Browse your saved services. Booking is disabled here.</p>
            <header class="booking-business" :class="{ compact: step !== 1, 'booking-business-complete': step === 4 }">
                <img v-if="step === 1 && business.cover_url" :src="business.cover_url" :alt="business.cover_alt" class="booking-cover" />
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-[var(--text-muted)]">Book an appointment</p>
                    <h1 class="mt-1 text-xl font-bold tracking-tight text-[var(--text-strong)] sm:text-2xl">{{ business.name }}</h1>
                    <p v-if="step === 1 && business.description" class="mt-2 line-clamp-3 text-sm text-[var(--text-muted)]">{{ business.description }}</p>
                    <p class="mt-2 flex items-start gap-1.5 text-sm text-[var(--text-muted)]">
                        <MapPinIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {{ location?.name }}
                        <span v-if="location?.address" class="hidden sm:inline"> · {{ location.address }}</span>
                    </p>
                </div>
            </header>
            <p v-if="error" role="alert" class="booking-message danger">{{ error }}</p>
            <p v-if="notice" role="status" class="booking-message">{{ notice }}</p>
            <div class="booking-grid" :class="{ 'booking-complete-grid': step === 4 }">
                <section v-if="restoring" class="booking-panel"><BookingSkeleton label="Restoring your booking" /></section>
                <section v-else-if="step === 1" class="booking-panel">
                    <h2 id="booking-heading" tabindex="-1" class="cd-section-title">Choose your services</h2>
                    <p v-if="!catalog.locations.length" class="mt-3 text-sm text-[var(--text-muted)]">No services are ready for online booking yet. Review services, team assignments and opening hours in Business setup.</p>
                    <BranchPicker v-if="catalog.locations.length > 1" class="mt-5" :branches="catalog.locations" v-model="selection.location" />
                    <LocationCard v-if="location && catalog.locations.length === 1" class="mt-4 lg:hidden" :location="location" />
                    <details v-else-if="location" class="mt-3 rounded-lg border border-[var(--border-subtle)] p-3 lg:hidden"><summary class="cursor-pointer text-sm font-semibold">Arrival &amp; directions · {{ location.name }}</summary><LocationCard class="mt-3" :location="location" /></details>
                    <FormField v-if="catalog.services.length > 8" id="service-search" label="Find a service" class="mt-5">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-3 size-5 text-[var(--text-muted)]" aria-hidden="true" />
                            <input id="service-search" v-model="searchText" type="search" class="cd-input pl-10" placeholder="Search services" />
                        </div>
                    </FormField>
                    <div v-if="categories.length > 2" class="mt-4 flex flex-wrap gap-2" role="group" aria-label="Service categories">
                        <button v-for="item in categories" :key="item" class="booking-choice" :aria-pressed="category === item" @click="category = item">{{ item }}</button>
                    </div>
                    <div class="mt-4 divide-y divide-[var(--border-subtle)]">
                        <button
                            v-for="service in visibleServices"
                            :key="service.public_id"
                            class="booking-service"
                            :aria-pressed="selection.services.includes(service.public_id)"
                            @click="chooseService(service.public_id)"
                        >
                            <span class="booking-check" :class="{ selected: selection.services.includes(service.public_id) }">
                                <CheckCircleIcon v-if="selection.services.includes(service.public_id)" class="size-5" aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs text-[var(--text-muted)]">{{ service.kind === 'addon' ? 'Add-on' : service.category }}</span>
                                <strong class="mt-0.5 block">{{ service.name }}</strong>
                                <span v-if="service.description" class="mt-1 block text-sm text-[var(--text-muted)]">{{ service.description }}</span>
                                <span class="mt-2 block text-xs text-[var(--text-muted)]">
                                    {{ durationLabel(estimate(service).visit_minutes) }}
                                    <span v-if="service.client_eligibility === 'existing' || catalog.policy.online_new_client_rule === 'existing_only' || (catalog.policy.online_new_client_rule === 'consultation_only' && !service.consultation_required)">· Returning customers</span>
                                    <span v-else-if="service.client_eligibility === 'new'"> · First visits</span>
                                </span>
                            </span>
                            <strong class="booking-price">{{ priceLabel(service) }}</strong>
                        </button>
                    </div>
                    <p v-if="!visibleServices.length" class="py-8 text-sm text-[var(--text-muted)]">
                        {{ searchText ? 'No matching services. Try another search.' : 'No online services are available for these choices.' }}
                        <a v-if="business.phone && !searchText" :href="`tel:${business.phone}`" class="underline">Contact the business</a>
                    </p>
                    <div class="booking-action">
                        <span class="min-w-0 text-sm">
                            <strong>{{ selectedServices.length }} selected</strong>
                            <span class="block text-xs text-[var(--text-muted)]">{{ durationLabel(duration) }} estimated</span>
                        </span>
                        <AppButton :disabled="!canContinue" @click="continueToTimes">
                            Continue
                            <ArrowRightIcon class="size-4" aria-hidden="true" />
                        </AppButton>
                    </div>
                </section>
                <section v-else-if="step === 2" class="booking-panel">
                    <button class="booking-back" :disabled="busy" @click="go(1)">
                        <ArrowLeftIcon class="size-4" aria-hidden="true" />
                        Services
                    </button>
                    <h2 id="booking-heading" tabindex="-1" class="cd-section-title mt-2">Choose a time</h2>
                    <FormField
                        v-if="catalog.policy.online_staff_preference !== 'any_only'"
                        id="booking-staff"
                        label="Professional"
                        :required="needsStaff"
                        class="mt-5"
                    >
                        <select id="booking-staff" v-model="selection.staff" :disabled="busy" class="cd-input">
                            <option value="">{{ needsStaff ? 'Choose a professional' : 'Anyone available · More appointment options' }}</option>
                            <option v-for="member in eligibleStaff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}{{ member.title ? ` · ${member.title}` : '' }}</option>
                        </select>
                    </FormField>
                    <FormField id="booking-date" label="Date" class="mt-4">
                        <input id="booking-date" v-model="selection.date" type="date" :min="today" :max="lastDate" :disabled="busy" class="cd-input" />
                    </FormField>
                    <div class="booking-date-rail mt-3" role="group" aria-label="Nearby dates">
                        <button
                            v-for="date in dateChoices"
                            :key="date"
                            class="booking-choice"
                            :aria-pressed="selection.date === date"
                            :disabled="busy"
                            @click="selection.date = date"
                        >
                            {{ date === today ? 'Today' : dateLabel(date) }}
                        </button>
                    </div>
                    <p class="mt-4 text-xs text-[var(--text-muted)]">All times at the location · {{ zone.replaceAll('_', ' ') }}</p>
                    <BookingSkeleton v-if="loadingSlots" variant="times" label="Finding available times" class="mt-4" />
                    <div v-else aria-live="polite" aria-atomic="true" class="mt-4 text-sm text-[var(--text-muted)]">
                        <span v-if="loadingSlots" role="status">Finding available times…</span>
                        <span v-else-if="!canSearch">{{ needsStaff && !selection.staff ? 'Choose a professional to see times.' : 'Choose a date within the booking window.' }}</span>
                        <span v-else-if="slots.length">{{ slots.length }} available times</span>
                        <span v-else-if="!error">No times on {{ dateLabel(selection.date) }}.</span>
                    </div>
                    <div v-if="groups.length" class="mt-4 flex flex-wrap gap-2" role="group" aria-label="Time of day">
                        <button
                            v-for="group in groups"
                            :key="group.label"
                            class="booking-choice"
                            :aria-pressed="visibleGroup?.label === group.label"
                            :disabled="busy"
                            @click="period = group.label"
                        >
                            {{ group.label }} · {{ group.slots.length }}
                        </button>
                    </div>
                    <div v-if="!loadingSlots" class="mt-3 grid grid-cols-3 gap-2">
                        <button
                            v-for="slot in visibleGroup?.slots || []"
                            :key="slot.starts_at_utc"
                            class="booking-slot"
                            :disabled="busy || loadingSlots"
                            @click="hold(slot)"
                        >
                            {{ slotLabel(slot) }}
                        </button>
                    </div>
                    <p v-if="busy" role="status" class="mt-4 text-sm">Reserving your time…</p>
                    <div v-if="!loadingSlots && canSearch && !slots.length && !error" class="mt-5">
                        <AppButton variant="secondary" :disabled="selection.date >= lastDate" @click="selection.date = shiftDate(selection.date, 1)">Try next day</AppButton>
                        <button
                            v-if="selectedServices.length === 1 && selectedServices[0].kind !== 'addon' && !waitlistOpen"
                            class="booking-back ml-2"
                            @click="openWaitlist"
                        >
                            Join waitlist
                        </button>
                        <p v-if="selectedServices.length > 1" class="mt-3 text-sm text-[var(--text-muted)]">You can also go back and try fewer services.</p>
                    </div>
                    <AppButton v-if="error" class="mt-4" variant="secondary" :disabled="loadingSlots" @click="search">Retry times</AppButton>
                    <form v-if="waitlistOpen && !waitlistSaved" class="mt-6 border-t border-[var(--border-subtle)] pt-5" @submit.prevent="joinWaitlist">
                        <h3 class="font-semibold">Notify me about an opening</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <FormField id="waitlist-name" label="Name" required :error="fieldErrors.client_name?.[0]">
                                <input id="waitlist-name" v-model="waitlist.client_name" class="cd-input" autocomplete="name" />
                            </FormField>
                            <FormField id="waitlist-mobile" label="Mobile" required :error="fieldErrors.client_mobile?.[0]">
                                <PhoneInput id="waitlist-mobile" v-model="waitlist.client_mobile" :country="business.country_code || 'US'" />
                            </FormField>
                            <FormField id="waitlist-email" label="Email" :required="waitlist.notification_method === 'email'" :error="fieldErrors.client_email?.[0]">
                                <input id="waitlist-email" v-model="waitlist.client_email" type="email" class="cd-input" autocomplete="email" />
                            </FormField>
                            <FormField id="waitlist-method" label="Notify me by">
                                <select id="waitlist-method" v-model="waitlist.notification_method" class="cd-input">
                                    <option value="email">Email</option>
                                    <option value="sms">Text message</option>
                                </select>
                            </FormField>
                            <FormField id="waitlist-from" label="From date" required :error="fieldErrors.acceptable_from?.[0]">
                                <input id="waitlist-from" v-model="waitlist.acceptable_from" type="date" :min="today" class="cd-input" />
                            </FormField>
                            <FormField id="waitlist-until" label="Until date" required :error="fieldErrors.acceptable_until?.[0]">
                                <input id="waitlist-until" v-model="waitlist.acceptable_until" type="date" :min="waitlist.acceptable_from" class="cd-input" />
                            </FormField>
                            <FormField id="waitlist-time-from" label="Earliest time" required :error="fieldErrors.time_from?.[0]">
                                <input id="waitlist-time-from" v-model="waitlist.time_from" type="time" class="cd-input" />
                            </FormField>
                            <FormField id="waitlist-time-until" label="Latest time" required :error="fieldErrors.time_until?.[0]">
                                <input id="waitlist-time-until" v-model="waitlist.time_until" type="time" class="cd-input" />
                            </FormField>
                        </div>
                        <AppButton type="submit" :loading="busy" class="mt-5">Join waitlist</AppButton>
                    </form>
                </section>
                <section v-else-if="step === 3" class="booking-panel">
                    <button class="booking-back" :disabled="busy || uncertainConfirm" @click="changeTime">
                        <ArrowLeftIcon class="size-4" aria-hidden="true" />
                        Change time
                    </button>
                    <h2 id="booking-heading" tabindex="-1" class="cd-section-title mt-2">Confirm your appointment</h2>
                    <div class="booking-review mt-4">
                        <strong>{{ fullTime(held.state.starts_at) }}</strong>
                        <p class="mt-1 text-sm">{{ location?.name }} · {{ zone.replaceAll('_', ' ') }}</p>
                        <LocationCard v-if="held.policy.location_details" class="mt-3" :location="held.policy.location_details" />
                        <ul class="mt-4 space-y-3">
                            <li v-for="(service, index) in held.policy.services" :key="index" class="flex justify-between gap-3 text-sm">
                                <span class="min-w-0">
                                    {{ service.name }}
                                    <span class="block text-xs text-[var(--text-muted)]">
                                        {{ durationLabel(service.visit_minutes) }}
                                        <span v-if="service.staff_name"> · {{ service.staff_name }}</span>
                                    </span>
                                </span>
                                <strong class="shrink-0">{{ service.price_type === 'from' ? 'From ' : '' }}{{ money(service.price_minor, service.currency_code) }}</strong>
                            </li>
                        </ul>
                        <div class="mt-4 flex justify-between border-t border-[var(--border-subtle)] pt-3 text-sm">
                            <strong>Total{{ held.policy.services.some(item => item.price_type === 'from') ? ' from' : '' }}</strong>
                            <strong>{{ money(held.policy.services.reduce((sum, item) => sum + item.price_minor, 0)) }}</strong>
                        </div>
                    </div>
                    <p v-if="remaining > 0" class="mt-3 text-xs text-[var(--text-muted)]">Time reserved for {{ remainingLabel }}</p>
                    <p v-else class="booking-message" role="status">Your time reservation ended. Choose a time again.</p>
                    <form @submit.prevent="detailsChecked ? confirm() : checkDetails()">
                        <fieldset v-if="!detailsChecked" :disabled="busy || uncertainConfirm || !remaining" class="mt-5">
                            <legend class="text-sm font-semibold">Your details</legend>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <FormField id="client-name" label="Full name" required :error="fieldErrors.client_name?.[0]">
                                    <input id="client-name" v-model="details.client_name" maxlength="255" autocomplete="name" class="cd-input" />
                                </FormField>
                                <FormField id="client-mobile" label="Mobile" required :error="fieldErrors.client_mobile?.[0]">
                                    <PhoneInput id="client-mobile" v-model="details.client_mobile" :country="business.country_code || 'US'" />
                                </FormField>
                                <FormField id="client-email" label="Email" required :error="fieldErrors.client_email?.[0]" class="sm:col-span-2">
                                    <input id="client-email" v-model="details.client_email" type="email" maxlength="255" autocomplete="email" class="cd-input" />
                                </FormField>
                            </div>
                            <details class="mt-4">
                                <summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold">Add a request or other details (optional)</summary>
                                <div class="mt-2 grid gap-4">
                                    <FormField id="special-request" label="Special request" :error="fieldErrors.special_request?.[0]">
                                        <textarea id="special-request" v-model="details.special_request" maxlength="2000" rows="2" class="cd-input" />
                                    </FormField>
                                    <FormField id="client-dob" label="Date of birth" :error="fieldErrors.client_date_of_birth?.[0]">
                                        <input id="client-dob" v-model="details.client_date_of_birth" type="date" :max="shiftDate(today, -1)" autocomplete="bday" class="cd-input" />
                                    </FormField>
                                    <FormField id="client-referral" label="How did you hear about us?" :error="fieldErrors.referral_source?.[0]">
                                        <input id="client-referral" v-model="details.referral_source" maxlength="255" class="cd-input" />
                                    </FormField>
                                </div>
                                <label class="booking-check-label">
                                    <input v-model="details.marketing_opt_in" type="checkbox" />
                                    <span>{{ held.policy.marketing_wording }}</span>
                                </label>
                            </details>
                        </fieldset>
                        <div v-if="detailsChecked">
                            <p class="mt-5 text-sm">
                                <strong>{{ details.client_name }}</strong>
                                <span class="block text-[var(--text-muted)]">{{ details.client_email }} · {{ details.client_mobile }}</span>
                            </p>
                            <button type="button" class="booking-back" :disabled="busy || uncertainConfirm" @click="editDetails">Edit details</button>
                            <div id="booking-policy" tabindex="-1" class="mt-4 border-t border-[var(--border-subtle)] pt-4 text-sm">
                                <strong>Cancellation policy</strong>
                                <p class="mt-1 text-[var(--text-muted)]">{{ held.policy.cancellation_policy }}</p>
                                <p v-if="held.policy.cancellation_fee_minor > 0" class="mt-2 text-[var(--text-muted)]">Late cancellation fee: {{ money(held.policy.cancellation_fee_minor) }}.</p>
                                <p v-if="held.policy.no_show_fee_minor > 0" class="mt-1 text-[var(--text-muted)]">Missed appointment fee: {{ money(held.policy.no_show_fee_minor) }}.</p>
                                <p class="mt-2">
                                    <a :href="held.policy.terms_url" target="_blank" rel="noopener" class="underline">Terms</a>
                                    ·
                                    <a :href="held.policy.privacy_url" target="_blank" rel="noopener" class="underline">Privacy</a>
                                </p>
                            </div>
                            <template v-if="held.policy.deposit_status === 'payment_required'">
                                <p class="booking-message" role="status">A {{ money(held.policy.deposit.amount_minor) }} deposit is required. Contact the business to arrange this appointment. Online payment is not available yet.</p>
                                <AppButton v-if="business.phone" :href="`tel:${business.phone}`">Call {{ business.name }}</AppButton>
                                <p class="mt-3 text-sm text-[var(--text-muted)]">This appointment has not been confirmed.</p>
                            </template>
                            <fieldset v-else :disabled="busy || uncertainConfirm || !remaining">
                                <label class="booking-check-label">
                                    <input v-model="details.policy_accepted" type="checkbox" required />
                                    <span>I agree to the booking terms and cancellation policy, and have read the privacy notice.</span>
                                </label>
                                <p v-if="fieldErrors.policy_accepted" role="alert" class="text-sm text-[var(--status-danger)]">{{ fieldErrors.policy_accepted[0] }}</p>
                                <label class="booking-check-label">
                                    <input
                                        type="checkbox"
                                        :checked="details.communication_preferences.includes('sms')"
                                        @change="details.communication_preferences = $event.target.checked ? ['email', 'sms'] : ['email']"
                                    />
                                    <span>{{ held.policy.sms_wording }}</span>
                                </label>
                            </fieldset>
                        </div>
                        <p v-if="fieldErrors.booking" role="alert" class="booking-message danger">
                            {{ fieldErrors.booking[0] }}
                            <button type="button" class="booking-back underline" :disabled="busy" @click="changeTime">Change services or time</button>
                        </p>
                        <p v-if="uncertainConfirm" role="status" class="booking-message">Your confirmation could not be checked. Retry to safely check the same appointment.</p>
                        <AppButton
                            type="submit"
                            :loading="busy"
                            :disabled="(detailsChecked && !details.policy_accepted) || (!remaining && !uncertainConfirm)"
                            v-if="!detailsChecked || held.policy.deposit_status !== 'payment_required'"
                            class="mt-4 w-full"
                        >
                            {{ uncertainConfirm ? 'Check confirmation' : detailsChecked ? 'Confirm appointment' : 'Review appointment' }}
                        </AppButton>
                    </form>
                </section>
                <section v-else-if="confirmation" class="booking-panel booking-confirmed text-center">
                    <CheckCircleIcon class="mx-auto size-12 text-[var(--status-success)]" aria-hidden="true" />
                    <h2 id="booking-heading" tabindex="-1" class="mt-3 text-2xl font-bold tracking-tight text-[var(--text-strong)]">You’re booked</h2>
                    <p class="mt-2 text-sm text-[var(--text-muted)]">Reference {{ confirmation.reference }}</p>
                    <div class="booking-review mt-5">
                        <strong>{{ fullTime(confirmation.starts_at) }}</strong>
                        <LocationCard class="mt-4 text-left" :location="confirmation.location_details" />
                        <p class="text-sm text-[var(--text-muted)]">{{ confirmation.address }}</p>
                        <p class="mt-3 text-sm">{{ confirmation.services?.join(', ') }}</p>
                        <p v-if="confirmation.staff?.length" class="mt-1 text-sm text-[var(--text-muted)]">With {{ confirmation.staff.join(', ') }}</p>
                    </div>
                    <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                        <AppButton :href="confirmation.calendar_url" variant="secondary">
                            <CalendarDaysIcon class="size-5" aria-hidden="true" />
                            Add to calendar
                        </AppButton>
                        <AppButton :href="confirmation.view_url">Manage appointment</AppButton>
                    </div>
                    <p class="mt-4 text-sm text-[var(--text-muted)]">Keep your private link to view, change or cancel your appointment.</p>
                </section>
                <aside v-if="step < 4" class="booking-panel booking-aside" aria-label="Your appointment">
                    <h2 class="font-semibold">Your appointment</h2>
                    <LocationCard v-if="location" class="mt-3" :location="location" />
                    <ul class="mt-5 space-y-4">
                        <li v-for="service in selectedServices" :key="service.public_id" class="flex justify-between gap-3 text-sm">
                            <span>{{ service.name }}</span>
                            <strong class="shrink-0">{{ priceLabel(service) }}</strong>
                        </li>
                    </ul>
                    <p v-if="!selectedServices.length" class="mt-4 text-sm text-[var(--text-muted)]">Select a service to get started.</p>
                    <p
                        v-if="selectedServices.length"
                        class="mt-5 flex items-center gap-2 border-t border-[var(--border-subtle)] pt-4 text-sm text-[var(--text-muted)]"
                    >
                        <ClockIcon class="size-4" aria-hidden="true" />
                        {{ durationLabel(duration) }}
                        estimated
                    </p>
                    <p v-if="step === 2 && selection.staff" class="mt-3 text-sm">With {{ eligibleStaff.find(member => member.public_id === selection.staff)?.display_name }}</p>
                    <p v-if="step === 2 && !selection.staff" class="mt-3 text-sm text-[var(--text-muted)]">Anyone available</p>
                </aside>
            </div>
        </template>
    </PublicBookingLayout>
</template>

<style scoped>
.booking-date-rail {
    display: flex;
    gap: .5rem;
    overflow-x: auto;
    padding: 4px 3px 8px;
}
.booking-date-rail button {
    flex-shrink: 0;
}
.booking-business {
    display: flex;
    gap: 1.25rem;
    align-items: center;
    padding: 1rem 0 1.5rem;
}
.booking-cover {
    width: 8rem;
    height: 7rem;
    object-fit: cover;
    border-radius: 1rem;
}
.booking-business.compact {
    padding: .25rem 0 1rem;
}
.booking-grid {
    display: grid;
    gap: 1.25rem;
    align-items: start;
}
.booking-panel {
    min-width: 0;
    background: var(--surface-raised);
    border: 1px solid var(--border-subtle);
    border-radius: 1rem;
    padding: 1.25rem;
}
.booking-choice,.booking-slot {
    min-height: 44px;
    padding: .6rem .85rem;
    border: 1px solid var(--border-strong);
    border-radius: .6rem;
    font-size: .875rem;
    background: var(--surface-raised);
    text-align: center;
}
.booking-choice[aria-pressed=true] {
    background: var(--status-info-soft);
    border-color: var(--action-primary);
    color: var(--text-strong);
    box-shadow: inset 0 0 0 1px var(--action-primary);
}
.booking-slot:hover {
    border-color: var(--action-primary);
    background: var(--status-info-soft);
}
.booking-service {
    display: flex;
    width: 100%;
    gap: .75rem;
    align-items: flex-start;
    padding: 1rem 0;
    text-align: left;
}
.booking-check {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border: 1.5px solid var(--border-strong);
    border-radius: 50%;
    margin-top: 1rem;
}
.booking-check.selected {
    background: var(--action-primary);
    border-color: var(--action-primary);
    color: white;
}
.booking-price {
    font-size: .875rem;
    text-align: right;
    max-width: 7.5rem;
    padding-top: 1rem;
}
.booking-action {
    display: flex;
    gap: .75rem;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    bottom: 0;
    padding: 1rem 0 calc(.5rem + env(safe-area-inset-bottom));
    border-top: 1px solid var(--border-subtle);
    background: var(--surface-raised);
    z-index: 5;
}
.booking-back {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    min-height: 44px;
    font-size: .875rem;
    color: var(--text-muted);
}
.booking-review {
    padding: 1rem;
    border-radius: .75rem;
    background: var(--surface-subtle);
}
.booking-check-label {
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    padding: .75rem 0;
    min-height: 44px;
    font-size: .875rem;
}
.booking-check-label input {
    margin-top: .2rem;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    accent-color: var(--action-primary);
}
.booking-message {
    padding: 1rem;
    background: var(--status-info-soft);
    border-radius: .75rem;
    font-size: .875rem;
    margin: 1rem 0;
}
.booking-message.danger {
    background: var(--status-danger-soft);
}
.booking-aside {
    display: none;
}
button:focus-visible,a:focus-visible,summary:focus-visible {
    outline: 3px solid var(--action-primary);
    outline-offset: 3px;
}
button:disabled {
    opacity: .5;
    cursor: not-allowed;
}
#booking-heading:focus {
    outline: none;
}
@media(min-width:1024px) {
    .booking-grid {
        grid-template-columns: minmax(0,1fr) 20rem;
    }
    .booking-aside {
        display: block;
        position: sticky;
        top: 1rem;
    }
    .booking-panel {
        padding: 1.5rem;
    }
}
@media(max-width:359px) {
    .booking-cover {
        width: 4rem;
        height: 5rem;
    }
    .booking-panel {
        padding: 1rem;
    }
    .booking-price {
        max-width: 5rem;
    }
    .booking-service {
        gap: .5rem;
    }
    .booking-action {
        flex-wrap: wrap;
    }
}
.booking-business-complete {
    justify-content: center;
    text-align: center;
}
.booking-business-complete p {
    justify-content: center;
}
.booking-grid.booking-complete-grid {
    grid-template-columns: minmax(0,1fr);
    max-width: 42rem;
    margin-inline: auto;
}
.booking-confirmed {
    padding: 2rem 1.25rem;
}
@media(min-width:640px) {
    .booking-confirmed {
        padding: 2.5rem;
    }
}
</style>
