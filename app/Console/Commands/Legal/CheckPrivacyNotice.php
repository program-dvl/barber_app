<?php

namespace App\Console\Commands\Legal;

use App\Support\Legal\PrivacyNotice;
use Illuminate\Console\Command;

class CheckPrivacyNotice extends Command
{
    protected $signature = 'legal:check-privacy';

    protected $description = 'Check approved privacy notice publication configuration without exposing private credentials';

    public function handle(PrivacyNotice $notice): int
    {
        foreach ($notice->blockers() as $blocker) {
            $this->error($blocker);
        }
        if (! $notice->isPublished()) {
            $this->warn('Policy is a review preview. Do not deploy it as an approved notice or resubmit it to Google.');

            return self::FAILURE;
        }
        $this->info('Privacy notice publication configuration is complete. Confirm the deployment checklist before release.');

        return self::SUCCESS;
    }
}
