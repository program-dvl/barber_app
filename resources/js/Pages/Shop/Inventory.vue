<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';

defineProps({ products: Object, freshAt: String, timeZone: String, locations: Array });
const page = usePage();
const money = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format((value || 0) / 100);
</script>

<template>
    <AppLayout title="Inventory" :business-label="page.props.tenant.name">
        <Head title="Inventory" />
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--border-subtle)] pb-5">
            <div><p class="text-sm font-semibold text-[var(--brand-pine)]">FR-16 · Append-only stock</p><h1 class="gh-display mt-1 text-4xl text-[var(--text-strong)]">Inventory</h1><p class="mt-2 text-sm text-[var(--text-muted)]">Fresh {{ new Date(freshAt).toLocaleString() }} · {{ timeZone }}</p></div>
            <a :href="route('business.inventory.export', page.props.tenant.public_id)" class="inline-flex min-h-11 items-center rounded-lg border border-[var(--border-strong)] px-4 text-sm font-semibold">Export product CSV</a>
        </header>
        <StatePanel v-if="!products.data.length" class="mt-6" title="No retail products yet" description="Import the version 1 CSV template or add the first product, then receive stock with a location and reason." />
        <div v-else class="mt-6 overflow-x-auto rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)]">
            <table class="min-w-full text-left text-sm"><thead class="bg-[var(--surface-subtle)] text-xs uppercase tracking-wide text-[var(--text-muted)]"><tr><th class="p-3">Product</th><th class="p-3">SKU / barcode</th><th class="p-3">Price / cost</th><th class="p-3">Stock</th><th class="p-3">History</th></tr></thead><tbody class="divide-y divide-[var(--border-subtle)]"><tr v-for="product in products.data" :key="product.public_id"><td class="p-3"><p class="font-semibold text-[var(--text-strong)]">{{ product.name }}</p><p class="text-xs text-[var(--text-muted)]">{{ product.category || 'Uncategorised' }} · {{ product.status }}</p></td><td class="p-3">{{ product.sku }}<span v-if="product.barcode" class="block text-xs text-[var(--text-muted)]">{{ product.barcode }}</span></td><td class="p-3">{{ money(product.sale_price_minor) }}<span class="block text-xs text-[var(--text-muted)]">Cost {{ money(product.cost_minor) }}</span></td><td class="p-3"><span :class="['gh-status', product.low_stock ? 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]' : 'bg-[var(--status-success-soft)] text-[var(--status-success)]']">{{ product.current_stock }} on hand</span><span class="mt-1 block text-xs text-[var(--text-muted)]">Low at {{ product.low_stock_threshold }}</span></td><td class="p-3">{{ product.movement_count }} movements</td></tr></tbody></table>
        </div>
    </AppLayout>
</template>
