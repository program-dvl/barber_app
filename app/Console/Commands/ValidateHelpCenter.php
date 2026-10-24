<?php

namespace App\Console\Commands;

use App\Domain\HelpCenter\HelpCatalog;
use Illuminate\Console\Command;

class ValidateHelpCenter extends Command
{
    protected $signature = 'help:validate';

    protected $description = 'Validate Help Center content, relationships, media and application routes';

    public function handle(HelpCatalog $catalog): int
    {
        $errors = $catalog->validate();
        foreach ($errors as $error) {
            $this->error($error);
        }
        if ($errors !== []) {
            return self::FAILURE;
        }
        $this->info(count($catalog->articles()).' published articles validated across '.count($catalog->categories()).' categories.');

        return self::SUCCESS;
    }
}
