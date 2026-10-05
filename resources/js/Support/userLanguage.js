/** Only use for application-owned statuses/keys, never business-entered text. */
export const sentenceLabel = value => ({
    trialing: 'Free trial', past_due: 'Payment overdue', cancel_scheduled: 'Cancellation scheduled',
    canceled: 'Cancelled', cancelled_by_client: 'Cancelled by client', cancelled_by_shop: 'Cancelled by business',
    barber_stylist: 'Staff member', in_service: 'In service', checked_in: 'Checked in', no_show: 'No-show',
    not_required: 'Not required', not_configured: 'Not configured',
    billing_webhooks: 'Subscription updates', payment_webhooks: 'Payment updates', notifications: 'Notifications', jobs: 'Background jobs',
    queue: 'Background jobs', communications: 'Notifications', webhooks: 'Provider updates', reconciliation: 'Payment reconciliation', backup: 'Backups',
    pending_over_15m: 'Waiting more than 15 minutes', oldest_pending_seconds: 'Oldest waiting job (seconds)',
    failed_callbacks: 'Failed delivery updates', billing_failed: 'Failed subscription updates',
    appointment_payment_failed: 'Failed appointment payment updates', oldest_unprocessed_at: 'Oldest unprocessed update',
    open_payment_tasks: 'Unresolved payment tasks', last_verified_restore_at: 'Last verified restore',
}[value] || String(value ?? '').replaceAll('_', ' ').replaceAll('.', ' ').replace(/^./, letter => letter.toUpperCase()));
