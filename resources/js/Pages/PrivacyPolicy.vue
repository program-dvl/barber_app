<script setup>
import LegalDocument from '@/Components/Marketing/LegalDocument.vue';

defineProps({ policy: String, document: Object });
</script>

<template>
    <LegalDocument :document="document" :content="policy" />
</template>
