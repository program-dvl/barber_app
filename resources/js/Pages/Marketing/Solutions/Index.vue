<script setup>
import { computed } from 'vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage } from '@inertiajs/vue3';
import '../../../../css/solutions.css';
const props = defineProps({ solutions: Array, seo: Object });
const page = usePage();
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
        <section class="solution-intro">
            <PublicContainer class="pb-10 pt-5 sm:pb-14">
                <p class="cd-eyebrow">Find your fit</p>
                <h1 class="solution-title cd-public-title font-display max-w-4xl">Find the workflow that fits your business</h1>
                <p class="solution-lead max-w-3xl">Explore what ClipperDesk can do for your working day—with real product screens, a practical trial checklist and the limits to know before you switch.</p>
                <nav class="solution-hub-groups" aria-label="Business groups"><a v-for="group in groups" :key="group.id" :href="`#${group.id}`" class="cd-button cd-button-secondary">{{ group.title }}</a></nav>
                <div class="solution-actions"><Link :href="route('marketing.product-tour')" class="solution-text-link">Walk through the product <ArrowRightIcon aria-hidden="true" /></Link><Link :href="route('marketing.pricing')" class="solution-text-link">Compare current pricing <ArrowRightIcon aria-hidden="true" /></Link></div>
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
                <div v-if="page.props.brand?.support_email" class="solution-actions"><a :href="`mailto:${page.props.brand.support_email}?subject=Help%20assessing%20ClipperDesk%20fit`" class="cd-button cd-button-secondary">Email us about your workflow</a></div>
                <p class="solution-proof-note">Business requirements only—no client or patient records needed.</p>
            </section>
        </PublicContainer>
        <ConversionBand title="Try a real working-day scenario." description="Start with fictional clients, inspect the workflow and compare the current plan terms before you switch." context="solutions_hub_final" />
    </HomeLayout>
</template>
