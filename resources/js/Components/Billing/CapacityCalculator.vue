<script setup>
import { computed, ref, watch } from 'vue';
import { CheckCircleIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import { billingMoney } from '@/Support/billingWorkspace';
import { capacityEstimate, capacityAnnualSaving, capacityMonthlyEquivalent } from '@/Support/capacityPricing';

const props = defineProps({ initialSelection: Object, catalog: Object, country: String, current: Object, usage: Object, disabled: Boolean, reviewDisabled: Boolean, actionLabel: { type: String, default: 'Review subscription' } });
const emit = defineEmits(['review']);
const markets = computed(() => (props.catalog?.markets || []).filter(item => Object.values(item.intervals).some(rate => rate.ready || rate.preview_only)));
const marketCode = ref(markets.value.find(item => item.code === props.current?.market)?.code || markets.value[0]?.code || '');
const interval = ref(props.current?.billing_interval || props.initialSelection?.selected_billing_interval || 'monthly');
const locations = ref(props.current?.locations || Math.max(1, props.usage?.['locations.max'] || 1, props.initialSelection?.selected_locations || 1));
const staff = ref(props.current?.staff || Math.max(1, props.usage?.['staff.max'] || 1, props.initialSelection?.selected_staff || 1));
const useCurrentRates = ref(false);
const newerRates = computed(() => props.current && market.value?.intervals?.[interval.value]?.ready && props.current.revision !== market.value.intervals[interval.value].revision);
const market = computed(() => props.catalog?.markets?.find(item => item.code === marketCode.value));
const rate = computed(() => !useCurrentRates.value && props.current?.market === marketCode.value && props.current?.billing_interval === interval.value
    ? { ...props.current, sms_monthly_allowance: props.current.sms_monthly_allowance / props.current.staff, ready: Boolean(market.value?.intervals?.[interval.value]?.ready) } : market.value?.intervals?.[interval.value]);
const estimate = computed(() => { const value = capacityEstimate(rate.value, locations.value, staff.value); return value ? { ...value, use_current_rates: useCurrentRates.value } : null; });
const saving = computed(() => !useCurrentRates.value && props.current && props.current.revision !== market.value?.intervals?.[interval.value]?.revision ? 0 : capacityAnnualSaving(market.value, locations.value, staff.value));
const savingPercent = computed(() => { const monthly = capacityEstimate(market.value?.intervals?.monthly, locations.value, staff.value); return monthly?.total_minor ? (saving.value / (monthly.total_minor * 12) * 100).toFixed(1) : ''; });
const money = value => billingMoney(value, estimate.value?.currency || market.value?.currency || 'USD');
watch(() => [props.current?.market, props.current?.billing_interval, props.current?.locations, props.current?.staff].join('|'), () => { const value = props.current; if (value) { marketCode.value = value.market; interval.value = value.billing_interval; locations.value = value.locations; staff.value = value.staff; } });
</script>

<template>
    <div class="capacity-calculator">
        <label v-if="newerRates" class="capacity-migration"><input v-model="useCurrentRates" type="checkbox" :disabled="disabled"/> Review a switch to current pricing at renewal. Your existing rates stay in place until you confirm.</label>
        <div class="capacity-inputs">
            <label v-if="markets.length > 1">Billing currency<AppSelect v-model="marketCode" class="cd-input" :disabled="Boolean(current)"><option v-for="item in markets" :key="item.code" :value="item.code">{{ item.currency }}</option></AppSelect></label>
            <fieldset class="capacity-cadence"><legend>Billing cycle</legend><div class="cd-segmented"><button type="button" :aria-pressed="interval === 'monthly'" :class="{ selected: interval === 'monthly' }" :disabled="disabled || !market?.intervals?.monthly" @click="interval = 'monthly'">Monthly</button><button type="button" :aria-pressed="interval === 'annual'" :class="{ selected: interval === 'annual' }" :disabled="disabled || !market?.intervals?.annual" @click="interval = 'annual'">Yearly <span v-if="saving">Save {{ savingPercent }}%</span></button></div></fieldset>
            <label>Locations<input v-model="locations" class="cd-input" :disabled="disabled" type="number" inputmode="numeric" min="1" max="1000" step="1"><small>Your active stores or branches.</small></label>
            <label>Bookable staff<input v-model="staff" class="cd-input" :disabled="disabled" type="number" inputmode="numeric" min="1" max="10000" step="1"><small>People taking appointments, counted once across locations.</small></label>
        </div>
        <div v-if="estimate" class="capacity-total" aria-live="polite" aria-atomic="true">
            <span class="billing-eyebrow">{{ estimate.ready ? 'Your subscription' : 'Your subscription estimate' }}</span>
            <p class="capacity-amount">{{ money(estimate.total_minor) }}<span>/ {{ interval === 'annual' ? 'year' : 'month' }}</span></p>
            <p>{{ estimate.currency }} · {{ interval === 'annual' ? 'Billed yearly, upfront' : 'Billed monthly' }} · before tax</p>
            <dl><div><dt>1 location + 1 bookable staff</dt><dd>{{ money(estimate.base_minor) }}</dd></div><div v-if="estimate.locations > 1"><dt>{{ estimate.locations - 1 }} extra locations × {{ money(estimate.location_minor) }}</dt><dd>{{ money((estimate.locations - 1) * estimate.location_minor) }}</dd></div><div v-for="line in estimate.staff_lines" :key="line.from"><dt>Staff {{ line.from }}{{ line.to > line.from ? `–${line.to}` : '' }} · {{ line.quantity }} × {{ money(line.unit_minor) }}</dt><dd>{{ money(line.total_minor) }}</dd></div></dl>
            <p v-if="interval === 'annual'">Equivalent to {{ money(capacityMonthlyEquivalent(estimate.total_minor)) }} / month. The full yearly amount is charged upfront.</p>
            <p v-if="interval === 'annual' && saving">Save {{ money(saving) }} a year compared with monthly billing.</p>
            <p class="capacity-included"><CheckCircleIcon aria-hidden="true"/><span>{{ estimate.sms_monthly_allowance.toLocaleString() }} text credits each month<br/>Email notifications included</span></p>
            <AppButton :disabled="disabled || reviewDisabled || !estimate.ready" @click="emit('review', estimate)">{{ actionLabel }}</AppButton>
            <AppNotice v-if="!estimate.ready && catalog?.available" title="Checkout for this billing cycle is not ready yet" description="You can still compare your options." />
        </div>
        <AppNotice v-else :title="market ? 'Check your selection' : 'Subscriptions are temporarily unavailable'" :description="market ? 'Enter whole numbers: at least one location and one bookable staff member.' : 'Please contact support for help with your subscription.'" />
        <p class="capacity-footnote">Receptionists and administrators who do not take appointments are included. Text usage varies by message length and destination.</p>
    </div>
</template>
