<script setup>
defineProps({ as: { type: String, default: 'div' } });
</script>

<template><component :is="as" class="cd-public-container"><slot /></component></template>
