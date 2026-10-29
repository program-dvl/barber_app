<script setup>
import { computed, ref } from 'vue';
import { MapPinIcon, PhotoIcon, StarIcon } from '@heroicons/vue/24/outline';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppButton from '@/Components/Product/AppButton.vue';

const props = defineProps({ business: Object, location: Object, reviews: Object, hasTeam: Boolean });
const galleryDialog = ref(null), active = ref(0), failed = ref([]);
const photos = computed(() => {
    const gallery = props.business.gallery || [];
    const items = gallery.length ? gallery : props.business.has_cover_image && props.business.cover_url ? [{ url: props.business.cover_url, caption: props.business.cover_alt }] : [];
    return items.filter(item => !failed.value.includes(item.url));
});
const current = computed(() => photos.value[active.value] || photos.value[0]);
const showPhoto = index => { active.value = index; galleryDialog.value.open(); };
const initials = computed(() => props.business.name?.trim().split(/\s+/u).filter(part => /[\p{L}\p{N}]/u.test(part)).slice(0, 2).map(part => part.match(/[\p{L}\p{N}]/u)[0]).join(''));
</script>

<template>
    <header class="salon-showcase">
        <div class="salon-heading">
            <img v-if="business.logo_url" :src="business.logo_url" alt="" class="salon-logo" @error="$event.target.hidden = true" />
            <span v-else class="salon-monogram" aria-hidden="true">{{ initials }}</span>
            <div class="min-w-0">
                <p class="salon-eyebrow">Your next appointment</p>
                <h1>{{ business.name }}</h1>
                <div class="salon-facts">
                    <a v-if="reviews?.count" href="#salon-reviews" class="salon-rating"><StarIcon class="size-4" aria-hidden="true" /><strong>{{ reviews.average }}</strong><span>({{ reviews.count }} {{ reviews.count === 1 ? 'review' : 'reviews' }})</span></a>
                    <span v-if="location" class="inline-flex items-center gap-1.5"><MapPinIcon class="size-4 shrink-0" aria-hidden="true" />{{ location.name }}</span>
                </div>
            </div>
        </div>
        <p v-if="business.description" class="salon-description">{{ business.description }}</p>
        <div v-if="photos.length" class="salon-photos" :class="{ single: photos.length === 1, pair: photos.length === 2 }" aria-label="Salon photos">
            <button v-for="(photo, index) in photos.slice(0, 3)" :key="photo.url" type="button" :aria-label="`View photo ${index + 1}${photo.caption ? `: ${photo.caption}` : ''}`" @click="showPhoto(index)">
                <img :src="photo.url" :alt="photo.caption || `${business.name} photo ${index + 1}`" :loading="index ? 'lazy' : 'eager'" :fetchpriority="index ? 'auto' : 'high'" @error="failed.push(photo.url)" />
                <span v-if="index === Math.min(2, photos.length - 1)" class="salon-view-photos"><PhotoIcon class="size-4" aria-hidden="true" />{{ photos.length === 1 ? 'View photo' : `View all ${photos.length} photos` }}</span>
            </button>
        </div>
        <nav class="salon-navigation" aria-label="Explore this salon">
            <a href="#salon-services">Services</a>
            <a v-if="hasTeam" href="#salon-team">Team</a>
            <a v-if="reviews?.count" href="#salon-reviews">Reviews</a>
            <a href="#salon-about">About &amp; location</a>
        </nav>
        <AppDialog ref="galleryDialog" :title="`${business.name} photos`">
            <figure v-if="current" class="salon-full-photo">
                <img :src="current.url" :alt="current.caption || `${business.name} photo ${active + 1}`" />
                <figcaption v-if="current.caption">{{ current.caption }}</figcaption>
            </figure>
            <div v-if="photos.length > 1" class="salon-thumbnails" role="group" aria-label="Choose a salon photo">
                <button v-for="(photo, index) in photos" :key="photo.url" type="button" :aria-label="`Photo ${index + 1}`" :aria-pressed="active === index" @click="active = index"><img :src="photo.url" alt="" loading="lazy" /></button>
            </div>
            <template #footer><AppButton variant="secondary" @click="galleryDialog.close()">Close photos</AppButton></template>
        </AppDialog>
    </header>
