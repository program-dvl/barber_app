<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircleIcon, ChevronRightIcon, ClockIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    business: Object,
    onboarding: Object,
    readiness: Object,
    locations: Array,
    services: Array,
    staff: Array,
    imports: Array,
    steps: Array,
    referenceData: Object,
});

const form = useForm({
    name: props.business.name || '', booking_slug: props.business.booking_slug || '', business_type: props.business.business_type || '',
    country_code: props.business.country_code || '', locale: props.business.locale || '', currency_code: props.business.currency_code || '',
    time_zone: props.business.time_zone || '', week_starts_on: props.business.week_starts_on ?? 1,
    appointment_interval_minutes: props.business.appointment_interval_minutes ?? 15, tax_posture: props.business.tax_posture || '',
    default_tax_rate_bps: props.business.default_tax_rate_bps ?? 0,
    phone: props.business.phone || '', email: props.business.email || '', website_url: props.business.website_url || '', social_links: props.business.social_links || {},
    address: props.business.address || '', map_url: props.business.map_url || '', default_cancellation_policy: props.business.default_cancellation_policy || '',
    terms_url: props.business.terms_url || '', privacy_url: props.business.privacy_url || '',
});
const publicPolicyForm = useForm({
    online_booking_enabled: props.business.online_booking_enabled ?? true,
    online_staff_preference: props.business.online_staff_preference || 'any_or_preferred',
    online_price_display: props.business.online_price_display || 'service_setting',
    online_new_client_rule: props.business.online_new_client_rule || 'allow',
    staff_gender_request_enabled: props.business.staff_gender_request_enabled ?? false,
    cancellation_cutoff_minutes: props.business.cancellation_cutoff_minutes ?? 1440,
    waitlist_offer_batch_size: props.business.waitlist_offer_batch_size ?? 1,
    public_link_ttl_minutes: props.business.public_link_ttl_minutes ?? 10080,
});
const importType = ref('clients');
const importCsv = ref('');
const importFileName = ref('');
const importHeaders = ref([]);
const importMapping = ref({});
const importResult = ref(null);
const importBusy = ref(false);
const importError = ref('');
const duplicateResolutions = ref({});
const importFields = {
    clients: ['external_id', 'name', 'email', 'mobile'],
    staff: ['external_id', 'display_name', 'email', 'mobile', 'title'],
    services: ['external_id', 'name', 'price_minor', 'duration_minutes', 'currency_code'],
    products: ['external_id', 'name', 'sku', 'price_minor'],
};
const sections = [
    { id: 'overview', label: 'Setup overview' },
    { id: 'business_details', label: 'Business profile' },
    { id: 'hours', label: 'Bookable foundation' },
    { id: 'booking_rules', label: 'Booking experience' },
    { id: 'import', label: 'Import clients' },
    { id: 'preview', label: 'Review & launch' },
];
const sectionForStep = step => ({ services: 'hours', staff: 'hours', staff_availability: 'hours', publish: 'preview' }[step] || step || 'overview');
const requestedSection = new URLSearchParams(window.location.search).get('section');
const activeSection = ref(sections.some(section => section.id === requestedSection) ? requestedSection : (props.business.configuration_published_at ? 'overview' : sectionForStep(props.onboarding.current_step)));
const blockerCodes = computed(() => new Set((props.readiness.blockers || []).map(item => item.code)));
const setupItems = computed(() => [
    { id: 'profile', title: 'Business profile', description: 'Identity, contact details, region, currency and tax treatment.', section: 'business_details', ready: ![...blockerCodes.value].some(code => code.startsWith('profile.')) },
    { id: 'location', title: 'Location & opening hours', description: 'Confirm where clients visit and when the salon is open.', section: 'hours', href: route('business.locations.index', props.business.public_id), ready: ![...blockerCodes.value].some(code => code.startsWith('locations.')) },
    { id: 'services', title: 'Services', description: 'Publish at least one priced service with a valid delivery path.', section: 'hours', href: route('business.services.index', props.business.public_id), ready: ![...blockerCodes.value].some(code => code.startsWith('services.')) },
    { id: 'staff', title: 'Team & availability', description: 'Connect the people delivering services to locations and working hours.', section: 'hours', href: route('business.team.index', props.business.public_id), ready: ![...blockerCodes.value].some(code => code.startsWith('staff.')) },
    { id: 'booking', title: 'Booking experience', description: 'Choose online-booking, cancellation and secure-link rules.', section: 'booking_rules', ready: !blockerCodes.value.has('rules.appointment_interval') },
    { id: 'preview', title: 'Review & publish', description: 'Check the client experience on desktop and mobile before launch.', section: 'preview', ready: !blockerCodes.value.has('preview.required') },
]);
const readyCount = computed(() => setupItems.value.filter(item => item.ready).length);
const taxRatePercent = computed({
    get: () => Number(form.default_tax_rate_bps || 0) / 100,
    set: value => { form.default_tax_rate_bps = Math.round((Number(value) || 0) * 100); },
});
const selectSection = section => {
    activeSection.value = section;
    window.history.replaceState({}, '', `${window.location.pathname}?section=${section}`);
};
const openSetupItem = item => item.href ? router.visit(item.href) : selectSection(item.section);
watch(() => form.country_code, (country, previous) => {
    if (!country || country === previous) return;
    const defaults = props.referenceData.country_defaults?.[country] || {};
    const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (!props.services.length && defaults.currency) form.currency_code = defaults.currency;
    if ((defaults.time_zones || []).length) form.time_zone = defaults.time_zones.includes(browserTimeZone) ? browserTimeZone : defaults.time_zones[0];
    try {
        const language = new Intl.Locale(`und-${country}`).maximize().language;
        form.locale = `${language}-${country}`;
    } catch { form.locale = `en-${country}`; }
});
const save = () => form.patch(route('business.configuration.profile.update', props.business.public_id), { preserveScroll: true });
const savePublicPolicy = () => publicPolicyForm.patch(route('business.configuration.public-booking-policy.update', props.business.public_id), { preserveScroll: true });
const markPreviewed = () => router.post(route('business.configuration.preview', props.business.public_id), {}, { preserveScroll: true });
const publish = () => router.post(route('business.configuration.publish', props.business.public_id), {}, { preserveScroll: true });
const resetImportMapping = () => {
    importMapping.value = Object.fromEntries(importFields[importType.value].map(field => [field, importHeaders.value.includes(field) ? field : '']));
};
watch(importType, resetImportMapping);
const chooseImportFile = async event => {
    const file = event.target.files?.[0];
    if (!file) return;
    importFileName.value = file.name;
    importCsv.value = await file.text();
    importHeaders.value = (importCsv.value.split(/\r?\n/, 1)[0] || '').split(',').map(value => value.trim().replace(/^"|"$/g, ''));
    resetImportMapping();
    importResult.value = null;
};
const previewImport = async () => {
    importBusy.value = true;
    importError.value = '';
    try {
        const mapping = Object.fromEntries(Object.entries(importMapping.value).filter(([, header]) => header));
        const response = await axios.post(route('business.configuration.imports.preview', props.business.public_id), {
            entity_type: importType.value, idempotency_key: `${importType.value}-${Date.now()}-${importFileName.value}`,
            source_name: importFileName.value, csv: importCsv.value, mapping,
        });
        importResult.value = response.data;
    } catch (error) {
        importError.value = error.response?.data?.message || 'The import preview could not be created.';
    } finally {
        importBusy.value = false;
    }
};
const commitImport = async () => {
    importBusy.value = true;
    importError.value = '';
    try {
        const response = await axios.post(route('business.configuration.imports.commit', [props.business.public_id, importResult.value.public_id]), {
            duplicate_resolutions: duplicateResolutions.value,
        });
        importResult.value = response.data;
    } catch (error) {
        importError.value = error.response?.data?.message || 'Review every duplicate before starting the import.';
    } finally {
        importBusy.value = false;
    }
};
</script>

