<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { BanknotesIcon, BuildingLibraryIcon, CheckCircleIcon, DevicePhoneMobileIcon, ReceiptPercentIcon, WalletIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ appointments: { type: Array, default: () => [] }, sales: { type: Array, default: () => [] }, selectedAppointment: String });
const page = usePage();
const selected = ref(props.appointments.some(item => item.public_id === props.selectedAppointment) ? props.selectedAppointment : null);
const currentSale = ref(null); const method = ref('cash'); const working = ref(false); const error = ref(''); const completedSale = ref(null);
const formatMoney = (amount, currency = page.props.tenant?.regional?.currency_code || 'INR') => new Intl.NumberFormat(page.props.tenant?.regional?.locale || undefined, { style: 'currency', currency }).format((amount || 0) / 100);
const selectedAppointment = computed(() => props.appointments.find(appointment => appointment.public_id === selected.value));
const methods = [
    { id: 'cash', label: 'Cash', icon: BanknotesIcon },
    { id: 'upi', label: 'UPI received', icon: DevicePhoneMobileIcon },
    { id: 'bank_transfer', label: 'Bank transfer', icon: BuildingLibraryIcon },
    { id: 'custom', label: 'Other', icon: WalletIcon },
];
const choose = appointment => { selected.value = appointment.public_id; currentSale.value = appointment.sale || null; completedSale.value = null; error.value = ''; };
const prepare = async () => {
    if (!selectedAppointment.value) return; working.value = true; error.value = '';
    try {
        const { data } = await axios.post(route('business.checkout.open', [page.props.tenant.public_id, selectedAppointment.value.public_id]), { lines: [] });
        currentSale.value = data.sale;
    } catch (exception) { error.value = exception.response?.data?.message || 'Checkout could not be prepared. Please try again.'; }
    finally { working.value = false; }
};
const recordPayment = async () => {
    if (!currentSale.value?.balance_minor) return; working.value = true; error.value = '';
    try {
        const { data } = await axios.post(route('business.checkout.tender', [page.props.tenant.public_id, currentSale.value.public_id]), {
            method: method.value, amount_minor: currentSale.value.balance_minor,
            idempotency_key: `manual-payment-${currentSale.value.public_id}-${crypto.randomUUID()}`,
            evidence: { recorded_in: 'front_desk', manually_confirmed: true },
        });
        completedSale.value = data.sale; currentSale.value = data.sale;
        router.reload({ only: ['appointments', 'sales'], preserveScroll: true });
    } catch (exception) { error.value = exception.response?.data?.message || 'Payment could not be recorded. Nothing was changed.'; }
    finally { working.value = false; }
};
</script>

