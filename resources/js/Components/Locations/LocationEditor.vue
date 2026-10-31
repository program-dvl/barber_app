<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { coordinates, placeAddress } from '@/Support/locationIntelligence';
import { loadGoogleMaps, mapId, onGoogleMapsFailure } from '@/Support/googleMaps';
import AppSelect from '@/Components/Product/AppSelect.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import FieldError from '@/Components/Product/FieldError.vue';

const props = defineProps({ modelValue: { type: Object, required: true }, errors: { type: Object, default: () => ({}) }, optional: Boolean, countries: { type: Object, default: () => ({}) }, id: { type: String, default: 'location' } });
const emit = defineEmits(['update:modelValue']);
const host = ref(null), canvas = ref(null), loading = ref(false), error = ref(''), mapError = ref(''), searchUnavailable = ref(false), retrySearch = ref(false), mapsBlocked = ref(false), mapOpen = ref(false), pinNotice = ref('');
const entryMode = ref(props.modelValue.address && !props.modelValue.google_place_id ? 'manual' : 'search');
function chooseEntryMode(mode) {
    ++selectionVersion;
    loading.value = false;
    entryMode.value = mode;
    if (mode === 'manual' && !props.modelValue.address) {
        const draft = typeof widget?.value === 'string' ? widget.value.trim() : '';
        if (draft) manualAddress(draft);
    }
    nextTick(() => document.getElementById(mode === 'manual' ? `${props.id}-address` : `${props.id}-search`)?.focus());
}
let stopFailure;
let widget, map, marker, alive = true, selectedPoint, selectionVersion = 0, searchGeneration = 0;
const currentPoint = computed(() => coordinates(props.modelValue));
const update = values => emit('update:modelValue', { ...props.modelValue, ...values });
const fields = [ ['address_line_1', 'Street address'], ['city', 'City / locality'], ['state', 'State / province / region'], ['postal_code', 'Postal code'] ];

