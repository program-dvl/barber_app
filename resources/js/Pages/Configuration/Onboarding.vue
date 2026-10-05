<script setup>
import { billingDate } from '@/Support/billingWorkspace';
import FormField from '@/Components/Product/FormField.vue';
import FieldError from '@/Components/Product/FieldError.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import { computed, nextTick, ref, watch, onMounted, onBeforeUnmount } from 'vue';
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
    SparklesIcon, ChevronDownIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import SetupHome from '@/Components/Setup/SetupHome.vue';
import SetupServices from '@/Components/Setup/SetupServices.vue';
import SetupHours from '@/Components/Setup/SetupHours.vue';
import SetupTeam from '@/Components/Setup/SetupTeam.vue';
import { weekFromHours } from '@/Support/businessSetup';
import '../../../css/business-setup.css';

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
    setupSummary: Object, serviceReview: Array, hoursRevisions: Object, setupTeam: Array, channels: Object, canManageTeam: Boolean,
});

const exploring=ref(false), lockedService=ref(false);
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
const starterPrice=service=>{const currency=props.onboardingCatalog.country_defaults[guidedForm.value.country_code]?.currency||'USD';const base=props.onboardingCatalog.starter_price_major[currency]||35;return new Intl.NumberFormat('en',{style:'currency',currency}).format(Math.max(1,Math.round(base*service.price_factor)));};
const starterCategories = computed(() => guidedType.value?.categories || [...new Set(starterServices.value.map(service => service.category))]);
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
        onError: async errors => { guidedErrors.value = errors; await nextTick(); document.querySelector('#main-content [aria-invalid="true"]')?.focus(); },
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
        onError: async errors => { guidedErrors.value = errors; await nextTick(); document.querySelector('#main-content [aria-invalid="true"]')?.focus(); },
        onFinish: () => { guidedBusy.value = false; },
    });
};
watch(() => guidedForm.value.country_code, country => {
    const zones = props.onboardingCatalog.country_defaults?.[country]?.time_zones || [];
    const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (!zones.includes(guidedForm.value.time_zone)) guidedForm.value.time_zone = zones.includes(browserTimeZone) ? browserTimeZone : (zones[0] || '');
}, { immediate: true });