<template>
    <AppLayout title="Checkout & sales" :business-label="page.props.tenant.name">
        <PageHeader eyebrow="Front desk · Manual payment" title="Checkout & sales" description="Settle completed visits, record payments received and review recent sales." />
        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(22rem,.95fr)]">
            <SurfaceCard :padding="false" title="Ready to checkout" :description="`${appointments.length} ${appointments.length === 1 ? 'visit' : 'visits'} waiting`">
                <StatePanel v-if="!appointments.length" tone="success" title="Everything is settled" description="Completed visits that still need payment will appear here." />
                <div v-else class="divide-y divide-[var(--border-subtle)]"><button v-for="appointment in appointments" :key="appointment.public_id" type="button" :class="['flex min-h-20 w-full items-center gap-4 px-5 py-4 text-left transition hover:bg-[var(--surface-subtle)]', selected === appointment.public_id ? 'bg-[var(--action-secondary-hover)] ring-1 ring-inset ring-[var(--action-primary)]' : '']" @click="choose(appointment)"><span class="grid size-11 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] font-bold text-white">{{ appointment.client?.slice(0, 1) || '?' }}</span><span class="min-w-0 flex-1"><strong class="block truncate text-[var(--text-strong)]">{{ appointment.client || 'Walk-in client' }}</strong><span class="block text-sm capitalize text-[var(--text-muted)]">{{ appointment.reference }} · {{ appointment.status.replaceAll('_', ' ') }}</span></span><span class="font-bold tabular-nums">{{ formatMoney(appointment.sale?.balance_minor ?? appointment.price_minor, appointment.currency_code) }}</span></button></div>
            </SurfaceCard>

            <SurfaceCard title="Payment received" description="Record only money you have actually received outside ClipperDesk.">
                <template v-if="completedSale"><div class="text-center"><span class="mx-auto grid size-14 place-items-center rounded-full bg-[var(--status-success-soft)] text-[var(--status-success)]"><CheckCircleIcon class="size-8" /></span><h2 class="mt-4 text-xl font-bold text-[var(--text-strong)]">Payment recorded</h2><p class="mt-2 text-sm text-[var(--text-muted)]">The sale is complete and now feeds payment, revenue, staff and location reporting.</p><AppButton class="mt-5 w-full" :href="route('business.checkout.receipt', [page.props.tenant.public_id, completedSale.public_id])" variant="secondary"><ReceiptPercentIcon class="size-5" />View receipt</AppButton></div></template>
                <template v-else-if="selectedAppointment"><div class="rounded-2xl bg-[var(--surface-subtle)] p-4"><p class="text-sm text-[var(--text-muted)]">{{ selectedAppointment.client }}</p><div class="mt-2 flex items-end justify-between gap-3"><span class="font-semibold">Amount due</span><strong class="text-2xl tabular-nums text-[var(--text-strong)]">{{ formatMoney(currentSale?.balance_minor ?? selectedAppointment.price_minor, selectedAppointment.currency_code) }}</strong></div></div><p v-if="error" class="mt-4 rounded-lg bg-[var(--status-danger-soft)] p-3 text-sm text-[var(--status-danger)]" role="alert">{{ error }}</p><AppButton v-if="!currentSale" class="mt-4 w-full" :disabled="working" @click="prepare">{{ working ? 'Preparing…' : 'Review payment' }}</AppButton><template v-else><fieldset class="mt-5"><legend class="text-sm font-semibold text-[var(--text-strong)]">How was it paid?</legend><div class="mt-2 grid grid-cols-2 gap-2"><label v-for="item in methods" :key="item.id" :class="['flex min-h-20 cursor-pointer flex-col justify-center rounded-xl border p-3', method === item.id ? 'border-[var(--action-primary)] bg-[var(--action-secondary-hover)] text-[var(--action-primary)]' : 'border-[var(--border-subtle)]']"><input v-model="method" class="sr-only" type="radio" :value="item.id" /><component :is="item.icon" class="size-5" /><span class="mt-1 text-sm font-semibold">{{ item.label }}</span></label></div></fieldset><div class="mt-5 rounded-xl border border-[var(--status-warning)]/30 bg-[var(--status-warning-soft)] p-3 text-xs leading-5 text-[var(--text-default)]"><strong>Manual confirmation</strong><br>No card or online payment is processed here. Confirm only after you have received the full amount.</div><AppButton class="mt-4 w-full" :disabled="working" @click="recordPayment">{{ working ? 'Recording…' : `Mark ${formatMoney(currentSale.balance_minor, selectedAppointment.currency_code)} received` }}</AppButton></template></template>
                <template v-else><div class="py-8 text-center"><span class="mx-auto grid size-12 place-items-center rounded-full bg-[var(--surface-subtle)] text-[var(--action-primary)]"><BanknotesIcon class="size-6" /></span><h2 class="mt-4 font-semibold text-[var(--text-strong)]">Choose a visit</h2><p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">The service total, client history, receipt and reports will stay connected automatically.</p></div></template>
            </SurfaceCard>
        </div>

        <SurfaceCard class="mt-6" title="Recent sales" description="Completed and outstanding records remain traceable."><StatePanel v-if="!sales.length" compact title="No sales yet" description="Sales will appear here as completed visits move through checkout." /><div v-else class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="text-xs uppercase tracking-wide text-[var(--text-muted)]"><tr><th class="pb-3">Client</th><th class="pb-3">Reference</th><th class="pb-3">Status</th><th class="pb-3 text-right">Total</th><th class="pb-3 text-right">Outstanding</th></tr></thead><tbody class="divide-y divide-[var(--border-subtle)]"><tr v-for="sale in sales" :key="sale.public_id"><td class="py-3 font-semibold">{{ sale.client || 'Client' }}</td><td class="py-3 text-[var(--text-muted)]">{{ sale.reference }}</td><td class="py-3 capitalize">{{ sale.status }}</td><td class="py-3 text-right tabular-nums">{{ formatMoney(sale.total_minor, sale.currency_code) }}</td><td class="py-3 text-right tabular-nums">{{ formatMoney(sale.balance_minor, sale.currency_code) }}</td></tr></tbody></table></div></SurfaceCard>
    </AppLayout>
</template>
