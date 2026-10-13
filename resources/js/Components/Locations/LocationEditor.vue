<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { coordinates, placeAddress } from '@/Support/locationIntelligence';
import { loadGoogleMaps, mapId, onGoogleMapsFailure } from '@/Support/googleMaps';
import AppSelect from '@/Components/Product/AppSelect.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import FieldError from '@/Components/Product/FieldError.vue';

const props = defineProps({ modelValue: { type: Object, required: true }, errors: { type: Object, default: () => ({}) }, optional: Boolean, countries: { type: Object, default: () => ({}) }, id: { type: String, default: 'location' } });
const emit = defineEmits(['update:modelValue']);
const host = ref(null), canvas = ref(null), loading = ref(false), error = ref(''), mapOpen = ref(false), pinNotice = ref('');
let stopFailure;
let widget, map, marker, alive = true, selectedPoint, selectionVersion = 0;
const currentPoint = computed(() => coordinates(props.modelValue));
const update = values => emit('update:modelValue', { ...props.modelValue, ...values });
const fields = [ ['address_line_1', 'Street address'], ['city', 'City / locality'], ['state', 'State / province / region'], ['postal_code', 'Postal code'] ];

function manualAddress(value) {
    ++selectionVersion; selectedPoint = null; pinNotice.value = currentPoint.value ? 'Address updated. Check your saved entrance pin before saving.' : ''; loading.value = false;
    if (marker) marker.map = null;
    mapOpen.value = false;
    update({ address: value, google_place_id: '', address_line_1: '', city: '', state: '', postal_code: '' });
}
function structuredAddress(key, value) {
    if (key === 'address_line_2') return update({ [key]: value });
    const next = { ...props.modelValue, [key]: key === 'country_code' ? value.toUpperCase() : value };
    manualAddress([next.address_line_1, next.city, next.state, next.postal_code, props.countries[next.country_code] || next.country_code].filter(Boolean).join(', '));
    update({ ...next, address: [next.address_line_1, next.city, next.state, next.postal_code, props.countries[next.country_code] || next.country_code].filter(Boolean).join(', '), google_place_id: '' });
}
function savePin(position) {
    const point = { lat: typeof position.lat === 'function' ? position.lat() : position.lat, lng: typeof position.lng === 'function' ? position.lng() : position.lng };
    update({ entrance_latitude: Number(point.lat.toFixed(7)), entrance_longitude: Number(point.lng.toFixed(7)) });
    pinNotice.value = 'Entrance pin set. Save your location to keep it.';
}
async function showMap() {
    mapOpen.value = true; loading.value = true; error.value = '';
    try {
        const maps = await loadGoogleMaps();
        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([maps.importLibrary('maps'), maps.importLibrary('marker')]);
        await nextTick();
        if (!alive || !canvas.value) return;
        const point = currentPoint.value || selectedPoint || { lat: 20, lng: 0 };
        if (!map) {
            map = new Map(canvas.value, { center: point, zoom: currentPoint.value || selectedPoint ? 18 : 2, mapId: mapId(), mapTypeControl: false, streetViewControl: false, gestureHandling: 'cooperative' });
            marker = new AdvancedMarkerElement({ map, position: point, gmpDraggable: true, title: 'Customer entrance. Use arrow keys to position the pin.' });
            marker.addListener('dragend', () => savePin(marker.position));
            map.addListener('click', event => { if (event.latLng) { marker.map = map; marker.position = event.latLng; savePin(event.latLng); } });
        } else { marker.map = map; marker.position = point; map.setCenter(point); map.setZoom(currentPoint.value || selectedPoint ? 18 : 2); }
    } catch { if (alive) error.value = 'Map unavailable. You can still save your address and arrival notes. Try the map again later.'; }
    finally { if (alive) loading.value = false; }
}
async function startSearch() {
    loading.value = true; error.value = '';
    try {
        const maps = await loadGoogleMaps();
        const { PlaceAutocompleteElement } = await maps.importLibrary('places');
        if (!alive || !host.value || widget) return;
        widget = new PlaceAutocompleteElement();
        widget.setAttribute('aria-label', 'Search your business name or address');
        widget.setAttribute('placeholder', 'Business name or street address');
        widget.style.width = '100%';
        // The supported widget owns attribution, keyboard/touch interaction and session tokens.
        widget.addEventListener('gmp-error', () => { if (alive) error.value = 'Google search is unavailable or has reached its limit. Enter the address below.'; });
        widget.addEventListener('gmp-select', async ({ placePrediction }) => {
            const version = ++selectionVersion; loading.value = true; error.value = '';
            try {
                const place = placePrediction.toPlace();
                await place.fetchFields({ fields: ['id', 'formattedAddress', 'addressComponents', 'location'] });
                if (!alive || version !== selectionVersion) return;
                selectedPoint = place.location ? { lat: place.location.lat(), lng: place.location.lng() } : null;
                const samePlace = place.id === props.modelValue.google_place_id && currentPoint.value;
                update({ ...placeAddress(place), ...(samePlace ? { entrance_latitude: props.modelValue.entrance_latitude, entrance_longitude: props.modelValue.entrance_longitude } : {}) });
                pinNotice.value = samePlace ? 'Check your address and saved entrance before saving.' : 'Review your address. Click or move the pin to mark your customer entrance.';
                if (selectedPoint) await showMap();
            } catch { if (alive && version === selectionVersion) error.value = 'That address could not load. Try another result or enter it manually.'; }
            finally { if (alive && version === selectionVersion) loading.value = false; }
        });
        host.value.replaceChildren(widget);
    } catch (failure) { if (alive) error.value = failure.message; }
    finally { if (alive) loading.value = false; }
}
watch(currentPoint, point => { if (map && marker && point) { marker.map = map; marker.position = point; } });
watch(() => props.id, () => { ++selectionVersion; selectedPoint = null; pinNotice.value = ''; loading.value = false; mapOpen.value = false; if (marker) marker.map = null; });
onMounted(() => { stopFailure = onGoogleMapsFailure(() => { loading.value = false; error.value = 'Google Maps is unavailable. Enter your address here and try the map again later.'; mapOpen.value = false; }); startSearch(); });
onBeforeUnmount(() => { alive = false; ++selectionVersion; stopFailure?.(); widget?.remove(); if (marker) marker.map = null; });
</script>

