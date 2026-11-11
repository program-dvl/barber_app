<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDaysIcon, ClockIcon, MapPinIcon, HeartIcon, ArrowUpRightIcon, PlusIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import MarketplaceAccountLayout from '@/Layouts/MarketplaceAccountLayout.vue';
const props = defineProps({ bookings: Object, favourites: Array, view: String });
const history = computed(() => props.view === 'history');
const status = value => ({confirmed:'Booked',pending_approval:'Awaiting approval',cancelled_by_shop:'Cancelled by business',cancelled_by_client:'Cancelled',rescheduled:'Rescheduled',completed:'Completed',no_show:'Missed visit'}[value] || value.replaceAll('_',' '));
const format = (booking, options) => new Intl.DateTimeFormat(undefined,{...options,timeZone:booking.time_zone}).format(new Date(booking.starts_at));
const manage = useForm({});
</script>
<template>
    <MarketplaceAccountLayout title="My bookings" description="Manage your appointments and saved businesses." active="bookings">
        <template #actions><Link href="/marketplace" class="mp-button"><PlusIcon aria-hidden="true"/>Find an appointment</Link></template>
        <nav class="mp-account-tabs" aria-label="Choose booking view"><Link href="/marketplace/account?view=upcoming" :aria-current="!history?'page':undefined">Upcoming visits</Link><Link href="/marketplace/account?view=history" :aria-current="history?'page':undefined">Past visits</Link></nav>
        <div class="mp-account-columns"><section aria-label="Your appointments">
            <div v-if="bookings.data.length" class="mp-account-bookings"><article v-for="(booking,index) in bookings.data" :key="booking.id" class="mp-visit-card">
                <div v-if="!history && index===0 && bookings.current_page===1" class="mp-visit-next"><CalendarDaysIcon aria-hidden="true"/>Your next visit</div>
                <div class="mp-visit-main"><div class="mp-visit-date" aria-hidden="true"><span>{{format(booking,{month:'short'})}}</span><strong>{{format(booking,{day:'numeric'})}}</strong><small>{{format(booking,{weekday:'short'})}}</small></div><div><div class="mp-visit-heading"><h2>{{booking.salon}}</h2><span class="mp-visit-status">{{status(booking.status)}}</span></div><p class="mp-visit-services">{{booking.services.join(' · ') || 'Your appointment'}}</p><div class="mp-visit-meta"><time :datetime="booking.starts_at"><ClockIcon aria-hidden="true"/><span>{{format(booking,{dateStyle:'full',timeStyle:'short'})}}<small>{{booking.time_zone.replaceAll('_',' ')}}</small></span></time><span v-if="booking.address"><MapPinIcon aria-hidden="true"/>{{booking.address}}</span></div></div></div>
                <footer class="mp-visit-footer"><span class="mp-visit-reference">Booking {{booking.reference}}</span><div class="mp-actions"><button class="mp-button" :disabled="manage.processing" @click="manage.post(`/marketplace/account/bookings/${booking.id}/manage`)">Manage booking</button><Link class="mp-button secondary" :href="`/marketplace/account/bookings/${booking.id}/messages`"><ChatBubbleLeftRightIcon aria-hidden="true"/>Messages{{booking.unread?` (${booking.unread})`:''}}</Link><Link :href="booking.salon_url" class="mp-button quiet">Book again</Link></div></footer>
            </article></div>
            <div v-else class="mp-account-empty"><span><CalendarDaysIcon aria-hidden="true"/></span><h2>{{history?'A little history, in time.':'Make room for a good day.'}}</h2><p>{{history?'Your past visits will appear here, ready whenever you want to book again.':'Book your next visit with your account email and it will appear here automatically.'}}</p><div class="mp-actions"><Link href="/marketplace" class="mp-button">Explore businesses<ArrowUpRightIcon aria-hidden="true"/></Link></div></div>
            <nav v-if="bookings.prev_page_url || bookings.next_page_url" class="mp-pagination" aria-label="Booking pages"><Link v-if="bookings.prev_page_url" :href="bookings.prev_page_url">Previous</Link><span>Page {{bookings.current_page}}</span><Link v-if="bookings.next_page_url" :href="bookings.next_page_url">Next</Link></nav>
        </section><aside><section id="favourites" class="mp-account-surface mp-favourites"><header class="mp-account-section-head"><h2>Saved for later</h2><HeartIcon aria-hidden="true"/></header><template v-if="favourites.length"><Link v-for="salon in favourites" :key="salon.url" :href="salon.url" class="mp-favourite-link"><span>{{salon.name}}</span><ArrowUpRightIcon aria-hidden="true"/></Link></template><div v-else class="mp-favourite-empty">Found somewhere you love? Save it from a business profile and keep it close.</div></section></aside></div>
    </MarketplaceAccountLayout>
</template>
