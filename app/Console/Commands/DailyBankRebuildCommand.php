<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Services\DailyBankService;

class DailyBankRebuildCommand extends Command
{
    protected $signature = 'daily-bank:rebuild
        {--from= : Earliest transaction date to rebuild in YYYY-MM-DD format}
        {--bank=* : Optional bank IDs; omit to rebuild every bank in the current tenant}';

    protected $description = 'Rebuild daily bank snapshots from active signed bank movements.';

    public function handle(DailyBankService $dailyBank): int
    {
        $from = trim((string) $this->option('from'));
        $validDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $from, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);

        if (!$validDate) {
            $this->error('A valid --from=YYYY-MM-DD date is required.');

            return self::FAILURE;
        }

        $requested = collect($this->option('bank'))->filter()->map(fn ($id) => (int) $id)->unique();
        $bankIds = $requested->isNotEmpty()
            ? Banking::whereIn('bk_id', $requested)->orderBy('bk_id')->pluck('bk_id')
            : Banking::orderBy('bk_id')->pluck('bk_id');

        if ($bankIds->isEmpty()) {
            $this->warn('No bank accounts were found in the current tenant.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($bankIds as $bankId) {
            $result = $dailyBank->rebuildFrom((int) $bankId, $from);
            $rows[] = [
                $result['bank_id'],
                $result['from_date'],
                $result['to_date'],
                number_format($result['closing_balance'], 2, '.', ''),
                number_format($result['banking_balance'], 2, '.', ''),
                number_format($result['difference'], 2, '.', ''),
                $result['rows'],
            ];
        }

        $this->table(
            ['Bank', 'From', 'To', 'Rebuilt close', 'Banking close', 'Difference', 'Rows'],
            $rows
        );

        return self::SUCCESS;
    }
}
