<script setup>
import { computed } from 'vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import FormField from '@/Components/Product/FormField.vue';

const props = defineProps({ form: { type: Object, required: true } });
const emit = defineEmits(['save']);
const error = key => props.form.errors?.[key];
const priceHint = computed(() => ({
    service_setting: 'Matches the fixed or starting-price label saved for each service.',
    exact: 'Uses fixed-price labels. Prices may still show “from” when team member prices vary.',
    from: 'Adds “from” before every listed price. Your saved service prices stay the same.',
}[props.form.online_price_display]));
const clientHint = computed(() => ({
    allow: 'New and returning clients can book. Each service’s client restrictions still apply.',
    consultation_only: 'New clients can only book services marked “Consultation service” in Services.',
    existing_only: 'Clients must match a saved client record using the contact details they enter.',
}[props.form.online_new_client_rule]));
const staffHint = computed(() => ({
    any_or_preferred: 'Clients can pick a professional or ask for the first available.',
    any_only: 'Clients book with the first available qualified team member.',
    preferred_required: 'Clients must pick a team member before seeing available times.',
}[props.form.online_staff_preference]));
const cancellationOptions = [
    { value: 0, label: 'Until the appointment starts' },
    { value: 360, label: '6 hours before' },
    { value: 720, label: '12 hours before' },
    { value: 1440, label: '24 hours before' },
    { value: 2880, label: '48 hours before' },
    { value: 10080, label: '7 days before' },
];
const linkOptions = [
    { value: 60, label: '1 hour' },
    { value: 1440, label: '1 day' },
    { value: 4320, label: '3 days' },
    { value: 10080, label: '7 days' },
    { value: 20160, label: '14 days' },
    { value: 43200, label: '30 days' },
];
const hasCustomCancellation = computed(() => !cancellationOptions.some(option => option.value === Number(props.form.cancellation_cutoff_minutes)));
const hasCustomLink = computed(() => !linkOptions.some(option => option.value === Number(props.form.public_link_ttl_minutes)));
const submit = () => { if (!props.form.processing) emit('save'); };
</script>

