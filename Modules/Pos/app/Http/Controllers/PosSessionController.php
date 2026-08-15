<?php

namespace Modules\Pos\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Pos\app\Models\PosSession;
use Modules\Pos\app\Models\PosCounter;
use Modules\Sale\app\Models\Sale;
use Modules\Settings\app\Models\Company;

class PosSessionController extends Controller
{
    public function open(Request $request)
    {
        $validated = $request->validate([
            'pos_counter_id' => ['required', 'integer', 'exists:pos_counters,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $existing = PosSession::where('user_id', Auth::id())->where('status', 'open')->first();
        if ($existing) {
            return response()->json(['session' => $existing], 200);
        }

        $counter = PosCounter::whereKey($validated['pos_counter_id'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', Auth::id());
            })
            ->firstOrFail();

        $counterAlreadyOpen = PosSession::where('pos_counter_id', $counter->id)
            ->where('status', 'open')
            ->exists();
        if ($counterAlreadyOpen) {
            throw ValidationException::withMessages(['pos_counter_id' => 'This counter is already open.']);
        }

        $session = PosSession::create([
            'user_id' => Auth::id(),
            'pos_counter_id' => $counter->id,
            'company_id' => Company::query()->value('id'),
            'financial_year' => session('financial_year') ?: Sale::getFinancialYear(now()),
            'counter_code' => $counter->name,
            'opening_cash' => $validated['opening_cash'],
            'expected_cash' => $validated['opening_cash'],
            'opened_at' => now(),
            'status' => 'open',
        ]);

        return response()->json(['session' => $session], 201);
    }

    public function close(Request $request, PosSession $session)
    {
        abort_unless($session->user_id === Auth::id() || $request->user()->can('pos.close-session'), 403);

        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['session' => 'This POS session is already closed.']);
        }

        $validated = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $session->update([
            'closing_cash' => $validated['closing_cash'],
            'cash_difference' => round((float) $validated['closing_cash'] - (float) $session->expected_cash, 2),
            'closing_note' => $validated['closing_note'] ?? null,
            'closed_at' => now(),
            'status' => 'closed',
        ]);

        return response()->json(['session' => $session->fresh()]);
    }
}
