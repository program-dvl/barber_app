<script setup>
import '../../../css/walk-in-queue.css';
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowDownIcon, ArrowUpIcon, ArrowPathIcon, BellAlertIcon, CalendarDaysIcon, CheckCircleIcon, ChevronRightIcon, ClockIcon, ChevronDownIcon, MagnifyingGlassIcon, PlusIcon, ScissorsIcon, AdjustmentsHorizontalIcon, UserGroupIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import FormField from '@/Components/Product/FormField.vue';
import PhoneInput from '@/Components/Product/PhoneInput.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import QueueRow from '@/Components/WalkIns/QueueRow.vue';
import { elapsedMinutes, localInput, matchesQueue, pastQuote, waitLabel, waitingStatuses } from '@/Support/walkInWorkspace';
const props = defineProps({businessLabel:String, location:Object, locations:Array, services:Array, staff:Array, entries:Array, recent:Array, updatedAt:String, permissions:Object, canReorder:Boolean, bookingIntervalMinutes:Number,clientPrefill:Object});
const now = ref(Date.now());
const teamExpanded = ref(true);
let teamMedia;
const adaptTeam = () => { teamExpanded.value = !teamMedia.matches; };
const search = ref(''); const filter = ref(''); const staffFilter = ref('');
const refreshBusy = ref(false); const refreshError = ref(''); const mutating = ref(false);
const addDialog = ref(null); const detailDialog = ref(null); const leaveDialog = ref(null); const reorderDialog = ref(null); const notifyDialog = ref(null);
const selectedId = ref(null); const selectedStaff = ref(''); const assignmentReason = ref(''); const actionError = ref(''); const readiness = ref(null); const checking = ref(false);
const activeEntry = computed(() => props.entries.find(e=>e.public_id===selectedId.value));
const waiting = computed(()=>props.entries.filter(e=>waitingStatuses.has(e.status)));
const inService = computed(()=>props.entries.filter(e=>e.status==='in_service'));
const visibleWaiting = computed(()=>waiting.value.filter(e=>matchesQueue(e,search.value) && (!staffFilter.value || [e.assigned_staff_id,e.preferred_staff_id].includes(staffFilter.value)) && (!filter.value || (filter.value==='attention' ? pastQuote(e,now.value) : e.status===filter.value))));
const longest = computed(()=>Math.max(0,...waiting.value.map(e=>elapsedMinutes(e.arrived_at,now.value))));
const attention = computed(()=>waiting.value.filter(e=>pastQuote(e,now.value)).length);
const available = computed(()=>props.staff.filter(s=>s.available_now).length);
const stale = computed(()=>now.value-Date.parse(props.updatedAt)>120000);
const time = value=>value ? new Intl.DateTimeFormat(undefined,{hour:'numeric',minute:'2-digit',timeZone:props.location.time_zone}).format(new Date(value)) : '—';
const dateLabel = computed(()=>new Intl.DateTimeFormat(undefined,{weekday:'short',day:'numeric',month:'short',timeZone:props.location.time_zone}).format(now.value));
const initials = name=>name.split(/\s+/).slice(0,2).map(s=>s[0]).join('').toUpperCase();
const calendarUrl = computed(()=>route('business.calendar',{business:route().params.business,location:props.location.public_id,view:'staff'}));
const checkoutUrl = entry=>route('business.checkout.index',{business:route().params.business,appointment:entry.appointment_id});
const refresh = (manual=false)=>{
    if(refreshBusy.value || mutating.value || document.visibilityState!=='visible' || document.querySelector('dialog[open]') || (!manual && (document.querySelector('.wq-filter[open], .wq-recent[open]') || document.activeElement?.closest('input,select,textarea,[role=combobox]')))) return;
    router.reload({only:['entries','recent','staff','services','permissions','canReorder','updatedAt'],preserveScroll:true,onStart:()=>refreshBusy.value=true,onSuccess:()=>refreshError.value='',onError:()=>refreshError.value='Could not refresh the queue. Your current view is still here.',onNetworkError:()=>{refreshError.value='Connection lost. The queue will update when you reconnect.';return false;},onFinish:()=>refreshBusy.value=false});
};
const changeLocation = value=>router.get(route('business.walk-ins.index',route().params.business),{location:value},{onBefore:()=>!mutating.value});
let clockTimer, pollTimer, searchTimer, searchAbort, readinessAbort; let searchGeneration=0, readinessGeneration=0;
const clientSearch=ref(''); const clientResults=ref([]); const selectedClient=ref(null); const searchingClients=ref(false); const clientError=ref(''); const searchDone=ref(false);
const addForm=useForm({location:props.location.public_id,service:props.services[0]?.public_id || '',preferred_staff:'',client_mode:'existing',client:'',client_name:'',client_mobile:'',client_email:'',arrived_at:'',notes:'',idempotency_key:''});
const openAdd=async()=>{ addForm.idempotency_key=crypto.randomUUID(); addForm.location=props.location.public_id; addForm.arrived_at=localInput(Date.now(),props.location.time_zone); addForm.clearErrors(); await addDialog.value?.open(); await nextTick(); document.getElementById(addForm.client_mode==='existing'?'walkin-client-search':'walkin-name')?.focus(); };
watch(clientSearch,value=>{
    clearTimeout(searchTimer); searchAbort?.abort(); const generation=++searchGeneration;
    clientResults.value=[]; searchingClients.value=false; searchDone.value=false; clientError.value='';
    if(value.trim().length<2 || selectedClient.value) return;
    searchingClients.value=true;
    searchTimer=setTimeout(async()=>{
        searchAbort=new AbortController();
        try{ const response=await fetch(route('business.walk-ins.clients.search',route().params.business)+`?q=${encodeURIComponent(value.trim())}`,{signal:searchAbort.signal,headers:{Accept:'application/json'}}); if(!response.ok) throw new Error(); const result=await response.json(); if(generation===searchGeneration){clientResults.value=result.clients;searchDone.value=true;} }
        catch(error){if(error.name!=='AbortError' && generation===searchGeneration) clientError.value='Client search is unavailable. Try again.';}
        finally{if(generation===searchGeneration) searchingClients.value=false;}
    },250);
});
const chooseClient=async client=>{selectedClient.value=client;addForm.client=client.public_id;clientSearch.value=client.name;clientResults.value=[];addForm.clearErrors();await nextTick();document.getElementById('walkin-service')?.focus();};
const setClientMode=async mode=>{addForm.client_mode=mode;addForm.client='';selectedClient.value=null;clientSearch.value='';clientResults.value=[];addForm.clearErrors();await nextTick();document.getElementById(mode==='new'?'walkin-name':'walkin-client-search')?.focus();};
const add=()=>addForm.post(route('business.walk-ins.store',route().params.business),{preserveScroll:true,onSuccess:()=>{addDialog.value?.close();addForm.reset('client','client_name','client_mobile','client_email','notes');selectedClient.value=null;clientSearch.value='';}});
const openEntry=entry=>{selectedId.value=entry.public_id;selectedStaff.value=entry.assigned_staff_id || entry.preferred_staff_id || entry.suggested_staff_id || '';assignmentReason.value='';actionError.value='';readiness.value=null;detailDialog.value?.open();if(selectedStaff.value) checkReadiness();};
const checkReadiness=async()=>{
    readinessAbort?.abort(); const generation=++readinessGeneration; readiness.value=null;
    if(!activeEntry.value || !selectedStaff.value) {checking.value=false;return;}
    checking.value=true; readinessAbort=new AbortController();
    try {const response=await fetch(route('business.walk-ins.readiness',[route().params.business,activeEntry.value.public_id])+`?staff=${encodeURIComponent(selectedStaff.value)}`,{signal:readinessAbort.signal,headers:{Accept:'application/json'}});if(!response.ok) throw new Error();const result=await response.json();if(generation===readinessGeneration) readiness.value=result;}
    catch(error){if(error.name!=='AbortError' && generation===readinessGeneration) readiness.value={ready:false,message:'Availability could not be checked. Retry before starting.'};}
    finally{if(generation===readinessGeneration) checking.value=false;}
};
const pickStaff=id=>{selectedStaff.value=id;actionError.value='';checkReadiness();};
const choices=computed(()=>[...(activeEntry.value?.choices || [])].sort((a,b)=>Number(b.qualified)-Number(a.qualified) || (Date.parse(a.available_at || '9999-01-01')-Date.parse(b.available_at || '9999-01-01'))));
const command=(url,data,onSuccess,method='post')=>router[method](url,data,{preserveScroll:true,onStart:()=>{mutating.value=true;actionError.value='';},onSuccess,onError:errors=>{actionError.value=Object.values(errors)[0] || 'The queue changed. Refresh and try again.';readiness.value=null;},onNetworkError:()=>{actionError.value='Connection lost. Refresh the queue to check whether your change was saved.';return false;},onFinish:()=>mutating.value=false});
const start=()=>{if(!readiness.value?.ready || !activeEntry.value || mutating.value)return;const e=activeEntry.value;command(route('business.walk-ins.start',[route().params.business,e.public_id]),{staff:selectedStaff.value,starts_at:readiness.value.starts_at,version:e.version,idempotency_key:`queue-${e.public_id}-${crypto.randomUUID()}`},()=>detailDialog.value?.close());};
const assign=()=>{if(!activeEntry.value || mutating.value)return;router.patch(route('business.walk-ins.assign',[route().params.business,activeEntry.value.public_id]),{staff:selectedStaff.value,version:activeEntry.value.version,reason:assignmentReason.value || null},{preserveScroll:true,onStart:()=>{mutating.value=true;actionError.value='';},onSuccess:()=>detailDialog.value?.close(),onError:errors=>actionError.value=Object.values(errors)[0],onNetworkError:()=>{actionError.value='Connection lost. Refresh the queue to check whether your change was saved.';return false;},onFinish:()=>mutating.value=false});};
const complete=entry=>command(route('business.appointments.status',[route().params.business,entry.appointment_id]),{status:'completed',version:entry.appointment_version,idempotency_key:`queue-complete-${crypto.randomUUID()}`},()=>{},'patch');
const leaveForm=useForm({version:1,reason:'',confirmed:true});
const openLeave=()=>{leaveForm.version=activeEntry.value.version;leaveForm.reset('reason');leaveForm.clearErrors();detailDialog.value?.close();leaveDialog.value?.open();};
const leave=()=>leaveForm.post(route('business.walk-ins.leave',[route().params.business,selectedId.value]),{preserveScroll:true,onSuccess:()=>leaveDialog.value?.close()});
const openNotify=()=>{detailDialog.value?.close();notifyDialog.value?.open();};
const notify=()=>command(route('business.walk-ins.notify',[route().params.business,selectedId.value]),{version:activeEntry.value.version},()=>notifyDialog.value?.close());
const reorderForm=useForm({location:props.location.public_id,entries:[],reason:'',confirmed:true});
const openReorder=()=>{reorderForm.entries=waiting.value.map(e=>e.public_id);reorderForm.location=props.location.public_id;reorderForm.reason='';reorderForm.clearErrors();reorderDialog.value?.open();};
const move=(index,delta)=>{const other=index+delta;if(other>=0 && other<reorderForm.entries.length)[reorderForm.entries[index],reorderForm.entries[other]]=[reorderForm.entries[other],reorderForm.entries[index]];};
const reorder=()=>reorderForm.post(route('business.walk-ins.reorder',route().params.business),{preserveScroll:true,onSuccess:()=>reorderDialog.value?.close()});
const keyHandler=event=>{if(event.key.toLowerCase()==='n' && !event.metaKey && !event.ctrlKey && !event.altKey && !document.querySelector('dialog[open]') && !event.target.closest('input,textarea,select,[contenteditable]')){event.preventDefault();openAdd();}};
onMounted(()=>{if(props.clientPrefill){chooseClient(props.clientPrefill);openAdd();}teamMedia=window.matchMedia('(max-width:1050px)');adaptTeam();teamMedia.addEventListener('change',adaptTeam);clockTimer=setInterval(()=>now.value=Date.now(),15000);pollTimer=setInterval(()=>refresh(),30000);window.addEventListener('keydown',keyHandler);});
onBeforeUnmount(()=>{teamMedia?.removeEventListener('change',adaptTeam);clearInterval(clockTimer);clearInterval(pollTimer);clearTimeout(searchTimer);searchAbort?.abort();readinessAbort?.abort();window.removeEventListener('keydown',keyHandler);});
watch(()=>props.location.public_id,()=>{search.value='';filter.value='';staffFilter.value='';});
</script>

