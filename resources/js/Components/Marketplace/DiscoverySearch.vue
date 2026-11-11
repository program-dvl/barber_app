<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import LocationSearch from './LocationSearch.vue';
import { MagnifyingGlassIcon, MapPinIcon, CalendarDaysIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ busy: Boolean, nearbyBusy: Boolean, nearby: Boolean, salons: { type: Array, default: () => [] } });
const emit = defineEmits(['search', 'category', 'nearby', 'place', 'service', 'query-edit']);
const query = defineModel('query');
const location = defineModel('location');
const date = defineModel('date');
const panel = ref('');
const root = ref(null);
const services = ref([]), servicesLoading = ref(false), servicesError = ref(false);
let loaded = false, controller;
watch(panel, async value => {
    if (value !== 'service' || loaded || servicesLoading.value) return;
    loaded = true; servicesLoading.value = true; controller = new AbortController();
    try { const response = await axios.get('/marketplace/services', { signal: controller.signal }); services.value = response.data.services; }
    catch (error) { if (error.code !== 'ERR_CANCELED') servicesError.value = true; }
    finally { servicesLoading.value = false; }
});
const serviceSuggestions = computed(() => services.value.filter(service => !query.value || `${service.name} ${service.category}`.toLowerCase().includes(query.value.toLowerCase())).slice(0, 8));
const localDate = (offset = 0) => {
    const value = new Date();
    value.setDate(value.getDate() + offset);
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
};
const dateLabel = computed(() => !date.value ? 'Any day' : date.value === 'today' ? 'Today' : new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(`${date.value}T12:00:00`)));
const suggestions = computed(() => props.salons.filter(salon => !query.value || salon.name.toLowerCase().includes(query.value.toLowerCase())).slice(0, 4));
const closeOutside = event => { if (!root.value?.contains(event.target)) panel.value = ''; };
const submit = () => { panel.value = ''; emit('search'); };
onMounted(() => document.addEventListener('pointerdown', closeOutside));
onBeforeUnmount(() => { document.removeEventListener('pointerdown', closeOutside); controller?.abort(); });
</script>

<template>
    <form ref="root" class="discovery-search" aria-label="Find an appointment" @submit.prevent="submit" @keydown.esc="panel = ''">
        <div class="search-segment" :class="{ active: panel === 'service' }">
            <MagnifyingGlassIcon />
            <label><span>Service or business</span><input v-model="query" name="q" maxlength="100" placeholder="What’s your next treat?" autocomplete="off" @focus="panel = 'service'" @input="emit('query-edit')" /></label>
            <div v-if="panel === 'service'" class="search-panel">
                <p class="panel-heading">Services from our salons</p>
                <p v-if="servicesLoading" class="panel-help" role="status">Finding services…</p>
                <p v-else-if="servicesError" class="panel-help">You can still type a service above and search.</p>
                <p v-else-if="!serviceSuggestions.length" class="panel-help">No service suggestions for this term. Search by service or salon name.</p>
                <button v-for="service in serviceSuggestions" :key="service.name" type="button" class="salon-suggestion" @click="panel='';emit('service',service.name)"><MagnifyingGlassIcon/><span>{{ service.name }}<small>{{service.category}}</small></span></button>
                <template v-if="suggestions.length"><p class="panel-heading">Salons in these results</p><button v-for="salon in suggestions" :key="salon.url" class="salon-suggestion" type="button" @click="panel='';emit('service',salon.name)"><MagnifyingGlassIcon /><span>{{ salon.name }}</span></button></template>
            </div>
        </div>
        <div class="search-segment" :class="{ active: panel === 'location' }">
            <MapPinIcon />
            <label><span>Location</span><input v-model="location" name="location" maxlength="150" :placeholder="nearby ? 'Selected location · 25 km' : 'Any city or neighbourhood'" autocomplete="off" @focus="panel = 'location'" /></label>
            <div v-if="panel === 'location'" class="search-panel location-panel"><button class="salon-suggestion" type="button" :disabled="nearbyBusy" @click="panel='';emit('nearby')"><MapPinIcon /><span>{{ nearbyBusy ? 'Finding your area…' : 'Use my current location' }}</span></button><LocationSearch @select="point=>{panel='';emit('place',point)}"/><p class="panel-help">Choose a suggestion to find salons within 25 km, or type a city above and search.</p></div>
        </div>
        <div class="search-segment date-segment" :class="{ active: panel === 'date' }">
            <CalendarDaysIcon /><button type="button" class="date-trigger" :aria-expanded="panel==='date'" aria-controls="search-date-panel" @click="panel=panel==='date'?'':'date'"><span>When</span><strong>{{ dateLabel }}</strong></button>
            <div v-if="panel === 'date'" id="search-date-panel" class="search-panel date-panel"><p class="panel-heading">Make time for yourself</p><div class="date-choices"><button type="button" @click="date='';panel=''">Any day</button><button type="button" @click="date='today';panel=''">Today</button><button type="button" @click="date=localDate(1);panel=''">Tomorrow</button></div><label class="calendar-field"><span>Choose a date</span><input type="date" aria-label="Appointment date (optional)" :min="localDate()" :value="date==='today'?localDate():date" @input="date=$event.target.value" /></label></div>
        </div>
        <button class="search-submit" type="submit" :disabled="busy">{{ busy ? 'Searching…' : 'Search' }}<ArrowRightIcon /></button>
    </form>
