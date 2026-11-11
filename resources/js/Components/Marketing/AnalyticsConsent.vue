<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import { getMarketingAnalytics } from '@/Support/googleAnalytics';

const state = ref({ available: false, visible: false });
const panel = ref(null);
let unsubscribe;
onMounted(() => { unsubscribe = getMarketingAnalytics()?.subscribe(value => {
    const focusPanel = value.preferencesOpen && !state.value.preferencesOpen;
    state.value = value;
    if (focusPanel) nextTick(() => panel.value?.querySelector('button')?.focus({ preventScroll: true }));
}); });
onUnmounted(() => unsubscribe?.());
const choose = choice => getMarketingAnalytics()?.choose(choice);
</script>

<template>
    <aside v-if="state.visible" ref="panel" class="cd-analytics-consent" aria-label="Website analytics choices">
        <AppNotice title="Your cookie choices">
            <p v-if="state.privacySignal">Your browser asks us to keep analytics off. We respect that choice.</p>
            <p v-else-if="state.storageError">Your browser couldn't save this choice. Analytics is off. You can keep browsing.</p>
            <p v-else>We use essential cookies for sign-in and account security. With your permission, optional cookies from Google Analytics helps us understand visits and trial buttons on our public website. It stays off until you accept. Your bookings and workspace are excluded.</p>
            <p><a href="/privacy-policy#cookies-and-website-measurement" class="underline underline-offset-4">Read our privacy policy</a>. Change your choice anytime using Cookie settings in the footer.</p>
            <template #actions>
                <template v-if="!state.privacySignal && !state.storageError">
                    <button type="button" class="cd-button cd-button-secondary" @click="choose('accepted')">Accept optional cookies</button>
                    <button type="button" class="cd-button cd-button-secondary" @click="choose('rejected')">Reject optional cookies</button>
                </template>
                <button v-if="state.choice || state.storageError" type="button" class="cd-button cd-button-quiet" @click="getMarketingAnalytics()?.closePreferences()">Close</button>
            </template>
        </AppNotice>
    </aside>
</template>

<style scoped>
.cd-analytics-consent { position:fixed; z-index:60; bottom:16px; left:16px; right:16px; max-width:680px; margin-left:auto; box-shadow:0 12px 44px rgb(15 23 42 / 14%); border-radius:12px; max-height:calc(100dvh - 32px); overflow:auto; }
.cd-analytics-consent button { min-height:44px; }
@media(max-width:480px) { .cd-analytics-consent { bottom:10px; left:10px; right:10px; max-height:calc(100dvh - 20px); } }
@media print { .cd-analytics-consent { display:none; } }
</style>
