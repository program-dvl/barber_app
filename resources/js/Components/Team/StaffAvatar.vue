<script setup>
import { ref, watch } from 'vue';
import { initialName } from '@/Support/teamWorkspace';
const props = defineProps({ name: String, photoUrl: String });
const failed = ref(false);
watch(() => props.photoUrl, () => { failed.value = false; });
</script>

<template>
    <span class="tm-avatar staff-avatar" aria-hidden="true">
        <img v-if="photoUrl && !failed" :src="photoUrl" alt="" loading="lazy" @error="failed = true" />
        <template v-else>{{ initialName(name || '') }}</template>
    </span>
</template>

<style scoped>
.staff-avatar { overflow: hidden; flex-shrink: 0; }
.staff-avatar img { width: 100%; height: 100%; object-fit: cover; }
</style>