function searchFailed(canRetry = false) {
    ++selectionVersion;
    ++searchGeneration;
    searchUnavailable.value = true;
    retrySearch.value = canRetry;
    loading.value = false;
    widget?.setAttribute('disabled', '');
    error.value = 'You can enter your address manually and continue setup. Your other details are kept.';
}

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
    if (mapsBlocked.value || loading.value) return;
    mapOpen.value = true; loading.value = true; mapError.value = '';
    try {
        const maps = await loadGoogleMaps();
        if (!alive || mapsBlocked.value) return;
        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([maps.importLibrary('maps'), maps.importLibrary('marker')]);
        await nextTick();
        if (!alive || mapsBlocked.value || !mapOpen.value || !canvas.value) return;
        const point = currentPoint.value || selectedPoint || { lat: 20, lng: 0 };
        if (!map) {
            map = new Map(canvas.value, { center: point, zoom: currentPoint.value || selectedPoint ? 18 : 2, mapId: mapId(), mapTypeControl: false, streetViewControl: false, gestureHandling: 'cooperative' });
            marker = new AdvancedMarkerElement({ map, position: point, gmpDraggable: true, title: 'Customer entrance. Use arrow keys to position the pin.' });
            marker.addListener('dragend', () => savePin(marker.position));
            map.addListener('click', event => { if (event.latLng) { marker.map = map; marker.position = event.latLng; savePin(event.latLng); } });
        } else { marker.map = map; marker.position = point; map.setCenter(point); map.setZoom(currentPoint.value || selectedPoint ? 18 : 2); }
    } catch (failure) { if (alive) { mapsBlocked.value ||= ['MAPS_AUTHENTICATION_FAILED', 'MAPS_CONFIGURATION_MISSING'].includes(failure?.code); mapError.value = 'Your address and arrival notes are kept. You can save this location without an entrance pin.'; mapOpen.value = false; } }
    finally { if (alive) loading.value = false; }
}
async function startSearch() {
    if (mapsBlocked.value || loading.value || (searchUnavailable.value && !retrySearch.value)) return;
    const generation = ++searchGeneration;
    loading.value = true; error.value = '';
    try {
        const maps = await loadGoogleMaps();
        if (!alive || mapsBlocked.value || generation !== searchGeneration) return;
        const { PlaceAutocompleteElement } = await maps.importLibrary('places');
        if (!alive || mapsBlocked.value || generation !== searchGeneration || !host.value || widget) return;
        searchUnavailable.value = false; retrySearch.value = false;
        widget = new PlaceAutocompleteElement();
        widget.setAttribute('aria-label', 'Search your business name or address');
        widget.setAttribute('placeholder', 'Business name or street address');
        widget.style.width = '100%';
        // Google defaults to the device theme. This product uses a fixed light surface.
        // Use its supported host CSS properties; do not alter the widget's shadow tree.
        widget.style.colorScheme = 'light';
        widget.style.backgroundColor = 'var(--surface-raised)';
        widget.style.color = 'var(--text-default)';
        widget.style.fontFamily = 'inherit';
        widget.style.fontSize = '16px';
        widget.style.border = '0';
        widget.style.borderRadius = 'inherit';
        // The supported widget owns attribution, keyboard/touch interaction and session tokens.
        // A denied request does not tell us whether the cause is quota, billing or restrictions.
        widget.addEventListener('gmp-error', () => { if (alive) searchFailed(); });
        widget.addEventListener('gmp-select', async ({ placePrediction }) => {
            if (searchUnavailable.value || entryMode.value !== 'search') return;
            const version = ++selectionVersion; loading.value = true; error.value = '';
            try {
                const place = placePrediction.toPlace();
                await place.fetchFields({ fields: ['id', 'formattedAddress', 'addressComponents', 'location'] });
                if (!alive || version !== selectionVersion) return;
                selectedPoint = place.location ? { lat: place.location.lat(), lng: place.location.lng() } : null;
                const samePlace = place.id === props.modelValue.google_place_id && currentPoint.value;
                update({ ...placeAddress(place), ...(samePlace ? { entrance_latitude: props.modelValue.entrance_latitude, entrance_longitude: props.modelValue.entrance_longitude } : {}) });
                pinNotice.value = samePlace ? 'Check your address and saved entrance before saving.' : 'Address selected. You can add an entrance pin if clients need help finding you.';
                entryMode.value = 'search';
                mapOpen.value = false;
                if (marker) marker.map = null;
            } catch { if (alive && version === selectionVersion) { error.value = 'That address could not be opened. Choose another result or enter the address manually. Your other details are kept.'; retrySearch.value = false; } }
            finally { if (alive && version === selectionVersion) loading.value = false; }
        });
        host.value.replaceChildren(widget);
    } catch (failure) { if (alive && generation === searchGeneration) { mapsBlocked.value = ['MAPS_AUTHENTICATION_FAILED', 'MAPS_CONFIGURATION_MISSING'].includes(failure?.code); searchFailed(!mapsBlocked.value); } }
    finally { if (alive && generation === searchGeneration) loading.value = false; }
}
watch(currentPoint, point => { if (map && marker && point) { marker.map = map; marker.position = point; } });
watch(() => props.id, () => { ++selectionVersion; selectedPoint = null; pinNotice.value = ''; loading.value = false; mapOpen.value = false; entryMode.value = props.modelValue.address && !props.modelValue.google_place_id ? 'manual' : 'search'; if (marker) marker.map = null; });
onMounted(() => { stopFailure = onGoogleMapsFailure(() => { mapsBlocked.value = true; searchFailed(); if (mapOpen.value) mapError.value = 'Your address and arrival notes are kept. You can save this location without an entrance pin.'; mapOpen.value = false; }); startSearch(); });
onBeforeUnmount(() => { alive = false; ++selectionVersion; ++searchGeneration; stopFailure?.(); widget?.remove(); if (marker) marker.map = null; });
</script>

