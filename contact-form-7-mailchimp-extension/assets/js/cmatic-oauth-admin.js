/**
 * OAuth admin connection handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
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
            updateConnectButton('error', 'Pop-up blocked. Allow pop-ups for this site, then click Connect again');
            return;
        }

        popup.location = authUrl;
        beacon('popup_opened', { token: token });

        var pollCount = 0;
        var maxPolls = 240;
        var finishing = false;
		let closedPolls = 0;
        var pollInterval = window.setInterval(function() {
			if ( ! finishing && popup.closed && ++closedPolls > 2 ) {
				window.clearInterval( pollInterval );
				beacon( 'finish_failed', {
					token,
					reason: 'popup_closed',
				} );
				updateConnectButton(
					'error',
					'Sign-in window closed. Click Connect again when you are ready.'
				);
				return;
			}
            if (++pollCount > maxPolls) {
                window.clearInterval(pollInterval);
                beacon('timeout', { token: token });
                updateConnectButton('error', 'OAuth timed out. Please try again');
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
                    updateConnectButton('error', 'Mailchimp authorization failed. Please try again');
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

    function setOAuthStatus(statusEl, message, color) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.hidden = !message;
        statusEl.style.color = color || '';
    }

    function updateConnectButton(state, message) {
        var btn = document.querySelector('.cmatic-oauth-connect');
        var statusEl = document.querySelector('.cmatic-oauth-status');

        if (!statusEl) {
            statusEl = document.createElement('span');
            statusEl.className = 'cmatic-oauth-status';
            statusEl.setAttribute('role', 'status');
            statusEl.setAttribute('aria-live', 'polite');
			if ( btn ) {
				btn.appendChild( statusEl );
			}
        }

        switch (state) {
            case 'starting':
                setButtonText(btn, 'Starting...');
                if (btn) btn.disabled = true;
                setOAuthStatus(statusEl, 'Opening secure sign-in...', '#5d6878');
                break;
            case 'waiting':
                setButtonText(btn, 'Waiting...');
                if (btn) btn.disabled = true;
                setOAuthStatus(statusEl, 'Complete sign-in in the popup.', '#5d6878');
                break;
            case 'connecting':
                setButtonText(btn, 'Connecting...');
                if (btn) btn.disabled = true;
                setOAuthStatus(statusEl, 'Saving your connection...', '#5d6878');
                break;
            case 'connected':
                setButtonText(btn, 'Connected');
                if (btn) btn.disabled = true;
                setOAuthStatus(statusEl, 'Mailchimp connected.', '#16835b');
                break;
            case 'error':
                setButtonText(btn, 'Continue with Mailchimp');
                if (btn) btn.disabled = false;
                setOAuthStatus(statusEl, message || 'Could not connect. Try again.', '#b42318');
                break;
            default:
                setButtonText(btn, 'Continue with Mailchimp');
                if (btn) btn.disabled = false;
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
