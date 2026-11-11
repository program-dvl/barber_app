<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { loadGoogleMaps, mapId, onGoogleMapsFailure } from '@/Support/googleMaps';
import AppNotice from '@/Components/Product/AppNotice.vue';
const props = defineProps({ salons: Array });
const emit = defineEmits(['show-list']);
const canvas = ref(null), selected = ref(null), error = ref(''), loading = ref(true), cards = ref(null);
let map, mapsApi, markers = [], alive = true, stopFailure;
const points = props.salons.filter(salon => salon.point);
const select = (salon, fromMap = false) => {
    selected.value = salon.url;
    if (salon.point && map) { map.panTo(salon.point); if (map.getZoom() < 12) map.setZoom(12); }
    markers.forEach(({ marker, element, url }) => { element.style.background = url === salon.url ? '#d5f18b' : '#202d29'; element.style.color = url === salon.url ? '#202d29' : '#fff'; marker.zIndex = url === salon.url ? 10 : 1; });
    if (fromMap) nextTick(() => cards.value?.querySelector('[aria-current="true"]')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
};
const fit = () => {
    if (!map || !points.length) return;
    if (points.length === 1) { map.setCenter(points[0].point); map.setZoom(13); return; }
    const bounds = new mapsApi.LatLngBounds(); points.forEach(salon => bounds.extend(salon.point));
    map.fitBounds(bounds, 60);
    mapsApi.event.addListenerOnce(map, 'idle', () => { if (alive && map.getZoom() > 15) map.setZoom(15); });
};
onMounted(async () => {
    if (!points.length) { error.value = 'These businesses don’t have map pins yet. Their booking pages are available in the list.'; loading.value = false; return; }
    stopFailure = onGoogleMapsFailure(() => { if (alive) { error.value = 'The map isn’t available right now. Your business results are still here.'; loading.value = false; } });
    try {
        const maps = await loadGoogleMaps();
        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([maps.importLibrary('maps'), maps.importLibrary('marker')]);
        if (!alive || error.value) return;
        mapsApi = maps;
        map = new Map(canvas.value, { center: points[0].point, zoom: 13, mapId: mapId(), mapTypeControl: false, streetViewControl: false, fullscreenControl: false, gestureHandling: 'cooperative', clickableIcons: false });
        for (const [index, salon] of points.entries()) {
            const element = document.createElement('div'); element.textContent = String(index + 1);
            element.style.cssText = 'min-width:36px;height:36px;padding:0 10px;border-radius:100px;background:#202d29;color:white;border:2px solid white;box-shadow:0 3px 10px #0004;display:grid;place-items:center;font:600 13px Inter, sans-serif;';
            const marker = new AdvancedMarkerElement({ map, position: salon.point, content: element, title: `${salon.name} · Show business details` });
            marker.addListener('click', () => select(salon, true)); markers.push({ marker, element, url: salon.url });
        }
        fit();
    } catch { if (alive) error.value = 'The map isn’t available right now. Your business results are still here.'; }
    finally { if (alive) loading.value = false; }
});
onBeforeUnmount(() => { alive = false; stopFailure?.(); markers.forEach(({ marker }) => { marker.map = null; }); markers = []; map = null; });
</script>
<template>
    <section class="map-experience" aria-label="Business map and results">
        <div ref="cards" class="map-salons"><div class="map-list-heading"><h3>Find your place</h3><p>{{salons.length}} businesses on this page · {{points.length}} on the map</p></div><article v-for="salon in salons" :key="salon.url" :aria-current="selected===salon.url ? 'true' : undefined" class="map-salon"><button class="map-salon-select" type="button" :aria-pressed="selected===salon.url" @click="select(salon)"><img v-if="salon.image" :src="salon.image" alt="" loading="lazy"/><span v-else class="map-initial">{{salon.name.slice(0,1)}}</span><span><strong>{{salon.name}}</strong><span v-if="salon.reviews.count" class="map-rating">★ {{salon.reviews.average}} · {{salon.reviews.count}} reviews</span><span>{{salon.address}}</span><small>{{salon.point ? `Map pin ${points.indexOf(salon)+1}` : 'Address available · map pin not added' }}<template v-if="salon.distance!==null"> · {{salon.distance}} km away</template></small></span></button><a :href="salon.url" class="map-book">Explore & book <span aria-hidden="true">↗</span></a></article></div>
        <div class="map-panel"><div class="map-topline"><span>Explore businesses on the map</span><button type="button" :disabled="loading || !!error" @click="fit">Show all pins</button></div><p v-if="loading" class="map-loading" role="status">Loading the map…</p><div v-show="!error" ref="canvas" class="map-canvas" aria-label="Interactive business map"/><div v-if="error" class="map-error"><AppNotice title="Keep exploring businesses" :description="error"><template #actions><button class="mp-button secondary" @click="emit('show-list')">Show business list</button></template></AppNotice></div><p class="map-caption">Select a pin to see its business. Distances are straight-line estimates.</p></div>
    </section>
</template>
<style scoped>
.map-experience{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.5fr);border:1px solid #dce2d5;border-radius:16px;background:white;overflow:hidden}.map-salons{max-height:630px;overflow:auto;scrollbar-width:thin}.map-list-heading{padding:22px 20px 16px;border-bottom:1px solid #e0e5d8}.map-list-heading h3{font-size:21px;margin:0 0 5px;letter-spacing:-.5px}.map-list-heading p,.map-caption{font-size:11px;color:#63705c;line-height:1.7;margin:0}.map-salon{padding:16px 18px;border-bottom:1px solid #e1e6db}.map-salon[aria-current=true]{background:#f1f6e8;box-shadow:inset 3px 0 #657c48}.map-salon-select{display:flex;align-items:start;gap:13px;background:transparent;border:0;width:100%;text-align:left;padding:0;color:#202d29;min-height:90px}.map-salon-select>img,.map-initial{width:78px;height:85px;object-fit:cover;border-radius:8px;flex-shrink:0}.map-initial{display:grid;place-items:center;background:#e7eddc;font:35px Georgia,serif}.map-salon-select>span:last-child{display:grid;gap:7px;font-size:12px;line-height:1.5;min-width:0}.map-salon-select strong{font-size:14px}.map-salon-select small{font-size:10px;color:#64755a}.map-rating{font-size:11px}.map-book{min-height:44px;display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:650;margin-top:10px;border-top:1px solid #e0e5d8;padding-top:10px;color:#304c3c!important}.map-panel{position:relative;min-width:0;background:#f1f4ed;display:flex;flex-direction:column}.map-topline{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;background:white;border-left:1px solid #e1e6da;font-size:12px}.map-topline button{min-height:44px;border:1px solid #d5dfca;border-radius:100px;padding:8px 14px;background:white;color:#304c3c;font-size:11px}.map-canvas{min-height:500px;flex:1}.map-caption{padding:10px 18px;background:white;border-left:1px solid #e1e6da}.map-loading{position:absolute;z-index:2;top:90px;left:20px;background:white;border-radius:8px;padding:14px;font-size:13px;box-shadow:0 5px 15px #0001}.map-error{padding:25px;min-height:330px;display:grid;align-content:center}@media(max-width:850px){.map-experience{grid-template-columns:1fr}.map-panel{grid-row:1}.map-canvas{min-height:370px}.map-salons{max-height:470px}.map-topline{padding:10px 14px}.map-topline span{font-size:11px}.map-salon-select>img,.map-initial{width:85px;height:90px}.map-caption{font-size:10px}}
</style>
