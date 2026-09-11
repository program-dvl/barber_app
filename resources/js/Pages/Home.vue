<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const storyMoments = [
    {
        moment: 'Before the doors open',
        title: 'Open ready.',
        text: 'Live availability, appointments and the walk-in picture are already together, so the team starts aligned.',
    },
    {
        moment: 'While the room is moving',
        title: 'Stay present.',
        text: 'Client context, staff time and resource changes travel with the visit—not through side conversations.',
    },
    {
        moment: 'When the last client leaves',
        title: 'Close clearly.',
        text: 'Checkout and source records turn the day into useful reporting without rebuilding the story after hours.',
    },
];

const featuredBusinesses = [
    { label: 'Barbershops', text: 'Bookings, walk-ins and the chair plan in one view.', slug: 'barbershops', image: '/images/marketing/industries/barbershop.webp' },
    { label: 'Salons', text: 'Multi-service visits and client context that travel together.', slug: 'salons', image: '/images/marketing/industries/home-salon-hero.webp' },
    { label: 'Nail salons', text: 'Technicians, stations and repeat client preferences.', slug: 'nail-salons', image: '/images/marketing/industries/nail-salon.webp' },
    { label: 'Spa & sauna', text: 'Practitioners, rooms and treatment capacity kept aligned.', slug: 'spas', image: '/images/marketing/industries/spa-sauna.webp' },
    { label: 'Fitness & recovery', text: 'Coaches, equipment and appointment-led sessions.', slug: 'fitness-recovery', image: '/images/marketing/industries/fitness-recovery.webp' },
    { label: 'Pet grooming', text: 'Groomers, tables and every return visit in context.', slug: 'pet-grooming', image: '/images/marketing/industries/pet-grooming.webp' },
];

const faqs = [
    {
        question: 'What is ClipperDesk?',
        answer: 'ClipperDesk is the daily operating system for salons and barbershops. It connects booking and scheduling with client context, checkout and day-to-day reporting.',
    },
    {
        question: 'Who is ClipperDesk built for?',
        answer: 'The first release is designed for independent professionals and single-location salons and barbershops, including teams with a shared front desk.',
    },
    {
        question: 'How is this different from a booking-only tool?',
        answer: 'Booking starts the operational record. ClipperDesk carries that record into the calendar, walk-in operations, client history, checkout, inventory and traceable reporting.',
    },
    {
        question: 'Are payments and reminders ready for live use?',
        answer: 'The application workflows and provider boundaries are implemented and locally verified. Live card payments, email and WhatsApp sending still require approved provider accounts, callbacks and production certification before public launch.',
    },
    {
        question: 'Can I bring existing data with me?',
        answer: 'ClipperDesk includes reviewed CSV imports for clients, staff, services and products. Imports preview validation errors and duplicate candidates before accepted rows become operational records.',
    },
];

const updatePerspective = (event) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || event.pointerType === 'touch') return;

    const bounds = event.currentTarget.getBoundingClientRect();
    const x = (event.clientX - bounds.left) / bounds.width - 0.5;
    const y = (event.clientY - bounds.top) / bounds.height - 0.5;

    event.currentTarget.style.setProperty('--tilt-x', `${(-y * 4).toFixed(2)}deg`);
    event.currentTarget.style.setProperty('--tilt-y', `${(x * 5).toFixed(2)}deg`);
    event.currentTarget.style.setProperty('--drift-x', `${(x * 8).toFixed(1)}px`);
    event.currentTarget.style.setProperty('--drift-y', `${(y * 7).toFixed(1)}px`);
};

const resetPerspective = (event) => {
    event.currentTarget.style.setProperty('--tilt-x', '0deg');
    event.currentTarget.style.setProperty('--tilt-y', '0deg');
    event.currentTarget.style.setProperty('--drift-x', '0px');
    event.currentTarget.style.setProperty('--drift-y', '0px');
};
</script>

