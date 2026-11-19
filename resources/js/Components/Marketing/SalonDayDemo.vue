<script setup>
import { ref, nextTick } from 'vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import { ArrowRightIcon, ArrowPathIcon, ScissorsIcon } from '@heroicons/vue/24/outline';
const stage = ref('ready');
const chosen = ref('');
const firstSlot = ref(null);
const findButton = ref(null);
const staff = ['Alex', 'Sam', 'Taylor'];
const times = ['10:00', '10:30', '11:00', '11:30'];
const free = (person, index) => (person === 'Alex' && index === 1) || (person === 'Taylor' && index === 2);
async function findTime() { stage.value = 'choose'; await nextTick(); firstSlot.value?.focus(); }
function book(person, time) { if (stage.value !== 'choose') return; chosen.value = `${person} at ${time}`; stage.value = 'done'; }
async function replay() { chosen.value = ''; stage.value = 'ready'; await nextTick(); findButton.value?.focus(); }
</script>
<template>
    <section id="sample-day" class="salon-demo" aria-labelledby="demo-heading">
        <PublicContainer class="salon-demo-layout">
            <div class="salon-demo-intro">
                <p class="salon-eyebrow">Interactive demo · Sample data</p>
                <h2 id="demo-heading">A walk-in arrives.<br>You’ve got this.</h2>
                <p>Try it with sample appointments.</p>
                <div class="salon-visit"><ScissorsIcon aria-hidden="true" /><div><strong>Jamie</strong><span>Haircut · 30 minutes</span></div></div>
                <div class="salon-actions">
                    <button ref="findButton" v-if="stage !== 'done'" type="button" class="cd-button salon-demo-primary" @click="findTime">{{ stage === 'ready' ? 'Find a time' : 'Show available times' }} <ArrowRightIcon aria-hidden="true" /></button>
                    <button type="button" class="salon-replay" @click="replay"><ArrowPathIcon aria-hidden="true" /> Replay</button>
                </div>
            </div>
            <div class="salon-demo-board">
                <div class="salon-schedule" role="group" aria-label="Sample staff availability, 30-minute appointments">
                    <div class="salon-schedule-head"><span>Team</span><span v-for="time in times" :key="time">{{ time }}</span></div>
                    <div v-for="person in staff" :key="person" class="salon-schedule-row"><strong>{{ person }}</strong>
                        <template v-for="(time, index) in times" :key="time">
                            <button v-if="free(person,index)" :ref="el => { if(person === 'Alex') firstSlot = el; }" type="button" :disabled="stage !== 'choose'" class="salon-slot" :class="{ 'is-chosen': chosen === `${person} at ${time}`, 'is-available': stage === 'choose' }" :aria-label="`Choose ${person} at ${time} for Jamie’s 30-minute haircut`" @click="book(person,time)"><span>{{ chosen === `${person} at ${time}` ? 'Jamie' : time }}</span><small>{{ chosen === `${person} at ${time}` ? 'Haircut' : 'Available' }}</small></button>
                            <div v-else class="salon-booked" :class="{ 'salon-booked-cyan': index > 1 }"><span class="sr-only">{{ person }} at {{ time }}: </span>Booked</div>
                        </template>
                    </div>
                </div>
                <div class="salon-demo-outcome">
                    <AppNotice v-if="stage === 'done'" tone="success" title="Jamie’s in the sample day." :description="`A 30-minute haircut with ${chosen}. Your existing appointments stay in place. This is a demo; no real booking was made.`" />
                    <p v-else aria-live="polite">{{ stage === 'choose' ? 'Two times fit. Choose an available time to add Jamie.' : 'Find a time, choose a slot, and see Jamie join the day.' }}</p>
                </div>
                <p class="salon-demo-footnote">A simplified example. Your live calendar uses your services, working hours and staff availability.</p>
                <noscript><p>The interactive example needs JavaScript. Explore the booking and walk-in features below, or start your trial.</p></noscript>
            </div>
        </PublicContainer>
    </section>
</template>
