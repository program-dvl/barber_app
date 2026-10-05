<script setup>
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import StatePanel from '@/Components/Product/StatePanel.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    businessLabel: String,
    module: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout :title="module.label" :business-label="businessLabel">
        <PageHeader :title="module.label" />
        <div class="mt-6">
            <StatePanel
                tone="info"
                title="This area is coming later"
                description="This feature is not available yet."
            >
                <template #actions><AppButton :href="route('business.dashboard', $page.props.tenant.public_id)" variant="secondary">Return to dashboard</AppButton></template>
            </StatePanel>
        </div>
    </AppLayout>
</template>
