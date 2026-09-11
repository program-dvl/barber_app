<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArchiveBoxIcon, CheckCircleIcon, PlusIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ business: Object, locations: Array, staff: Array, services: Array, readiness: Object });
const editing = ref(null);
const amount = ref(0);
const defaults = () => ({
    category: 'Services', name: '', description: '', price_type: 'fixed', price_minor: 0,
    duration_minutes: 30, processing_minutes: 0, cleanup_minutes: 5,
    minimum_notice_minutes: 0, maximum_advance_days: 60, deposit_type: 'none', deposit_value: 0,
    client_eligibility: 'all', consultation_required: false, online_visible: true, tax_category: '',
    location_ids: props.locations.map(item => item.public_id), staff_ids: props.staff.filter(item => item.has_working_hours).map(item => item.public_id),
});
const form = useForm(defaults());
const readyProviders = computed(() => props.staff.filter(item => item.has_working_hours));
const isReady = computed(() => !(props.readiness.blockers || []).some(item => item.code.startsWith('services.')));
const money = value => new Intl.NumberFormat(undefined, { style: 'currency', currency: props.business.currency_code || 'INR' }).format(value / 100);
const reset = () => { editing.value = null; form.defaults(defaults()); form.reset(); form.clearErrors(); amount.value = 0; };
const edit = service => {
    editing.value = service;
    amount.value = service.price_minor / 100;
    Object.assign(form, {
        ...defaults(), ...service, category: service.category || 'Services',
        location_ids: service.locations.map(item => item.public_id), staff_ids: service.staff.map(item => item.public_id),
    });
    document.querySelector('#service-editor')?.scrollIntoView({ behavior: 'smooth' });
};
const save = () => {
    form.price_minor = Math.round((Number(amount.value) || 0) * 100);
    const options = { preserveScroll: true, onSuccess: reset };
    if (editing.value) form.put(route('business.services.update', [props.business.public_id, editing.value.public_id]), options);
    else form.post(route('business.services.store', props.business.public_id), options);
};
const toggle = service => router.patch(route('business.services.status', [props.business.public_id, service.public_id]), { active: !service.is_active }, { preserveScroll: true });
</script>

