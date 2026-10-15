<script setup>
import AppNotice from '@/Components/Product/AppNotice.vue';
defineProps({ text: String, title: String });
</script>

<template>
    <AppNotice tone="success" :title="title" :description="text" />
</template>