<template>
    <HomeLayout>
        <Head title="Salon, barber and wellness business software" />

        <section class="cd-home-hero overflow-hidden">
            <PublicContainer class="grid min-h-[calc(100svh-5rem)] gap-10 py-12 sm:py-16 lg:grid-cols-[0.84fr_1.16fr] lg:items-center lg:gap-14 lg:py-20">
                <div class="relative z-10 max-w-2xl">
                    <p class="cd-hero-kicker"><span aria-hidden="true">✦</span> Made for people-powered businesses</p>
                    <h1 class="mt-6 font-display text-[clamp(3.5rem,6.2vw,6.6rem)] font-bold leading-[0.88] tracking-[-0.075em] text-white text-balance">
                        Your whole day,<br><span class="cd-hero-highlight">beautifully</span> run.
                    </h1>
                    <p class="mt-7 max-w-xl text-lg leading-8 text-slate-300 sm:text-xl">
                        Bookings, clients, staff, checkout and reporting—one polished workspace for ambitious salon, barber and wellness businesses.
                    </p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <PublicCta context="homepage_hero" />
                        <Link :href="route('marketing.solutions')" class="cd-button cd-button-hero-secondary">
                            Find your business
                            <ArrowRightIcon class="ml-2 size-4" aria-hidden="true" />
                        </Link>
                    </div>
                    <p class="mt-5 max-w-lg text-sm leading-6 text-slate-400">
                        Start with a verified owner account and a clearly dated trial. Review every rule before your booking page goes live.
                    </p>
                </div>

                <figure class="cd-photo-hero min-w-0">
                    <img src="/images/marketing/industries/home-salon-hero.webp" alt="A modern salon team serving clients during a busy working day" width="1800" height="1200" class="cd-photo-hero-image" fetchpriority="high">
                    <div class="cd-photo-hero-badge cd-photo-hero-badge-top" aria-hidden="true">
                        <CalendarDaysIcon class="size-5" />
                        <span><strong>One live day</strong><small>Bookings + walk-ins</small></span>
                    </div>
                    <div class="cd-photo-hero-badge cd-photo-hero-badge-bottom" aria-hidden="true">
                        <CheckCircleIcon class="size-5" />
                        <span><strong>Everything connected</strong><small>Client to checkout</small></span>
                    </div>
                    <figcaption class="sr-only">An editorial representation of a modern salon business. No customer relationship is implied.</figcaption>
                </figure>
            </PublicContainer>

            <PublicContainer class="pb-14 sm:pb-20">
                <div class="cd-home-rhythm">
                    <p class="cd-home-rhythm-intro">One calmer operating rhythm</p>
                    <p class="cd-home-rhythm-line" aria-label="A calmer calendar, a better-prepared team and a clean close of day">
                        <span>A calmer calendar</span>
                        <i aria-hidden="true"></i>
                        <span>A better-prepared team</span>
                        <i aria-hidden="true"></i>
                        <span>A clean close</span>
                    </p>
                </div>
            </PublicContainer>
        </section>

        <section id="workflow" class="cd-home-story scroll-mt-24">
            <PublicContainer>
                <div class="cd-home-story-heading">
                    <p class="cd-eyebrow">The day, simplified</p>
                    <h2 class="mt-4 font-display text-[clamp(2.7rem,5.5vw,5.7rem)] font-semibold leading-[0.94] tracking-[-0.06em] text-[var(--text-strong)] text-balance">Less admin between the moments that <em>matter.</em></h2>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-[var(--text-muted)]">ClipperDesk keeps the working day connected in the background, so the experience out front can feel effortless.</p>
                </div>
                <div class="cd-home-story-moments">
                    <article v-for="moment in storyMoments" :key="moment.title" class="cd-home-story-moment">
                        <p class="cd-home-story-label">{{ moment.moment }}</p>
                        <h3>{{ moment.title }}</h3>
                        <p>{{ moment.text }}</p>
                    </article>
                </div>

                <div class="cd-home-story-note">
                    <span aria-hidden="true">✦</span>
                    <p><strong>One continuous record.</strong> No repeated entry. No end-of-day detective work.</p>
                    <Link :href="route('marketing.features')">See how the product connects <ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                </div>
            </PublicContainer>
        </section>

        <section id="proof" class="scroll-mt-24 bg-white py-16 sm:py-24 lg:py-28">
            <PublicContainer class="space-y-20 lg:space-y-28">
                <article class="cd-home-feature grid gap-10 lg:grid-cols-[0.78fr_1.22fr] lg:items-center">
                    <div class="max-w-lg">
                        <p class="cd-eyebrow">Calendar &amp; front desk</p>
                        <h2 class="mt-4 font-display text-[clamp(2.2rem,3.4vw,3.35rem)] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-strong)] text-balance">The right appointment. With the right person.</h2>
                        <p class="mt-6 text-lg leading-8 text-[var(--text-muted)]">See appointments, staff time, blocks and walk-ins together, then handle changes through explicit operational workflows.</p>
                        <ul class="mt-7 space-y-3">
                            <li v-for="item in ['Staff-aware availability and resource checks', 'Calendar, walk-in queue and daily attention view', 'Controlled reschedule, cancellation and status changes']" :key="item" class="flex gap-3 leading-7 text-[var(--text-default)]">
                                <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" />{{ item }}
                            </li>
                        </ul>
                        <Link :href="route('marketing.features.show', 'calendar-and-walk-ins')" class="mt-7 inline-flex min-h-11 items-center font-extrabold text-[var(--brand-secondary)] hover:underline">Explore calendar and walk-ins <ArrowRightIcon class="ml-2 size-4" aria-hidden="true" /></Link>
                    </div>
                    <figure class="cd-system-visual" @pointermove="updatePerspective" @pointerleave="resetPerspective">
                        <img src="/images/marketing/industries/barbershop.webp" alt="A busy modern barbershop balancing active services and waiting clients" width="1400" height="933" class="cd-system-visual-image" loading="lazy">
                    </figure>
                </article>

                <article class="cd-home-feature grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                    <figure class="cd-system-visual lg:order-1" @pointermove="updatePerspective" @pointerleave="resetPerspective">
                        <img src="/images/marketing/industries/nail-salon.webp" alt="A nail technician working with a returning client in a contemporary nail studio" width="1400" height="933" class="cd-system-visual-image" loading="lazy">
                    </figure>
                    <div class="max-w-lg lg:order-2 lg:justify-self-center">
                        <p class="cd-eyebrow">Client context</p>
                        <h2 class="mt-4 font-display text-[clamp(2.2rem,3.4vw,3.35rem)] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-strong)] text-balance">Know every client. Delight every visit.</h2>
                        <p class="mt-6 text-lg leading-8 text-[var(--text-muted)]">Give the team useful history and preferences without exposing private schedules, notes or secure tokens in the public experience.</p>
                        <ul class="mt-7 space-y-3">
                            <li v-for="item in ['Service and product history in one record', 'Preferences, allergies, forms and notes in context', 'Automated reminders and secure self-service']" :key="item" class="flex gap-3 leading-7 text-[var(--text-default)]">
                                <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-accent)]" aria-hidden="true" />{{ item }}
                            </li>
                        </ul>
                        <Link :href="route('marketing.features.show', 'online-booking')" class="mt-7 inline-flex min-h-11 items-center font-extrabold text-[var(--brand-secondary)] hover:underline">Explore the client experience <ArrowRightIcon class="ml-2 size-4" aria-hidden="true" /></Link>
                    </div>
                </article>

                <article class="cd-home-feature grid gap-10 lg:grid-cols-[0.78fr_1.22fr] lg:items-center">
                    <div class="max-w-lg">
                        <p class="cd-eyebrow">Checkout &amp; reporting</p>
                        <h2 class="mt-4 font-display text-[clamp(2.2rem,3.4vw,3.35rem)] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-strong)] text-balance">Collect with ease. Grow with insight.</h2>
                        <p class="mt-6 text-lg leading-8 text-[var(--text-muted)]">Completed work moves through services, products, tenders and receipts into reports that remain traceable to source records.</p>
                        <ul class="mt-7 space-y-3">
                            <li v-for="item in ['Clear checkout totals and tender history', 'Append-only corrections and refund evidence', 'Revenue, stock and commission reporting from source records']" :key="item" class="flex gap-3 leading-7 text-[var(--text-default)]">
                                <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--action-primary)]" aria-hidden="true" />{{ item }}
                            </li>
                        </ul>
                        <Link :href="route('marketing.features.show', 'checkout-and-reporting')" class="mt-7 inline-flex min-h-11 items-center font-extrabold text-[var(--brand-secondary)] hover:underline">Explore checkout and reporting <ArrowRightIcon class="ml-2 size-4" aria-hidden="true" /></Link>
                    </div>
                    <figure class="cd-system-visual" @pointermove="updatePerspective" @pointerleave="resetPerspective">
                        <img src="/images/marketing/industries/spa-sauna.webp" alt="A spa team preparing a guest treatment in a refined modern spa" width="1400" height="933" class="cd-system-visual-image" loading="lazy">
                    </figure>
                </article>
            </PublicContainer>
        </section>

        <section id="fit" class="scroll-mt-24 border-y border-[var(--border-subtle)] bg-white py-16 sm:py-20">
            <PublicContainer>
                <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div class="max-w-3xl">
                        <p class="cd-eyebrow">Built around real businesses</p>
                        <h2 class="mt-4 font-display text-[clamp(2.2rem,4.5vw,3.75rem)] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-strong)] text-balance">Different rooms. Different rhythms. One connected foundation.</h2>
                    </div>
                    <Link :href="route('marketing.solutions')" class="cd-button cd-button-secondary shrink-0">Explore all 13 business types</Link>
                </div>
                <div class="cd-home-business-grid mt-12">
                    <Link v-for="business in featuredBusinesses" :key="business.slug" :href="route('marketing.solutions.show', business.slug)" class="cd-home-business-card group">
                        <img :src="business.image" :alt="`${business.label} team at work`" width="1400" height="933" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                        <span class="cd-home-business-overlay"></span>
                        <span class="cd-home-business-copy"><strong>{{ business.label }}</strong><small>{{ business.text }}</small></span>
                        <ArrowRightIcon class="absolute right-5 top-5 size-5 text-white transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </Link>
                </div>
            </PublicContainer>
        </section>

        <section class="bg-white py-16 sm:py-24">
            <PublicContainer class="grid gap-12 lg:grid-cols-[0.92fr_1.08fr] lg:items-start">
                <div>
                    <p class="cd-eyebrow">A considered way to start</p>
                    <h2 class="mt-4 font-display text-[clamp(2.3rem,4.5vw,4rem)] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-strong)] text-balance">Set up the rules before opening the calendar.</h2>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-[var(--text-muted)]">Guided readiness covers business details, hours, services, staff, availability, booking policy, import, preview and publish.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <PublicCta context="homepage_plan_teaser" />
                        <Link :href="route('marketing.pricing')" class="cd-button cd-button-secondary">See pricing</Link>
                    </div>
                </div>
                <div id="questions" class="scroll-mt-24 rounded-[var(--radius-xl)] border border-indigo-200 bg-indigo-50/70 p-6 sm:p-8">
                    <p class="cd-eyebrow">Straight answers</p>
                    <h2 class="mt-3 font-display text-3xl font-semibold tracking-[-0.035em] text-[var(--text-strong)]">What owners usually want to know first</h2>
                    <div class="mt-6"><FaqList :items="faqs" /></div>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand
            title="Run the next working day with confidence."
            description="Start with a verified owner account, configure the business rules, and review the booking experience before publishing it."
            context="homepage_final"
        />
    </HomeLayout>
</template>
