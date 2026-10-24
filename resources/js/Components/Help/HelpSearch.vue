<script setup>
import AppNotice from '@/Components/Product/AppNotice.vue';
import { MagnifyingGlassIcon, ArrowUpRightIcon } from '@heroicons/vue/24/outline';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({ initialQuery: { type: String, default: '' }, compact: Boolean });
const query = ref(props.initialQuery), suggestions = ref([]), expanded = ref(false), loading = ref(false);
const failure = ref(false), cooldown = ref(0), active = ref(-1), input = ref(null);
let timer, countdown, controller, sequence = 0, suggestionsPaused = false;
const inputId = `help-search-${props.compact ? 'compact' : 'main'}`;
watch(() => props.initialQuery, value => { query.value = value; });
watch(query, value => {
    clearTimeout(timer);
    controller?.abort();
    const request = ++sequence;
    suggestions.value = [];
    expanded.value = false;
    active.value = -1;
    loading.value = false;
    if (suggestionsPaused || cooldown.value || value.trim().length < 2) return;
    failure.value = false;
    timer = setTimeout(async () => {
        controller = new AbortController();
        loading.value = true;
        try {
            const response = await fetch(`/help/search?q=${encodeURIComponent(value.trim().slice(0, 160))}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (response.status === 429) {
                const header = response.headers.get('Retry-After');
                const seconds = Number(header);
                const delay = header && Number.isFinite(seconds) ? seconds : (Date.parse(header || '') - Date.now()) / 1000;
                cooldown.value = Math.max(1, Math.ceil(Number.isFinite(delay) ? delay : 60));
                const until = Date.now() + cooldown.value * 1000;
                clearInterval(countdown);
                countdown = setInterval(() => { cooldown.value = Math.max(0, Math.ceil((until - Date.now()) / 1000)); if (!cooldown.value) { clearInterval(countdown); failure.value = false; } }, 1000);
                failure.value = true;
                return;
            }
            if (!response.ok) throw new Error('unavailable');
            const data = await response.json();
            if (request !== sequence) return;
            suggestions.value = data.results ?? [];
            expanded.value = true;
        } catch (error) {
            if (error.name !== 'AbortError' && request === sequence) { failure.value = true; suggestionsPaused = true; }
        } finally { if (request === sequence) loading.value = false; }
    }, 250);
});
const keydown = event => {
    if (event.key === 'Escape') { expanded.value = false; active.value = -1; }
    if (!expanded.value || !suggestions.value.length) return;
    if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        active.value = active.value < 0 ? (event.key === 'ArrowDown' ? 0 : suggestions.value.length - 1) : (active.value + (event.key === 'ArrowDown' ? 1 : -1) + suggestions.value.length) % suggestions.value.length;
        nextTick(() => document.getElementById(`${inputId}-option-${active.value}`)?.scrollIntoView({ block: 'nearest' }));
    }
    if (event.key === 'Enter' && active.value >= 0) { event.preventDefault(); window.location.assign(suggestions.value[active.value].url || `/help/articles/${suggestions.value[active.value].slug}`); }
};
onBeforeUnmount(() => { clearTimeout(timer); clearInterval(countdown); controller?.abort(); sequence++; });
</script>

<template>
    <div class="help-search" @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) expanded = false; }">
        <form action="/help" method="get" role="search" class="help-search-form">
            <label :for="inputId" class="ds-sr-only">Search the Help Center</label>
            <MagnifyingGlassIcon class="size-6 shrink-0" aria-hidden="true" />
            <input :id="inputId" ref="input" v-model="query" name="q" type="search" maxlength="160" autocomplete="off" placeholder="Ask a question, or search a topic…" role="combobox" :aria-expanded="expanded" :aria-controls="`${inputId}-results`" aria-autocomplete="list" :aria-activedescendant="active >= 0 ? `${inputId}-option-${active}` : undefined" @keydown="keydown" @focus="expanded = suggestions.length > 0" />
            <button class="cd-button cd-button-primary" type="submit">Search</button>
        </form>
        <p v-if="loading" role="status" class="help-search-status">Finding matching guides…</p>
        <div v-if="expanded" class="help-suggestions">
            <ul :id="`${inputId}-results`" role="listbox" aria-label="Suggested articles">
                <li v-for="(article, index) in suggestions" :id="`${inputId}-option-${index}`" :key="article.slug" role="option" :aria-selected="active === index" :class="{ 'is-active': active === index }">
                    <a :href="article.url || `/help/articles/${article.slug}`"><span><strong>{{ article.title }}</strong><small>{{ article.summary }}</small></span><ArrowUpRightIcon class="size-4 shrink-0" aria-hidden="true" /></a>
                </li>
            </ul>
            <p v-if="!suggestions.length" class="p-4 text-sm">No close match yet. Try “team”, “booking” or “billing”, or browse a topic.</p>
        </div>
        <AppNotice v-if="failure" class="mt-4" title="Search suggestions paused" :description="cooldown ? `Please wait ${cooldown} seconds before typing to get more suggestions. You can browse the topics below while you wait.` : 'Suggestions are unavailable. Your question is still here. Press Search for matching articles, or browse a topic below.'" />
    </div>
</template>
