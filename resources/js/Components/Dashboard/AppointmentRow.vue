<script setup>
import AppButton from '@/Components/Product/AppButton.vue';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
defineProps({ event: Object, timeZone: String, locale: String, busy: Boolean, isToday: Boolean, now: Number });
const emit = defineEmits(['open', 'act']);
const time = (value, zone, locale) => new Intl.DateTimeFormat(locale, {hour:'numeric', minute:'2-digit', timeZone:zone}).format(new Date(value));
const tones = { warning:'var(--status-warning)', danger:'var(--status-danger)', success:'var(--status-success)', strong:'var(--action-primary)', info:'var(--status-info)', neutral:'var(--text-muted)' };
</script>
<template>
    <article class="dw-appointment" :aria-label="`${event.title}, ${event.statusLabel}`">
        <div class="dw-time"><time :datetime="event.startsAt">{{ time(event.startsAt, timeZone, locale) }}</time><span>{{ Math.round((new Date(event.endsAt) - new Date(event.startsAt))/60000) }} min</span></div>
        <button type="button" class="dw-appointment-info" :aria-label="`Open ${event.title} appointment details`" @click="emit('open',event)">
            <span class="dw-client">{{ event.title }}<ChevronRightIcon class="size-3.5 shrink-0" aria-hidden="true" /></span>
            <span class="dw-service">{{ event.services.map(s => s.name).join(' + ') || 'Appointment' }}</span>
            <span class="dw-staff">{{ event.staff.map(s => s.name).join(', ') || 'Staff not assigned' }}</span>
        </button>
        <div class="dw-row-state"><span class="dw-status" :style="{'--status-ink': tones[event.tone] || tones.neutral}">{{ event.statusLabel }}</span><span v-if="isToday && event.status === 'in_service' && new Date(event.endsAt).getTime() < now" class="dw-exception">Past finish time</span></div>
        <div class="dw-row-action"><AppButton v-if="event.action" variant="secondary" size="small" :loading="busy" :aria-label="`${event.action.label}: ${event.title}`" @click="emit('act',event)">{{ event.action.label }}</AppButton><AppButton v-else-if="event.checkoutHref" variant="secondary" size="small" :href="event.checkoutHref">Checkout</AppButton><button v-else type="button" class="dw-text-action" :aria-label="`Open ${event.title} appointment details`" @click="emit('open',event)">Details</button></div>
    </article>
</template>
