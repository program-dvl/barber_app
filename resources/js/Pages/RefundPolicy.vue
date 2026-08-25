<script setup>
import LegalDocument from '@/Components/Marketing/LegalDocument.vue';

defineProps({ refund: String, document: Object });
</script>

<template>
    <LegalDocument :document="document" :content="refund" />
</template>
