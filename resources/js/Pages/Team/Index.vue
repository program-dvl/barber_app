<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowPathIcon, CheckCircleIcon, ChevronRightIcon, ClockIcon, KeyIcon, ShieldCheckIcon, SparklesIcon, UserPlusIcon, UsersIcon } from '@heroicons/vue/24/outline';
import AppSelect from '@/Components/Product/AppSelect.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    business: Object, locations: Array, services: Array, staff: Array, readiness: Object,
    roleSuggestions: Array, accessRoles: Array, accessModules: Array, pendingInvitations: Array,
    seatAllowance: Object, canManageOwners: Boolean, activityPreview: Array,
});
const editor = ref(null);
const accessEditor = ref(null);
const selectedPerson = ref(null);
const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const firstLocation = props.locations[0];
const locationHours = firstLocation?.hours || [];
const defaultRole = props.accessRoles.find(role => role.name === 'barber_stylist') || props.accessRoles[0];
const form = useForm({
    display_name: '', email: '', mobile: '', title: '', online_visible: true,
    location: firstLocation?.public_id || '', service_ids: [],
    working_days: locationHours.length ? [...new Set(locationHours.map(item => item.day_of_week))] : [1, 2, 3, 4, 5, 6],
    starts_at: locationHours[0]?.opens_at?.slice(0, 5) || '09:00', ends_at: locationHours[0]?.closes_at?.slice(0, 5) || '18:00',
    invite_access: true, access_role_id: defaultRole?.id || null, custom_access: false, permission_names: [],
});
const accessForm = useForm({ access_role_id: defaultRole?.id || null, custom_access: false, permission_names: [], location_ids: [], reason: '' });
const revokeForm = useForm({ reason: '' });
const restoreForm = useForm({ reason: '' });
const confirmRevoke = ref(false);
const selectedRole = computed(() => props.accessRoles.find(role => String(role.id) === String(form.access_role_id)));
const selectedAccessRole = computed(() => props.accessRoles.find(role => String(role.id) === String(accessForm.access_role_id)));
const isReady = computed(() => !(props.readiness.blockers || []).some(item => item.code.startsWith('staff.')));
const formatDate = value => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const moduleSelected = (module, target = form) => module.permissions.every(permission => target.permission_names.includes(permission));
const toggleModule = (module, target = form) => {
    const selected = moduleSelected(module, target);
    target.permission_names = selected
        ? target.permission_names.filter(permission => !module.permissions.includes(permission))
        : [...new Set([...target.permission_names, ...module.permissions])];
};
const save = () => form.post(route('business.team.providers.store', props.business.public_id), {
    preserveScroll: true,
    onSuccess: () => {
        editor.value?.close();
        form.reset('display_name', 'email', 'mobile', 'title');
        form.invite_access = true;
    },
});
const openAccess = person => {
    selectedPerson.value = person;
    const standard = props.accessRoles.find(role => role.name === person.membership?.role);
    accessForm.access_role_id = standard?.id || defaultRole?.id || null;
    accessForm.custom_access = !standard;
    accessForm.permission_names = [...(person.membership?.permission_names || [])];
    accessForm.location_ids = person.locations.map(location => location.public_id);
    accessForm.reason = '';
    confirmRevoke.value = false;
    revokeForm.reason = '';
    restoreForm.reason = '';
    accessEditor.value?.open();
};
const saveAccess = () => {
    const options = { preserveScroll: true, onSuccess: () => accessEditor.value?.close() };
    if (selectedPerson.value.membership) {
        accessForm.patch(route('business.team.memberships.access.update', [props.business.public_id, selectedPerson.value.membership.public_id]), options);
    } else {
        accessForm.post(route('business.team.staff.invite', [props.business.public_id, selectedPerson.value.public_id]), options);
    }
};
const revokeAccess = () => revokeForm.delete(route('business.team.memberships.access.destroy', [props.business.public_id, selectedPerson.value.membership.public_id]), {
    preserveScroll: true, onSuccess: () => accessEditor.value?.close(),
});
const restoreAccess = () => restoreForm.post(route('business.team.memberships.access.restore', [props.business.public_id, selectedPerson.value.membership.public_id]), {
    preserveScroll: true, onSuccess: () => accessEditor.value?.close(),
});
const cancelInvitation = invitation => router.delete(route('staff-invitations.destroy', [props.business.public_id, invitation.public_id]), {
    data: { reason: 'Invitation cancelled from Team access.' }, preserveScroll: true,
});
</script>

