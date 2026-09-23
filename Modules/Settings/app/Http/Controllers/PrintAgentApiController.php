<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Settings\app\Models\LocalPrintJob;
use Modules\Settings\app\Models\BarcodeSetting;
use Modules\Settings\app\Models\PrintAgent;
use Modules\Settings\app\Models\PrintSetting;
use Modules\Settings\app\Services\PrintDocumentRenderer;

class PrintAgentApiController extends Controller
{
    public function pair(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pairing_code' => ['required', 'string', 'min:8', 'max:20'],
            'machine_name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:40'],
        ]);

        $agent = PrintAgent::query()
            ->where('enabled', true)
            ->whereNotNull('pairing_code_hash')
            ->where('pairing_expires_at', '>', now())
            ->get()
            ->first(fn (PrintAgent $candidate) => Hash::check(Str::upper($validated['pairing_code']), $candidate->pairing_code_hash));

        if (!$agent) {
            return response()->json(['message' => 'The pairing code is invalid or expired.'], 422);
        }

        $plainToken = bin2hex(random_bytes(32));
        $agent->update([
            'machine_name' => $validated['machine_name'],
            'version' => $validated['version'],
            'token_hash' => hash('sha256', $plainToken),
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'last_seen_at' => now(),
            'last_error' => null,
        ]);

        return response()->json([
            'agent_id' => $agent->uuid,
            'agent_name' => $agent->name,
            'token' => $plainToken,
            'poll_seconds' => 1,
            'heartbeat_seconds' => 30,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machine_name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:40'],
            'printers' => ['required', 'array', 'max:100'],
            'printers.*' => ['required', 'string', 'max:255'],
            'default_printer' => ['nullable', 'string', 'max:255'],
            'last_error' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var PrintAgent $agent */
        $agent = $request->attributes->get('printAgent');
        $printers = array_values(array_unique($validated['printers']));
        $defaultPrinter = $validated['default_printer'] ?? null;
        if ($defaultPrinter && !in_array($defaultPrinter, $printers, true)) {
            $defaultPrinter = null;
        }

        $agent->update([
            'machine_name' => $validated['machine_name'],
            'version' => $validated['version'],
            'printers' => $printers,
            'default_printer' => $defaultPrinter,
            'last_seen_at' => now(),
            'last_error' => $validated['last_error'] ?? null,
        ]);

        return response()->json(['status' => 'ok', 'server_time' => now()->toIso8601String()]);
    }

    public function browserLinkCode(Request $request): JsonResponse
    {
        /** @var PrintAgent $agent */
        $agent = $request->attributes->get('printAgent');
        if (version_compare((string) $agent->version, PrintAgent::BROWSER_BINDING_MIN_VERSION, '<')) {
            return response()->json([
                'message' => 'Update Hubix Print Agent to v'.PrintAgent::BROWSER_BINDING_MIN_VERSION.' before linking a browser.',
            ], 409);
        }

        $code = Str::upper(Str::random(8));
        $expiresAt = now()->addMinutes(10);

        $agent->update([
            'browser_link_code_hash' => hash('sha256', $code),
            'browser_link_expires_at' => $expiresAt,
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'code' => $code,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function next(Request $request): JsonResponse
    {
        /** @var PrintAgent $agent */
        $agent = $request->attributes->get('printAgent');
        if (!$agent->last_seen_at || $agent->last_seen_at->lt(now()->subSeconds(30))) {
            $agent->update(['last_seen_at' => now()]);
        }

        $claimed = DB::transaction(function () use ($agent) {
            LocalPrintJob::query()
                ->where('print_agent_id', $agent->id)
                ->where('status', LocalPrintJob::STATUS_QUEUED)
                ->where('expires_at', '<=', now())
                ->update(['status' => LocalPrintJob::STATUS_EXPIRED]);

            $job = LocalPrintJob::query()
                ->where('print_agent_id', $agent->id)
                ->where('status', LocalPrintJob::STATUS_QUEUED)
                ->where('expires_at', '>', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$job) {
                return null;
            }

            $plainClaimToken = bin2hex(random_bytes(32));
            $job->update([
                'status' => LocalPrintJob::STATUS_CLAIMED,
                'claim_token_hash' => hash('sha256', $plainClaimToken),
                'claimed_at' => now(),
            ]);

            return [$job->fresh(['agent']), $plainClaimToken];
        });

        if (!$claimed) {
            return response()->json(null, 204);
        }

        [$job, $claimToken] = $claimed;
        $mappingType = $job->document_type === 'test' ? 'sale' : $job->document_type;
        $mapping = $agent->mappings()->where('document_type', $mappingType)->first();
        if (!$mapping || !in_array($mapping->printer_name, $agent->printers ?? [], true)) {
            $job->update([
                'status' => LocalPrintJob::STATUS_FAILED,
                'error' => 'The mapped printer is no longer installed on this computer.',
            ]);

            return response()->json(null, 204);
        }

        $settingType = $job->document_type === 'test' ? 'sale' : $job->document_type;
        $setting = PrintSetting::forDocument($settingType);
        $thermalSettings = $job->document_type === 'barcode' ? BarcodeSetting::activeLabelSettings() : null;
        $labelPageSize = $thermalSettings ? BarcodeSetting::pageSize($thermalSettings) : null;

        return response()->json([
            'job_id' => $job->uuid,
            'claim_token' => $claimToken,
            'render_url' => route('api.print-agent.jobs.render', $job),
            'printer_name' => $mapping->printer_name,
            'copies' => $job->copies,
            'settings' => [
                'paper_size' => $thermalSettings ? 'custom' : $setting->paper_size,
                'orientation' => $thermalSettings ? 'portrait' : $setting->orientation,
                'scale' => $thermalSettings ? 100 : $setting->scale,
                'margin_mm' => $thermalSettings ? 0 : $setting->margin_mm,
                'page_width_mm' => $labelPageSize['width_mm'] ?? null,
                'page_height_mm' => $labelPageSize['height_mm'] ?? null,
            ],
        ]);
    }

    public function render(Request $request, LocalPrintJob $localPrintJob, PrintDocumentRenderer $renderer)
    {
        $this->authorizeJob($request, $localPrintJob);

        abort_unless(in_array($localPrintJob->status, [LocalPrintJob::STATUS_CLAIMED, LocalPrintJob::STATUS_PRINTING], true), 409);

        return $renderer->render($localPrintJob->load('agent'));
    }

    public function status(Request $request, LocalPrintJob $localPrintJob): JsonResponse
    {
        $this->authorizeJob($request, $localPrintJob);
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                LocalPrintJob::STATUS_PRINTING,
                LocalPrintJob::STATUS_PRINTED,
                LocalPrintJob::STATUS_FAILED,
            ])],
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = match ($localPrintJob->status) {
            LocalPrintJob::STATUS_CLAIMED => [LocalPrintJob::STATUS_PRINTING, LocalPrintJob::STATUS_FAILED],
            LocalPrintJob::STATUS_PRINTING => [LocalPrintJob::STATUS_PRINTED, LocalPrintJob::STATUS_FAILED],
            default => [],
        };
        abort_unless(in_array($validated['status'], $allowed, true), 409, 'Invalid print-job state transition.');

        $localPrintJob->update([
            'status' => $validated['status'],
            'printed_at' => $validated['status'] === LocalPrintJob::STATUS_PRINTED ? now() : null,
            'error' => $validated['status'] === LocalPrintJob::STATUS_FAILED ? ($validated['error'] ?? 'Local print failed.') : null,
        ]);

        return response()->json(['status' => $localPrintJob->status]);
    }

    private function authorizeJob(Request $request, LocalPrintJob $job): void
    {
        /** @var PrintAgent $agent */
        $agent = $request->attributes->get('printAgent');
        $claimToken = (string) $request->header('X-Print-Claim');

        abort_unless($job->print_agent_id === $agent->id, 403);
        abort_unless($claimToken !== '' && hash_equals((string) $job->claim_token_hash, hash('sha256', $claimToken)), 403);
    }
}
