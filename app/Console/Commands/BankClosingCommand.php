<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\StockValue;

class BankClosingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:closing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close the bank every day.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function (): void {
            DailyBank::bankClosing();
            StockValue::stockClosing();
        });
        Log::info("Daily closings completed at " . now());
        $this->info('Bank and stock closing task completed.');
    }
}
