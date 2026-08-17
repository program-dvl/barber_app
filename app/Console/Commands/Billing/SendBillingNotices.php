<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use App\Notifications\BillingLifecycleNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendBillingNotices extends Command
{
    protected $signature = 'billing:send-notices {--limit=100}';

    protected $description = 'Deliver due, deduplicated billing lifecycle notices.';

    public function handle(): int
    {
        $failed = 0;
        DB::table('billing_notices')->whereNull('sent_at')->where('scheduled_for', '<=', now())
            ->oldest('scheduled_for')->limit((int) $this->option('limit'))->get()
            ->each(function (object $notice) use (&$failed): void {
                try {
                    $subscription = BusinessSubscription::query()->with('business')->findOrFail($notice->business_subscription_id);
                    $roleTable = config('permission.table_names.roles');
                    $pivotTable = config('permission.table_names.model_has_roles');
                    $modelKey = config('permission.column_names.model_morph_key');
                    $businessKey = config('permission.column_names.team_foreign_key');
                    $ownerUserId = DB::table('memberships')
                        ->join($pivotTable, function ($join) use ($pivotTable, $modelKey, $businessKey): void {
                            $join->on("{$pivotTable}.{$modelKey}", '=', 'memberships.id')
                                ->on("{$pivotTable}.{$businessKey}", '=', 'memberships.business_id')
                                ->where("{$pivotTable}.model_type", Membership::class);
                        })
                        ->join($roleTable, "{$roleTable}.id", '=', "{$pivotTable}.role_id")
                        ->where('memberships.business_id', $subscription->business_id)
                        ->where("{$roleTable}.name", 'owner')
                        ->value('memberships.user_id');
                    $owner = User::query()->findOrFail($ownerUserId);
                    $owner->notify(new BillingLifecycleNotice($subscription, $notice->type));
                    DB::table('billing_notices')->where('id', $notice->id)->update(['sent_at' => now(), 'attempts' => $notice->attempts + 1, 'updated_at' => now()]);
                } catch (Throwable $exception) {
                    $failed++;
                    DB::table('billing_notices')->where('id', $notice->id)->update(['attempts' => $notice->attempts + 1, 'last_error' => str($exception->getMessage())->limit(4000), 'updated_at' => now()]);
                }
            });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
