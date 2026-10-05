<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';

defineProps({ products: Object, freshAt: String, timeZone: String, locations: Array });
const page = usePage();
const money = (value, currency) => new Intl.NumberFormat(page.props.tenant?.regional?.locale || undefined, { style: 'currency', currency: currency || page.props.tenant?.regional?.currency_code || 'INR' }).format((value || 0) / 100);
</script>

<template>
    <AppLayout title="Inventory" :business-label="page.props.tenant.name">
        <Head title="Inventory" />
        <PageHeader title="Inventory"><template #actions><AppButton :href="route('business.inventory.export', page.props.tenant.public_id)" variant="secondary">Export products</AppButton></template></PageHeader>
        <p class="cd-page-context mt-3">Updated {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short', timeZone }).format(new Date(freshAt)) }} · {{ timeZone }}</p>
        <StatePanel v-if="!products.data.length" class="mt-6" title="No retail products yet" description="Products will appear here when they have been added to this business." />
        <div v-else class="mt-6 overflow-x-auto rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)]">
            <table class="min-w-full text-left text-sm"><thead class="bg-[var(--surface-subtle)] text-xs font-semibold text-[var(--text-muted)]"><tr><th class="p-3">Product</th><th class="p-3">SKU / barcode</th><th class="p-3">Price / cost</th><th class="p-3">Stock</th><th class="p-3">History</th></tr></thead><tbody class="divide-y divide-[var(--border-subtle)]"><tr v-for="product in products.data" :key="product.public_id"><td class="p-3"><p class="font-semibold text-[var(--text-strong)]">{{ product.name }}</p><p class="text-xs text-[var(--text-muted)]">{{ product.category || 'Uncategorised' }} · {{ product.status }}</p></td><td class="p-3">{{ product.sku }}<span v-if="product.barcode" class="block text-xs text-[var(--text-muted)]">{{ product.barcode }}</span></td><td class="p-3">{{ money(product.sale_price_minor, product.currency_code) }}<span class="block text-xs text-[var(--text-muted)]">Cost {{ money(product.cost_minor, product.currency_code) }}</span></td><td class="p-3"><span :class="['cd-status', product.low_stock ? 'bg-[var(--status-warning-soft)] text-[var(--status-warning)]' : 'bg-[var(--status-success-soft)] text-[var(--status-success)]']">{{ product.current_stock }} on hand</span><span class="mt-1 block text-xs text-[var(--text-muted)]">Low-stock threshold: {{ product.low_stock_threshold }}</span></td><td class="p-3">{{ product.movement_count }} movements</td></tr></tbody></table>
        </div>
    </AppLayout>
</template>
