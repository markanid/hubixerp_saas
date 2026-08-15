<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Settings\app\Models\PrintAgent;
use Modules\Settings\app\Models\PrintAgentPrinterMapping;
use Modules\Settings\app\Models\PrintSetting;
use Modules\Settings\app\Models\LocalPrintJob;

class PrintAgentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $code = Str::upper(Str::random(10));

        $agent = DB::transaction(function () use ($validated, $code) {
            $hasDefault = PrintAgent::query()->where('is_default', true)->exists();

            return PrintAgent::create([
                'uuid' => (string) Str::uuid(),
                'name' => $validated['name'],
                'pairing_code_hash' => Hash::make($code),
                'pairing_expires_at' => now()->addMinutes(10),
                'paired_by' => request()->user()?->getAuthIdentifier(),
                'enabled' => true,
                'is_default' => !$hasDefault,
            ]);
        });

        return back()->with('success', "Print agent {$agent->name} created.")
            ->with('print_agent_pairing_code', $code)
            ->with('print_agent_pairing_name', $agent->name);
    }

    public function refreshPairing(PrintAgent $printAgent): RedirectResponse
    {
        $code = Str::upper(Str::random(10));
        $printAgent->update([
            'token_hash' => null,
            'pairing_code_hash' => Hash::make($code),
            'pairing_expires_at' => now()->addMinutes(10),
            'paired_by' => request()->user()?->getAuthIdentifier(),
            'last_seen_at' => null,
        ]);

        return back()->with('success', "A new pairing code was created for {$printAgent->name}.")
            ->with('print_agent_pairing_code', $code)
            ->with('print_agent_pairing_name', $printAgent->name);
    }

    public function update(Request $request, PrintAgent $printAgent): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
            'mappings' => ['nullable', 'array'],
            'mappings.*' => ['nullable', 'string', 'max:255'],
        ]);

        $availablePrinters = collect($printAgent->printers ?? [])->map(fn ($printer) => (string) $printer)->all();

        DB::transaction(function () use ($validated, $printAgent, $availablePrinters) {
            $printAgent->update([
                'name' => $validated['name'],
                'enabled' => (bool) ($validated['enabled'] ?? false),
            ]);

            foreach (PrintSetting::DOCUMENTS as $documentType => $label) {
                $printer = trim((string) ($validated['mappings'][$documentType] ?? ''));

                if ($printer !== '' && !in_array($printer, $availablePrinters, true)) {
                    abort(422, "The selected {$label} printer is not reported by this agent.");
                }

                if ($printer === '') {
                    PrintAgentPrinterMapping::query()
                        ->where('print_agent_id', $printAgent->id)
                        ->where('document_type', $documentType)
                        ->delete();
                } else {
                    PrintAgentPrinterMapping::updateOrCreate(
                        ['print_agent_id' => $printAgent->id, 'document_type' => $documentType],
                        ['printer_name' => $printer]
                    );
                }
            }
        });

        return back()->with('success', "Print agent {$printAgent->name} updated.");
    }

    public function makeDefault(PrintAgent $printAgent): RedirectResponse
    {
        DB::transaction(function () use ($printAgent) {
            PrintAgent::query()->update(['is_default' => false]);
            $printAgent->update(['is_default' => true]);
        });

        return back()->with('success', "{$printAgent->name} is now the default print agent.");
    }

    public function test(Request $request, PrintAgent $printAgent): RedirectResponse
    {
        if (!$printAgent->enabled || !$printAgent->isOnline() || !$printAgent->mappings()->where('document_type', 'sale')->exists()) {
            return back()->with('error', 'The agent must be online with a Sale printer mapping before running a test.');
        }

        LocalPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'print_agent_id' => $printAgent->id,
            'document_type' => 'test',
            'document_id' => null,
            'status' => LocalPrintJob::STATUS_QUEUED,
            'copies' => 1,
            'requested_by' => $request->user()?->getAuthIdentifier(),
            'expires_at' => now()->addMinutes(10),
        ]);

        return back()->with('success', "A test page was queued on {$printAgent->name}.");
    }
}
