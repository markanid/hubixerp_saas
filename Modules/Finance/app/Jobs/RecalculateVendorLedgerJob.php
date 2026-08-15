<?php

namespace Modules\Finance\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Contacts\app\Models\Vendor;
use Modules\Finance\app\Models\LedgerBook;

class RecalculateVendorLedgerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vendorId;
    protected $fromDate;

    public function __construct($vendorId, $fromDate)
    {
        $this->vendorId = $vendorId;
        $this->fromDate = $fromDate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vendorId = $this->vendorId;
        $fromDate = $this->fromDate;

        $entries = LedgerBook::where('lb_payee', $vendorId)
            ->whereIn('lb_type', ['p', 'pr', 'pp', 'prp', 'loan'])
            ->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get();

        $runningBalance = optional(Vendor::find($vendorId))->op_balance ?? 0;

        foreach ($entries as $entry) {
            if ($entry->lb_date < $fromDate) {
                $runningBalance = $entry->lb_type == 'loan'
                    ? $runningBalance + $entry->lb_tramount
                    : (in_array($entry->lb_type, ['pr', 'prp'])
                    ? $runningBalance + $entry->lb_amount - $entry->lb_tramount
                    : $runningBalance - $entry->lb_amount + $entry->lb_tramount);
                continue;
            }

            $entry->lb_opbalance = $runningBalance;

            $entry->lb_clbalance = $entry->lb_type == 'loan'
                ? $entry->lb_opbalance + $entry->lb_tramount
                : (in_array($entry->lb_type, ['pr', 'prp'])
                ? $entry->lb_opbalance + $entry->lb_amount - $entry->lb_tramount
                : $entry->lb_opbalance - $entry->lb_amount + $entry->lb_tramount);

            $entry->save();
            $runningBalance = $entry->lb_clbalance;
        }
    }
}
