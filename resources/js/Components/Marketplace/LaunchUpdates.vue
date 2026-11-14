<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useForm, usePage, useRemember } from '@inertiajs/vue3';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import AppNotice from '@/Components/Product/AppNotice.vue';

const props = defineProps({ city: { type: String, default: '' } });
const page = usePage();
const form = useForm('marketplaceLaunchSignup', { area: props.city, email: '', consent: false });
const receipt = useRemember(reactive({ state: '', retryAt: 0, reference: null }), 'marketplaceLaunchReceipt');
const editing = ref(false);
const now = ref(Date.now());
const outcome = ref(null);
let timer;
const remaining = computed(() => Math.max(0, Math.ceil((receipt.retryAt - now.value) / 1000)));
const countdown = computed(() => `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`);
const subscribed = computed(() => receipt.state === 'subscribed' && !editing.value);
const unavailable = computed(() => receipt.state === 'unavailable');
watch(() => props.city, (city, previous) => { if (!form.area || form.area === previous) form.area = city; });
watch(() => page.props.flash?.launchUpdate, value => {
    if (!value) return;
    Object.assign(receipt, value);
    editing.value = false;
    now.value = Date.now();
}, { immediate: true });
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => clearInterval(timer));
const submit = () => {
    if (form.processing || remaining.value > 0) return;
    form.post('/marketplace/launch-updates', {
        preserveScroll: true,
        onSuccess: response => {
            const result = response.props.flash?.launchUpdate;
            if (result) { Object.assign(receipt, result); editing.value = false; now.value = Date.now(); }
            nextTick(() => outcome.value?.focus({preventScroll:true}));
        },
        onError: () => { nextTick(() => document.querySelector('#area-updates [aria-invalid="true"]')?.focus({preventScroll:true})); },
    });
};
const changeDetails = () => { editing.value = true; form.area = ''; form.consent = false; form.clearErrors(); nextTick(() => document.getElementById('launch-area')?.focus({preventScroll:true})); };
</script>
<template>
<section id="area-updates" class="launch-updates" aria-labelledby="area-updates-title">
    <div class="updates-copy"><p class="updates-kicker">SOMETHING GOOD, CLOSER TO YOU</p><h2 id="area-updates-title">Stay in the loop.</h2><p>Be first to hear when businesses join your area.</p></div>
    <div class="updates-content">
        <div v-if="subscribed" ref="outcome" tabindex="-1" class="updates-confirmation">
            <AppNotice tone="success" title="You’re on the list" :description="`We’ll email ${form.email} when businesses join ${form.area}.`"><template #actions><button type="button" class="updates-quiet" @click="changeDetails">Add another area</button></template></AppNotice>
        </div>
        <form v-else @submit.prevent="submit" :aria-busy="form.processing">
            <div class="updates-fields">
                <div><label for="launch-area">Your city</label><input id="launch-area" v-model="form.area" name="area" placeholder="e.g. Boston, MA, USA" required minlength="2" maxlength="150" autocomplete="address-level2" :aria-invalid="!!form.errors.area" :aria-describedby="form.errors.area ? 'launch-area-error' : undefined" /><span v-if="form.errors.area" id="launch-area-error" class="updates-error">{{ form.errors.area }}</span></div>
                <div><label for="launch-email">Email address</label><input id="launch-email" v-model="form.email" name="email" type="email" placeholder="you@example.com" required maxlength="254" autocomplete="email" :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'launch-email-error' : undefined" /><span v-if="form.errors.email" id="launch-email-error" class="updates-error">{{ form.errors.email }}</span></div>
                <button class="updates-primary" :disabled="form.processing || remaining>0">{{form.processing ? 'Subscribing…' : remaining ? `Try again in ${countdown}` : 'Subscribe'}}<ArrowRightIcon v-if="!form.processing && !remaining" aria-hidden="true"/></button>
            </div>
            <label class="updates-consent"><input v-model="form.consent" type="checkbox" required :aria-invalid="!!form.errors.consent" :aria-describedby="form.errors.consent ? 'launch-consent-error' : undefined"/><span>Email me about new businesses in this area. Unsubscribe anytime. <a href="/privacy-policy">Privacy policy</a></span></label>
            <p v-if="form.errors.consent" id="launch-consent-error" class="updates-error">{{form.errors.consent}}</p>
            <div v-if="unavailable || receipt.state==='waiting'" ref="outcome" tabindex="-1" class="updates-feedback">
                <AppNotice v-if="unavailable" tone="warning" title="We couldn’t save your signup" description="Your details are still here. Please try again shortly."><template #actions><a :href="`mailto:${page.props.brand?.support_email || 'support@clipperdesk.com'}?subject=${encodeURIComponent('Marketplace area updates — '+(receipt.reference || 'signup help'))}`">Contact support</a><span v-if="receipt.reference" class="updates-reference">Reference {{receipt.reference}}</span></template></AppNotice>
                <AppNotice v-else title="Please wait a moment" description="No new signup was saved. You can try again when the timer ends."/>
            </div>
        </form>
    </div>
