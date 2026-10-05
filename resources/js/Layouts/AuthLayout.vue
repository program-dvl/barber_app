<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import ProductMark from '@/Components/Product/ProductMark.vue';

defineProps({
    title: { type: String, default: 'ClipperDesk' },
    eyebrow: { type: String, default: 'Your whole day, beautifully run' },
    heading: { type: String, default: 'A calmer way to run every working day.' },
    description: { type: String, default: 'Bookings, clients, staff, checkout and reporting stay connected around the people you serve.' },
    image: { type: String, default: '/images/marketing/editorial/product-workday.webp' },
    imageAlt: { type: String, default: 'A salon team reviewing the working day together.' },
    imageCaption: { type: String, default: 'Designed for real people-powered businesses.' },
});
</script>

<template>
    <div class="cd-auth-shell min-h-screen text-[var(--text-default)] lg:grid lg:grid-cols-[minmax(28rem,1.02fr)_minmax(34rem,0.98fr)]">
        <Head :title="title" />
        <aside class="cd-auth-visual hidden min-h-screen overflow-hidden text-white lg:flex lg:flex-col">
            <img class="cd-auth-visual-image" :src="image" :alt="imageAlt" width="1536" height="1024" fetchpriority="high" />
            <div class="cd-auth-visual-overlay" aria-hidden="true"></div>
            <Link href="/" class="relative z-10 inline-flex w-fit min-h-11 items-center" aria-label="ClipperDesk home"><ProductMark inverse large /></Link>

            <div class="relative z-10 mt-auto max-w-xl pt-20">

                <h2 class="mt-4 max-w-lg text-3xl font-semibold leading-tight tracking-tight">{{ heading }}</h2>
                <p class="mt-6 max-w-lg text-base leading-6 text-white/78">{{ description }}</p>

            </div>


        </aside>

        <main class="cd-auth-main relative flex min-h-screen items-center justify-center px-4 py-7 sm:px-7 lg:px-10 lg:py-10 xl:px-14">
            <div class="w-full max-w-2xl">
                <div class="mb-8 flex items-center justify-between gap-4">
                    <Link href="/" class="inline-flex min-h-11 items-center lg:hidden" aria-label="ClipperDesk home"><ProductMark large /></Link>
                    <Link href="/" class="cd-auth-back ml-auto hidden min-h-11 items-center gap-2 text-sm font-bold text-[var(--text-muted)] hover:text-[var(--text-strong)] lg:inline-flex"><ArrowLeftIcon class="size-4" aria-hidden="true" />Back to website</Link>
                </div>
                <slot />

            </div>
        </main>
    </div>
</template>
