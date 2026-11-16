<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRightIcon, ArrowPathIcon, ClipboardDocumentIcon, LinkIcon, CheckIcon, CalendarDaysIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import ToastRegion from '@/Components/Product/ToastRegion.vue';
import { appointmentValueMinor, missedAppointmentCount, noShowEstimate, noShowMoney, collectedAmountMinor, calculatorCurrencies, exampleNoShows } from '@/Support/noShowCalculator';

const props = defineProps({ seo: Object });
const value = ref(exampleNoShows.value), missed = ref(exampleNoShows.missed), recovery = ref(exampleNoShows.recovery), currency = ref('USD');
const edited = ref(false), refined = ref(false);
const adjustments = ref({refilled:'0', collected:'0', avoided:'0'});
const touched = ref({ value:false, missed:false, refilled:false, collected:false, avoided:false });
const copyFallback = ref(''), toasts = ref([]), announcement = ref('');
let announceTimer, toastTimer;
const estimate = computed(() => noShowEstimate(value.value, missed.value, Number(recovery.value), adjustments.value));
const money = minor => noShowMoney(minor, currency.value);
const valueError = computed(() => appointmentValueMinor(value.value) === null ? 'Enter an amount from 0 to 1,000,000, with up to two decimal places.' : '');
const missedError = computed(() => missedAppointmentCount(missed.value) === null ? 'Enter a whole number from 0 to 100,000.' : '');
const refilledError = computed(() => { const n=missedAppointmentCount(adjustments.value.refilled); const total=missedAppointmentCount(missed.value); return n===null || (total!==null && n>total) ? 'Enter a whole number up to your missed appointment count.' : ''; });
const collectedError = computed(() => collectedAmountMinor(adjustments.value.collected)===null ? 'Enter the total kept for empty slots, using up to two decimal places.' : '');
const avoidedError = computed(() => appointmentValueMinor(adjustments.value.avoided)===null ? 'Enter a per-visit amount from 0 to 1,000,000.' : '');
const hasAdjustments = computed(() => Object.values(adjustments.value).some(n => Number(n)!==0));
const fullAmount = computed(() => estimate.value ? money(estimate.value.monthlyMinor) : '—');
const recoveredCount = computed(() => estimate.value ? new Intl.NumberFormat('en', {maximumFractionDigits:2}).format(estimate.value.recoveryAppointments) : '—');
const changed = () => { edited.value = true; copyFallback.value = ''; };
const reset = () => { value.value=exampleNoShows.value; missed.value=exampleNoShows.missed; recovery.value=exampleNoShows.recovery; touched.value={value:false,missed:false,refilled:false,collected:false,avoided:false}; adjustments.value={refilled:'0',collected:'0',avoided:'0'}; refined.value=false; edited.value=false; copyFallback.value=''; };
watch([estimate, currency], () => {
    clearTimeout(announceTimer);
    announceTimer = setTimeout(() => { announcement.value = estimate.value ? `Estimated earnings at risk: ${money(estimate.value.monthlyMinor)} per month, ${money(estimate.value.annualMinor)} over twelve months. Currency ${currency.value}.` : 'Add a valid appointment value and missed appointment count to see your estimate.'; }, 500);
});
onBeforeUnmount(() => { clearTimeout(announceTimer); clearTimeout(toastTimer); });
const summary = computed(() => estimate.value ? [
    `No-show cost estimate (${currency.value})`,
    `Average appointment value: ${money(appointmentValueMinor(value.value))}`,
    `Missed appointments per month: ${missed.value}; refilled slots: ${adjustments.value.refilled}`,
    `Retained payments for empty slots: ${money(estimate.value.collectedMinor)}; costs avoided: ${money(estimate.value.avoidedMonthlyMinor)}`,
    `Estimated earnings at risk: ${money(estimate.value.monthlyMinor)}/month; ${money(estimate.value.annualMinor)} over 12 months.`,
    `With ${recovery.value}% fewer empty slots: ${money(estimate.value.recoveryMonthlyMinor)}/month in estimated earnings protected.`,
    'Illustrative scenario using entered figures. Fixed business costs are not modeled. Actual outcomes vary.',
    props.seo.canonical,
].join('\n') : '');
async function copy(kind) {
    const text = kind === 'link' ? props.seo.canonical : summary.value;
    if (!text) return;
    try {
        if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(text);
        copyFallback.value=''; toasts.value=[{id:1,tone:'success',title:kind==='link'?'Tool link copied':'Estimate copied'}];
        clearTimeout(toastTimer); toastTimer=setTimeout(()=>toasts.value=[],3500);
    } catch { copyFallback.value=text; }
}
</script>

