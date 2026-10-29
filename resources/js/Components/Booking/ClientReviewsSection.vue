<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { StarIcon, CheckBadgeIcon } from '@heroicons/vue/24/solid';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
const props = defineProps({ summary: { type: Object, default: () => ({ count: 0, average: null, items: [] }) } });
const items = ref(props.summary.items || []), nextUrl = ref(props.summary.next_url), busy = ref(false), error = ref(''), retrySeconds = ref(0);
let controller, retryTimer, requestVersion = 0;
watch(() => props.summary, summary => { controller?.abort(); ++requestVersion; items.value = summary.items || []; nextUrl.value = summary.next_url; error.value = ''; busy.value = false; });
onBeforeUnmount(() => { controller?.abort(); clearInterval(retryTimer); });
async function loadMore() {
    if (!nextUrl.value || busy.value || retrySeconds.value) return;
    controller = new AbortController(); const version = ++requestVersion; busy.value = true; error.value = '';
    try {
        const { data } = await axios.get(nextUrl.value, { signal: controller.signal });
        if (version !== requestVersion) return;
        const seen = new Set(items.value.map(item => item.id));
        items.value.push(...data.items.filter(item => !seen.has(item.id))); nextUrl.value = data.next_url;
    } catch (failure) {
        if (failure.code === 'ERR_CANCELED' || version !== requestVersion) return;
        error.value = 'More reviews could not load. The reviews already shown are still available.';
        if (failure.response?.status === 429) {
            const wait = Number(failure.response?.headers?.['retry-after']); retrySeconds.value = Number.isFinite(wait) && wait > 0 ? Math.ceil(wait) : 60;
            retryTimer = setInterval(() => { retrySeconds.value = Math.max(0, retrySeconds.value - 1); if (!retrySeconds.value) clearInterval(retryTimer); }, 1000);
        } else if ([404, 410].includes(failure.response?.status)) nextUrl.value = null;
    } finally { if (version === requestVersion) busy.value = false; }
}
</script>

<template>
    <section v-if="summary?.count" aria-labelledby="client-reviews-title" class="client-reviews">
        <div class="reviews-heading"><h2 id="client-reviews-title">Client reviews</h2><p><StarIcon aria-hidden="true" /><strong>{{ summary.average }} out of 5</strong><span>· {{ summary.count }} {{ summary.count === 1 ? 'review' : 'reviews' }}</span></p></div>
        <div class="reviews-grid">
            <article v-for="review in items" :key="review.id" class="review-card">
                <div class="review-author"><span class="review-avatar">{{ review.author_initials }}</span><div><p class="review-score"><StarIcon aria-hidden="true" /><strong>{{ review.rating }} out of 5</strong></p><p class="review-verified"><CheckBadgeIcon aria-hidden="true" />Verified visit · <time :datetime="review.date">{{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${review.date}T12:00:00Z`)) }}</time></p></div></div>
                <p v-if="review.body" class="review-body">{{ review.body }}</p>
            </article>
        </div>
        <AppNotice v-if="error" class="mt-4" tone="error" title="Reviews could not load" :description="error" />
        <p v-if="retrySeconds" class="mt-3 text-sm text-[var(--text-muted)]" aria-live="off">More reviews will be available in {{ retrySeconds }} seconds.</p>
        <div v-if="nextUrl" class="mt-[14px] flex flex-wrap gap-2.5"><AppButton variant="secondary" :loading="busy" :disabled="retrySeconds > 0" @click="loadMore">{{ error ? 'Load more reviews again' : 'More reviews' }}</AppButton></div>
    </section>
</template>

<style scoped>
.client-reviews { padding-block:30px; border-top:1px solid var(--border-subtle); }
.reviews-heading h2 { margin:0; color:var(--text-strong); font-size:24px; font-weight:650; }
.reviews-heading>p,.review-score,.review-verified { display:flex; align-items:center; flex-wrap:wrap; gap:6px; }
.reviews-heading>p { margin-top:10px; font-size:14px; color:var(--text-muted); }
.reviews-heading svg,.review-score svg { width:17px; height:17px; color:var(--action-primary); }
.reviews-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-top:20px; }
.review-card { min-width:0; padding:20px; border:1px solid var(--border-subtle); border-radius:16px; background:var(--surface-raised); }
.review-author { display:flex; align-items:center; gap:12px; }
.review-avatar { display:grid; place-items:center; width:44px; height:44px; flex-shrink:0; border-radius:50%; background:var(--surface-subtle); font-size:14px; font-weight:600; }
.review-score { margin:0; font-size:14px; }
.review-verified { margin-top:4px; color:var(--text-muted); font-size:12px; }
.review-verified svg { width:14px; height:14px; }
.review-body { margin:16px 0 0; font-size:14px; line-height:1.7; white-space:pre-wrap; overflow-wrap:anywhere; }
@media(max-width:640px) { .reviews-grid { grid-template-columns:minmax(0,1fr); }.review-card { padding:16px; } }
</style>
