<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'document_id' => [Rule::requiredIf(fn () => $request->input('document_type') !== 'barcode'), 'nullable', 'integer', 'min:1'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:20'],
            'payload' => [Rule::requiredIf(fn () => $request->input('document_type') === 'barcode'), 'nullable', 'array'],
            'payload.source' => [Rule::requiredIf(fn () => $request->input('document_type') === 'barcode'), 'nullable', Rule::in(['products', 'inventory_labels'])],
            'payload.ids' => [Rule::requiredIf(fn () => $request->input('document_type') === 'barcode'), 'nullable', 'array', 'min:1', 'max:200'],
            'payload.ids.*' => ['integer', 'min:1', 'distinct'],
            'payload.code_type' => [Rule::requiredIf(fn () => $request->input('document_type') === 'barcode'), 'nullable', Rule::in(['barcode', 'qr', 'both'])],
            'payload.layout' => ['nullable', Rule::in(['single', 'sheet'])],
        ]);

        $ability = match ($validated['document_type']) {
            'sale', 'sale_return' => 'sale.view',
            'purchase', 'purchase_return' => 'purchase.view',
            'service' => 'service.view',
            default => null,
        };
        if ($ability !== null) {
            Gate::authorize($ability);
        }

        $documentId = isset($validated['document_id']) ? (int) $validated['document_id'] : null;
        $payload = $validated['document_type'] === 'barcode' ? ($validated['payload'] ?? []) : [];

        if (!$renderer->documentExists($validated['document_type'], $documentId, $payload)) {
            return response()->json(['message' => 'The document no longer exists.'], 404);
        }

        $setting = PrintSetting::forDocument($validated['document_type']);
        if ($setting->print_method !== 'local_agent') {
            return response()->json(['message' => 'This document is configured for browser printing.'], 409);
        }

        $boundAgentUuid = (string) $request->cookie(PrintAgent::BROWSER_COOKIE, '');
        if ($boundAgentUuid === '') {
            return response()->json([
                'message' => 'This browser is not linked to a Hubix print agent. Link it in Company Settings or use browser printing.',
                'fallback' => 'browser',
            ], 409);
        }

        $agent = PrintAgent::query()
            ->where('uuid', $boundAgentUuid)
            ->where('enabled', true)
            ->whereNotNull('token_hash')
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->whereHas('mappings', fn ($query) => $query->where('document_type', $validated['document_type']))
            ->first();

        if (!$agent) {
            return response()->json([
                'message' => 'The print agent linked to this browser is offline, disabled, or has no printer mapped for this document.',
                'fallback' => 'browser',
            ], 503);
        }

        if ($validated['document_type'] === 'barcode'
            && version_compare((string) $agent->version, PrintAgent::BARCODE_MIN_VERSION, '<')) {
            return response()->json([
                'message' => 'The print agent linked to this browser must be updated before it can print barcode labels.',
                'fallback' => 'browser',
            ], 409);
        }

        $job = LocalPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'print_agent_id' => $agent->id,
            'document_type' => $validated['document_type'],
            'document_id' => $documentId,
            'status' => LocalPrintJob::STATUS_QUEUED,
            'copies' => $validated['copies'] ?? 1,
            'requested_by' => $request->user()?->getAuthIdentifier(),
            'request_ip' => $request->ip(),
            'request_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'payload' => $payload ?: null,
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