<template>
    <form class="booking-preferences" @submit.prevent="submit">
        <div class="preferences-grid">
            <FormField id="bs-booking-interval" label="Appointment start interval" hint="Space between appointment start times. Service lengths stay the same." :error="error('appointment_interval_minutes')">
                <AppSelect id="bs-booking-interval" v-model="form.appointment_interval_minutes" :disabled="form.processing">
                    <option v-for="minutes in [5, 10, 15, 20, 30, 60]" :key="minutes" :value="minutes">{{ minutes }} minutes</option>
                </AppSelect>
            </FormField>
            <div class="preferences-online-toggle">
                <label for="bs-online-booking" class="preferences-check"><input id="bs-online-booking" v-model="form.online_booking_enabled" type="checkbox" :disabled="form.processing" :aria-invalid="error('online_booking_enabled') ? true : undefined" :aria-describedby="`bs-online-booking-hint${error('online_booking_enabled') ? ' bs-online-booking-error' : ''}`" /><span>Accept online bookings</span></label>
                <p id="bs-online-booking-hint">Your booking page must also be published before clients can book.</p>
                <p v-if="error('online_booking_enabled')" id="bs-online-booking-error" role="alert" class="preferences-field-error">{{ error('online_booking_enabled') }}</p>
            </div>
            <FormField id="bs-staff-choice" label="Team member selection" :hint="staffHint" :error="error('online_staff_preference')">
                <AppSelect id="bs-staff-choice" v-model="form.online_staff_preference" :disabled="form.processing">
                    <option value="any_or_preferred">Client choice or first available</option>
                    <option value="any_only">First available only</option>
                    <option value="preferred_required">Client must choose a team member</option>
                </AppSelect>
            </FormField>
            <FormField id="bs-online-prices" label="Prices on your booking page" :hint="priceHint" :error="error('online_price_display')">
                <AppSelect id="bs-online-prices" v-model="form.online_price_display" :disabled="form.processing">
                    <option value="service_setting">Use each service’s price setting</option>
                    <option value="exact">Show fixed prices</option>
                    <option value="from">Show “from” prices</option>
                </AppSelect>
            </FormField>
            <FormField id="bs-online-clients" label="Who can book online" :hint="clientHint" :error="error('online_new_client_rule')">
                <AppSelect id="bs-online-clients" v-model="form.online_new_client_rule" :disabled="form.processing">
                    <option value="allow">New and existing clients</option>
                    <option value="consultation_only">New clients: consultations only</option>
                    <option value="existing_only">Existing clients only</option>
                </AppSelect>
            </FormField>
            <FormField id="bs-online-deadline" label="Online change deadline" hint="How far ahead clients must cancel or reschedule online." :error="error('cancellation_cutoff_minutes')">
                <AppSelect id="bs-online-deadline" v-model="form.cancellation_cutoff_minutes" :disabled="form.processing">
                    <option v-if="hasCustomCancellation" :value="form.cancellation_cutoff_minutes">{{ form.cancellation_cutoff_minutes }} minutes before (current)</option>
                    <option v-for="option in cancellationOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </AppSelect>
            </FormField>
        </div>
        <details class="preferences-more" :open="!!error('waitlist_offer_batch_size') || !!error('public_link_ttl_minutes')">
            <summary>Waitlist & appointment links</summary>
            <div class="preferences-grid">
                <FormField id="bs-waitlist-offers" label="Waitlist offers per opening" hint="Offer each opening to this many matching waitlist clients. The first to confirm gets the time." :error="error('waitlist_offer_batch_size')">
                    <input id="bs-waitlist-offers" v-model="form.waitlist_offer_batch_size" type="number" min="1" max="10" step="1" class="cd-input" :disabled="form.processing" />
                </FormField>
                <FormField id="bs-appointment-link-validity" label="Private appointment links expire after" hint="Applies to new links for viewing or managing an appointment. This does not affect your public booking link." :error="error('public_link_ttl_minutes')">
                    <AppSelect id="bs-appointment-link-validity" v-model="form.public_link_ttl_minutes" :disabled="form.processing">
                        <option v-if="hasCustomLink" :value="form.public_link_ttl_minutes">{{ form.public_link_ttl_minutes }} minutes (current)</option>
                        <option v-for="option in linkOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </AppSelect>
                </FormField>
            </div>
        </details>
        <AppNotice v-if="error('setup_revision')" tone="error" title="Booking preferences changed" :description="error('setup_revision')" />
        <footer class="preferences-footer"><AppButton type="submit" :loading="form.processing" :disabled="form.processing">Save booking preferences</AppButton></footer>
    </form>
</template>

<style scoped>
.booking-preferences { display:grid; gap:24px; }
.preferences-grid { display:grid; gap:22px 28px; grid-template-columns:repeat(2,minmax(0,1fr)); }
.preferences-online-toggle { align-self:start; padding-top:2px; }
.preferences-check { display:flex; align-items:center; gap:12px; min-height:44px; font-size:14px; font-weight:600; color:var(--text-strong); }
.preferences-check input { flex-shrink:0; }
.preferences-online-toggle>p { margin-top:4px; font-size:14px; line-height:1.5; color:var(--text-muted); }
.preferences-online-toggle>.preferences-field-error { color:var(--status-danger); font-weight:500; }
.preferences-more { border-top:1px solid var(--border-subtle); padding-top:10px; }
.preferences-more summary { display:list-item; cursor:pointer; min-height:44px; padding-block:12px; font-size:14px; font-weight:600; color:var(--text-strong); }
.preferences-more summary:focus-visible { outline:3px solid var(--focus-ring); outline-offset:3px; border-radius:4px; }
.preferences-more .preferences-grid { margin-top:14px; }
.preferences-footer { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:10px; border-top:1px solid var(--border-subtle); padding-top:18px; }
@media(max-width:640px) { .preferences-grid { grid-template-columns:minmax(0,1fr); gap:20px; }.preferences-footer>.cd-app-button { width:100%; min-height:44px; } }
</style>
