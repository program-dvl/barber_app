<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import SectionBorder from '@/Components/Profile/SectionBorder.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <AppLayout title="Profile">
        <div>
            <PageHeader :title="$t('Profile & security')"><template #actions><AppButton v-if="$page.props.account?.workspaces?.length === 1" :href="route('business.dashboard', $page.props.account.workspaces[0].public_id)" variant="secondary">Back to workspace</AppButton></template></PageHeader>
            <div class="mt-5 space-y-5">
                <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <UpdateProfileInformationForm :user="$page.props.auth.user" />
                </div>

                <div v-if="$page.props.jetstream.canUpdatePassword">
                    <UpdatePasswordForm />
                </div>

                <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                    <TwoFactorAuthenticationForm
                        :requires-confirmation="confirmsTwoFactorAuthentication"
                    />
                </div>

                <div>
                    <LogoutOtherBrowserSessionsForm :sessions="sessions" />
                </div>

                <div v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
