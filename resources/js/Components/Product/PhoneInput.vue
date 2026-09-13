<script setup>
import AppSelect from '@/Components/Product/AppSelect.vue';
import { computed, ref, watch } from 'vue';
import { getCountries, getCountryCallingCode, parsePhoneNumberFromString } from 'libphonenumber-js/max';

const props = defineProps({
    modelValue: { type: String, default: '' },
    country: { type: String, default: 'IN' },
    countries: { type: Object, default: () => ({}) },
    id: { type: String, required: true },
    required: Boolean,
    autocomplete: { type: String, default: 'tel' },
});
const emit = defineEmits(['update:modelValue', 'validity']);
const supported = new Set(getCountries());
const regionNames = new Intl.DisplayNames([typeof navigator === 'undefined' ? 'en' : (navigator.language || 'en')], { type: 'region' });
const countryOptions = computed(() => {
    const entries = Object.keys(props.countries).length
        ? Object.entries(props.countries)
        : getCountries().map(code => [code, regionNames.of(code) || code]);

    return entries
        .filter(([code]) => supported.has(code))
        .map(([code, name]) => ({ code, name, dial: getCountryCallingCode(code) }))
        .sort((left, right) => left.name.localeCompare(right.name));
});
const inferred = () => {
    try { return parsePhoneNumberFromString(props.modelValue)?.country || null; } catch { return null; }
};
const selectedCountry = ref(inferred() || (supported.has(props.country) ? props.country : 'IN'));
const localNumber = ref('');
const touched = ref(false);

const synchronize = value => {
    if (!value) { localNumber.value = ''; return; }
    try {
        const parsed = parsePhoneNumberFromString(value);
        if (parsed?.country) selectedCountry.value = parsed.country;
        localNumber.value = parsed?.nationalNumber || String(value).replace(/\D/g, '');
    } catch {
        localNumber.value = String(value).replace(/\D/g, '');
    }
};
const publish = () => {
    const digits = localNumber.value.replace(/\D/g, '');
    const parsed = digits ? parsePhoneNumberFromString(digits, selectedCountry.value) : null;
    const value = parsed?.number || (digits ? `+${getCountryCallingCode(selectedCountry.value)}${digits}` : '');
    emit('update:modelValue', value);
    emit('validity', Boolean(parsed?.isValid()));
};
const valid = computed(() => {
    if (!localNumber.value) return !props.required;
    try { return Boolean(parsePhoneNumberFromString(localNumber.value, selectedCountry.value)?.isValid()); } catch { return false; }
});

watch(() => props.modelValue, value => {
    const current = localNumber.value ? `+${getCountryCallingCode(selectedCountry.value)}${localNumber.value.replace(/\D/g, '')}` : '';
    if (value !== current) synchronize(value);
}, { immediate: true });
watch(() => props.country, value => {
    if (!props.modelValue && supported.has(value)) selectedCountry.value = value;
});
watch(selectedCountry, publish);
</script>

<template>
    <div>
        <div class="cd-phone-control grid grid-cols-[minmax(7.5rem,0.42fr)_minmax(0,1fr)] rounded-lg border border-[var(--border-default)] bg-[var(--surface-raised)] focus-within:border-[var(--action-primary)] focus-within:ring-2 focus-within:ring-[var(--focus-ring)]/20">
            <label :for="`${id}-country`" class="ds-sr-only">Phone country</label>
            <AppSelect :id="`${id}-country`" v-model="selectedCountry" class="min-h-11 min-w-0 border-0 border-r border-[var(--border-subtle)] bg-[var(--surface-subtle)] px-3 text-sm font-medium focus:ring-0" :aria-label="`Country code, currently +${getCountryCallingCode(selectedCountry)}`">
                <option v-for="option in countryOptions" :key="option.code" :value="option.code">{{ option.name }} (+{{ option.dial }})</option>
            </AppSelect>
            <input :id="id" v-model="localNumber" type="tel" inputmode="tel" :autocomplete="autocomplete" :required="required" class="min-h-11 min-w-0 border-0 bg-transparent px-3 focus:ring-0" placeholder="Local phone number" @input="publish" @blur="touched = true">
        </div>
        <p v-if="touched && !valid" class="mt-1.5 text-xs text-[var(--status-danger)]" role="alert">Enter a valid phone number for the selected country.</p>
    </div>
</template>
