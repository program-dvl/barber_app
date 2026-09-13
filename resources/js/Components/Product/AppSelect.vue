<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs, useId, watch } from 'vue';
import { CheckIcon, ChevronDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { nextEnabledOption, selectMenuPosition } from '@/Support/selectMenu';

// Keep the native option/value contract, including numeric/null values, form
// validation and change events. Pages continue to own their option sources.
defineOptions({ inheritAttrs: false });
const props = defineProps({
    modelValue: { default: undefined }, value: { default: undefined },
    multiple: Boolean, disabled: Boolean, required: Boolean,
    searchable: { type: Boolean, default: undefined },
});
const emit = defineEmits(['update:modelValue', 'change']);
const attrs = useAttrs();
const uid = useId();
const controlId = computed(() => attrs.id || `select-${uid}`);
const native = ref(null);
const trigger = ref(null);
const panel = ref(null);
const search = ref(null);
const ready = ref(false);
const opened = ref(false);
const query = ref('');
const options = ref([]);
const active = ref(-1);
const label = ref('Choose an option');
const invalid = ref(false);
const descriptionId = computed(() => [attrs['aria-describedby'], invalid.value ? `${controlId.value}-required` : null].filter(Boolean).join(' ') || undefined);
const selection = computed({
    get: () => props.modelValue !== undefined ? props.modelValue : props.value,
    set: value => emit('update:modelValue', value),
});
const filtered = computed(() => options.value.filter(option => option.label.toLocaleLowerCase().includes(query.value.toLocaleLowerCase())));
const canSearch = computed(() => props.searchable ?? options.value.length > 7);
const selectedLabel = computed(() => {
    const chosen = options.value.filter(option => option.selected);
    return chosen.length > 1 ? `${chosen.length} selected` : chosen[0]?.label || 'Choose an option';
});
const activeId = computed(() => opened.value && active.value >= 0 ? `${controlId.value}-option-${filtered.value[active.value]?.index}` : undefined);
let observer;
let associatedLabel;
let typeahead = '';
let typeaheadTimer;

function synchronize() {
    if (!native.value) return;
    const next = [...native.value.options].map((option, index) => ({
        index, label: option.textContent.trim(), selected: option.selected,
        disabled: option.disabled || option.parentElement?.disabled,
        group: option.parentElement?.tagName === 'OPTGROUP' ? option.parentElement.label : '',
    }));
    if (JSON.stringify(next) !== JSON.stringify(options.value)) options.value = next;
}
function position() {
    if (!trigger.value || !panel.value) return;
    const rect = trigger.value.getBoundingClientRect();
    const height = window.visualViewport?.height || window.innerHeight;
    const width = window.visualViewport?.width || window.innerWidth;
    const placement = selectMenuPosition(rect, { height, width });
    Object.assign(panel.value.style, {
        left: `${placement.left}px`, width: `${placement.width}px`,
        maxHeight: `${placement.maxHeight}px`,
        top: placement.top === null ? 'auto' : `${placement.top}px`,
        bottom: placement.bottom === null ? 'auto' : `${placement.bottom}px`,
    });
}
async function show(direction = 1) {
    if (props.disabled || opened.value) return;
    synchronize();
    query.value = '';
    opened.value = true;
    await nextTick();
    position();
    panel.value?.showPopover?.();
    active.value = filtered.value.findIndex(option => option.selected && !option.disabled);
    if (active.value < 0) active.value = direction > 0 ? filtered.value.findIndex(option => !option.disabled) : filtered.value.findLastIndex(option => !option.disabled);
    await nextTick();
    (canSearch.value ? search.value : trigger.value)?.focus();
    reveal();
}
function close(restore = false) {
    if (!opened.value) return;
    panel.value?.hidePopover?.();
    opened.value = false;
    if (restore) trigger.value?.focus();
}
function reveal() {
    nextTick(() => panel.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}
function move(direction) {
    active.value = nextEnabledOption(filtered.value, active.value, direction);
    reveal();
}
function choose(option) {
    if (!option || option.disabled || props.disabled) return;
    const item = native.value.options[option.index];
    if (props.multiple) item.selected = !item.selected;
    else native.value.selectedIndex = option.index;
    native.value.dispatchEvent(new Event('change', { bubbles: true }));
    synchronize();
    if (!props.multiple) close(true);
}
function changed(event) {
    synchronize();
    invalid.value = !native.value.validity.valid;
    emit('change', event);
}
function showInvalid() {
    invalid.value = true;
    trigger.value?.focus();
}
function keyboard(event) {
    if (event.key === 'Escape' && opened.value) {
        event.preventDefault(); event.stopPropagation(); close(true); return;
    }
    if (event.key === 'Tab' && opened.value) {
        event.preventDefault();
        const scope = trigger.value.closest('dialog') || document;
        const controls = [...scope.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex="0"]')]
            .filter(element => element.tabIndex >= 0 && element.getClientRects().length && !panel.value?.contains(element) && !element.closest('[inert]'));
        const index = controls.indexOf(trigger.value);
        close();
        controls[(index + (event.shiftKey ? -1 : 1) + controls.length) % controls.length]?.focus();
        return;
    }
    if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        if (!opened.value) show(event.key === 'ArrowDown' ? 1 : -1);
        else move(event.key === 'ArrowDown' ? 1 : -1);
    } else if (['Enter', ' '].includes(event.key) && event.target !== search.value) {
        event.preventDefault();
        opened.value ? choose(filtered.value[active.value]) : show();
    } else if (event.key === 'Enter' && opened.value) {
        event.preventDefault(); choose(filtered.value[active.value]);
    } else if (['Home', 'End'].includes(event.key) && opened.value && event.target !== search.value) {
        event.preventDefault();
        active.value = event.key === 'Home' ? filtered.value.findIndex(item => !item.disabled) : filtered.value.findLastIndex(item => !item.disabled);
        reveal();
    } else if (event.key.length === 1 && !event.metaKey && !event.ctrlKey && event.target !== search.value) {
        event.preventDefault();
        typeahead += event.key.toLocaleLowerCase();
        clearTimeout(typeaheadTimer);
        typeaheadTimer = setTimeout(() => { typeahead = ''; }, 600);
        const match = options.value.find(item => !item.disabled && item.label.toLocaleLowerCase().startsWith(typeahead));
        if (match) opened.value ? (active.value = filtered.value.findIndex(item => item.index === match.index), reveal()) : choose(match);
    }
}
function outside(event) {
    if (!panel.value?.contains(event.target) && !trigger.value?.contains(event.target)) close();
}
function scrolled(event) {
    if (opened.value && !panel.value?.contains(event.target)) close();
}
watch(query, () => { active.value = filtered.value.findIndex(item => !item.disabled); });
watch(() => [props.modelValue, props.value], () => nextTick(synchronize), { deep: true });
watch(() => props.disabled, disabled => { if (disabled) close(); });
onMounted(async () => {
    const parentLabel = native.value.closest('label');
    if (parentLabel) {
        label.value = [...parentLabel.childNodes].filter(node => node.nodeType === 3).map(node => node.textContent.trim()).join(' ').trim() || label.value;
        if (!parentLabel.htmlFor && !parentLabel.querySelector('input, textarea')) {
            parentLabel.htmlFor = controlId.value;
            associatedLabel = parentLabel;
        }
    }
    else label.value = document.querySelector(`label[for="${controlId.value}"]`)?.textContent.trim() || label.value;
    ready.value = true;
    await nextTick(); synchronize();
    observer = new MutationObserver(() => nextTick(synchronize));
    observer.observe(native.value, { childList: true, subtree: true, characterData: true, attributes: true });
    document.addEventListener('pointerdown', outside);
    document.addEventListener('scroll', scrolled, true);
    window.addEventListener('resize', position);
});
onBeforeUnmount(() => {
    observer?.disconnect(); clearTimeout(typeaheadTimer);
    if (associatedLabel?.htmlFor === controlId.value) associatedLabel.removeAttribute('for');
    document.removeEventListener('pointerdown', outside);
    document.removeEventListener('scroll', scrolled, true);
    window.removeEventListener('resize', position);
});
defineExpose({ focus: () => trigger.value?.focus() });
</script>

<template>
    <span :class="['cd-select', attrs.class]" :style="attrs.style">
        <select ref="native" v-model="selection" :id="ready ? `${controlId}-native` : controlId" :name="attrs.name" :multiple="multiple" :required="required" :disabled="disabled" :hidden="ready" :class="ready ? 'cd-select-native' : 'cd-input'" :aria-hidden="ready || undefined" :tabindex="ready ? -1 : undefined" @change="changed" @invalid.prevent="showInvalid"><slot /></select>
        <button v-if="ready" ref="trigger" :id="controlId" type="button" class="cd-select-trigger" role="combobox" aria-haspopup="listbox" :aria-label="attrs['aria-label'] || label" :aria-labelledby="attrs['aria-labelledby']" :aria-describedby="descriptionId" :aria-invalid="attrs['aria-invalid'] || invalid || undefined" :aria-required="required || undefined" :aria-expanded="opened" :aria-controls="`${controlId}-list`" :aria-activedescendant="!canSearch ? activeId : undefined" :disabled="disabled" @click="opened ? close() : show()" @keydown="keyboard">
            <span class="truncate">{{ selectedLabel }}</span><ChevronDownIcon class="size-4 shrink-0 text-[var(--text-muted)]" aria-hidden="true" />
        </button>
        <span v-if="invalid" :id="`${controlId}-required`" role="alert" class="mt-1 block text-xs text-[var(--status-danger)]">Choose an option to continue.</span>
        <div v-if="opened" ref="panel" popover="manual" class="cd-select-panel" @keydown="keyboard" @click.stop.prevent>
            <div v-if="canSearch" class="cd-select-search"><MagnifyingGlassIcon class="size-4 shrink-0" aria-hidden="true" /><input ref="search" v-model="query" :aria-label="`Search ${attrs['aria-label'] || label}`" role="combobox" aria-autocomplete="list" :aria-expanded="opened" :aria-controls="`${controlId}-list`" :aria-activedescendant="activeId" placeholder="Type to search…" autocomplete="off" /></div>
            <div :id="`${controlId}-list`" role="listbox" :aria-label="attrs['aria-label'] || label" :aria-multiselectable="multiple || undefined" class="cd-select-options">
                <div v-for="(option, index) in filtered" :id="`${controlId}-option-${option.index}`" :key="option.index" role="option" :aria-selected="option.selected" :aria-disabled="option.disabled || undefined" :data-active="active === index" class="cd-select-option" @pointermove="!option.disabled && (active = index)" @mousedown.prevent @click="choose(option)"><span class="min-w-0 flex-1"><small v-if="option.group" class="block text-xs text-[var(--text-muted)]">{{ option.group }}</small>{{ option.label }}</span><CheckIcon v-if="option.selected" class="size-4 shrink-0" aria-hidden="true" /></div>
            </div>
            <p v-if="!filtered.length" class="px-3 py-5 text-center text-sm text-[var(--text-muted)]" role="status">No matches. Try another search.</p>
            <button v-if="multiple" type="button" class="cd-select-done" @click="close(true)">Done · {{ options.filter(option => option.selected).length }} selected</button>
        </div>
    </span>
</template>
