<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowPathIcon, CalendarDaysIcon, CheckCircleIcon, ChevronRightIcon, ClockIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppointmentRow from '@/Components/Dashboard/AppointmentRow.vue';
import { attentionItems, dayGroups, isFinished, localDateKey } from '@/Support/dailyWorkspace';
import '../../css/dashboard.css';

const props = defineProps({businessLabel:String, location:Object, locations:Array, date:String, calendar:Object, readiness:Object, permissions:Object, todayMetrics:Object, workspace:Object});
const page = usePage();
const business = computed(() => page.props.tenant.public_id);
const zone = computed(() => props.calendar.timeZone);
const locale = computed(() => page.props.tenant?.regional?.locale || undefined);
const current = ref(Date.parse(props.calendar.currentTime));
const followsToday = ref(props.workspace?.isToday);
let serverOffset = current.value - Date.now();
const activeView = ref('guided');
const filter = ref('active');
const working = ref(null);
const refreshing = ref(false);
const error = ref('');
const selectedId = ref(null);
const details = ref(null);
const dialogOpen = ref(false);
const expandedAttention = ref(false);
const mobileAttentionOpen = ref(false);
const views = [{id:'guided',label:'Now & next'},{id:'command',label:'Appointment list'},{id:'rhythm',label:'By staff'}];
const preferenceKey = computed(() => `clipperdesk:dashboard-view:${page.props.auth.user.id}`);
const appointments = computed(() => props.workspace?.appointments || []);
const selected = computed(() => appointments.value.find(e => e.id === selectedId.value));
const groups = computed(() => dayGroups(appointments.value, current.value, props.workspace?.isToday));
const attention = computed(() => attentionItems(appointments.value, current.value, props.workspace?.isToday));
const shownAttention = computed(() => expandedAttention.value ? attention.value : attention.value.slice(0,4));
const filtered = computed(() => appointments.value.filter(e => filter.value === 'all' || (filter.value === 'finished' ? isFinished(e) : filter.value === 'completed' ? e.status==='completed' : !isFinished(e))));
const statusCounts = computed(() => props.todayMetrics?.cards?.appointments?.by_status || {});
const activeCount = computed(() => Object.entries(statusCounts.value).reduce((sum,[status,count]) => sum + (isFinished({status}) ? 0 : count),0));
const completeCount = computed(() => statusCounts.value.completed || 0);
const teamColumns = computed(() => {
    const roster = new Map((props.workspace?.team || []).map(person => [person.id,person]));
    // Historical visits can belong to staff who are no longer on the active roster.
    for (const event of filtered.value) {
        for (const person of event.staff) {
            if (!roster.has(person.id)) roster.set(person.id,{...person,windows:[]});
        }
    }
    const people = [...roster.values()].map(p => ({...p, events:filtered.value.filter(e => e.staff.some(s => s.id === p.id))}));
    const unassigned = filtered.value.filter(e => !e.staff.length);
    if (unassigned.length) people.push({id:'unassigned', name:'Unassigned', windows:[], events:unassigned});
    return people;
});
const title = computed(() => props.workspace?.scope === 'personal' ? 'My day' : props.workspace?.scope === 'finance' ? 'Business overview' : 'Daily workspace');
const time = value => new Intl.DateTimeFormat(locale.value, {hour:'numeric', minute:'2-digit',timeZone:zone.value}).format(new Date(value));
const dateLabel = computed(() => new Intl.DateTimeFormat(locale.value,{weekday:'short',month:'short',day:'numeric',timeZone:'UTC'}).format(new Date(`${props.date}T12:00:00Z`)));
const money = (value, currency) => new Intl.NumberFormat(locale.value,{style:'currency',currency:currency || page.props.tenant?.regional?.currency_code || 'INR'}).format((value || 0)/100);
const href = (name, extra={}) => route(name,{business:business.value,...extra});
const calendarHref = (extra={}) => href('business.calendar',{location:props.location?.public_id,date:props.date,...extra});
const queueHref = computed(() => href('business.walk-ins.index',{location:props.location?.public_id}));
const phaseLabel = computed(() => ({open:'Open now', closing_soon:'Closing within an hour', before_open:'Before opening', after_close:'After closing', between_hours:'Between opening hours',closed:'Closed today',other_date:props.workspace?.hours.length ? 'Opening hours' : 'Closed on this date'}[props.workspace?.phase]));
const finances = computed(() => ['collected_revenue_minor','outstanding_minor','expected_revenue_minor','new_clients','low_stock'].flatMap(key => props.todayMetrics?.cards?.[key]?.visible ? [[key,props.todayMetrics.cards[key]]] : []));
const financialLabels = {collected_revenue_minor:'Collected',outstanding_minor:'Outstanding',expected_revenue_minor:'Sale value',new_clients:'New paying clients',low_stock:'Low stock'};
const emptyDescription = computed(() => props.workspace?.scope === 'personal' ? 'You have no appointments assigned on this date. Check the calendar for your next working day.' : !props.workspace?.hours.length ? 'There are no appointments on this date. Review another day or check the location’s opening hours.' : 'There are no appointments on this date. Add a booking or use the calendar to find an opening.');
const setView = value => { activeView.value=value; try { localStorage.setItem(preferenceKey.value,value); } catch {} };
const navigate = values => { if (working.value) return; followsToday.value = values.date === undefined || values.date === localDateKey(Date.now()+serverOffset,zone.value); details.value?.close(); dialogOpen.value=false; router.get(href('business.dashboard'),{date:props.date,location:props.location?.public_id,...values},{preserveState:true,preserveScroll:false,onStart:()=>refreshing.value=true,onFinish:()=>refreshing.value=false}); };
const refreshFailure = () => { error.value='The workspace could not be refreshed. The last loaded information is still shown. Try Refresh again.'; return false; };
const actionFailure = () => { error.value='This update could not be verified. Refresh the workspace before trying again.'; return false; };
const refresh = () => {
    if (refreshing.value || working.value || dialogOpen.value) return;
    error.value='';
    router.reload({only:['workspace','todayMetrics','calendar','date','location','permissions','readiness'],preserveScroll:true,onStart:()=>refreshing.value=true,onError:refreshFailure,onHttpException:refreshFailure,onNetworkError:refreshFailure,onFinish:()=>refreshing.value=false});
};
const open = async event => { selectedId.value=event.id; dialogOpen.value=true; await nextTick(); details.value?.open(); };
const act = event => {
    if (!event.action || working.value || refreshing.value) return;
    working.value=event.id; error.value='';
    router.patch(href('business.appointments.status',{appointment:event.id}), {status:event.action.status,version:event.version,idempotency_key:`dashboard-${event.id}-${event.version}-${event.action.status}`}, {
        preserveScroll:true, onSuccess:()=>{ details.value?.close(); dialogOpen.value=false; },
        onError:errors=>{ error.value=Object.entries(errors).filter(([key])=>key!=='booking_rule').flatMap(([,value])=>value).join(' ') || 'This appointment changed. Refresh the workspace before trying again.'; },
        onHttpException:actionFailure, onNetworkError:actionFailure,
        onFinish:()=>working.value=null,
    });
};
const close = () => { details.value?.close(); dialogOpen.value=false; };
watch(() => props.calendar.currentTime, value => { current.value=Date.parse(value); serverOffset=current.value-Date.now(); });
watch(() => [props.date,props.location?.public_id], () => { selectedId.value=null;filter.value='active';expandedAttention.value=false;mobileAttentionOpen.value=false; });
let timer;
const tick = () => {
    if (document.hidden || dialogOpen.value || working.value || refreshing.value) return;
    current.value = Date.now() + serverOffset;
    const today = localDateKey(current.value,zone.value);
    if (followsToday.value && today !== props.date) navigate({date:today});
    else refresh();
};
onMounted(() => {
    try { const saved=localStorage.getItem(preferenceKey.value); if (views.some(v=>v.id===saved)) activeView.value=saved; } catch {}
    timer=setInterval(tick,60000);
    document.addEventListener('visibilitychange',tick);
});
onUnmounted(()=>{clearInterval(timer);document.removeEventListener('visibilitychange',tick);});
</script>

