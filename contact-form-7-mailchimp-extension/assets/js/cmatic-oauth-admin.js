/**
 * OAuth admin connection handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

(function() {
    'use strict';

    var GATEWAY_DOMAIN = 'https://api.chimpmatic.com';

    function safeReload() {
        resetCf7DirtyState();
        window.onbeforeunload = null;
        var origPD = BeforeUnloadEvent.prototype.preventDefault;
        BeforeUnloadEvent.prototype.preventDefault = function() {};
        location.reload();
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

    function oauthPopupFeatures() {
        var width = 740;
        var height = 740;
        var left = window.screenX + (window.outerWidth - width) / 2;
        var top = window.screenY + (window.outerHeight - height) / 2;
        return 'toolbar=no,location=no,directories=no,' +
            'status=no,menubar=no,scrollbars=yes,resizable=yes,' +
            'width=' + width + ',height=' + height +
            ',top=' + top + ',left=' + left;
    }

    function beacon(event, extra) {
        var payload = { event: event };
        if (extra && extra.token) { payload.token = extra.token; }
        if (extra && extra.reason) { payload.reason = extra.reason; }
        restPost('oauth/event', payload).catch(function() {});
    }

    function openOAuthPopup(popup, authUrl, token, formId) {
        if (!popup || popup.closed) {
            beacon('popup_blocked', { token: token });
            updateConnectButton('error', 'Pop-up blocked — allow pop-ups for this site, then click Connect again');
            return;
        }

        popup.location = authUrl;
        beacon('popup_opened', { token: token });

        var pollCount = 0;
        var maxPolls = 240;
        var finishing = false;
        var pollInterval = window.setInterval(function() {
            if (++pollCount > maxPolls) {
                window.clearInterval(pollInterval);
                beacon('timeout', { token: token });
                updateConnectButton('error', 'OAuth timed out — please try again');
                return;
            }

            restPost('oauth/status', {
                url: GATEWAY_DOMAIN + '/api/status/' + token
            }).then(function(statusData) {
                if (finishing) {
                    return;
                }
                if (statusData.status === 'accepted') {
                    finishing = true;
                    window.clearInterval(pollInterval);
                    try { popup.close(); } catch(e) {}
                    updateConnectButton('connecting');

                    restPost('oauth/finish', {
                        token: token,
                        form_id: formId
                    }).then(function(finishData) {
                        if (finishData.connected) {
                            updateConnectButton('connected');
                            safeReload();
                        } else {
                            beacon('finish_failed', { token: token, reason: finishData.message || '' });
                            updateConnectButton('error', finishData.message || 'Connection failed');
                        }
                    }).catch(function(err) {
                        beacon('finish_failed', { token: token, reason: (err && err.message) || '' });
                        updateConnectButton('error', err.message || 'Connection failed');
                    });
                } else if (statusData.status === 'error') {
                    finishing = true;
                    window.clearInterval(pollInterval);
                    try { popup.close(); } catch(e) {}
                    beacon('finish_failed', { token: token, reason: 'callback_error' });
                    updateConnectButton('error', 'Mailchimp authorization failed — please try again');
                }
            }).catch(function() {});
        }, 2500);
    }

    function setButtonText(btn, text) {
        if (!btn) return;
        var label = btn.querySelector('[data-cmatic-oauth-action-label]');
        if (label) label.textContent = text;
        else btn.textContent = text;
    }

    function setAuthChoicesBusy(apiKeyButton, busy) {
        if (apiKeyButton) apiKeyButton.disabled = busy;
    }

    function setOAuthStatus(statusEl, message, color) {
        if (!statusEl) return;
        var card = statusEl.closest('.cmatic-oauth-connect');
        var description = card ? card.querySelector('[data-cmatic-oauth-description]') : null;
        statusEl.textContent = message || '';
        statusEl.hidden = !message;
        statusEl.style.color = color || '';
        if (description) description.hidden = Boolean(message);
    }

    function updateConnectButton(state, message) {
        var btn = document.querySelector('.cmatic-oauth-connect');
        var statusEl = document.querySelector('.cmatic-oauth-status');
        var apiKeyLink = document.querySelector('.cmatic-show-api-key');

        if (!statusEl) {
            statusEl = document.createElement('span');
            statusEl.className = 'cmatic-oauth-status';
            statusEl.setAttribute('role', 'status');
            statusEl.setAttribute('aria-live', 'polite');
            if (btn) {
                var action = btn.querySelector('.cmatic-auth-choice__action');
                btn.insertBefore(statusEl, action || null);
            }
        }

        switch (state) {
            case 'starting':
                setButtonText(btn, 'Starting...');
                if (btn) btn.disabled = true;
                setAuthChoicesBusy(apiKeyLink, true);
                setOAuthStatus(statusEl, 'Opening secure sign-in...', '#5d6878');
                break;
            case 'waiting':
                setButtonText(btn, 'Waiting...');
                if (btn) btn.disabled = true;
                setAuthChoicesBusy(apiKeyLink, true);
                setOAuthStatus(statusEl, 'Complete sign-in in the popup.', '#5d6878');
                break;
            case 'connecting':
                setButtonText(btn, 'Connecting...');
                if (btn) btn.disabled = true;
                setAuthChoicesBusy(apiKeyLink, true);
                setOAuthStatus(statusEl, 'Saving your connection...', '#5d6878');
                break;
            case 'connected':
                setButtonText(btn, 'Connected');
                if (btn) btn.disabled = true;
                setAuthChoicesBusy(apiKeyLink, true);
                setOAuthStatus(statusEl, 'Mailchimp connected.', '#16835b');
                break;
            case 'error':
                setButtonText(btn, 'Continue with Mailchimp');
                if (btn) btn.disabled = false;
                setAuthChoicesBusy(apiKeyLink, false);
                setOAuthStatus(statusEl, message || 'Could not connect. Try again.', '#b42318');
                break;
            default:
                setButtonText(btn, 'Continue with Mailchimp');
                if (btn) btn.disabled = false;
                setAuthChoicesBusy(apiKeyLink, false);
                setOAuthStatus(statusEl, '', '');
        }
    }

    document.addEventListener('click', function(e) {
        var connectBtn = e.target.closest('.cmatic-oauth-connect');
        if (connectBtn) {
            var formId = parseInt(connectBtn.getAttribute('data-form-id'), 10) || 0;
            updateConnectButton('starting');

            var popup = window.open('', 'ChimpMaticOAuth_' + Date.now(), oauthPopupFeatures());

            restPost('oauth/start', { form_id: formId })
                .then(function(data) {
                    if (data.token && data.auth_url) {
                        updateConnectButton('waiting');
                        openOAuthPopup(popup, data.auth_url, data.token, formId);
                    } else {
                        if (popup) { try { popup.close(); } catch(e) {} }
                        updateConnectButton('error', data.message || 'Could not start OAuth');
                    }
                })
                .catch(function(err) {
                    if (popup) { try { popup.close(); } catch(e) {} }
                    updateConnectButton('error', err.message || 'Could not start OAuth');
                });
        }

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

        if (e.target.classList.contains('cmatic-disconnect-cancel')) {
            var wrapper = e.target.closest('.cmatic-disconnect-confirm');
            var btn = wrapper.parentNode.querySelector('.cmatic-oauth-disconnect');
            wrapper.remove();
            if (btn) {
                btn.style.display = '';
            }
        }

        var apiKeyButton = e.target.closest('.cmatic-show-api-key');
        if (apiKeyButton) {
            e.preventDefault();
            var apiPanel = document.getElementById('cmatic-manual-api-panel');
            if (apiPanel) {
                apiPanel.classList.remove('cmatic-hidden');
                apiKeyButton.setAttribute('aria-expanded', 'true');
                var options = apiKeyButton.closest('.cmatic-connect-options');
                if (options) options.classList.add('cmatic-hidden');
                var keyInput = document.getElementById('cmatic-api');
                if (keyInput) keyInput.focus();
            }
        }

        var backButton = e.target.closest('.cmatic-back-auth-options');
        if (backButton) {
            e.preventDefault();
            var apiPanel = document.getElementById('cmatic-manual-api-panel');
            var options = document.querySelector('.cmatic-connect-options');
            var apiKeyButton = document.querySelector('.cmatic-show-api-key');
            if (apiPanel) apiPanel.classList.add('cmatic-hidden');
            if (options) options.classList.remove('cmatic-hidden');
            if (apiKeyButton) {
                apiKeyButton.setAttribute('aria-expanded', 'false');
                apiKeyButton.focus();
            }
        }
    });

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

    document.addEventListener('DOMContentLoaded', function() {
        if (getAuthType() !== 'oauth') {
            return;
        }

        var formId = getFormId();
        if (!formId) {
            return;
        }

        var listDropdown = document.getElementById('wpcf7-mailchimp-list');
        if (listDropdown && listDropdown.options.length > 1) {
            return;
        }

        var syncBtn = document.getElementById('chm_activalist');
        if (syncBtn) {
            syncBtn.click();
        }

        if (listDropdown) {
            var observer = new MutationObserver(function(mutations, obs) {
                if (listDropdown.options.length > 1) {
                    obs.disconnect();
                    setTimeout(resetCf7DirtyState, 500);
                }
            });
            observer.observe(listDropdown, { childList: true });
            setTimeout(function() {
                observer.disconnect();
                resetCf7DirtyState();
            }, 8000);
        } else {
            setTimeout(resetCf7DirtyState, 3000);
        }
    });
})();