<template>
    <div class="space-y-4">
        <div><p class="mb-2 text-sm font-semibold">Find your location</p><div ref="host" /><p v-if="loading" class="mt-2 text-sm text-[var(--text-muted)]" role="status">Loading Google Maps…</p><p v-if="error" class="mt-2 text-sm text-[var(--text-muted)]" role="status">{{ error }}</p><AppButton v-if="error && !widget" variant="quiet" @click="startSearch">Try Google search again</AppButton></div>
        <label :for="`${id}-address`" class="block text-sm font-semibold">Public address <span v-if="optional" class="font-normal text-[var(--text-muted)]">· optional until online publication</span>
            <textarea :id="`${id}-address`" :value="modelValue.address" :required="!optional" maxlength="1000" rows="2" class="cd-input mt-2" :aria-invalid="!!errors.address" :aria-describedby="`${id}-address-help ${id}-address-error`" @input="manualAddress($event.target.value)" />
        </label>
        <p :id="`${id}-address-help`" class="text-xs leading-5 text-[var(--text-muted)]">Check your address before saving. Not listed? Enter it here.</p>
        <FieldError :id="`${id}-address-error`" :message="errors.address" />

        <AppButton v-if="!mapOpen" variant="secondary" @click="showMap">{{ currentPoint ? 'Adjust entrance pin' : 'Set customer entrance on map' }}</AppButton>
        <div v-show="mapOpen"><div ref="canvas" class="h-72 w-full rounded-xl" aria-label="Position your customer entrance" /><p class="mt-2 text-xs leading-5 text-[var(--text-muted)]">Click your entrance or move the pin. Add arrival notes if the entrance is hard to find.</p></div>
        <p v-if="mapOpen" class="text-xs text-[var(--text-muted)]">Keyboard: focus the pin, press Alt/Option + Enter, move with arrow keys, then press Enter.</p>
        <AppButton v-if="mapOpen && currentPoint" variant="quiet" @click="update({ entrance_latitude: '', entrance_longitude: '' }); selectedPoint = null; marker && (marker.map = null); pinNotice = 'Entrance pin removed. Save your location to keep this change.'">Remove entrance pin</AppButton>
        <FieldError :message="errors.entrance_latitude || errors.entrance_longitude ? 'The entrance pin could not be saved. Choose it again on the map.' : ''" />
        <p v-if="pinNotice" role="status" class="text-sm text-[var(--text-muted)]">{{ pinNotice }}</p>

        <details class="rounded-lg border border-[var(--border-subtle)] p-3" :open="['address_line_2', 'entrance_instructions', 'parking_instructions'].some(key => errors[key])"><summary class="cursor-pointer text-sm font-semibold">Add unit, parking or arrival notes <span class="font-normal text-[var(--text-muted)]">· optional</span></summary><div class="mt-3 space-y-3"><label :for="`${id}-address_line_2`" class="block text-sm font-semibold">Unit, suite, floor or building<input :id="`${id}-address_line_2`" :value="modelValue.address_line_2" class="cd-input mt-2" maxlength="255" placeholder="e.g. Suite 2, second floor" @input="update({ address_line_2: $event.target.value })" /><FieldError :message="errors.address_line_2" /></label>        <div class="grid gap-3 sm:grid-cols-2"><label v-for="[key, label] in [['entrance_instructions', 'Arrival / entrance instructions'], ['parking_instructions', 'Parking instructions']]" :key="key" :for="`${id}-${key}`" class="text-sm font-semibold">{{ label }}<textarea :id="`${id}-${key}`" :value="modelValue[key]" rows="2" maxlength="1000" class="cd-input mt-2" :aria-invalid="!!errors[key]" :aria-describedby="`${id}-${key}-error`" @input="update({ [key]: $event.target.value })" /><FieldError :id="`${id}-${key}-error`" :message="errors[key]" /></label></div></div></details>
        <details class="rounded-lg border border-[var(--border-subtle)] p-3" :open="['address_line_1', 'city', 'state', 'postal_code', 'country_code'].some(key => errors[key])"><summary class="min-h-7 cursor-pointer text-sm font-semibold">Edit address details</summary><div class="mt-3 grid gap-3 sm:grid-cols-2"><label v-for="[key, label] in fields" :key="key" :for="`${id}-${key}`" class="text-sm">{{ label }}<input :id="`${id}-${key}`" :value="modelValue[key]" class="cd-input mt-1" :maxlength="key === 'address_line_1' ? 255 : key === 'postal_code' ? 32 : 150" :aria-invalid="!!errors[key]" :aria-describedby="`${id}-${key}-error`" @input="structuredAddress(key, $event.target.value)" /><FieldError :id="`${id}-${key}-error`" :message="errors[key]" /></label><label :for="`${id}-country_code`" class="text-sm">Country<AppSelect :id="`${id}-country_code`" :model-value="modelValue.country_code" class="cd-input mt-1" :aria-invalid="!!errors.country_code" @update:model-value="structuredAddress('country_code', $event)"><option value="">Choose country</option><option v-for="(name, code) in countries" :key="code" :value="code">{{ name }}</option></AppSelect><FieldError :message="errors.country_code" /></label></div></details>

        <p class="text-xs text-[var(--text-muted)]">Google Maps features are subject to <a href="https://maps.google.com/help/terms_maps/" target="_blank" rel="noopener noreferrer" class="underline">Google Maps terms</a> and <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="underline">Google Privacy Policy</a>.</p>
    </div>
</template>
