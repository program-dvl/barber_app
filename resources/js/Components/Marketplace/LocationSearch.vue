<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { loadGoogleMaps, onGoogleMapsFailure } from '@/Support/googleMaps';
const emit = defineEmits(['select']);
const host = ref(null), loading = ref(true), error = ref('');
let widget, alive = true, stopFailure, generation = 0;
const fail = () => { if (alive) { loading.value = false; error.value = 'Location suggestions are unavailable. Enter a city above or use your current location.'; widget?.remove(); widget = null; } };
onMounted(async () => {
    stopFailure = onGoogleMapsFailure(fail);
    try {
        const maps = await loadGoogleMaps();
        const { PlaceAutocompleteElement } = await maps.importLibrary('places');
        if (!alive || error.value) return;
        widget = new PlaceAutocompleteElement();
        widget.setAttribute('aria-label', 'Search a city, neighbourhood or address');
        widget.setAttribute('placeholder', 'Search any city or address');
        widget.style.cssText = 'width:100%;color-scheme:light;background:white;color:#202d29;font:inherit;border-radius:10px;';
        widget.addEventListener('gmp-error', fail);
        widget.addEventListener('gmp-select', async ({ placePrediction }) => {
            const version = ++generation;
            try {
                const place = placePrediction.toPlace();
                await place.fetchFields({ fields: ['location'] });
                if (alive && version === generation && !error.value && place.location) emit('select', { lat: place.location.lat(), lng: place.location.lng() });
            } catch { fail(); }
        });
        host.value.replaceChildren(widget);
    } catch { fail(); }
    finally { if (alive) loading.value = false; }
});
onBeforeUnmount(() => { alive = false; generation++; stopFailure?.(); widget?.remove(); });
</script>
<template><div class="place-search"><p v-if="loading" role="status">Loading location suggestions…</p><div ref="host" /><p v-if="error" role="status">{{ error }}</p></div></template>
<style scoped>.place-search{margin:14px 0 6px;overflow:visible;position:relative;z-index:25}.place-search p{font-size:12px;line-height:1.7;color:#62705d}.place-search>div{min-width:0}</style>
