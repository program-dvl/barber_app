<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import clipperDeskMark from '../../../images/brand/clipperdesk-mark.svg';

const page = usePage();
const productName = computed(() => page.props.brand?.product_name ?? 'ClipperDesk');
</script>

<template>
    <img :src="clipperDeskMark" :alt="productName" class="h-10 w-10 object-contain">
</template>
