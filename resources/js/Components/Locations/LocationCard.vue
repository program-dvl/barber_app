<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { MapPinIcon, MapIcon, ArrowUpRightIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { coordinates, navigationLinks } from '@/Support/locationIntelligence';
import { loadGoogleMaps, mapId, onGoogleMapsFailure } from '@/Support/googleMaps';

const props = defineProps({ location: { type: Object, required: true }, preview: Boolean });
const links = computed(() => navigationLinks(props.location));
const point = computed(() => coordinates(props.location));
const hasNotes = computed(() => props.location.entrance_instructions || props.location.parking_instructions);
const canvas = ref(null), opened = ref(false), loading = ref(false), error = ref('');
let stopFailure, map, marker, alive = true, version = 0;
async function showMap() {
    if (opened.value) { opened.value = false; return; }
    opened.value = true; loading.value = true; error.value = '';
    const request = ++version;
    try {
        const maps = await loadGoogleMaps();
        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([maps.importLibrary('maps'), maps.importLibrary('marker')]);
        await nextTick();
        if (!alive || request !== version || !canvas.value || !point.value) return;
        if (map) {
            map.setCenter(point.value);
            marker.map = map;
            marker.position = point.value;
            marker.title = props.location.name;
        } else {
            map = new Map(canvas.value, { center: point.value, zoom: 17, mapId: mapId(), mapTypeControl: false, streetViewControl: false, gestureHandling: 'cooperative' });
            marker = new AdvancedMarkerElement({ map, position: point.value, title: props.location.name });
        }
    } catch { if (alive && request === version) error.value = 'The map is unavailable. You can still get directions or use the address below.'; }
    finally { if (alive && request === version) loading.value = false; }
}
watch(() => props.location, () => { ++version; opened.value = false; loading.value = false; error.value = ''; if (marker) marker.map = null; }, { deep: true });
onMounted(() => { stopFailure = onGoogleMapsFailure(() => { loading.value = false; error.value = 'The map is unavailable. You can still get directions.'; }); });
onBeforeUnmount(() => { alive = false; ++version; stopFailure?.(); if (marker) marker.map = null; });
</script>

<template>
    <section class="location-card" aria-label="Appointment location">
        <p v-if="preview" class="location-eyebrow">Customer preview</p>
        <header class="location-heading">
            <span class="location-pin"><MapPinIcon aria-hidden="true" /></span>
            <div class="min-w-0"><h3>{{ location.name || 'Your location' }}</h3><p class="location-address">{{ location.address || 'Add your public address' }}</p><p v-if="location.address_line_2" class="location-unit">{{ location.address_line_2 }}</p></div>
        </header>
        <details v-if="hasNotes" class="location-notes">
            <summary><span>Arrival &amp; parking</span><ChevronDownIcon class="size-4" aria-hidden="true" /></summary>
            <p v-if="location.entrance_instructions"><strong>Arrival</strong>{{ location.entrance_instructions }}</p>
            <p v-if="location.parking_instructions"><strong>Parking</strong>{{ location.parking_instructions }}</p>
        </details>
        <button v-if="point" type="button" class="location-map-control" :aria-expanded="opened" :disabled="loading" @click="showMap">
            <span class="location-map-icon"><MapIcon aria-hidden="true" /></span><span><strong>{{ loading ? 'Loading map…' : opened ? 'Hide map' : 'View map' }}</strong><span v-if="!opened">Find the customer entrance</span></span><ChevronDownIcon :class="['ml-auto size-4 shrink-0', opened ? 'rotate-180' : '']" aria-hidden="true" />
        </button>
        <div v-show="opened" class="location-map-region">
            <p v-if="loading" class="location-status" role="status">Loading Google Maps…</p>
            <p v-if="error" class="location-status" role="status">{{ error }}</p>
            <div v-show="!error" ref="canvas" class="location-map" aria-label="Entrance map" />
        </div>
        <div v-if="links.directions_url" class="location-actions">
            <a :href="links.directions_url" target="_blank" rel="noopener noreferrer" class="location-directions">Get directions<ArrowUpRightIcon class="size-4" aria-hidden="true" /><span class="ds-sr-only"> in Google Maps, opens a new tab</span></a>
            <a v-if="links.apple_maps_url" :href="links.apple_maps_url" target="_blank" rel="noopener noreferrer" class="location-apple">Apple Maps<ArrowUpRightIcon class="size-3.5" aria-hidden="true" /><span class="ds-sr-only">, opens a new tab</span></a>
        </div>
    </section>
</template>

<style scoped>
.location-card{overflow:hidden;border:1px solid var(--border-subtle);border-radius:var(--radius-lg,16px);background:var(--surface-raised);padding:16px;text-align:left;font-size:.875rem;}
.location-eyebrow{margin:0 0 12px;color:var(--text-muted);font-size:.75rem;}
.location-heading{display:flex;align-items:flex-start;gap:12px;}
.location-pin{display:grid;place-items:center;flex-shrink:0;width:36px;height:36px;border-radius:12px;background:var(--surface-subtle);color:var(--action-primary);}
.location-pin svg{width:20px;height:20px;}
.location-heading h3{font-size:.875rem;font-weight:700;line-height:1.5;color:var(--text-strong);overflow-wrap:anywhere;}
.location-address{margin-top:4px;white-space:pre-line;line-height:1.65;color:var(--text-muted);overflow-wrap:anywhere;}
.location-unit{margin-top:4px;line-height:1.5;color:var(--text-default);}
.location-notes{margin-top:14px;border-top:1px solid var(--border-subtle);}
.location-notes summary{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;cursor:pointer;font-size:.8125rem;font-weight:600;color:var(--text-strong);list-style:none;}
.location-notes summary::-webkit-details-marker{display:none;}
.location-notes[open] summary svg{transform:rotate(180deg);}
.location-notes p{margin:0 0 12px;white-space:pre-line;line-height:1.65;color:var(--text-muted);overflow-wrap:anywhere;}
.location-notes strong{display:block;font-size:.75rem;color:var(--text-strong);}
.location-map-control{display:flex;align-items:center;gap:12px;width:100%;min-height:64px;margin-top:16px;border:1px solid var(--border-subtle);border-radius:12px;background:var(--surface-subtle);padding:10px 12px;text-align:left;color:var(--text-strong);}
.location-map-control:hover{border-color:var(--border-strong);}
.location-map-control:disabled{opacity:.65;}
.location-map-icon{display:grid;place-items:center;flex-shrink:0;width:32px;height:32px;color:var(--action-primary);}
.location-map-icon svg{width:26px;height:26px;}
.location-map-control strong{display:block;font-size:.8125rem;font-weight:600;}
.location-map-control span span{display:block;margin-top:2px;font-size:.6875rem;color:var(--text-muted);}
.location-map-region{margin-top:10px;}
.location-map{width:100%;height:224px;border-radius:10px;}
.location-status{margin-bottom:8px;font-size:.8125rem;line-height:1.5;color:var(--text-muted);}
.location-actions{display:flex;align-items:center;flex-wrap:wrap;gap:4px 12px;margin-top:12px;}
.location-directions{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;border:1px solid var(--border-default);border-radius:10px;background:var(--surface-raised);padding:8px 12px;font-size:.8125rem;font-weight:600;color:var(--action-primary);}
.location-directions:hover{background:var(--surface-subtle);}
.location-apple{display:inline-flex;align-items:center;gap:4px;min-height:44px;font-size:.75rem;color:var(--text-muted);text-decoration:underline;text-underline-offset:3px;}
button:focus-visible,a:focus-visible,summary:focus-visible{outline:3px solid var(--action-primary);outline-offset:3px;}
</style>
