<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { discoverBranches } from '@/Support/locationIntelligence';
import AppButton from '@/Components/Product/AppButton.vue';
const props = defineProps({ branches: { type: Array, required: true }, modelValue: String });
const emit = defineEmits(['update:modelValue']);
const query = ref(''), origin = ref(null), locating = ref(false), message = ref('');
const branches = computed(() => discoverBranches(props.branches, query.value, origin.value));
let alive = true;
function locate() {
    if (!navigator.geolocation) { message.value = 'Your browser cannot share location. Search by city or postal code instead.'; return; }
    locating.value = true; message.value = '';
    navigator.geolocation.getCurrentPosition(position => {
        if (!alive) return;
        origin.value = { lat: position.coords.latitude, lng: position.coords.longitude };
        locating.value = false; message.value = 'Branches sorted by straight-line distance. Your location stays on this page.';
    }, () => { if (alive) { locating.value = false; message.value = 'Location could not be shared. You can still choose any branch.'; } }, { timeout: 10000, maximumAge: 60000, enableHighAccuracy: false });
}
onBeforeUnmount(() => { alive = false; origin.value = null; });
</script>

<template>
    <fieldset><legend class="font-semibold">Choose your location</legend>
        <div class="mt-3 flex flex-wrap items-end gap-2"><label class="min-w-0 flex-1 text-sm" for="branch-search">Search branches<input id="branch-search" v-model="query" class="cd-input mt-1" type="search" placeholder="Branch, city or postal code" /></label><AppButton variant="secondary" :disabled="locating" @click="locate">{{ locating ? 'Locating…' : 'Use my location' }}</AppButton><AppButton v-if="origin" variant="quiet" @click="origin = null; message = ''">Clear nearby sort</AppButton></div>
        <p v-if="message" class="mt-2 text-xs text-[var(--text-muted)]" role="status">{{ message }}</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-2"><label v-for="branch in branches" :key="branch.public_id" :class="['flex min-h-20 cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm', modelValue === branch.public_id ? 'border-[var(--action-primary)] bg-[var(--surface-subtle)]' : 'border-[var(--border-subtle)]']"><input type="radio" name="booking-branch" class="mt-1" :value="branch.public_id" :checked="modelValue === branch.public_id" @change="emit('update:modelValue', branch.public_id)" /><span class="min-w-0"><strong class="block">{{ branch.name }}</strong><span class="mt-1 block text-[var(--text-muted)]">{{ branch.address }}</span><span v-if="branch.address_line_2" class="block text-[var(--text-muted)]">{{ branch.address_line_2 }}</span><span v-if="branch.distance !== null" class="mt-1 block text-xs">{{ branch.distance.toFixed(1) }} km away · straight line</span></span></label></div>
        <p v-if="!branches.length" class="py-4 text-sm text-[var(--text-muted)]" role="status">No matching branches. Try another name, city or postal code.</p>
    </fieldset>
</template>
