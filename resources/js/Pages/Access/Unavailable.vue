<script setup>
import { ExclamationTriangleIcon, LockClosedIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline';
import AppButton from '@/Components/Product/AppButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';

const props = defineProps({ businessLabel: String, state: Object });
const icon = props.state.code === 'setup_required'
    ? WrenchScrewdriverIcon
    : props.state.code === 'upgrade_required'
        ? LockClosedIcon
        : ExclamationTriangleIcon;
</script>

<template>
    <AppLayout title="Access information" :business-label="businessLabel">
        <div class="mx-auto flex py-6 max-w-3xl items-center">
            <SurfaceCard class="w-full text-center">
                <span class="mx-auto grid size-10 place-items-center rounded-2xl bg-[var(--surface-subtle)] text-[var(--action-primary)]">
                    <component :is="icon" class="size-5" aria-hidden="true" />
                </span>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-[var(--text-strong)]">{{ state.title }}</h1>
                <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-[var(--text-muted)]">{{ state.description }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    <AppButton v-if="state.action" :href="state.action.url">{{ state.action.label }}</AppButton>
                    <AppButton :href="route('business.dashboard', $page.props.tenant.public_id)" variant="secondary">Return to dashboard</AppButton>
                </div>
                <p v-if="state.code === 'permission_denied'" class="mt-5 text-xs text-[var(--text-muted)]">Contact your business owner if you believe your role should include this area.</p>
            </SurfaceCard>
        </div>
    </AppLayout>
</template>
