(function () {
    'use strict';

    if (window.__hubixLocalPrintStarted || !window.HubixLocalPrint) {
        return;
    }
    window.__hubixLocalPrintStarted = true;

    var config = window.HubixLocalPrint;
    var statusBox = document.getElementById('hubix-print-status');
    var terminalStatuses = ['printed', 'failed', 'expired'];

    function setStatus(message, isError) {
        if (!statusBox) return;
        statusBox.textContent = message;
        statusBox.style.borderColor = isError ? '#dc3545' : '#ced4da';
        statusBox.style.color = isError ? '#721c24' : '#212529';
    }

    function offerBrowserFallback(message) {
        setStatus(message + ' Use the browser print dialog if you still need to print.', true);
        if (!statusBox) return;

        var actions = document.createElement('div');
        actions.style.marginTop = '10px';
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Print in browser';
        button.style.cssText = 'padding:6px 10px;border:0;border-radius:3px;background:#007bff;color:#fff;cursor:pointer;';
        button.addEventListener('click', function () { window.print(); });
        actions.appendChild(button);
        statusBox.appendChild(actions);
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
                    ? 'Printing on the selected local printer…'
                    : 'Waiting for Hubix Local Print Agent…', false);
                window.setTimeout(check, 1000);
            }).catch(function (error) {
                offerBrowserFallback(error.message || 'Unable to check print status.');
            });
        }, 500);
    }

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
        setStatus('Queued on ' + job.agent + '. Waiting for the printer…', false);
        poll(job.status_url);
    }).catch(function (error) {
        offerBrowserFallback(error.message || 'No local print agent is available.');
    });
}());
