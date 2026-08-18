@php
    $useLocalPrintAgent = !($printAgentRender ?? false)
        && ($printSetting->print_method ?? 'browser') === 'local_agent';
@endphp

@if($useLocalPrintAgent)
    <div id="hubix-print-status" class="dontprint" role="status" aria-live="polite" style="position:fixed;right:16px;bottom:16px;z-index:99999;max-width:360px;padding:12px 14px;background:#fff;border:1px solid #ced4da;border-radius:4px;box-shadow:0 3px 14px rgba(0,0,0,.18);font:14px/1.4 Arial,sans-serif;color:#212529;">
        <div id="hubix-print-message">
            {{ ($printSetting->auto_print ?? true)
                ? 'Sending document to Hubix Local Print Agent...'
                : 'Review the document, then choose how to print.' }}
        </div>
        <div id="hubix-print-actions" style="display:none;margin-top:10px;gap:8px;flex-wrap:wrap;">
            <button id="hubix-agent-print" type="button" style="padding:6px 10px;border:0;border-radius:3px;background:#28a745;color:#fff;cursor:pointer;">
                Print with Hubix
            </button>
            <button id="hubix-browser-print" type="button" style="padding:6px 10px;border:0;border-radius:3px;background:#007bff;color:#fff;cursor:pointer;">
                Print in browser
            </button>
        </div>
    </div>
    <script>
        window.HubixLocalPrint = {
            createUrl: @json(route('local-print-jobs.store')),
            csrfToken: @json(csrf_token()),
            documentType: @json($documentType),
            documentId: @json((int) $documentId),
            autoPrint: @json((bool) ($printSetting->auto_print ?? true))
        };
    </script>
    <script src="{{ asset('js/local-print-agent.js') }}?v={{ filemtime(public_path('js/local-print-agent.js')) }}"></script>
@endif
