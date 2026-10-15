<script setup>
import { computed } from 'vue';
import { InformationCircleIcon, CheckCircleIcon, ExclamationTriangleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';
const props = defineProps({
    tone: { type: String, default: 'info', validator: value => ['info', 'success', 'warning', 'error'].includes(value) },
    title: String,
    description: String,
    urgent: Boolean,
});
const icon = computed(() => ({ info: InformationCircleIcon, success: CheckCircleIcon, warning: ExclamationTriangleIcon, error: ExclamationCircleIcon }[props.tone]));
</script>

<template>
    <div class="cd-notice" :data-tone="tone" :role="tone === 'error' || urgent ? 'alert' : 'status'">
        <span class="cd-notice-icon"><component :is="icon" aria-hidden="true" /></span>
        <div class="cd-notice-content">
            <p v-if="title" class="cd-notice-title">{{ title }}</p>
            <div v-if="description || $slots.default" class="cd-notice-body"><slot>{{ description }}</slot></div>
            <div v-if="$slots.actions" class="cd-notice-actions"><slot name="actions" /></div>
        </div>
        <slot name="trailing" />
    </div>
</template>

<style scoped>
.cd-notice { --notice-accent:var(--action-primary); display:grid; grid-template-columns:32px minmax(0,1fr); gap:14px; padding:18px 20px; border:1px solid var(--border-subtle); border-radius:12px; background:var(--surface-raised); color:var(--text-default); text-align:left; }
.cd-notice[data-tone="success"] { --notice-accent:var(--status-success); }
.cd-notice[data-tone="warning"] { --notice-accent:var(--status-warning); }
.cd-notice[data-tone="error"] { --notice-accent:var(--status-danger); }
.cd-notice-icon { display:grid; place-items:center; width:32px; height:32px; border-radius:10px; background:color-mix(in srgb,var(--notice-accent) 8%,var(--surface-raised)); color:var(--notice-accent); }
.cd-notice-icon svg { width:19px; height:19px; }
.cd-notice-content { min-width:0; padding-block:3px; }
.cd-notice-title { margin:0; color:var(--text-strong); font-size:14px; line-height:1.5; font-weight:650; }
.cd-notice-body { font-size:13px; line-height:1.65; overflow-wrap:anywhere; }
.cd-notice-title + .cd-notice-body { margin-top:4px; }
.cd-notice-body :deep(p + p) { margin-top:6px; }
.cd-notice-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:14px; }
.cd-notice-actions :deep(a),.cd-notice-actions :deep(button) { max-width:100%; }
@media(max-width:480px) { .cd-notice { grid-template-columns:24px minmax(0,1fr); gap:10px; padding:16px; } .cd-notice-icon { width:24px; height:28px; background:transparent; } .cd-notice-actions { gap:10px; } }
</style>
