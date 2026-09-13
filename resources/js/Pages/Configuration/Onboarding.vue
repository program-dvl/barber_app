<script setup>
import AppSelect from '@/Components/Product/AppSelect.vue';
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ChevronRightIcon,
    ClipboardDocumentIcon,
    ClockIcon,
    MapPinIcon,
    PaintBrushIcon,
    ScissorsIcon,
    SparklesIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
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
    onboardingCatalog: Object,
});

const guidedSteps = ['business_type', 'business_shape', 'location', 'starter_services'];
const guidedStep = ref(guidedSteps.includes(props.onboarding.current_step) ? props.onboarding.current_step : guidedSteps[0]);
const guidedBusy = ref(false);
const guidedErrors = ref({});
const guidedAnswers = props.onboarding.answers ?? {};
const initialBusinessType = guidedAnswers.business_type || props.business.business_type || '';
const initialCountry = guidedAnswers.country_code || props.business.country_code || 'IN';
const guidedForm = ref({
    business_type: initialBusinessType,
    operation_model: guidedAnswers.operation_model || 'at_location',
    team_size: guidedAnswers.team_size || '1',
    location_scale: guidedAnswers.location_scale || 'single',
    owner_bookable: guidedAnswers.owner_bookable ?? true,
    accepts_online_bookings: guidedAnswers.accepts_online_bookings ?? false,
    country_code: initialCountry,
    time_zone: guidedAnswers.time_zone || props.business.time_zone || '',
    address: guidedAnswers.address || props.business.address || '',
    phone: guidedAnswers.phone || props.business.phone || '',
    schedule_preset: guidedAnswers.schedule_preset || 'tuesday_saturday',
    service_keys: guidedAnswers.service_keys || (props.onboardingCatalog.business_types[initialBusinessType]?.services || []).map(item => item.key),
});
const guidedIndex = computed(() => guidedSteps.indexOf(guidedStep.value));
const guidedType = computed(() => props.onboardingCatalog.business_types[guidedForm.value.business_type] || null);
const guidedTypes = computed(() => Object.entries(props.onboardingCatalog.business_types).map(([key, value]) => ({ key, ...value })));
const starterServices = computed(() => guidedType.value?.services || []);
const selectBusinessType = key => {
    guidedForm.value.business_type = key;
    guidedForm.value.service_keys = props.onboardingCatalog.business_types[key].services.map(item => item.key);
};
const toggleStarterService = key => {
    guidedForm.value.service_keys = guidedForm.value.service_keys.includes(key)
        ? guidedForm.value.service_keys.filter(value => value !== key)
        : [...guidedForm.value.service_keys, key];
};
const guidedPayload = step => ({
    business_type: { step, business_type: guidedForm.value.business_type },
    business_shape: {
        step, operation_model: guidedForm.value.operation_model, team_size: guidedForm.value.team_size,
        location_scale: guidedForm.value.location_scale, owner_bookable: guidedForm.value.owner_bookable,
        accepts_online_bookings: guidedForm.value.accepts_online_bookings,
    },
    location: {
        step, country_code: guidedForm.value.country_code, time_zone: guidedForm.value.time_zone,
        address: guidedForm.value.address, phone: guidedForm.value.phone, schedule_preset: guidedForm.value.schedule_preset,
    },
    starter_services: { step, service_keys: guidedForm.value.service_keys },
}[step]);
const saveGuidedStep = () => {
    guidedBusy.value = true;
    guidedErrors.value = {};
    router.patch(route('business.configuration.guided-onboarding.update', props.business.public_id), guidedPayload(guidedStep.value), {
        preserveScroll: false,
        onSuccess: () => {
            guidedStep.value = guidedSteps[Math.min(guidedIndex.value + 1, guidedSteps.length - 1)];
            window.scrollTo({ top: 0, behavior: 'smooth' });
            window.setTimeout(() => document.querySelector('#main-content')?.focus({ preventScroll: true }), 250);
        },
        onError: errors => { guidedErrors.value = errors; },
        onFinish: () => { guidedBusy.value = false; },
    });
};
const completeGuidedOnboarding = () => {
    guidedBusy.value = true;
    guidedErrors.value = {};
    router.post(route('business.configuration.guided-onboarding.complete', props.business.public_id), {
        service_keys: guidedForm.value.service_keys,
    }, {
        onSuccess: () => {
            activeSection.value = 'overview';
            justPrepared.value = true;
            window.scrollTo({ top: 0, behavior: 'smooth' });
            window.setTimeout(() => document.querySelector('#setup-overview-title')?.focus({ preventScroll: true }), 250);
        },
        onError: errors => { guidedErrors.value = errors; },
        onFinish: () => { guidedBusy.value = false; },
    });
};
watch(() => guidedForm.value.country_code, country => {
    const zones = props.onboardingCatalog.country_defaults?.[country]?.time_zones || [];
    const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (!zones.includes(guidedForm.value.time_zone)) guidedForm.value.time_zone = zones.includes(browserTimeZone) ? browserTimeZone : (zones[0] || '');
}, { immediate: true });

