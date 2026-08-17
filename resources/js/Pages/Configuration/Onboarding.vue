<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
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
    phone: props.business.phone || '', email: props.business.email || '', website_url: props.business.website_url || '', social_links: props.business.social_links || {},
    address: props.business.address || '', map_url: props.business.map_url || '', default_cancellation_policy: props.business.default_cancellation_policy || '',
    terms_url: props.business.terms_url || '', privacy_url: props.business.privacy_url || '',
});
const availabilityForm = useForm({
    location_name: '', location_address: '', time_zone: props.business.time_zone || '', working_days: [1, 2, 3, 4, 5, 6],
    opens_at: '09:00', closes_at: '18:00', staff_name: '', staff_email: '', staff_title: '', category_name: 'Services',
    service_name: '', price_minor: 0, duration_minutes: 30, processing_minutes: 0, cleanup_minutes: 5, tax_category: '',
    resource_name: '', resource_type: 'station', resource_quantity: 1, required_quantity: 1,
});
const priceAmount = ref((availabilityForm.price_minor || 0) / 100);
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
const labels = {
    business_details: 'Business details', hours: 'Hours & closures', services: 'Services & add-ons', staff: 'Staff',
    staff_availability: 'Availability', booking_rules: 'Booking rules', import: 'Import', preview: 'Preview', publish: 'Publish',
};
const currentIndex = computed(() => props.steps.indexOf(props.onboarding.current_step));
const activeSection = ref(props.onboarding.current_step || 'business_details');
const save = () => form.patch(route('business.configuration.profile.update', props.business.public_id), { preserveScroll: true });
const saveFirstPath = () => {
    availabilityForm.price_minor = Math.round((Number(priceAmount.value) || 0) * 100);
    availabilityForm.post(route('business.configuration.first-bookable-path.store', props.business.public_id), { preserveScroll: true });
};
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
        <PageHeader eyebrow="Business settings" title="Get ready to take bookings" description="Complete one focused section at a time. Good Hours saves each section separately, so you can leave and return safely.">
            <template #actions>
                <AppButton v-if="business.configuration_published_at" :href="route('booking.business', business.booking_slug)" variant="secondary">View booking page</AppButton>
            </template>
        </PageHeader>

        <nav class="gh-section-nav mt-6" aria-label="Setup sections">
            <ol class="flex min-w-max">
                <li v-for="(step, index) in steps" :key="step">
                    <button type="button" class="gh-section-nav-item" :aria-current="activeSection === step ? 'step' : undefined" @click="activeSection = step"><span :class="['grid size-6 place-items-center rounded-full text-xs', (onboarding.completed_steps || []).includes(step) ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']">{{ index + 1 }}</span>{{ labels[step] }}</button>
                </li>
            </ol>
        </nav>

        <div v-show="activeSection === 'business_details'" class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.7fr)]">
            <SurfaceCard id="business_details" title="Business profile" description="Public identity, regional formats, contact details, and the policies clients review before booking.">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <label class="text-sm font-semibold">Business name<input v-model="form.name" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Booking link<span class="mt-2 flex min-h-11 items-center rounded-lg border border-[var(--border-strong)] bg-white pl-3 text-sm text-[var(--text-muted)]">book.getgoodhours.com/<input v-model="form.booking_slug" required minlength="3" class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-[var(--text-strong)] focus:ring-0" /></span></label>
                    <label class="text-sm font-semibold">Business type<select v-model="form.business_type" required class="gh-input mt-2"><option value="" disabled>Choose a business type</option><option v-if="form.business_type && !referenceData.business_types[form.business_type]" :value="form.business_type">{{ form.business_type }}</option><option v-for="(label, value) in referenceData.business_types" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Country<select v-model="form.country_code" required class="gh-input mt-2"><option value="" disabled>Choose a country</option><option v-if="form.country_code && !referenceData.countries[form.country_code]" :value="form.country_code">{{ form.country_code }}</option><option v-for="(label, value) in referenceData.countries" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Language & region<select v-model="form.locale" required class="gh-input mt-2"><option value="" disabled>Choose a locale</option><option v-if="form.locale && !referenceData.locales[form.locale]" :value="form.locale">{{ form.locale }}</option><option v-for="(label, value) in referenceData.locales" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Currency<select v-model="form.currency_code" required class="gh-input mt-2"><option value="" disabled>Choose a currency</option><option v-if="form.currency_code && !referenceData.currencies[form.currency_code]" :value="form.currency_code">{{ form.currency_code }}</option><option v-for="(label, value) in referenceData.currencies" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Time zone<select v-model="form.time_zone" required class="gh-input mt-2"><option value="" disabled>Choose a time zone</option><option v-for="zone in referenceData.time_zones" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></select></label>
                    <label class="text-sm font-semibold">Week starts<select v-model="form.week_starts_on" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option :value="1">Monday</option><option :value="7">Sunday</option></select></label>
                    <label class="text-sm font-semibold">Appointment interval<select v-model="form.appointment_interval_minutes" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option v-for="value in [5,10,15,20,30,60]" :key="value" :value="value">{{ value }} minutes</option></select></label>
                    <label class="text-sm font-semibold">Tax posture<select v-model="form.tax_posture" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="" disabled>Choose</option><option value="inclusive">Prices include tax</option><option value="exclusive">Tax added</option><option value="not_registered">Not tax registered</option></select></label>
                    <label class="text-sm font-semibold">Phone<input v-model="form.phone" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Email<input v-model="form.email" required type="email" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Address<textarea v-model="form.address" required rows="2" class="mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Cancellation policy<textarea v-model="form.default_cancellation_policy" required rows="3" class="mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" /></label>
                    <label class="text-sm font-semibold">Terms URL<input v-model="form.terms_url" required type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Privacy URL<input v-model="form.privacy_url" required type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Website (optional)<input v-model="form.website_url" type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Map URL (optional)<input v-model="form.map_url" type="url" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <p v-if="Object.keys(form.errors).length" class="sm:col-span-2 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">Check the highlighted profile fields before saving.</p>
                    <div class="sm:col-span-2 flex justify-end"><AppButton type="submit" :disabled="form.processing">Save business details</AppButton></div>
                </form>
            </SurfaceCard>

            <aside class="space-y-6">
                <SurfaceCard title="Launch readiness" :description="readiness.publishable ? 'No blockers remain.' : `${readiness.blockers.length} blocker${readiness.blockers.length === 1 ? '' : 's'} remain.`">
                    <ul v-if="readiness.blockers.length" class="space-y-3">
                        <li v-for="item in readiness.blockers" :key="item.code" class="rounded-lg border border-[var(--status-danger)] bg-[var(--status-danger-soft)] p-3 text-sm"><strong class="block text-[var(--status-danger)]">Blocks publishing</strong>{{ item.message }}</li>
                    </ul>
                    <p v-else class="rounded-lg bg-[var(--status-success-soft)] p-3 text-sm font-semibold text-[var(--status-success)]">Ready to publish valid availability.</p>
                </SurfaceCard>
                <SurfaceCard title="Optional improvements">
                    <ul class="space-y-2 text-sm text-[var(--text-muted)]"><li v-for="item in readiness.improvements" :key="item.code">• {{ item.message }}</li></ul>
                </SurfaceCard>
            </aside>
        </div>

        <SurfaceCard v-show="activeSection === 'booking_rules'" id="booking_rules" class="mt-6" title="Public booking controls" description="Choose what clients can do online. Policy changes are shown again to anyone already booking.">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="savePublicPolicy">
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.online_booking_enabled" type="checkbox" />Accept online bookings</label>
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.staff_gender_request_enabled" type="checkbox" />Allow staff gender requests</label>
                <label class="text-sm font-semibold">Staff choice<select v-model="publicPolicyForm.online_staff_preference" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="any_or_preferred">Any or preferred</option><option value="any_only">First available only</option><option value="preferred_required">Client must choose</option></select></label>
                <label class="text-sm font-semibold">Price display<select v-model="publicPolicyForm.online_price_display" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="service_setting">Per service setting</option><option value="exact">Exact price</option><option value="from">From price</option></select></label>
                <label class="text-sm font-semibold">New clients<select v-model="publicPolicyForm.online_new_client_rule" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="allow">Allow</option><option value="consultation_only">Consultation only</option><option value="existing_only">Existing clients only</option></select></label>
                <label class="text-sm font-semibold">Cancellation cutoff<select v-model="publicPolicyForm.cancellation_cutoff_minutes" class="gh-input mt-2"><option :value="0">Any time</option><option :value="360">6 hours before</option><option :value="720">12 hours before</option><option :value="1440">24 hours before</option><option :value="2880">48 hours before</option><option :value="10080">7 days before</option></select></label>
                <label class="text-sm font-semibold">Waitlist offer batch<input v-model="publicPolicyForm.waitlist_offer_batch_size" type="number" min="1" max="10" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Secure link remains valid for<select v-model="publicPolicyForm.public_link_ttl_minutes" class="gh-input mt-2"><option :value="60">1 hour</option><option :value="1440">1 day</option><option :value="4320">3 days</option><option :value="10080">7 days</option><option :value="20160">14 days</option><option :value="43200">30 days</option></select></label>
                <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><AppButton type="submit" :disabled="publicPolicyForm.processing">Save booking rules</AppButton></div>
            </form>
        </SurfaceCard>

        <SurfaceCard v-show="['hours','services','staff','staff_availability'].includes(activeSection)" id="hours" class="mt-6" title="Create your first bookable service" description="Connect one location, staff member, service, and weekly schedule. You can add exceptions and advanced capacity rules later.">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="saveFirstPath">
                <label class="text-sm font-semibold">Location name<input v-model="availabilityForm.location_name" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold lg:col-span-2">Location address<input v-model="availabilityForm.location_address" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Time zone<select v-model="availabilityForm.time_zone" required class="gh-input mt-2"><option v-for="zone in referenceData.time_zones" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></select></label>
                <label class="text-sm font-semibold">Opens<input v-model="availabilityForm.opens_at" type="time" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Closes<input v-model="availabilityForm.closes_at" type="time" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <fieldset class="sm:col-span-2"><legend class="text-sm font-semibold">Working days</legend><div class="mt-2 flex flex-wrap gap-3"><label v-for="(day, index) in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="day" class="inline-flex min-h-11 items-center gap-2"><input v-model="availabilityForm.working_days" type="checkbox" :value="index + 1" />{{ day }}</label></div></fieldset>
                <label class="text-sm font-semibold">Staff name<input v-model="availabilityForm.staff_name" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Staff email<input v-model="availabilityForm.staff_email" type="email" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Staff title<input v-model="availabilityForm.staff_title" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Category<input v-model="availabilityForm.category_name" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Service name<input v-model="availabilityForm.service_name" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Price ({{ business.currency_code }})<input v-model="priceAmount" type="number" min="0" step="0.01" inputmode="decimal" required class="gh-input mt-2" /></label>
                <label class="text-sm font-semibold">Active minutes<input v-model="availabilityForm.duration_minutes" type="number" min="1" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Processing minutes<input v-model="availabilityForm.processing_minutes" type="number" min="0" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Cleanup minutes<input v-model="availabilityForm.cleanup_minutes" type="number" min="0" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Resource (optional)<input v-model="availabilityForm.resource_name" placeholder="Barber chairs" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Available quantity<input v-model="availabilityForm.resource_quantity" type="number" min="1" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Required per service<input v-model="availabilityForm.required_quantity" type="number" min="1" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <p v-if="Object.keys(availabilityForm.errors).length" class="sm:col-span-2 lg:col-span-4 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">Check the first-path details and your plan limits.</p>
                <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><AppButton type="submit" :disabled="availabilityForm.processing">Save bookable service</AppButton></div>
            </form>
        </SurfaceCard>

        <div v-show="['hours','services','staff','staff_availability'].includes(activeSection)" class="mt-6 grid gap-6 md:grid-cols-3">
            <SurfaceCard title="Locations & capacity"><p class="text-3xl font-bold text-[var(--brand-pine)]">{{ locations.length }}</p><p class="mt-2 text-sm text-[var(--text-muted)]">Locations with normal/special hours, holidays, closures, and reusable resources.</p></SurfaceCard>
            <SurfaceCard id="services" title="Services & add-ons"><p class="text-3xl font-bold text-[var(--brand-pine)]">{{ services.length }}</p><p class="mt-2 text-sm text-[var(--text-muted)]">Segmented duration, price, tax, deposit, eligibility, staff variants, and resource rules.</p></SurfaceCard>
            <SurfaceCard id="staff" title="Staff & schedules"><p class="text-3xl font-bold text-[var(--brand-pine)]">{{ staff.length }}</p><p class="mt-2 text-sm text-[var(--text-muted)]">Invitations, locations, split shifts, breaks, leave, and temporary changes.</p></SurfaceCard>
        </div>

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

        <SurfaceCard v-show="['preview','publish'].includes(activeSection)" id="preview" class="mt-6" title="Preview & publish" description="Review exactly what clients will see on desktop and mobile before making the booking page available.">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-canvas)] p-5" aria-label="Desktop booking preview">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--text-muted)]">Desktop preview</p>
                    <div class="mt-4 flex items-start justify-between gap-4"><div><h3 class="text-xl font-bold text-[var(--text-strong)]">{{ business.name }}</h3><p class="mt-1 text-sm text-[var(--text-muted)]">{{ business.address || 'Add an address' }}</p></div><span class="rounded-full bg-[var(--brand-pine)] px-3 py-1 text-xs font-semibold text-white">Book</span></div>
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
