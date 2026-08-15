@php
    $useLocalPrintAgent = !($printAgentRender ?? false)
        && ($printSetting->print_method ?? 'browser') === 'local_agent';
@endphp

@if($useLocalPrintAgent)
    <div id="hubix-print-status" role="status" aria-live="polite" style="position:fixed;right:16px;bottom:16px;z-index:99999;max-width:360px;padding:12px 14px;background:#fff;border:1px solid #ced4da;border-radius:4px;box-shadow:0 3px 14px rgba(0,0,0,.18);font:14px/1.4 Arial,sans-serif;color:#212529;">
        Sending document to Hubix Local Print Agent…
    </div>
    <script>
        window.HubixLocalPrint = {
            createUrl: @json(route('local-print-jobs.store')),
            csrfToken: @json(csrf_token()),
            documentType: @json($documentType),
            documentId: @json((int) $documentId)
        };
    </script>
    <script src="{{ asset('js/local-print-agent.js') }}"></script>
@endif
