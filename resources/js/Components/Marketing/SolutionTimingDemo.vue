<script setup>
import { computed, ref } from 'vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
const props = defineProps({ experience: { type: Object, required: true } });
const selected = ref(0);
const choice = computed(() => props.experience.options[selected.value]);
const total = computed(() => choice.value.minutes + props.experience.buffer);
const fits = computed(() => total.value <= 60);
const clock = minutes => `${String(10 + Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
</script>
<template>
    <section id="sample-timing" class="experience-demo" aria-labelledby="timing-title">
        <PublicContainer class="experience-demo-grid">
            <div>
                <p class="cd-eyebrow">A little hands-on clarity</p>
                <h2 id="timing-title">{{ experience.demo_title }}</h2>
                <p>{{ experience.demo_body }}</p>
                <div class="experience-options" role="group" aria-label="Choose a sample service">
                    <button v-for="(option, index) in experience.options" :key="option.label" type="button" :aria-pressed="selected === index" @click="selected = index">{{ option.label }}<span>{{ option.minutes }} min</span></button>
                </div>
            </div>
            <div class="experience-timing">
                <div class="experience-clock"><span>10:00 · Start</span><span>11:00 · Next client</span><span>11:30</span></div>
                <div class="experience-track" aria-hidden="true">
                    <div class="experience-duration" :style="{ width: `${choice.minutes / 90 * 100}%` }"></div>
                    <div class="experience-buffer" :style="{ width: `${experience.buffer / 90 * 100}%` }"></div>
                    <span class="experience-deadline"></span>
                </div>
                <p class="experience-timing-detail">{{ choice.minutes }} minutes of service · {{ experience.buffer_label }}</p>
                <div aria-live="polite" aria-atomic="true">
                    <AppNotice :tone="fits ? 'success' : 'info'" :title="fits ? `Ready by ${clock(total)}` : `This would overlap by ${total - 60} minutes`">
                        <p>{{ fits ? `${60 - total} minutes remain before the next client. This example fits the time available.` : 'Choose a shorter service to see what fits, or look for a later opening in your real calendar.' }}</p>
                    </AppNotice>
                </div>
                <p class="experience-demo-note">Illustrative timing exercise. No appointment is created. Real availability also depends on staff, hours and configured resources.</p>
            </div>
        </PublicContainer>
    </section>
</template>
