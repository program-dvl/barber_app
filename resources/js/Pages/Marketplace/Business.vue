<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppNotice from '@/Components/Product/AppNotice.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import MarketplaceRecovery from '@/Components/Product/MarketplaceRecovery.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import '../../../css/marketplace.css';

const props = defineProps({ businessLabel: String, canManageListing: Boolean, listing: Object, stats: Object, publication: Object });
const form = useForm({ listed: props.listing.listed, version: props.listing.version, consent: true });
const save = () => form.patch(props.listing.save_url, {
    preserveScroll: true,
    onSuccess: () => {
        form.version = props.listing.version;
        form.listed = props.listing.listed;
    },
});
</script>

<template>
    <AppLayout title="Marketplace listing" :business-label="businessLabel">
        <MarketplaceRecovery business>
            <div class="business-listing-settings">
                <PageHeader title="Marketplace listing" />
                <AppNotice v-if="$page.props.marketplaceStatus" tone="success" :title="$page.props.marketplaceStatus" />
                <section v-if="canManageListing" class="mp-card">
                    <h2>Your listing</h2>
                    <p class="mp-muted">Publishing your booking page makes your business searchable on Marketplace. Services, prices and locations update from your saved business details.</p>
                    <span class="mp-status-label">{{ listing.listed && listing.ready ? 'Listed on Marketplace' : listing.listed ? 'Complete booking setup to appear' : 'Hidden from Marketplace' }}</span>
                    <div class="mp-actions">
                        <a class="mp-button secondary" :href="listing.preview_url">Preview listing</a>
                        <a v-if="listing.listed && listing.ready" class="mp-button secondary" :href="listing.url">Open listing ↗</a>
                        <AppButton :href="listing.setup_url" variant="quiet" size="small">Edit business details</AppButton>
                    </div>
                    <form @submit.prevent="save">
                        <label class="mp-check"><input v-model="form.listed" type="checkbox" /><span>Show my business on Marketplace</span></label>
                        <AppNotice v-if="form.errors.listed || form.errors.consent" tone="warning" title="Your listing wasn’t changed" :description="form.errors.listed || form.errors.consent" />
                        <div class="mp-actions">
                            <AppButton type="submit" size="small" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save visibility' }}</AppButton>
                            <AppButton v-if="form.errors.listed" variant="secondary" size="small" @click="router.reload()">Refresh details</AppButton>
                        </div>
                    </form>
                </section>
                <section v-if="publication?.updating || publication?.support_reference" class="mp-card">
                    <AppNotice :title="publication.support_reference ? 'Your listing update needs our help' : 'Your listing is updating'" :description="publication.support_reference ? `Your business details are saved. Contact support with reference ${publication.support_reference}.` : 'Your latest saved details will appear shortly.'">
                        <template v-if="publication.support_reference" #actions>
                            <a class="mp-button secondary" :href="`mailto:${$page.props.brand.support_email}?subject=${encodeURIComponent(publication.support_reference)}`">Contact support</a>
                        </template>
                    </AppNotice>
                </section>
                <section v-if="stats" class="mp-card">
                    <h2>Booking entry activity</h2>
                    <p class="mp-lead"><strong>{{ stats.bookings }}</strong> confirmed bookings · <strong>{{ stats.started }}</strong> booking sessions started</p>
                    <p class="mp-muted">Includes owner-shared profile links. Sessions can include repeat visits by the same customer.</p>
                    <div v-if="publication" class="mp-actions"><AppButton :href="publication.report_url" variant="secondary" size="small">Discovery &amp; booking outcomes</AppButton></div>
                </section>
            </div>
        </MarketplaceRecovery>
    </AppLayout>
</template>

<style scoped>.business-listing-settings{max-width:1050px;color:var(--text-default)}.mp-card{background:var(--surface-raised);border-color:var(--border-default);padding:24px;margin-top:20px}.mp-card h2{font-size:18px;margin-bottom:12px;color:var(--text-strong)}.mp-muted,.mp-lead{font-size:14px;line-height:1.7;color:var(--text-muted)}.mp-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.mp-button{font-size:13px;padding:8px 12px;min-height:36px;border-radius:8px;background:var(--action-primary);color:white}.mp-button.secondary{background:var(--surface-raised);border-color:var(--border-default);color:var(--text-default)}.mp-button.quiet{background:transparent;color:var(--text-default)}.mp-check{font-size:14px;margin-top:18px;gap:10px;line-height:1.7}.mp-check input{width:18px;height:18px;flex-shrink:0;margin-top:3px;accent-color:var(--action-primary)}.mp-status-label{font-size:12px;padding:5px 10px}.mp-button:focus-visible{outline:2px solid var(--action-primary);outline-offset:2px}@media(max-width:640px){.mp-card{padding:18px}.mp-button{min-height:44px}.mp-check input{width:20px;height:20px}}</style>
