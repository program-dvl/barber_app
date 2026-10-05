<script setup>
defineProps({ id: String, message: String });
</script>

<template>
    <span v-if="message" :id="id" class="cd-field-error block" role="alert">{{ message }}</span>
</template>
