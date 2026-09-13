<script setup>
import AppSelect from '@/Components/Product/AppSelect.vue';
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { BuildingStorefrontIcon, CheckCircleIcon, ClockIcon, MapPinIcon, PlusIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ business: Object, locations: Array, readiness: Object, timeZones: Array, locationAllowance: Object });
const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const first = props.locations[0];
const firstHours = first?.hours || [];
const form = useForm({
    location: first?.public_id || null,
    create_new: false,
    name: first?.name || props.business.name || '',
    address: first?.address || props.business.address || '',
    time_zone: first?.time_zone || props.business.time_zone || '',
    phone: first?.phone || props.business.phone || '',
    email: first?.email || props.business.email || '',
    working_days: firstHours.length ? [...new Set(firstHours.map(item => item.day_of_week))] : [1, 2, 3, 4, 5, 6],
    opens_at: firstHours[0]?.opens_at?.slice(0, 5) || '09:00',
    closes_at: firstHours[0]?.closes_at?.slice(0, 5) || '18:00',
});
const isReady = computed(() => !(props.readiness.blockers || []).some(item => item.code.startsWith('locations.')));
const editingNew = computed(() => form.create_new);
const nextLocationName = () => props.locations.length ? `${props.business.name} · Location ${props.locations.length + 1}` : props.business.name;
const resetLocation = (location = null) => {
    const hours = location?.hours || [];
    form.clearErrors();
    Object.assign(form, {
        location: location?.public_id || null,
        create_new: !location,
        name: location?.name || nextLocationName(),
        address: location?.address || props.business.address || '',
        time_zone: location?.time_zone || props.business.time_zone || '',
        phone: location?.phone || props.business.phone || '',
        email: location?.email || props.business.email || '',
        working_days: hours.length ? [...new Set(hours.map(item => item.day_of_week))] : [1, 2, 3, 4, 5, 6],
        opens_at: hours[0]?.opens_at?.slice(0, 5) || '09:00',
        closes_at: hours[0]?.closes_at?.slice(0, 5) || '18:00',
    });
};
const selectLocation = event => {
    const location = props.locations.find(item => item.public_id === event.target.value);
    if (location) resetLocation(location);
};
const startNew = () => props.locationAllowance?.can_add && resetLocation();
const save = () => form.post(route('business.locations.activation.store', props.business.public_id), {
    preserveScroll: true,
    onSuccess: () => { if (editingNew.value) form.create_new = false; },
});
</script>

