<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpFeedbackReport extends Command
{
    protected $signature = 'help:feedback {--article= : Limit the aggregate report to one article slug}';

    protected $description = 'Show aggregate helpfulness counts by Help Center article and revision';

    public function handle(): int
    {
        if (! Schema::hasTable('help_article_feedback')) {
            $this->error('Help Center feedback storage is not ready. Apply the Help Center migration first.');

            return self::FAILURE;
        }
        $query = DB::table('help_article_feedback')->select('article_slug', 'revision')
            ->selectRaw('SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) AS helpful_count')
            ->selectRaw('SUM(CASE WHEN helpful = 0 THEN 1 ELSE 0 END) AS unhelpful_count')
            ->groupBy('article_slug', 'revision')->orderBy('article_slug')->orderByDesc('revision');
        if ($this->option('article')) {
            $query->where('article_slug', $this->option('article'));
        }
        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->info('No feedback has been recorded for this selection.');

            return self::SUCCESS;
        }
        $this->table(['Article', 'Revision', 'Helpful', 'Not helpful'], $rows->map(fn ($row) => [
            $row->article_slug, $row->revision, $row->helpful_count, $row->unhelpful_count,
        ])->all());

        return self::SUCCESS;
    }
}
