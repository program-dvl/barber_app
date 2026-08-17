<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import ProofFrame from '@/Components/Marketing/ProofFrame.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BanknotesIcon,
    CalendarDaysIcon,
    ChartBarSquareIcon,
    CheckCircleIcon,
    ClockIcon,
    QueueListIcon,
    ShieldCheckIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import dashboardImage from '../../../docs/evidence/product-shell/good-hours-shop-dashboard-desktop-1488.png';
import bookingImage from '../../../docs/evidence/product-shell/good-hours-public-booking-360.png';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const outcomes = [
    { title: 'Get booked', text: 'Offer a mobile booking path with current services, staff choices, policies and secure self-service links.', icon: CalendarDaysIcon, feature: 'online-booking' },
    { title: 'Protect the calendar', text: 'Recheck staff time and required rooms, chairs or equipment before a booking is committed.', icon: ShieldCheckIcon, feature: 'calendar-and-walk-ins' },
    { title: 'Run the day', text: 'Keep appointments, blocks, arrivals and walk-ins visible to the front desk in one operational view.', icon: QueueListIcon, feature: 'calendar-and-walk-ins' },
    { title: 'Get paid', text: 'Move completed work through checkout, tenders, receipts and traceable corrections without erasing history.', icon: BanknotesIcon, feature: 'checkout-and-reporting' },
    { title: 'Know clients', text: 'Carry visits, preferences, notes, forms and consent evidence forward with role-aware access.', icon: UserGroupIcon, feature: 'client-management' },
    { title: 'Know the business', text: 'Trace daily sales, payment, stock and commission totals back to the records that created them.', icon: ChartBarSquareIcon, feature: 'checkout-and-reporting' },
];

const workflow = [
    ['01', 'A client finds a valid time', 'Availability considers location hours, staff qualification and required resources.'],
    ['02', 'The booking becomes an operational record', 'The day view carries the service lines, timing, status and client context forward.'],
    ['03', 'The team handles changes explicitly', 'Reschedules, walk-ins, late work and closures follow controlled, recoverable workflows.'],
    ['04', 'Checkout closes the loop', 'Completed services, tenders, receipts and corrections feed client history and reporting.'],
];

const faqs = [
    {
        question: 'What is Good Hours?',
        answer: 'Good Hours is the daily operating system for salons and barbershops. It connects the work from booking and scheduling through client context, checkout and day-to-day reporting.',
    },
    {
        question: 'Who is Good Hours built for?',
        answer: 'The first release is designed for independent professionals and single-location salons and barbershops, including busy teams with a shared front desk. The product is location-aware without assuming a large franchise operation.',
    },
    {
        question: 'How is this different from a booking-only tool?',
        answer: 'Booking is the start of the record, not the end. Good Hours carries the appointment into the calendar, walk-in operations, client history, checkout, inventory and traceable reporting so the day is not split across disconnected tools.',
    },
    {
        question: 'Are payments and reminders ready for live use?',
        answer: 'The application workflows and provider boundaries are implemented and locally verified. Live card payments, email and WhatsApp sending still require approved provider accounts, callbacks and production certification before a public launch.',
    },
    {
        question: 'Can I bring existing data with me?',
        answer: 'Good Hours includes reviewed CSV imports for clients, staff, services and products. Imports preview validation errors and duplicate candidates before accepted rows become operational records.',
    },
];
</script>