<template>
<HomeLayout>
    <Head :title="seo.title" />
    <div class="no-show-page">
        <div class="ns-wrap">
            <Breadcrumbs :items="[{label:'Home',href:route('marketing.home')},{label:'Resources',href:route('marketing.resources')},{label:'No-show calculator'}]" />
            <header class="ns-intro"><div><p class="ns-eyebrow"><span></span>FREE BUSINESS TOOL</p><h1>Empty appointments.<br><em>A real cost.</em></h1><p>A few missed visits can add up. See what missed visits could mean for your month. Start with two figures, then account for refilled slots, kept payments and costs you avoided.</p></div><div class="ns-intro-note"><ShieldCheckIcon aria-hidden="true"/><span>No signup. No spreadsheet.<br><strong>Your figures stay in this calculator.</strong></span></div></header>
            <section class="ns-calculator" aria-label="No-show cost calculator">
                <div class="ns-inputs">
                    <div class="ns-card-heading"><span class="ns-step">01</span><div><h2>A picture of your month</h2><p>Use a typical month, or try the example.</p></div></div>
                    <div class="ns-currency"><label for="ns-currency">Your currency</label><select id="ns-currency" v-model="currency" @change="changed"><option v-for="code in calculatorCurrencies" :key="code" :value="code">{{code}}</option></select></div>
                    <div class="ns-field"><label for="ns-value">Average appointment value</label><div class="ns-number"><span class="ns-currency-affix" aria-hidden="true">{{currency}}</span><input id="ns-value" v-model="value" type="text" inputmode="decimal" maxlength="12" autocomplete="off" :aria-invalid="touched.value && !!valueError" :aria-describedby="touched.value && valueError ? 'ns-value-error' : 'ns-value-help'" @input="changed" @blur="touched.value=true" /></div><p v-if="touched.value && valueError" id="ns-value-error" class="ns-error">{{valueError}}</p><p v-else id="ns-value-help" class="ns-help">Your usual service price, before tax.</p></div>
                    <div class="ns-field"><label for="ns-missed">Missed appointments per month</label><div class="ns-number"><span class="ns-leading-icon" aria-hidden="true"><CalendarDaysIcon/></span><input id="ns-missed" v-model="missed" type="text" inputmode="numeric" maxlength="8" autocomplete="off" :aria-invalid="touched.missed && !!missedError" :aria-describedby="touched.missed && missedError ? 'ns-missed-error' : 'ns-missed-help'" @input="changed" @blur="touched.missed=true"/><span class="ns-unit" aria-hidden="true">visits</span></div><p v-if="touched.missed && missedError" id="ns-missed-error" class="ns-error">{{missedError}}</p><p v-else id="ns-missed-help" class="ns-help">Count missed visits. You can subtract refilled slots below.</p></div>
                    <div class="ns-refine">
                        <button type="button" class="ns-refine-toggle" :aria-expanded="refined" aria-controls="ns-refinements" @click="refined=!refined"><span>{{refined ? 'Your adjustments' : 'Make it more accurate'}}<small>{{refilledError || collectedError || avoidedError ? 'Check your adjustments' : hasAdjustments ? 'Adjustments included in your estimate' : 'Optional · three quick adjustments'}}</small></span><span aria-hidden="true">{{refined ? '−' : '+'}}</span></button>
                        <div v-show="refined" id="ns-refinements" class="ns-refinements">
                            <div class="ns-field"><label for="ns-refilled">How many slots did you refill?</label><div class="ns-number"><input id="ns-refilled" v-model="adjustments.refilled" type="text" inputmode="numeric" maxlength="8" :aria-invalid="!!refilledError" aria-describedby="ns-refilled-help" @input="changed"/><span class="ns-unit" aria-hidden="true">slots</span></div><p id="ns-refilled-help" :class="refilledError ? 'ns-error' : 'ns-help'">{{refilledError || 'Count only replacement bookings that went ahead.'}}</p></div>
                            <div class="ns-field"><label for="ns-collected">Payments kept for the empty slots</label><div class="ns-number"><span class="ns-currency-affix" aria-hidden="true">{{currency}}</span><input id="ns-collected" v-model="adjustments.collected" type="text" inputmode="decimal" maxlength="16" :aria-invalid="!!collectedError" aria-describedby="ns-collected-help" @input="changed"/></div><p id="ns-collected-help" :class="collectedError ? 'ns-error' : 'ns-help'">{{collectedError || 'Monthly total of deposits, fees or payments retained for slots that stayed empty. Leave out refunded amounts.'}}</p></div>
                            <div class="ns-field"><label for="ns-avoided">Costs avoided per empty appointment</label><div class="ns-number"><span class="ns-currency-affix" aria-hidden="true">{{currency}}</span><input id="ns-avoided" v-model="adjustments.avoided" type="text" inputmode="decimal" maxlength="12" :aria-invalid="!!avoidedError" aria-describedby="ns-avoided-help" @input="changed"/></div><p id="ns-avoided-help" :class="avoidedError ? 'ns-error' : 'ns-help'">{{avoidedError || 'For example, materials or commission you only pay for completed visits. Leave out rent and regular salaries.'}}</p></div>
                        </div>
                    </div>
                    <div class="ns-input-footer"><span><CheckIcon aria-hidden="true"/>Updates as you type</span><button type="button" @click="reset"><ArrowPathIcon aria-hidden="true"/>Reset example</button></div>
                </div>
                <div class="ns-results" aria-labelledby="ns-result-title">
                    <div class="ns-result-top"><span class="ns-step">02</span><span class="ns-status">{{edited ? 'Your estimate' : 'Example estimate'}}</span></div>
                    <h2 id="ns-result-title">Estimated earnings at risk</h2><p class="ns-total" :class="{'ns-total-long':fullAmount.length>12}">{{fullAmount}}<span v-if="estimate">{{currency}} / month</span></p>
                    <p v-if="estimate" class="ns-year"><strong>{{money(estimate.annualMinor)}}</strong> over 12 months at this rate</p><p v-else class="ns-year">Add valid figures to see your estimate.</p>
                    <p class="ns-result-caption">{{hasAdjustments ? 'After your adjustments, before fixed business costs.' : 'Quick estimate. Refine it for payments kept and costs avoided.'}}</p>
                    <div v-if="estimate && (refined || hasAdjustments)" class="ns-breakdown"><h3>Your month, explained</h3><dl><div><dt>Value of missed appointments</dt><dd>{{money(estimate.grossMonthlyMinor)}}</dd></div><div><dt>Value preserved by refilling slots</dt><dd>− {{money(estimate.refilledValueMinor)}}</dd></div><div><dt>Payments already kept</dt><dd>− {{money(estimate.collectedMinor)}}</dd></div><div><dt>Per-visit costs avoided</dt><dd>− {{money(estimate.avoidedMonthlyMinor)}}</dd></div><div class="ns-net"><dt>Estimated earnings at risk</dt><dd>{{money(estimate.monthlyMinor)}}</dd></div></dl><p v-if="estimate.covered">Your kept payments and avoided costs cover the empty slots’ service value. The at-risk estimate stops at zero.</p><p v-else>{{estimate.emptySlots}} {{estimate.emptySlots===1?'slot stayed':'slots stayed'}} empty after replacement bookings.</p></div>
                    <div class="ns-scenario"><div class="ns-scenario-title"><h3>What if you had fewer empty slots?</h3><span>{{recovery}}%</span></div><label for="ns-recovery" class="sr-only">Percentage reduction in empty slots for your scenario</label><input id="ns-recovery" v-model.number="recovery" type="range" min="0" max="100" step="5" :aria-valuetext="`${recovery}% fewer empty slots`" @input="changed"/><div class="ns-range-labels" aria-hidden="true"><span>None</span><span>No empty slots</span></div><div class="ns-scenario-result"><div><span>Estimated earnings protected</span><strong>{{estimate ? money(estimate.recoveryMonthlyMinor) : '—'}}<small> / month</small></strong></div><span v-if="estimate">Equivalent to {{recoveredCount}} fewer empty {{estimate.recoveryAppointments===1?'appointment':'appointments'}}.</span></div><p>Your scenario, using the same average payments and costs—not a forecast.</p></div>
                    <div class="ns-result-actions"><button type="button" :disabled="!estimate" @click="copy('summary')"><ClipboardDocumentIcon aria-hidden="true"/>Copy estimate</button><button type="button" @click="copy('link')"><LinkIcon aria-hidden="true"/>Share this tool</button></div>
                </div>
            </section>
            <p class="sr-only" aria-live="polite" aria-atomic="true">{{announcement}}</p>
            <div v-if="copyFallback" class="ns-copy-fallback"><AppNotice title="Copy the text below" description="Your browser couldn’t copy automatically. Select the text and use your usual Copy command."/><label for="ns-copy">{{copyFallback===seo.canonical?'Tool link':'Your estimate'}}</label><textarea id="ns-copy" :value="copyFallback" readonly rows="6" @focus="$event.target.select()"/></div>
            <div class="ns-under"><p><ShieldCheckIcon aria-hidden="true"/>No account needed. Calculator inputs aren’t saved or sent to analytics.</p><a href="#how-it-works">How the calculation works <ArrowRightIcon aria-hidden="true"/></a></div>
            <section id="how-it-works" class="ns-method"><div><p class="ns-eyebrow">THE MATH, MADE CLEAR</p><h2>A simple estimate.<br>A useful starting point.</h2><p>For salons, wellness studios, fitness professionals, pet groomers and other appointment-led businesses.</p></div><div class="ns-method-details"><div class="ns-formula"><span>Value of empty slots</span><b aria-label="minus">−</b><span>Kept payments & avoided costs</span><b aria-label="equals">=</b><strong>Earnings at risk</strong></div><p>We start with appointment value × (missed visits − refilled slots). Then we subtract payments kept for the remaining empty slots and per-visit costs you avoided. The result stops at zero. Multiply by 12 for a yearly estimate at the same rate.</p><p>The scenario models fewer empty slots, assuming the same average appointment value, retained payments and avoidable costs. It rounds to the nearest currency minor unit. Fixed costs, tax and any new cost of preventing a no-show are outside the estimate; subtract those costs when deciding what to spend.</p><p>Use before-tax amounts throughout. Changing currency changes the display currency; it doesn’t convert your figures.</p></div></section>
            <section class="ns-next"><div><p class="ns-eyebrow">MAKE THE NEXT MONTH EASIER TO MANAGE</p><h2>Give every appointment<br>a clearer path to arrival.</h2><p>Bring bookings, client details and your team’s calendar together. Confirmations, reminders and clear booking information can support the way you run your day.</p><p class="ns-next-note">Message delivery depends on your setup. SMS also needs client consent, supported numbers and available credits.</p></div><div class="ns-next-actions"><PublicCta context="no_show_calculator"/><Link :href="route('marketing.product-tour')" class="ns-tour">See how Clipperdesk works<ArrowRightIcon aria-hidden="true"/></Link></div></section>
            <section class="ns-related"><div><p class="ns-eyebrow">PUT YOUR ESTIMATE TO WORK</p><h2>A little preparation goes a long way.</h2></div><div><Link :href="route('marketing.guides.show','booking-policy-basics')"><span>Practical guide</span><strong>Make booking expectations clear.</strong><ArrowRightIcon aria-hidden="true"/></Link><Link :href="route('marketing.features.show','online-booking')"><span>Explore Clipperdesk</span><strong>One place to book and manage visits.</strong><ArrowRightIcon aria-hidden="true"/></Link></div></section>
        </div>
    </div>
    <ToastRegion :messages="toasts" @dismiss="toasts=[]"/>