<template>
    <AppLayout title="Team & access" :business-label="business.name">
        <PageHeader eyebrow="People · Scheduling · Access" title="Your team, ready to work" description="Create bookable profiles, invite secure login seats and keep each person focused on only the tools they need.">
            <template #actions>
                <AppButton :href="route('business.activity.index', business.public_id)" variant="quiet"><ClockIcon class="size-4" />Activity log</AppButton>
                <AppButton @click="editor?.open()"><UserPlusIcon class="size-4" />Add team member</AppButton>
            </template>
        </PageHeader>

        <section class="mt-6 grid gap-4 lg:grid-cols-[1.35fr_0.65fr]">
            <div class="relative overflow-hidden rounded-[1.5rem] bg-[linear-gradient(135deg,#172554,#3730a3)] p-6 text-white shadow-[var(--shadow-raised)] sm:p-8">
                <div class="absolute -right-12 -top-16 size-52 rounded-full bg-cyan-300/20 blur-3xl" aria-hidden="true"></div>
                <SparklesIcon class="size-7 text-cyan-200" />
                <p class="mt-5 text-xs font-bold uppercase tracking-[0.14em] text-cyan-200">Team workspace</p>
                <h2 class="cd-display mt-2 max-w-2xl text-3xl font-semibold tracking-[-0.04em]">One person, one secure login, exactly the right access.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-white/70">Bookable providers can stay without login access. When someone needs the platform, send a single-use invitation and choose a proven role or tailor modules.</p>
            </div>
            <div class="rounded-[1.5rem] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-6 shadow-sm">
                <div class="flex items-center justify-between"><span class="grid size-11 place-items-center rounded-2xl bg-[var(--brand-primary-soft)] text-[var(--brand-secondary)]"><UsersIcon class="size-6" /></span><span class="rounded-full bg-[var(--surface-subtle)] px-3 py-1 text-xs font-bold text-[var(--text-muted)]">Current plan</span></div>
                <p class="mt-5 text-sm font-semibold text-[var(--text-muted)]">Team seats</p>
                <p class="mt-1 text-3xl font-bold text-[var(--text-strong)]">{{ seatAllowance.used }} <span class="text-base font-semibold text-[var(--text-muted)]">of {{ seatAllowance.limit || 'unlimited' }}</span></p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-[var(--surface-subtle)]"><div class="h-full rounded-full bg-[var(--brand-secondary)]" :style="{ width: `${seatAllowance.limit ? Math.min(100, (seatAllowance.used / seatAllowance.limit) * 100) : 15}%` }"></div></div>
                <p class="mt-3 text-xs leading-5 text-[var(--text-muted)]">A seat is reserved by an active team profile. Login access can be connected without creating a duplicate person.</p>
            </div>
        </section>

        <AppDialog ref="editor" drawer title="Add a team member" description="Save their working profile and, if needed, email a secure invitation in the same step.">
            <div v-if="!locations.length" class="rounded-xl bg-[var(--status-warning-soft)] p-4 text-sm text-[var(--status-warning)]"><strong>Add a location first.</strong><p class="mt-1">Every team member needs a home location before their profile is saved.</p><AppButton class="mt-4" :href="route('business.locations.index', business.public_id)" variant="secondary">Set up location</AppButton></div>
            <form v-else id="provider-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <label class="text-sm font-semibold">Name<input v-model="form.display_name" required autocomplete="name" class="cd-input mt-2" /></label>
                <label class="text-sm font-semibold">Role or title<input v-model="form.title" list="team-role-suggestions" placeholder="Choose or type a title" autocomplete="organization-title" class="cd-input mt-2" /><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Shown to clients; you can type a title not in the list.</span></label>
                <datalist id="team-role-suggestions"><option v-for="role in roleSuggestions" :key="role" :value="role" /></datalist>
                <label class="text-sm font-semibold">Email<input v-model="form.email" required type="email" autocomplete="email" class="cd-input mt-2" /><span class="mt-1 block text-xs font-normal text-[var(--text-muted)]">Used for their profile and secure invitation.</span></label>
                <label class="text-sm font-semibold">Mobile (optional)<PhoneInput id="provider-phone" v-model="form.mobile" class="mt-2" :country="business.country_code || 'IN'" /></label>
                <label class="text-sm font-semibold sm:col-span-2">Primary location<AppSelect v-model="form.location" required class="cd-input mt-2"><option v-for="location in locations" :key="location.public_id" :value="location.public_id">{{ location.name }} · {{ location.time_zone }}</option></AppSelect></label>
                <fieldset v-if="services.length" class="sm:col-span-2"><legend class="text-sm font-semibold">Services they can perform <span class="font-normal text-[var(--text-muted)]">(optional)</span></legend><div class="mt-2 flex flex-wrap gap-2"><label v-for="service in services" :key="service.public_id" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[var(--border-subtle)] px-3 text-sm"><input v-model="form.service_ids" type="checkbox" :value="service.public_id" />{{ service.name }}</label></div></fieldset>
                <div class="sm:col-span-2 border-t border-[var(--border-subtle)] pt-5"><h3 class="font-semibold">Normal availability</h3><p class="mt-1 text-sm text-[var(--text-muted)]">Easy defaults from this location; adjust detailed availability later.</p></div>
                <fieldset class="sm:col-span-2"><legend class="ds-sr-only">Working days</legend><div class="flex flex-wrap gap-2"><label v-for="(day, index) in days" :key="day" :class="['inline-flex min-h-11 cursor-pointer items-center rounded-xl border px-3 text-sm font-semibold', form.working_days.includes(index + 1) ? 'border-[var(--action-primary)] bg-[var(--brand-primary-soft)] text-[var(--action-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="form.working_days" class="sr-only" type="checkbox" :value="index + 1" />{{ day }}</label></div></fieldset>
                <label class="text-sm font-semibold">Starts<input v-model="form.starts_at" required type="time" class="cd-input mt-2" /></label><label class="text-sm font-semibold">Ends<input v-model="form.ends_at" required type="time" class="cd-input mt-2" /></label>
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold sm:col-span-2"><input v-model="form.online_visible" type="checkbox" />Let clients choose this person online</label>

                <div class="sm:col-span-2 rounded-2xl border border-[var(--brand-secondary)]/20 bg-[var(--brand-primary-soft)] p-4">
                    <label class="flex cursor-pointer items-start gap-3"><input v-model="form.invite_access" type="checkbox" class="mt-1" /><span><strong class="block text-[var(--text-strong)]">Invite them to use ClipperDesk</strong><span class="mt-1 block text-sm leading-6 text-[var(--text-muted)]">Recommended. They create their own password from a 7-day, single-use email link. We never email temporary passwords.</span></span></label>
                </div>
                <template v-if="form.invite_access">
                    <label class="text-sm font-semibold sm:col-span-2">Workspace role<AppSelect v-model="form.access_role_id" :disabled="form.custom_access" required class="cd-input mt-2"><option v-for="role in accessRoles" :key="role.id" :value="role.id" :disabled="role.name === 'owner' && !canManageOwners">{{ role.label }}</option></AppSelect><span v-if="selectedRole && !form.custom_access" class="mt-2 block text-xs font-normal leading-5 text-[var(--text-muted)]">{{ selectedRole.description }}</span></label>
                    <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold sm:col-span-2"><input v-model="form.custom_access" type="checkbox" />Customize module access</label>
                    <fieldset v-if="form.custom_access" class="grid gap-2 sm:col-span-2 sm:grid-cols-2"><legend class="mb-1 text-sm font-semibold">Choose only what they need</legend><button v-for="module in accessModules" :key="module.key" type="button" :class="['rounded-2xl border p-4 text-left transition', moduleSelected(module) ? 'border-[var(--brand-secondary)] bg-[var(--brand-primary-soft)]' : 'border-[var(--border-subtle)] hover:border-[var(--border-strong)]']" :aria-pressed="moduleSelected(module)" @click="toggleModule(module)"><span class="flex items-center gap-2 font-semibold text-[var(--text-strong)]"><CheckCircleIcon v-if="moduleSelected(module)" class="size-5 text-[var(--brand-secondary)]" />{{ module.label }}</span><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ module.description }}</span></button></fieldset>
                </template>
                <ul v-if="Object.keys(form.errors).length" class="rounded-xl bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)] sm:col-span-2" role="alert"><li v-for="(message, field) in form.errors" :key="field">{{ message }}</li></ul>
            </form>
            <template #footer><AppButton variant="secondary" @click="editor?.close()">Cancel</AppButton><AppButton type="submit" form="provider-form" :disabled="form.processing || !locations.length || !seatAllowance.can_add">{{ form.processing ? 'Saving…' : (form.invite_access ? 'Save & send invitation' : 'Save team member') }}</AppButton></template>
        </AppDialog>

        <AppDialog ref="accessEditor" drawer :title="`${selectedPerson?.membership ? 'Manage' : 'Invite'} ${selectedPerson?.display_name || 'team member'}`" :description="selectedPerson?.membership ? 'Adjust login role, module access and assigned locations. Every access change is recorded.' : 'Choose what they can use, then send a secure single-use invitation.'">
            <form v-if="selectedPerson" id="access-form" class="space-y-5" @submit.prevent="saveAccess">
                <div :class="['flex items-center gap-3 rounded-2xl p-4', selectedPerson.has_login ? 'bg-[var(--status-success-soft)]' : 'bg-[var(--brand-primary-soft)]']"><ShieldCheckIcon :class="['size-6', selectedPerson.has_login ? 'text-[var(--status-success)]' : 'text-[var(--brand-secondary)]']" /><div><p class="font-semibold text-[var(--text-strong)]">{{ selectedPerson.has_login ? 'Active secure login' : (selectedPerson.membership ? 'Login access removed' : 'Login invitation') }}</p><p class="text-sm text-[var(--text-muted)]">{{ selectedPerson.email }}</p></div></div>
                <div v-if="selectedPerson.membership && !selectedPerson.has_login" class="rounded-2xl bg-[var(--status-warning-soft)] p-4"><p class="font-semibold text-[var(--status-warning)]">Restore this login</p><p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">They will use their existing password. Add a reason so the restoration is auditable.</p><textarea v-model="restoreForm.reason" required rows="2" class="cd-input mt-3" placeholder="Required reason, such as returning to the team"></textarea><AppButton class="mt-3" size="small" :disabled="!restoreForm.reason || restoreForm.processing" @click="restoreAccess">Restore access</AppButton><p v-if="restoreForm.errors.reason" class="mt-2 text-sm text-[var(--status-danger)]">{{ restoreForm.errors.reason }}</p></div>
                <label class="block text-sm font-semibold">Workspace role<AppSelect v-model="accessForm.access_role_id" :disabled="accessForm.custom_access" required class="cd-input mt-2"><option v-for="role in accessRoles" :key="role.id" :value="role.id" :disabled="role.name === 'owner' && !canManageOwners">{{ role.label }}</option></AppSelect><span v-if="selectedAccessRole && !accessForm.custom_access" class="mt-2 block text-xs font-normal leading-5 text-[var(--text-muted)]">{{ selectedAccessRole.description }}</span></label>
                <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="accessForm.custom_access" type="checkbox" />Customize module access</label>
                <fieldset v-if="accessForm.custom_access" class="grid gap-2 sm:grid-cols-2"><legend class="mb-1 text-sm font-semibold sm:col-span-2">Module access</legend><button v-for="module in accessModules" :key="module.key" type="button" :class="['rounded-2xl border p-4 text-left', moduleSelected(module, accessForm) ? 'border-[var(--brand-secondary)] bg-[var(--brand-primary-soft)]' : 'border-[var(--border-subtle)]']" :aria-pressed="moduleSelected(module, accessForm)" @click="toggleModule(module, accessForm)"><span class="font-semibold text-[var(--text-strong)]">{{ module.label }}</span><span class="mt-1 block text-xs leading-5 text-[var(--text-muted)]">{{ module.description }}</span></button></fieldset>
                <fieldset><legend class="text-sm font-semibold">Assigned locations</legend><div class="mt-2 flex flex-wrap gap-2"><label v-for="location in locations" :key="location.public_id" :class="['inline-flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm', accessForm.location_ids.includes(location.public_id) ? 'border-[var(--brand-secondary)] bg-[var(--brand-primary-soft)]' : 'border-[var(--border-subtle)]']"><input v-model="accessForm.location_ids" type="checkbox" :value="location.public_id" />{{ location.name }}</label></div></fieldset>
                <label class="block text-sm font-semibold">Reason for access change <span class="font-normal text-[var(--text-muted)]">(optional)</span><textarea v-model="accessForm.reason" rows="2" class="cd-input mt-2" placeholder="Promotion, location transfer, responsibility change…"></textarea></label>
                <ul v-if="Object.keys(accessForm.errors).length" class="rounded-xl bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert"><li v-for="(message, field) in accessForm.errors" :key="field">{{ message }}</li></ul>
                <div v-if="selectedPerson.has_login" class="border-t border-[var(--border-subtle)] pt-5">
                    <button v-if="!confirmRevoke" type="button" class="text-sm font-semibold text-[var(--status-danger)]" @click="confirmRevoke = true">Remove login access…</button>
                    <div v-else class="rounded-2xl bg-[var(--status-danger-soft)] p-4"><p class="font-semibold text-[var(--status-danger)]">Remove access immediately?</p><p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">Active sessions will end. Their historical actions and bookable profile will remain.</p><textarea v-model="revokeForm.reason" required rows="2" class="cd-input mt-3" placeholder="Required reason, such as employment ended"></textarea><div class="mt-3 flex gap-2"><AppButton size="small" variant="secondary" @click="confirmRevoke = false">Keep access</AppButton><AppButton size="small" variant="danger" :disabled="!revokeForm.reason || revokeForm.processing" @click="revokeAccess">Remove access</AppButton></div><p v-if="revokeForm.errors.reason" class="mt-2 text-sm text-[var(--status-danger)]">{{ revokeForm.errors.reason }}</p></div>
                </div>
            </form>
            <template #footer><AppButton variant="secondary" @click="accessEditor?.close()">Cancel</AppButton><AppButton type="submit" form="access-form" :disabled="accessForm.processing || !accessForm.location_ids.length">{{ selectedPerson?.membership ? 'Save access' : 'Send invitation' }}</AppButton></template>
        </AppDialog>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
            <SurfaceCard title="Team" :description="`${staff.length} profile${staff.length === 1 ? '' : 's'} · login and booking status at a glance`">
                <div v-if="!staff.length" class="rounded-2xl border border-dashed border-[var(--border-strong)] p-8 text-center"><UserPlusIcon class="mx-auto size-8 text-[var(--text-muted)]" /><p class="mt-3 font-semibold">Add your first team member</p><p class="mt-1 text-sm text-[var(--text-muted)]">Their schedule and access can be ready in one focused step.</p></div>
                <ul v-else class="divide-y divide-[var(--border-subtle)]"><li v-for="person in staff" :key="person.public_id" class="py-5 first:pt-0 last:pb-0"><div class="flex flex-wrap items-start justify-between gap-4"><div class="flex min-w-0 items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-[var(--surface-subtle)] font-bold text-[var(--brand-secondary)]">{{ person.display_name.charAt(0) }}</span><div class="min-w-0"><p class="font-semibold text-[var(--text-strong)]">{{ person.display_name }}</p><p class="mt-1 text-sm text-[var(--text-muted)]">{{ person.title || 'Team member' }} · {{ person.locations.map(item => item.name).join(', ') || 'No location' }}</p></div></div><AppButton v-if="!person.membership?.is_current" size="small" variant="secondary" @click="openAccess(person)">{{ person.membership ? 'Manage access' : 'Invite access' }}<ChevronRightIcon class="size-4" /></AppButton><span v-else class="rounded-full bg-[var(--surface-subtle)] px-3 py-1 text-xs font-semibold text-[var(--text-muted)]">Your account</span></div><div class="mt-3 flex flex-wrap gap-2 text-xs"><span :class="['rounded-full px-2.5 py-1 font-semibold', person.has_login ? 'bg-[var(--status-success-soft)] text-[var(--status-success)]' : 'bg-[var(--surface-subtle)] text-[var(--text-muted)]']"><KeyIcon class="mr-1 inline size-3.5" />{{ person.has_login ? `${person.membership.role_label} access` : (person.membership ? 'Login access removed' : 'No login access') }}</span><span class="rounded-full bg-[var(--surface-subtle)] px-2.5 py-1">{{ person.availability.filter(item => item.kind === 'working').length }} working days</span><span class="rounded-full bg-[var(--surface-subtle)] px-2.5 py-1">{{ person.services.length }} services</span></div></li></ul>
            </SurfaceCard>

            <div class="space-y-6">
                <SurfaceCard title="Pending invitations" :description="`${pendingInvitations.length} waiting to join`">
                    <p v-if="!pendingInvitations.length" class="text-sm leading-6 text-[var(--text-muted)]">No invitations are waiting. New secure invitations appear here until accepted or cancelled.</p>
                    <ul v-else class="space-y-3"><li v-for="invitation in pendingInvitations" :key="invitation.public_id" class="rounded-2xl border border-[var(--border-subtle)] p-4"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-[var(--text-strong)]">{{ invitation.person || invitation.email }}</p><p class="mt-1 text-xs text-[var(--text-muted)]">{{ invitation.role }} · expires {{ formatDate(invitation.expires_at) }}</p></div><button type="button" class="text-xs font-semibold text-[var(--status-danger)]" @click="cancelInvitation(invitation)">Cancel</button></div><p class="mt-2 truncate text-sm text-[var(--text-muted)]">{{ invitation.email }}</p></li></ul>
                </SurfaceCard>

                <SurfaceCard title="Recent team activity" description="Immutable access and operational evidence">
                    <p v-if="!activityPreview.length" class="text-sm text-[var(--text-muted)]">Activity appears as your team starts working.</p>
                    <ul v-else class="space-y-4"><li v-for="event in activityPreview.slice(0, 5)" :key="event.public_id" class="flex gap-3"><span class="mt-1 size-2 shrink-0 rounded-full bg-[var(--brand-secondary)]"></span><div><p class="text-sm font-semibold text-[var(--text-strong)]">{{ event.label }}</p><p class="mt-0.5 text-xs text-[var(--text-muted)]">{{ event.actor }} · {{ formatDate(event.occurred_at) }}</p></div></li></ul>
                    <AppButton class="mt-5 w-full" :href="route('business.activity.index', business.public_id)" variant="secondary">View complete activity log<ArrowPathIcon class="size-4" /></AppButton>
                </SurfaceCard>
            </div>
        </div>
        <div v-if="isReady" class="mt-6 flex items-center gap-3 rounded-xl bg-[var(--status-success-soft)] p-4 text-sm text-[var(--status-success)]"><CheckCircleIcon class="size-5" /><strong>Team readiness complete.</strong> At least one active provider has a location and working hours.</div>
    </AppLayout>
</template>
