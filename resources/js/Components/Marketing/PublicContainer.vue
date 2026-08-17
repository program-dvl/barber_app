<script setup>
defineProps({ as: { type: String, default: 'div' } });
</script>

<template><component :is="as" class="gh-public-container"><slot /></component></template>
