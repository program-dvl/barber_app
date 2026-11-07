<script setup>
import { usePage } from '@inertiajs/vue3';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';

const page = usePage();
</script>

<template>
    <section class="cd-public-section border-y border-[var(--border-subtle)] bg-[var(--surface-subtle)]">
        <PublicContainer>
            <div class="grid gap-10 lg:grid-cols-2">
                <div>
                    <p class="text-sm font-bold text-[var(--brand-primary)]">Personal setup &amp; migration help</p>
                    <h2 class="mt-4 font-display text-3xl font-semibold leading-tight text-[var(--text-strong)] sm:text-4xl">Your business. Ready for bookings.</h2>
                    <p class="mt-5 max-w-xl leading-8 text-[var(--text-muted)]">Opening a new business or moving from another system? Let our team help with the setup, so you can focus on your clients.</p>
                    <div v-if="page.props.brand?.support_email" class="mt-6 flex flex-wrap items-center gap-3">
                        <a :href="`mailto:${page.props.brand.support_email}?subject=Setup%20and%20migration%20help`" class="cd-button cd-button-primary min-h-11">Email us for setup help</a>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-[var(--text-muted)]">Tell us about your business and what you use today. No client files needed in your first email.</p>
                </div>
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-bold text-[var(--text-strong)]">Starting fresh? We’ll help you set up.</h3>
                        <p class="mt-2 leading-7 text-[var(--text-muted)]">Get help preparing your services, prices, team, working hours and booking page, with a review before you go live.</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-[var(--text-strong)]">Switching? Bring your business with you.</h3>
                        <p class="mt-2 leading-7 text-[var(--text-muted)]">We help migrate existing clients, upcoming appointments and bookings. Have other records to bring across? We’ll review them with you and confirm what can be transferred from your current software or spreadsheets.</p>
                    </div>
                </div>
            </div>
        </PublicContainer>
    </section>
</template>
