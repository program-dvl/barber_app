<script setup>
import { Link } from '@inertiajs/vue3';
import ProductMark from '@/Components/Product/ProductMark.vue';
</script>

<template>
    <Link :href="'/'" class="inline-flex min-h-11 items-center justify-center rounded-lg" aria-label="Good Hours home">
        <ProductMark />
    </Link>
</template>
