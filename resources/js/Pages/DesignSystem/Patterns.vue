<script setup>
import { ref } from 'vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import DataTable from '@/Components/Product/DataTable.vue';
import FormField from '@/Components/Product/FormField.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import SkeletonBlock from '@/Components/Product/SkeletonBlock.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import ToastRegion from '@/Components/Product/ToastRegion.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const destructiveDialog = ref(null);
const messages = ref([]);

const showSuccess = () => {
    messages.value = [{ id: Date.now(), title: 'Pattern confirmed', description: 'The toast is announced without moving keyboard focus.', tone: 'success' }];
};

const dismiss = id => {
    messages.value = messages.value.filter(message => message.id !== id);
};
</script>

<template>
    <AppLayout title="Interface patterns">
        <ToastRegion :messages="messages" @dismiss="dismiss" />
        <PageHeader eyebrow="Foundation preview" title="Interface patterns" description="Implemented product primitives for consistent actions, fields, structured data, feedback, waiting, and confirmation. These examples are interface evidence, not salon records or workflows." />

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <SurfaceCard title="Actions" description="Use one primary action per decision area.">
                <div class="flex flex-wrap gap-2">
                    <AppButton @click="showSuccess">Primary action</AppButton>
                    <AppButton variant="secondary">Secondary action</AppButton>
                    <AppButton variant="quiet">Quiet action</AppButton>
                    <AppButton variant="danger" @click="destructiveDialog.open()">Destructive action</AppButton>
                </div>
            </SurfaceCard>

            <SurfaceCard title="Form field" description="Visible label, hint, required text, and linked error.">
                <FormField id="pattern-name" label="Pattern name" hint="Use a concise, task-specific label." error="Enter a pattern name." required v-slot="{ describedby }">
                    <input id="pattern-name" class="ds-control w-full px-3" aria-invalid="true" :aria-describedby="describedby" />
                </FormField>
            </SurfaceCard>

            <SurfaceCard title="Structured data" description="Tables keep a caption and a labelled scroll boundary." :padding="false">
                <DataTable caption="Implemented component behavior">
                    <thead><tr><th class="border-b border-[var(--border-subtle)] px-5 py-3 font-semibold text-[var(--text-strong)]">Pattern</th><th class="border-b border-[var(--border-subtle)] px-5 py-3 font-semibold text-[var(--text-strong)]">Required behavior</th></tr></thead>
                    <tbody><tr><td class="px-5 py-3 font-medium">Data table</td><td class="px-5 py-3 text-[var(--text-muted)]">Caption, headers, and keyboard scrolling</td></tr><tr><td class="border-t border-[var(--border-subtle)] px-5 py-3 font-medium">Dialog</td><td class="border-t border-[var(--border-subtle)] px-5 py-3 text-[var(--text-muted)]">Label, safe initial focus, Escape, and explicit consequence</td></tr></tbody>
                </DataTable>
            </SurfaceCard>

            <SurfaceCard title="Loading" description="Text status plus reduced-motion-safe skeleton.">
                <SkeletonBlock label="Loading pattern preview" />
            </SurfaceCard>
        </div>

        <SurfaceCard class="mt-6" title="Product states" description="Colour is always paired with an icon and plain-language title.">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatePanel compact title="No results" description="Explain what is empty and how to continue." />
                <StatePanel compact tone="loading" title="Loading" description="Keep the previous context where possible." />
                <StatePanel compact tone="error" title="Could not load" description="State what failed and offer recovery." />
                <StatePanel compact tone="success" title="Saved" description="Confirm the durable result." />
            </div>
        </SurfaceCard>

        <AppDialog ref="destructiveDialog" id="pattern-destructive-dialog" title="Remove this example?" description="This preview demonstrates consequence-first destructive confirmation. No product data will be changed." confirm-label="Remove example" destructive @confirm="showSuccess" />
    </AppLayout>
</template>