const form = useForm({
    setup_revision: props.business.setup_revision,
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
    appointment_interval_minutes: props.business.appointment_interval_minutes ?? 15,
    setup_revision: props.business.setup_revision,
    online_booking_enabled: props.business.online_booking_enabled ?? false,
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
    {id:'overview',label:'Readiness'}, {id:'business_details',label:'Business profile'},
    {id:'hours',label:'Opening hours'}, {id:'services',label:'Services'}, {id:'team',label:'Team & availability'},
    {id:'booking_rules',label:'Booking preferences'}, {id:'preview',label:'Online booking'},
];
const optionalSections=[{id:'connections',label:'Payments & notifications'},{id:'branding',label:'Branding'},{id:'import',label:'Import records'}];
const allSections=[...sections,...optionalSections];
const profileGroup=ref('identity'), childDirty=ref({}), discardDialog=ref(null), version=ref(0);
let pendingNavigation=null;
const dirty=computed(()=>form.isDirty || publicPolicyForm.isDirty || Object.values(childDirty.value).some(Boolean));
const sectionForStep = step => ({ staff: 'team', staff_availability: 'team', publish: 'preview' }[step] || step || 'overview');
const searchParams = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
const requestedSection = searchParams.get('section');
const justPrepared = ref(searchParams.get('welcome') === '1');
const copiedBookingLink = ref(false);
const bookingUrl = computed(() => props.business.booking_slug ? route('booking.business', props.business.booking_slug) : '');
const preparedImage = computed(() => props.onboardingCatalog.business_types[props.business.business_type]?.image || '/images/marketing/editorial/product-workday.webp');
const copyBookingLink = async () => {
    try{await navigator.clipboard.writeText(bookingUrl.value);copiedBookingLink.value=true;}catch{publishError.value='Copy isn’t available in this browser. Select the booking link below.';}
    window.setTimeout(() => { copiedBookingLink.value = false; }, 2200);
};
const activeSection = ref(allSections.some(section => section.id === requestedSection)
    ? requestedSection
    : 'overview');
watch(() => props.onboarding.guided_completed_at, (completed, previous) => {
    if (!completed) return;

    const arrivedFromCompletion = new URLSearchParams(window.location.search).get('welcome') === '1' || !previous;
    const activeSectionExists = sections.some(section => section.id === activeSection.value);
    if (arrivedFromCompletion || !activeSectionExists) {
        activeSection.value = 'overview';
        justPrepared.value = arrivedFromCompletion;
    }
}, { flush: 'post' });
const summary=computed(()=>props.setupSummary);
const previewServices=computed(()=>props.serviceReview.filter(service=>service.bookable_online));
const previewWeek=computed(()=>weekFromHours(props.locations[0]?.hours||[]).map((day,index)=>({...day,label:['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][index]})));
const publishBusy=ref(false),publishError=ref('');
const taxRatePercent = computed({
    get: () => Number(form.default_tax_rate_bps || 0) / 100,
    set: value => { form.default_tax_rate_bps = Math.round((Number(value) || 0) * 100); },
});
const applySection=section=>{activeSection.value=section;window.history.replaceState({},'',`${window.location.pathname}?section=${section}`);};
const selectSection=(section,group=null)=>{if(lockedService.value)return;const navigate=()=>{if(group)profileGroup.value=group;applySection(section);};if(dirty.value){pendingNavigation=navigate;discardDialog.value?.open();return;}navigate();};
const discardChanges=()=>{form.reset();publicPolicyForm.reset();childDirty.value={};version.value++;const navigate=pendingNavigation;pendingNavigation=null;navigate?.();};
const beforeUnload=event=>{if(dirty.value){event.preventDefault();event.returnValue='';}};
let stopNavigation;
onMounted(()=>{window.addEventListener('beforeunload',beforeUnload);stopNavigation=router.on('before',event=>{if(dirty.value&&!form.processing&&!publicPolicyForm.processing&&!publishBusy.value&&event.detail.visit.method.toLowerCase()==='get'){event.preventDefault();if(lockedService.value)return;pendingNavigation=()=>router.visit(event.detail.visit.url.href);discardDialog.value?.open();}});});
onBeforeUnmount(()=>{window.removeEventListener('beforeunload',beforeUnload);stopNavigation?.();});
const syncingProfile=ref(false);
watch(()=>props.business,value=>{syncingProfile.value=true;if(!form.isDirty){form.defaults(Object.fromEntries(Object.keys(form.data()).map(key=>[key,value[key]??''])));form.reset();}if(!publicPolicyForm.isDirty){publicPolicyForm.defaults(Object.fromEntries(Object.keys(publicPolicyForm.data()).map(key=>[key,value[key]??publicPolicyForm[key]])));publicPolicyForm.reset();}nextTick(()=>syncingProfile.value=false);});
watch(() => form.country_code, (country, previous) => {
    if (!country || country === previous || syncingProfile.value) return;
    const defaults = props.referenceData.country_defaults?.[country] || {};
    const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (!props.services.length && defaults.currency) form.currency_code = defaults.currency;
    if ((defaults.time_zones || []).length) form.time_zone = defaults.time_zones.includes(browserTimeZone) ? browserTimeZone : defaults.time_zones[0];
    try {
        const language = new Intl.Locale(`und-${country}`).maximize().language;
        form.locale = `${language}-${country}`;
    } catch { form.locale = `en-${country}`; }
});
const save=()=>form.patch(route('business.configuration.profile.update',props.business.public_id),{preserveScroll:true,onSuccess:()=>{form.setup_revision=props.business.setup_revision;form.defaults();},onError:async errors=>{const key=Object.keys(errors)[0];profileGroup.value=['country_code','currency_code','locale','time_zone','week_starts_on','appointment_interval_minutes','tax_posture','default_tax_rate_bps'].includes(key)?'region':['default_cancellation_policy','terms_url','privacy_url','website_url','map_url'].includes(key)?'policies':'identity';await nextTick();document.querySelector('#business_details [aria-invalid="true"]')?.focus();}});
const savePublicPolicy=()=>publicPolicyForm.patch(route('business.configuration.public-booking-policy.update',props.business.public_id),{preserveScroll:true,onSuccess:()=>{publicPolicyForm.setup_revision=props.business.setup_revision;publicPolicyForm.defaults();}});
const markPreviewed=()=>{publishBusy.value=true;router.post(route('business.configuration.preview',props.business.public_id),{},{preserveScroll:true,onFinish:()=>publishBusy.value=false});};
const publish=()=>{publishBusy.value=true;publishError.value='';router.post(route('business.configuration.publish',props.business.public_id),{},{preserveScroll:true,onError:errors=>publishError.value=Object.values(errors).flat().join(' '),onFinish:()=>publishBusy.value=false});};
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
        <div class="bs-workspace">
        <div v-if="!onboarding.guided_completed_at && !exploring" class="mx-auto max-w-6xl pb-10">
            <PageHeader title="Let’s prepare your business" description="Four short decisions. We’ll do the setup work for you." />
            <div class="mt-3 max-w-xl">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-[var(--text-muted)]">A few decisions help us prepare your workspace.</p><AppButton variant="quiet" size="small" @click="exploring=true">Explore now, finish later<ArrowRightIcon class="size-4" /></AppButton></div><div class="flex items-center justify-between gap-3 text-sm text-[var(--text-muted)]"><span>{{ guidedIndex + 1 }} of {{ guidedSteps.length }} decisions</span><span>Progress saves when you continue.</span></div>
                <div class="mt-2 h-1 overflow-hidden rounded-full bg-[var(--border-subtle)]" aria-hidden="true"><div class="h-full bg-[var(--action-primary)]" :style="{ width: `${((guidedIndex + 1) / guidedSteps.length) * 100}%` }" /></div>
            </div>

            <section class="mt-6 overflow-hidden rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] shadow-[var(--shadow-raised)]" aria-live="polite">
                <div v-if="guidedStep === 'business_type'" class="p-4 sm:p-5">
                    <h2 class="text-lg font-semibold text-[var(--text-strong)]">Business type</h2>
                    <div class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-3">
                        <button v-for="type in guidedTypes" :key="type.key" type="button" :aria-pressed="guidedForm.business_type === type.key" :class="['flex min-h-11 items-center gap-3 rounded-lg border p-3 text-left text-sm', guidedForm.business_type === type.key ? 'border-[var(--action-primary)] bg-[var(--status-info-soft)]' : 'border-[var(--border-subtle)] hover:bg-[var(--surface-subtle)]']" @click="selectBusinessType(type.key)">
                            <img :src="type.image" alt="" width="32" height="32" class="hidden size-8 shrink-0 rounded-md object-cover sm:block" />
                            <strong class="min-w-0 flex-1">{{ type.label }}</strong>
                            <CheckCircleIcon v-if="guidedForm.business_type === type.key" class="size-4 shrink-0 text-[var(--action-primary)]" aria-hidden="true" />
                        </button>
                    </div>
                    <p v-if="guidedType" class="mt-3 text-sm text-[var(--text-muted)]">{{ guidedType.description }}</p>

                </div>

                <div v-else-if="guidedStep === 'business_shape'" class="max-w-4xl">
                    <div class="p-4 sm:p-5"><h2 class="cd-display mt-2 text-xl font-semibold tracking-[-0.035em] text-[var(--text-strong)] ">Business details</h2>
                        <fieldset class="mt-4"><legend class="font-semibold text-[var(--text-strong)]">Where appointments happen</legend><div class="mt-3 grid gap-3 sm:grid-cols-3"><label v-for="(option, key) in onboardingCatalog.operation_models" :key="key" :class="['cursor-pointer rounded-lg border p-3', guidedForm.operation_model === key ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)] ring-1 ring-[var(--brand-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.operation_model" class="ds-sr-only" type="radio" :value="key" /><strong class="block text-sm">{{ option.label }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ option.description }}</span></label></div></fieldset>
                        <fieldset class="mt-4"><legend class="font-semibold text-[var(--text-strong)]">Team size</legend><div class="mt-3 flex flex-wrap gap-2"><label v-for="(label, key) in onboardingCatalog.team_sizes" :key="key" :class="['cursor-pointer rounded-full border px-4 py-2.5 text-sm font-semibold', guidedForm.team_size === key ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white' : 'border-[var(--border-subtle)] hover:border-[var(--brand-primary)]']"><input v-model="guidedForm.team_size" class="ds-sr-only" type="radio" :value="key" />{{ label }}</label></div></fieldset>
                        <div class="mt-4 grid gap-5 sm:grid-cols-2"><fieldset><legend class="font-semibold text-[var(--text-strong)]">Locations</legend><div class="mt-3 grid grid-cols-2 gap-2"><label v-for="(label, key) in onboardingCatalog.location_scales" :key="key" :class="['cursor-pointer rounded-xl border p-3 text-sm font-semibold', guidedForm.location_scale === key ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.location_scale" class="ds-sr-only" type="radio" :value="key" />{{ label }}</label></div></fieldset><fieldset><legend class="font-semibold text-[var(--text-strong)]">Do you take appointments yourself?</legend><div class="mt-3 grid grid-cols-2 gap-2"><label v-for="option in [{ value: true, label: 'Yes, add me' }, { value: false, label: 'No, owner only' }]" :key="String(option.value)" :class="['cursor-pointer rounded-xl border p-3 text-sm font-semibold', guidedForm.owner_bookable === option.value ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.owner_bookable" class="ds-sr-only" type="radio" :value="option.value" />{{ option.label }}</label></div></fieldset></div>
                        <label class="mt-6 flex min-h-14 cursor-pointer items-center justify-between gap-4 rounded-xl bg-[var(--surface-subtle)] p-4"><span><strong class="block text-sm text-[var(--text-strong)]">Already accept online bookings?</strong><span class="mt-1 block text-xs text-[var(--text-muted)]">We’ll keep import and switching help visible.</span></span><input v-model="guidedForm.accepts_online_bookings" type="checkbox" class="size-5 rounded" /></label>
                    </div>

                </div>

                <div v-else-if="guidedStep === 'location'" class="max-w-4xl">
                    <div class="p-4 sm:p-5"><h2 class="cd-display mt-2 text-xl font-semibold tracking-[-0.035em] text-[var(--text-strong)] ">Where do you work?</h2><p class="mt-3 max-w-2xl leading-7 text-[var(--text-muted)]">Country sets currency and local formats. Add public contact details now or before going online.</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <FormField id="guided-country" label="Country" required :error="guidedErrors.country_code"><AppSelect id="guided-country" v-model="guidedForm.country_code"><option v-for="(label, code) in onboardingCatalog.countries" :key="code" :value="code">{{ label }}</option></AppSelect></FormField>
                            <FormField id="guided-time-zone" label="Time zone" required :error="guidedErrors.time_zone"><AppSelect id="guided-time-zone" v-model="guidedForm.time_zone"><option v-for="zone in onboardingCatalog.country_defaults[guidedForm.country_code]?.time_zones || []" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></AppSelect></FormField>
                            <FormField id="guided-address" class="sm:col-span-2" label="Business address or operating base" hint="Optional for now. Needed before online booking." :error="guidedErrors.address"><textarea id="guided-address" v-model="guidedForm.address" rows="2" class="cd-input" placeholder="Street, area, city and postal code" /></FormField>
                            <FormField id="guided-phone" class="sm:col-span-2" label="Public phone" hint="Optional for now. Needed before online booking." :error="guidedErrors.phone"><PhoneInput id="guided-phone" v-model="guidedForm.phone" :country="guidedForm.country_code" :countries="onboardingCatalog.countries" /></FormField>
                        </div>
                        <fieldset class="mt-4"><legend class="font-semibold text-[var(--text-strong)]">Start with these opening hours</legend><div class="mt-3 grid gap-3 sm:grid-cols-3"><label v-for="(preset, key) in onboardingCatalog.schedule_presets" :key="key" :class="['cursor-pointer rounded-lg border p-3', guidedForm.schedule_preset === key ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)] ring-1 ring-[var(--brand-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="guidedForm.schedule_preset" class="ds-sr-only" type="radio" :value="key" /><ClockIcon class="size-5 text-[var(--brand-primary)]" aria-hidden="true" /><strong class="mt-2 block text-sm">{{ preset.label }}</strong><span class="mt-1 block text-xs text-[var(--text-muted)]">{{ preset.opens_at }}–{{ preset.closes_at }}</span></label></div></fieldset>
                    </div>

                </div>

                <div v-else class="grid lg:grid-cols-[minmax(0,1fr)_23rem]">
                    <div class="p-4 sm:p-5"><h2 class="cd-display mt-2 text-xl font-semibold tracking-[-0.035em] text-[var(--text-strong)] ">Starter services</h2><p class="mt-3 max-w-2xl leading-7 text-[var(--text-muted)]">Select the services you offer. Suggested prices and durations stay editable. Your page stays private until you review it and go live.</p>
                        <section class="mt-4 border-y border-[var(--border-subtle)] py-3" aria-labelledby="starter-categories-title">
                            <h3 id="starter-categories-title" class="text-sm font-semibold text-[var(--text-strong)]">Categories prepared for you</h3>
                            <p class="mt-1 text-xs leading-5 text-[var(--text-muted)]">Based on your business type. Rename, reorder or archive them later in Services.</p>
                            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-[var(--text-strong)]"><li v-for="category in starterCategories" :key="category">{{ category }}</li></ul>
                        </section>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2"><button v-for="service in starterServices" :key="service.key" type="button" :aria-pressed="guidedForm.service_keys.includes(service.key)" :class="['group flex min-h-11 items-start gap-3 rounded-2xl border p-4 text-left transition', guidedForm.service_keys.includes(service.key) ? 'border-[var(--brand-primary)] bg-[var(--status-info-soft)] ring-1 ring-[var(--brand-primary)]' : 'border-[var(--border-subtle)] opacity-65 hover:opacity-100']" @click="toggleStarterService(service.key)"><span :class="['grid size-8 shrink-0 place-items-center rounded-lg', guidedForm.service_keys.includes(service.key) ? 'bg-[var(--brand-primary)] text-white' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']"><ScissorsIcon class="size-5" aria-hidden="true" /></span><span class="min-w-0 flex-1"><span class="text-xs font-bold uppercase tracking-wide text-[var(--text-muted)]">{{ service.category }}</span><strong class="mt-1 block text-[var(--text-strong)]">{{ service.name }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ service.duration }} min · Suggested {{ starterPrice(service) }}<span class="block">{{ service.description }}</span></span></span><CheckCircleIcon v-if="guidedForm.service_keys.includes(service.key)" class="size-5 shrink-0 text-[var(--brand-primary)]" aria-hidden="true" /></button></div>
                    </div>
                    <aside class="relative overflow-hidden bg-[var(--surface-inverse)] p-6 text-white"><div class="absolute -right-12 top-6 size-48 rounded-full bg-[var(--brand-secondary)]/35 blur-3xl" /><div class="relative"><p class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.13em] text-white/60"><PaintBrushIcon class="size-4" aria-hidden="true" />Setup summary</p><h3 class="mt-4 text-2xl font-semibold">Review your setup</h3><ul class="mt-6 space-y-4 text-sm"><li class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--brand-accent)]" /><span><strong class="block">{{ guidedForm.service_keys.length }} {{ guidedForm.service_keys.length === 1 ? 'service' : 'services' }}</strong><span class="text-white/60">Priced and timed in {{ onboardingCatalog.country_defaults[guidedForm.country_code]?.currency }}</span></span></li><li v-if="guidedForm.owner_bookable" class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--brand-accent)]" /><span><strong class="block">Your staff profile</strong><span class="text-white/60">Connected to services and working hours</span></span></li><li class="flex gap-3"><CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-[var(--brand-accent)]" /><span><strong class="block">Booking page</strong><span class="text-white/60">A unique link generated from {{ business.name }}</span></span></li></ul><div class="mt-4 rounded-2xl border border-white/10 bg-white/8 p-4"><p class="text-xs leading-5 text-white/65">We prepare a private booking page. Review it from Business Setup and choose when to go live.</p></div></div></aside>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <button v-if="guidedIndex > 0" type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-raised)]" @click="guidedStep = guidedSteps[guidedIndex - 1]"><ArrowLeftIcon class="size-4" />Back</button><span v-else class="hidden sm:block" />
                    <div class="sm:text-right"><p v-if="Object.keys(guidedErrors).some(key => !['country_code', 'time_zone', 'address', 'phone'].includes(key))" class="mb-2 max-w-xl text-sm text-[var(--status-danger)]" role="alert">{{ Object.values(guidedErrors)[0] }}</p><AppButton v-if="guidedStep !== 'starter_services'" :disabled="guidedBusy || (guidedStep === 'business_type' && !guidedForm.business_type)" @click="saveGuidedStep">Continue<ArrowRightIcon class="size-4" /></AppButton><AppButton v-else :disabled="guidedBusy" @click="completeGuidedOnboarding"><SparklesIcon class="size-4" />{{ guidedBusy ? 'Saving…' : 'Prepare my workspace' }}</AppButton></div>
                </div>
            </section>
        </div>

        <template v-else>
        <PageHeader title="Business setup">
            <template #actions>
                <AppButton v-if="business.configuration_published_at && business.online_booking_enabled" :href="route('booking.business', business.booking_slug)" variant="secondary">View booking page</AppButton>
            </template>
        </PageHeader>

        <div v-if="!onboarding.guided_completed_at" class="bs-callout"><strong>Your starter workspace is waiting.</strong><p>Continue the saved introduction to prepare services, hours and your owner profile.</p><AppButton size="small" @click="exploring=false">Continue introduction</AppButton></div><nav class="bs-section-nav" aria-label="Setup sections"><button v-for="section in sections" :key="section.id" type="button" :aria-current="activeSection===section.id?'page':undefined" @click="selectSection(section.id)">{{section.label}}</button><details class="bs-more"><summary :class="{'selected':optionalSections.some(s=>s.id===activeSection)}">Optional setup<ChevronDownIcon aria-hidden="true" /></summary><div><button v-for="section in optionalSections" :key="section.id" type="button" :aria-current="activeSection===section.id?'page':undefined" @click="selectSection(section.id); $event.target.closest('details').removeAttribute('open')">{{section.label}}</button></div></details></nav>
        <SetupHome @region="selectSection('business_details','region')" v-if="activeSection==='overview'" :summary="summary" :business="business" :onboarding="onboarding" :channels="channels" @section="selectSection" />
        <SetupServices v-if="activeSection==='services'" :key="`services-${version}`" :business="business" :services="serviceReview" :staff="staff" @dirty="childDirty.services=$event" @locked="lockedService=$event" />
        <SetupHours v-if="activeSection==='hours'" :key="`hours-${version}`" :business="business" :locations="locations" :revisions="hoursRevisions" @dirty="childDirty.hours=$event" />
        <SetupTeam :key="version" @dirty="childDirty.team=$event" v-if="activeSection==='team'" :business="business" :team="setupTeam" :locations="locations" :can-manage="canManageTeam" />
        <section v-if="activeSection==='connections'" class="bs-panel"><header class="bs-panel-heading"><div><h2>Payments & client notifications</h2><p>Start with the essentials. Add connected channels when you need them.</p></div><AppButton variant="quiet" @click="selectSection('overview')">Do this later</AppButton></header><div class="bs-connection-grid"><section><h3>Checkout payments<span class="bs-status ready">Available</span></h3><p>Record cash or payments collected on your own card terminal. You can start without connecting an online payment provider.</p><AppButton :href="route('business.checkout.index',business.public_id)" variant="secondary">Open checkout</AppButton></section><section><h3>Online payments<span class="bs-status neutral">Optional</span></h3><p>Online deposit checkout is not available yet. Start without required deposits, or collect payment at your business. Online payments are separate from your ClipperDesk subscription.</p><AppButton :href="route('business.services.index',business.public_id)" variant="secondary">Review service deposits</AppButton></section><section><h3>Client notifications<span class="bs-status neutral">Prepared messages</span></h3><p>Booking confirmations, a 24-hour reminder, cancellation notices and receipts use prepared templates.</p><dl class="bs-channel-status"><div><dt>Email</dt><dd>{{channels.email}}</dd></div><div><dt>SMS</dt><dd>{{channels.sms}}</dd></div></dl><AppButton :href="route('business.communications.page',business.public_id)" variant="secondary">Review client notifications</AppButton></section><section><h3>Subscription & billing<span class="bs-status neutral">Your plan</span></h3><p v-if="$page.props.tenant?.subscription?.status === 'trialing'">Your free trial ends {{ billingDate($page.props.tenant.subscription.trial_ends_at, business.time_zone, business.locale) }}. Payment setup is optional during the trial; no automatic charge is scheduled.</p><p v-else-if="$page.props.tenant?.subscription">{{ $page.props.tenant.subscription.plan_name }} controls your locations, active team and included features. Review price, access and payments in billing.</p><p v-else>Your ClipperDesk plan controls locations, staff and features.</p><AppButton v-if="$page.props.tenant?.can_manage_billing" :href="route('business.billing.show',business.public_id)" variant="secondary">Review your plan</AppButton></section></div></section>
        <div v-show="activeSection === 'business_details'" class="mt-6">
            <SurfaceCard id="business_details" title="Business profile" description="Public identity, regional formats, contact details, and the policies clients review before booking.">
                <div class="bs-profile-tabs" aria-label="Profile groups"><button v-for="group in [{id:'identity',label:'Identity & contact'},{id:'region',label:'Region & money'},{id:'policies',label:'Client-facing policies'}]" :key="group.id" type="button" :aria-pressed="profileGroup===group.id" @click="profileGroup=group.id">{{group.label}}</button></div>
                <form novalidate class="bs-profile-form" @submit.prevent="save"><div v-show="profileGroup==='identity'" class="bs-profile-grid">
                    <div class="sm:col-span-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Identity</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Used across the workspace, booking page, receipts and client messages.</p></div>
                    <label class="text-sm font-semibold">Business name<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><input v-model="form.name" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"  id="field-onboarding-form-name" :aria-invalid="form.errors.name ? true : undefined" :aria-describedby="form.errors.name ? 'field-onboarding-form-name-error' : undefined"/><FieldError id="field-onboarding-form-name-error" :message="form.errors.name" /></label>
                    <label class="text-sm font-semibold">Booking link<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><span class="mt-2 flex min-h-11 items-center rounded-lg border border-[var(--border-strong)] bg-white pl-3 text-sm text-[var(--text-muted)]">{{ $page.props.brand.booking_host }}/<input v-model="form.booking_slug" minlength="3" class="cd-input min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-[var(--text-strong)] focus:ring-0"  id="field-onboarding-form-booking-slug" :aria-invalid="form.errors.booking_slug ? true : undefined" :aria-describedby="form.errors.booking_slug ? 'field-onboarding-form-booking-slug-error' : undefined"/></span><FieldError id="field-onboarding-form-booking-slug-error" :message="form.errors.booking_slug" /></label>
                    <label class="text-sm font-semibold">Business type<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="form.business_type" class="cd-input mt-2" id="field-onboarding-form-business-type" :aria-invalid="form.errors.business_type ? true : undefined" :aria-describedby="form.errors.business_type ? 'field-onboarding-form-business-type-error' : undefined"><option value="" disabled>Choose a business type</option><option v-if="form.business_type && !referenceData.business_types[form.business_type]" :value="form.business_type">{{ form.business_type }}</option><option v-for="(label, value) in referenceData.business_types" :key="value" :value="value">{{ label }}</option></AppSelect><FieldError id="field-onboarding-form-business-type-error" :message="form.errors.business_type" /></label>
                    <label class="text-sm font-semibold">Accent colour<input v-model="form.brand_color" type="color" class="cd-input mt-2 h-11 w-full rounded-lg border border-[var(--border-subtle)] bg-white p-1"  id="field-onboarding-form-brand-color" :aria-invalid="form.errors.brand_color ? true : undefined" :aria-describedby="form.errors.brand_color ? 'field-onboarding-form-brand-color-error' : undefined"/><FieldError id="field-onboarding-form-brand-color-error" :message="form.errors.brand_color" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Business description<textarea v-model="form.description" rows="3" maxlength="1200" class="cd-input mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3" placeholder="A short, welcoming introduction for your booking page."  id="field-onboarding-form-description" :aria-invalid="form.errors.description ? true : undefined" :aria-describedby="form.errors.description ? 'field-onboarding-form-description-error' : undefined"/><FieldError id="field-onboarding-form-description-error" :message="form.errors.description" /></label>
                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Client-facing details</h3><p class="mt-1 text-xs text-[var(--text-muted)]">These details appear wherever clients need to identify or contact your business.</p></div>
                    <label class="text-sm font-semibold" for="business-phone">Public phone<span class="text-xs font-normal text-[var(--text-muted)]"> · needed for online booking</span><PhoneInput id="business-phone" v-model="form.phone" class="mt-2" :country="form.country_code || 'IN'" :countries="referenceData.countries"  :aria-invalid="form.errors.phone ? true : undefined" :aria-describedby="form.errors.phone ? 'business-phone-error' : undefined"/><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Saved internationally and reused on booking pages and receipts.</span><FieldError id="business-phone-error" :message="form.errors.phone" /></label>
                    <label class="text-sm font-semibold">Email<span class="text-xs font-normal text-[var(--text-muted)]"> · needed for online booking</span><input v-model="form.email" type="email" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"  id="field-onboarding-form-email" :aria-invalid="form.errors.email ? true : undefined" :aria-describedby="form.errors.email ? 'field-onboarding-form-email-error' : undefined"/><FieldError id="field-onboarding-form-email-error" :message="form.errors.email" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Address<span class="text-xs font-normal text-[var(--text-muted)]"> · needed for online booking</span><textarea v-model="form.address" rows="2" class="cd-input mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3"  id="field-onboarding-form-address" :aria-invalid="form.errors.address ? true : undefined" :aria-describedby="form.errors.address ? 'field-onboarding-form-address-error' : undefined"/><FieldError id="field-onboarding-form-address-error" :message="form.errors.address" /></label>
</div><div v-show="profileGroup==='region'" class="bs-profile-grid">                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Region & money</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Changing country suggests a currency, language and time zone. Review them before saving.</p></div>
                    <label class="text-sm font-semibold">Country<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="form.country_code" class="cd-input mt-2" id="field-onboarding-form-country-code" :aria-invalid="form.errors.country_code ? true : undefined" :aria-describedby="form.errors.country_code ? 'field-onboarding-form-country-code-error' : undefined"><option value="" disabled>Choose a country</option><option v-if="form.country_code && !referenceData.countries[form.country_code]" :value="form.country_code">{{ form.country_code }}</option><option v-for="(label, value) in referenceData.countries" :key="value" :value="value">{{ label }}</option></AppSelect><FieldError id="field-onboarding-form-country-code-error" :message="form.errors.country_code" /></label>
                    <label class="text-sm font-semibold">Language & region<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="form.locale" class="cd-input mt-2" id="field-onboarding-form-locale" :aria-invalid="form.errors.locale ? true : undefined" :aria-describedby="form.errors.locale ? 'field-onboarding-form-locale-error' : undefined"><option value="" disabled>Choose a language and region</option><option v-if="form.locale && !referenceData.locales[form.locale]" :value="form.locale">{{ form.locale }}</option><option v-for="(label, value) in referenceData.locales" :key="value" :value="value">{{ label }}</option></AppSelect><FieldError id="field-onboarding-form-locale-error" :message="form.errors.locale" /></label>
                    <label class="text-sm font-semibold">Currency<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="form.currency_code" class="cd-input mt-2" id="field-onboarding-form-currency-code" :aria-invalid="form.errors.currency_code ? true : undefined" :aria-describedby="form.errors.currency_code ? 'field-onboarding-form-currency-code-error' : undefined"><option value="" disabled>Choose a currency</option><option v-if="form.currency_code && !referenceData.currencies[form.currency_code]" :value="form.currency_code">{{ form.currency_code }}</option><option v-for="(label, value) in referenceData.currencies" :key="value" :value="value">{{ label }}</option></AppSelect><FieldError id="field-onboarding-form-currency-code-error" :message="form.errors.currency_code" /></label>
                    <label class="text-sm font-semibold">Time zone<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="form.time_zone" class="cd-input mt-2" id="field-onboarding-form-time-zone" :aria-invalid="form.errors.time_zone ? true : undefined" :aria-describedby="form.errors.time_zone ? 'field-onboarding-form-time-zone-error' : undefined"><option value="" disabled>Choose a time zone</option><option v-for="zone in referenceData.time_zones" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></AppSelect><FieldError id="field-onboarding-form-time-zone-error" :message="form.errors.time_zone" /></label>
                    <label class="text-sm font-semibold">Week starts<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="form.week_starts_on" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" id="field-onboarding-form-week-starts-on" :aria-invalid="form.errors.week_starts_on ? true : undefined" :aria-describedby="form.errors.week_starts_on ? 'field-onboarding-form-week-starts-on-error' : undefined"><option :value="1">Monday</option><option :value="7">Sunday</option></AppSelect><FieldError id="field-onboarding-form-week-starts-on-error" :message="form.errors.week_starts_on" /></label>
                    <label class="text-sm font-semibold">Appointment interval<AppSelect v-model="form.appointment_interval_minutes" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" id="field-onboarding-form-appointment-interval-minutes" :aria-invalid="form.errors.appointment_interval_minutes ? true : undefined" :aria-describedby="form.errors.appointment_interval_minutes ? 'field-onboarding-form-appointment-interval-minutes-error' : undefined"><option v-for="value in [5,10,15,20,30,60]" :key="value" :value="value">{{ value }} minutes</option></AppSelect><FieldError id="field-onboarding-form-appointment-interval-minutes-error" :message="form.errors.appointment_interval_minutes" /></label>
                    <label class="text-sm font-semibold">Tax treatment<span aria-hidden="true" class="text-[var(--status-danger)]"> *</span><span class="ds-sr-only"> required</span><AppSelect v-model="form.tax_posture" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" id="field-onboarding-form-tax-posture" :aria-invalid="form.errors.tax_posture ? true : undefined" :aria-describedby="form.errors.tax_posture ? 'field-onboarding-form-tax-posture-error' : undefined"><option value="" disabled>Choose</option><option value="inclusive">Prices include tax</option><option value="exclusive">Tax added at checkout</option><option value="not_registered">Not tax registered</option></AppSelect><FieldError id="field-onboarding-form-tax-posture-error" :message="form.errors.tax_posture" /></label>
                    <label v-if="form.tax_posture !== 'not_registered'" class="text-sm font-semibold">Default tax rate<input v-model="taxRatePercent" type="number" min="0" max="100" step="0.01" inputmode="decimal" class="cd-input mt-2"><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Percent applied at checkout when a service or product has no more specific rate.</span></label>
</div><div v-show="profileGroup==='policies'" class="bs-profile-grid">                    <div class="sm:col-span-2 mt-2 border-b border-[var(--border-subtle)] pb-2"><h3 class="font-semibold text-[var(--text-strong)]">Booking policies</h3><p class="mt-1 text-xs text-[var(--text-muted)]">Clients review these before confirming; existing appointments keep their original policy.</p></div>
                    <label class="text-sm font-semibold sm:col-span-2">Cancellation policy<span class="text-xs font-normal text-[var(--text-muted)]"> · needed for online booking</span><textarea v-model="form.default_cancellation_policy" rows="3" class="cd-input mt-2 w-full rounded-lg border border-[var(--border-subtle)] p-3"  id="field-onboarding-form-default-cancellation-policy" :aria-invalid="form.errors.default_cancellation_policy ? true : undefined" :aria-describedby="form.errors.default_cancellation_policy ? 'field-onboarding-form-default-cancellation-policy-error' : undefined"/><FieldError id="field-onboarding-form-default-cancellation-policy-error" :message="form.errors.default_cancellation_policy" /></label>
                    <label class="text-sm font-semibold">Terms link<span class="text-xs font-normal text-[var(--text-muted)]"> · needed for online booking</span><input v-model="form.terms_url" type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"  id="field-onboarding-form-terms-url" :aria-invalid="form.errors.terms_url ? true : undefined" :aria-describedby="form.errors.terms_url ? 'field-onboarding-form-terms-url-error' : undefined"/><FieldError id="field-onboarding-form-terms-url-error" :message="form.errors.terms_url" /></label>
                    <label class="text-sm font-semibold">Privacy link<span class="text-xs font-normal text-[var(--text-muted)]"> · needed for online booking</span><input v-model="form.privacy_url" type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"  id="field-onboarding-form-privacy-url" :aria-invalid="form.errors.privacy_url ? true : undefined" :aria-describedby="form.errors.privacy_url ? 'field-onboarding-form-privacy-url-error' : undefined"/><FieldError id="field-onboarding-form-privacy-url-error" :message="form.errors.privacy_url" /></label>
                    <label class="text-sm font-semibold">Website (optional)<input v-model="form.website_url" type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"  id="field-onboarding-form-website-url" :aria-invalid="form.errors.website_url ? true : undefined" :aria-describedby="form.errors.website_url ? 'field-onboarding-form-website-url-error' : undefined"/><FieldError id="field-onboarding-form-website-url-error" :message="form.errors.website_url" /></label>
                    <label class="text-sm font-semibold">Map link (optional)<input v-model="form.map_url" type="url" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"  id="field-onboarding-form-map-url" :aria-invalid="form.errors.map_url ? true : undefined" :aria-describedby="form.errors.map_url ? 'field-onboarding-form-map-url-error' : undefined"/><FieldError id="field-onboarding-form-map-url-error" :message="form.errors.map_url" /></label>
</div>                    <p v-if="Object.keys(form.errors).length" class="sm:col-span-2 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{Object.values(form.errors).join(' ')}}</p>
                    <div class="bs-save-bar"><span>{{form.processing?'Saving…':form.isDirty?'Unsaved profile changes':'Your saved business profile'}}</span><AppButton type="submit" :loading="form.processing">Save business profile</AppButton></div>
                </form>
            </SurfaceCard>

        </div>

        <SurfaceCard v-show="activeSection === 'branding'" title="Make it yours" description="Optional. A logo or photo never blocks appointments."><div class="bs-branding">                    <div class="sm:col-span-2">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label v-for="asset in [{ kind: 'logo', title: 'Business logo', note: 'Square PNG, JPEG or WebP works best.', saved: business.logo_path }, { kind: 'cover', title: 'Booking cover', note: 'Use a bright, natural wide image of your space or work.', saved: business.cover_image_path }]" :key="asset.kind" :class="['group flex min-h-11 items-start gap-3 rounded-2xl border border-dashed border-[var(--border-strong)] bg-[var(--surface-subtle)] p-4 transition', $page.props.tenant.entitlements?.['branding.custom'] === false ? 'cursor-not-allowed opacity-70' : 'cursor-pointer hover:border-[var(--brand-primary)] hover:bg-[var(--status-info-soft)]']">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-[var(--brand-primary)] shadow-sm"><PaintBrushIcon class="size-5" aria-hidden="true" /></span>
                                <span class="min-w-0 flex-1"><strong class="block text-sm text-[var(--text-strong)]">{{ asset.title }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ asset.saved ? 'Image added — choose another to replace it.' : asset.note }}</span><span class="mt-3 inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-[var(--brand-primary)]">{{ $page.props.tenant.entitlements?.['branding.custom'] === false ? 'Available on Pro' : (asset.saved ? 'Replace image' : 'Choose image') }}</span></span>
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="ds-sr-only" :disabled="brandUpload.processing || $page.props.tenant.entitlements?.['branding.custom'] === false" @change="uploadBrandAsset(asset.kind, $event)" />
                            </label>
                        </div>
                        <div v-if="$page.props.tenant.entitlements?.['branding.custom'] === false" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-3"><p class="text-xs leading-5 text-[var(--text-muted)]">Custom logo and cover uploads are included on Pro.</p><AppButton v-if="$page.props.tenant.can_manage_billing" :href="route('business.billing.show', business.public_id)" variant="secondary">See plan options</AppButton></div>
                        <p v-if="brandUpload.errors.asset || brandUpload.errors.kind" class="mt-2 text-sm text-[var(--status-danger)]" role="alert">{{ brandUpload.errors.asset || brandUpload.errors.kind }}</p>
                    </div>
