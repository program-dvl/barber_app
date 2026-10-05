<script setup>
import { computed, ref, watch } from 'vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import { billingMoney } from '@/Support/billingWorkspace';
import { capacityEstimate, capacityAnnualSaving } from '@/Support/capacityPricing';

const props = defineProps({ catalog: Object, country: String, current: Object, usage: Object, disabled: Boolean, actionLabel: { type: String, default: 'Review subscription' } });
const emit = defineEmits(['review']);
const marketCode = ref(props.current?.market || props.country || props.catalog?.markets?.[0]?.code || '');
const interval = ref(props.current?.billing_interval || 'monthly');
const locations = ref(props.current?.locations || Math.max(1, props.usage?.['locations.max'] || 1));
const staff = ref(props.current?.staff || Math.max(1, props.usage?.['staff.max'] || 1));
const market = computed(() => props.catalog?.markets?.find(item => item.code === marketCode.value));
const rate = computed(() => props.current?.market === marketCode.value && props.current?.billing_interval === interval.value
    ? { ...props.current, sms_monthly_allowance: props.current.sms_monthly_allowance / props.current.staff, ready: Boolean(market.value?.intervals?.[interval.value]?.ready) } : market.value?.intervals?.[interval.value]);
const estimate = computed(() => capacityEstimate(rate.value, locations.value, staff.value));
const saving = computed(() => capacityAnnualSaving(market.value, locations.value, staff.value));
const money = value => billingMoney(value, estimate.value?.currency || market.value?.currency || 'USD');
watch(() => props.current, value => { if (value) { marketCode.value = value.market; interval.value = value.billing_interval; locations.value = value.locations; staff.value = value.staff; } });
</script>

<template>
    <div class="capacity-calculator">
        <div class="capacity-inputs">
            <label>Pricing country<AppSelect v-model="marketCode" class="cd-input" :disabled="Boolean(current)"><option value="" disabled>Choose country</option><option v-if="country && !catalog?.markets?.some(item => item.code === country)" :value="country">{{ country }} · pricing being prepared</option><option v-for="item in catalog?.markets || []" :key="item.code" :value="item.code">{{ item.name }} · {{ item.currency }}</option></AppSelect></label>
            <fieldset class="capacity-cadence"><legend>Billing cycle</legend><label><input type="radio" v-model="interval" value="monthly" :disabled="!market?.intervals?.monthly"> Monthly</label><label><input type="radio" v-model="interval" value="annual" :disabled="!market?.intervals?.annual"> Yearly</label></fieldset>
            <label>Locations<input v-model="locations" class="cd-input" type="number" inputmode="numeric" min="1" max="1000" step="1"><small>Active stores or branches.</small></label>
            <label>Bookable staff<input v-model="staff" class="cd-input" type="number" inputmode="numeric" min="1" max="10000" step="1"><small>People who take appointments. Count each person once across locations.</small></label>
        </div>
        <div v-if="estimate" class="capacity-total" aria-live="polite" aria-atomic="true">
            <span v-if="!estimate.ready" class="billing-status warning">Draft example · payments disabled</span>
            <p class="capacity-amount">{{ money(estimate.total_minor) }}<span>/ {{ interval === 'annual' ? 'year' : 'month' }}</span></p>
            <p>{{ estimate.currency }} · {{ interval === 'annual' ? 'Billed yearly, upfront' : 'Billed monthly' }} · before tax</p>
            <dl><div><dt>1 location + 1 bookable staff</dt><dd>{{ money(estimate.base_minor) }}</dd></div><div v-if="estimate.locations > 1"><dt>{{ estimate.locations - 1 }} extra locations × {{ money(estimate.location_minor) }}</dt><dd>{{ money((estimate.locations - 1) * estimate.location_minor) }}</dd></div><div v-if="estimate.staff > 1"><dt>{{ estimate.staff - 1 }} extra staff × {{ money(estimate.staff_minor) }}</dt><dd>{{ money((estimate.staff - 1) * estimate.staff_minor) }}</dd></div></dl>
            <p v-if="interval === 'annual' && saving">Save {{ money(saving) }} a year compared with monthly billing.</p>
            <p><strong>{{ estimate.sms_monthly_allowance.toLocaleString() }} text credits / month</strong>, shared by your business. Routine email notifications included.</p>
            <AppButton :disabled="disabled || !estimate.ready" @click="emit('review', estimate)">{{ actionLabel }}</AppButton>
        </div>
        <p v-else class="billing-alert neutral" role="status">{{ market ? 'Enter whole numbers: at least one location and one bookable staff member.' : 'Local pricing is being prepared for this country. Your existing subscription remains available above.' }}</p>
        <p class="capacity-footnote">Receptionists and administrators without bookable staff profiles are included. Empty chairs and archived people do not count. Text credits depend on message length and destination; one supported standard local SMS uses one credit. Extra texts require an optional purchase.</p>
    </div>
</template>
