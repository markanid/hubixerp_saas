<?php

namespace App\Http\Middleware;

use App\Support\FinancialYear;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RestrictFinancialYearReportDates
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->routeIs('mrp-reports.index', 'batch-reports.index')) {
            return $next($request);
        }

        foreach (['from_date', 'to_date'] as $field) {
            if (!$request->filled($field)) {
                continue;
            }

            Validator::make($request->all(), [$field => ['date']])->validate();
            FinancialYear::assertDate($request->input($field), $field);
        }

        return $next($request);
    }
}