<p v-if="brandUpload.errors.asset" class="bs-callout attention" role="alert">{{brandUpload.errors.asset}}</p><AppButton variant="quiet" @click="selectSection('overview')">Do this later</AppButton></div></SurfaceCard>
        <SurfaceCard v-show="activeSection === 'booking_rules'" id="booking_rules" class="mt-6" title="Booking preferences" description="Your calendar interval comes first. Review online choices when you’re ready to publish.">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="savePublicPolicy">
                <label class="text-sm font-semibold">Calendar interval<AppSelect v-model="publicPolicyForm.appointment_interval_minutes" class="cd-input mt-2" id="bs-booking-interval" :aria-invalid="publicPolicyForm.errors.appointment_interval_minutes ? true : undefined" aria-describedby="bs-booking-interval-error"><option v-for="minutes in [5,10,15,20,30,60]" :key="minutes" :value="minutes">{{minutes}} minutes</option></AppSelect><FieldError id="bs-booking-interval-error" :message="publicPolicyForm.errors.appointment_interval_minutes" /></label>
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.online_booking_enabled" type="checkbox" />Accept online bookings</label>
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="publicPolicyForm.staff_gender_request_enabled" type="checkbox" />Allow staff gender requests</label>
                <label class="text-sm font-semibold">Staff choice<AppSelect v-model="publicPolicyForm.online_staff_preference" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" id="field-onboarding-publicpolicyform-online-staff-preference" :aria-invalid="publicPolicyForm.errors.online_staff_preference ? true : undefined" :aria-describedby="publicPolicyForm.errors.online_staff_preference ? 'field-onboarding-publicpolicyform-online-staff-preference-error' : undefined"><option value="any_or_preferred">Any or preferred</option><option value="any_only">First available only</option><option value="preferred_required">Client must choose</option></AppSelect><FieldError id="field-onboarding-publicpolicyform-online-staff-preference-error" :message="publicPolicyForm.errors.online_staff_preference" /></label>
                <label class="text-sm font-semibold">Price display<AppSelect v-model="publicPolicyForm.online_price_display" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" id="field-onboarding-publicpolicyform-online-price-display" :aria-invalid="publicPolicyForm.errors.online_price_display ? true : undefined" :aria-describedby="publicPolicyForm.errors.online_price_display ? 'field-onboarding-publicpolicyform-online-price-display-error' : undefined"><option value="service_setting">Per service setting</option><option value="exact">Exact price</option><option value="from">From price</option></AppSelect><FieldError id="field-onboarding-publicpolicyform-online-price-display-error" :message="publicPolicyForm.errors.online_price_display" /></label>
                <label class="text-sm font-semibold">New clients<AppSelect v-model="publicPolicyForm.online_new_client_rule" class="mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3" id="field-onboarding-publicpolicyform-online-new-client-rule" :aria-invalid="publicPolicyForm.errors.online_new_client_rule ? true : undefined" :aria-describedby="publicPolicyForm.errors.online_new_client_rule ? 'field-onboarding-publicpolicyform-online-new-client-rule-error' : undefined"><option value="allow">Allow</option><option value="consultation_only">Consultation only</option><option value="existing_only">Existing clients only</option></AppSelect><FieldError id="field-onboarding-publicpolicyform-online-new-client-rule-error" :message="publicPolicyForm.errors.online_new_client_rule" /></label>
                <label class="text-sm font-semibold">Cancellation cutoff<AppSelect v-model="publicPolicyForm.cancellation_cutoff_minutes" class="cd-input mt-2" id="field-onboarding-publicpolicyform-cancellation-cutoff-minutes" :aria-invalid="publicPolicyForm.errors.cancellation_cutoff_minutes ? true : undefined" :aria-describedby="publicPolicyForm.errors.cancellation_cutoff_minutes ? 'field-onboarding-publicpolicyform-cancellation-cutoff-minutes-error' : undefined"><option :value="0">Any time</option><option :value="360">6 hours before</option><option :value="720">12 hours before</option><option :value="1440">24 hours before</option><option :value="2880">48 hours before</option><option :value="10080">7 days before</option></AppSelect><FieldError id="field-onboarding-publicpolicyform-cancellation-cutoff-minutes-error" :message="publicPolicyForm.errors.cancellation_cutoff_minutes" /></label>
                <details class="sm:col-span-2 lg:col-span-4"><summary class="bs-text-link min-h-11">More online preferences</summary><div class="mt-4 grid gap-4 sm:grid-cols-2">                <label class="text-sm font-semibold">Clients notified per opening<input v-model="publicPolicyForm.waitlist_offer_batch_size" type="number" min="1" max="10" class="cd-input mt-2 min-h-11 w-full rounded-lg border border-[var(--border-subtle)] px-3"  id="field-onboarding-publicpolicyform-waitlist-offer-batch-size" :aria-invalid="publicPolicyForm.errors.waitlist_offer_batch_size ? true : undefined" :aria-describedby="publicPolicyForm.errors.waitlist_offer_batch_size ? 'field-onboarding-publicpolicyform-waitlist-offer-batch-size-error' : undefined"/><FieldError id="field-onboarding-publicpolicyform-waitlist-offer-batch-size-error" :message="publicPolicyForm.errors.waitlist_offer_batch_size" /></label>
                <label class="text-sm font-semibold">Appointment link expires after<AppSelect v-model="publicPolicyForm.public_link_ttl_minutes" class="cd-input mt-2" id="field-onboarding-publicpolicyform-public-link-ttl-minutes" :aria-invalid="publicPolicyForm.errors.public_link_ttl_minutes ? true : undefined" :aria-describedby="publicPolicyForm.errors.public_link_ttl_minutes ? 'field-onboarding-publicpolicyform-public-link-ttl-minutes-error' : undefined"><option :value="60">1 hour</option><option :value="1440">1 day</option><option :value="4320">3 days</option><option :value="10080">7 days</option><option :value="20160">14 days</option><option :value="43200">30 days</option></AppSelect><FieldError id="field-onboarding-publicpolicyform-public-link-ttl-minutes-error" :message="publicPolicyForm.errors.public_link_ttl_minutes" /></label>
