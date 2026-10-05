<?php

namespace App\Console\Commands\AccountNotifications;

use App\Domain\AccountNotifications\Models\AccountLoginActivity;
use Illuminate\Console\Command;

class PruneLoginActivity extends Command
{
    protected $signature = 'account-email:prune-login-activity {--days= : Override the configured retention period}';

    protected $description = 'Delete expired browser sign-in activity used for new-device email alerts.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('account-notifications.login_history_days', 180));
        $days = max(30, min($days, 3650));
        $deleted = AccountLoginActivity::query()->where('last_seen_at', '<', now()->subDays($days))->delete();

        $this->components->info("Pruned {$deleted} expired sign-in activity record(s). Retention: {$days} days.");

        return self::SUCCESS;
    }
}
