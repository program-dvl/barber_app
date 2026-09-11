<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CheckCircleIcon, UserPlusIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ business: Object, locations: Array, services: Array, staff: Array, readiness: Object });
const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const firstLocation = props.locations[0];
const locationHours = firstLocation?.hours || [];
const form = useForm({
    display_name: '', email: '', mobile: '', title: '', online_visible: true,
    location: firstLocation?.public_id || '', service_ids: [],
    working_days: locationHours.length ? [...new Set(locationHours.map(item => item.day_of_week))] : [1, 2, 3, 4, 5, 6],
    starts_at: locationHours[0]?.opens_at?.slice(0, 5) || '09:00', ends_at: locationHours[0]?.closes_at?.slice(0, 5) || '18:00',
});
const isReady = computed(() => !(props.readiness.blockers || []).some(item => item.code.startsWith('staff.')));
const save = () => form.post(route('business.team.providers.store', props.business.public_id), { preserveScroll: true, onSuccess: () => form.reset('display_name', 'email', 'mobile', 'title') });
</script>

<template>
    <AppLayout title="Team & availability" :business-label="business.name">
        <PageHeader eyebrow="Salon setup · Step 3" title="Add the people clients can book" description="A provider profile controls bookability. Login access is separate, so you can schedule a team member now and invite them to the workspace later.">
            <template #actions><AppButton :href="route('business.configuration.show', business.public_id)" variant="secondary">Back to launch plan</AppButton></template>
        </PageHeader>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(22rem,1.1fr)]">
            <SurfaceCard title="Add a provider" description="Start with their normal weekly hours. Breaks, leave and exceptions can be layered on later.">
                <div v-if="!locations.length" class="rounded-xl bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]"><strong>Add a location first.</strong><p class="mt-1">A provider needs a place and local schedule before they can receive bookings.</p><AppButton class="mt-4" :href="route('business.locations.index', business.public_id)" variant="secondary">Set up location</AppButton></div>
                <form v-else class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <label class="text-sm font-semibold">Display name<input v-model="form.display_name" required class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Role or title<input v-model="form.title" placeholder="Senior stylist" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Email<input v-model="form.email" required type="email" class="cd-input mt-2" /><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Used to safely retry this setup; no login is created.</span></label>
                    <label class="text-sm font-semibold">Mobile (optional)<PhoneInput id="provider-phone" v-model="form.mobile" class="mt-2" :country="business.country_code || 'IN'" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Works at<select v-model="form.location" required class="cd-input mt-2"><option v-for="location in locations" :key="location.public_id" :value="location.public_id">{{ location.name }} · {{ location.time_zone }}</option></select></label>
                    <fieldset v-if="services.length" class="sm:col-span-2"><legend class="text-sm font-semibold">Services they can perform (optional now)</legend><div class="mt-2 flex flex-wrap gap-2"><label v-for="service in services" :key="service.public_id" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-[var(--border-subtle)] px-3 text-sm"><input v-model="form.service_ids" type="checkbox" :value="service.public_id" />{{ service.name }}</label></div></fieldset>
                    <div class="sm:col-span-2 border-t border-[var(--border-subtle)] pt-5"><h3 class="font-semibold">Normal weekly availability</h3><p class="mt-1 text-sm text-[var(--text-muted)]">Times are interpreted in the selected location's time zone.</p></div>
                    <fieldset class="sm:col-span-2"><legend class="ds-sr-only">Working days</legend><div class="flex flex-wrap gap-2"><label v-for="(day, index) in days" :key="day" :class="['inline-flex min-h-11 cursor-pointer items-center rounded-lg border px-3 text-sm font-semibold', form.working_days.includes(index + 1) ? 'border-[var(--action-primary)] bg-[var(--surface-subtle)] text-[var(--action-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="form.working_days" class="sr-only" type="checkbox" :value="index + 1" />{{ day }}</label></div></fieldset>
                    <label class="text-sm font-semibold">Starts<input v-model="form.starts_at" required type="time" class="cd-input mt-2" /></label>
                    <label class="text-sm font-semibold">Ends<input v-model="form.ends_at" required type="time" class="cd-input mt-2" /></label>
                    <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold sm:col-span-2"><input v-model="form.online_visible" type="checkbox" />Let clients choose this provider online</label>
                    <p v-if="Object.keys(form.errors).length" class="rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)] sm:col-span-2" role="alert">Review the provider and schedule details.</p>
                    <div class="flex flex-wrap justify-end gap-3 sm:col-span-2"><AppButton type="submit" :disabled="form.processing"><UserPlusIcon class="size-4" />{{ form.processing ? 'Saving…' : 'Save provider' }}</AppButton><AppButton v-if="isReady" :href="route('business.services.index', business.public_id)" variant="secondary">Next: add service</AppButton></div>
                </form>
            </SurfaceCard>

            <SurfaceCard title="Team" :description="`${staff.length} provider profile${staff.length === 1 ? '' : 's'} · access and scheduling stay separate`">
                <div v-if="!staff.length" class="rounded-xl border border-dashed border-[var(--border-strong)] p-8 text-center"><UserPlusIcon class="mx-auto size-8 text-[var(--text-muted)]" /><p class="mt-3 font-semibold">Add your first provider</p><p class="mt-1 text-sm text-[var(--text-muted)]">They will become available for service assignment as soon as their schedule is saved.</p></div>
                <ul v-else class="divide-y divide-[var(--border-subtle)]"><li v-for="person in staff" :key="person.public_id" class="py-4 first:pt-0 last:pb-0"><div class="flex items-start justify-between gap-4"><div><p class="font-semibold text-[var(--text-strong)]">{{ person.display_name }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ person.title || 'Service provider' }} · {{ person.locations.map(item => item.name).join(', ') || 'No location' }}</p></div><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', person.status === 'active' ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']">{{ person.status }}</span></div><div class="mt-3 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-[var(--surface-subtle)] px-2.5 py-1">{{ person.has_login ? 'Workspace access connected' : 'No login access' }}</span><span class="rounded-full bg-[var(--surface-subtle)] px-2.5 py-1">{{ person.availability.filter(item => item.kind === 'working').length }} working days</span><span class="rounded-full bg-[var(--surface-subtle)] px-2.5 py-1">{{ person.services.length }} services</span></div></li></ul>
            </SurfaceCard>
        </div>
        <div v-if="isReady" class="mt-6 flex items-center gap-3 rounded-xl bg-[var(--status-success-soft)] p-4 text-sm text-[var(--status-success)]"><CheckCircleIcon class="size-5" /><strong>Team readiness complete.</strong> At least one active provider has a location and working hours.</div>
    </AppLayout>
</template>
