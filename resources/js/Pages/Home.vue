<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import FaqList from '@/Components/Marketing/FaqList.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    ArrowRightIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    ChartBarSquareIcon,
    CheckCircleIcon,
    ClockIcon,
    DevicePhoneMobileIcon,
    EnvelopeIcon,
    QueueListIcon,
    ReceiptPercentIcon,
    RocketLaunchIcon,
    ScissorsIcon,
    UserGroupIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const activeModule = ref('Calendar');

const heroModules = [
    { label: 'Calendar', position: 'calendar' },
    { label: 'Clients', position: 'clients' },
    { label: 'Booking', position: 'booking' },
    { label: 'Checkout', position: 'checkout' },
    { label: 'Reporting', position: 'reporting' },
];

const heroSignals = [
    { title: 'An organised day', text: 'Appointments and walk-ins stay visible together.', icon: CalendarDaysIcon },
    { title: 'Clients in context', text: 'Visits, preferences and forms travel with the record.', icon: UserGroupIcon },
    { title: 'Traceable decisions', text: 'Checkout and reports stay tied to source activity.', icon: ChartBarSquareIcon },
    { title: 'Teams that flow', text: 'Roles, queues and notes keep everyone aligned.', icon: UserIcon },
    { title: 'A business that grows', text: 'Clear records turn each day into useful insight.', icon: RocketLaunchIcon },
];

const operatingLoop = [
    { label: 'Book', text: 'Clients choose a valid time.', icon: DevicePhoneMobileIcon },
    { label: 'Manage', text: 'The team runs one calendar.', icon: CalendarDaysIcon },
    { label: 'Serve', text: 'Client context stays at hand.', icon: ScissorsIcon },
    { label: 'Checkout', text: 'Payment closes the visit.', icon: ReceiptPercentIcon },
    { label: 'Grow', text: 'Records become clear reports.', icon: ChartBarSquareIcon },
];

const journey = [
    { label: 'Book', text: 'Clients book online from live availability.', icon: DevicePhoneMobileIcon },
    { label: 'Confirm', text: 'Policy and visit details stay attached.', icon: EnvelopeIcon },
    { label: 'Arrive', text: 'Appointments and walk-ins share one view.', icon: QueueListIcon },
    { label: 'Operate', text: 'Staff, time and resources stay coordinated.', icon: CalendarDaysIcon },
    { label: 'Checkout', text: 'Services, products and tenders stay traceable.', icon: BanknotesIcon },
    { label: 'Learn', text: 'Daily activity becomes useful reporting.', icon: ChartBarSquareIcon },
];