</section>
</template>
<style scoped>
.launch-updates{display:grid;grid-template-columns:minmax(0,.7fr) minmax(0,1.3fr);gap:36px;padding:30px 34px;background:#edf1e5;border:1px solid #dce4d0;border-radius:18px;align-items:start;scroll-margin-top:24px;color:#24382e}.updates-copy{padding-top:7px}.updates-kicker{font-size:10px;font-weight:650;letter-spacing:1.8px;margin:0 0 12px}.updates-copy h2{font-size:32px;font-weight:550;letter-spacing:-1px;line-height:1.15;margin:0 0 12px}.updates-copy>p:not(.updates-kicker){font-size:14px;line-height:1.7;margin:0;color:#52634c;max-width:340px}.updates-content{min-width:0}.updates-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr) auto;gap:12px;align-items:start}.updates-fields label{display:block;font-size:12px;font-weight:600;line-height:1.5}.updates-fields input{display:block;width:100%;min-height:48px;border:1px solid #c8d2be;border-radius:8px;background:white;padding:11px 12px;font-size:14px;margin-top:7px;color:#24382e;box-shadow:none}.updates-primary{display:inline-flex;align-items:center;justify-content:center;gap:12px;min-height:48px;margin-top:25px;padding:12px 18px;border:1px solid #244032;border-radius:8px;background:#244032;color:#fff;font-size:13px;font-weight:600;white-space:nowrap;cursor:pointer}.updates-primary svg{width:18px;height:18px;flex-shrink:0}.updates-primary:disabled{opacity:.6;cursor:default}.updates-consent{display:flex;align-items:start;gap:9px;min-height:44px;margin:12px 0 0;padding:8px 0;font-size:11px;line-height:1.7;cursor:pointer}.updates-consent input{margin-top:2px;flex:0 0 16px;width:16px;height:16px;border:1px solid #9bae8b;border-radius:3px;accent-color:#294731}.updates-consent input:checked{background-color:#294731;border-color:#294731}.updates-consent a{text-decoration:underline;text-underline-offset:3px}.updates-quiet{min-height:44px;padding:8px 0;background:transparent;border:0;text-decoration:underline;text-underline-offset:4px;font-size:12px;font-weight:550}.updates-confirmation :deep(.cd-notice){background:#fff;border-color:#d5dfca;padding:18px;gap:12px}.updates-confirmation :deep(.cd-notice-title){font-size:17px}.updates-confirmation :deep(.cd-notice-body){font-size:13px}.updates-confirmation :deep(.cd-notice-actions){margin-top:8px}.updates-feedback{margin-top:14px}.updates-feedback :deep(.cd-notice){padding:14px;gap:10px}.updates-feedback :deep(.cd-notice-title){font-size:13px}.updates-feedback :deep(.cd-notice-body){font-size:12px}.updates-feedback a{display:inline-flex;min-height:44px;align-items:center;text-decoration:underline;text-underline-offset:4px;font-size:12px}.updates-reference{font-size:10px;line-height:1.6;overflow-wrap:anywhere;color:#69745f}.updates-error{display:block;font-size:12px;line-height:1.65;color:#933b2c;margin:7px 0 0}input:focus-visible,button:focus-visible,a:focus-visible{outline:3px solid #6d8752;outline-offset:3px}.updates-confirmation:focus,.updates-feedback:focus{outline:none}@media(max-width:1100px){.launch-updates{grid-template-columns:1fr;gap:24px}.updates-copy>p:not(.updates-kicker){max-width:none}}@media(max-width:650px){.launch-updates{padding:24px 20px;gap:22px}.updates-copy h2{font-size:30px}.updates-fields{grid-template-columns:1fr;gap:14px}.updates-fields input{font-size:16px}.updates-primary{margin-top:2px;width:100%}.updates-consent{font-size:12px}.updates-kicker{font-size:9px;letter-spacing:1.6px}.updates-confirmation :deep(.cd-notice){padding:16px}}
</style>