</template>

<style scoped>
.salon-showcase { padding:8px 0 24px; }
.salon-heading { display:flex; align-items:center; gap:16px; }
.salon-logo,.salon-monogram { width:64px; height:64px; flex-shrink:0; border-radius:16px; object-fit:contain; background:var(--surface-raised); border:1px solid var(--border-subtle); }
.salon-monogram { display:grid; place-items:center; font-size:23px; font-weight:700; color:var(--action-primary); background:var(--surface-subtle); }
.salon-eyebrow { font-size:12px; font-weight:600; color:var(--text-muted); }
h1 { margin-top:3px; font-size:clamp(26px,4vw,40px); line-height:1.15; letter-spacing:-.035em; font-weight:700; color:var(--text-strong); overflow-wrap:anywhere; }
.salon-facts { display:flex; flex-wrap:wrap; align-items:center; gap:4px 18px; font-size:13px; color:var(--text-muted); margin-top:8px; }
.salon-rating { display:inline-flex; align-items:center; gap:5px; min-height:44px; color:var(--text-strong); }
.salon-rating svg { fill:var(--status-warning); color:var(--status-warning); }
.salon-description { max-width:52rem; font-size:14px; line-height:1.75; margin-top:16px; color:var(--text-muted); white-space:pre-line; }
.salon-photos { display:grid; grid-template-columns:2fr 1fr; grid-template-rows:155px 155px; gap:10px; margin-top:24px; border-radius:18px; overflow:hidden; }
.salon-photos button { position:relative; overflow:hidden; min-width:0; background:var(--surface-subtle); }
.salon-photos button:first-child { grid-row:span 2; }
.salon-photos.single { grid-template-columns:1fr; }
.salon-photos.pair button:nth-child(2) { grid-row:span 2; }
.salon-photos img { width:100%; height:100%; object-fit:cover; transition:transform 180ms ease; }
.salon-photos button:hover img { transform:scale(1.02); }
.salon-view-photos { display:inline-flex; align-items:center; justify-content:center; gap:7px; position:absolute; bottom:12px; right:12px; min-height:44px; padding:8px 12px; background:var(--surface-raised); border:1px solid var(--border-subtle); border-radius:10px; color:var(--text-strong); font-size:12px; font-weight:600; }
.salon-navigation { display:flex; flex-wrap:wrap; gap:6px 18px; margin-top:20px; border-bottom:1px solid var(--border-subtle); }
.salon-navigation a { min-height:44px; padding:10px 2px; font-size:14px; font-weight:600; color:var(--text-strong); }
.salon-full-photo img { width:100%; max-height:55vh; object-fit:contain; border-radius:10px; }
.salon-full-photo figcaption { margin-top:12px; font-size:14px; line-height:1.6; }
.salon-thumbnails { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
.salon-thumbnails button { width:64px; height:64px; border:2px solid transparent; border-radius:10px; padding:2px; }
.salon-thumbnails button[aria-pressed=true] { border-color:var(--action-primary); }
.salon-thumbnails img { width:100%; height:100%; object-fit:cover; border-radius:6px; }
a:focus-visible,button:focus-visible { outline:3px solid var(--focus-ring); outline-offset:3px; }
@media(max-width:639px) { .salon-heading { gap:12px; }.salon-logo,.salon-monogram { width:52px; height:52px; border-radius:12px; }.salon-photos { grid-template-rows:110px 110px; gap:6px; margin-top:18px; }.salon-photos.single { grid-template-rows:110px 110px; }.salon-view-photos { bottom:8px; right:8px; font-size:11px; max-width:calc(100% - 16px); padding:8px; }.salon-view-photos svg { display:none; }.salon-navigation { gap:4px 14px; }.salon-navigation a { font-size:13px; } }
@media(prefers-reduced-motion:reduce) { .salon-photos img { transition:none; }.salon-photos button:hover img { transform:none; } }
</style>
