<?php

namespace App\Domain\PlatformAccess\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformHealthService
{
    /** @return array<string,mixed> */
    public function summary(): array
    {
        $oldestJob = Schema::hasTable('jobs') ? DB::table('jobs')->min('available_at') : null;

        return [
            'generated_at' => now()->toIso8601String(),
            'queue' => [
                'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed' => DB::table('failed_jobs')->count(),
                'oldest_pending_seconds' => $oldestJob ? max(0, now()->timestamp - (int) $oldestJob) : 0,
            ],
            'communications' => [
                'failed' => DB::table('communication_messages')->where('status', 'failed')->count(),
                'pending_over_15m' => DB::table('communication_messages')->whereIn('status', ['queued', 'sending'])->where('created_at', '<', now()->subMinutes(15))->count(),
                'failed_callbacks' => DB::table('communication_provider_events')->where('status', 'failed')->count(),
            ],
            'webhooks' => [
                'billing_failed' => DB::table('billing_provider_events')->where('status', 'failed')->count(),
                'appointment_payment_failed' => DB::table('payment_provider_events')->where('processing_status', 'failed')->count(),
                'oldest_unprocessed_at' => DB::table('billing_provider_events')->whereIn('status', ['pending', 'failed'])->min('created_at'),
            ],
            'reconciliation' => ['open_payment_tasks' => DB::table('payment_reconciliation_tasks')->where('status', 'open')->count()],
            'backup' => ['status' => 'not_configured', 'last_verified_restore_at' => null],
        ];
    }
}