<template>
    <AppLayout title="Walk-in queue" :business-label="businessLabel">
        <div class="wq-workspace">
            <PageHeader title="Walk-in queue">
                <template #actions>
                    <AppSelect
                        v-if="locations.length>1"
                        :model-value="location.public_id"
                        class="cd-input wq-location"
                        aria-label="Queue location"
                        @update:model-value="changeLocation"
                    >
                        <option v-for="place in locations" :key="place.public_id" :value="place.public_id">{{ place.name }}</option>
                    </AppSelect>
                    <AppButton v-if="permissions.calendar" variant="secondary" :href="calendarUrl">
                        <CalendarDaysIcon aria-hidden="true" class="size-4" />
                        Calendar
                    </AppButton>
                    <AppButton :disabled="!services.length" title="Add walk-in (N)" @click="openAdd">
                        <PlusIcon aria-hidden="true" class="size-4" />
                        Add walk-in
                    </AppButton>
                </template>
            </PageHeader>
            <div class="wq-context">
                <span>
                    {{ location.name }}
                    <span class="wq-context-dot">·</span>
                    {{ dateLabel }}
                </span>
                <span class="wq-live" :class="{'wq-live-stale':stale}">
                    <i aria-hidden="true" />
                    {{ stale ? 'Update needed' : 'Auto-refresh on' }}
                    <time class="wq-updated" :datetime="updatedAt">· {{ time(updatedAt) }}</time>
                    <button
                        class="wq-icon-button"
                        type="button"
                        :disabled="refreshBusy || mutating"
                        aria-label="Refresh queue"
                        @click="refresh(true)"
                    >
                        <ArrowPathIcon :class="{'animate-spin':refreshBusy}" aria-hidden="true" />
                    </button>
                </span>
            </div>
            <p v-if="refreshError || actionError" class="wq-error" role="alert">{{ refreshError || actionError }}</p>
            <p v-if="!services.length" class="wq-error" role="status">
                No active services are available at this location. Add an eligible service before adding walk-ins.
            </p>
            <div class="wq-board">
                <div class="wq-main">
                    <div class="wq-summary" aria-label="Queue at a glance">
                        <span>
                            <strong>{{ waiting.length }}</strong>
                            waiting
                        </span>
                        <span>
                            <strong>{{ waiting.length ? waitLabel(longest) : '—' }}</strong>
                            longest wait
                        </span>
                        <a href="#in-service-title" class="wq-service-jump" :aria-label="`View ${inService.length} walk-ins in service`">
                            <strong>{{ inService.length }}</strong>
                            in service
                        </a>
                        <button
                            v-if="attention"
                            type="button"
                            class="wq-attention-link"
                            :aria-pressed="filter==='attention'"
                            @click="filter=filter==='attention'?'':'attention'"
                        >
                            <ClockIcon aria-hidden="true" />
                            {{ attention }}
                            past estimate
                        </button>
                    </div>
                    <section class="wq-queue" aria-labelledby="waiting-title">
                        <div class="wq-toolbar">
                            <div>
                                <h2 id="waiting-title">
                                    Waiting
                                    <span>{{ waiting.length }}</span>
                                </h2>
                                <p>Actual queue order · changes recorded</p>
                            </div>
                            <div class="wq-tools">
                                <label class="wq-search">
                                    <MagnifyingGlassIcon aria-hidden="true" />
                                    <span class="sr-only">Search queue</span>
                                    <input
                                        v-model="search"
                                        type="search"
                                        placeholder="Find client, service or staff"
                                        autocomplete="off"
                                    />
                                </label>
                                <details class="wq-filter">
                                    <summary aria-label="Filter waiting queue">
                                        <AdjustmentsHorizontalIcon aria-hidden="true" />
                                        <span>Filters</span>
                                        <i v-if="filter || staffFilter" />
                                    </summary>
                                    <div class="wq-filter-menu">
                                        <FormField id="queue-status" label="Show">
                                            <AppSelect id="queue-status" v-model="filter" class="cd-input">
                                                <option value="">All waiting</option>
                                                <option value="attention">Past estimate</option>
                                                <option value="assigned">Assigned</option>
                                                <option value="notified">Notified</option>
                                            </AppSelect>
                                        </FormField>
                                        <FormField id="queue-staff-filter" label="Assigned or preferred staff">
                                            <AppSelect id="queue-staff-filter" v-model="staffFilter" class="cd-input">
                                                <option value="">All staff</option>
                                                <option
                                                    v-for="member in staff"
                                                    :key="member.public_id"
                                                    :value="member.public_id"
                                                >
                                                    {{ member.display_name }}
                                                </option>
                                            </AppSelect>
                                        </FormField>
                                        <button type="button" class="wq-text-button" @click="filter='';staffFilter=''">Clear filters</button>
                                    </div>
                                </details>
                                <button
                                    v-if="canReorder && waiting.length>1"
                                    type="button"
                                    class="wq-text-button wq-reorder"
                                    @click="openReorder"
                                >
                                    Reorder
                                </button>
                            </div>
                        </div>
                        <div v-if="search || filter || staffFilter" class="wq-filter-result">
                            {{ visibleWaiting.length }}
                            of
                            {{ waiting.length }}
                            shown · queue positions stay unchanged
                            <button type="button" @click="search='';filter='';staffFilter=''">
                                Clear
                                <XMarkIcon aria-hidden="true" />
                            </button>
                        </div>
                        <div class="wq-columns" aria-hidden="true">
                            <span>#</span>
                            <span>Client</span>
                            <span>Waiting</span>
                            <span>Service</span>
                            <span>Staff</span>
                            <span>Est. wait</span>
                            <span />
                        </div>
                        <ol v-if="visibleWaiting.length" class="wq-list">
                            <QueueRow
                                v-for="entry in visibleWaiting"
                                :key="entry.public_id"
                                :entry="entry"
                                :now="now"
                                :updated-at="updatedAt"
                                :first="entry.public_id===waiting[0]?.public_id"
                                :zone="location.time_zone"
                                @open="openEntry"
                            />
                        </ol>
                        <div v-else class="wq-empty">
                            <CheckCircleIcon aria-hidden="true" />
                            <h3>{{ waiting.length ? 'No matching walk-ins' : 'No clients are waiting right now.' }}</h3>
                            <p>
                                {{ waiting.length ? 'Try a different search or clear your filters.' : 'The next arrival starts here. Your team’s availability is beside you.' }}
                            </p>
                            <button
                                v-if="waiting.length"
                                type="button"
                                class="wq-text-button"
                                @click="search='';filter='';staffFilter=''"
                            >
                                Clear filters
                            </button>
                            <AppButton
                                v-else
                                variant="secondary"
                                size="small"
                                :disabled="!services.length"
                                @click="openAdd"
                            >
                                <PlusIcon class="size-4" aria-hidden="true" />
                                Add walk-in
                            </AppButton>
                        </div>
                        <footer class="wq-queue-footer">
                            <ClockIcon aria-hidden="true" />
                            <span>Estimates use queue order, staff skills and today’s schedule. Resources are checked before starting.</span>
                        </footer>
                    </section>
                    <section class="wq-activity" aria-labelledby="in-service-title">
                        <div class="wq-section-title">
                            <h2 id="in-service-title">
                                <ScissorsIcon aria-hidden="true" />
                                In service
                                <span>{{ inService.length }}</span>
                            </h2>
                            <span>Walk-ins underway</span>
                        </div>
                        <p v-if="!inService.length" class="wq-quiet-empty">No walk-in services underway.</p>
                        <div v-for="entry in inService" :key="entry.public_id" class="wq-service-row">
                            <span class="wq-avatar wq-avatar-service">{{ initials(entry.client_name) }}</span>
                            <div class="wq-active-client">
                                <strong>{{ entry.client_name }}</strong>
                                <span>{{ entry.service_name }} · {{ entry.assigned_staff_name || 'Staff unavailable' }}</span>
                            </div>
                            <div class="wq-active-time">
                                <strong>{{ waitLabel(elapsedMinutes(entry.service_started_at,now)) }} in service</strong>
                                <span
                                    :class="{'wq-overrun':entry.service_ends_at && Date.parse(entry.service_ends_at)<now}"
                                >
                                    {{ entry.service_ends_at && Date.parse(entry.service_ends_at)<now ? 'Past planned finish' : `Planned finish ${time(entry.service_ends_at)}` }}
                                </span>
                            </div>
                            <AppButton
                                v-if="entry.can_complete"
                                variant="secondary"
                                size="small"
                                :loading="mutating"
                                :aria-label="`Complete ${entry.client_name}`"
                                @click="complete(entry)"
                            >
                                Complete
                                <CheckCircleIcon class="size-4" aria-hidden="true" />
                            </AppButton>
                            <AppButton v-else-if="permissions.calendar" variant="quiet" size="small" :href="calendarUrl">Calendar</AppButton>
                        </div>
                    </section>
                    <details v-if="recent.length" class="wq-recent">
                        <summary>
                            Recently finished
                            <span>{{ recent.length }}</span>
                        </summary>
                        <div v-for="entry in recent" :key="entry.public_id" class="wq-recent-row">
                            <div>
                                <strong>{{ entry.client_name }}</strong>
                                <span>
                                    {{ entry.service_name }}
                                    ·
                                    {{ entry.status==='left'?'Left the queue':'Completed' }}
                                    <template v-if="entry.actual_wait_minutes!==null">· waited {{ waitLabel(entry.actual_wait_minutes) }}</template>
                                </span>
                            </div>
                            <AppButton
                                v-if="permissions.checkout && entry.status==='completed' && entry.appointment_id"
                                size="small"
                                variant="quiet"
                                :href="checkoutUrl(entry)"
                            >
                                Checkout
                                <ChevronRightIcon class="size-4" aria-hidden="true" />
                            </AppButton>
                        </div>
                    </details>
                </div>
                <aside class="wq-team" aria-labelledby="team-title">
                    <details :open="teamExpanded" @toggle="teamExpanded=$event.target.open">
                        <summary class="wq-team-heading">
                            <h2 id="team-title">
                                <UserGroupIcon aria-hidden="true" />
                                Team availability
                            </h2>
                            <span>
                                {{ available }}
                                free now
                                <ChevronDownIcon class="wq-team-chevron" aria-hidden="true" />
                            </span>
                        </summary>
                        <p class="wq-team-note">Staff time · service fit checked on selection</p>
                        <div v-if="!staff.length" class="wq-quiet-empty">No active staff at this location.</div>
                        <div v-for="(member,index) in staff" :key="member.public_id" class="wq-team-row">
                            <span class="wq-avatar" :class="`wq-avatar-${index%3}`">{{ initials(member.display_name) }}</span>
                            <div>
                                <strong>{{ member.display_name }}</strong>
                                <span class="wq-staff-state" :class="{'wq-staff-free':member.available_now}">
                                    <i aria-hidden="true" />
                                    {{ member.state }}
                                </span>
                                <small v-if="member.next_booking_at">
                                    Booking
                                    {{ time(member.next_booking_at) }}
                                    <template v-if="member.available_now">· {{ waitLabel(member.gap_minutes) }} gap</template>
                                </small>
                                <small v-else-if="member.available_now">{{ waitLabel(member.gap_minutes) }} free in schedule</small>
                                <small v-else-if="member.available_at">Next gap {{ time(member.available_at) }}</small>
                                <template
                                    v-for="visit in inService.filter(e=>e.assigned_staff_id===member.public_id)"
                                    :key="visit.public_id"
                                >
                                    <small class="wq-serving-name">{{ visit.client_name }}</small>
                                    <button
                                        v-if="visit.can_complete"
                                        type="button"
                                        class="wq-text-button"
                                        :disabled="mutating"
                                        :aria-label="`Finish service for ${visit.client_name}`"
                                        @click="complete(visit)"
                                    >
                                        Complete
                                        <CheckCircleIcon class="size-3.5" aria-hidden="true" />
                                    </button>
                                </template>
                            </div>
                        </div>
                        <AppButton
                            v-if="permissions.calendar"
                            variant="quiet"
                            size="small"
                            class="wq-team-calendar"
                            :href="calendarUrl"
                        >
                            Review staff calendar
                            <ChevronRightIcon class="size-4" aria-hidden="true" />
                        </AppButton>
                        <p class="wq-team-footnote">
                            {{ location.time_zone }}
                            <br></br>
                            Updates every 30 seconds while idle.
                        </p>
                    </details>
                </aside>
            </div>
        </div>
        <AppDialog
            id="queue-visit"
            ref="detailDialog"
            drawer
            :title="activeEntry?.client_name || 'Walk-in details'"
            :description="activeEntry ? `Queue position ${activeEntry.queue_position} · checked in ${time(activeEntry.arrived_at)}` : ''"
        >
            <template v-if="activeEntry">
                <div class="wq-detail-summary">
                    <span>
                        <ClockIcon aria-hidden="true" />
                        <strong>{{ waitLabel(elapsedMinutes(activeEntry.arrived_at,now)) }}</strong>
                        waiting
                    </span>
                    <span>{{ activeEntry.service_name }} · {{ activeEntry.duration_minutes }} min</span>
                </div>
                <p v-if="pastQuote(activeEntry,now)" class="wq-notice">
                    Past the arrival estimate of
                    {{ time(activeEntry.original_estimated_at) }}
                    . Check in with the client.
                </p>
                <p v-if="activeEntry.public_id!==waiting[0]?.public_id" class="wq-notice">
                    {{ waiting.findIndex(e=>e.public_id===activeEntry.public_id) }}
                    ahead in queue order. Starting this client keeps the remaining queue in its current order.
                </p>
                <p v-if="activeEntry.preferred_staff_name" class="wq-preference">
                    Preferred:
                    <strong>{{ activeEntry.preferred_staff_name }}</strong>
                    <template v-if="selectedStaff && selectedStaff!==activeEntry.preferred_staff_id">· confirm the change with the client.</template>
                </p>
                <h3 class="wq-drawer-label">Choose staff</h3>
                <div class="wq-staff-options" role="group" aria-label="Service staff">
                    <button
                        v-for="choice in choices"
                        :key="choice.staff_id"
                        type="button"
                        :disabled="!choice.qualified || mutating"
                        :aria-pressed="selectedStaff===choice.staff_id"
                        :class="{'wq-staff-selected':selectedStaff===choice.staff_id}"
                        @click="pickStaff(choice.staff_id)"
                    >
                        <span class="wq-avatar">{{ initials(choice.name) }}</span>
                        <span>
                            <strong>{{ choice.name }}</strong>
                            <small>
                                {{ !choice.qualified ? 'Not qualified for this service' : choice.available_at ? `Queue estimate ${time(choice.available_at)} · ${choice.duration_minutes} min` : 'No full service gap in today’s schedule' }}
                            </small>
                        </span>
                        <CheckCircleIcon v-if="selectedStaff===choice.staff_id" aria-hidden="true" />
                    </button>
                </div>
                <p v-if="!choices.length" class="wq-notice">No eligible staff are available at this location. Review the service and team setup.</p>
                <p v-if="activeEntry.appointment_id && permissions.calendar" class="wq-search-status">
                    <a :href="route('business.calendar',{business:route().params.business,location:location.public_id,appointment:activeEntry.appointment_id})" class="wq-text-button">Open linked Calendar visit</a>
                </p>
                <div class="wq-readiness" :class="{'wq-readiness-ready':readiness?.ready}" role="status">
                    <p v-if="checking">Checking staff, bookings, hours and resources…</p>
                    <template v-else-if="readiness">
                        <strong>
                            {{ readiness.ready ? `Schedule start ${time(readiness.starts_at)} · finish ${time(readiness.ends_at)}` : 'Cannot start with this staff member' }}
                        </strong>
                        <p>{{ readiness.message }}</p>
                        <button v-if="!readiness.ready" type="button" class="wq-text-button" @click="checkReadiness">Check again</button>
                    </template>
                    <p v-else>
                        Select staff to check the full service fit.
                        <button v-if="selectedStaff" type="button" class="wq-text-button" @click="checkReadiness">Check again</button>
                    </p>
                </div>
                <FormField
                    v-if="activeEntry.assigned_staff_id && selectedStaff!==activeEntry.assigned_staff_id"
                    id="queue-reassign-reason"
                    label="Reassignment note (optional)"
                >
                    <input
                        id="queue-reassign-reason"
                        v-model="assignmentReason"
                        class="cd-input"
                        maxlength="1000"
                        placeholder="Why is the assignment changing?"
                    />
                </FormField>
                <p v-if="actionError" class="wq-error" role="alert">{{ actionError }}</p>
                <details class="wq-visit-details">
                    <summary>Client & visit details</summary>
                    <p v-if="activeEntry.client_mobile">{{ activeEntry.client_mobile }}</p>
                    <p v-if="activeEntry.notes" class="wq-note-text">{{ activeEntry.notes }}</p>
                    <p v-else>No visible visit note.</p>
                    <a
                        v-if="activeEntry.client_public_id"
                        :href="route('business.clients.show',[route().params.business,activeEntry.client_public_id])"
                        class="wq-text-button"
                    >
                        Open client profile
                    </a>
                    <ul class="wq-history">
                        <li v-for="(item,index) in activeEntry.history" :key="index">
                            <span>{{ time(item.at) }}</span>
                            <div>
                                {{ ({created:'Joined queue',assigned:'Staff assigned',notified:'Turn notification requested',reordered:'Queue order changed',converted:'Appointment created',calendar_updated:'Calendar visit updated',service_started:'Service started',left:'Left queue',completed:'Completed'})[item.action] || item.action }}
                                <small v-if="item.reason">{{ item.reason }}</small>
                            </div>
                        </li>
                    </ul>
                </details>
                <div class="wq-secondary-actions">
                    <button type="button" :disabled="mutating || activeEntry.status==='notified'" @click="openNotify">
                        <BellAlertIcon aria-hidden="true" />
                        {{ activeEntry.status==='notified'?'Notification requested':'Notify client' }}
                    </button>
                    <button v-if="!activeEntry.appointment_id" type="button" :disabled="mutating" @click="openLeave">Mark as left</button>
                </div>
            </template>
            <template #footer>
                <AppButton variant="quiet" :disabled="mutating" @click="detailDialog?.close()">Close</AppButton>
                <AppButton
                    v-if="!activeEntry?.appointment_id && selectedStaff && selectedStaff!==activeEntry?.assigned_staff_id"
                    variant="secondary"
                    :disabled="!choices.some(c=>c.staff_id===selectedStaff && c.qualified)"
                    :loading="mutating"
                    @click="assign"
                >
                    Save assignment
                </AppButton>
                <AppButton :disabled="!readiness?.ready || checking" :loading="mutating" @click="start">
                    <ScissorsIcon class="size-4" aria-hidden="true" />
                    Start service
                </AppButton>
            </template>
        </AppDialog>
        <AppDialog
            id="add-walk-in"
            ref="addDialog"
            drawer
            title="Add walk-in"
            :description="`Check in at ${location.name}.`"
            confirm-label="Add to queue"
            :close-on-confirm="false"
            :confirm-disabled="addForm.processing || !addForm.service || (addForm.client_mode==='existing' && !addForm.client)"
            @confirm="add"
        >
            <div class="wq-add-form">
                <p v-if="Object.keys(addForm.errors).length" class="wq-error" role="alert">{{ Object.values(addForm.errors)[0] }}</p>
                <div class="wq-client-tabs" aria-label="Client choice">
                    <button
                        type="button"
                        :aria-pressed="addForm.client_mode==='existing'"
                        @click="setClientMode('existing')"
                    >
                        Existing client
                    </button>
                    <button type="button" :aria-pressed="addForm.client_mode==='new'" @click="setClientMode('new')">New client</button>
                </div>
                <div v-if="addForm.client_mode==='existing'">
                    <template v-if="!selectedClient">
                        <FormField id="walkin-client-search" label="Find a client" required>
                            <input
                                id="walkin-client-search"
                                v-model="clientSearch"
                                class="cd-input"
                                autocomplete="off"
                                :placeholder="permissions.contact?'Name, phone or email':'Search by name'"
                                aria-controls="walkin-search-results"
                            />
                        </FormField>
                        <p v-if="searchingClients" class="wq-search-status" role="status">Searching clients…</p>
                        <p v-if="clientError" class="wq-error" role="alert">{{ clientError }}</p>
                        <p v-if="searchDone" class="sr-only" role="status">{{ clientResults.length }} matching clients.</p>
                        <div id="walkin-search-results" class="wq-client-results">
                            <button
                                v-for="client in clientResults"
                                :key="client.public_id"
                                type="button"
                                @click="chooseClient(client)"
                            >
                                <span class="wq-avatar">{{ initials(client.name) }}</span>
                                <span>
                                    <strong>{{ client.name }}</strong>
                                    <small>{{ client.mobile || client.email || 'Existing client' }}</small>
                                </span>
                                <ChevronRightIcon aria-hidden="true" />
                            </button>
                        </div>
                        <p v-if="searchDone && !clientResults.length" class="wq-search-status">
                            No matching client with a mobile number.
                            <button
                                type="button"
                                class="wq-text-button"
                                @click="addForm.client_name=clientSearch;setClientMode('new')"
                            >
                                Add a new client
                            </button>
                        </p>
                    </template>
                    <div v-else class="wq-selected-client">
                        <span class="wq-avatar">{{ initials(selectedClient.name) }}</span>
                        <span>
                            <strong>{{ selectedClient.name }}</strong>
                            <small>{{ selectedClient.mobile || 'Existing client linked' }}</small>
                        </span>
                        <button
                            type="button"
                            class="wq-icon-button"
                            aria-label="Change selected client"
                            @click="setClientMode('existing')"
                        >
                            <XMarkIcon aria-hidden="true" />
                        </button>
                    </div>
                    <p v-if="selectedClient && addForm.errors.client" class="wq-error" role="alert">{{ addForm.errors.client }}</p>
                    <a v-if="selectedClient && permissions.clients" class="wq-text-button" :href="route('business.clients.show',[route().params.business,selectedClient.public_id])">Open client profile</a>
                </div>
                <template v-else>
                    <FormField id="walkin-name" label="Client name" required :error="addForm.errors.client_name">
                        <input
                            id="walkin-name"
                            v-model="addForm.client_name"
                            class="cd-input"
                            autocomplete="name"
                            maxlength="255"
                            :aria-invalid="!!addForm.errors.client_name"
                        />
                    </FormField>
                    <FormField id="walkin-mobile" label="Mobile number" required :error="addForm.errors.client_mobile">
                        <PhoneInput
                            id="walkin-mobile"
                            v-model="addForm.client_mobile"
                            required
                            :country="$page.props.tenant?.regional?.country_code || 'IN'"
                            :aria-invalid="!!addForm.errors.client_mobile"
                        />
                    </FormField>
                </template>
                <FormField id="walkin-service" label="Requested service" required :error="addForm.errors.service">
                    <AppSelect id="walkin-service" v-model="addForm.service" class="cd-input">
                        <option v-for="service in services" :key="service.public_id" :value="service.public_id">
                            {{ service.name }}
                            ·
                            {{ service.duration_minutes }}
                            min
                        </option>
                    </AppSelect>
                </FormField>
                <FormField id="walkin-staff" label="Preferred staff" :error="addForm.errors.preferred_staff">
                    <AppSelect id="walkin-staff" v-model="addForm.preferred_staff" class="cd-input">
                        <option value="">First available</option>
                        <option v-for="member in staff" :key="member.public_id" :value="member.public_id">{{ member.display_name }}</option>
                    </AppSelect>
                </FormField>
                <details
                    class="wq-add-extra"
                    :open="!!(addForm.errors.arrived_at || addForm.errors.notes || addForm.errors.client_email)"
                >
                    <summary>Arrival time, notes & optional details</summary>
                    <FormField
                        id="walkin-arrival"
                        label="Arrival time"
                        :hint="location.time_zone"
                        :error="addForm.errors.arrived_at"
                    >
                        <input
                            id="walkin-arrival"
                            v-model="addForm.arrived_at"
                            type="datetime-local"
                            class="cd-input"
                            :aria-invalid="!!addForm.errors.arrived_at"
                        />
                    </FormField>
                    <FormField
                        v-if="addForm.client_mode==='new'"
                        id="walkin-email"
                        label="Email (optional)"
                        :error="addForm.errors.client_email"
                    >
                        <input
                            id="walkin-email"
                            v-model="addForm.client_email"
                            type="email"
                            class="cd-input"
                            autocomplete="email"
                        />
                        <small>Queue emails follow the client’s notification preferences.</small>
                    </FormField>
                    <FormField v-if="permissions.notes" id="walkin-notes" label="Visit note (optional)" :error="addForm.errors.notes">
                        <textarea id="walkin-notes" v-model="addForm.notes" rows="2" maxlength="2000" class="cd-input" />
                    </FormField>
                </details>
                <p class="wq-add-footnote">
                    <CheckCircleIcon aria-hidden="true" />
                    {{ addForm.client_mode==='new' ? 'Only a name and mobile number are needed to check in.' : 'Saved client details will be used. Choose staff after check-in.' }}
                </p>
            </div>
        </AppDialog>
        <AppDialog
            id="leave-walk-in"
            ref="leaveDialog"
            title="Mark client as left?"
            description="Remove them from the active queue. Their visit and waiting history will be retained."
            confirm-label="Mark as left"
            destructive
            :close-on-confirm="false"
            :confirm-disabled="leaveForm.processing"
            @confirm="leave"
        >
            <FormField id="leave-reason" label="Reason" required :error="leaveForm.errors.reason">
                <textarea
                    id="leave-reason"
                    v-model="leaveForm.reason"
                    rows="3"
                    maxlength="1000"
                    class="cd-input"
                    :aria-invalid="!!leaveForm.errors.reason"
                />
            </FormField>
        </AppDialog>
        <AppDialog
            id="notify-walk-in"
            ref="notifyDialog"
            title="Notify client?"
            description="Request a turn-approaching message using the client’s enabled notification channels. Delivery follows their preferences."
            confirm-label="Notify client"
            :close-on-confirm="false"
            :confirm-disabled="mutating"
            @confirm="notify"
        >
            <p class="text-sm">{{ activeEntry?.client_name }} · queue position {{ activeEntry?.queue_position }}</p>
            <p v-if="actionError" class="wq-error" role="alert">{{ actionError }}</p>
        </AppDialog>
        <AppDialog
            id="reorder-walk-ins"
            ref="reorderDialog"
            title="Reorder queue"
            description="Change the actual queue order. A reason is recorded for the team."
            confirm-label="Save order"
            :close-on-confirm="false"
            :confirm-disabled="reorderForm.processing"
            @confirm="reorder"
        >
            <p v-if="Object.keys(reorderForm.errors).length" class="wq-error" role="alert">{{ Object.values(reorderForm.errors)[0] }}</p>
            <ol class="wq-reorder-list">
                <li v-for="(id,index) in reorderForm.entries" :key="id">
                    <span>{{ index+1 }}</span>
                    <strong>{{ waiting.find(e=>e.public_id===id)?.client_name }}</strong>
                    <button
                        type="button"
                        class="wq-icon-button"
                        :disabled="index===0"
                        :aria-label="`Move ${waiting.find(e=>e.public_id===id)?.client_name} up`"
                        @click="move(index,-1)"
                    >
                        <ArrowUpIcon aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="wq-icon-button"
                        :disabled="index===reorderForm.entries.length-1"
                        :aria-label="`Move ${waiting.find(e=>e.public_id===id)?.client_name} down`"
                        @click="move(index,1)"
                    >
                        <ArrowDownIcon aria-hidden="true" />
                    </button>
                </li>
            </ol>
            <FormField id="reorder-reason" label="Reason" required :error="reorderForm.errors.reason">
                <textarea
                    id="reorder-reason"
                    v-model="reorderForm.reason"
                    rows="2"
                    maxlength="1000"
                    class="cd-input"
                    :aria-invalid="!!reorderForm.errors.reason"
                />
            </FormField>
        </AppDialog>
    </AppLayout>
</template>
