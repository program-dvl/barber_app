<script setup>
import AppNotice from '@/Components/Product/AppNotice.vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
const props = defineProps({ slug: String });
const page = usePage();
const needsHelp = ref(false);
const saving = ref(false), saved = ref(false), failure = ref(false), cooldown = ref(0);
let countdown;
const vote = async helpful => {
    if (saving.value || cooldown.value > 0) return;
    saving.value = true;
    failure.value = false;
    try { await axios.post(`/help/articles/${props.slug}/feedback`, { helpful }); needsHelp.value = !helpful; saved.value = true; }
    catch (error) {
        failure.value = true;
        const retry = Number(error.response?.headers?.['retry-after']);
        cooldown.value = Number.isFinite(retry) && retry > 0 ? Math.ceil(retry) : 30;
        const until = Date.now() + cooldown.value * 1000;
        clearInterval(countdown);
        countdown = setInterval(() => { cooldown.value = Math.max(0, Math.ceil((until - Date.now()) / 1000)); if (!cooldown.value) clearInterval(countdown); }, 1000);
    } finally { saving.value = false; }
};
onBeforeUnmount(() => clearInterval(countdown));
</script>
<template>
    <section class="help-feedback" aria-label="Article feedback">
        <AppNotice v-if="saved" :title="needsHelp ? 'Let’s find your next step' : 'Thank you for your feedback'" :description="needsHelp ? 'Explore the related guides below, or contact support. Share the screen name and what you were trying to do.' : 'Your answer helps us improve this guide.'" :tone="needsHelp ? 'info' : 'success'">
            <template v-if="needsHelp && page.props.brand?.support_email" #actions><a :href="`mailto:${page.props.brand.support_email}`" class="cd-button cd-button-secondary">Contact support</a><a href="/help" class="cd-button cd-button-quiet">Search all help</a></template>
        </AppNotice>
        <template v-else>
            <AppNotice v-if="failure" title="Your feedback could not be saved" :description="cooldown ? `You can keep reading. Please wait ${cooldown} seconds before trying again.` : 'You can try again now, or continue browsing help.'" />
            <template v-else><h2>Was this article helpful?</h2><p>Help us make these guides more useful.</p></template>
            <div class="help-actions"><button type="button" class="cd-button cd-button-secondary" :disabled="saving || cooldown > 0" @click="vote(true)">Yes, it helped</button><button type="button" class="cd-button cd-button-quiet" :disabled="saving || cooldown > 0" @click="vote(false)">I still need help</button></div>
        </template>
    </section>
</template>
