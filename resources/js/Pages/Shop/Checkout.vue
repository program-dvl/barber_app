<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ appointments: { type: Array, default: () => [] }, sales: { type: Array, default: () => [] } })
const page = usePage()
const selected = ref(null)
const formatMoney = (amount, currency = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format((amount || 0) / 100)
const selectedAppointment = computed(() => props.appointments.find((appointment) => appointment.public_id === selected.value))
</script>

<template>
  <AppLayout title="Checkout & sales" :business-label="page.props.tenant.name">
  <div class="mx-auto max-w-6xl space-y-6">
    <header class="flex flex-col gap-3 border-b border-base-300 pb-5 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[.18em] text-primary">Front desk</p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight text-base-content">Checkout</h1>
        <p class="mt-2 max-w-2xl text-sm text-base-content/70">Start from the appointment, confirm the service value, then take one or more payments. Every receipt is retained as a faithful financial record.</p>
      </div>
      <div class="rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success"><span class="font-semibold">Secure payment workflow</span><br>Amounts are verified server-side before collection.</div>
    </header>
    <div class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
      <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
        <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Ready to checkout</h2><span class="text-sm text-base-content/60">{{ appointments.length }} appointments</span></div>
        <div class="mt-4 divide-y divide-base-300">
          <button v-for="appointment in appointments" :key="appointment.public_id" type="button" class="flex w-full items-center gap-4 px-1 py-4 text-left transition hover:bg-base-200/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" :class="selected === appointment.public_id ? 'bg-primary/5' : ''" @click="selected = appointment.public_id">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/10 font-semibold text-primary">{{ appointment.client?.slice(0, 1) || '?' }}</span>
            <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ appointment.client || 'Walk-in client' }}</span><span class="block text-sm text-base-content/60">{{ appointment.reference }} · {{ appointment.status.replaceAll('_', ' ') }}</span></span>
            <span class="font-semibold tabular-nums">{{ formatMoney(appointment.price_minor, appointment.currency_code) }}</span>
          </button>
          <p v-if="!appointments.length" class="py-10 text-center text-sm text-base-content/60">No appointments are ready for checkout.</p>
        </div>
      </section>
      <aside class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm" aria-live="polite">
        <template v-if="selectedAppointment"><p class="text-sm font-medium text-base-content/60">Selected appointment</p><h2 class="mt-1 text-xl font-semibold">{{ selectedAppointment.client || 'Walk-in client' }}</h2><dl class="mt-5 space-y-3 text-sm"><div class="flex justify-between"><dt>Service total</dt><dd class="font-medium">{{ formatMoney(selectedAppointment.price_minor, selectedAppointment.currency_code) }}</dd></div><div class="flex justify-between"><dt>Deposit</dt><dd>Applied at payment step</dd></div><div class="border-t border-base-300 pt-3 text-base font-semibold"><dt class="inline">Amount due</dt><dd class="float-right">{{ formatMoney(selectedAppointment.price_minor, selectedAppointment.currency_code) }}</dd></div></dl><p class="mt-5 rounded-lg bg-base-200 p-3 text-xs leading-5 text-base-content/70">The payment screen will present split tender, tips, discount approval, and receipt delivery only after the appointment-derived basket is created.</p></template>
        <template v-else><h2 class="text-lg font-semibold">Choose an appointment</h2><p class="mt-2 text-sm leading-6 text-base-content/65">This prevents an unlinked sale and makes client history, deposits, receipts, and reconciliation line up automatically.</p></template>
      </aside>
    </div>
    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Recent sales</h2><span class="text-sm text-base-content/60">Receipts stay reproducible</span></div><div class="mt-4 overflow-x-auto"><table class="table"><thead><tr><th>Sale</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Outstanding</th></tr></thead><tbody><tr v-for="sale in sales" :key="sale.public_id"><td class="font-mono text-xs">{{ sale.public_id }}</td><td class="capitalize">{{ sale.status }}</td><td class="text-right tabular-nums">{{ formatMoney(sale.total_minor, sale.currency_code) }}</td><td class="text-right tabular-nums">{{ formatMoney(sale.balance_minor, sale.currency_code) }}</td></tr><tr v-if="!sales.length"><td colspan="4" class="py-7 text-center text-sm text-base-content/60">Completed and open sales will appear here.</td></tr></tbody></table></div></section>
  </div>
  </AppLayout>
</template>
