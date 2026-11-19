<script setup>
import SetupSupport from '@/Components/Marketing/SetupSupport.vue';
import HelpScreenshot from '@/Components/Help/HelpScreenshot.vue';
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import RelatedReading from '@/Components/Marketing/RelatedReading.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowRightIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage } from '@inertiajs/vue3';
import '../../../../css/help-center.css';
import '../../../../css/solutions.css';

defineProps({ solution: Object, features: Array, seo: Object, trialLabel: String, guides: { type: Array, default: () => [] } });
const page = usePage();
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="solution-intro">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Solutions', href: route('marketing.solutions') }, { label: solution.label }]" />
                <div class="solution-hero-grid">
                    <div>
                        <p class="cd-eyebrow">ClipperDesk for {{ solution.label }}</p>
                        <h1 class="font-display cd-public-title solution-title">{{ solution.title }}</h1>
                        <p class="solution-lead">{{ solution.description }}</p>
                        <div class="solution-actions">
                            <PublicCta :context="`solution_${solution.slug}_hero`" />
                            <Link :href="route('marketing.product-tour')" class="cd-button cd-button-secondary">See the product walkthrough</Link>
                        </div>
                        <p class="solution-trial">{{ trialLabel }} free trial after email verification. No card required. No automatic charge.</p>
                        <Link :href="route('marketing.pricing')" class="solution-text-link">Compare current pricing <ArrowRightIcon aria-hidden="true" /></Link>
                    </div>
                    <div v-if="solution.screenshots?.length" class="solution-hero-proof">
                        <p class="solution-proof-label">The actual product · demo data</p>
                        <HelpScreenshot :media="solution.screenshots[0]" eager />
                        <p class="solution-proof-note">Shared ClipperDesk workspace, not a specialist {{ solution.label.toLowerCase() }} module. Enlarge to inspect the controls.</p>
                    </div>
                </div>
                <div class="solution-fit"><strong>Is it a fit?</strong><p>{{ solution.fit }}</p><a href="#fit-check" class="solution-text-link">Check your workflow <ArrowRightIcon aria-hidden="true" /></a></div>
            </PublicContainer>
        </section>

        <section class="solution-section">
            <PublicContainer>
                <p class="cd-eyebrow">Your working day</p>
                <h2 class="solution-section-title font-display">{{ solution.heading }}</h2>
                <div class="solution-capabilities">
                    <article v-for="challenge in solution.challenges" :key="challenge.title">
                        <h3>{{ challenge.title }}</h3><p>{{ challenge.body }}</p>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <section class="solution-section solution-example" aria-labelledby="workflow-heading">
            <PublicContainer>
                <div class="solution-story-grid">
                    <div>
                        <p class="cd-eyebrow">A workflow to inspect</p>
                        <h2 id="workflow-heading" class="solution-section-title font-display">{{ solution.scenario.title }}</h2>
                        <p class="solution-body">{{ solution.scenario.body }}</p>
                        <ol class="solution-journey">
                            <li v-for="(step, index) in solution.day" :key="step"><span aria-hidden="true">{{ index + 1 }}</span><p>{{ step }}</p></li>
                        </ol>
                    </div>
                    <div class="solution-story-proof">
                        <HelpScreenshot v-if="solution.screenshots?.[1]" :media="solution.screenshots[1]" />
                        <p class="solution-proof-note">Real product screens with sample clients, services and prices. The scenario is an evaluation exercise, not a customer result.</p>
                        <div class="solution-actions"><Link :href="route('marketing.product-tour')" class="cd-button cd-button-secondary">Inspect the full visit walkthrough</Link></div>
                    </div>
                </div>
            </PublicContainer>
        </section>

        <section id="fit-check" class="solution-section solution-check-section" aria-labelledby="fit-heading">
            <PublicContainer class="solution-fit-grid">
                <div>
                    <p class="cd-eyebrow">Before you switch</p>
                    <h2 id="fit-heading" class="solution-section-title font-display">Put your own working day to the test</h2>
                    <p class="solution-body">Use fictional clients first. These checks help you decide whether the workflow fits before importing records or sharing your booking link.</p>
                    <ul class="solution-checks"><li v-for="check in solution.checks" :key="check"><CheckCircleIcon aria-hidden="true" /><span>{{ check }}</span></li></ul>
                </div>
                <div>
                    <AppNotice tone="info" title="Know the limits before choosing">
                        <ul class="solution-limits"><li v-for="limit in solution.limits" :key="limit">{{ limit }}</li></ul>
                        <div v-if="page.props.brand?.support_email" class="solution-actions"><a :href="`mailto:${page.props.brand.support_email}?subject=${encodeURIComponent(`Product fit: ${solution.label}`)}`" class="cd-button cd-button-secondary">Ask about your workflow</a></div>
                        <p class="mt-3 text-sm leading-6">Describe your business requirements only. Please do not include client or patient records.</p>
                    </AppNotice>
                </div>
            </PublicContainer>
        </section>

        <section class="solution-section" aria-labelledby="questions-heading">
            <PublicContainer class="solution-questions-grid">
                <div><p class="cd-eyebrow">Buying questions</p><h2 id="questions-heading" class="solution-section-title font-display">Answers before you start</h2><p class="solution-body">The details that can change your decision.</p></div>
                <dl class="solution-answers"><div v-for="faq in solution.faqs" :key="faq.question"><dt>{{ faq.question }}</dt><dd>{{ faq.answer }}</dd></div></dl>
            </PublicContainer>
        </section>

        <section class="solution-section solution-related" aria-labelledby="related-heading">
            <PublicContainer>
                <h2 id="related-heading" class="font-display text-2xl font-semibold">Look closer at the tools you would use</h2>
                <div class="solution-feature-links"><Link v-for="feature in features" :key="feature.slug" :href="route('marketing.features.show', feature.slug)"><span>{{ feature.label }}</span><ArrowRightIcon aria-hidden="true" /></Link></div>
                <div v-if="['salons', 'barbershops', 'nail-salons'].includes(solution.slug)" class="solution-actions"><Link href="/help/articles/appointments-waitlist" class="solution-text-link">How cancellation waitlists differ from walk-ins</Link><Link href="/tools/no-show-calculator" class="solution-text-link">Estimate the cost of missed visits</Link></div>
            </PublicContainer>
        </section>
        <RelatedReading v-if="guides.length" :items="guides.map(guide => ({ ...guide, description: guide.excerpt }))" />
        <SetupSupport />
        <ConversionBand :title="solution.final_title" description="Set up a sample service, inspect a booking and decide whether it works for your business. See Pricing for current location, staff and messaging terms." :context="`solution_${solution.slug}_final`" />
    </HomeLayout>
</template>