const form = useForm({
    name: props.business.name || '', booking_slug: props.business.booking_slug || '', business_type: props.business.business_type || '',
    description: props.business.description || '', brand_color: props.business.brand_color || '#6D4AFF',
    country_code: props.business.country_code || '', locale: props.business.locale || '', currency_code: props.business.currency_code || '',
    time_zone: props.business.time_zone || '', week_starts_on: props.business.week_starts_on ?? 1,
    appointment_interval_minutes: props.business.appointment_interval_minutes ?? 15, tax_posture: props.business.tax_posture || '',
    default_tax_rate_bps: props.business.default_tax_rate_bps ?? 0,
    phone: props.business.phone || '', email: props.business.email || '', website_url: props.business.website_url || '', social_links: props.business.social_links || {},
    address: props.business.address || '', map_url: props.business.map_url || '', default_cancellation_policy: props.business.default_cancellation_policy || '',
    terms_url: props.business.terms_url || '', privacy_url: props.business.privacy_url || '',
});
const brandUpload = useForm({ kind: 'logo', asset: null });
const uploadBrandAsset = (kind, event) => {
    const asset = event.target.files?.[0];
    if (!asset) return;
    brandUpload.kind = kind;
    brandUpload.asset = asset;
    brandUpload.post(route('business.configuration.branding.store', props.business.public_id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => brandUpload.reset('asset'),
        onFinish: () => { event.target.value = ''; },
    });
};
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
const justPrepared = ref(new URLSearchParams(window.location.search).get('welcome') === '1');
const copiedBookingLink = ref(false);
const bookingUrl = computed(() => props.business.booking_slug ? route('booking.business', props.business.booking_slug) : '');
const preparedImage = computed(() => props.onboardingCatalog.business_types[props.business.business_type]?.image || '/images/marketing/editorial/product-workday.webp');
const copyBookingLink = async () => {
    await navigator.clipboard.writeText(bookingUrl.value);
    copiedBookingLink.value = true;
    window.setTimeout(() => { copiedBookingLink.value = false; }, 2200);
};
const activeSection = ref(sections.some(section => section.id === requestedSection)
    ? requestedSection
    : (justPrepared.value || props.business.configuration_published_at ? 'overview' : sectionForStep(props.onboarding.current_step)));
