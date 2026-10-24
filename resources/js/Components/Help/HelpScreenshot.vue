<script setup>
import { ref } from 'vue';
import { ArrowsPointingOutIcon, XMarkIcon } from '@heroicons/vue/24/outline';
defineProps({ media: Object });
const dialog = ref(null);
const trigger = ref(null);
const close = () => { dialog.value?.close(); trigger.value?.focus(); };
</script>
<template>
    <figure class="help-screenshot">
        <button ref="trigger" type="button" :aria-label="`Enlarge screenshot: ${media.alt}`" @click="dialog.showModal()">
            <img :src="media.url || media.path" :alt="media.alt" :width="media.width" :height="media.height" loading="lazy" decoding="async" />
            <span class="help-enlarge"><ArrowsPointingOutIcon class="size-4" aria-hidden="true" />Enlarge screenshot</span>
        </button>
        <figcaption>{{ media.caption }}</figcaption>
        <dialog ref="dialog" class="help-lightbox" :aria-label="media.alt" @click="event => { if (event.target === dialog) close(); }" @cancel.prevent="close">
            <div class="flex items-center justify-between gap-4 p-4"><p>{{ media.alt }}</p><button type="button" class="cd-icon-button shrink-0" aria-label="Close screenshot" @click="close"><XMarkIcon class="size-5" aria-hidden="true" /></button></div>
            <div class="help-lightbox-image"><img :src="media.url || media.path" :alt="media.alt" :width="media.width" :height="media.height" /></div>
            <p class="p-4 text-sm">{{ media.caption }}</p>
        </dialog>
    </figure>
</template>
