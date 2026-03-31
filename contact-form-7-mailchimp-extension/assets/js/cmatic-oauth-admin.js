(function() {
    'use strict';

    var GATEWAY_DOMAIN = 'https://api.chimpmatic.com';

    // Suppress CF7's beforeunload dirty-form warning before programmatic reloads.
    // CF7's handler calls e.preventDefault() on the BeforeUnloadEvent, which Chrome
    // uses to show the "Reload site?" dialog. We use two strategies:
    // 1. Reset all form field baselines so CF7's dirty check finds nothing changed.
    // 2. As a fallback, temporarily neuter preventDefault on BeforeUnloadEvent so
    //    even if CF7's check still fires, it can't trigger the dialog.
    function safeReload() {
        resetCf7DirtyState();
        window.onbeforeunload = null;
        var origPD = BeforeUnloadEvent.prototype.preventDefault;
        BeforeUnloadEvent.prototype.preventDefault = function() {};
        location.reload();
        // Restore after a tick (reload is async, this runs if reload is delayed).
        setTimeout(function() {
            BeforeUnloadEvent.prototype.preventDefault = origPD;
        }, 0);
    }

    function getAuthType() {
        var dataContainer = document.getElementById('cmatic_data');
        return (dataContainer && dataContainer.dataset.authType) || '';
    }

    function getFormId() {
        var dataContainer = document.getElementById('cmatic_data');
        if (dataContainer && dataContainer.dataset.formId) {
            return parseInt(dataContainer.dataset.formId, 10) || 0;
        }
        return 0;
    }

    function restPost(endpoint, data) {
        return fetch(chimpmaticOAuth.restUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': chimpmaticOAuth.nonce
            },
            body: JSON.stringify(data)
        }).then(function(r) { return r.json(); });
    }

    function openOAuthPopup(authUrl, token, formId) {
        var width = 740;
        var height = 740;
        var left = window.screenX + (window.outerWidth - width) / 2;
        var top = window.screenY + (window.outerHeight - height) / 2;
        var features = 'toolbar=no,location=no,directories=no,' +
            'status=no,menubar=no,scrollbars=yes,resizable=yes,' +
            'width=' + width + ',height=' + height +
            ',top=' + top + ',left=' + left;

        var popup = window.open(authUrl, 'ChimpMatic OAuth', features);

        if (!popup) {
            alert('Pop-up blocked. Please allow pop-ups for this site and try again.');
            return;
        }

        var pollCount = 0;
        var maxPolls = 2400; // 10 minutes at 250ms intervals.
        var pollInterval = window.setInterval(function() {
            if (++pollCount > maxPolls) {
                window.clearInterval(pollInterval);
                updateConnectButton('error', 'OAuth timed out — please try again');
                return;
            }
            if (!popup.closed) {
                return;
            }

            window.clearInterval(pollInterval);
            updateConnectButton('connecting');

            restPost('oauth/status', {
                url: GATEWAY_DOMAIN + '/api/status/' + token
            }).then(function(statusData) {
                if (statusData.status === 'accepted') {
                    return restPost('oauth/finish', {
                        token: token,
                        form_id: formId
                    }).then(function(finishData) {
                        if (finishData.connected) {
                            updateConnectButton('connected');
                            safeReload();
                        } else {
                            updateConnectButton('error', finishData.message || 'Connection failed');
                        }
                    });
                } else {
                    updateConnectButton('error', 'Authentication was not completed');
                }
            }).catch(function(err) {
                updateConnectButton('error', err.message || 'Connection failed');
            });
        }, 250);
    }

    function createConnectSvg() {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '18');
        svg.setAttribute('height', '18');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4');
        var polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        polyline.setAttribute('points', '10 17 15 12 10 7');
        var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', '15');
        line.setAttribute('y1', '12');
        line.setAttribute('x2', '3');
        line.setAttribute('y2', '12');
        svg.appendChild(path);
        svg.appendChild(polyline);
        svg.appendChild(line);
        return svg;
    }

    function setButtonText(btn, text, showIcon) {
        if (!btn) return;
        btn.textContent = text;
        if (showIcon) {
            btn.appendChild(document.createTextNode(' '));
            btn.appendChild(createConnectSvg());
        }
    }

    function updateConnectButton(state, message) {
        var btn = document.querySelector('.cmatic-oauth-connect');
        var statusEl = document.querySelector('.cmatic-oauth-status');
        var apiKeyLink = document.querySelector('.cmatic-show-api-key');

        if (!statusEl) {
            statusEl = document.createElement('span');
            statusEl.className = 'cmatic-oauth-status';
            statusEl.style.cssText = 'display: block; margin-top: 6px; font-style: italic; font-size: 12px; text-align: center;';
            var wrap = document.querySelector('.cmatic-oauth-connect-wrap');
            if (wrap) {
                wrap.appendChild(statusEl);
            } else if (btn && btn.parentNode) {
                btn.parentNode.insertBefore(statusEl, btn.nextSibling);
            }
        }

        switch (state) {
            case 'starting':
                setButtonText(btn, 'Starting...', false);
                if (btn) btn.disabled = true;
                if (apiKeyLink) apiKeyLink.style.display = 'none';
                statusEl.textContent = '';
                statusEl.style.color = '';
                break;
            case 'waiting':
                setButtonText(btn, 'Waiting...', false);
                if (btn) btn.disabled = true;
                if (apiKeyLink) apiKeyLink.style.display = 'none';
                statusEl.textContent = 'Complete login in the popup window';
                statusEl.style.color = '#666';
                break;
            case 'connecting':
                setButtonText(btn, 'Connecting...', false);
                if (btn) btn.disabled = true;
                if (apiKeyLink) apiKeyLink.style.display = 'none';
                statusEl.textContent = 'Saving credentials...';
                statusEl.style.color = '#666';
                break;
            case 'connected':
                setButtonText(btn, 'Connected', false);
                if (btn) btn.disabled = true;
                if (apiKeyLink) apiKeyLink.style.display = 'none';
                statusEl.textContent = 'Success!';
                statusEl.style.color = '#46b450';
                break;
            case 'error':
                setButtonText(btn, 'Connect with Mailchimp', true);
                if (btn) btn.disabled = false;
                if (apiKeyLink) apiKeyLink.style.display = '';
                statusEl.textContent = message || 'Error';
                statusEl.style.color = '#d63638';
                break;
            default:
                setButtonText(btn, 'Connect with Mailchimp', true);
                if (btn) btn.disabled = false;
                if (apiKeyLink) apiKeyLink.style.display = '';
                statusEl.textContent = '';
        }
    }

    // Connect button click.
    document.addEventListener('click', function(e) {
        var connectBtn = e.target.closest('.cmatic-oauth-connect');
        if (connectBtn) {
            var formId = parseInt(connectBtn.getAttribute('data-form-id'), 10) || 0;
            updateConnectButton('starting');

            restPost('oauth/start', { form_id: formId })
                .then(function(data) {
                    if (data.token && data.auth_url) {
                        updateConnectButton('waiting');
                        openOAuthPopup(data.auth_url, data.token, formId);
                    } else {
                        updateConnectButton('error', data.message || 'Could not start OAuth');
                    }
                })
                .catch(function(err) {
                    updateConnectButton('error', err.message || 'Could not start OAuth');
                });
        }

        // Disconnect button click — show inline confirmation.
        if (e.target.classList.contains('cmatic-oauth-disconnect')) {
            var btn = e.target;
            var formId = btn.getAttribute('data-form-id');

            var confirm = document.createElement('span');
            confirm.className = 'cmatic-disconnect-confirm';
            confirm.innerHTML =
                '<span class="cmatic-disconnect-label">Disconnect?</span>' +
                '<button type="button" class="cmatic-disconnect-yes">Yes, disconnect</button>' +
                '<button type="button" class="cmatic-disconnect-cancel">Cancel</button>';

            btn.parentNode.insertBefore(confirm, btn.nextSibling);
            btn.style.display = 'none';
        }

        // Inline confirm — Yes, disconnect.
        if (e.target.classList.contains('cmatic-disconnect-yes')) {
            var wrapper = e.target.closest('.cmatic-disconnect-confirm');
            var formId = parseInt(wrapper.parentNode.querySelector('.cmatic-oauth-disconnect').getAttribute('data-form-id'), 10) || 0;
            var statusText = wrapper.parentNode.querySelector('.cmatic-oauth-status-text');
            var description = wrapper.parentNode.querySelector('.description');

            wrapper.remove();
            if (statusText) {
                statusText.innerHTML = 'Disconnecting...';
                statusText.style.color = '#666';
                statusText.style.fontWeight = '400';
            }
            if (description) {
                description.style.display = 'none';
            }

            restPost('oauth/disconnect', { form_id: formId })
                .then(function(data) {
                    if (data.disconnected) {
                        safeReload();
                    }
                });
        }

        // Inline confirm — Cancel.
        if (e.target.classList.contains('cmatic-disconnect-cancel')) {
            var wrapper = e.target.closest('.cmatic-disconnect-confirm');
            var btn = wrapper.parentNode.querySelector('.cmatic-oauth-disconnect');
            wrapper.remove();
            if (btn) {
                btn.style.display = '';
            }
        }

        // "Use your existing key" toggle in State 3.
        if (e.target.classList.contains('cmatic-show-api-key')) {
            e.preventDefault();
            var apiPanel = document.getElementById('cmatic-manual-api-panel');
            if (apiPanel) {
                apiPanel.classList.toggle('cmatic-hidden');
            }
        }
    });

    // Reset CF7's dirty-form baseline so dynamically populated fields
    // (audience dropdown, merge fields) don't trigger "Reload site?" warnings.
    // CF7 compares defaultValue/defaultSelected on beforeunload — we sync them
    // after our pipeline finishes populating the DOM.
    function resetCf7DirtyState() {
        var form = document.getElementById('wpcf7-admin-form-element');
        if (!form) return;

        form.querySelectorAll('input, textarea, select').forEach(function(el) {
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.defaultChecked = el.checked;
            } else if (el.type === 'select-multiple' || el.type === 'select-one') {
                Array.prototype.forEach.call(el.options, function(opt) {
                    opt.defaultSelected = opt.selected;
                });
            } else {
                el.defaultValue = el.value;
            }
        });
    }

    // Auto-load audiences for OAuth connections on page load.
    // Delegates to main JS pipeline via the Sync button click, which handles
    // the full flow: fetchMailchimpLists() -> renderListsDropdown() ->
    // attachFetchFieldsListeners() -> merge field loading.
    document.addEventListener('DOMContentLoaded', function() {
        if (getAuthType() !== 'oauth') {
            return;
        }

        var formId = getFormId();
        if (!formId) {
            return;
        }

        // If audiences already loaded, skip.
        var listDropdown = document.getElementById('wpcf7-mailchimp-list');
        if (listDropdown && listDropdown.options.length > 1) {
            return;
        }

        // Click the hidden Sync Audiences button to trigger the main JS pipeline.
        // The main JS's fetchListsButton handler is already patched (Task 3.7)
        // to allow OAuth through when apiKey is empty and authType === 'oauth'.
        // Programmatic .click() fires event listeners on hidden elements.
        var syncBtn = document.getElementById('chm_activalist');
        if (syncBtn) {
            syncBtn.click();
        }

        // The pipeline runs async (fetch calls). Watch for the audience
        // dropdown to get populated, then reset CF7's dirty-form baseline.
        // Also set a fallback timer in case the observer misses it.
        if (listDropdown) {
            var observer = new MutationObserver(function(mutations, obs) {
                if (listDropdown.options.length > 1) {
                    obs.disconnect();
                    // Small delay to let merge field loading finish too.
                    setTimeout(resetCf7DirtyState, 500);
                }
            });
            observer.observe(listDropdown, { childList: true });
            // Fallback: disconnect observer and reset after 8s regardless.
            setTimeout(function() {
                observer.disconnect();
                resetCf7DirtyState();
            }, 8000);
        } else {
            setTimeout(resetCf7DirtyState, 3000);
        }
    });
})();
