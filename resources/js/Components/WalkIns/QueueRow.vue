<script setup>
import { computed } from 'vue';
import { ChevronRightIcon, ClockIcon, ScissorsIcon } from '@heroicons/vue/24/outline';
import { elapsedMinutes, estimateLabel, pastQuote, waitLabel } from '@/Support/walkInWorkspace';
import AppButton from '@/Components/Product/AppButton.vue';
const props = defineProps({entry:Object, now:Number, updatedAt:String, first:Boolean, zone:String});
const emit = defineEmits(['open']);
const late = computed(() => pastQuote(props.entry, props.now));
const time = value => new Intl.DateTimeFormat(undefined,{hour:'numeric',minute:'2-digit',timeZone:props.zone}).format(new Date(value));
const status = computed(() => ({waiting:'Waiting', assigned:'Assigned', notified:'Notified'}[props.entry.status] || props.entry.status));
</script>
<template>
    <li
        class="wq-row"
        :class="{'wq-row-next':first,'wq-row-attention':late}"
        :aria-label="`Position ${entry.queue_position}, ${entry.client_name}, ${status}`"
    >
        <div class="wq-position">
            <span>{{ entry.queue_position }}</span>
            <small v-if="first">NEXT</small>
        </div>
        <div class="wq-client">
            <button class="wq-client-name" :title="entry.client_name" type="button" @click="emit('open',entry)">{{ entry.client_name }}</button>
            <p>{{ entry.client_mobile || `Checked in ${time(entry.arrived_at)}` }}</p>
            <span v-if="entry.history.some(h=>h.action==='reordered')" class="wq-inline-note">Order adjusted</span>
        </div>
        <div class="wq-wait">
            <strong>
                <ClockIcon aria-hidden="true" />
                {{ waitLabel(elapsedMinutes(entry.arrived_at,now)) }}
            </strong>
            <small>{{ late ? 'Past estimate' : `Since ${time(entry.arrived_at)}` }}</small>
        </div>
        <div class="wq-service">
            <span>
                <ScissorsIcon aria-hidden="true" />
                {{ entry.service_name }}
            </span>
            <small>{{ entry.duration_minutes ? `${entry.duration_minutes} min service` : 'Duration unavailable' }}</small>
        </div>
        <div class="wq-assignment">
            <span>{{ entry.assigned_staff_name || entry.preferred_staff_name || 'First available' }}</span>
            <small>{{ entry.assigned_staff_name ? 'Assigned' : entry.preferred_staff_name ? 'Preferred staff' : 'Any qualified staff' }}</small>
        </div>
        <div
            class="wq-estimate"
            :title="entry.estimated_at ? `Staff estimate ${time(entry.estimated_at)}` : 'No full service gap found today'"
        >
            <strong>{{ estimateLabel(entry.estimated_at,now,updatedAt) }}</strong>
            <small>{{ status }}</small>
        </div>
        <AppButton
            size="small"
            :variant="first ? 'primary' : 'secondary'"
            class="wq-row-action"
            :aria-label="`Serve ${entry.client_name}`"
            @click="emit('open',entry)"
        >
            Serve
            <ChevronRightIcon class="size-3.5" aria-hidden="true" />
        </AppButton>
    </li>
</template>
