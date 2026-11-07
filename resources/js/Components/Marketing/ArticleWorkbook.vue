<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
const props = defineProps({ slug: String });
const sheets = {
    'salon-consultation-notes-a-practical-template-and-example': { title: 'Salon consultation worksheet', intro: 'Use one sheet per visit. Record only information needed for the service and keep completed sheets private.', fields: ['Visit date and professional', 'Client reference and booked service', 'Client goal and preferences', 'Agreed result, service, time and price', 'Changes agreed during the visit', 'Result and question for next visit'], step: 'clients' },
    'how-to-increase-salon-rebooking-scripts-and-a-30-day-plan': { title: 'Weekly salon rebooking scorecard', intro: 'Use the same eligibility rules each week. Rebooking rate = visits rebooked before departure ÷ eligible completed visits × 100. Track completed returns separately.', fields: ['Week and eligibility rules', 'Eligible completed visits', 'Visits rebooked before departure', 'Rebooking rate (%)', 'Rebooked visits now due / completed / cancelled / missed', 'One change to try next week'], step: 'calendar' },
    'how-to-reduce-salon-no-shows-10-practical-strategies-that-work': { title: 'Weekly appointment attendance worksheet', intro: 'Count each appointment due once as attended, cancelled or no-show. No-show rate = no-shows ÷ appointments due × 100. Keep the same definitions each week.', fields: ['Week and location', 'Appointments due / attended', 'Cancelled appointments / no-shows', 'No-show rate (%)', 'Released slots filled again', 'Message delivery issues and next action'], step: 'booking' },
    'best-salon-management-software-in-2027-features-pricing-buying-guide': { title: 'Salon software trial checklist', intro: 'Try these tasks with fictional client details. Mark each Pass, Needs help or Unavailable. An essential unavailable feature should rule out an option.', fields: ['Provider, trial date and team size', 'Phone booking and appointment changes', 'Staff absence, break and conflicting booking', 'Walk-in before a reserved appointment', 'Client history and access permissions', 'Recorded payment, receipt and report total', 'Full subscription cost, usage charges and exit terms'], step: 'booking' },
    'how-to-manage-barbershop-walk-ins-without-delaying-bookings': { title: 'Barbershop walk-in shift checklist', intro: 'Check the entire service window, including reset time. Waits are estimates. Keep completed sheets private and avoid unnecessary client details.', fields: ['Shift, location and queue owner', 'Staff availability and next bookings checked', 'Service duration and reset time checked', 'Quoted wait range and actual start', 'Queue changes and affected appointments', 'Handoff and one improvement for tomorrow'], step: 'walk-ins' },
};
const sheet = computed(() => sheets[props.slug]);
const print = () => window.print();
</script>
<template>
    <section v-if="sheet" class="cd-article-workbook cd-public-section bg-[var(--surface-subtle)]" aria-labelledby="workbook-title">
        <PublicContainer>
            <div class="mx-auto max-w-3xl rounded-2xl border border-[var(--border-subtle)] bg-white p-6 sm:p-10">
                <p class="cd-eyebrow">A worksheet for your team</p>
                <h2 id="workbook-title" class="mt-4 font-display text-3xl font-semibold text-[var(--text-strong)]">{{ sheet.title }}</h2>
                <p class="mt-4 leading-7 text-[var(--text-muted)]">{{ sheet.intro }}</p>
                <ol class="mt-8 space-y-5">
                    <li v-for="(field, index) in sheet.fields" :key="field" class="break-inside-avoid">
                        <p class="font-semibold text-[var(--text-strong)]">{{ index + 1 }}. {{ field }}</p>
                        <div class="mt-3 h-10 border-b border-slate-300" aria-hidden="true" />
                    </li>
                </ol>
                <div class="workbook-actions mt-8 flex flex-wrap gap-3">
                    <button type="button" class="cd-button cd-button-primary" @click="print">Print worksheet or save as PDF</button>
                    <Link :href="`${route('marketing.product-tour')}#${sheet.step}`" class="cd-button cd-button-secondary">See the product workflow</Link>
                    <Link :href="route('marketing.solutions.show', slug.includes('barbershop-walk-ins') ? 'barbershops' : 'salons')" class="cd-button cd-button-secondary">Explore software for your team</Link>
                </div>
                <p class="mt-5 text-sm leading-6 text-[var(--text-muted)]">Blank working template · ClipperDesk · Adapt to your business. No entries are collected on this page.</p>
            </div>
        </PublicContainer>
    </section>
</template>
<style>
@media print {
    body:has(.cd-article-workbook) header,
    body:has(.cd-article-workbook) footer,
    body:has(.cd-article-workbook) nav,
    body:has(.cd-article-workbook) main > :not(article),
    body:has(.cd-article-workbook) article > :not(.cd-article-workbook),
    .cd-article-workbook .workbook-actions { display: none !important; }
    .cd-article-workbook { padding: 0 !important; background: white !important; }
    .cd-article-workbook > div > div { border: 0; padding: 0; }
    .cd-article-workbook h2 { font-size: 22pt; }
    .cd-article-workbook p { color: black !important; }
    .cd-article-workbook li { break-inside: avoid; }
}
</style>
