<script setup>
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import BusinessMessagesLayout from '@/Layouts/BusinessMessagesLayout.vue';
defineProps({businessLabel:String,inbox:Object});
</script>
<template><BusinessMessagesLayout :business-label="businessLabel" :inbox="inbox"><div class="business-message-placeholder"><ChatBubbleLeftRightIcon aria-hidden="true"/><h2>Select a conversation</h2><p>Read and reply to customer messages here.</p></div></BusinessMessagesLayout></template>
<style scoped>.business-message-placeholder{min-height:620px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}.business-message-placeholder svg{width:36px;height:36px;color:var(--text-muted);margin-bottom:18px}.business-message-placeholder h2{font-size:17px;font-weight:600;color:var(--text-strong);margin-bottom:8px}.business-message-placeholder p{font-size:13px;color:var(--text-muted)}</style>
