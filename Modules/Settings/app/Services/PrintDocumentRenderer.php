<?php

namespace Modules\Settings\app\Services;

use Illuminate\Contracts\View\View;
use Modules\Consumption\app\Http\Controllers\ConsumptionController;
use Modules\Consumption\app\Models\Consumption;
use Modules\Estimation\app\Http\Controllers\EstimationController;
use Modules\Estimation\app\Models\Estimation;
use Modules\Purchase\app\Http\Controllers\PurchaseController;
use Modules\Purchase\app\Models\Purchase;
use Modules\Returns\app\Http\Controllers\ReturnController;
use Modules\Returns\app\Models\Returns;
use Modules\Sale\app\Http\Controllers\SaleController;
use Modules\Sale\app\Models\Sale;
use Modules\Service\app\Http\Controllers\ServiceController;
use Modules\Service\app\Models\Service;
use Modules\Settings\app\Models\LocalPrintJob;
use Modules\Settings\app\Models\PrintSetting;

class PrintDocumentRenderer
{
    public function documentExists(string $type, int $id): bool
    {
        $model = match ($type) {
            'sale' => Sale::class,
            'purchase' => Purchase::class,
            'estimation' => Estimation::class,
            'service' => Service::class,
            'sale_return', 'purchase_return' => Returns::class,
            'consumption' => Consumption::class,
            default => null,
        };

        if (!$model || !$model::query()->whereKey($id)->exists()) {
            return false;
        }

        if (in_array($type, ['sale_return', 'purchase_return'], true)) {
            $expectedReturnType = $type === 'purchase_return' ? '1' : '2';

            return Returns::query()->whereKey($id)->where('pr_type', $expectedReturnType)->exists();
        }

        return true;
    }

    public function render(LocalPrintJob $job): View
    {
        if ($job->document_type === 'test') {
            return view('settings::print-agent.test', [
                'agent' => $job->agent,
                'job' => $job,
                'printSetting' => PrintSetting::forDocument('sale'),
                'printAgentRender' => true,
            ]);
        }

        $view = match ($job->document_type) {
            'sale' => app(SaleController::class)->print($job->document_id),
            'purchase' => app(PurchaseController::class)->print($job->document_id),
            'estimation' => app(EstimationController::class)->print($job->document_id),
            'service' => app(ServiceController::class)->print($job->document_id),
            'sale_return' => app(ReturnController::class)->sprint($job->document_id),
            'purchase_return' => app(ReturnController::class)->pprint($job->document_id),
            'consumption' => app(ConsumptionController::class)->print($job->document_id),
            default => abort(404, 'Unknown print document type.'),
        };

        return $view->with('printAgentRender', true);
    }
}