<template>
    <AppLayout title="Locations" :business-label="business.name">
        <PageHeader eyebrow="Business setup · Locations" title="Locations & opening hours" description="Keep each location's address, local time and opening hours accurate so calendars, booking and reports stay aligned.">
            <template #actions><AppButton :href="route('business.configuration.show', business.public_id)" variant="secondary">Back to launch plan</AppButton><AppButton v-if="locationAllowance?.can_add" @click="startNew"><PlusIcon class="size-5" />Add location</AppButton><AppButton v-else :href="route('business.billing.show', business.public_id)" variant="secondary"><PlusIcon class="size-5" />Add location · plan limit</AppButton></template>
        </PageHeader>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <SurfaceCard :title="editingNew ? 'Add another location' : 'Location details'" :description="editingNew ? 'We copied useful business defaults; review them and save.' : 'These details appear in public booking, confirmations and calendar context.'">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <label v-if="locations.length && !editingNew" class="text-sm font-semibold sm:col-span-2">Location to edit<AppSelect v-model="form.location" class="cd-input mt-2" @change="selectLocation"><option v-for="location in locations" :key="location.public_id" :value="location.public_id">{{ location.name }}</option></AppSelect></label>
                    <label class="text-sm font-semibold">Location name<input v-model="form.name" required :class="['cd-input mt-2', form.errors.name ? 'border-[var(--status-danger)]' : '']" /><span v-if="form.errors.name" class="mt-1 block text-xs text-[var(--status-danger)]" role="alert">{{ form.errors.name }}</span></label>
                    <label class="text-sm font-semibold">Time zone<AppSelect v-model="form.time_zone" required class="cd-input mt-2"><option value="" disabled>Choose a time zone</option><option v-for="zone in timeZones" :key="zone" :value="zone">{{ zone.replaceAll('_', ' ') }}</option></AppSelect></label>
                    <label class="text-sm font-semibold sm:col-span-2">Address<textarea v-model="form.address" required rows="2" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Public phone<PhoneInput id="location-phone" v-model="form.phone" class="mt-2" :country="business.country_code || 'IN'" /></label>
                    <label class="text-sm font-semibold">Public email<input v-model="form.email" type="email" class="cd-input mt-2" /></label>
                    <div class="sm:col-span-2 border-t border-[var(--border-subtle)] pt-5">
                        <h3 class="font-semibold text-[var(--text-strong)]">Normal opening hours</h3>
                        <p class="mt-1 text-sm text-[var(--text-muted)]">Choose the days that share this first schedule. Split shifts and exceptions remain available after launch.</p>
                    </div>
                    <fieldset class="sm:col-span-2"><legend class="ds-sr-only">Working days</legend><div class="flex flex-wrap gap-2"><label v-for="(day, index) in days" :key="day" :class="['inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-semibold', form.working_days.includes(index + 1) ? 'border-[var(--action-primary)] bg-[var(--surface-subtle)] text-[var(--action-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="form.working_days" class="sr-only" type="checkbox" :value="index + 1" />{{ day }}</label></div></fieldset>
                    <label class="text-sm font-semibold">Opens<input v-model="form.opens_at" required type="time" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Closes<input v-model="form.closes_at" required type="time" class="cd-input mt-2" /></label>
                    <p v-if="Object.keys(form.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)] sm:col-span-2" role="alert">Review the highlighted location and hours fields.</p>
                    <div class="flex flex-wrap justify-end gap-3 sm:col-span-2"><AppButton v-if="editingNew && locations.length" variant="quiet" @click="resetLocation(first)">Discard</AppButton><AppButton type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : (editingNew ? 'Add location' : 'Save changes') }}</AppButton><AppButton v-if="isReady && !editingNew" :href="route('business.team.index', business.public_id)" variant="secondary">Next: add provider</AppButton></div>
                </form>
            </SurfaceCard>

            <div class="space-y-5">
                <SurfaceCard title="Your locations" compact><ul class="space-y-2"><li v-for="location in locations" :key="location.public_id"><button type="button" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-2 text-left hover:bg-[var(--surface-subtle)]" @click="resetLocation(location)"><span class="grid size-9 place-items-center rounded-full bg-[var(--surface-subtle)] text-[var(--action-primary)]"><BuildingStorefrontIcon class="size-5" /></span><span><strong class="block text-sm">{{ location.name }}</strong><span class="block text-xs text-[var(--text-muted)]">{{ location.time_zone }}</span></span></button></li></ul><button v-if="locationAllowance?.can_add" type="button" class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-[var(--border-strong)] text-sm font-semibold text-[var(--action-primary)]" @click="startNew"><PlusIcon class="size-4" />Add location</button><div v-else class="mt-3 rounded-xl bg-[var(--surface-subtle)] p-3 text-xs leading-5 text-[var(--text-muted)]"><strong class="text-[var(--text-strong)]">{{ locationAllowance?.used }} of {{ locationAllowance?.limit || locationAllowance?.used }} locations active.</strong><br>Your current plan is at its location allowance. <a :href="route('business.billing.show', business.public_id)" class="font-semibold text-[var(--action-primary)]">Review plan options</a>.</div></SurfaceCard>
                <SurfaceCard compact><div class="flex gap-3"><span :class="['grid size-10 shrink-0 place-items-center rounded-full', isReady ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]']"><CheckCircleIcon v-if="isReady" class="size-5" /><ClockIcon v-else class="size-5" /></span><div><p class="font-semibold text-[var(--text-strong)]">{{ isReady ? 'Locations ready' : 'Location needs hours' }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">Providers and services use these locations to create accurate booking times.</p></div></div></SurfaceCard>
                <SurfaceCard title="What clients see" compact><div class="flex gap-3"><MapPinIcon class="size-5 shrink-0 text-[var(--action-primary)]" /><div><p class="font-semibold">{{ form.name || 'Your location' }}</p><p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">{{ form.address || 'Add the public address' }}<br>{{ form.time_zone || 'Choose a time zone' }}</p></div></div></SurfaceCard>
            </div>
        </div>
    </AppLayout>
</template>