<template>
    <AppLayout title="Services" :business-label="business.name">
        <PageHeader eyebrow="Salon setup · Step 4" title="Services clients can understand and book" description="Create the offer once, then connect it to the locations and qualified providers that can actually deliver it.">
            <template #actions><AppButton :href="route('business.configuration.show', business.public_id)" variant="secondary">Back to launch plan</AppButton></template>
        </PageHeader>

        <div v-if="!locations.length || !readyProviders.length" class="mt-6 rounded-xl bg-[var(--status-warning-soft)] p-5 text-sm text-[var(--status-warning)]"><strong>Complete the delivery path first.</strong><p class="mt-1">A bookable service needs an active location and at least one provider with working hours.</p><div class="mt-4 flex flex-wrap gap-3"><AppButton v-if="!locations.length" :href="route('business.locations.index', business.public_id)" variant="secondary">Set up location</AppButton><AppButton v-if="!readyProviders.length" :href="route('business.team.index', business.public_id)" variant="secondary">Add provider</AppButton></div></div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(24rem,0.9fr)]">
            <SurfaceCard title="Service catalogue" :description="`${services.length} service${services.length === 1 ? '' : 's'} · archived services retain booking history`">
                <div v-if="!services.length" class="rounded-xl border border-dashed border-[var(--border-strong)] p-8 text-center"><WrenchScrewdriverIcon class="mx-auto size-8 text-[var(--text-muted)]" /><p class="mt-3 font-semibold">Create your first service</p><p class="mt-1 text-sm text-[var(--text-muted)]">Start with the service clients request most often.</p></div>
                <ul v-else class="divide-y divide-[var(--border-subtle)]"><li v-for="service in services" :key="service.public_id" class="py-5 first:pt-0 last:pb-0"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><p class="font-semibold text-[var(--text-strong)]">{{ service.name }}</p><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', service.is_active && service.online_visible ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']">{{ service.is_active ? (service.online_visible ? 'Online' : 'Internal only') : 'Archived' }}</span></div><p class="mt-1 text-sm text-[var(--text-muted)]">{{ service.category || 'Uncategorised' }} · {{ service.duration_minutes + service.processing_minutes + service.cleanup_minutes }} min · {{ money(service.price_minor) }}</p><p class="mt-2 text-xs text-[var(--text-muted)]">{{ service.locations.map(item => item.name).join(', ') || 'No location' }} · {{ service.staff.map(item => item.display_name).join(', ') || 'No provider' }}</p></div><div class="flex gap-2"><AppButton variant="secondary" @click="edit(service)">Edit</AppButton><AppButton variant="quiet" @click="toggle(service)"><ArchiveBoxIcon class="size-4" />{{ service.is_active ? 'Archive' : 'Restore' }}</AppButton></div></div></li></ul>
            </SurfaceCard>

            <SurfaceCard id="service-editor" :title="editing ? `Edit ${editing.name}` : 'Add a service'" description="Pricing follows the salon currency and tax posture saved in Business profile.">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <label class="text-sm font-semibold">Service name<input v-model="form.name" required class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Category<input v-model="form.category" required class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Description<textarea v-model="form.description" rows="2" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Price ({{ business.currency_code }})<input v-model="amount" required min="0" step="0.01" type="number" inputmode="decimal" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Price display<select v-model="form.price_type" class="cd-input mt-2"><option value="fixed">Fixed price</option><option value="from">Starting from</option></select></label>
                    <label class="text-sm font-semibold">Active time<input v-model="form.duration_minutes" required min="1" type="number" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Cleanup time<input v-model="form.cleanup_minutes" required min="0" type="number" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Processing time<input v-model="form.processing_minutes" required min="0" type="number" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Book up to<select v-model="form.maximum_advance_days" class="cd-input mt-2"><option :value="30">30 days ahead</option><option :value="60">60 days ahead</option><option :value="90">90 days ahead</option><option :value="365">1 year ahead</option></select></label>
                    <fieldset class="sm:col-span-2"><legend class="text-sm font-semibold">Available at</legend><div class="mt-2 flex flex-wrap gap-2"><label v-for="location in locations" :key="location.public_id" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-[var(--border-subtle)] px-3 text-sm"><input v-model="form.location_ids" type="checkbox" :value="location.public_id" />{{ location.name }}</label></div></fieldset>
                    <fieldset class="sm:col-span-2"><legend class="text-sm font-semibold">Qualified providers</legend><div class="mt-2 flex flex-wrap gap-2"><label v-for="person in readyProviders" :key="person.public_id" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-[var(--border-subtle)] px-3 text-sm"><input v-model="form.staff_ids" type="checkbox" :value="person.public_id" />{{ person.display_name }}</label></div></fieldset>
                    <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold sm:col-span-2"><input v-model="form.online_visible" type="checkbox" />Show this service in online booking</label>
                    <p v-if="Object.keys(form.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)] sm:col-span-2" role="alert">Review the service, location and qualified-provider fields.</p>
                    <div class="flex flex-wrap justify-end gap-3 sm:col-span-2"><AppButton v-if="editing" variant="quiet" @click="reset">Cancel</AppButton><AppButton type="submit" :disabled="form.processing || !locations.length || !readyProviders.length"><PlusIcon v-if="!editing" class="size-4" />{{ form.processing ? 'Saving…' : (editing ? 'Save changes' : 'Add service') }}</AppButton></div>
                </form>
            </SurfaceCard>
        </div>

        <div v-if="isReady" class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-[var(--status-success-soft)] p-4 text-sm text-[var(--status-success)]"><span class="flex items-center gap-3"><CheckCircleIcon class="size-5" /><strong>Your first delivery path is bookable.</strong></span><AppButton :href="route('business.configuration.show', business.public_id) + '?section=preview'" variant="secondary">Review and publish</AppButton></div>
    </AppLayout>
</template>
