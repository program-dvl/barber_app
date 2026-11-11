<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppNotice from '@/Components/Product/AppNotice.vue';
import { acquisitionConsent, acquisitionLabels, ACQUISITION_CONSENT_KEY, ACQUISITION_PREFERENCES_EVENT } from '@/Support/marketplaceAttribution';
const page = usePage();
const opened = ref(false), saved = ref(false), blocked = ref(false), failed = ref(false);
const available = computed(() => !!page.props.acquisitionPublic);
let alive = true, stopFinish, last = '';
function read() {
    blocked.value = window.navigator.globalPrivacyControl === true || ['1','yes'].includes(String(window.navigator.doNotTrack ?? window.doNotTrack ?? '').toLowerCase());
    try { const record = JSON.parse(localStorage.getItem(ACQUISITION_CONSENT_KEY)); saved.value = record?.version === 'mpg-consent-v1' && record.expires_at > Date.now(); } catch { saved.value = false; }
    if (!saved.value && available.value && !blocked.value) opened.value = true;
}
async function observe() {
    if (!available.value || !acquisitionConsent() || !alive) return;
    const publicPage = page.props.acquisitionPublic;
    const key = publicPage.page;
    if (last === key) return;
    last = key; // Failure stops automatic retry; booking is independent.
    const labels = acquisitionLabels({referrer:document.referrer, url:window.location.href, kind:publicPage.kind});
    try { await axios.post('/marketplace/acquisition/observe', {consent:true,version:'mpg-consent-v1',page:key,...labels}); } catch { /* No source evidence is invented when storage fails. */ }
}
async function choose(choice) {
    failed.value = false;
    if (choice === 'accepted' && blocked.value) return;
    try {
        const record = JSON.stringify({version:'mpg-consent-v1',choice,expires_at:Date.now()+30*86400000});
        localStorage.setItem(ACQUISITION_CONSENT_KEY, record);
        if (localStorage.getItem(ACQUISITION_CONSENT_KEY) !== record) throw Error();
        saved.value = true; opened.value = false;
        if (choice === 'accepted' && acquisitionConsent()) { last = ''; await observe(); }
        else { await axios.delete('/marketplace/acquisition'); }
    } catch { failed.value = true; opened.value = true; }
}
const openPreferences = () => { opened.value = true; };
watch(() => page.url, () => { if (typeof window !== 'undefined') { read(); observe(); } });
onMounted(() => { window.addEventListener(ACQUISITION_PREFERENCES_EVENT, openPreferences); read(); observe(); stopFinish = router.on('success', () => {read(); observe();}); });
onBeforeUnmount(() => {window.removeEventListener(ACQUISITION_PREFERENCES_EVENT, openPreferences);alive=false;stopFinish?.();});
</script>
<template>
    <aside v-if="available && opened" class="acquisition-panel" aria-label="Discovery analytics choice"><AppNotice title="Your cookie choices"><p v-if="blocked">Your browser asks us to keep analytics off. You can keep browsing and booking.</p><p v-else-if="failed">We couldn’t save your choice. You can keep browsing and booking. Discovery measurement stays off unless your browser has saved permission.</p><p v-else>We use essential cookies for sign-in and bookings. With your permission, optional cookies and similar storage help us understand which public pages lead to appointments. We remember this discovery source for 30 days, without saving your search phrases or sharing your contact details.</p><p><a href="/privacy-policy#cookies-and-website-measurement">Read the privacy policy</a>. Change your choice using Cookie settings.</p><template #actions><template v-if="!blocked"><button type="button" class="mp-button secondary" @click="choose('accepted')">Accept optional cookies</button><button type="button" class="mp-button secondary" @click="choose('rejected')">Reject optional cookies</button></template><button type="button" class="mp-button quiet" @click="opened=false">Close</button></template></AppNotice></aside>
</template>
<style scoped>.acquisition-panel{position:fixed;bottom:16px;right:16px;left:16px;max-width:680px;margin-left:auto;z-index:60;max-height:calc(100dvh - 32px);overflow:auto;box-shadow:0 12px 44px #17255424;border-radius:14px}.acquisition-panel button{min-height:44px}.acquisition-panel p+p{margin-top:10px}@media(max-width:480px){.acquisition-panel{bottom:10px;right:10px;left:10px}}</style>
