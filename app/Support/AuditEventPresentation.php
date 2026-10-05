<?php

namespace App\Support;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\BusinessRole;
use Carbon\CarbonImmutable;

/** Read-only projection. Never changes the append-only audit payload. */
class AuditEventPresentation
{
    public function label(AuditEvent $event): string
    {
        return [
            'configuration.published' => 'Booking page published',
            'configuration.profile.updated' => 'Business profile updated',
            'configuration.location_hours.updated' => 'Opening hours changed',
            'configuration.public_booking_policy.updated' => 'Booking preferences changed',
            'configuration.brand_asset.updated' => 'Business artwork updated',
            'onboarding.starter_workspace.created' => 'Starter workspace prepared',
            'service.catalog.created' => 'Service added',
            'service.catalog.updated' => 'Service configuration changed',
            'service.category.saved' => 'Service category saved',
            'service.categories.reordered' => 'Service categories reordered',
            'activation.service.status_changed' => 'Service availability changed',
            'client.manually_created' => 'Client added',
            'client.profile_updated' => 'Client details updated',
            'client.preferences_updated' => 'Client preferences updated',
            'client.note_added' => 'Client note added',
            'client.merge_completed' => 'Client profiles merged',
            'client.deletion_anonymization_policy_blocked' => 'Privacy request awaiting policy approval',
            'client.privacy_export_completed' => 'Client data export completed',
            'client.privacy_request_submitted' => 'Privacy request submitted',
            'staff.invitation.issued' => 'Team invitation created',
            'staff.invitation.accepted' => 'Team invitation accepted',
            'staff.invitation.revoked' => 'Team invitation cancelled',
            'membership.access.revoked' => 'Login access removed',
            'membership.access.restored' => 'Login access restored',
            'membership.role.changed' => 'Team role changed',
            'membership.locations.changed' => 'Assigned locations changed',
            'subscription.plan_change.requested' => 'Plan change requested',
            'subscription.plan_change.failed' => 'Plan change failed',
            'subscription.plan_change.expired' => 'Plan change expired',
            'subscription.plan_change.superseded' => 'Plan change replaced',
            'subscription.restricted' => 'Subscription access limited',
            'subscription.trial.expired' => 'Free trial ended',
            'communication.replay_requested' => 'Notification retry requested',
            'communication.settings_updated' => 'Notification preferences changed',
            'communication.template_saved' => 'Notification draft saved',
            'communication.template_published' => 'Notification template published',
            'communication.sender_setup_requested' => 'Branded notification sender requested',
            'support.access.granted' => 'Support access approved',
            'support.access.entered' => 'Support session started',
            'support.access.exited' => 'Support session ended',
            'support.access.revoked' => 'Support access removed',
        ][$event->action] ?? ucfirst(str_replace(['.', '_'], ' ', $event->action));
    }

    public function summary(AuditEvent $event): string
    {
        $labels = [
            'status' => 'Status', 'restriction_level' => 'Access',
            'plan_id' => 'Plan', 'billing_plan_id' => 'Plan',
            'price_id' => 'Price', 'billing_plan_price_id' => 'Price',
            'billing_interval' => 'Billing period', 'is_active' => 'Active',
            'online_visible' => 'Visible online', 'online_booking_enabled' => 'Online booking',
            'starts_at' => 'Start', 'ends_at' => 'End', 'trial_ends_at' => 'Trial ends',
            'effective_at' => 'Effective date', 'role' => 'Role', 'role_id' => 'Role',
        ];
        if (str_starts_with($event->action, 'service.catalog.')) {
            $labels += ['price_minor' => 'Base price', 'duration_minutes' => 'Active time', 'processing_minutes' => 'Processing', 'cleanup_minutes' => 'Cleanup', 'staff_count' => 'Assigned staff', 'location_count' => 'Locations', 'category' => 'Category'];
        }
        $changes = [];
        foreach ($event->after ?? [] as $field => $value) {
            if (! isset($labels[$field]) || (array_key_exists($field, $event->before ?? []) && $event->before[$field] === $value)) {
                continue;
            }
            $after = $this->value($field, $value, $event);
            if ($after === null) {
                continue;
            }
            $before = $this->value($field, $event->before[$field] ?? null, $event);
            $changes[] = $labels[$field].': '.($before !== null ? $before.' → ' : '').$after;
        }

        return implode(' · ', $changes);
    }

    private function value(string $field, mixed $value, AuditEvent $event): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        if ($field === 'price_minor') {
            return ($event->after['currency_code'] ?? $event->before['currency_code'] ?? '').' '.number_format((int) $value / 100, 2);
        }
        if (in_array($field, ['duration_minutes', 'processing_minutes', 'cleanup_minutes'], true)) {
            return $value.' min';
        }
        if ($field === 'role_id') {
            $role = BusinessRole::query()->where('business_id', $event->business_id)->find($value);

            return $role ? ($role->label ?? ucfirst(str_replace('_', ' ', $role->name))) : null;
        }
        if (in_array($field, ['plan_id', 'billing_plan_id'], true)) {
            return BillingPlan::query()->whereKey($value)->value('name');
        }
        if (in_array($field, ['price_id', 'billing_plan_price_id'], true)) {
            $price = BillingPlanPrice::query()->find($value);

            return $price ? $price->currency.' '.number_format($price->amount_minor / 100, 2).' / '.($price->billing_interval->value === 'annual' ? 'year' : 'month') : null;
        }
        if (str_ends_with($field, '_at')) {
            try {
                return CarbonImmutable::parse($value)->setTimezone($event->business?->time_zone ?? 'UTC')->format('j M Y, H:i');
            } catch (\Throwable) {
                return null;
            }
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return [
            'trialing' => 'Free trial', 'past_due' => 'Payment overdue', 'grace' => 'Payment recovery period',
            'restricted' => 'Limited access', 'read_only' => 'Read-only', 'none' => 'Full access',
            'cancel_scheduled' => 'Cancellation scheduled', 'canceled' => 'Cancelled', 'terminated' => 'Closed',
            'barber_stylist' => 'Professional',
        ][(string) $value] ?? ucfirst(str_replace('_', ' ', (string) $value));
    }
}