watch(() => props.onboarding.guided_completed_at, (completed, previous) => {
    if (!completed) return;

    const arrivedFromCompletion = new URLSearchParams(window.location.search).get('welcome') === '1' || !previous;
    const activeSectionExists = sections.some(section => section.id === activeSection.value);
    if (arrivedFromCompletion || !activeSectionExists) {
        activeSection.value = 'overview';
        justPrepared.value = arrivedFromCompletion;
    }
}, { flush: 'post' });
const blockerCodes = computed(() => new Set((props.readiness.blockers || []).map(item => item.code)));
const setupItems = computed(() => [
    { id: 'profile', title: 'Business profile', description: 'Identity, contact details, region, currency and tax treatment.', section: 'business_details', ready: ![...blockerCodes.value].some(code => code.startsWith('profile.')) },
    { id: 'location', title: 'Location & opening hours', description: 'Confirm where clients visit and when the business is open.', section: 'hours', href: route('business.locations.index', props.business.public_id), ready: ![...blockerCodes.value].some(code => code.startsWith('locations.')) },
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
        <div v-if="!onboarding.guided_completed_at" class="mx-auto max-w-6xl pb-10">
            <header class="relative overflow-hidden rounded-[2rem] bg-[var(--surface-inverse)] px-5 py-7 text-white shadow-[var(--shadow-raised)] sm:px-8 sm:py-9">
                <div class="absolute -right-12 -top-20 size-72 rounded-full bg-[var(--brand-secondary)]/30 blur-3xl" aria-hidden="true" />
                <div class="absolute bottom-0 left-1/3 size-44 rounded-full bg-[var(--brand-accent)]/15 blur-3xl" aria-hidden="true" />
                <div class="relative grid gap-7 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-end">
                    <div><p class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-white/60"><SparklesIcon class="size-4" aria-hidden="true" />Create your workspace</p><h1 class="cd-display mt-3 max-w-3xl text-[clamp(2.4rem,6vw,4.5rem)] font-semibold leading-[0.96] tracking-[-0.055em]">A working setup, shaped around you.</h1><p class="mt-4 max-w-2xl text-base leading-7 text-white/68">Four quick decisions. We’ll prepare your first location, hours, team profile, service menu and booking link—then you can change anything.</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/8 p-4 backdrop-blur"><div class="flex items-center justify-between text-xs font-semibold text-white/65"><span>About 3 minutes</span><span>{{ guidedIndex + 1 }} of {{ guidedSteps.length }}</span></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-white/15"><div class="h-full rounded-full bg-[var(--brand-accent)] transition-[width] duration-500" :style="{ width: `${((guidedIndex + 1) / guidedSteps.length) * 100}%` }" /></div><p class="mt-3 text-sm font-semibold">Your progress saves after every answer.</p></div>
                </div>
            </header>

            <section class="mt-6 overflow-hidden rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-[var(--shadow-raised)]" aria-live="polite">
                <div v-if="guidedStep === 'business_type'" class="p-5 sm:p-8">
                    <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Start with what you do</p><h2 class="cd-display mt-2 text-3xl font-semibold tracking-[-0.035em] text-[var(--text-strong)] sm:text-4xl">What kind of business are we preparing?</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">This only chooses your starter service menu and language. You can switch type or mix services later.</p></div>
                    <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <button v-for="type in guidedTypes" :key="type.key" type="button" :aria-pressed="guidedForm.business_type === type.key" :class="['group relative min-h-48 overflow-hidden rounded-2xl border text-left transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)] focus-visible:ring-offset-2', guidedForm.business_type === type.key ? 'border-[var(--brand-primary)] ring-2 ring-[var(--brand-primary)]' : 'border-[var(--border-subtle)] hover:-translate-y-0.5 hover:border-[var(--border-strong)] hover:shadow-lg']" @click="selectBusinessType(type.key)">
                            <img :src="type.image" :alt="`${type.label} workspace atmosphere`" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105" />
                            <span class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/25 to-transparent" aria-hidden="true" />
                            <span v-if="guidedForm.business_type === type.key" class="absolute right-3 top-3 grid size-8 place-items-center rounded-full bg-white text-[var(--brand-primary)]"><CheckCircleIcon class="size-5" aria-hidden="true" /></span>
                            <span class="absolute inset-x-0 bottom-0 p-4 text-white"><strong class="block text-lg">{{ type.label }}</strong><span class="mt-1 block text-xs leading-5 text-white/70">{{ type.description }}</span></span>
                        </button>
                    </div>
                </div>

                <div v-else-if="guidedStep === 'business_shape'" class="grid lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="p-5 sm:p-8"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Shape the workspace</p><h2 class="cd-display mt-2 text-3xl font-semibold tracking-[-0.035em] text-[var(--text-strong)] sm:text-4xl">How does {{ business.name }} work today?</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">A few operational choices help us avoid showing irrelevant setup.</p>
                        <fieldset class="mt-7"><legend class="font-semibold text-[var(--text-strong)]">Where appointments happen</legend><div class="mt-3 grid gap-3 sm:grid-cols-3"><label v-for="(option, key) in onboardingCatalog.operation_models" :key="key" :class="['cursor-pointer rounded-xl border p-4', guidedForm.operation_model === key ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)] ring-1 ring-[var(--brand-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.operation_model" class="ds-sr-only" type="radio" :value="key" /><MapPinIcon class="size-5 text-[var(--brand-primary)]" aria-hidden="true" /><strong class="mt-3 block text-sm">{{ option.label }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ option.description }}</span></label></div></fieldset>
                        <fieldset class="mt-7"><legend class="font-semibold text-[var(--text-strong)]">Team size</legend><div class="mt-3 flex flex-wrap gap-2"><label v-for="(label, key) in onboardingCatalog.team_sizes" :key="key" :class="['cursor-pointer rounded-full border px-4 py-2.5 text-sm font-semibold', guidedForm.team_size === key ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white' : 'border-[var(--border-subtle)] hover:border-[var(--brand-primary)]']"><input v-model="guidedForm.team_size" class="ds-sr-only" type="radio" :value="key" />{{ label }}</label></div></fieldset>
                        <div class="mt-7 grid gap-5 sm:grid-cols-2"><fieldset><legend class="font-semibold text-[var(--text-strong)]">Locations</legend><div class="mt-3 grid grid-cols-2 gap-2"><label v-for="(label, key) in onboardingCatalog.location_scales" :key="key" :class="['cursor-pointer rounded-xl border p-3 text-sm font-semibold', guidedForm.location_scale === key ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.location_scale" class="ds-sr-only" type="radio" :value="key" />{{ label }}</label></div></fieldset><fieldset><legend class="font-semibold text-[var(--text-strong)]">Do you take appointments yourself?</legend><div class="mt-3 grid grid-cols-2 gap-2"><label v-for="option in [{ value: true, label: 'Yes, add me' }, { value: false, label: 'No, owner only' }]" :key="String(option.value)" :class="['cursor-pointer rounded-xl border p-3 text-sm font-semibold', guidedForm.owner_bookable === option.value ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.owner_bookable" class="ds-sr-only" type="radio" :value="option.value" />{{ option.label }}</label></div></fieldset></div>
                        <label class="mt-6 flex min-h-14 cursor-pointer items-center justify-between gap-4 rounded-xl bg-[var(--surface-subtle)] p-4"><span><strong class="block text-sm text-[var(--text-strong)]">Already accept online bookings?</strong><span class="mt-1 block text-xs text-[var(--text-muted)]">We’ll keep import and switching help visible.</span></span><input v-model="guidedForm.accepts_online_bookings" type="checkbox" class="size-5 rounded" /></label>
                    </div>
                    <aside class="relative min-h-72 overflow-hidden bg-[var(--surface-inverse)] text-white"><img :src="guidedType?.image" :alt="guidedType ? `${guidedType.label} team at work` : ''" class="absolute inset-0 size-full object-cover opacity-70" /><div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/35 to-transparent" /><div class="absolute inset-x-0 bottom-0 p-6"><UserGroupIcon class="size-7" aria-hidden="true" /><p class="mt-3 text-xl font-semibold">We’ll add you as the first team member.</p><p class="mt-2 text-sm leading-6 text-white/70">Invitations and additional locations can wait until the workspace is useful.</p></div></aside>
                </div>

                <div v-else-if="guidedStep === 'location'" class="grid lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="p-5 sm:p-8"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Your first calendar</p><h2 class="cd-display mt-2 text-3xl font-semibold tracking-[-0.035em] text-[var(--text-strong)] sm:text-4xl">Where should appointments begin?</h2><p class="mt-3 max-w-2xl leading-7 text-[var(--text-muted)]">Country sets currency and local formats. Address and phone appear on the booking page.</p>
                        <div class="mt-7 grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold">Country<AppSelect v-model="guidedForm.country_code" class="cd-input mt-2" required><option v-for="(label, code) in onboardingCatalog.countries" :key="code" :value="code">{{ label }}</option></AppSelect></label><label class="text-sm font-semibold">Time zone<AppSelect v-model="guidedForm.time_zone" class="cd-input mt-2" required><option v-for="zone in onboardingCatalog.country_defaults[guidedForm.country_code]?.time_zones || []" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></AppSelect></label><label class="text-sm font-semibold sm:col-span-2">Business address or service-area base<textarea v-model="guidedForm.address" rows="3" required class="cd-input mt-2 w-full rounded-xl border border-[var(--border-subtle)] p-3" placeholder="Street, area, city and postal code" /><span class="mt-1.5 block text-xs font-normal text-[var(--text-muted)]">Mobile businesses can use their operating base and refine travel areas later.</span></label><label class="text-sm font-semibold sm:col-span-2">Public phone<PhoneInput id="guided-phone" v-model="guidedForm.phone" class="mt-2" :country="guidedForm.country_code" :countries="onboardingCatalog.countries" required /></label></div>
                        <fieldset class="mt-7"><legend class="font-semibold text-[var(--text-strong)]">Start with these opening hours</legend><div class="mt-3 grid gap-3 sm:grid-cols-3"><label v-for="(preset, key) in onboardingCatalog.schedule_presets" :key="key" :class="['cursor-pointer rounded-xl border p-4', guidedForm.schedule_preset === key ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)] ring-1 ring-[var(--brand-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.schedule_preset" class="ds-sr-only" type="radio" :value="key" /><ClockIcon class="size-5 text-[var(--brand-primary)]" aria-hidden="true" /><strong class="mt-2 block text-sm">{{ preset.label }}</strong><span class="mt-1 block text-xs text-[var(--text-muted)]">{{ preset.opens_at }}–{{ preset.closes_at }}</span></label></div></fieldset>
                    </div>
                    <aside class="bg-[var(--surface-subtle)] p-6"><MapPinIcon class="size-8 text-[var(--brand-primary)]" aria-hidden="true" /><h3 class="mt-4 text-xl font-semibold text-[var(--text-strong)]">Smart, not permanent.</h3><p class="mt-3 text-sm leading-6 text-[var(--text-muted)]">We’ll create one active location and a complete working week. Split shifts, holidays and additional locations remain available in Business setup.</p><div class="mt-6 rounded-2xl bg-[var(--surface-raised)] p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-[var(--text-muted)]">Prepared for you</p><ul class="mt-3 space-y-2 text-sm"><li>Local calendar time</li><li>Matching currency</li><li>Editable public contact details</li><li>Reasonable booking notice</li></ul></div></aside>
                </div>

                <div v-else class="grid lg:grid-cols-[minmax(0,1fr)_23rem]">
                    <div class="p-5 sm:p-8"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Your starter menu</p><h2 class="cd-display mt-2 text-3xl font-semibold tracking-[-0.035em] text-[var(--text-strong)] sm:text-4xl">Begin with services clients recognize.</h2><p class="mt-3 max-w-2xl leading-7 text-[var(--text-muted)]">Keep the useful suggestions. Names, prices, descriptions, durations and visibility remain fully editable.</p>
                        <div class="mt-7 grid gap-3 sm:grid-cols-2"><button v-for="service in starterServices" :key="service.key" type="button" :aria-pressed="guidedForm.service_keys.includes(service.key)" :class="['group flex min-h-32 items-start gap-4 rounded-2xl border p-4 text-left transition', guidedForm.service_keys.includes(service.key) ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)] ring-1 ring-[var(--brand-primary)]' : 'border-[var(--border-subtle)] opacity-65 hover:opacity-100']" @click="toggleStarterService(service.key)"><span :class="['grid size-11 shrink-0 place-items-center rounded-xl', guidedForm.service_keys.includes(service.key) ? 'bg-[var(--brand-primary)] text-white' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']"><ScissorsIcon class="size-5" aria-hidden="true" /></span><span class="min-w-0 flex-1"><span class="text-xs font-bold uppercase tracking-wide text-[var(--text-muted)]">{{ service.category }}</span><strong class="mt-1 block text-[var(--text-strong)]">{{ service.name }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ service.duration }} min · {{ service.description }}</span></span><CheckCircleIcon v-if="guidedForm.service_keys.includes(service.key)" class="size-5 shrink-0 text-[var(--brand-primary)]" aria-hidden="true" /></button></div>
                    </div>
                    <aside class="relative overflow-hidden bg-[var(--surface-inverse)] p-6 text-white"><div class="absolute -right-12 top-6 size-48 rounded-full bg-[var(--brand-secondary)]/35 blur-3xl" /><div class="relative"><p class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.13em] text-white/60"><PaintBrushIcon class="size-4" aria-hidden="true" />Ready on arrival</p><h3 class="mt-4 text-2xl font-semibold">Your business, already in motion.</h3><ul class="mt-6 space-y-4 text-sm"><li class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--brand-accent)]" /><span><strong class="block">{{ guidedForm.service_keys.length }} services</strong><span class="text-white/60">Priced and timed in {{ onboardingCatalog.country_defaults[guidedForm.country_code]?.currency }}</span></span></li><li class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--brand-accent)]" /><span><strong class="block">First team profile</strong><span class="text-white/60">Connected to services and working hours</span></span></li><li class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--brand-accent)]" /><span><strong class="block">Shareable booking page</strong><span class="text-white/60">A unique link generated from {{ business.name }}</span></span></li></ul><div class="mt-7 rounded-2xl border border-white/10 bg-white/8 p-4"><p class="text-xs leading-5 text-white/65">Selecting “Prepare my workspace” confirms these starter choices and makes a valid booking page live when the owner is bookable.</p></div></div></aside>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <button v-if="guidedIndex > 0" type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-raised)]" @click="guidedStep = guidedSteps[guidedIndex - 1]"><ArrowLeftIcon class="size-4" />Back</button><span v-else class="hidden sm:block" />
                    <div class="sm:text-right"><p v-if="Object.keys(guidedErrors).length" class="mb-2 max-w-xl text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(guidedErrors)[0] }}</p><AppButton v-if="guidedStep !== 'starter_services'" :disabled="guidedBusy || (guidedStep === 'business_type' && !guidedForm.business_type)" @click="saveGuidedStep">Continue<ArrowRightIcon class="size-4" /></AppButton><AppButton v-else :disabled="guidedBusy || !guidedForm.service_keys.length" @click="completeGuidedOnboarding"><SparklesIcon class="size-4" />{{ guidedBusy ? 'Preparing…' : 'Prepare my workspace' }}</AppButton></div>
                </div>
            </section>
        </div>

        <template v-else>
        <PageHeader eyebrow="Business setup" title="Business setup" description="Manage your business details, booking experience and launch readiness.">
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
            <div v-if="justPrepared" class="relative overflow-hidden rounded-[2rem] bg-[var(--surface-inverse)] text-white shadow-[var(--shadow-raised)]">
                <img :src="preparedImage" :alt="`${business.name} industry workspace`" class="absolute inset-y-0 right-0 h-full w-full object-cover opacity-45 lg:w-[48%]" />
                <div class="absolute inset-0 bg-gradient-to-r from-[var(--surface-inverse)] via-[var(--surface-inverse)]/95 to-transparent" aria-hidden="true" />
                <div class="relative max-w-3xl p-6 sm:p-9"><span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.13em] text-[var(--brand-accent)]"><SparklesIcon class="size-4" />Workspace prepared</span><h2 class="cd-display mt-4 text-[clamp(2.4rem,6vw,4.6rem)] font-semibold leading-[0.95] tracking-[-0.055em]">You’re ready to take a booking.</h2><p class="mt-4 max-w-2xl text-base leading-7 text-white/70">Your service menu, owner calendar, first location, working hours and booking page are connected. Try the client experience now—everything remains editable.</p><div class="mt-6 flex flex-wrap gap-3"><AppButton :href="bookingUrl" target="_blank">Preview live booking page<ChevronRightIcon class="size-4" /></AppButton><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10" @click="copyBookingLink"><CheckCircleIcon v-if="copiedBookingLink" class="size-4 text-[var(--brand-accent)]" /><ClipboardDocumentIcon v-else class="size-4" />{{ copiedBookingLink ? 'Link copied' : 'Copy booking link' }}</button><AppButton :href="route('business.calendar', business.public_id)" variant="secondary"><CalendarDaysIcon class="size-4" />See your calendar</AppButton></div></div>
            </div>
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
                <SurfaceCard>
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div><p class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--action-primary)]">Launch plan</p><h2 id="setup-overview-title" tabindex="-1" class="mt-2 text-2xl font-semibold text-[var(--text-strong)] focus:outline-none">{{ readyCount }} of {{ setupItems.length }} required areas are ready</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--text-muted)]">Every item below explains what is missing and takes you directly to the right workflow. Optional brand and import improvements never block a valid launch.</p></div>
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
                    <label class="text-sm font-semibold">Business name<input v-model="form.name" required class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Booking link<span class="mt-2 flex min-h-11 items-center rounded-lg border border-[var(--border-strong)] bg-white pl-3 text-sm text-[var(--text-muted)]">{{ $page.props.brand.booking_host }}/<input v-model="form.booking_slug" required minlength="3" class="cd-input min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-[var(--text-strong)] focus:ring-0" /></span></label>
                    <label class="text-sm font-semibold">Business type<AppSelect v-model="form.business_type" required class="cd-input mt-2"><option value="" disabled>Choose a business type</option><option v-if="form.business_type && !referenceData.business_types[form.business_type]" :value="form.business_type">{{ form.business_type }}</option><option v-for="(label, value) in referenceData.business_types" :key="value" :value="value">{{ label }}</option></AppSelect></label>
                    <label class="text-sm font-semibold">Accent colour<input v-model="form.brand_color" type="color" class="cd-input mt-2 h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white p-1" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Business description<textarea v-model="form.description" rows="3" maxlength="1200" class="cd-input mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" placeholder="A short, welcoming introduction for your booking page." /></label>
                    <div class="sm:col-span-2">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label v-for="asset in [{ kind: 'logo', title: 'Business logo', note: 'Square PNG, JPEG or WebP works best.', saved: business.logo_path }, { kind: 'cover', title: 'Booking cover', note: 'Use a bright, natural wide image of your space or work.', saved: business.cover_image_path }]" :key="asset.kind" :class="['group flex min-h-32 items-start gap-4 rounded-2xl border border-dashed border-[var(--border-strong)] bg-[var(--surface-subtle)] p-4 transition', $page.props.tenant.entitlements?.['branding.custom'] === false ? 'cursor-not-allowed opacity-70' : 'cursor-pointer hover:border-[var(--brand-primary)] hover:bg-[var(--status-info-soft)]']">
                                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-[var(--brand-primary)] shadow-sm"><PaintBrushIcon class="size-5" aria-hidden="true" /></span>
                                <span class="min-w-0 flex-1"><strong class="block text-sm text-[var(--text-strong)]">{{ asset.title }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ asset.saved ? 'Image added — choose another to replace it.' : asset.note }}</span><span class="mt-3 inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-[var(--brand-primary)]">{{ $page.props.tenant.entitlements?.['branding.custom'] === false ? 'Available on Pro' : (asset.saved ? 'Replace image' : 'Choose image') }}</span></span>
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="ds-sr-only" :disabled="brandUpload.processing || $page.props.tenant.entitlements?.['branding.custom'] === false" @change="uploadBrandAsset(asset.kind, $event)" />
                            </label>
                        </div>
                        <div v-if="$page.props.tenant.entitlements?.['branding.custom'] === false" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-3"><p class="text-xs leading-5 text-[var(--text-muted)]">Your industry cover and accent colour keep the page polished. Custom logo and cover uploads are included on Pro.</p><AppButton v-if="$page.props.tenant.can_manage_billing" :href="route('business.billing.show', business.public_id)" variant="secondary">See plan options</AppButton></div>
                        <p v-if="brandUpload.errors.asset || brandUpload.errors.kind" class="mt-2 text-sm text-[var(--status-danger)]" role="alert">{{ brandUpload.errors.asset || brandUpload.errors.kind }}</p>
                    </div>
                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Region & money</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Country suggests the phone code, currency, locale and a local time zone. You remain in control before saving.</p></div>
                    <label class="text-sm font-semibold">Country<AppSelect v-model="form.country_code" required class="cd-input mt-2"><option value="" disabled>Choose a country</option><option v-if="form.country_code && !referenceData.countries[form.country_code]" :value="form.country_code">{{ form.country_code }}</option><option v-for="(label, value) in referenceData.countries" :key="value" :value="value">{{ label }}</option></AppSelect></label>
                    <label class="text-sm font-semibold">Language & region<AppSelect v-model="form.locale" required class="cd-input mt-2"><option value="" disabled>Choose a locale</option><option v-if="form.locale && !referenceData.locales[form.locale]" :value="form.locale">{{ form.locale }}</option><option v-for="(label, value) in referenceData.locales" :key="value" :value="value">{{ label }}</option></AppSelect></label>
                    <label class="text-sm font-semibold">Currency<AppSelect v-model="form.currency_code" required class="cd-input mt-2"><option value="" disabled>Choose a currency</option><option v-if="form.currency_code && !referenceData.currencies[form.currency_code]" :value="form.currency_code">{{ form.currency_code }}</option><option v-for="(label, value) in referenceData.currencies" :key="value" :value="value">{{ label }}</option></AppSelect></label>
                    <label class="text-sm font-semibold">Time zone<AppSelect v-model="form.time_zone" required class="cd-input mt-2"><option value="" disabled>Choose a time zone</option><option v-for="zone in referenceData.time_zones" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></AppSelect></label>
                    <label class="text-sm font-semibold">Week starts<AppSelect v-model="form.week_starts_on" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option :value="1">Monday</option><option :value="7">Sunday</option></AppSelect></label>
                    <label class="text-sm font-semibold">Appointment interval<AppSelect v-model="form.appointment_interval_minutes" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option v-for="value in [5,10,15,20,30,60]" :key="value" :value="value">{{ value }} minutes</option></AppSelect></label>
                    <label class="text-sm font-semibold">Tax posture<AppSelect v-model="form.tax_posture" required class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="" disabled>Choose</option><option value="inclusive">Prices include tax</option><option value="exclusive">Tax added at checkout</option><option value="not_registered">Not tax registered</option></AppSelect></label>
                    <label v-if="form.tax_posture !== 'not_registered'" class="text-sm font-semibold">Default tax rate<input v-model="taxRatePercent" type="number" min="0" max="100" step="0.01" inputmode="decimal" class="cd-input mt-2"><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Percent applied at checkout when a service or product has no more specific rate.</span></label>
                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Client-facing details</h3><p class="mt-1 text-xs text-[var(--text-muted)]">These details appear wherever clients need to identify or contact your business.</p></div>
                    <label class="text-sm font-semibold">Public phone<PhoneInput id="business-phone" v-model="form.phone" class="mt-2" :country="form.country_code || 'IN'" :countries="referenceData.countries" required /><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Saved internationally and reused on booking pages and receipts.</span></label>
                    <label class="text-sm font-semibold">Email<input v-model="form.email" required type="email" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Address<textarea v-model="form.address" required rows="2" class="cd-input mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" /></label>
                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Booking policies</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Clients review these before confirming; past bookings keep their original policy snapshot.</p></div>
                    <label class="text-sm font-semibold sm:col-span-2">Cancellation policy<textarea v-model="form.default_cancellation_policy" required rows="3" class="cd-input mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" /></label>
                    <label class="text-sm font-semibold">Terms URL<input v-model="form.terms_url" required type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Privacy URL<input v-model="form.privacy_url" required type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Website (optional)<input v-model="form.website_url" type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <label class="text-sm font-semibold">Map URL (optional)<input v-model="form.map_url" type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                    <p v-if="Object.keys(form.errors).length" class="sm:col-span-2 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">Check the highlighted profile fields before saving.</p>
                    <div class="sm:col-span-2 flex justify-end"><AppButton type="submit" :disabled="form.processing">Save business details</AppButton></div>
                </form>
            </SurfaceCard>

        </div>

        <SurfaceCard v-show="activeSection === 'booking_rules'" id="booking_rules" class="mt-6" title="Public booking controls" description="Choose what clients can do online. Policy changes are shown again to anyone already booking.">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="savePublicPolicy">
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.online_booking_enabled" type="checkbox" />Accept online bookings</label>
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.staff_gender_request_enabled" type="checkbox" />Allow staff gender requests</label>
                <label class="text-sm font-semibold">Staff choice<AppSelect v-model="publicPolicyForm.online_staff_preference" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="any_or_preferred">Any or preferred</option><option value="any_only">First available only</option><option value="preferred_required">Client must choose</option></AppSelect></label>
                <label class="text-sm font-semibold">Price display<AppSelect v-model="publicPolicyForm.online_price_display" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="service_setting">Per service setting</option><option value="exact">Exact price</option><option value="from">From price</option></AppSelect></label>
                <label class="text-sm font-semibold">New clients<AppSelect v-model="publicPolicyForm.online_new_client_rule" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="allow">Allow</option><option value="consultation_only">Consultation only</option><option value="existing_only">Existing clients only</option></AppSelect></label>
                <label class="text-sm font-semibold">Cancellation cutoff<AppSelect v-model="publicPolicyForm.cancellation_cutoff_minutes" class="cd-input mt-2"><option :value="0">Any time</option><option :value="360">6 hours before</option><option :value="720">12 hours before</option><option :value="1440">24 hours before</option><option :value="2880">48 hours before</option><option :value="10080">7 days before</option></AppSelect></label>
                <label class="text-sm font-semibold">Waitlist offer batch<input v-model="publicPolicyForm.waitlist_offer_batch_size" type="number" min="1" max="10" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" /></label>
                <label class="text-sm font-semibold">Secure link remains valid for<AppSelect v-model="publicPolicyForm.public_link_ttl_minutes" class="cd-input mt-2"><option :value="60">1 hour</option><option :value="1440">1 day</option><option :value="4320">3 days</option><option :value="10080">7 days</option><option :value="20160">14 days</option><option :value="43200">30 days</option></AppSelect></label>
                <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><AppButton type="submit" :disabled="publicPolicyForm.processing">Save booking rules</AppButton></div>
            </form>
        </SurfaceCard>

        <SurfaceCard v-show="activeSection === 'hours'" id="hours" class="mt-6" title="Build the bookable path" description="Complete these focused workflows in order. Each area becomes the long-term workspace for managing that part of your business.">
            <ol class="grid gap-4 md:grid-cols-3">
                <li class="rounded-xl border border-[var(--border-subtle)] p-5"><span class="text-xs font-bold uppercase tracking-wide text-[var(--action-primary)]">Step 2</span><h3 class="mt-2 font-semibold text-[var(--text-strong)]">Location & hours</h3><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ locations.length }} location{{ locations.length === 1 ? '' : 's' }}. Confirm the public address, local time zone and normal opening week.</p><AppButton class="mt-4 w-full" :href="route('business.locations.index', business.public_id)" variant="secondary">Open locations</AppButton></li>
                <li class="rounded-xl border border-[var(--border-subtle)] p-5"><span class="text-xs font-bold uppercase tracking-wide text-[var(--action-primary)]">Step 3</span><h3 class="mt-2 font-semibold text-[var(--text-strong)]">Team & availability</h3><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ staff.length }} provider{{ staff.length === 1 ? '' : 's' }}. Add schedulable people without forcing them to have login access.</p><AppButton class="mt-4 w-full" :href="route('business.team.index', business.public_id)" variant="secondary">Open team</AppButton></li>
                <li class="rounded-xl border border-[var(--border-subtle)] p-5"><span class="text-xs font-bold uppercase tracking-wide text-[var(--action-primary)]">Step 4</span><h3 class="mt-2 font-semibold text-[var(--text-strong)]">Services</h3><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">{{ services.length }} service{{ services.length === 1 ? '' : 's' }}. Connect price, duration, locations and qualified providers.</p><AppButton class="mt-4 w-full" :href="route('business.services.index', business.public_id)" variant="secondary">Open services</AppButton></li>
            </ol>
        </SurfaceCard>

        <SurfaceCard v-show="activeSection === 'import'" id="import" class="mt-6" title="Import existing records" description="Upload a CSV, map the columns, review invalid or duplicate rows, then start the import when the preview is clean.">
            <div class="grid gap-4 md:grid-cols-3">
                <label class="text-sm font-semibold">Record type<AppSelect v-model="importType" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="clients">Clients</option><option value="staff">Staff</option><option value="services">Services</option><option value="products">Products</option></AppSelect></label>
                <label class="text-sm font-semibold md:col-span-2">CSV file<input type="file" accept=".csv,text/csv" class="mt-2 block min-h-11 w-full rounded-lg border border-[var(--border-subtle)] p-2" @change="chooseImportFile" /></label>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-sm"><span class="font-semibold">Download a template:</span><a v-for="type in ['clients','staff','services','products']" :key="type" class="text-[var(--action-primary)] underline" :href="route('business.configuration.imports.template', [business.public_id, type])">{{ type }}</a></div>
            <fieldset v-if="importHeaders.length" class="mt-5"><legend class="font-semibold">Map columns</legend><div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label v-for="field in importFields[importType]" :key="field" class="text-sm font-semibold">{{ field.replaceAll('_', ' ') }}<AppSelect v-model="importMapping[field]" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="">Do not import</option><option v-for="header in importHeaders" :key="header" :value="header">{{ header }}</option></AppSelect></label></div></fieldset>
            <div class="mt-4"><AppButton variant="secondary" :disabled="!importCsv || importBusy" @click="previewImport">Validate and preview</AppButton></div>
            <p v-if="importError" class="mt-4 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ importError }}</p>
            <div v-if="importResult" class="mt-5 rounded-xl border border-[var(--border-subtle)] p-4">
                <p class="font-semibold">{{ importResult.status.replaceAll('_', ' ') }} · {{ importResult.total_rows }} rows</p>
                <p class="mt-1 text-sm text-[var(--text-muted)]">{{ importResult.failed_rows }} invalid · {{ importResult.duplicate_rows }} need duplicate review</p>
                <ul v-if="importResult.rows?.length" class="mt-4 max-h-80 space-y-3 overflow-y-auto">
                    <li v-for="row in importResult.rows" :key="row.id" class="rounded-lg bg-[var(--surface-subtle)] p-3 text-sm">
                        <p><strong>Row {{ row.row_number }}</strong> · {{ row.status.replaceAll('_', ' ') }}</p>
                        <p v-if="row.errors?.length" class="mt-1 text-[var(--status-danger)]">{{ row.errors.join('; ') }}</p>
                        <label v-if="row.status === 'duplicate_review'" class="mt-2 block font-semibold">Duplicate decision<AppSelect v-model="duplicateResolutions[row.id]" class="mt-1 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"><option value="" disabled>Choose</option><option value="update">Update matched record</option><option value="create">Create separately</option><option value="skip">Skip row</option></AppSelect></label>
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
        </template>
    </AppLayout>
</template>