<template>
    <div class="space-y-4">
        <fieldset>
            <legend class="text-sm font-semibold">Business address <span v-if="optional" class="font-normal text-[var(--text-muted)]">· add now or before going online</span></legend>
            <p class="mt-1 text-sm text-[var(--text-muted)]">Search for your business or enter its address. This is the address clients will see.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <label v-for="option in [{value:'search',label:'Search Google Maps'},{value:'manual',label:'Enter address manually'}]" :key="option.value" :class="['inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-semibold', entryMode === option.value ? 'border-[var(--action-primary)] bg-[var(--status-info-soft)]' : 'border-[var(--border-subtle)]']">
                    <input type="radio" :name="`${id}-address-mode`" :value="option.value" :checked="entryMode === option.value" @change="chooseEntryMode(option.value)" />{{ option.label }}
                </label>
            </div>
        </fieldset>
        <div v-show="entryMode === 'search'">
            <p :id="`${id}-search-label`" class="mb-2 text-sm font-semibold">Find your location</p>
            <div :id="`${id}-search`" ref="host" tabindex="-1" :aria-labelledby="`${id}-search-label`" :aria-disabled="searchUnavailable || undefined" class="cd-location-search min-h-11 rounded-lg border border-[var(--border-strong)] bg-[var(--surface-raised)] focus-within:ring-2 focus-within:ring-[var(--action-primary)]" />
            <p v-if="loading" class="mt-2 text-sm text-[var(--text-muted)]" role="status">Loading Google Maps…</p>
            <AppNotice v-if="error" class="mt-4" :title="searchUnavailable ? 'Address search is unavailable' : 'Choose another address'" :description="error"><template #actions><AppButton variant="secondary" @click="chooseEntryMode('manual')">Enter address manually</AppButton><AppButton v-if="retrySearch && !widget" variant="quiet" :disabled="loading" @click="startSearch">Try search again</AppButton></template></AppNotice>
        </div>
        <div v-show="entryMode === 'manual' || !!errors.address">
            <label :for="`${id}-address`" class="block text-sm font-semibold">Full business address
                <textarea :id="`${id}-address`" :value="modelValue.address" :required="!optional && entryMode === 'manual'" maxlength="1000" rows="2" placeholder="Street, city, state or province, postal code" class="cd-input mt-2" :aria-invalid="!!errors.address" :aria-describedby="`${id}-address-help ${id}-address-error`" @input="manualAddress($event.target.value)" />
            </label>
            <p :id="`${id}-address-help`" class="mt-2 text-xs leading-5 text-[var(--text-muted)]">Enter the address as you want clients to see it. A Google listing is not required.</p>
        </div>
        <div v-if="entryMode === 'search' && modelValue.address" class="rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-4" role="status">
            <p class="text-sm font-semibold">Selected address</p><p class="mt-1 text-sm">{{ modelValue.address }}</p>
            <AppButton variant="quiet" size="small" class="mt-2" @click="chooseEntryMode('manual')">Edit this address</AppButton>
        </div>
        <FieldError :id="`${id}-address-error`" :message="errors.address" />

        <AppButton v-if="modelValue.address && !mapOpen && !mapsBlocked" variant="secondary" @click="showMap">{{ currentPoint ? 'Adjust customer entrance' : 'Add an entrance pin · optional' }}</AppButton>
        <AppNotice v-if="mapError && (entryMode !== 'search' || !searchUnavailable)" title="The entrance map is unavailable" :description="mapError" />
        <div v-show="mapOpen"><div ref="canvas" class="h-72 w-full rounded-xl" aria-label="Position your customer entrance" /><p class="mt-2 text-xs leading-5 text-[var(--text-muted)]">Click your entrance or move the pin. Add arrival notes if the entrance is hard to find.</p></div>
        <p v-if="mapOpen" class="text-xs text-[var(--text-muted)]">Keyboard: focus the pin, press Alt/Option + Enter, move with arrow keys, then press Enter.</p>
        <AppButton v-if="mapOpen && currentPoint" variant="quiet" @click="update({ entrance_latitude: '', entrance_longitude: '' }); selectedPoint = null; marker && (marker.map = null); pinNotice = 'Entrance pin removed. Save your location to keep this change.'">Remove entrance pin</AppButton>
        <FieldError :message="errors.entrance_latitude || errors.entrance_longitude ? 'The entrance pin could not be saved. Choose it again on the map.' : ''" />
        <p v-if="pinNotice" role="status" class="text-sm text-[var(--text-muted)]">{{ pinNotice }}</p>

        <details v-show="modelValue.address" class="rounded-lg border border-[var(--border-subtle)] p-3" :open="['address_line_2', 'entrance_instructions', 'parking_instructions'].some(key => errors[key])"><summary class="cursor-pointer text-sm font-semibold">Add unit, parking or arrival notes <span class="font-normal text-[var(--text-muted)]">· optional</span></summary><div class="mt-3 space-y-3"><label :for="`${id}-address_line_2`" class="block text-sm font-semibold">Unit, suite, floor or building<input :id="`${id}-address_line_2`" :value="modelValue.address_line_2" class="cd-input mt-2" maxlength="255" placeholder="e.g. Suite 2, second floor" @input="update({ address_line_2: $event.target.value })" /><FieldError :message="errors.address_line_2" /></label>        <div class="grid gap-3 sm:grid-cols-2"><label v-for="[key, label] in [['entrance_instructions', 'Arrival / entrance instructions'], ['parking_instructions', 'Parking instructions']]" :key="key" :for="`${id}-${key}`" class="text-sm font-semibold">{{ label }}<textarea :id="`${id}-${key}`" :value="modelValue[key]" rows="2" maxlength="1000" class="cd-input mt-2" :aria-invalid="!!errors[key]" :aria-describedby="`${id}-${key}-error`" @input="update({ [key]: $event.target.value })" /><FieldError :id="`${id}-${key}-error`" :message="errors[key]" /></label></div></div></details>
        <details v-show="modelValue.address" class="rounded-lg border border-[var(--border-subtle)] p-3" :open="['address_line_1', 'city', 'state', 'postal_code', 'country_code'].some(key => errors[key])"><summary class="min-h-7 cursor-pointer text-sm font-semibold">Street, city or postal code corrections <span class="font-normal text-[var(--text-muted)]">· optional</span></summary><div class="mt-3 grid gap-3 sm:grid-cols-2"><label v-for="[key, label] in fields" :key="key" :for="`${id}-${key}`" class="text-sm">{{ label }}<input :id="`${id}-${key}`" :value="modelValue[key]" class="cd-input mt-1" :maxlength="key === 'address_line_1' ? 255 : key === 'postal_code' ? 32 : 150" :aria-invalid="!!errors[key]" :aria-describedby="`${id}-${key}-error`" @input="structuredAddress(key, $event.target.value)" /><FieldError :id="`${id}-${key}-error`" :message="errors[key]" /></label><label :for="`${id}-country_code`" class="text-sm">Country<AppSelect :id="`${id}-country_code`" :model-value="modelValue.country_code" class="cd-input mt-1" :aria-invalid="!!errors.country_code" @update:model-value="structuredAddress('country_code', $event)"><option value="">Choose country</option><option v-for="(name, code) in countries" :key="code" :value="code">{{ name }}</option></AppSelect><FieldError :message="errors.country_code" /></label></div></details>

        <p class="text-xs text-[var(--text-muted)]">Google Maps features are subject to <a href="https://maps.google.com/help/terms_maps/" target="_blank" rel="noopener noreferrer" class="underline">Google Maps terms</a> and <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="underline">Google Privacy Policy</a>.</p>
    </div>
</template>

<style scoped>
/* Google's predictions extend below this field; clipping belongs to neither
   the field wrapper nor the card that contains it. Keep these shared field
   styles here so signup, guided setup and Locations render the same widget. */
.cd-location-search { position:relative; overflow:visible; color-scheme:light; color:var(--text-default); }
.cd-location-search:focus-within { z-index:20; }
.cd-location-search :deep(gmp-place-autocomplete) { display:block; width:100%; min-height:44px; border:0; border-radius:inherit; box-shadow:none; color-scheme:light; background-color:var(--surface-raised); color:var(--text-default); }
</style>
