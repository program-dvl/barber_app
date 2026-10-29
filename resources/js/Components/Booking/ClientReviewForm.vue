<script setup>
import { computed, inject, ref, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import { StarIcon } from '@heroicons/vue/24/solid';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import FormField from '@/Components/Product/FormField.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';

const props = defineProps({ review: Object, actionToken: String, reviewUrl: String });
const route = inject('route');
const form = useForm({ rating: null, body: '', public_consent: false });
const withdrawal = useForm({ operation: 'withdraw_public_consent' });
const submissionError = ref('');
const savedResult = ref(null), retrySeconds = ref(0), unavailable = ref(false);
let retryTimer;
onBeforeUnmount(() => clearInterval(retryTimer));
const saved = computed(() => savedResult.value || props.review?.saved);
const labels = ['Very disappointing', 'Disappointing', 'Okay', 'Good', 'Excellent'];
const postUrl = computed(() => props.actionToken ? route('public.appointment.mutate', [props.actionToken, 'review'], false) : '');
async function saveFeedback(withdraw = false) {
    if (form.processing || withdrawal.processing || retrySeconds.value || unavailable.value) return;
    const state = withdraw ? withdrawal : form;
    state.processing = true;
    submissionError.value = '';
    form.errors = {}; withdrawal.errors = {};
    try {
        const { data } = await axios.post(postUrl.value, withdraw ? { operation: 'withdraw_public_consent' } : { rating: form.rating, body: form.body, public_consent: form.public_consent }, { headers: { Accept: 'application/json' } });
        savedResult.value = data.review.saved;
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) state.errors = Object.fromEntries(Object.entries(error.response.data.errors || {}).map(([key, values]) => [key, Array.isArray(values) ? values[0] : values]));
        if (status === 429) {
            const retryAfter = Number(error.response?.headers?.['retry-after']);
            retrySeconds.value = Number.isFinite(retryAfter) && retryAfter > 0 ? Math.ceil(retryAfter) : 60;
            retryTimer = setInterval(() => { retrySeconds.value = Math.max(0, retrySeconds.value - 1); if (!retrySeconds.value) clearInterval(retryTimer); }, 1000);
            submissionError.value = 'Please wait before sending again. Your words are still here.';
        } else if (status === 404 || status === 410) {
            unavailable.value = true;
            submissionError.value = 'This review link is no longer available. Your words are still here. Ask the business for a fresh review link.';
        } else submissionError.value = withdraw ? 'Your review is still public. We could not save your change. Try again, or contact ClipperDesk support.' : 'We could not confirm that your feedback was saved. Your words are still here. You can safely send the same feedback again.';
    } finally { state.processing = false; }
}
</script>