<template>
    <AppLayout title="Business setup" :business-label="business.name">
        <PageHeader eyebrow="Salon setup" title="Build a business clients can book with confidence" description="Work through one focused area at a time. Your profile, regional rules and booking choices become the shared source for scheduling, checkout, messages and receipts.">
            <template #actions>
                <AppButton v-if="business.configuration_published_at" :href="route('booking.business', business.booking_slug)" variant="secondary">View booking page</AppButton>
            </template>
        </PageHeader>

        <nav class="cd-section-nav mt-6" aria-label="Setup sections">
            <ol class="flex min-w-max">
                <li v-for="(section, index) in sections" :key="section.id">
                    <button type="button" class="cd-section-nav-item" :aria-current="activeSection === section.id ? 'step' : undefined" @click="selectSection(section.id)"><span :class="['grid size-6 place-items-center rounded-full text-xs', activeSection === section.id ? 'bg-[var(--brand-primary)] text-white' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']">{{ index + 1 }}</span>{{ section.label }}</button>
                </li>
            </ol>
        </nav>

        <section v-show="activeSection === 'overview'" class="mt-6 space-y-6" aria-labelledby="setup-overview-title">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
                <SurfaceCard>
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div><p class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--action-primary)]">Launch plan</p><h2 id="setup-overview-title" class="mt-2 text-2xl font-semibold text-[var(--text-strong)]">{{ readyCount }} of {{ setupItems.length }} required areas are ready</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--text-muted)]">Every item below explains what is missing and takes you directly to the right workflow. Optional brand and import improvements never block a valid launch.</p></div>
                        <span :class="['inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold', readiness.publishable ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]']"><CheckCircleIcon v-if="readiness.publishable" class="size-4" aria-hidden="true" /><ClockIcon v-else class="size-4" aria-hidden="true" />{{ readiness.publishable ? 'Ready to publish' : 'Setup in progress' }}</span>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <button v-for="item in setupItems" :key="item.id" type="button" class="group flex min-h-28 items-start gap-3 rounded-xl border border-[var(--border-subtle)] p-4 text-left transition-colors hover:border-[var(--action-primary)] hover:bg-[var(--surface-subtle)]" @click="openSetupItem(item)">
                            <span :class="['mt-0.5 grid size-8 shrink-0 place-items-center rounded-full', item.ready ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]']"><CheckCircleIcon v-if="item.ready" class="size-5" aria-hidden="true" /><span v-else class="text-xs font-bold">{{ setupItems.indexOf(item) + 1 }}</span></span>
                            <span class="min-w-0 flex-1"><strong class="block text-sm text-[var(--text-strong)]">{{ item.title }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ item.description }}</span></span><ChevronRightIcon class="mt-1 size-4 shrink-0 text-[var(--text-muted)] group-hover:text-[var(--action-primary)]" aria-hidden="true" />
                        </button>
                    </div>
                </SurfaceCard>
                <div class="space-y-5">
                    <SurfaceCard title="Next best action" :description="readiness.publishable ? 'Your booking configuration has no launch blockers.' : readiness.blockers[0]?.message">
                        <AppButton v-if="!readiness.publishable" class="w-full" @click="selectSection(sectionForStep(readiness.next_step))">Continue setup<ChevronRightIcon class="size-4" aria-hidden="true" /></AppButton>
                        <AppButton v-else class="w-full" @click="selectSection('preview')">Review and publish</AppButton>
                    </SurfaceCard>
                    <SurfaceCard v-if="readiness.improvements.length" title="Useful later" description="Optional improvements that can be completed after the core booking path works.">
                        <ul class="space-y-2 text-sm leading-6 text-[var(--text-muted)]"><li v-for="item in readiness.improvements" :key="item.code">{{ item.message }}</li></ul>
                    </SurfaceCard>
                </div>
            </div>
        </section>

        <div v-show="activeSection === 'business_details'" class="mt-6">
            <SurfaceCard id="business_details" title="Business profile" description="Public identity, regional formats, contact details, and the policies clients review before booking.">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <div class="sm:col-span-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Identity</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Used across the workspace, booking page, receipts and client messages.</p></div>
                    <label class="text-sm font-semibold">Business name<input v-model="form.name" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Booking link<span class="mt-2 flex min-h-11 items-center rounded-lg border border-[var(--border-strong)] bg-white pl-3 text-sm text-[var(--text-muted)]">{{ $page.props.brand.booking_host }}/<input v-model="form.booking_slug" required minlength="3" class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-[var(--text-strong)] focus:ring-0" /></span></label>
                    <label class="text-sm font-semibold">Business type<select v-model="form.business_type" required class="cd-input mt-2"><option value="" disabled>Choose a business type</option><option v-if="form.business_type && !referenceData.business_types[form.business_type]" :value="form.business_type">{{ form.business_type }}</option><option v-for="(label, value) in referenceData.business_types" :key="value" :value="value">{{ label }}</option></select></label>
                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Region & money</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Country suggests the phone code, currency, locale and a local time zone. You remain in control before saving.</p></div>
                    <label class="text-sm font-semibold">Country<select v-model="form.country_code" required class="cd-input mt-2"><option value="" disabled>Choose a country</option><option v-if="form.country_code && !referenceData.countries[form.country_code]" :value="form.country_code">{{ form.country_code }}</option><option v-for="(label, value) in referenceData.countries" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Language & region<select v-model="form.locale" required class="cd-input mt-2"><option value="" disabled>Choose a locale</option><option v-if="form.locale && !referenceData.locales[form.locale]" :value="form.locale">{{ form.locale }}</option><option v-for="(label, value) in referenceData.locales" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Currency<select v-model="form.currency_code" required class="cd-input mt-2"><option value="" disabled>Choose a currency</option><option v-if="form.currency_code && !referenceData.currencies[form.currency_code]" :value="form.currency_code">{{ form.currency_code }}</option><option v-for="(label, value) in referenceData.currencies" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Time zone<select v-model="form.time_zone" required class="cd-input mt-2"><option value="" disabled>Choose a time zone</option><option v-for="zone in referenceData.time_zones" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></select></label>
                    <label class="text-sm font-semibold">Week starts<select v-model="form.week_starts_on" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option :value="1">Monday</option><option :value="7">Sunday</option></select></label>
                    <label class="text-sm font-semibold">Appointment interval<select v-model="form.appointment_interval_minutes" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option v-for="value in [5,10,15,20,30,60]" :key="value" :value="value">{{ value }} minutes</option></select></label>
                    <label class="text-sm font-semibold">Tax posture<select v-model="form.tax_posture" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="" disabled>Choose</option><option value="inclusive">Prices include tax</option><option value="exclusive">Tax added at checkout</option><option value="not_registered">Not tax registered</option></select></label>
                    <label v-if="form.tax_posture !== 'not_registered'" class="text-sm font-semibold">Default tax rate<input v-model="taxRatePercent" type="number" min="0" max="100" step="0.01" inputmode="decimal" class="cd-input mt-2"><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Percent applied at checkout when a service or product has no more specific rate.</span></label>
                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Client-facing details</h3><p class="mt-1 text-xs text-[var(--text-muted)]">These details appear wherever clients need to identify or contact the salon.</p></div>
                    <label class="text-sm font-semibold">Public phone<PhoneInput id="business-phone" v-model="form.phone" class="mt-2" :country="form.country_code || 'IN'" :countries="referenceData.countries" required /><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Saved internationally and reused on booking pages and receipts.</span></label>
                    <label class="text-sm font-semibold">Email<input v-model="form.email" required type="email" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Address<textarea v-model="form.address" required rows="2" class="mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" /></label>
                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Booking policies</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Clients review these before confirming; past bookings keep their original policy snapshot.</p></div>
                    <label class="text-sm font-semibold sm:col-span-2">Cancellation policy<textarea v-model="form.default_cancellation_policy" required rows="3" class="mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" /></label>
                    <label class="text-sm font-semibold">Terms URL<input v-model="form.terms_url" required type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Privacy URL<input v-model="form.privacy_url" required type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Website (optional)<input v-model="form.website_url" type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Map URL (optional)<input v-model="form.map_url" type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <p v-if="Object.keys(form.errors).length" class="sm:col-span-2 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">Check the highlighted profile fields before saving.</p>
                    <div class="sm:col-span-2 flex justify-end"><AppButton type="submit" :disabled="form.processing">Save business details</AppButton></div>
                </form>
            </SurfaceCard>

        </div>

        <SurfaceCard v-show="activeSection === 'booking_rules'" id="booking_rules" class="mt-6" title="Public booking controls" description="Choose what clients can do online. Policy changes are shown again to anyone already booking.">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="savePublicPolicy">
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.online_booking_enabled" type="checkbox" />Accept online bookings</label>
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.staff_gender_request_enabled" type="checkbox" />Allow staff gender requests</label>
                <label class="text-sm font-semibold">Staff choice<select v-model="publicPolicyForm.online_staff_preference" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="any_or_preferred">Any or preferred</option><option value="any_only">First available only</option><option value="preferred_required">Client must choose</option></select></label>
                <label class="text-sm font-semibold">Price display<select v-model="publicPolicyForm.online_price_display" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="service_setting">Per service setting</option><option value="exact">Exact price</option><option value="from">From price</option></select></label>
                <label class="text-sm font-semibold">New clients<select v-model="publicPolicyForm.online_new_client_rule" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="allow">Allow</option><option value="consultation_only">Consultation only</option><option value="existing_only">Existing clients only</option></select></label>
                <label class="text-sm font-semibold">Cancellation cutoff<select v-model="publicPolicyForm.cancellation_cutoff_minutes" class="cd-input mt-2"><option :value="0">Any time</option><option :value="360">6 hours before</option><option :value="720">12 hours before</option><option :value="1440">24 hours before</option><option :value="2880">48 hours before</option><option :value="10080">7 days before</option></select></label>
                <label class="text-sm font-semibold">Waitlist offer batch<input v-model="publicPolicyForm.waitlist_offer_batch_size" type="number" min="1" max="10" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Secure link remains valid for<select v-model="publicPolicyForm.public_link_ttl_minutes" class="cd-input mt-2"><option :value="60">1 hour</option><option :value="1440">1 day</option><option :value="4320">3 days</option><option :value="10080">7 days</option><option :value="20160">14 days</option><option :value="43200">30 days</option></select></label>
                <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><AppButton type="submit" :disabled="publicPolicyForm.processing">Save booking rules</AppButton></div>
            </form>
        </SurfaceCard>

        <SurfaceCard v-show="activeSection === 'hours'" id="hours" class="mt-6" title="Build the bookable path" description="Complete these focused workflows in order. Each area becomes the long-term workspace for managing that part of your salon.">
            <ol class="grid gap-4 md:grid-cols-3">
                <li class="rounded-xl border border-[var(--border-subtle)] p-5"><span class="text-xs font-bold uppercase tracking-wide text-[var(--action-primary)]">Step 2</span><h3 class="mt-2 font-semibold text-[var(--text-strong)]">Location & hours</h3><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ locations.length }} location{{ locations.length === 1 ? '' : 's' }}. Confirm the public address, local time zone and normal opening week.</p><AppButton class="mt-4 w-full" :href="route('business.locations.index', business.public_id)" variant="secondary">Open locations</AppButton></li>
                <li class="rounded-xl border border-[var(--border-subtle)] p-5"><span class="text-xs font-bold uppercase tracking-wide text-[var(--action-primary)]">Step 3</span><h3 class="mt-2 font-semibold text-[var(--text-strong)]">Team & availability</h3><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ staff.length }} provider{{ staff.length === 1 ? '' : 's' }}. Add schedulable people without forcing them to have login access.</p><AppButton class="mt-4 w-full" :href="route('business.team.index', business.public_id)" variant="secondary">Open team</AppButton></li>
                <li class="rounded-xl border border-[var(--border-subtle)] p-5"><span class="text-xs font-bold uppercase tracking-wide text-[var(--action-primary)]">Step 4</span><h3 class="mt-2 font-semibold text-[var(--text-strong)]">Services</h3><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ services.length }} service{{ services.length === 1 ? '' : 's' }}. Connect price, duration, locations and qualified providers.</p><AppButton class="mt-4 w-full" :href="route('business.services.index', business.public_id)" variant="secondary">Open services</AppButton></li>
            </ol>
        </SurfaceCard>

        <SurfaceCard v-show="activeSection === 'import'" id="import" class="mt-6" title="Import existing records" description="Upload a CSV, map the columns, review invalid or duplicate rows, then start the import when the preview is clean.">
            <div class="grid gap-4 md:grid-cols-3">
                <label class="text-sm font-semibold">Record type<select v-model="importType" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="clients">Clients</option><option value="staff">Staff</option><option value="services">Services</option><option value="products">Products</option></select></label>
                <label class="text-sm font-semibold md:col-span-2">CSV file<input type="file" accept=".csv,text/csv" class="mt-2 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] p-2" @change="chooseImportFile" /></label>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-sm"><span class="font-semibold">Download a template:</span><a v-for="type in ['clients','staff','services','products']" :key="type" class="text-[var(--action-primary)] underline" :href="route('business.configuration.imports.template', [business.public_id, type])">{{ type }}</a></div>
            <fieldset v-if="importHeaders.length" class="mt-5"><legend class="font-semibold">Map columns</legend><div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label v-for="field in importFields[importType]" :key="field" class="text-sm font-semibold">{{ field.replaceAll('_', ' ') }}<select v-model="importMapping[field]" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="">Do not import</option><option v-for="header in importHeaders" :key="header" :value="header">{{ header }}</option></select></label></div></fieldset>
            <div class="mt-4"><AppButton variant="secondary" :disabled="!importCsv || importBusy" @click="previewImport">Validate and preview</AppButton></div>
            <p v-if="importError" class="mt-4 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ importError }}</p>
            <div v-if="importResult" class="mt-5 rounded-xl border border-[var(--border-subtle)] p-4">
                <p class="font-semibold">{{ importResult.status.replaceAll('_', ' ') }} · {{ importResult.total_rows }} rows</p>
                <p class="mt-1 text-sm text-[var(--text-muted)]">{{ importResult.failed_rows }} invalid · {{ importResult.duplicate_rows }} need duplicate review</p>
                <ul v-if="importResult.rows?.length" class="mt-4 max-h-80 space-y-3 overflow-y-auto">
                    <li v-for="row in importResult.rows" :key="row.id" class="rounded-lg bg-[var(--surface-subtle)] p-3 text-sm">
                        <p><strong>Row {{ row.row_number }}</strong> · {{ row.status.replaceAll('_', ' ') }}</p>
                        <p v-if="row.errors?.length" class="mt-1 text-[var(--status-danger)]">{{ row.errors.join('; ') }}</p>
                        <label v-if="row.status === 'duplicate_review'" class="mt-2 block font-semibold">Duplicate decision<select v-model="duplicateResolutions[row.id]" class="mt-1 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="" disabled>Choose</option><option value="update">Update matched record</option><option value="create">Create separately</option><option value="skip">Skip row</option></select></label>
                    </li>
                </ul>
                <AppButton v-if="importResult.status === 'previewed'" class="mt-4" :disabled="importBusy" @click="commitImport">Start import</AppButton>
            </div>
            <p class="mt-4 text-sm text-[var(--text-muted)]">{{ imports.length ? `${imports.length} recent import job(s).` : 'Import is optional for launch readiness.' }}</p>
        </SurfaceCard>

        <SurfaceCard v-show="activeSection === 'preview'" id="preview" class="mt-6" title="Preview & publish" description="Review exactly what clients will see on desktop and mobile before making the booking page available.">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-canvas)] p-5" aria-label="Desktop booking preview">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--text-muted)]">Desktop preview</p>
                    <div class="mt-4 flex items-start justify-between gap-4"><div><h3 class="text-xl font-bold text-[var(--text-strong)]">{{ business.name }}</h3><p class="mt-1 text-sm text-[var(--text-muted)]">{{ business.address || 'Add an address' }}</p></div><span class="rounded-full bg-[var(--brand-primary)] px-3 py-1 text-xs font-semibold text-white">Book</span></div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2"><article v-for="service in services.slice(0, 4)" :key="service.public_id" class="rounded-xl bg-[var(--surface-raised)] p-4"><p class="font-semibold text-[var(--text-strong)]">{{ service.name }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ service.duration_minutes + service.processing_minutes + service.cleanup_minutes }} minutes · {{ business.currency_code }} {{ (service.price_minor / 100).toFixed(2) }}</p></article><p v-if="!services.length" class="text-sm text-[var(--text-muted)]">Services will appear here.</p></div>
                </section>
                <section class="mx-auto w-full max-w-72 rounded-[2rem] border-4 border-[var(--text-strong)] bg-[var(--surface-canvas)] p-4 shadow-[var(--shadow-raised)]" aria-label="Mobile booking preview">
                    <p class="text-center text-xs font-semibold uppercase tracking-[0.12em] text-[var(--text-muted)]">Mobile preview</p><h3 class="mt-4 text-lg font-bold text-[var(--text-strong)]">{{ business.name }}</h3><p class="mt-1 text-xs text-[var(--text-muted)]">{{ locations[0]?.name || 'Your location' }} · {{ business.time_zone }}</p><div class="mt-4 space-y-2"><p v-for="service in services.slice(0, 2)" :key="service.public_id" class="rounded-lg bg-[var(--surface-raised)] p-3 text-sm font-semibold">{{ service.name }}</p><p v-if="!services.length" class="rounded-lg bg-[var(--surface-raised)] p-3 text-sm text-[var(--text-muted)]">Your first service</p></div>
                </section>
            </div>
            <div class="mt-5 flex flex-wrap gap-3">
                <AppButton variant="secondary" @click="markPreviewed">I reviewed mobile & desktop</AppButton>
                <AppButton :disabled="!readiness.publishable" @click="publish">Publish booking configuration</AppButton>
            </div>
            <p v-if="!readiness.publishable" class="mt-3 text-sm text-[var(--text-muted)]">Resolve the explicit blockers above before publishing.</p>
        </SurfaceCard>
    </AppLayout>
</template>