</HomeLayout>
</template>

<style scoped>
.no-show-page{background:#f7f8fc;color:var(--text-strong,#172554);padding:26px 0 76px}
.ns-wrap{max-width:1280px;width:90%;margin:auto}
.ns-intro{display:flex;justify-content:space-between;align-items:end;gap:40px;padding:48px 0 38px}
.ns-eyebrow{font-size:10px;font-weight:750;letter-spacing:1.8px;color:#4c46c5;margin:0 0 18px;display:flex;gap:8px;align-items:center}
.ns-eyebrow>span{width:6px;height:6px;border-radius:50%;background:#5b4de0}
.ns-intro h1{font-size:clamp(42px,5.2vw,70px);font-weight:650;letter-spacing:-3px;line-height:1.06;margin:0 0 22px}
.ns-intro h1 em{font-style:normal;color:#5041ce}
.ns-intro>div>p:last-child{max-width:600px;font-size:16px;line-height:1.85;color:#596780;margin:0}
.ns-intro-note{display:flex;align-items:start;gap:12px;padding:16px 0 5px;flex-shrink:0}
.ns-intro-note>svg{width:22px;height:22px;color:#6960ba;flex-shrink:0}
.ns-intro-note span{font-size:12px;line-height:1.9;color:#64708a}
.ns-intro-note strong{font-weight:550;color:#374366}
.ns-calculator{display:grid;grid-template-columns:1fr 1.05fr;align-items:stretch;border:1px solid #dce2ee;border-radius:24px;background:white;overflow:hidden;box-shadow:0 20px 65px #1725540a}
.ns-inputs{padding:34px 38px;min-width:0}
.ns-card-heading{display:flex;align-items:center;gap:14px;margin-bottom:25px}
.ns-step{width:32px;height:32px;display:grid;place-items:center;border:1px solid #d8ddeb;border-radius:50%;font-size:10px;font-weight:650;flex-shrink:0;color:#697590}
.ns-card-heading h2{font-size:23px;letter-spacing:-.65px;font-weight:650;line-height:1.3;margin:0 0 4px}
.ns-card-heading p{font-size:12px;color:#68748b;margin:0;line-height:1.6}
.ns-currency{display:flex;align-items:center;justify-content:space-between;padding-bottom:17px;border-bottom:1px solid #e7ebf2;margin-bottom:25px}
.ns-currency label{font-size:12px;color:#65718b}
.ns-currency select{min-height:44px;border:1px solid #dae0ec;border-radius:8px;background:#f8f9fd;padding:9px 34px 9px 13px;font-size:13px;font-weight:650}
.ns-field+.ns-field{margin-top:25px}
.ns-field>label{display:block;font-size:13px;font-weight:650;color:#344262;margin-bottom:10px}
.ns-number{display:flex;align-items:center;gap:12px;min-width:0;border:1px solid #ccd5e5;border-radius:11px;padding:5px 15px;background:#fff}
.ns-number:focus-within{outline:3px solid #dad5fb;border-color:#6957da}
.ns-number>span{flex:0 0 auto;white-space:nowrap;overflow-wrap:normal;word-break:normal;line-height:1.4}
.ns-currency-affix{width:32px;min-width:32px;font-size:12px;font-weight:600;color:#7a849d}
.ns-leading-icon{display:flex;align-items:center;width:32px;min-width:32px}.ns-leading-icon>svg{width:19px;height:19px;flex:0 0 19px;color:#8290aa}
.ns-number input{flex:1 1 0%;width:0;min-width:0;line-height:1.25;min-height:48px;border:0;background:transparent;padding:6px 0;font-size:27px;font-weight:600;letter-spacing:-.7px;color:#25345a;box-shadow:none;outline:none}
.ns-number input:focus{box-shadow:none;outline:none}
.ns-unit{font-size:12px;color:#7a849d;white-space:nowrap}
.ns-help{font-size:12px;line-height:1.7;color:#728097;margin:9px 0 0}
.ns-error{font-size:12px;line-height:1.7;color:#a33632;margin:9px 0 0}
.ns-input-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:30px;padding-top:18px;border-top:1px solid #e7ebf2;font-size:11px;color:#7b869b;flex-wrap:wrap}
.ns-input-footer>span,.ns-input-footer button{display:flex;align-items:center;gap:7px}
.ns-input-footer button{min-height:44px;font-size:12px;color:#5d54b0;background:none;border:0;padding:8px 0}
.ns-input-footer svg{width:15px;height:15px}
.ns-results{padding:30px 36px;background:#152448;color:#fff;min-width:0}
.ns-result-top{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:24px}
.ns-results .ns-step{border-color:#435171;color:#b8c3dc}
.ns-status{font-size:10px;font-weight:650;color:#d1d6ff;padding:7px 12px;background:#ffffff0d;border:1px solid #ffffff1c;border-radius:20px}
.ns-results>h2{font-size:13px;font-weight:500;letter-spacing:.2px;color:#c7d2e9;margin:0 0 12px}
.ns-total{font-size:clamp(43px,4.7vw,66px);letter-spacing:-2.5px;font-weight:600;line-height:1.13;margin:0;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
.ns-total-long{font-size:38px;letter-spacing:-1.5px}
.ns-total>span{display:block;font-size:11px;font-weight:500;line-height:1.7;letter-spacing:.5px;color:#b9c8e5;margin-top:5px}
.ns-year{font-size:12px;line-height:1.8;color:#b4c3e1;margin:20px 0 0;overflow-wrap:anywhere}
.ns-year strong{font-size:19px;font-weight:600;color:white;margin-right:5px}
.ns-result-caption{font-size:10px;line-height:1.8;color:#9cafd1;margin:5px 0 22px}
.ns-scenario{border:1px solid #ffffff20;border-radius:13px;padding:18px 20px;background:#ffffff07}
.ns-scenario-title{display:flex;gap:15px;align-items:start;justify-content:space-between}
.ns-scenario-title h3{font-size:12px;font-weight:550;line-height:1.7;margin:0;color:#e1e8fb;max-width:260px}
.ns-scenario-title>span{flex:0 0 auto;white-space:nowrap;font-size:17px;color:#b9afff;font-weight:650}
.ns-scenario input[type=range]{display:block;width:100%;min-height:44px;margin:8px 0 0;accent-color:#b6aaff;cursor:pointer}
.ns-range-labels{display:flex;justify-content:space-between;font-size:9px;color:#9bafd2;margin-top:-3px}
.ns-scenario-result{margin-top:18px;padding-top:16px;border-top:1px solid #ffffff16}
.ns-scenario-result>div>span{display:block;font-size:10px;color:#b4c5e5;margin-bottom:6px}
.ns-scenario-result strong{display:block;font-size:28px;font-weight:600;letter-spacing:-.7px;color:#ddd6ff;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
.ns-scenario-result small{display:inline-block;white-space:nowrap;font-size:10px;font-weight:400;letter-spacing:0;color:#adbbd8}
.ns-scenario-result>span{display:block;font-size:10px;line-height:1.8;color:#afbfdd;margin-top:5px}
.ns-scenario>p{font-size:10px;line-height:1.7;margin:10px 0 0;color:#9fb1d3}
.ns-result-actions{display:flex;flex-wrap:wrap;gap:16px;margin-top:17px}
.ns-result-actions button{display:flex;gap:8px;align-items:center;min-height:44px;color:#d8e2f8;background:none;border:0;padding:5px 0;font-size:11px}
.ns-result-actions svg{width:16px;height:16px}
.ns-result-actions button:disabled{opacity:.45}
.ns-under{display:flex;justify-content:space-between;align-items:center;gap:20px;margin:20px 0 58px}
.ns-under>p,.ns-under>a{display:flex;align-items:center;gap:8px;font-size:11px;line-height:1.8;color:#7c879d;margin:0}
.ns-under>a{min-height:44px;color:#4b48a5;text-decoration:underline;text-underline-offset:4px;flex-shrink:0}
.ns-under svg{width:16px;height:16px;flex-shrink:0}
.ns-method{display:grid;grid-template-columns:.85fr 1.15fr;gap:60px;padding:10px 0 50px;scroll-margin-top:120px}
.ns-method h2{font-size:31px;font-weight:600;line-height:1.2;letter-spacing:-1px;margin:0 0 18px}
.ns-method>div>p:last-child{font-size:14px;line-height:1.85;max-width:340px;color:#6c7890}
.ns-formula{display:flex;align-items:center;gap:12px;padding:18px;border:1px solid #dce2ed;border-radius:12px;background:white;font-size:11px;color:#6a7790;line-height:1.65}
.ns-formula>span{flex:1}
.ns-formula b{font-size:18px;font-weight:450;color:#9ba5b9}
.ns-formula strong{flex:1.2;font-weight:650;color:#4a43a6}
.ns-method-details>p{font-size:12px;color:#6b7890;line-height:1.9;margin:16px 0 0}
.ns-next{display:flex;justify-content:space-between;align-items:center;gap:35px;padding:36px 40px;border:1px solid #dedcf1;border-radius:18px;background:#efedfa}
.ns-next h2{font-size:32px;font-weight:600;letter-spacing:-1px;line-height:1.2;margin:0 0 18px}
.ns-next>div>p:not(.ns-eyebrow){font-size:13px;line-height:1.85;max-width:590px;color:#615c7c;margin:0}
.ns-next .ns-next-note{font-size:10px!important;line-height:1.8!important;margin-top:10px!important;max-width:540px!important;color:#7a7293!important}
.ns-next-actions{display:flex;flex-direction:column;align-items:start;gap:12px;flex-shrink:0}
.ns-tour{display:flex;align-items:center;gap:10px;min-height:44px;font-size:12px;color:#5149a4}
.ns-tour svg{width:16px;height:16px}
.ns-related{margin-top:50px}
.ns-related h2{font-size:27px;font-weight:600;letter-spacing:-.7px;margin:0}
.ns-related>div:last-child{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:24px}
.ns-related a{position:relative;display:block;border:1px solid #dce2ed;border-radius:12px;padding:23px 45px 23px 24px;background:white}
.ns-related a>span{display:block;font-size:10px;color:#7d879b;margin-bottom:8px}
.ns-related a>strong{font-size:16px;font-weight:600;color:#39496d}
.ns-related svg{width:18px;height:18px;position:absolute;right:20px;top:calc(50% - 9px);color:#6b60bf}
.ns-copy-fallback{margin-top:20px}
.ns-copy-fallback>label{display:block;font-size:12px;margin:15px 0 8px}
.ns-copy-fallback textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:15px;font-size:13px;line-height:1.8;background:white}
button:focus-visible,a:focus-visible,select:focus-visible,input[type=range]:focus-visible,textarea:focus-visible{outline:3px solid #8c7ee5;outline-offset:4px}
@media(max-width:950px){.ns-intro-note{display:none}
.ns-inputs,.ns-results{padding:28px}
.ns-card-heading h2{font-size:20px}
.ns-method{gap:32px}
.ns-next{align-items:start;flex-direction:column}
.ns-next-actions{flex-direction:row;align-items:center;flex-wrap:wrap;gap:14px}
}
@media(max-width:650px){.no-show-page{padding-top:16px;padding-bottom:45px}
.ns-wrap{width:90%}
.ns-intro{padding:30px 0 28px}
.ns-intro h1{font-size:43px;letter-spacing:-2px}
.ns-intro>div>p:last-child{font-size:14px;line-height:1.8}
.ns-eyebrow{font-size:9px;letter-spacing:1.6px;margin-bottom:14px}
.ns-calculator{grid-template-columns:1fr;border-radius:18px}
.ns-inputs,.ns-results{padding:24px 22px}
.ns-card-heading h2{font-size:21px}
.ns-card-heading{gap:11px}
.ns-currency{margin-bottom:22px}
.ns-number input{font-size:27px;min-height:46px}
.ns-input-footer{margin-top:22px}
.ns-results{padding-top:24px}
.ns-total{font-size:58px}
.ns-total-long{font-size:34px}
.ns-scenario{padding:17px}
.ns-year strong{font-size:18px}
.ns-scenario-result strong{font-size:28px}
.ns-under{align-items:start;flex-direction:column;gap:6px;margin-bottom:32px}
.ns-under>p{align-items:start;font-size:10px}
.ns-under svg{margin-top:2px}
.ns-method{grid-template-columns:1fr;gap:18px;padding-bottom:32px}
.ns-method h2{font-size:29px}
.ns-formula{gap:9px;padding:14px;font-size:10px}
.ns-next{padding:26px 23px;gap:22px}
.ns-next h2{font-size:28px}
.ns-next-actions{flex-direction:column;align-items:stretch;width:100%;gap:10px}
.ns-next-actions :deep(.cd-button){width:100%;justify-content:center}
.ns-related{margin-top:34px}
.ns-related h2{font-size:25px}
.ns-related>div:last-child{grid-template-columns:1fr;gap:12px;margin-top:18px}
.ns-related a{padding:22px 45px 22px 20px}
.ns-related a>strong{font-size:15px}
}


.ns-refine{margin-top:26px;border:1px solid #ddd9f1;border-radius:11px;background:#faf9fe}
.ns-refine-toggle{display:flex;justify-content:space-between;align-items:center;gap:15px;width:100%;min-height:64px;padding:14px 16px;border:0;background:none;text-align:left;color:#5146a8;font-size:13px;font-weight:650}
.ns-refine-toggle small{display:block;font-size:10px;line-height:1.7;font-weight:400;color:#837b9f;margin-top:4px}
.ns-refine-toggle>span:first-child{min-width:0}
.ns-refine-toggle>span:last-child{flex:0 0 20px;text-align:center;font-size:23px;font-weight:400}
.ns-refinements{padding:8px 16px 20px}
.ns-refinements .ns-field>label{font-size:12px}
.ns-refinements .ns-number input{min-height:38px;font-size:21px}
.ns-refinements .ns-field+.ns-field{margin-top:20px}
.ns-refinements .ns-help,.ns-refinements .ns-error{font-size:11px}
.ns-breakdown{margin:24px 0;padding-top:20px;border-top:1px solid #ffffff20}
.ns-breakdown h3{font-size:12px;font-weight:600;margin:0 0 14px;color:#e1e8fb}
.ns-breakdown dl{margin:0}
.ns-breakdown dl>div{display:flex;justify-content:space-between;gap:16px;margin:11px 0;font-size:11px;line-height:1.7}
.ns-breakdown dt{color:#b4c3e1}
.ns-breakdown dt{min-width:0;flex:1 1 auto}
.ns-breakdown dd{margin:0;color:#e0e8f9;text-align:right;flex:0 0 auto;white-space:nowrap;font-variant-numeric:tabular-nums}
.ns-breakdown .ns-net{padding-top:12px;margin-top:13px;border-top:1px solid #ffffff20;font-weight:600}
.ns-breakdown .ns-net dt,.ns-breakdown .ns-net dd{color:#d8ceff}
.ns-breakdown>p{font-size:10px;line-height:1.8;color:#a4b7d9;margin:14px 0 0}
.ns-inputs{display:flex;flex-direction:column}
.ns-input-footer{margin-top:auto;padding-top:22px}
.ns-refine+.ns-input-footer{margin-top:24px}

</style>
