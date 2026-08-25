<script setup>
import LegalDocument from '@/Components/Marketing/LegalDocument.vue';

defineProps({ terms: String, document: Object });
</script>

<template>
    <LegalDocument :document="document" :content="terms" />
</template>