<template>
    <HomeLayout>
        <Head title="Salon and barbershop software from booking to checkout" />

        <section class="relative overflow-hidden border-b border-[var(--border-subtle)]">
            <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-0 h-[28rem] bg-[radial-gradient(circle_at_80%_0%,rgba(242,184,128,0.34),transparent_45%),radial-gradient(circle_at_10%_35%,rgba(229,106,77,0.14),transparent_32%)]"></div>
            <PublicContainer class="relative z-10 grid gap-12 py-16 sm:py-22 lg:grid-cols-[0.92fr_1.08fr] lg:items-center lg:py-28">
                <div class="max-w-2xl">
                    <p class="gh-eyebrow">Good Hours for salons and barbershops</p>
                    <h1 class="mt-5 font-display text-[clamp(3rem,7vw,5.7rem)] font-semibold leading-[0.94] tracking-[-0.05em] text-[var(--text-strong)] text-balance">
                        Run your salon or barbershop from booking to checkout.
                    </h1>
                    <p class="mt-7 max-w-xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">
                        Keep the calendar, clients, staff, checkout and the day ahead connected in one calm operating system.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <PublicCta context="homepage_hero" />
                        <Link :href="route('marketing.features')" class="gh-button gh-button-secondary">Explore features</Link>
                    </div>
                    <p class="mt-5 max-w-xl text-sm leading-6 text-[var(--text-muted)]">
                        Start with a verified owner account and a clearly dated trial. Live payment and messaging depend on approved provider setup.
                    </p>
                </div>

                <ProofFrame caption="Synthetic Good Hours dashboard showing the command desk for a salon day. No customer data is used.">
                    <img
                        :src="dashboardImage"
                        alt="Good Hours dashboard with today’s appointments, walk-in queue and operational priorities"
                        width="1488"
                        height="1058"
                        class="aspect-[8/5] w-full rounded-xl object-cover object-left-top"
                        fetchpriority="high"
                    >
                </ProofFrame>
            </PublicContainer>
        </section>

        <section id="outcomes" class="gh-public-section scroll-mt-24">
            <PublicContainer>
                <SectionHeading
                    eyebrow="One connected record"
                    title="Six outcomes. One working day."
                    description="Good Hours follows the client journey instead of leaving each team to reconcile separate booking, notes and payment tools."
                />
                <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <MarketingCard v-for="outcome in outcomes" :key="outcome.title">
                        <component :is="outcome.icon" class="size-7 text-[var(--action-primary)]" aria-hidden="true" />
                        <h2 class="mt-5 text-xl font-extrabold text-[var(--text-strong)]">{{ outcome.title }}</h2>
                        <p class="mt-3 leading-7 text-[var(--text-muted)]">{{ outcome.text }}</p>
                        <Link :href="route('marketing.features.show', outcome.feature)" class="mt-4 inline-flex min-h-11 items-center rounded-lg text-sm font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">See how it works</Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>

        <section id="workflow" class="gh-public-section scroll-mt-24 bg-[var(--brand-pine)] text-white">
            <PublicContainer class="grid gap-14 lg:grid-cols-[0.82fr_1.18fr] lg:items-start">
                <div class="lg:sticky lg:top-28">
                    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-[var(--brand-apricot)]">The operating loop</p>
                    <h2 class="mt-4 font-display text-4xl leading-tight tracking-tight sm:text-5xl">A booking should make the rest of the day clearer.</h2>
                    <p class="mt-6 max-w-lg leading-8 text-white/76">
                        Good Hours carries the same governed record through capacity, front-desk changes, service, payment and reporting.
                    </p>
                </div>
                <ol class="space-y-4">
                    <li v-for="item in workflow" :key="item[0]" class="grid gap-4 rounded-[var(--radius-lg)] border border-white/15 bg-white/7 p-6 sm:grid-cols-[3rem_1fr]">
                        <span class="font-display text-2xl text-[var(--brand-apricot)]" aria-hidden="true">{{ item[0] }}</span>
                        <div>
                            <h3 class="text-lg font-extrabold">{{ item[1] }}</h3>
                            <p class="mt-2 leading-7 text-white/72">{{ item[2] }}</p>
                        </div>
                    </li>
                </ol>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer class="grid gap-14 lg:grid-cols-2 lg:items-center">
                <ProofFrame caption="Synthetic mobile booking flow showing the focused client experience, separate from the Good Hours marketing site.">
                    <div class="flex min-h-[34rem] items-center justify-center bg-[var(--surface-subtle)] px-5 py-8">
                        <img
                            :src="bookingImage"
                            alt="Mobile Good Hours booking page for choosing a location and service"
                            width="360"
                            height="1208"
                            class="h-auto max-h-[38rem] w-auto rounded-[1.4rem] shadow-[var(--shadow-overlay)]"
                            loading="lazy"
                        >
                    </div>
                </ProofFrame>
                <div>
                    <SectionHeading
                        eyebrow="Client-simple, operator-controlled"
                        title="A focused booking path backed by the same scheduling rules."
                        description="Clients see the choices and policy they need. Private staff schedules, internal notes and secure tokens remain out of the public experience."
                    />
                    <ul class="mt-8 space-y-4">
                        <li v-for="item in ['Mobile service, staff and time selection', 'Commit-time capacity revalidation', 'Secure, expiring appointment self-service', 'Waitlist offers that cannot overbook the opening']" :key="item" class="flex gap-3 leading-7">
                            <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--status-success)]" aria-hidden="true" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </PublicContainer>
        </section>

        <section id="fit" class="gh-public-section scroll-mt-24 border-y border-[var(--border-subtle)] bg-[var(--surface-subtle)]">
            <PublicContainer>
                <SectionHeading
                    eyebrow="Designed around real operating patterns"
                    title="For independent professionals and busy shared front desks."
                    description="The same connected foundation supports appointment-led salons, walk-in-heavy barbershops, independent operators and small teams that schedule rooms or equipment."
                />
                <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div v-for="fit in [
                        ['Barbershops', 'Coordinate walk-ins, booked clients, staff columns and fast checkout.'],
                        ['Salons', 'Keep multi-service appointments, client context and team schedules together.'],
                        ['Independent stylists', 'Move from guided setup to booking, service and payment without a separate admin team.'],
                        ['Small spas', 'Reserve required rooms or equipment alongside qualified staff without making medical claims.'],
                    ]" :key="fit[0]" class="rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-6">
                        <h2 class="text-lg font-extrabold text-[var(--text-strong)]">{{ fit[0] }}</h2>
                        <p class="mt-3 text-sm leading-7 text-[var(--text-muted)]">{{ fit[1] }}</p>
                    </div>
                </div>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer class="grid gap-10 lg:grid-cols-[1fr_0.85fr] lg:items-center">
                <SectionHeading
                    eyebrow="A considered way to start"
                    title="Set up the rules before opening the calendar."
                    description="Guided readiness covers business details, hours, services, staff, availability, booking policy, import, preview and publish. Reviewed CSV imports can bring clients, staff, services and products across without silently merging uncertain records."
                />
                <MarketingCard>
                    <ClockIcon class="size-7 text-[var(--action-primary)]" aria-hidden="true" />
                    <h2 class="mt-5 text-xl font-extrabold text-[var(--text-strong)]">Trial and plans</h2>
                    <p class="mt-3 leading-7 text-[var(--text-muted)]">
                        A verified owner starts a clearly dated trial. Starter and Pro support monthly or annual billing through server-enforced entitlements—not scattered plan-name checks.
                    </p>
                    <PublicCta context="homepage_plan_teaser" class="mt-6" />
                </MarketingCard>
            </PublicContainer>
        </section>

        <section id="questions" class="gh-public-section scroll-mt-24 bg-[var(--surface-raised)]">
            <PublicContainer>
                <SectionHeading eyebrow="Straight answers" title="What owners usually want to know first" />
                <div class="mt-10"><FaqList :items="faqs" /></div>
            </PublicContainer>
        </section>

        <ConversionBand
            title="Make the next working day easier to run."
            description="Start with a verified owner account, configure the business rules, and review the booking experience before publishing it."
            context="homepage_final"
        />
    </HomeLayout>
</template>