<template>
    <AppLayout title="Dashboard" :business-label="businessLabel">
        <div class="dw-workspace">
            <PageHeader :title="title">
                <template #context><span>{{ workspace?.scope === 'personal' ? 'Your appointments and next steps' : workspace?.scope === 'finance' ? 'Sales and payments for your location' : 'Appointments, arrivals and the work ahead' }}</span></template>
                <template #actions><AppButton v-if="permissions.clients" class="dw-client-shortcut" variant="quiet" :href="href('business.clients.index')">Find client</AppButton><AppButton v-if="permissions.calendar" variant="secondary" :href="calendarHref()"><CalendarDaysIcon class="size-4" aria-hidden="true" />Calendar</AppButton><AppButton v-if="permissions.calendar && permissions.createAppointment" :href="calendarHref({create:1})">Add appointment</AppButton><AppButton v-else-if="permissions.revenue && permissions.reports" :href="href('business.reports.index')">Open reports</AppButton></template>
            </PageHeader>

            <div class="dw-context-bar">
                <div class="dw-context-fields"><label class="dw-date"><span class="sr-only">Workspace date</span><input type="date" :value="date" class="cd-input" :disabled="refreshing || !!working" @change="navigate({date:$event.target.value || workspace?.today})" /></label><AppButton v-if="workspace && !workspace.isToday" variant="quiet" size="small" @click="navigate({date:workspace.today})">Today</AppButton><label v-if="locations.length > 1" class="dw-location"><span class="sr-only">Location</span><AppSelect aria-label="Location" :value="location?.public_id" :disabled="refreshing || !!working" @change="navigate({location:$event.target.value,date:undefined})"><option v-for="item in locations" :key="item.public_id" :value="item.public_id">{{ item.name }}</option></AppSelect></label><span v-else>{{ location?.name || 'No location assigned' }}</span></div>
                <div class="dw-freshness"><span v-if="location" :title="zone">{{ zone.replaceAll('_',' ') }}</span><span v-if="todayMetrics">Updated {{ time(todayMetrics.fresh_at) }}</span><button class="dw-refresh" type="button" :disabled="refreshing || !!working || dialogOpen" aria-label="Refresh workspace" @click="refresh"><ArrowPathIcon :class="['size-4',refreshing ? 'animate-spin motion-reduce:animate-none' : '']" aria-hidden="true" /></button></div>
            </div>
            <p v-if="error" class="dw-error" role="alert">{{ error }} <button type="button" class="underline" @click="refresh">Refresh</button></p>
            <div v-if="permissions.setup && !readiness.publishable" class="dw-setup"><ExclamationTriangleIcon class="size-5 shrink-0" aria-hidden="true" /><div><strong>Finish your booking setup</strong><p>{{ readiness.blockers[0]?.message || 'Complete the remaining setup before publishing your booking page.' }}</p></div><Link :href="href('business.configuration.show')" class="dw-text-action">Continue setup<ChevronRightIcon class="size-4" aria-hidden="true" /></Link></div>

            <StatePanel v-if="!workspace" title="Choose where you work" :description="permissions.setup ? 'Set up an active location to start managing appointments and your business day.' : 'Ask your business administrator to assign you to an active location.'"><template #actions><AppButton v-if="permissions.setup" :href="href('business.configuration.show')">Set up location</AppButton></template></StatePanel>
            <template v-else>
                <div class="dw-hours"><span class="dw-open-indicator" :class="{'dw-is-open':['open','closing_soon'].includes(workspace.phase)}" aria-hidden="true"></span><strong>{{ phaseLabel }}</strong><span v-if="workspace.hours.length">{{ workspace.hours.map(w=>`${time(w.startsAt)}–${time(w.endsAt)}`).join(' · ') }}</span><span v-if="workspace.scope==='personal'" class="dw-own-label">Only your assigned appointments</span></div>
                <div v-if="permissions.calendar" class="dw-pulse" aria-label="Day at a glance"><button type="button" @click="filter='all';setView('command')"><strong>{{ todayMetrics.cards.appointments.value }}</strong><span class="dw-desktop-copy">Appointments</span><span class="dw-mobile-copy">Visits</span></button><button type="button" @click="filter='active';setView('command')"><strong>{{ activeCount }}</strong><span class="dw-desktop-copy">Still to finish</span><span class="dw-mobile-copy">To finish</span></button><button type="button" @click="filter='completed';setView('command')"><strong>{{ completeCount }}</strong><span class="dw-desktop-copy">Completed</span><span class="dw-mobile-copy">Done</span></button><Link v-if="permissions.walkIns && workspace.isToday" :href="queueHref"><strong>{{ todayMetrics.cards.walk_ins_waiting.value }}</strong><span class="dw-desktop-copy">Walk-ins in queue</span><span class="dw-mobile-copy">Queue</span></Link><a v-else href="#dw-team"><strong>{{ todayMetrics.cards.staff_available.value }}</strong><span class="dw-desktop-copy">{{ workspace.scope==='personal' ? 'Scheduled today' : 'Staff scheduled' }}</span><span class="dw-mobile-copy">On rota</span></a></div>

                <div v-if="permissions.calendar" class="dw-main-grid">
                    <SurfaceCard class="dw-schedule" :padding="false">
                        <div class="dw-section-heading"><h2>{{ workspace.scope==='personal' ? 'My schedule' : 'Daily schedule' }}</h2><span>{{ dateLabel }}</span></div>
                        <div class="dw-schedule-toolbar"><div class="dw-view-switch" role="group" aria-label="Schedule view"><button v-for="view in views" :key="view.id" type="button" :aria-pressed="activeView===view.id" @click="setView(view.id)">{{ view.label }}</button></div><label v-if="activeView!=='guided'"><span class="sr-only">Appointment status filter</span><AppSelect aria-label="Appointment status filter" :value="filter" @change="filter=$event.target.value"><option value="active">Active</option><option value="all">All statuses</option><option value="completed">Completed</option><option value="finished">Finished & cancelled</option></AppSelect></label></div>
                        <p v-if="workspace.truncated" class="dw-limit">Showing up to 100 appointments, prioritizing active visits. <Link :href="calendarHref()">Open the calendar for the full schedule.</Link></p>
                        <StatePanel v-if="!appointments.length" compact title="A clear schedule" :description="emptyDescription"><template #actions><AppButton v-if="permissions.createAppointment && workspace.hours.length" variant="secondary" :href="calendarHref({create:1})">Add appointment</AppButton><AppButton v-else variant="secondary" :href="calendarHref()">Open calendar</AppButton></template></StatePanel>
                        <template v-else-if="activeView==='guided'">
                            <section v-for="group in [{id:'now',label:'On the floor'},{id:'unresolved',label:'Needs a status update'},{id:'next',label:'Up next · within 2 hours'},{id:'later',label:workspace.isToday ? 'Later today' : 'Scheduled appointments'}].filter(g=>groups[g.id].length)" :key="group.id" :aria-label="group.label"><div class="dw-group-heading"><h3>{{ group.label }}</h3><span>{{ groups[group.id].length }}</span></div><AppointmentRow v-for="event in groups[group.id]" :key="event.id" :event="event" :time-zone="zone" :locale="locale" :busy="working===event.id" :is-today="workspace.isToday" :now="current" @open="open" @act="act" /></section>
                            <p v-if="!activeCount" class="dw-day-done"><CheckCircleIcon class="size-5" aria-hidden="true" />All appointments are finished for this date.</p>
                            <details v-if="groups.finished.length" class="dw-history"><summary>Finished & cancelled <span>{{ groups.finished.length }}</span></summary><AppointmentRow v-for="event in groups.finished" :key="event.id" :event="event" :time-zone="zone" :locale="locale" :busy="working===event.id" @open="open" @act="act" /></details>
                        </template>
                        <template v-else-if="activeView==='command'"><p v-if="!filtered.length" class="dw-inline-empty">No appointments match this status. Choose All statuses to see the full day.</p><AppointmentRow v-for="event in filtered" :key="event.id" :event="event" :time-zone="zone" :locale="locale" :busy="working===event.id" :is-today="workspace.isToday" :now="current" @open="open" @act="act" /></template>
                        <div v-else class="dw-staff-schedule"><section v-for="person in teamColumns" :key="person.id"><div class="dw-group-heading"><h3>{{ person.name }}</h3><span>{{ person.events.length }} appointments</span></div><p v-if="!person.events.length" class="dw-inline-empty">No appointments assigned.</p><AppointmentRow v-for="event in person.events" :key="event.id" :event="event" :time-zone="zone" :locale="locale" :busy="working===event.id" :is-today="workspace.isToday" :now="current" @open="open" @act="act" /></section></div>
                    </SurfaceCard>

                    <aside class="dw-attention" :class="{'dw-mobile-open':mobileAttentionOpen}" aria-labelledby="dw-attention-title"><SurfaceCard :padding="false"><div class="dw-section-heading"><h2 id="dw-attention-title">Needs attention</h2><span v-if="attention.length || workspace.checkoutCount">{{ attention.length + workspace.checkoutCount }}{{ workspace.truncated ? '+' : '' }}</span><CheckCircleIcon v-else class="size-5 text-[var(--status-success)]" aria-hidden="true" /><button type="button" class="dw-mobile-attention-toggle" :aria-expanded="mobileAttentionOpen" aria-controls="dw-attention-content" @click="mobileAttentionOpen=!mobileAttentionOpen">{{ mobileAttentionOpen ? 'Hide' : 'Review' }}</button></div><p v-if="!mobileAttentionOpen" class="dw-mobile-attention-summary">{{ attention[0]?.reasons[0] || (workspace.checkoutCount ? 'Unpaid visits ready for checkout' : 'No appointment issues') }}<span v-if="workspace.checkoutCount && attention.length"> · {{ workspace.checkoutCount }} ready for checkout</span><span v-if="permissions.walkIns && workspace.isToday && todayMetrics.cards.walk_ins_waiting.value"> · {{ todayMetrics.cards.walk_ins_waiting.value }} in queue</span></p><div id="dw-attention-content"><div v-if="attention.length" class="dw-attention-list" :class="{'dw-attention-expanded':expandedAttention}"><article v-for="item in shownAttention" :key="item.event.id"><button class="dw-attention-entry" type="button" :aria-label="`Review ${item.event.title}: ${item.reasons.join(', ')}`" @click="open(item.event)"><span class="dw-attention-reason">{{ item.reasons[0] }}</span><strong>{{ item.event.title }}</strong><span>{{ time(item.event.startsAt) }} · {{ item.event.staff.map(p=>p.name).join(', ') || 'Unassigned' }}</span><span v-if="item.reasons.length>1">{{ item.reasons.slice(1).join(' · ') }}</span><ChevronRightIcon class="size-4" aria-hidden="true" /></button><AppButton v-if="item.event.action" class="dw-attention-action" size="small" variant="secondary" :loading="working===item.event.id" :aria-label="`${item.event.action.label}: ${item.event.title}`" @click="act(item.event)">{{ item.event.action.label }}</AppButton></article><button v-if="attention.length>2" type="button" class="dw-show-more" :class="{'dw-mobile-more':attention.length<=4}" :aria-expanded="expandedAttention" @click="expandedAttention=!expandedAttention">{{ expandedAttention ? 'Show fewer' : `Show all ${attention.length}` }}</button></div><p v-else class="dw-inline-empty">No appointment issues in {{ workspace.truncated ? 'the appointments shown' : 'this schedule' }}.</p>
                        <section v-if="workspace.checkouts.length" class="dw-checkouts"><h3>Ready for checkout <span>{{ workspace.checkoutCount }}</span></h3><Link v-for="item in workspace.checkouts.slice(0,3)" :key="item.id" :href="item.href"><span><strong>{{ item.name }}</strong><small>{{ item.date===workspace.today ? 'Today' : item.date }}</small></span><span>{{ money(item.amount,item.currency) }}<ChevronRightIcon class="size-4" aria-hidden="true" /></span></Link><Link v-if="workspace.checkoutCount>3" class="dw-text-action" :href="href('business.checkout.index',{location:location.public_id})">Review all checkouts</Link></section>
                        <section v-if="permissions.walkIns && workspace.isToday" class="dw-queue"><div class="dw-group-heading"><h3>Walk-in queue</h3><Link :href="queueHref" class="dw-text-action">Open queue</Link></div><p v-if="!workspace.queue.length" class="dw-inline-empty">Nobody waiting. Add new arrivals in the queue.</p><Link v-for="entry in workspace.queue.slice(0,3)" :key="entry.id" :href="queueHref" class="dw-queue-entry"><span class="dw-queue-position">{{ entry.queuePosition }}</span><span><strong>{{ entry.title }}</strong><small>{{ entry.status==='assigned' ? 'Assigned to staff' : entry.status==='notified' ? 'Turn notification sent' : 'Waiting' }}<template v-if="entry.estimatedWaitMinutes != null"> · estimate {{ entry.estimatedWaitMinutes }} min</template></small></span><ChevronRightIcon class="size-4 shrink-0" aria-hidden="true" /></Link><Link v-if="todayMetrics.cards.walk_ins_waiting.value>3" class="dw-show-more" :href="queueHref">View all {{ todayMetrics.cards.walk_ins_waiting.value }} in queue</Link></section>
                    </div></SurfaceCard></aside>
                </div>

                <div v-if="!permissions.calendar && !permissions.revenue" class="dw-limited"><StatePanel compact title="Your workspace access" :description="permissions.clients ? 'Use the client directory to find the records you can work with. Ask your administrator if you also need an assigned schedule.' : 'Your access does not include a schedule or financial overview. Ask your administrator to confirm your modules and staff assignment.'"><template #actions><AppButton v-if="permissions.clients" :href="href('business.clients.index')">Find a client</AppButton></template></StatePanel></div>

                <section v-if="finances.length" class="dw-performance" aria-labelledby="dw-performance-title"><div class="dw-section-heading"><h2 id="dw-performance-title">{{ permissions.revenue ? 'Business performance' : 'Inventory' }}</h2><Link v-if="permissions.reports" :href="todayMetrics.cards.collected_revenue_minor.visible ? todayMetrics.cards.collected_revenue_minor.drill : todayMetrics.cards.low_stock.drill" class="dw-text-action">View reports<ChevronRightIcon class="size-4" aria-hidden="true" /></Link></div><div class="dw-financial-grid"><Link v-for="[key,card] in finances" :key="key" :href="card.drill"><span>{{ financialLabels[key] }}</span><strong>{{ key.endsWith('_minor') ? money(card.value) : card.value }}</strong></Link></div><p v-if="permissions.revenue" class="dw-financial-note">{{ dateLabel }} · Sale value includes open and completed sales. Collected includes applied deposits, less refunds. Unchecked-out appointments are excluded.</p></section>

                <div v-if="permissions.calendar" class="dw-support-grid"><SurfaceCard id="dw-team" :title="workspace.scope==='personal' ? 'My working hours' : 'Team schedule'" :padding="false"><p class="dw-support-note">Scheduled hours after breaks and leave. Check the calendar for appointments and bookable openings.</p><ul class="dw-team-list"><li v-for="person in workspace.team" :key="person.id"><span class="dw-team-avatar" aria-hidden="true">{{ person.name.split(' ').map(n=>n[0]).join('').slice(0,2) }}</span><strong>{{ person.name }}</strong><span>{{ person.windows.length ? person.windows.map(w=>`${w.opens_at.slice(0,5)}–${w.closes_at.slice(0,5)}`).join(' · ') : 'Not scheduled' }}</span></li></ul><p v-if="!workspace.team.length" class="dw-inline-empty">No active staff are assigned to this location.</p><Link :href="calendarHref({view:'staff'})" class="dw-show-more">Review staff calendar</Link></SurfaceCard><SurfaceCard v-if="workspace.blocks.length" title="Blocked time" :padding="false"><ul class="dw-block-list"><li v-for="block in workspace.blocks" :key="block.id"><ClockIcon class="size-4 shrink-0" aria-hidden="true" /><span><strong>{{ block.title || block.statusLabel }}</strong><small>{{ time(block.startsAt) }}–{{ time(block.endsAt) }} · {{ block.statusLabel }}</small></span></li></ul></SurfaceCard></div>
            </template>
        </div>

        <AppDialog ref="details" :title="selected?.title || 'Appointment'" drawer @cancel="dialogOpen=false">
            <template v-if="selected"><div class="dw-detail-meta"><span class="dw-status">{{ selected.statusLabel }}</span><p>{{ dateLabel }} · {{ time(selected.startsAt) }}–{{ time(selected.endsAt) }}</p><p>{{ location?.name }}</p></div><dl class="dw-detail-list"><div><dt>Services</dt><dd>{{ selected.services.map(s=>s.name).join(' + ') || 'Appointment' }}</dd></div><div><dt>Staff</dt><dd>{{ selected.staff.map(s=>s.name).join(', ') || 'Not assigned' }}</dd></div><div v-if="selected.forms?.requested"><dt>Forms</dt><dd>{{ selected.forms.completed }} of {{ selected.forms.requested }} complete</dd></div><div v-if="selected.internalNotes"><dt>Appointment notes</dt><dd class="whitespace-pre-wrap">{{ selected.internalNotes }}</dd></div></dl><div v-if="selected.clientMobile || selected.clientEmail" class="dw-contact"><a v-if="selected.clientMobile" :href="`tel:${selected.clientMobile}`">Call {{ selected.clientMobile }}</a><a v-if="selected.clientEmail" :href="`mailto:${selected.clientEmail}`">Email {{ selected.clientEmail }}</a></div><p v-if="error" class="dw-error" role="alert">{{ error }}</p><div class="dw-detail-actions"><AppButton v-if="selected.action" :loading="working===selected.id" @click="act(selected)">{{ selected.action.label }}</AppButton><AppButton v-if="selected.checkoutHref" :href="selected.checkoutHref">Open checkout</AppButton><AppButton variant="secondary" :href="selected.href">Open in calendar</AppButton></div></template>
            <template #footer><AppButton variant="secondary" @click="close">Close</AppButton></template>
        </AppDialog>
    </AppLayout>
</template>
