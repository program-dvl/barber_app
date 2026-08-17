<script setup>
import StatePanel from '@/Components/Product/StatePanel.vue';
import SurfaceCard from '@/Components/Product/SurfaceCard.vue';
import PublicBookingLayout from '@/Layouts/PublicBookingLayout.vue';
</script>
<template><PublicBookingLayout title="Form received" mode="self-service"><SurfaceCard><StatePanel tone="success" title="Form received" description="Your answers were sent securely to the business. You can close this page now." /></SurfaceCard></PublicBookingLayout></template>
