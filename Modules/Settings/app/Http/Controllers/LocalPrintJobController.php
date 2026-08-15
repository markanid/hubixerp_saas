<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Settings\app\Models\LocalPrintJob;
use Modules\Settings\app\Models\PrintAgent;
use Modules\Settings\app\Models\PrintSetting;
use Modules\Settings\app\Services\PrintDocumentRenderer;

class LocalPrintJobController extends Controller
{
    public function store(Request $request, PrintDocumentRenderer $renderer): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(PrintSetting::DOCUMENTS))],
            'document_id' => ['required', 'integer', 'min:1'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if (!$renderer->documentExists($validated['document_type'], (int) $validated['document_id'])) {
            return response()->json(['message' => 'The document no longer exists.'], 404);
        }

        $setting = PrintSetting::forDocument($validated['document_type']);
        if ($setting->print_method !== 'local_agent') {
            return response()->json(['message' => 'This document is configured for browser printing.'], 409);
        }

        $agent = PrintAgent::query()
            ->where('enabled', true)
            ->whereNotNull('token_hash')
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->whereHas('mappings', fn ($query) => $query->where('document_type', $validated['document_type']))
            ->orderByDesc('is_default')
            ->orderByDesc('last_seen_at')
            ->first();

        if (!$agent) {
            return response()->json([
                'message' => 'No online print agent has a printer mapped for this document.',
                'fallback' => 'browser',
            ], 503);
        }

        $job = LocalPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'print_agent_id' => $agent->id,
            'document_type' => $validated['document_type'],
            'document_id' => $validated['document_id'],
            'status' => LocalPrintJob::STATUS_QUEUED,
            'copies' => $validated['copies'] ?? 1,
            'requested_by' => $request->user()?->getAuthIdentifier(),
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'job_id' => $job->uuid,
            'status' => $job->status,
            'agent' => $agent->name,
            'status_url' => route('local-print-jobs.show', $job),
        ], 201);
    }

    public function show(Request $request, LocalPrintJob $localPrintJob): JsonResponse
    {
        abort_unless((int) $localPrintJob->requested_by === (int) $request->user()?->getAuthIdentifier(), 403);

        if (!$localPrintJob->isTerminal() && $localPrintJob->expires_at->isPast()) {
            $localPrintJob->update(['status' => LocalPrintJob::STATUS_EXPIRED]);
        }

        return response()->json([
            'job_id' => $localPrintJob->uuid,
            'status' => $localPrintJob->status,
            'error' => $localPrintJob->error,
        ]);
    }
}
