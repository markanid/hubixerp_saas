(function () {
    'use strict';

    if (window.__hubixLocalPrintStarted || !window.HubixLocalPrint) {
        return;
    }
    window.__hubixLocalPrintStarted = true;

    var config = window.HubixLocalPrint;
    var statusBox = document.getElementById('hubix-print-status');
    var messageBox = document.getElementById('hubix-print-message');
    var actions = document.getElementById('hubix-print-actions');
    var agentButton = document.getElementById('hubix-agent-print');
    var browserButton = document.getElementById('hubix-browser-print');
    var jobRequested = false;

    function setStatus(message, isError) {
        if (!statusBox) return;
        if (messageBox) messageBox.textContent = message;
        statusBox.style.borderColor = isError ? '#dc3545' : '#ced4da';
        statusBox.style.color = isError ? '#721c24' : '#212529';
    }

    function showActions(showAgent, showBrowser) {
        if (!actions) return;
        if (agentButton) agentButton.style.display = showAgent ? 'inline-block' : 'none';
        if (browserButton) browserButton.style.display = showBrowser ? 'inline-block' : 'none';
        actions.style.display = showAgent || showBrowser ? 'flex' : 'none';
    }

    function offerBrowserFallback(message) {
        setStatus(message + ' Use the browser print dialog if you still need to print.', true);
        jobRequested = false;
        if (agentButton) agentButton.disabled = false;
        showActions(true, true);
    }

    function jsonFetch(url, options) {
        return fetch(url, options).then(function (response) {
            if (response.status === 204) return null;
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    var error = new Error(body.message || 'The print request failed.');
                    error.status = response.status;
                    throw error;
                }
                return body;
            });
        });
    }

    function poll(statusUrl) {
        window.setTimeout(function check() {
            jsonFetch(statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (job) {
                if (!job) throw new Error('The print agent returned an empty response.');

                if (job.status === 'printed') {
                    setStatus('Printed successfully by Hubix Local Print Agent.', false);
                    return;
                }
                if (job.status === 'failed' || job.status === 'expired') {
                    offerBrowserFallback(job.error || (job.status === 'expired'
                        ? 'The local print request expired.'
                        : 'The local print request failed.'));
                    return;
                }

                setStatus(job.status === 'printing'
                    ? 'Printing on the selected local printer...'
                    : 'Waiting for Hubix Local Print Agent...', false);
                window.setTimeout(check, 500);
            }).catch(function (error) {
                offerBrowserFallback(error.message || 'Unable to check print status.');
            });
        }, 250);
    }

    function queuePrintJob() {
        if (jobRequested) return;

        jobRequested = true;
        if (agentButton) agentButton.disabled = true;
        showActions(false, false);
        setStatus('Sending document to Hubix Local Print Agent...', false);

        jsonFetch(config.createUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken
            },
            body: JSON.stringify({
                document_type: config.documentType,
                document_id: config.documentId,
                copies: 1
            })
        }).then(function (job) {
            setStatus('Queued on ' + job.agent + '. Waiting for the printer...', false);
            poll(job.status_url);
        }).catch(function (error) {
            offerBrowserFallback(error.message || 'No local print agent is available.');
        });
    }

    if (agentButton) agentButton.addEventListener('click', queuePrintJob);
    if (browserButton) browserButton.addEventListener('click', function () { window.print(); });

    if (config.autoPrint) {
        queuePrintJob();
    } else {
        showActions(true, true);
    }
}());