</template>

<style scoped>
.discovery-search{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr) minmax(130px,.65fr) auto;gap:6px;align-items:center;position:relative;background:#fff;border:1px solid #dce2d3;border-radius:20px;padding:10px;box-shadow:0 14px 38px #243c2410;z-index:5}.search-segment{display:flex;position:relative;align-items:center;gap:12px;padding:12px 18px;border-radius:12px;min-width:0;min-height:68px}.search-segment+.search-segment:before{content:'';position:absolute;left:-3px;top:18px;bottom:18px;width:1px;background:#e2e7db}.search-segment.active{background:#f1f5e9}.search-segment>svg{width:21px;height:21px;flex-shrink:0;color:#637852}.search-segment label{min-width:0;flex:1}.search-segment label>span,.date-trigger>span{display:block;color:#54624d;font-size:11px;font-weight:600;margin-bottom:6px}.search-segment input{border:0;background:transparent;width:100%;padding:0;min-height:24px;box-shadow:none;color:#202d29;font-size:14px}.search-segment input:focus{outline:none}.search-segment:focus-within{outline:2px solid #748c5c;outline-offset:-2px}.date-trigger{border:0;background:none;text-align:left;padding:0;flex:1;min-height:44px}.date-trigger strong{font-size:14px;font-weight:500;color:#202d29}.search-submit{display:flex;align-items:center;justify-content:center;gap:15px;background:#202d29;color:white;border:0;border-radius:12px;padding:20px 25px;min-height:60px;font-size:14px;font-weight:600}.search-submit svg{width:19px;height:19px}.search-panel{position:absolute;top:calc(100% + 18px);left:0;width:390px;max-width:calc(90vw - 20px);padding:22px;background:white;border:1px solid #dce2d3;border-radius:18px;box-shadow:0 16px 45px #20312522;z-index:20}.panel-heading{font-size:12px;font-weight:650;color:#536447;margin:0 0 12px}.panel-heading:not(:first-child){margin-top:22px}.service-choices,.date-choices{display:flex;gap:8px;flex-wrap:wrap}.service-choices button,.date-choices button{min-height:44px;display:flex;align-items:center;gap:10px;border:1px solid #dce2d3;border-radius:100px;padding:10px 14px;color:#304c3c;background:#fafbf7;font-size:13px}.service-choices svg{width:14px;height:14px}.salon-suggestion{display:flex;align-items:center;gap:12px;width:100%;min-height:48px;padding:10px 4px;background:white;border:0;text-align:left;color:#304c3c;font-size:13px}.salon-suggestion:hover{background:#f1f5e9}.salon-suggestion svg{width:18px;height:18px;flex-shrink:0}.panel-help{font-size:12px;color:#63705a;line-height:1.7;margin:12px 0 0}.date-panel{left:auto;right:0;width:330px}.calendar-field{display:block;margin-top:20px}.calendar-field input{padding:10px!important;border:1px solid #cdd6c2!important;border-radius:8px!important;min-height:44px!important}.search-submit:disabled{opacity:.65}@media(max-width:950px){.discovery-search{grid-template-columns:1fr 1fr}.date-segment:before{display:none}.date-panel{left:0;right:auto}.search-submit{margin:4px 8px}}@media(max-width:600px){.discovery-search{grid-template-columns:minmax(0,1fr);padding:8px;border-radius:16px}.search-segment{min-height:66px;padding:10px 14px}.search-segment+.search-segment:before{left:14px;right:14px;top:-3px;bottom:auto;width:auto;height:1px}.search-panel{width:100%;max-width:none;top:calc(100% + 7px)}.search-submit{margin:4px 0 0;min-height:52px;padding:16px}.search-segment input{font-size:16px}}
</style>

<style scoped>.salon-suggestion small{display:block;font-size:11px;color:#69775e;margin-top:4px}.location-panel{overflow:visible}</style>

<style scoped>.search-segment:first-child .search-panel{max-height:min(450px,65vh);overflow-y:auto;overscroll-behavior:contain}</style>
