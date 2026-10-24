<script setup>
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import { Head, Link } from '@inertiajs/vue3';
import '../../../css/help-center.css';
defineProps({ action: Object, workspaces: Array, message: String });
</script>
<template><MarketingLayout page-class="help-center"><Head title="Choose a workspace" /><div class="help-container help-main"><nav class="help-breadcrumbs" aria-label="Breadcrumb"><Link href="/help">Help Center</Link><span aria-hidden="true">/</span><span>Open your workspace</span></nav><div class="help-workspaces"><h1>{{ action.label }}</h1><p class="help-article-summary">Choose the business you want to work in.</p><AppNotice v-if="message" class="my-6" title="Workspace access" :description="message" /><ul class="help-workspace-list"><li v-for="workspace in workspaces" :key="workspace.public_id || workspace.name"><strong>{{ workspace.name }}</strong><a v-if="workspace.allowed && workspace.href" :href="workspace.href" class="cd-button cd-button-primary">Open workspace</a><p v-else>{{ workspace.reason || 'Ask the business owner to review your access.' }}</p></li></ul><div class="help-actions"><Link href="/help" class="cd-button cd-button-secondary">Back to Help Center</Link><a href="/dashboard" class="cd-button cd-button-quiet">My dashboard</a></div></div></div></MarketingLayout></template>
