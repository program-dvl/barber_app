<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeftIcon, ClockIcon, MagnifyingGlassIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ business: Object, filters: Object, events: Object });
const search = ref(props.filters.search || '');
const category = ref(props.filters.category || 'all');
let timer;
const refresh = () => router.get(route('business.activity.index', props.business.public_id), { search: search.value || undefined, category: category.value }, { preserveState: true, replace: true });
watch(search, () => { clearTimeout(timer); timer = setTimeout(refresh, 350); });
watch(category, refresh);
const formatDate = value => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const detail = event => {
    const changes = Object.keys(event.after || {}).slice(0, 3).map(key => key.replaceAll('_', ' '));
    return event.reason || (changes.length ? `Updated ${changes.join(', ')}` : 'Recorded by the workspace audit trail.');
};
</script>

<template>
    <AppLayout title="Activity log" :business-label="business.name">
        <PageHeader eyebrow="Security & accountability" title="Workspace activity" description="A searchable, append-only record of important access, scheduling, client, payment and configuration actions.">
            <template #actions><AppButton :href="route('business.team.index', business.public_id)" variant="secondary"><ArrowLeftIcon class="size-4" />Back to team</AppButton></template>
        </PageHeader>

        <div class="mt-6 grid gap-4 rounded-[1.5rem] bg-[linear-gradient(135deg,#172554,#312e81)] p-6 text-white sm:grid-cols-[1fr_auto] sm:items-center sm:p-8">
            <div class="flex items-start gap-4"><span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white/10"><ShieldCheckIcon class="size-6 text-cyan-200" /></span><div><h2 class="text-xl font-semibold">Know what changed, who changed it, and why.</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-white/70">Activity cannot be edited or deleted by ordinary users. Sensitive credentials and tokens are automatically redacted.</p></div></div>
            <span class="rounded-full bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.12em] text-cyan-100">Owner visibility</span>
        </div>

        <SurfaceCard class="mt-6" title="Complete activity" :description="`${events.total} recorded event${events.total === 1 ? '' : 's'}`">
            <template #actions>
                <div class="grid gap-2 sm:grid-cols-[minmax(15rem,1fr)_12rem]">
                    <label class="relative"><span class="ds-sr-only">Search activity</span><MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-3.5 size-4 text-[var(--text-muted)]" /><input v-model="search" class="cd-input pl-9" placeholder="Search person, action or reason" /></label>
                    <label><span class="ds-sr-only">Filter category</span><AppSelect v-model="category" class="cd-input"><option value="all">All activity</option><option value="access">Team & access</option><option value="appointments">Appointments</option><option value="clients">Clients</option><option value="payments">Payments</option><option value="configuration">Configuration</option></AppSelect></label>
                </div>
            </template>

            <div v-if="!events.data.length" class="py-14 text-center"><ClockIcon class="mx-auto size-9 text-[var(--text-muted)]" /><p class="mt-3 font-semibold">No matching activity</p><p class="mt-1 text-sm text-[var(--text-muted)]">Try a broader search or another category.</p></div>
            <ol v-else class="divide-y divide-[var(--border-subtle)]">
                <li v-for="event in events.data" :key="event.public_id" class="grid gap-3 py-5 first:pt-0 last:pb-0 sm:grid-cols-[10rem_1fr_auto] sm:items-start">
                    <time class="text-xs font-semibold text-[var(--text-muted)]" :datetime="event.occurred_at">{{ formatDate(event.occurred_at) }}</time>
                    <div><p class="font-semibold text-[var(--text-strong)]">{{ event.label }}</p><p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">{{ detail(event) }}</p></div>
                    <div class="sm:text-right"><p class="text-sm font-semibold text-[var(--text-strong)]">{{ event.actor }}</p><p v-if="event.actor_email" class="mt-1 text-xs text-[var(--text-muted)]">{{ event.actor_email }}</p></div>
                </li>
            </ol>

            <nav v-if="events.links?.length > 3" class="mt-6 flex flex-wrap gap-2 border-t border-[var(--border-subtle)] pt-5" aria-label="Activity pages"><template v-for="link in events.links" :key="link.label"><AppButton v-if="link.url" :href="link.url" size="small" :variant="link.active ? 'primary' : 'secondary'" v-html="link.label" /><span v-else class="px-2 py-2 text-sm text-[var(--text-muted)]" v-html="link.label"></span></template></nav>
        </SurfaceCard>
    </AppLayout>
</template>
