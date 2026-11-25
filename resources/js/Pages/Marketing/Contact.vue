<script setup>
import { ref, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import '../../../css/contact.css';
const props = defineProps({ seo: Object, submissionId: String, selectedIndustry: String, industries: Array, receipt: Object });
const form = useForm({ submission_id: props.submissionId, name: '', email: '', business: '', industry: props.selectedIndustry || '', message: '', website: '' });
watch(() => props.submissionId, value => { form.submission_id = value; });
const remaining = ref(0);
let timer;
onUnmounted(() => clearInterval(timer));
const submit = () => {
    if (form.processing || remaining.value > 0) return;
    form.post(route('marketing.contact.store'), {
        preserveScroll: true,
        onSuccess: async () => {
            form.reset('name', 'email', 'business', 'message');
            await nextTick();
            document.getElementById('contact-result')?.focus();
        },
        onError: async errors => {
            await nextTick();
            document.querySelector('[aria-invalid="true"]')?.focus();
            if (errors.retry_after) {
                const deadline = Date.now() + Number(errors.retry_after) * 1000;
                clearInterval(timer);
                const tick = () => { remaining.value = Math.max(0, Math.ceil((deadline - Date.now()) / 1000)); if (!remaining.value) clearInterval(timer); };
                tick(); timer = setInterval(tick, 1000);
            }
        },
    });
};
</script>
<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="contact-page">
            <PublicContainer class="contact-grid">
                <div class="contact-intro">
                    <p class="cd-eyebrow">Let’s talk about your business</p>
                    <h1>A good fit starts<br>with a conversation.</h1>
                    <p class="contact-lead">Your team, your clients, your way of working. Tell us what you need, and we’ll help you work out the next step.</p>
                    <div class="contact-topics"><article><span>01</span><div><h2>Find your fit</h2><p>Explore the workflows that matter to your business.</p></div></article><article><span>02</span><div><h2>Make the move</h2><p>Discuss setup, existing records and a sensible changeover plan.</p></div></article><article><span>03</span><div><h2>Get clear on pricing</h2><p>Talk through your team, locations and plan questions.</p></div></article></div>
                    <Link :href="route('marketing.pricing')" class="contact-quiet-link">Prefer to explore? See pricing →</Link>
                </div>
                <div id="contact-result" class="contact-card" :tabindex="receipt ? -1 : undefined">
                    <template v-if="receipt">
                        <AppNotice :title="receipt.delayed ? 'Your enquiry is saved' : 'Thanks for getting in touch'" :tone="receipt.delayed ? 'info' : 'success'">
                            <p>{{ receipt.delayed ? 'Email delivery is delayed, but we have saved your message. You do not need to send it again.' : 'Your message has been sent to our team. We’ll reply to the email address you provided.' }}</p>
                            <p class="contact-reference">Your reference: {{ receipt.reference }}</p>
                        </AppNotice>
                        <div class="contact-actions"><Link :href="route('marketing.contact.new')" method="post" as="button" class="cd-button cd-button-secondary">Send another enquiry</Link></div>
                        <div class="contact-actions"><Link :href="route('marketing.solutions')" class="cd-button cd-button-primary">Explore your industry</Link><Link :href="route('marketing.product-tour')" class="cd-button cd-button-secondary">See how it works</Link></div>
                    </template>
                    <form v-else @submit.prevent="submit" novalidate>
                        <h2>Tell us a little about you.</h2><p class="contact-form-note">We’ll use these details to respond to your enquiry.</p>
                        <div class="contact-fields">
                            <div><label for="contact-name">Your name <span>(required)</span></label><input id="contact-name" v-model="form.name" autocomplete="name" maxlength="100" required :aria-invalid="!!form.errors.name" :aria-describedby="form.errors.name ? 'contact-name-error' : undefined"><p v-if="form.errors.name" id="contact-name-error" class="contact-error">{{ form.errors.name }}</p></div>
                            <div><label for="contact-email">Email address <span>(required)</span></label><input id="contact-email" v-model="form.email" type="email" autocomplete="email" maxlength="254" required :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'contact-email-error' : undefined"><p v-if="form.errors.email" id="contact-email-error" class="contact-error">{{ form.errors.email }}</p></div>
                            <div><label for="contact-business">Business name <span>(optional)</span></label><input id="contact-business" v-model="form.business" autocomplete="organization" maxlength="160" :aria-invalid="!!form.errors.business"><p v-if="form.errors.business" class="contact-error">{{ form.errors.business }}</p></div>
                            <div><label for="contact-industry">Business type <span>(optional)</span></label><select id="contact-industry" v-model="form.industry" :aria-invalid="!!form.errors.industry"><option value="">Choose your business type</option><option v-for="industry in industries" :key="industry.value" :value="industry.value">{{ industry.label }}</option></select><p v-if="form.errors.industry" class="contact-error">{{ form.errors.industry }}</p></div>
                            <div class="contact-message"><label for="contact-message">How can we help? <span>(required)</span></label><textarea id="contact-message" v-model="form.message" rows="5" minlength="15" maxlength="5000" required :aria-invalid="!!form.errors.message" aria-describedby="contact-message-help contact-message-error"></textarea><p id="contact-message-help" class="contact-form-note">Tell us about your business needs. Please leave out client records, health details and passwords.</p><p v-if="form.errors.message" id="contact-message-error" class="contact-error">{{ form.errors.message }}</p></div>
                        </div>
                        <div class="contact-trap" aria-hidden="true"><label for="contact-website">Leave this empty</label><input id="contact-website" v-model="form.website" tabindex="-1" autocomplete="off"></div>
                        <AppNotice v-if="form.errors.website || form.errors.submission_id" tone="info" title="We couldn’t submit this message"><p>Please refresh this page before sending. Copy your message first so you can keep it.</p></AppNotice>
                        <p class="contact-form-note">By sending, you ask us to respond to this enquiry. <Link :href="route('policy.show')">Read our privacy policy.</Link></p>
                        <div class="contact-actions"><button class="cd-button cd-button-primary" type="submit" :disabled="form.processing || remaining > 0">{{ form.processing ? 'Sending your enquiry…' : remaining > 0 ? `Please wait ${remaining}s` : 'Send your enquiry →' }}</button></div>
                    </form>
                </div>
            </PublicContainer>
        </section>
    </HomeLayout>
</template>
