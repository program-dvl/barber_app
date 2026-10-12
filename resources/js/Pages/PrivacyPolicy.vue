<script setup>
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ content: String, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <div v-html="content" />
    </HomeLayout>
</template>
