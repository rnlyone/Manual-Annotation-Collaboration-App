<?php

namespace App\Console\Commands;

use App\Models\Phase2Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPhase2NonNormal extends Command
{
    protected $signature   = 'phase2:backfill-non-normal';
    protected $description = 'Recalculate total_non_normal for all Phase 2 runs from ai_screenings.phase1_label';

    public function handle(): int
    {
        $runs = Phase2Run::whereNotNull('source_package_id')->get(['id']);

        if ($runs->isEmpty()) {
            $this->info('No Phase 2 runs found.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($runs->count());
        $bar->start();

        foreach ($runs as $run) {
            $count = DB::table('ai_screenings')
                ->where('phase2_run_id', $run->id)
                ->whereNotNull('phase1_label')
                ->where('phase1_label', '!=', 'Normal')
                ->count();

            DB::table('phase2_runs')
                ->where('id', $run->id)
                ->update(['total_non_normal' => $count]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$runs->count()} run(s).");

        return self::SUCCESS;
    }
}
