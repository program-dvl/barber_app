<script setup>
import { computed, ref } from 'vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage } from '@inertiajs/vue3';
import '../../../../css/solutions.css';
const props = defineProps({ solutions: Array, seo: Object });
const page = usePage();
const selectedIndustry = ref('barbershops');
const featured = computed(() => props.solutions.filter(item => ['barbershops', 'nail-salons', 'spas', 'massage'].includes(item.slug)));
const selected = computed(() => featured.value.find(item => item.slug === selectedIndustry.value) || featured.value[0]);
const descriptions = {
    'Hair, beauty & studio services': 'Start with everyday bookings, staff eligibility, client history and recorded checkout. Each page explains the details for your working day.',
    'Treatments & individual sessions': 'Explore individual appointment workflows. Shared rooms and equipment need setup review; classes and memberships are not included.',
    'Specialist fit review': 'These businesses often need specialist records, compliance, devices or billing. Read the limits first; an industry page does not certify suitability.',
};
const groups = computed(() => Object.entries(descriptions).map(([title, description], index) => ({ title, description, id: `industry-group-${index + 1}`, items: props.solutions.filter(item => item.group === title) })));
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <div class="solution-experience experience-hub">
        <section class="experience-hub-intro">
            <PublicContainer>
                <p class="cd-eyebrow">One connected day. Your way.</p>
                <h1>Different crafts.<br>The same care.</h1>
                <p class="solution-lead">Appointment scheduling and business management for people who look after people. Find the workflow that fits your business.</p>
                <div class="experience-industry-tabs" role="group" aria-label="Preview a business type"><button v-for="item in featured" :key="item.slug" type="button" :aria-pressed="selectedIndustry === item.slug" @click="selectedIndustry = item.slug">{{ item.label }}</button></div>
                <div v-if="selected" class="experience-industry-preview">
                    <img :key="selected.image" :src="selected.image" :alt="selected.image_alt" width="1400" height="933" fetchpriority="high">
                    <div aria-live="polite" aria-atomic="true"><p class="cd-eyebrow">ClipperDesk for {{ selected.label }}</p><h2>{{ selected.title }}</h2><p>{{ selected.description }}</p><Link :href="route('marketing.solutions.show', selected.slug)" class="cd-button cd-button-primary">Explore {{ selected.label.toLowerCase() }} <ArrowRightIcon aria-hidden="true" /></Link><span class="solution-proof-note">Illustrative scene</span></div>
                </div>
                <nav class="solution-hub-groups" aria-label="Business groups"><a v-for="group in groups" :key="group.id" :href="`#${group.id}`" class="cd-button cd-button-secondary">{{ group.title }}</a></nav>
            </PublicContainer>
        </section>
        <PublicContainer>
            <section v-for="(group, groupIndex) in groups" :id="group.id" :key="group.id" class="solution-hub-group" style="scroll-margin-top:110px" :aria-labelledby="`${group.id}-heading`">
                <h2 :id="`${group.id}-heading`" class="solution-section-title font-display">{{ group.title }}</h2>
                <p class="solution-body max-w-3xl">{{ group.description }}</p>
                <div class="solution-hub-cards">
                    <article v-for="(solution, index) in group.items" :key="solution.slug" class="solution-hub-card">
                        <img :src="solution.image" alt="" width="1400" height="933" :loading="groupIndex === 0 && index < 3 ? 'eager' : 'lazy'" decoding="async">
                        <Link :href="route('marketing.solutions.show', solution.slug)">
                            <h3>{{ solution.label }}</h3>
                            <p>{{ solution.description }}</p>
                            <span class="solution-text-link">Explore {{ solution.label.toLowerCase() }} <ArrowRightIcon aria-hidden="true" /></span>
                        </Link>
                    </article>
                </div>
            </section>
            <section class="solution-section">
                <h2 class="solution-section-title font-display">Not sure where your business fits?</h2>
                <p class="solution-body max-w-3xl">Tell us how a typical appointment works, what your team needs and which specialist tools you rely on. We can discuss the gaps before you move records or publish a booking link.</p>
                <div class="solution-actions"><Link :href="route('marketing.contact')" class="cd-button cd-button-secondary">Ask about your workflow</Link></div>
                <p class="solution-proof-note">Business requirements only—no client or patient records needed.</p>
            </section>
        </PublicContainer>
        <ConversionBand title="Try a real working-day scenario." description="Start with fictional clients, inspect the workflow and compare the current plan terms before you switch." context="solutions_hub_final" />
        </div>
    </HomeLayout>
</template>
