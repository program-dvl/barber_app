<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/Components/Product/AppButton.vue';
import ProductMark from '@/Components/Product/ProductMark.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';

const props = defineProps({
    businessName: { type: String, required: true },
    expiresAt: { type: String, required: true },
    acceptUrl: { type: String, required: true },
});
const accepting = ref(false);
const accept = () => {
    accepting.value = true;
    router.post(props.acceptUrl, {}, { onFinish: () => { accepting.value = false; } });
};
</script>

<template>
    <div class="min-h-screen bg-[var(--surface-canvas)] px-4 py-10 text-[var(--text-default)] sm:py-16">
        <Head title="Staff invitation" />
        <main class="mx-auto max-w-xl">
            <div class="mb-8 flex justify-center"><ProductMark large /></div>
            <SurfaceCard title="Join this workspace" :description="`${businessName} invited you to work with them in Good Hours.`">
                <p class="text-sm leading-6 text-[var(--text-muted)]">
                    Your access will use this signed-in email identity and the role and locations chosen by the business. This invitation expires {{ new Date(expiresAt).toLocaleString() }}.
                </p>
                <div class="mt-6">
                    <AppButton type="button" :disabled="accepting" @click="accept">
                        {{ accepting ? 'Joining…' : `Join ${businessName}` }}
                    </AppButton>
                </div>
            </SurfaceCard>
        </main>
    </div>
</template>
