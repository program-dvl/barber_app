<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import SetupSupport from '@/Components/Marketing/SetupSupport.vue';
import SalonDayDemo from '@/Components/Marketing/SalonDayDemo.vue';
import { ArrowRightIcon, CalendarDaysIcon, UserIcon } from '@heroicons/vue/24/outline';
import '../../css/homepage.css';
defineProps({ seo: Object, canLogin: Boolean, canRegister: Boolean, screenshots: Array, homepage: Object });
const moment = ref(0);
const moments = [
    { time: '09:00', label: 'Open ready', title: 'A little clarity before the first client.', text: 'See today’s appointments and your team’s availability before the doors open.', appointment: 'Alex · First visit at 09:30', client: 'The day is ready' },
    { time: '12:00', label: 'In the flow', title: 'Keep the day moving, together.', text: 'Bring appointments, walk-ins and client details into one connected working day.', appointment: 'Alex · Next visit at 12:30', client: 'Jamie · Waiting' },
    { time: '18:00', label: 'Close clearly', title: 'Finish with a clear view of your day.', text: 'Review completed visits, recorded payments and the next day’s appointments.', appointment: 'Today’s visits · Complete', client: 'Tomorrow · In view' },
];
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <div class="salon-home">
            <section class="salon-hero" aria-labelledby="home-title" :data-moment="moment">
                <picture class="salon-hero-art">
                    <source media="(max-width: 700px)" srcset="/images/marketing/homepage/salon-day-small.webp">
                    <img src="/images/marketing/homepage/salon-day.webp" alt="An illustrative sunlit salon with arched mirrors, stylists and clients" width="1604" height="980" fetchpriority="high">
                </picture>
                <PublicContainer class="salon-hero-content">
                    <p class="salon-eyebrow">Appointment scheduling &amp; business management</p>
                    <h1 id="home-title">A good day.<br>By design.</h1>
                    <p class="salon-lead">{{ homepage.lead }}</p>
                    <p class="salon-hero-audience">{{ homepage.audience }}</p>
                    <div class="salon-actions">
                        <PublicCta context="homepage_hero" />
                        <a href="#sample-day" class="cd-button cd-button-secondary">Try a sample day <ArrowRightIcon aria-hidden="true" /></a>
                    </div>
                </PublicContainer>
                <div class="salon-scene-label salon-scene-appointment" aria-hidden="true"><CalendarDaysIcon />{{ moments[moment].appointment }}</div>
                <div class="salon-scene-label salon-scene-client" aria-hidden="true"><UserIcon />{{ moments[moment].client }}</div>
            </section>
            <section class="salon-day-selector" aria-label="Explore a sample salon day">
                <PublicContainer>
                    <div class="salon-times" role="group" aria-label="Time of day">
                        <button v-for="(item, index) in moments" :key="item.time" type="button" :aria-pressed="moment === index" @click="moment = index"><span class="salon-time-dot" aria-hidden="true"></span><strong>{{ item.time }}</strong><span>{{ item.label }}</span></button>
                    </div>
                    <div class="salon-moment-copy" aria-live="polite" aria-atomic="true"><strong>{{ moments[moment].title }}</strong><p>{{ moments[moment].text }}</p></div>
                    <p class="salon-caption">Illustrative salon · Sample day. No customer relationship is implied.</p>
                </PublicContainer>
            </section>
            <SalonDayDemo />
            <section id="workflow" class="salon-journey">
                <PublicContainer>
                    <p class="salon-eyebrow">The whole visit, connected</p>
                    <h2>From first booking<br>to next visit.</h2>
                    <div class="salon-journey-grid">
                        <article>
                            <img src="/images/marketing/industries/home-salon-hero.webp" alt="A salon team welcoming clients" width="1800" height="1200" loading="lazy">
                            <h3>Online appointment booking.</h3><p>Let clients choose a service and an available time. Keep every appointment in the working calendar.</p>
                            <Link :href="route('marketing.features.show', 'calendar-and-walk-ins')">Explore booking &amp; walk-ins <ArrowRightIcon aria-hidden="true" /></Link>
                        </article>
                        <article>
                            <img src="/images/marketing/industries/barbershop.webp" alt="A barber at work with a client" width="1400" height="933" loading="lazy">
                            <h3>Walk-ins &amp; staff schedules.</h3><p>See who’s waiting, assign available staff and keep scheduled visits in view.</p>
                            <Link :href="route('marketing.product-tour')">See the product in action <ArrowRightIcon aria-hidden="true" /></Link>
                        </article>
                        <article>
                            <img src="/images/marketing/industries/nail-salon.webp" alt="A nail technician caring for a returning client" width="1400" height="933" loading="lazy">
                            <h3>Client records &amp; visit history.</h3><p>Keep preferences, visit history and notes together, ready for the next appointment.</p>
                            <Link :href="route('marketing.features.show', 'client-management')">Explore client records <ArrowRightIcon aria-hidden="true" /></Link>
                        </article>
                    </div>
                    <div class="salon-records"><div><h3>A clear close to the day.</h3><p>Record payments received outside ClipperDesk, issue receipts and review your sales.</p></div><Link :href="route('marketing.features.show', 'checkout-and-reporting')">Explore checkout &amp; reports <ArrowRightIcon aria-hidden="true" /></Link></div>
                </PublicContainer>
            </section>
            <section class="salon-use-cases" aria-labelledby="home-use-cases">
                <PublicContainer>
                    <p class="salon-eyebrow">Start with the problem you want to solve</p>
                    <h2 id="home-use-cases">Built around your working day.</h2>
                    <div class="salon-use-case-list">
                        <article v-for="item in homepage.use_cases" :key="item.slug">
                            <h3>{{ item.title }}</h3><p>{{ item.body }}</p>
                            <Link :href="route(item.route, item.slug)">{{ item.label }} <ArrowRightIcon aria-hidden="true" /></Link>
                        </article>
                    </div>
                </PublicContainer>
            </section>
            <section id="fit" class="salon-fit">
                <PublicContainer>
                    <p class="salon-eyebrow">Your craft. Your way of working.</p>
                    <h2>Made for people-powered businesses.</h2>
                    <p>{{ homepage.audience }}</p>
                    <div class="salon-business-links"><Link v-for="business in homepage.businesses" :key="business.slug" :href="route('marketing.solutions.show', business.slug)">{{ business.label }} <ArrowRightIcon aria-hidden="true" /></Link></div>
                    <Link :href="route('marketing.solutions')" class="salon-text-link">Explore all 13 business types <ArrowRightIcon aria-hidden="true" /></Link>
                </PublicContainer>
            </section>
            <section id="questions" class="salon-faq">
                <PublicContainer class="salon-faq-layout"><div><p class="salon-eyebrow">Before you make the move</p><h2>Straight answers.</h2><p>Start with your services, team and working hours. Preview your booking page before sharing it.</p><div class="salon-actions"><PublicCta context="homepage_plan_teaser" /><Link :href="route('marketing.pricing')" class="cd-button cd-button-secondary">See pricing</Link></div></div><FaqList :items="homepage.faqs" /></PublicContainer>
            </section>
            <SetupSupport />
            <section class="salon-final">
                <PublicContainer><h2>Make room for<br>your best work.</h2><div><div class="salon-actions"><PublicCta context="homepage_final" /><Link :href="route('marketing.pricing')" class="cd-button cd-button-secondary">See pricing</Link></div><a :href="`mailto:${$page.props.brand?.support_email || 'support@clipperdesk.com'}`">Need help switching? Talk to us.</a></div></PublicContainer>
            </section>
        </div>
    </HomeLayout>
</template>