const audiences = [
    ['Barbershops', 'Coordinate booked clients, walk-ins, staff columns and checkout.', ScissorsIcon, 'barbershops'],
    ['Salons', 'Keep multi-service visits, client context and team schedules together.', CalendarDaysIcon, 'salons'],
    ['Independent stylists', 'Move from guided setup to booking and payment without a separate admin team.', UserGroupIcon, 'independent-stylists'],
    ['Small spas', 'Reserve required rooms or equipment alongside qualified staff.', ClockIcon, 'spas'],
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
        <Head title="Salon and barbershop software from booking to checkout" />

        <section class="cd-home-hero overflow-hidden border-b border-[var(--border-subtle)] bg-white">
            <PublicContainer class="grid gap-10 py-14 sm:py-20 lg:grid-cols-[0.78fr_1.22fr] lg:items-center lg:gap-8 lg:py-24">
                <div class="relative z-10 max-w-xl">
                    <p class="cd-eyebrow">ClipperDesk for salons &amp; barbershops</p>
                    <h1 class="mt-5 font-display text-[clamp(3.25rem,4.1vw,4rem)] font-bold leading-[0.94] tracking-[-0.06em] text-[var(--text-strong)] text-balance">
                        <span class="block lg:whitespace-nowrap">Run the day.</span><span class="block lg:whitespace-nowrap">Grow the business.</span>
                    </h1>
                    <p class="mt-7 max-w-lg text-lg leading-8 text-[var(--text-muted)] sm:text-xl">
                        Run your salon or barbershop from booking to checkout, with the calendar, clients, staff, payments and reporting connected in one calm operating system.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <PublicCta context="homepage_hero" />
                        <Link :href="route('marketing.features')" class="cd-button cd-button-secondary">
                            Explore features
                            <ArrowRightIcon class="ml-2 size-4" aria-hidden="true" />
                        </Link>
                    </div>
                    <p class="mt-5 max-w-lg text-sm leading-6 text-[var(--text-muted)]">
                        Start with a verified owner account and a clearly dated trial. Live payment and messaging depend on approved provider setup.
                    </p>
                </div>

                <figure class="min-w-0 lg:-mr-8">
                    <div class="cd-orbit-scene" @pointermove="updatePerspective" @pointerleave="resetPerspective">
                        <div class="cd-orbit-art">
                            <img src="/images/marketing/system/orbital-operations.webp" alt="Conceptual ClipperDesk operating system connecting calendar, clients, booking, checkout and reporting" width="1536" height="1120" class="cd-orbit-image" fetchpriority="high">
                        </div>
                        <button
                            v-for="module in heroModules"
                            :key="module.label"
                            type="button"
                            class="cd-orbit-label"
                            :class="[`cd-orbit-label-${module.position}`, { 'is-active': activeModule === module.label }]"
                            :aria-pressed="activeModule === module.label"
                            @mouseenter="activeModule = module.label"
                            @focus="activeModule = module.label"
                        >
                            <span>{{ module.label }}</span>
                            <span class="cd-orbit-label-state">{{ activeModule === module.label ? 'Connected now' : 'Connected' }}</span>
                        </button>
                        <div class="cd-orbit-status" aria-live="polite">
                            <span class="cd-orbit-status-dot" aria-hidden="true"></span>
                            {{ activeModule }} in the same operating loop
                        </div>
                    </div>
                    <figcaption class="sr-only">A conceptual overview of how ClipperDesk connects the working day. No customer data is used.</figcaption>
                </figure>
            </PublicContainer>

            <PublicContainer class="pb-14 sm:pb-20">
                <div class="cd-home-signal-grid">
                    <article v-for="signal in heroSignals" :key="signal.title" class="cd-home-signal">
                        <span class="cd-home-signal-icon"><component :is="signal.icon" class="size-5" aria-hidden="true" /></span>
                        <h2 class="mt-4 text-sm font-extrabold text-[var(--text-strong)]">{{ signal.title }}</h2>
                        <p class="mt-1 text-xs leading-5 text-[var(--text-muted)]">{{ signal.text }}</p>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <section id="workflow" class="scroll-mt-24 bg-white py-16 sm:py-20">
            <PublicContainer>
                <div class="mx-auto max-w-3xl text-center">
                    <p class="cd-eyebrow">One operating system. Five steps. Every working day.</p>
                    <h2 class="mt-4 font-display text-[clamp(2.2rem,4vw,3.5rem)] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-strong)] text-balance">A simple flow from first booking to better decisions.</h2>
                </div>
                <ol class="cd-home-loop-grid mt-12">
                    <li v-for="(step, index) in operatingLoop" :key="step.label" class="cd-home-loop-step">
                        <div class="flex items-center gap-3">
                            <span class="cd-home-loop-icon"><component :is="step.icon" class="size-5" aria-hidden="true" /></span>
                            <ArrowRightIcon v-if="index < operatingLoop.length - 1" class="ml-auto hidden size-4 text-indigo-300 lg:block" aria-hidden="true" />
                        </div>
                        <p class="mt-4 text-xs font-bold uppercase tracking-[0.14em] text-indigo-500">0{{ index + 1 }}</p>
                        <h3 class="mt-2 text-base font-extrabold text-[var(--text-strong)]">{{ step.label }}</h3>
                        <p class="mt-1 text-sm leading-6 text-[var(--text-muted)]">{{ step.text }}</p>
                    </li>
                </ol>
            </PublicContainer>
        </section>

        <section class="cd-home-journey">
            <PublicContainer>
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-cyan-200">One connected record</p>
                    <h2 class="mt-5 font-display text-[clamp(2.4rem,5vw,4.5rem)] font-semibold leading-[1] tracking-[-0.045em] text-white text-balance">From booking to business insight—connected.</h2>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-300">Each step adds context instead of starting again, so the team can act with confidence and owners can trace what happened.</p>
                </div>
                <ol class="mt-14 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <li v-for="(step, index) in journey" :key="step.label" class="cd-home-journey-step">
                        <div class="flex items-center justify-between gap-4">
                            <span class="cd-home-journey-icon"><component :is="step.icon" class="size-6" aria-hidden="true" /></span>
                            <span class="font-display text-sm font-semibold text-cyan-200">0{{ index + 1 }}</span>
                        </div>
                        <h3 class="mt-7 text-lg font-extrabold text-white">{{ step.label }}</h3>
                        <p class="mt-3 text-sm leading-6 text-slate-300">{{ step.text }}</p>
                    </li>
                </ol>
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
                        <img src="/images/marketing/system/scheduling-capacity.webp" alt="Conceptual scheduling system showing appointments, staff capacity and a walk-in entering one connected day plan" width="1536" height="1024" class="cd-system-visual-image" loading="lazy">
                    </figure>
                </article>

                <article class="cd-home-feature grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                    <figure class="cd-system-visual lg:order-1" @pointermove="updatePerspective" @pointerleave="resetPerspective">
                        <img src="/images/marketing/system/client-context.webp" alt="Conceptual client record with preferences, visit history, communication and loyalty context moving together" width="1536" height="1024" class="cd-system-visual-image" loading="lazy">
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
                        <img src="/images/marketing/system/checkout-insight.webp" alt="Conceptual checkout flow connecting a payment and receipt to traceable business reporting" width="1536" height="1024" class="cd-system-visual-image" loading="lazy">
                    </figure>
                </article>
            </PublicContainer>
        </section>

        <section id="fit" class="scroll-mt-24 border-y border-[var(--border-subtle)] bg-white py-16 sm:py-20">
            <PublicContainer>
                <div class="mx-auto max-w-3xl text-center">
                    <p class="cd-eyebrow">Built around real operating patterns</p>
                    <h2 class="mt-4 font-display text-[clamp(2.2rem,4.5vw,3.75rem)] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-strong)] text-balance">One connected foundation. Four distinct ways of working.</h2>
                </div>
                <div class="mt-12 grid gap-px overflow-hidden rounded-[var(--radius-xl)] border border-[var(--border-subtle)] bg-[var(--border-subtle)] md:grid-cols-2 xl:grid-cols-4">
                    <Link v-for="audience in audiences" :key="audience[0]" :href="route('marketing.solutions.show', audience[3])" class="group bg-white p-7 transition-colors hover:bg-indigo-50">
                        <component :is="audience[2]" class="size-7 text-[var(--action-primary)]" aria-hidden="true" />
                        <h3 class="mt-6 text-lg font-extrabold text-[var(--text-strong)]">{{ audience[0] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-[var(--text-muted)]">{{ audience[1] }}</p>
                        <span class="mt-5 inline-flex items-center text-sm font-extrabold text-[var(--brand-secondary)]">Explore fit <ArrowRightIcon class="ml-2 size-4 transition-transform group-hover:translate-x-1" aria-hidden="true" /></span>
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