<template>
    <SurfaceCard title="How was your visit?" class="mt-5" description="Share your experience with the business. A review is always optional.">
        <template v-if="saved">
            <AppNotice v-if="!submissionError" tone="success" title="Thank you for your feedback" :description="saved.public_consent ? 'Your review can appear on this business’s booking page with your initials. Your contact details and appointment details stay private.' : 'Your feedback is private and can be read by the team who manages your visit.'" />
            <p class="mt-4 text-sm font-semibold">{{ saved.rating }} out of 5</p>
            <p v-if="saved.body" class="mt-2 whitespace-pre-wrap break-words text-sm leading-6">{{ saved.body }}</p>
            <div v-if="saved.public_consent && actionToken" class="mt-[14px] flex flex-wrap gap-2.5">
                <AppButton variant="secondary" :loading="withdrawal.processing" :disabled="retrySeconds > 0 || unavailable" @click="saveFeedback(true)">Keep my feedback private</AppButton>
            </div>
            <div v-else-if="saved.public_consent && reviewUrl" class="mt-[14px] flex flex-wrap gap-2.5"><AppButton variant="secondary" :href="reviewUrl">Manage public display</AppButton></div>
            <p v-if="withdrawal.errors.review" class="mt-3 text-sm" role="alert">{{ withdrawal.errors.review }}</p>
            <AppNotice v-if="submissionError" class="mt-4" tone="error" title="Your feedback needs attention" :description="submissionError"><template v-if="unavailable" #actions><a href="mailto:support@clipperdesk.com" class="inline-flex min-h-11 items-center underline">Contact support</a></template></AppNotice>
            <p v-if="retrySeconds" class="mt-3 text-sm text-[var(--text-muted)]" aria-live="off">You can send again in {{ retrySeconds }} seconds.</p>
        </template>
        <AppNotice v-else-if="!review?.eligible" title="Feedback opens after your visit" description="Once the business marks your service complete, you can return to this appointment page to share your experience." />
        <template v-else-if="!actionToken">
            <p class="text-sm text-[var(--text-muted)]">Tell the team what went well and what could be better.</p>
            <div class="mt-[14px] flex flex-wrap gap-2.5"><AppButton :href="reviewUrl">Write a review</AppButton></div>
        </template>
        <form v-else @submit.prevent="saveFeedback()" class="space-y-5">
            <AppNotice v-if="submissionError || form.errors.review" tone="error" title="Your feedback needs attention" :description="form.errors.review || submissionError" />
            <fieldset>
                <legend class="text-sm font-semibold">Your rating <span aria-hidden="true">*</span></legend>
                <div class="mt-3 flex flex-wrap gap-2.5">
                    <label v-for="rating in 5" :key="rating" class="review-rating" :class="{ 'is-selected': form.rating === rating }">
                        <input v-model="form.rating" type="radio" :value="rating" name="review-rating" required :disabled="form.processing" :aria-label="`${rating} out of 5 — ${labels[rating - 1]}`" />
                        <StarIcon aria-hidden="true" /><span>{{ rating }}</span>
                    </label>
                </div>
                <p class="mt-2 text-sm text-[var(--text-muted)]">{{ form.rating ? labels[form.rating - 1] : 'Choose from 1 to 5 stars.' }}</p>
                <p v-if="form.errors.rating" class="mt-2 text-sm text-[var(--status-danger)]" role="alert">{{ form.errors.rating }}</p>
            </fieldset>
            <FormField id="review-details" label="Tell us about your experience (optional)" :error="form.errors.body">
                <textarea id="review-details" v-model="form.body" class="cd-input" rows="5" maxlength="5000" :disabled="form.processing" placeholder="What did you enjoy? What could the team improve?" :aria-invalid="form.errors.body ? true : undefined" />
            </FormField>
            <label class="flex items-start gap-3 text-sm leading-6">
                <input v-model="form.public_consent" type="checkbox" :disabled="form.processing" class="mt-1 size-5 shrink-0" />
                <span>Show my rating and review on this business’s booking page with my initials. <span class="block text-[var(--text-muted)]">Leave this unchecked to send private feedback. Avoid including contact details or sensitive personal information in a public review.</span></span>
            </label>
            <p v-if="form.errors.public_consent" role="alert" class="text-sm text-[var(--status-danger)]">{{ form.errors.public_consent }}</p>
            <p v-if="retrySeconds" class="text-sm text-[var(--text-muted)]" aria-live="off">You can send again in {{ retrySeconds }} seconds.</p>
            <div class="flex flex-wrap gap-2.5 pt-1"><AppButton type="submit" :loading="form.processing" :disabled="!form.rating || retrySeconds > 0 || unavailable">{{ form.public_consent ? 'Publish my review' : 'Send private feedback' }}</AppButton><a v-if="unavailable" class="min-h-11 inline-flex items-center underline" href="mailto:support@clipperdesk.com">Contact support</a></div>
        </form>
    </SurfaceCard>
</template>

<style scoped>
.review-rating { position:relative; display:flex; align-items:center; justify-content:center; gap:5px; min-width:52px; min-height:48px; border:1px solid var(--border-strong); border-radius:10px; cursor:pointer; font-size:14px; font-weight:600; }
.review-rating input { position:absolute; width:1px; height:1px; opacity:0; }
.review-rating svg { width:18px; height:18px; color:var(--text-muted); }
.review-rating.is-selected { border-color:var(--action-primary); background:var(--surface-subtle); }
.review-rating.is-selected svg { color:var(--action-primary); }
.review-rating:focus-within { outline:3px solid var(--focus-ring,var(--action-primary)); outline-offset:3px; }
</style>