</div></details>
                <p v-if="Object.keys(publicPolicyForm.errors).length" class="bs-callout attention sm:col-span-2 lg:col-span-4" role="alert">{{Object.values(publicPolicyForm.errors).join(' ')}}</p>
                <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><AppButton type="submit" :disabled="publicPolicyForm.processing">Save changes</AppButton></div>
            </form>
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
            <p class="mt-4 text-sm text-[var(--text-muted)]">{{ imports.length ? `${imports.length} recent ${imports.length === 1 ? 'import' : 'imports'}.` : 'Importing data is optional.' }}</p>
        </SurfaceCard>

        <SurfaceCard v-show="activeSection === 'preview'" id="preview" class="mt-6" title="Your online booking page" description="A private layout preview. Review your services, prices, hours and policies before going live.">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-canvas)] p-5" aria-label="Desktop booking preview">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--text-muted)]">Desktop preview</p>
                    <div class="mt-4 flex items-start justify-between gap-4"><div><h3 class="text-xl font-bold text-[var(--text-strong)]">{{ business.name }}</h3><p class="mt-1 text-sm text-[var(--text-muted)]">{{ business.address || 'Add an address' }}</p></div><span class="rounded-full bg-[var(--brand-primary)] px-3 py-1 text-xs font-semibold text-white">Book</span></div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2"><article v-for="service in previewServices.slice(0, 6)" :key="service.public_id" class="rounded-xl bg-[var(--surface-raised)] p-4"><p class="font-semibold text-[var(--text-strong)]">{{ service.name }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ service.duration_minutes + service.processing_minutes }} minutes · {{ business.currency_code }} {{ (service.price_minor / 100).toFixed(2) }}</p></article><p v-if="!previewServices.length" class="text-sm text-[var(--text-muted)]">No service has an online booking path yet. Review staff, assignments and hours.</p></div>
                </section>
                <section class="mx-auto w-full max-w-72 rounded-xl border-4 border-[var(--text-strong)] bg-[var(--surface-canvas)] p-4 shadow-[var(--shadow-raised)]" aria-label="Mobile booking preview">
                    <p class="text-center text-xs font-semibold uppercase tracking-[0.12em] text-[var(--text-muted)]">Mobile preview</p><h3 class="mt-4 text-lg font-bold text-[var(--text-strong)]">{{ business.name }}</h3><p class="mt-1 text-xs text-[var(--text-muted)]">{{ locations[0]?.name || 'Your location' }} · {{ business.time_zone }}</p><div class="mt-4 space-y-2"><p v-for="service in previewServices.slice(0, 3)" :key="service.public_id" class="rounded-lg bg-[var(--surface-raised)] p-3 text-sm font-semibold">{{ service.name }}</p><p v-if="!previewServices.length" class="rounded-lg bg-[var(--surface-raised)] p-3 text-sm text-[var(--text-muted)]">Review online availability</p></div>
                </section>
            </div>
            <div class="bs-preview-context"><section><h4>Opening week · {{locations[0]?.name || 'Your location'}}</h4><dl><div v-for="day in previewWeek" :key="day.day_of_week"><dt>{{day.label}}</dt><dd>{{day.open ? day.periods.map(period=>`${period.opens_at}–${period.closes_at}`).join(', ') : 'Closed'}}</dd></div></dl></section><section><h4>Client information</h4><p>{{business.default_cancellation_policy || 'Add a cancellation policy before publishing.'}}</p><div class="flex flex-wrap gap-4 mt-3"><a v-if="business.terms_url" :href="business.terms_url" target="_blank" rel="noopener">Terms</a><a v-if="business.privacy_url" :href="business.privacy_url" target="_blank" rel="noopener">Privacy</a></div><p class="bs-muted mt-3">Base prices shown. Staff and location price differences appear during booking. Available times are calculated when a client books.</p></section></div>
            <div class="mt-5 flex flex-wrap gap-3">
                <AppButton variant="secondary" :disabled="publishBusy" @click="markPreviewed">{{onboarding.previewed_at?'Preview reviewed':'I reviewed this preview'}}</AppButton>
                <AppButton v-if="summary.online_state!=='Live'" :disabled="!readiness.publishable || publishBusy" @click="publish">{{publishBusy?'Saving…':business.configuration_published_at?'Resume online booking':'Go live with online booking'}}</AppButton><AppButton v-if="summary.online_state==='Live'" variant="secondary" @click="copyBookingLink">{{copiedBookingLink?'Link copied':'Copy booking link'}}</AppButton><AppButton :href="route('business.calendar',business.public_id)" variant="quiet">Open calendar</AppButton>
            </div>
            <p v-if="publishError" class="bs-callout attention" role="alert">{{publishError}}</p><div class="bs-public-state"><span class="bs-status neutral">{{summary.online_state}}</span><a v-if="summary.online_state==='Live'" :href="bookingUrl" target="_blank" rel="noopener">{{bookingUrl}}</a><p v-else-if="!business.configuration_published_at">Your page stays private until you explicitly go live.</p><p v-else-if="!business.online_booking_enabled">Online bookings are paused. Review your page before resuming.</p><p v-else>Your page is published. Review the issues below before accepting new online bookings.</p></div><ul v-if="readiness.blockers.length" class="bs-online-blockers"><li v-for="item in readiness.blockers" :key="item.code"><span>{{item.message}}</span><button v-if="item.step!=='preview'" type="button" @click="selectSection(sectionForStep(item.step))">Review<ChevronRightIcon class="size-3" aria-hidden="true" /></button></li></ul>
        </SurfaceCard>
        </template>
        <AppDialog ref="discardDialog" title="Discard unsaved setup changes?" description="Your saved settings stay as they are." confirm-label="Discard changes" @confirm="discardChanges" />
        </div>
    </AppLayout>
</template>
