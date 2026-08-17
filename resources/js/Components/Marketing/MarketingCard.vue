<script setup>
defineProps({ as: { type: String, default: 'article' } });
</script>

<template><component :is="as" class="gh-marketing-card"><slot /></component></template>
