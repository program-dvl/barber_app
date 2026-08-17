<script setup>
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';

defineProps({ module: { type: Object, required: true } });
</script>

<template>
    <PlatformAdminLayout :title="module.label">
        <PageHeader eyebrow="Internal operations" :title="module.label" :description="module.description" />
        <div class="mt-6">
            <StatePanel tone="info" title="This operation is not available yet" description="The destination is reserved, but no business data or administrative action is exposed until the workflow and its safety controls are implemented and verified.">
                <template #actions><AppButton :href="route('platform.overview')" variant="secondary">Return to overview</AppButton></template>
            </StatePanel>
        </div>
    </PlatformAdminLayout>
</template>
