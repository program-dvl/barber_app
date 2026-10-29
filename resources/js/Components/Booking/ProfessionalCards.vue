<script setup>
import { ref } from 'vue';
import { CheckCircleIcon, UserIcon } from '@heroicons/vue/24/outline';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppButton from '@/Components/Product/AppButton.vue';

defineProps({ members: { type: Array, default: () => [] }, selectable: Boolean, modelValue: String, required: Boolean, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
const profile = ref(null), profileDialog = ref(null), failed = ref([]);
const initials = name => name?.trim().split(/\s+/u).slice(0, 2).map(part => [...part][0]).join('');
const showProfile = member => { profile.value = member; profileDialog.value.open(); };
</script>

<template>
    <div class="professionals" :class="{ selectable }" :role="selectable ? 'group' : undefined" :aria-label="selectable ? 'Choose your professional' : undefined">
        <button v-if="selectable && !required" type="button" class="professional-any" :aria-pressed="!modelValue" :disabled="disabled" @click="emit('update:modelValue', '')">
            <UserIcon class="size-6 shrink-0" aria-hidden="true" /><span><strong>Anyone available</strong><small>More appointment options</small></span><CheckCircleIcon v-if="!modelValue" class="size-5 shrink-0" aria-hidden="true" />
        </button>
        <article v-for="member in members" :key="member.public_id" class="professional" :class="{ selected: selectable && modelValue === member.public_id }">
            <component :is="selectable ? 'button' : 'div'" :type="selectable ? 'button' : undefined" class="professional-main" :aria-pressed="selectable ? modelValue === member.public_id : undefined" :disabled="selectable ? disabled : undefined" @click="selectable && emit('update:modelValue', member.public_id)">
                <img v-if="member.photo_url && !failed.includes(member.photo_url)" :src="member.photo_url" alt="" loading="lazy" @error="failed.push(member.photo_url)" />
                <span v-else class="professional-initials" aria-hidden="true">{{ initials(member.display_name) }}</span>
                <span class="professional-copy"><strong>{{ member.display_name }}</strong><span v-if="member.title">{{ member.title }}</span><small v-if="selectable && modelValue === member.public_id" class="professional-selected"><CheckCircleIcon class="size-4" aria-hidden="true" />Selected</small></span>
            </component>
            <p v-if="member.biography && !selectable" class="professional-bio">{{ member.biography }}</p>
            <button v-if="member.biography" class="professional-about" type="button" :aria-label="`About ${member.display_name}`" @click="showProfile(member)">About {{ member.display_name }}</button>
        </article>
        <AppDialog ref="profileDialog" :title="profile?.display_name || 'Professional profile'">
            <div v-if="profile" class="professional-detail">
                <img v-if="profile.photo_url && !failed.includes(profile.photo_url)" :src="profile.photo_url" :alt="profile.display_name" />
                <p v-if="profile.title" class="font-semibold">{{ profile.title }}</p><p v-if="profile.biography" class="mt-3 whitespace-pre-line text-sm leading-7">{{ profile.biography }}</p>
            </div>
            <template #footer><AppButton variant="secondary" @click="profileDialog.close()">Close profile</AppButton></template>
        </AppDialog>
    </div>
</template>

<style scoped>
.professionals { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.professional { min-width:0; border:1px solid var(--border-subtle); border-radius:14px; background:var(--surface-raised); padding:16px; }
.professional.selected { border-color:var(--action-primary); box-shadow:inset 0 0 0 1px var(--action-primary); }
.professional-main { display:flex; width:100%; align-items:center; gap:12px; text-align:left; min-height:64px; border-radius:8px; }
.professional-main img,.professional-initials { width:60px; height:60px; flex-shrink:0; border-radius:50%; object-fit:cover; }
.professional-initials { display:grid; place-items:center; background:var(--surface-subtle); font-size:19px; font-weight:700; color:var(--action-primary); }
.professional-copy { display:grid; gap:3px; min-width:0; }.professional-copy strong { color:var(--text-strong); font-size:14px; }.professional-copy>span { font-size:12px; color:var(--text-muted); }
.professional-selected { display:flex; align-items:center; gap:5px; font-size:12px; font-weight:600; color:var(--action-primary); }
.professional-bio { margin-top:12px; font-size:13px; line-height:1.7; color:var(--text-muted); display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
.professional-about { display:inline-flex; align-items:center; min-height:44px; margin-top:6px; font-size:12px; font-weight:600; color:var(--action-primary); text-align:left; }
.professional-any { grid-column:1/-1; display:flex; align-items:center; gap:12px; min-height:76px; padding:16px; border:1px solid var(--border-subtle); border-radius:14px; text-align:left; }.professional-any[aria-pressed=true] { border-color:var(--action-primary); box-shadow:inset 0 0 0 1px var(--action-primary); }.professional-any>span { flex:1; min-width:0; display:grid; gap:4px; }.professional-any strong { font-size:14px; }.professional-any small { color:var(--text-muted); font-size:12px; }
.professional-detail>img { width:104px; height:104px; border-radius:50%; object-fit:cover; margin-bottom:16px; }
button:focus-visible { outline:3px solid var(--focus-ring); outline-offset:3px; }button:disabled { opacity:.55; cursor:not-allowed; }
@media(max-width:480px) { .professionals { grid-template-columns:1fr; }.professional { padding:14px; } }
.professionals.selectable { grid-template-columns:repeat(2,minmax(0,1fr)); }
.selectable .professional { padding:12px; }
.selectable .professional-main { flex-direction:column; align-items:flex-start; gap:8px; }
.selectable .professional-main img,.selectable .professional-initials { width:48px; height:48px; }
.selectable .professional-about { margin-top:4px; }
@media(min-width:640px) { .professionals.selectable { grid-template-columns:repeat(3,minmax(0,1fr)); } }
</style>
