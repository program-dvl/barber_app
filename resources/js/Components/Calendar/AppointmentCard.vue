<script setup>
import { computed } from 'vue';
import { CheckIcon, ClockIcon, ExclamationTriangleIcon, PauseIcon, PlayIcon, XMarkIcon, UserIcon } from '@heroicons/vue/16/solid';
const props = defineProps({event:Object, height:Number, selected:Boolean, timeZone:String, draggable:Boolean});
const emit = defineEmits(['open','dragstart','dragend']);
const icon = computed(() => ({pending_confirmation:ClockIcon, confirmed:CheckIcon, arrived:UserIcon, checked_in:UserIcon, in_service:PlayIcon, completed:CheckIcon, late:ExclamationTriangleIcon, no_show:XMarkIcon, cancelled_by_client:XMarkIcon,cancelled_by_shop:XMarkIcon,rescheduled:ClockIcon}[props.event.status] || ClockIcon));
const time = value => new Intl.DateTimeFormat(undefined,{hour:'numeric',minute:'2-digit',timeZone:props.timeZone}).format(new Date(value));
const services = computed(() => props.event.services.map(s=>s.name).join(' + '));
const summary = computed(() => `${props.event.title} · ${services.value} · ${time(props.event.startsAt)}–${time(props.event.endsAt)} · ${props.event.processing ? 'Processing — staff released' : props.event.statusLabel}`);
</script>
<template>
    <button type="button" class="cal-appointment" :class="[`cal-tone-${event.tone}`, {'is-selected':selected,'is-tiny':height<35,'is-short':height<60,'is-processing':event.processing,'is-finished':['completed','cancelled_by_client','cancelled_by_shop','no_show','rescheduled'].includes(event.status)}]" :draggable="draggable" :title="summary" :aria-label="summary" aria-haspopup="dialog" @click="emit('open',event)" @dragstart="emit('dragstart',$event,event)" @dragend="emit('dragend')">
        <span class="cal-card-top"><strong>{{ event.title }}</strong><small v-if="height>=35">{{ time(event.startsAt) }}</small><component :is="event.processing ? PauseIcon : icon" class="cal-card-icon" aria-hidden="true" /></span>
        <span v-if="height>=35" class="cal-card-service"><span>{{ event.processing ? 'Processing · staff released' : services }}</span><small v-if="height<60 && !event.processing">{{ event.statusLabel }}</small></span>
        <span v-if="height>=60" class="cal-card-time">{{ time(event.startsAt) }}–{{ time(event.endsAt) }}<span>{{ event.statusLabel }}</span></span>
        <span v-if="height>=100" class="cal-card-meta"><span>{{ event.source === 'walk_in' ? 'Walk-in' : event.source === 'online' ? 'Online booking' : `${Math.round((Date.parse(event.endsAt)-Date.parse(event.startsAt))/60000)} min` }}</span><span v-if="event.forms?.pending">Form needed</span><span v-else-if="event.internalNotes">Note</span><span v-else-if="event.checkoutReady">Checkout</span></span>
    </button>
</template>
