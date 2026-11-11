<script setup>
import {ref,watch,onMounted,onBeforeUnmount} from 'vue';
import {usePage} from '@inertiajs/vue3';
import AppNotice from './AppNotice.vue';
defineProps({business:Boolean});
const page=usePage();const remaining=ref(0);let timer;
const update=()=>{remaining.value=Math.max(0,Math.ceil(((page.props.marketplaceRetryAt||0)-Date.now())/1000));};
watch(()=>page.props.marketplaceRetryAt,update,{immediate:true});
onMounted(()=>{timer=setInterval(update,1000);});onBeforeUnmount(()=>clearInterval(timer));
</script>
<template><div><AppNotice v-if="page.props.errors?.marketplace" tone="warning" title="Let’s help you continue" :description="page.props.errors.marketplace"><template #actions><span v-if="remaining">Try again in {{remaining}} seconds</span><a :class="business?'cd-app-button cd-app-button-small inline-flex items-center justify-center border border-[var(--border-default)] bg-[var(--surface-raised)] px-3 text-sm font-semibold text-[var(--text-strong)]':'mp-button secondary'" :href="`mailto:${page.props.brand?.support_email || 'support@clipperdesk.com'}`">Contact support</a></template></AppNotice><fieldset :disabled="remaining>0||page.props.marketplaceBlocked" style="min-width:0;border:0;padding:0;margin:0"><slot /></fieldset></div></template>
