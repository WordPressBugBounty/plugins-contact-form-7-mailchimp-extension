/**
 * Console logging handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

'use strict';

(function() {
	if (typeof chimpmaticLite === 'undefined' || !chimpmaticLite.loggingEnabled) {
		return;
	}

	const originalConsole = {
		log: console.log,
		info: console.info,
		warn: console.warn,
		error: console.error,
		debug: console.debug
	};

	async function sendLogToServer(level, message, ...args) {
		let formattedMessage = message;
		let dataString = '';

		if (args.length > 0) {
			try {
				dataString = args.map(arg => typeof arg === 'object' ? JSON.stringify(arg, null, 2) : String(arg)).join(' | ');
				formattedMessage += ' ' + dataString;
			} catch (e) {
				dataString = '[Unable to stringify arguments]';
			}
		}

		try {
			await fetch(`${chimpmaticLite.restUrl}logs/browser`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': chimpmaticLite.restNonce
				},
				body: JSON.stringify({ level, message: formattedMessage, data: dataString })
			});
		} catch (error) {
			originalConsole.error('[ChimpMatic Lite] Failed to send log to server:', error);
		}
	}

	['log', 'info', 'warn', 'error', 'debug'].forEach(level => {
		console[level] = function(...args) {
			originalConsole[level].apply(console, args);
			const message = args[0] ? String(args[0]) : '';
			sendLogToServer(level, message, ...args.slice(1));
		};
	});
})();

document.addEventListener('DOMContentLoaded', function() {
	if (typeof chimpmaticLite === 'undefined') {
		return;
	}

	const isProFieldPanelActive = !!document.getElementById('chm_panel_gencamposygrupos');

	function getFormId() {
		const dataContainer = document.getElementById('cmatic_data');
		return dataContainer?.dataset?.formId ? parseInt(dataContainer.dataset.formId, 10) || 0 : 0;
	}

	async function fetchMailchimpLists(formId, apiKey) {
		const response = await fetch(`${chimpmaticLite.restUrl}lists`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': chimpmaticLite.restNonce
			},
			body: JSON.stringify({ form_id: formId, api_key: apiKey })
		});

		const data = await response.json();
		if (!response.ok) throw new Error(data.message || 'Failed to fetch lists');
		return data;
	}

	async function getDebugLog(filtered = true) {
		const url = filtered
			? `${chimpmaticLite.restUrl}logs`
			: `${chimpmaticLite.restUrl}logs?filter=0`;

		const response = await fetch(url, {
			method: 'GET',
			headers: { 'X-WP-Nonce': chimpmaticLite.restNonce }
		});

		const data = await response.json();
		if (!response.ok) throw new Error(data.message || 'Failed to fetch log');
		return data;
	}

	async function clearDebugLog() {
		const response = await fetch(`${chimpmaticLite.restUrl}logs/clear`, {
			method: 'POST',
			headers: { 'X-WP-Nonce': chimpmaticLite.restNonce }
		});

		const data = await response.json();
		if (!response.ok) throw new Error(data.message || 'Failed to clear log');
		return data;
	}

	function findBestMatch(mergeTag, fieldName, cf7Tags) {
		if (!mergeTag || !cf7Tags || cf7Tags.length === 0) return null;

		const normalize = str => String(str).toLowerCase().replace(/[^a-z0-9]/g, '');
		const normalizedTag = normalize(mergeTag);
		const normalizedName = normalize(fieldName);

		const keywordMappings = {
			email: ['email', 'mail', 'correo'],
			emailaddress: ['email', 'mail'],
			fname: ['name', 'firstname', 'first', 'nombre', 'your-name'],
			firstname: ['name', 'firstname', 'first', 'nombre'],
			lname: ['lastname', 'last', 'apellido', 'surname'],
			lastname: ['lastname', 'last', 'apellido'],
			name: ['name', 'nombre', 'your-name'],
			fullname: ['name', 'fullname', 'nombre'],
			phone: ['phone', 'tel', 'telefono', 'mobile', 'cell'],
			mobilephone: ['phone', 'tel', 'mobile', 'cell'],
			address: ['address', 'direccion', 'street'],
			address1: ['address', 'address1', 'street'],
			address2: ['address2', 'apt', 'suite'],
			city: ['city', 'ciudad'],
			state: ['state', 'province', 'region', 'estado'],
			zip: ['zip', 'postal', 'postcode'],
			country: ['country', 'pais'],
			company: ['company', 'organization', 'empresa', 'org'],
			website: ['website', 'url', 'web', 'sitio'],
			birthday: ['birthday', 'birth', 'dob', 'cumpleanos'],
			message: ['message', 'comments', 'mensaje', 'nota', 'your-message']
		};

		for (const [mcKeyword, cf7Keywords] of Object.entries(keywordMappings)) {
			if (normalizedTag.includes(mcKeyword) || normalizedName.includes(mcKeyword)) {
				for (const cf7Keyword of cf7Keywords) {
					const match = cf7Tags.find(tag => normalize(tag.name || tag).includes(cf7Keyword));
					if (match) return match.name || match;
				}
			}
		}

		for (const tag of cf7Tags) {
			const tagName = normalize(tag.name || tag);
			if (normalizedTag.includes(tagName) || tagName.includes(normalizedTag)) return tag.name || tag;
			if (normalizedName.includes(tagName) || tagName.includes(normalizedName)) return tag.name || tag;
		}

		return null;
	}

	function applyFuzzyMatching(mergeFields) {
		const cf7Tags = [];
		const sampleDropdown = document.getElementById('wpcf7-mailchimp-field4');
		if (sampleDropdown) {
			Array.from(sampleDropdown.options).forEach(option => {
				if (option.value && option.value.trim() !== '' && option.value !== ' ') {
					cf7Tags.push({ name: option.value });
				}
			});
		}

		if (cf7Tags.length === 0) return;

		const fieldMappings = [
			{ id: 'field3', index: 0 },
			{ id: 'field4', index: 1 },
			{ id: 'field5', index: 2 },
			{ id: 'field6', index: 3 }
		];

		const changedFields = [];

		fieldMappings.forEach(mapping => {
			const mergeField = mergeFields[mapping.index];
			if (!mergeField) return;

			const dropdown = document.getElementById(`wpcf7-mailchimp-${mapping.id}`);
			if (!dropdown) return;

			if (dropdown.value && dropdown.value.trim() !== '' && dropdown.value !== ' ') return;

			const bestMatch = findBestMatch(mergeField.tag, mergeField.name, cf7Tags);
			if (bestMatch) {
				dropdown.value = bestMatch;
				changedFields.push({ field: mapping.id, value: bestMatch });

				Array.from(dropdown.options).forEach(opt => {
					opt.defaultSelected = (opt.value === bestMatch);
				});
			}
		});

		if (changedFields.length > 0) {
			saveFieldMappings(changedFields);
		}
	}

	async function saveFieldMappings(fields) {
		const formId = getFormId();
		if (!formId || fields.length === 0) return;

		for (const { field, value } of fields) {
			try {
				await fetch(chimpmaticLite.restUrl + 'form/field', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': chimpmaticLite.restNonce
					},
					body: JSON.stringify({ form_id: formId, field, value })
				});
			} catch (error) {
				console.error('Failed to save field mapping:', field, error);
			}
		}
	}

	function updateFieldLabels(mergeFields) {
		const fieldMappings = [
			{ id: 'field3', index: 0 },
			{ id: 'field4', index: 1 },
			{ id: 'field5', index: 2 },
			{ id: 'field6', index: 3 }
		];

		fieldMappings.forEach(mapping => {
			const label = document.querySelector(`label[for="wpcf7-mailchimp-${mapping.id}"]`);
			const container = label ? label.closest('.mcee-container') : null;

			if (mergeFields[mapping.index]) {
				const field = mergeFields[mapping.index];
				if (label) {
					const requiredBadge = field.tag === 'EMAIL' ? '<span class="mce-required">Required</span>' : '';
					label.innerHTML = `${field.name} - *|${field.tag}|* <span class="mce-type">${field.type}</span> ${requiredBadge}`;
				}
				if (container) container.style.display = '';
			} else {
				if (container) container.style.display = 'none';
			}
		});

		applyFuzzyMatching(mergeFields);
	}

	function renderListsDropdown(listsData, currentSelection) {
		const { api_valid, lists, total } = listsData;

		const dataContainer = document.getElementById('cmatic_data');
		if (dataContainer) dataContainer.dataset.apiValid = api_valid ? '1' : '0';

		const label = document.getElementById('cmatic-audiences-label');
		if (label) {
			label.textContent = api_valid && total > 0 ? `Total Mailchimp Audiences: ${total}` : 'Mailchimp Audiences';
		}

		let optionsHtml = '';

		if (api_valid && total > 0) {
			let selectedAudience = currentSelection;
			if (!selectedAudience && lists.length > 0) selectedAudience = lists[0].id;

			lists.forEach((list, index) => {
				const selected = selectedAudience === list.id ? ' selected' : '';
				const optionText = `${index + 1}:${list.member_count} ${list.name}  ${list.field_count} fields #${list.id}`;
				optionsHtml += `<option value="${list.id}"${selected}>${optionText}</option>`;
			});
		}

		return optionsHtml;
	}

	function updateApiStatus(isValid) {
		// Update sidebar status
		const versionInfo = document.getElementById('chimpmatic-version-info');
		if (versionInfo) {
			const statusText = versionInfo.querySelector('.chmm');
			if (statusText) {
				if (isValid) {
					statusText.classList.remove('invalid');
					statusText.classList.add('valid');
					statusText.textContent = 'API Connected';
				} else {
					statusText.classList.remove('valid');
					statusText.classList.add('invalid');
					statusText.textContent = 'API Inactive';
				}
			}
		}

		// Update header status dot
		const headerDot = document.querySelector('.cmatic-header__status-dot');
		const headerText = document.querySelector('.cmatic-header__status-text');
		if (headerDot) {
			if (isValid) {
				headerDot.classList.remove('cmatic-header__status-dot--disconnected');
				headerDot.classList.add('cmatic-header__status-dot--connected');
			} else {
				headerDot.classList.remove('cmatic-header__status-dot--connected');
				headerDot.classList.add('cmatic-header__status-dot--disconnected');
			}
		}
		if (headerText) {
			headerText.textContent = isValid ? 'API Connected' : 'API Inactive';
		}
	}

	function updateLiteBadgeStatus(status) {
		const liteBadge = document.querySelector('.cm-lite');
		if (!liteBadge) return;

		liteBadge.classList.remove('cm-status-neutral', 'cm-status-connected', 'cm-status-error');

		if (status === 'connected') liteBadge.classList.add('cm-status-connected');
		else if (status === 'error') liteBadge.classList.add('cm-status-error');
		else liteBadge.classList.add('cm-status-neutral');
	}

	async function getSecureApiKey(apiKeyInput, formId) {
		const isMasked = apiKeyInput.dataset.isMasked === '1';
		const hasKey = apiKeyInput.dataset.hasKey === '1';
		const inputValue = apiKeyInput.value.trim();

		if (!isMasked) {
			return inputValue;
		}

		if (!hasKey) {
			return '';
		}

		try {
			const response = await fetch(
				`${chimpmaticLite.restUrl}api-key/${formId}`,
				{
					method: 'GET',
					headers: {
						'X-WP-Nonce': chimpmaticLite.restNonce,
						'Content-Type': 'application/json'
					}
				}
			);

			if (!response.ok) {
				console.error('ChimpMatic: Failed to fetch API key');
				return '';
			}

			const data = await response.json();
			return data.api_key || '';
		} catch (err) {
			console.error('ChimpMatic: Error fetching API key', err);
			return '';
		}
	}

	const fetchListsButton = document.getElementById('chm_activalist');
	if (fetchListsButton) {
		fetchListsButton.addEventListener('click', async function(event) {
			event.preventDefault();

			const apiKeyInput = document.getElementById('cmatic-api');
			const selectElement = document.getElementById('wpcf7-mailchimp-list');

			if (!apiKeyInput || !selectElement) return;

			const formId = getFormId();
			const apiKey = await getSecureApiKey(apiKeyInput, formId);

			if (!apiKey) {
				if (typeof showInlineMessage === 'function') {
					showInlineMessage(fetchListsButton, 'Enter API key first', 'warning');
				}
				updateApiStatus(false);
				updateLiteBadgeStatus('neutral');
				return;
			}

			if (!formId || formId <= 0) {
				if (typeof showInlineMessage === 'function') {
					showInlineMessage(fetchListsButton, 'Save form first', 'warning');
				}
				updateApiStatus(false);
				updateLiteBadgeStatus('neutral');
				return;
			}

			const originalText = fetchListsButton.value || fetchListsButton.textContent;
			fetchListsButton.disabled = true;
			if (fetchListsButton.tagName === 'INPUT') fetchListsButton.value = 'Syncing Audiences...';
			else fetchListsButton.textContent = 'Syncing Audiences...';

			try {
				const data = await fetchMailchimpLists(formId, apiKey);

				const currentSelection = selectElement.value || '';
				selectElement.innerHTML = renderListsDropdown(data, currentSelection);

				attachFetchFieldsListeners();

				const newListDropdown = document.getElementById('wpcf7-mailchimp-list');
				if (newListDropdown && newListDropdown.value) {
					if (isProFieldPanelActive) {
						newListDropdown.dispatchEvent(new Event('change', { bubbles: true }));
					} else {
						fetchFieldsForSelectedList();
					}
				}

				if (data.api_valid) {
					updateApiStatus(true);
					updateLiteBadgeStatus('connected');

					document.querySelectorAll('.chmp-inactive').forEach(el => {
						el.classList.remove('chmp-inactive');
						el.classList.add('chmp-active');
					});

					const newUserSection = document.getElementById('chmp-new-user');
					if (newUserSection) {
						newUserSection.classList.remove('chmp-active');
						newUserSection.classList.add('chmp-inactive');
					}
				} else {
					updateApiStatus(false);
					updateLiteBadgeStatus('error');

					document.querySelectorAll('.chmp-active').forEach(el => {
						el.classList.remove('chmp-active');
						el.classList.add('chmp-inactive');
					});

					const newUserSection = document.getElementById('chmp-new-user');
					if (newUserSection) {
						newUserSection.classList.remove('chmp-inactive');
						newUserSection.classList.add('chmp-active');
					}
				}

				if (fetchListsButton.tagName === 'INPUT') fetchListsButton.value = 'Synced ✓';
				else fetchListsButton.textContent = 'Synced ✓';

				setTimeout(() => {
					if (fetchListsButton.tagName === 'INPUT') fetchListsButton.value = originalText;
					else fetchListsButton.textContent = originalText;
					fetchListsButton.disabled = false;
				}, 1000);

			} catch (error) {
				if (fetchListsButton.tagName === 'INPUT') fetchListsButton.value = originalText;
				else fetchListsButton.textContent = originalText;
				fetchListsButton.disabled = false;
				alert(chimpmaticLite.i18n.error);
			}
		});
	}

	const apiKeyInput = document.getElementById('cmatic-api');
	if (apiKeyInput && fetchListsButton) {
		function isValidApiKey(key) {
			if (key.length !== 36 || key.charAt(32) !== '-') return false;
			if (!/^[a-f0-9]{32}$/i.test(key.substring(0, 32))) return false;
			const dc = key.substring(33).toLowerCase();
			const validDCs = ['us1','us2','us3','us4','us5','us6','us7','us8','us9','us10','us11','us12','us13','us14','us15','us16','us17','us18','us19','us20','us21'];
			return validDCs.includes(dc);
		}

		function debounce(func, wait) {
			let timeout;
			return function(...args) {
				clearTimeout(timeout);
				timeout = setTimeout(() => func.apply(this, args), wait);
			};
		}

		apiKeyInput.addEventListener('paste', function() {
			setTimeout(() => {
				if (isValidApiKey(apiKeyInput.value.trim())) fetchListsButton.click();
			}, 50);
		});

		apiKeyInput.addEventListener('input', function() {
			updateLiteBadgeStatus('neutral');
		});

		apiKeyInput.addEventListener('input', debounce(function() {
			const apiKey = apiKeyInput.value.trim();
			if (isValidApiKey(apiKey)) {
				fetchListsButton.click();
			} else if (apiKey === '') {
				const formId = getFormId();
				if (formId) {
					fetch(`${chimpmaticLite.restUrl}settings/reset`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': chimpmaticLite.restNonce
						},
						body: JSON.stringify({ form_id: formId })
					})
					.then(response => response.json())
					.then(() => {
						const selectElement = document.getElementById('wpcf7-mailchimp-list');
						if (selectElement) selectElement.innerHTML = '';
						const label = document.getElementById('cmatic-audiences-label');
						if (label) label.textContent = 'Mailchimp Audiences';
						const fieldsContainer = document.getElementById('cmatic-fields');
						if (fieldsContainer) fieldsContainer.innerHTML = '';
						updateLiteBadgeStatus('neutral');

						document.querySelectorAll('.chmp-active').forEach(el => {
							el.classList.remove('chmp-active');
							el.classList.add('chmp-inactive');
						});

						const newUserSection = document.getElementById('chmp-new-user');
						if (newUserSection) {
							newUserSection.classList.remove('chmp-inactive');
							newUserSection.classList.add('chmp-active');
						}
					});
				}
			}
		}, 500));
	}

	function initToggleAutoSave() {
		const globalFields = ['debug', 'backlink', 'auto_update', 'telemetry'];
		const toggles = document.querySelectorAll('.cmatic-toggle input[data-field]');
		if (toggles.length === 0) return;

		toggles.forEach(function(toggle) {
			toggle.addEventListener('change', async function() {
				const field = this.dataset.field;

				if (!globalFields.includes(field)) return;

				const enabled = this.checked;
				const wrapper = this.closest('.cmatic-toggle');

				if (wrapper) wrapper.classList.add('is-saving');

				try {
					const response = await fetch(chimpmaticLite.restUrl + 'settings/toggle', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': chimpmaticLite.restNonce
						},
						body: JSON.stringify({ field, enabled })
					});

					const data = await response.json();
					if (wrapper) wrapper.classList.remove('is-saving');
					if (data.success) {
						// Sync defaultChecked to prevent CF7 beforeunload warning
						this.defaultChecked = this.checked;
					} else {
						this.checked = !enabled;
					}
				} catch (error) {
					if (wrapper) wrapper.classList.remove('is-saving');
					this.checked = !enabled;
				}
			});
		});
	}

	initToggleAutoSave();

	function initSelectAutoSave() {
		const selects = document.querySelectorAll('select.chm-select[data-field]');
		if (selects.length === 0) return;

		const perFormFields = ['double_optin', 'sync_tags'];

		selects.forEach(function(select) {
			select.addEventListener('change', async function() {
				const field = this.dataset.field;
				const value = this.value === '1';
				const wrapper = this.closest('.mcee-container');

				if (wrapper) wrapper.classList.add('is-saving');

				try {
					let url, body;

					if (perFormFields.includes(field)) {
						const formId = getFormId();
						if (!formId) {
							if (wrapper) wrapper.classList.remove('is-saving');
							this.value = value ? '0' : '1';
							return;
						}
						const rootUrl = chimpmaticLite.restUrl.replace('chimpmatic-lite/v1/', '');
						url = rootUrl + 'cmatic/form/setting';
						body = JSON.stringify({ form_id: formId, field, value });
					} else {
						url = chimpmaticLite.restUrl + 'settings/toggle';
						body = JSON.stringify({ field, enabled: value });
					}

					const response = await fetch(url, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': chimpmaticLite.restNonce
						},
						body
					});

					const data = await response.json();
					if (wrapper) wrapper.classList.remove('is-saving');
					if (data.success) {
						// Sync defaultSelected to prevent CF7 beforeunload warning
						Array.from(this.options).forEach(function(opt) {
							opt.defaultSelected = opt.selected;
						});
					} else {
						this.value = value ? '0' : '1';
					}
				} catch (error) {
					if (wrapper) wrapper.classList.remove('is-saving');
					this.value = value ? '0' : '1';
				}
			});
		});
	}

	initSelectAutoSave();

	const debugLogButton = document.querySelector('.cme-trigger-log:not(.cmatic-accordion-btn)');
	if (debugLogButton) {
		debugLogButton.addEventListener('click', async function(event) {
			event.preventDefault();

			const logPanelContainer = document.getElementById('eventlog-sys');
			const logPanel = document.getElementById('log_panel');
			const advancedSettings = document.querySelector('.vc-advanced-settings');
			const advancedToggleButton = document.querySelector('.vc-view-advanced');
			const testContainer = document.getElementById('cmatic-test-container');
			const testSubmissionBtn = document.querySelector('.vc-test-submission');

			if (!logPanelContainer || !logPanel) return;

			const isLogVisible = window.getComputedStyle(logPanelContainer).display !== 'none';

			if (isLogVisible) {
				logPanelContainer.style.transition = 'opacity 0.5s ease-out';
				logPanelContainer.style.opacity = '0';

				setTimeout(() => {
					logPanelContainer.style.display = 'none';
					logPanelContainer.style.removeProperty('opacity');
					logPanelContainer.style.removeProperty('transition');
				}, 500);

				this.setAttribute('aria-expanded', 'false');
			} else {
				if (advancedSettings) {
					const isAdvancedVisible = window.getComputedStyle(advancedSettings).display !== 'none';
					if (isAdvancedVisible) {
						advancedSettings.style.transition = 'opacity 0.5s ease-out';
						advancedSettings.style.opacity = '0';

						setTimeout(() => {
							advancedSettings.style.display = 'none';
							advancedSettings.style.removeProperty('opacity');
							advancedSettings.style.removeProperty('transition');
						}, 500);

						if (advancedToggleButton) advancedToggleButton.setAttribute('aria-expanded', 'false');
					}
				}

				if (testContainer) {
					const isTestVisible = window.getComputedStyle(testContainer).display !== 'none';
					if (isTestVisible) {
						testContainer.style.transition = 'opacity 0.5s ease-out';
						testContainer.style.opacity = '0';

						setTimeout(() => {
							testContainer.style.display = 'none';
							testContainer.style.removeProperty('opacity');
							testContainer.style.removeProperty('transition');
						}, 500);

						if (testSubmissionBtn) testSubmissionBtn.setAttribute('aria-expanded', 'false');
					}
				}

				logPanel.textContent = chimpmaticLite.i18n.loading;
				logPanelContainer.style.opacity = '0';
				logPanelContainer.style.display = 'block';
				logPanelContainer.style.transition = 'opacity 0.5s ease-in';

				setTimeout(() => { logPanelContainer.style.opacity = '1'; }, 50);
				this.setAttribute('aria-expanded', 'true');

				try {
					const data = await getDebugLog();
					logPanel.textContent = data.success ? (data.logs || data.message) : 'Error: ' + (data.message || 'Unknown error');
				} catch (error) {
					logPanel.textContent = chimpmaticLite.i18n.error;
				}
			}
		});
	}

	const advancedToggleButton = document.querySelector('.vc-view-advanced:not(.cmatic-accordion-btn)');
	if (advancedToggleButton) {
		advancedToggleButton.addEventListener('click', function(event) {
			event.preventDefault();

			const advancedSettings = document.querySelector('.vc-advanced-settings');
			const logPanelContainer = document.getElementById('eventlog-sys');
			const debugLogBtn = document.querySelector('.cme-trigger-log');
			const testContainer = document.getElementById('cmatic-test-container');
			const testSubmissionBtn = document.querySelector('.vc-test-submission');

			if (!advancedSettings) return;

			const isVisible = window.getComputedStyle(advancedSettings).display !== 'none';

			if (isVisible) {
				advancedSettings.style.transition = 'opacity 0.5s ease-out';
				advancedSettings.style.opacity = '0';

				setTimeout(() => {
					advancedSettings.style.display = 'none';
					advancedSettings.style.removeProperty('opacity');
					advancedSettings.style.removeProperty('transition');
				}, 500);

				this.setAttribute('aria-expanded', 'false');
			} else {
				if (logPanelContainer) {
					const isLogVisible = window.getComputedStyle(logPanelContainer).display !== 'none';
					if (isLogVisible) {
						logPanelContainer.style.transition = 'opacity 0.5s ease-out';
						logPanelContainer.style.opacity = '0';

						setTimeout(() => {
							logPanelContainer.style.display = 'none';
							logPanelContainer.style.removeProperty('opacity');
							logPanelContainer.style.removeProperty('transition');
						}, 500);

						if (debugLogBtn) debugLogBtn.setAttribute('aria-expanded', 'false');
					}
				}

				if (testContainer) {
					const isTestVisible = window.getComputedStyle(testContainer).display !== 'none';
					if (isTestVisible) {
						testContainer.style.transition = 'opacity 0.5s ease-out';
						testContainer.style.opacity = '0';

						setTimeout(() => {
							testContainer.style.display = 'none';
							testContainer.style.removeProperty('opacity');
							testContainer.style.removeProperty('transition');
						}, 500);

						if (testSubmissionBtn) testSubmissionBtn.setAttribute('aria-expanded', 'false');
					}
				}

				advancedSettings.style.opacity = '0';
				advancedSettings.style.display = 'block';
				advancedSettings.style.transition = 'opacity 0.5s ease-in';

				setTimeout(() => { advancedSettings.style.opacity = '1'; }, 50);
				this.setAttribute('aria-expanded', 'true');
			}
		});
	}

	const clearLogsButton = document.querySelector('.vc-clear-logs');
	if (clearLogsButton) {
		clearLogsButton.addEventListener('click', async function(event) {
			event.preventDefault();

			const logPanel = document.getElementById('log_panel');
			const originalText = this.textContent;

			this.disabled = true;
			this.textContent = 'Clearing Logs...';

			try {
				const data = await clearDebugLog();

				if (data.success && data.cleared) {
					this.textContent = 'Cleared';
					if (logPanel) logPanel.textContent = 'Debug log cleared.';
				} else {
					this.textContent = 'Cleared';
					if (logPanel) logPanel.textContent = data.message || 'Debug log was already empty.';
				}

				setTimeout(() => {
					this.textContent = 'Clear Logs';
					this.disabled = false;
				}, 2000);
			} catch (error) {
				this.textContent = 'Clearing Log Error';
				setTimeout(() => {
					this.textContent = 'Clear Logs';
					this.disabled = false;
				}, 3000);
			}
		});
	}

	(function initAccordionPanels() {
		const accordionContainer = document.querySelector('.cmatic-panel-toggles');
		if (!accordionContainer) return;

		const panelConfig = {
			'eventlog-sys': {
				filtered: true,
				onOpen: async function(panel) {
					const logPanel = document.getElementById('log_panel');
					const toggleLink = panel.querySelector('.vc-toggle-filter');
					const config = panelConfig['eventlog-sys'];

					if (toggleLink && !toggleLink.hasAttribute('data-listener')) {
						toggleLink.setAttribute('data-listener', 'true');
						toggleLink.addEventListener('click', async function(e) {
							e.preventDefault();
							config.filtered = !config.filtered;
							this.textContent = config.filtered ? 'Show All' : 'Plugin Only';
							this.setAttribute('data-filtered', config.filtered ? '1' : '0');

							if (logPanel) {
								logPanel.textContent = chimpmaticLite.i18n?.loading || 'Loading...';
								try {
									const data = await getDebugLog(config.filtered);
									logPanel.textContent = data.success ? (data.logs || data.message) : 'Error: ' + (data.message || 'Unknown error');
								} catch (error) {
									logPanel.textContent = chimpmaticLite.i18n?.error || 'Error loading logs';
								}
							}
						});
					}

					if (logPanel && typeof chimpmaticLite !== 'undefined') {
						logPanel.textContent = chimpmaticLite.i18n?.loading || 'Loading...';
						try {
							const data = await getDebugLog(config.filtered);
							logPanel.textContent = data.success ? (data.logs || data.message) : 'Error: ' + (data.message || 'Unknown error');
						} catch (error) {
							logPanel.textContent = chimpmaticLite.i18n?.error || 'Error loading logs';
						}
					}
				}
			},
			'cmatic-test-container': {
				useModal: true
			}
		};

		function hidePanel(panel, button) {
			if (!panel) return;
			const isVisible = window.getComputedStyle(panel).display !== 'none';
			if (!isVisible) return;

			panel.style.transition = 'opacity 0.5s ease-out';
			panel.style.opacity = '0';

			setTimeout(() => {
				panel.style.display = 'none';
				panel.style.removeProperty('opacity');
				panel.style.removeProperty('transition');
			}, 500);

			if (button) button.setAttribute('aria-expanded', 'false');
		}

		function showPanel(panel, button, config) {
			if (!panel) return;

			panel.style.opacity = '0';
			panel.style.display = 'block';
			panel.style.transition = 'opacity 0.5s ease-in';

			setTimeout(() => { panel.style.opacity = '1'; }, 50);
			if (button) button.setAttribute('aria-expanded', 'true');

			if (config && config.onOpen) {
				config.onOpen(panel);
			}
		}

		function closeAllPanels() {
			const buttons = accordionContainer.querySelectorAll('.cmatic-accordion-btn');
			buttons.forEach(btn => {
				const panelId = btn.getAttribute('aria-controls');
				if (panelId) {
					const panel = document.getElementById(panelId);
					hidePanel(panel, btn);
				}
			});
		}

		accordionContainer.addEventListener('click', function(event) {
			const button = event.target.closest('.cmatic-accordion-btn');
			if (!button) return;

			event.preventDefault();

			const panelId = button.getAttribute('aria-controls');
			if (!panelId) return;

			const config = panelConfig[panelId] || {};

			if (config.useModal) {
				return;
			}

			const panel = document.getElementById(panelId);
			if (!panel) return;

			const isExpanded = button.getAttribute('aria-expanded') === 'true';

			if (isExpanded) {
				hidePanel(panel, button);
			} else {
				closeAllPanels();

				setTimeout(() => {
					showPanel(panel, button, config);
				}, 100);
			}
		});

		const buttons = accordionContainer.querySelectorAll('.cmatic-accordion-btn');
		buttons.forEach(btn => {
			const panelId = btn.getAttribute('aria-controls');
			const config = panelConfig[panelId] || {};
			if (panelId && !config.useModal) {
				const panel = document.getElementById(panelId);
				if (panel) {
					panel.style.display = 'none';
				}
			}
			btn.setAttribute('aria-expanded', 'false');
		});
	})();

	function relocateSidebarElements() {
		const moveElements = document.querySelectorAll('.mce-move');
		const submitDiv = document.getElementById('submitdiv');
		const postboxContainer = document.querySelector('.postbox-container');

		if (!moveElements.length || !submitDiv || !postboxContainer) return;

		let insertAfter = submitDiv;

		moveElements.forEach((el) => {
			if (insertAfter.nextSibling) {
				postboxContainer.insertBefore(el, insertAfter.nextSibling);
			} else {
				postboxContainer.appendChild(el);
			}
			insertAfter = el;

			el.classList.add('mce-fade-in');
			el.classList.remove('mce-hidden');
		});
	}

	relocateSidebarElements();

	async function fetchFieldsForSelectedList() {
		if (isProFieldPanelActive) return;

		const listDropdown = document.getElementById('wpcf7-mailchimp-list');
		const fetchFieldsButton = document.getElementById('mce_fetch_fields');
		const formId = getFormId();

		if (!listDropdown || !listDropdown.value || !formId) return;

		const listId = listDropdown.value;
		const originalText = fetchFieldsButton ? (fetchFieldsButton.value || fetchFieldsButton.textContent) : 'Sync Fields';

		if (fetchFieldsButton) {
			fetchFieldsButton.disabled = true;
			if (fetchFieldsButton.tagName === 'INPUT') fetchFieldsButton.value = 'Syncing Fields...';
			else fetchFieldsButton.textContent = 'Syncing Fields...';
		}

		try {
			const response = await fetch(`${chimpmaticLite.restUrl}merge-fields`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': chimpmaticLite.restNonce
				},
				body: JSON.stringify({ form_id: formId, list_id: listId })
			});

			const data = await response.json();

			if (data.success && data.merge_fields) {
				updateFieldLabels(data.merge_fields);
				applyFuzzyMatching(data.merge_fields);

				if (fetchFieldsButton) {
					if (fetchFieldsButton.tagName === 'INPUT') fetchFieldsButton.value = 'Synced ✓';
					else fetchFieldsButton.textContent = 'Synced ✓';

					setTimeout(() => {
						if (fetchFieldsButton.tagName === 'INPUT') fetchFieldsButton.value = originalText;
						else fetchFieldsButton.textContent = originalText;
						fetchFieldsButton.disabled = false;
					}, 1000);
				}
			} else {
				alert('Failed to load fields. Please try again.');
				if (fetchFieldsButton) {
					if (fetchFieldsButton.tagName === 'INPUT') fetchFieldsButton.value = originalText;
					else fetchFieldsButton.textContent = originalText;
					fetchFieldsButton.disabled = false;
				}
			}
		} catch (error) {
			alert('Error loading fields. Check console for details.');
			if (fetchFieldsButton) {
				if (fetchFieldsButton.tagName === 'INPUT') fetchFieldsButton.value = originalText;
				else fetchFieldsButton.textContent = originalText;
				fetchFieldsButton.disabled = false;
			}
		}
	}

	function attachFetchFieldsListeners() {
		const listDropdown = document.getElementById('wpcf7-mailchimp-list');
		if (listDropdown) {
			listDropdown.removeEventListener('change', handleListChange);
			listDropdown.addEventListener('change', handleListChange);
		}

		const fetchFieldsButton = document.getElementById('mce_fetch_fields');
		if (fetchFieldsButton) {
			fetchFieldsButton.removeEventListener('click', handleFetchFieldsClick);
			fetchFieldsButton.addEventListener('click', handleFetchFieldsClick);
		}
	}

	function handleListChange(e) {
		const selectedList = e.target.value;
		const fetchFieldsButton = document.getElementById('mce_fetch_fields');
		const listDropdown = e.target;

		if (isProFieldPanelActive) return;

		Array.from(listDropdown.options).forEach(opt => {
			opt.defaultSelected = (opt.value === selectedList);
		});

		if (selectedList) {
			if (fetchFieldsButton) fetchFieldsButton.disabled = false;

			for (let i = 3; i <= 8; i++) {
				const dropdown = document.getElementById(`wpcf7-mailchimp-field${i}`);
				if (dropdown) dropdown.value = ' ';
			}

			fetchFieldsForSelectedList();
		} else {
			if (fetchFieldsButton) fetchFieldsButton.disabled = true;
		}
	}

	async function handleFetchFieldsClick(event) {
		if (isProFieldPanelActive) return;
		event.preventDefault();
		await fetchFieldsForSelectedList();
	}

	attachFetchFieldsListeners();

	const initialListDropdown = document.getElementById('wpcf7-mailchimp-list');
	const initialFetchButton = document.getElementById('mce_fetch_fields');
	if (initialListDropdown && initialListDropdown.options.length > 0) {
		if (!initialListDropdown.value || initialListDropdown.value === '' || initialListDropdown.value === ' ') {
			initialListDropdown.value = initialListDropdown.options[0].value;
			if (initialFetchButton) initialFetchButton.disabled = false;
		} else {
			if (initialFetchButton && initialFetchButton.disabled) initialFetchButton.disabled = false;
		}
	}

	if (chimpmaticLite.mergeFields && chimpmaticLite.mergeFields.length > 0) {
		updateFieldLabels(chimpmaticLite.mergeFields);
	}

	function initLicenseResetButton() {
		const button = document.getElementById('cmatic-license-reset-btn');
		const messageDiv = document.getElementById('cmatic-license-reset-message');

		if (!button || !messageDiv) return;

		button.addEventListener('click', async function(e) {
			e.preventDefault();

			button.disabled = true;
			button.textContent = 'Resetting...';
			messageDiv.innerHTML = '';

			try {
				const response = await fetch(chimpmaticLite.licenseResetUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': chimpmaticLite.nonce
					},
					credentials: 'same-origin',
					body: JSON.stringify({ type: 'nuclear' })
				});

				const data = await response.json();

				if (data.success) {
					button.textContent = 'Done Resetting';
					messageDiv.innerHTML = '<span style="color: #46b450;">✓ ' + escapeHtml(data.message) + '</span><br>' +
						'<small style="color: #666;">Deleted ' + data.deleted_counts.options + ' options and ' +
						data.deleted_counts.transients + ' transients</small>';

					setTimeout(function() {
						button.textContent = 'Reset License Data';
						button.disabled = false;
						messageDiv.innerHTML = '';
					}, 3000);
				} else {
					button.textContent = 'Reset License Data';
					button.disabled = false;
					messageDiv.innerHTML = '<span style="color: #dc3232;">✗ Error: ' +
						escapeHtml(data.message || 'Unknown error occurred') + '</span>';

					setTimeout(function() { messageDiv.innerHTML = ''; }, 5000);
				}
			} catch (error) {
				button.textContent = 'Reset License Data';
				button.disabled = false;
				messageDiv.innerHTML = '<span style="color: #dc3232;">✗ Network error: ' +
					escapeHtml(error.message) + '</span>';

				setTimeout(function() { messageDiv.innerHTML = ''; }, 5000);
			}
		});
	}

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	initLicenseResetButton();

	let formHandlerAttached = false;

	function openTestModal() {
		const modal = document.getElementById('cmatic-test-modal');
		if (!modal) return;

		modal.classList.add('cmatic-modal--active');
		document.body.classList.add('cmatic-modal-open');

		const form = modal.querySelector('.wpcf7 form');
		if (form && !form.querySelector('input[name="_cmatic_test_modal"]')) {
			const hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = '_cmatic_test_modal';
			hidden.value = '1';
			form.appendChild(hidden);
		}

		if (!formHandlerAttached) {
			if (form) {
				attachTestFormHandler(form);
				formHandlerAttached = true;
			}
		}

		const closeBtn = modal.querySelector('.cmatic-modal__close');
		if (closeBtn) closeBtn.focus();
	}

	function closeTestModal() {
		const modal = document.getElementById('cmatic-test-modal');
		if (!modal) return;

		modal.classList.remove('cmatic-modal--active');
		document.body.classList.remove('cmatic-modal-open');
	}

	function attachTestFormHandler(form) {
		form.addEventListener('submit', async function(e) {
			e.preventDefault();

			const submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
			const headerSubmitBtn = document.querySelector('.cmatic-modal__submit');
			const responseOutput = form.querySelector('.wpcf7-response-output') || createResponseOutput(form);
			const formId = form.querySelector('input[name="_wpcf7"]')?.value;

			if (!formId) {
				showResponse(responseOutput, 'error', 'Form ID not found.');
				return;
			}

			const originalBtnText = submitBtn?.value || submitBtn?.textContent;
			if (submitBtn) {
				submitBtn.disabled = true;
				if (submitBtn.tagName === 'INPUT') {
					submitBtn.value = 'Sending...';
				} else {
					submitBtn.textContent = 'Sending...';
				}
			}

			if (headerSubmitBtn) {
				headerSubmitBtn.disabled = true;
				headerSubmitBtn.textContent = 'Submitting...';
				headerSubmitBtn.classList.remove('cmatic-modal__submit--success', 'cmatic-modal__submit--error');
			}

			responseOutput.textContent = '';
			responseOutput.className = 'wpcf7-response-output';
			responseOutput.style.display = 'none';
			hideChimpmaticFeedback();

			let isSuccess = false;

			try {
				const formData = new FormData(form);
				const restUrl = chimpmaticLite.restUrl.replace('chimpmatic-lite/v1/', '') + 'contact-form-7/v1/contact-forms/' + formId + '/feedback';

				const response = await fetch(restUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				});

				const data = await response.json();

				if (data.chimpmatic) {
					showChimpmaticFeedback(data.chimpmatic);
				}

				if (data.status === 'mail_sent') {
					isSuccess = true;
					showResponse(responseOutput, 'success', data.message || 'Message sent successfully.');
					form.reset();
					refreshDebugLogsAfterSubmission();
				} else if (data.status === 'validation_failed' || data.status === 'mail_failed') {
					showResponse(responseOutput, 'error', data.message || 'There was an error sending your message.');
					if (data.invalid_fields) {
						data.invalid_fields.forEach(field => {
							const wrap = form.querySelector(`.wpcf7-form-control-wrap[data-name="${field.field}"]`);
							if (wrap) {
								const tip = document.createElement('span');
								tip.className = 'wpcf7-not-valid-tip';
								tip.textContent = field.message;
								wrap.appendChild(tip);
							}
						});
					}
				} else {
					showResponse(responseOutput, 'error', data.message || 'An error occurred.');
				}
			} catch (error) {
				console.error('Test form submission error:', error);
				showResponse(responseOutput, 'error', 'Network error. Please try again.');
			} finally {
				if (submitBtn) {
					submitBtn.disabled = false;
					if (submitBtn.tagName === 'INPUT') {
						submitBtn.value = originalBtnText;
					} else {
						submitBtn.textContent = originalBtnText;
					}
				}

				if (headerSubmitBtn) {
					headerSubmitBtn.disabled = false;
					if (isSuccess) {
						headerSubmitBtn.textContent = 'Success!';
						headerSubmitBtn.classList.add('cmatic-modal__submit--success');
					} else {
						headerSubmitBtn.textContent = 'Error';
						headerSubmitBtn.classList.add('cmatic-modal__submit--error');
					}
					setTimeout(() => {
						headerSubmitBtn.textContent = 'Submit';
						headerSubmitBtn.classList.remove('cmatic-modal__submit--success', 'cmatic-modal__submit--error');
					}, 2000);
				}
			}
		});

		form.addEventListener('input', function(e) {
			const wrap = e.target.closest('.wpcf7-form-control-wrap');
			if (wrap) {
				const tip = wrap.querySelector('.wpcf7-not-valid-tip');
				if (tip) tip.remove();
			}
		});
	}

	function createResponseOutput(form) {
		const output = document.createElement('div');
		output.className = 'wpcf7-response-output';
		output.setAttribute('aria-live', 'polite');
		form.appendChild(output);
		return output;
	}

	function showResponse(element, type, message) {
		element.textContent = message;
		element.style.display = 'block';
		element.className = 'wpcf7-response-output';
		if (type === 'success') {
			element.classList.add('wpcf7-mail-sent-ok');
			element.style.borderColor = '#00a32a';
			element.style.background = '#edfaef';
		} else {
			element.classList.add('wpcf7-mail-sent-ng');
			element.style.borderColor = '#d63638';
			element.style.background = '#fcf0f1';
		}
	}

	function showChimpmaticFeedback(chimpmatic) {
		const modal = document.getElementById('cmatic-test-modal');
		if (!modal) return;

		const feedback = modal.querySelector('.cmatic-modal__feedback');
		if (!feedback) return;

		const icon = feedback.querySelector('.cmatic-modal__feedback-icon');
		const title = feedback.querySelector('.cmatic-modal__feedback-title');
		const details = feedback.querySelector('.cmatic-modal__feedback-details');

		feedback.classList.remove('cmatic-modal__feedback--success', 'cmatic-modal__feedback--error', 'cmatic-modal__feedback--skipped');

		if (chimpmatic.success === true) {
			feedback.classList.add('cmatic-modal__feedback--success');
			icon.innerHTML = '<span class="dashicons dashicons-yes-alt"></span>';
			title.textContent = chimpmatic.message;

			const sent = chimpmatic.merge_vars || {};
			const received = chimpmatic.received || {};
			const allKeys = new Set([...Object.keys(sent), ...Object.keys(received)]);

			if (allKeys.size > 0) {
				let tableHtml = '<table class="cmatic-modal__feedback-table">';
				tableHtml += '<thead><tr><th>Field</th><th>Sent</th><th>Received</th></tr></thead><tbody>';

				for (const key of allKeys) {
					const sentVal = sent[key] !== undefined ? escapeHtml(String(sent[key])) : '<span class="field-empty">—</span>';
					const recvVal = received[key] !== undefined ? escapeHtml(String(received[key])) : '<span class="field-empty">—</span>';
					const mismatch = sent[key] !== undefined && received[key] !== undefined && String(sent[key]) !== String(received[key]);
					const rowClass = mismatch ? ' class="field-mismatch"' : '';
					tableHtml += '<tr' + rowClass + '><td class="field-key">' + escapeHtml(key) + '</td><td>' + sentVal + '</td><td>' + recvVal + '</td></tr>';
				}

				tableHtml += '</tbody></table>';
				details.innerHTML = tableHtml;
			} else {
				details.innerHTML = '';
			}
		} else if (chimpmatic.skipped === true) {
			feedback.classList.add('cmatic-modal__feedback--skipped');
			icon.innerHTML = '<span class="dashicons dashicons-info-outline"></span>';
			title.textContent = 'Subscription skipped';
			details.textContent = chimpmatic.message;
		} else {
			feedback.classList.add('cmatic-modal__feedback--error');
			icon.innerHTML = '<span class="dashicons dashicons-dismiss"></span>';
			title.textContent = 'Subscription failed';
			details.textContent = chimpmatic.message;
		}

		feedback.style.display = 'flex';
	}

	function hideChimpmaticFeedback() {
		const modal = document.getElementById('cmatic-test-modal');
		if (!modal) return;

		const feedback = modal.querySelector('.cmatic-modal__feedback');
		if (feedback) {
			feedback.style.display = 'none';
		}
	}

	async function refreshDebugLogsAfterSubmission() {
		await new Promise(resolve => setTimeout(resolve, 500));

		const logPanelContainer = document.getElementById('eventlog-sys');
		const logPanel = document.getElementById('log_panel');
		const debugLogBtn = document.querySelector('.cme-trigger-log');

		if (!logPanelContainer || !logPanel) return;

		const isLogVisible = window.getComputedStyle(logPanelContainer).display !== 'none';
		if (!isLogVisible) {
			logPanel.textContent = chimpmaticLite.i18n.loading;
			logPanelContainer.style.opacity = '0';
			logPanelContainer.style.display = 'block';
			logPanelContainer.style.transition = 'opacity 0.5s ease-in';
			setTimeout(() => { logPanelContainer.style.opacity = '1'; }, 50);
			if (debugLogBtn) debugLogBtn.setAttribute('aria-expanded', 'true');
		}

		try {
			const data = await getDebugLog();
			logPanel.textContent = data.success ? (data.logs || data.message) : 'Error: ' + (data.message || 'Unknown error');
		} catch (error) {
			logPanel.textContent = chimpmaticLite.i18n.error;
		}
	}

	document.addEventListener('click', function(event) {
		const btn = event.target.closest('.vc-test-submission');
		if (!btn) return;

		event.preventDefault();
		openTestModal();
	});

	document.addEventListener('click', function(event) {
		const closeBtn = event.target.closest('#cmatic-test-modal .cmatic-modal__close');
		if (!closeBtn) return;

		event.preventDefault();
		closeTestModal();
	});

	document.addEventListener('click', function(event) {
		const overlay = event.target.closest('#cmatic-test-modal .cmatic-modal__overlay');
		if (!overlay) return;

		closeTestModal();
	});

	document.addEventListener('keydown', function(event) {
		if (event.key === 'Escape') {
			const modal = document.getElementById('cmatic-test-modal');
			if (modal && modal.classList.contains('cmatic-modal--active')) {
				closeTestModal();
			}
		}
	});

	document.addEventListener('click', function(event) {
		const submitBtn = event.target.closest('#cmatic-test-modal .cmatic-modal__submit');
		if (!submitBtn) return;

		event.preventDefault();
		const modal = document.getElementById('cmatic-test-modal');
		if (!modal) return;

		const form = modal.querySelector('.wpcf7 form');
		if (!form) return;

		form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
	});
});

document.addEventListener('DOMContentLoaded', function() {
	const eye = document.querySelector('.cmatic-eye');
	const input = document.getElementById('cmatic-api');
	if (!eye || !input) return;

	let cachedRealKey = null;

	eye.addEventListener('click', async function(e) {
		e.preventDefault();
		const icon = this.querySelector('.dashicons');
		const isMasked = input.dataset.isMasked === '1';
		const hasKey = input.dataset.hasKey === '1';

		if (isMasked && hasKey) {
			if (!cachedRealKey) {
				const formId = typeof chimpmaticLite !== 'undefined' ? chimpmaticLite.formId : 0;
				if (!formId) {
					console.warn('ChimpMatic: No form ID available');
					return;
				}

				try {
					eye.style.opacity = '0.5';
					const response = await fetch(
						`${chimpmaticLite.restUrl}api-key/${formId}`,
						{
							method: 'GET',
							headers: {
								'X-WP-Nonce': chimpmaticLite.restNonce,
								'Content-Type': 'application/json'
							}
						}
					);

					if (!response.ok) {
						throw new Error('Failed to fetch API key');
					}

					const data = await response.json();
					cachedRealKey = data.api_key || '';
				} catch (err) {
					console.error('ChimpMatic: Error fetching API key', err);
					eye.style.opacity = '1';
					return;
				}
				eye.style.opacity = '1';
			}

			input.value = cachedRealKey;
			input.dataset.isMasked = '0';
			icon.classList.remove('dashicons-visibility');
			icon.classList.add('dashicons-hidden');
		} else {
			input.value = input.dataset.maskedKey;
			input.dataset.isMasked = '1';
			icon.classList.remove('dashicons-hidden');
			icon.classList.add('dashicons-visibility');
		}
	});

	const form = input.closest('form');
	if (form) {
		form.addEventListener('submit', async function(e) {
			const isMasked = input.dataset.isMasked === '1';
			const hasKey = input.dataset.hasKey === '1';

			if (isMasked && hasKey) {
				if (cachedRealKey) {
					input.value = cachedRealKey;
				} else {
					const formId = typeof chimpmaticLite !== 'undefined' ? chimpmaticLite.formId : 0;
					if (formId) {
						e.preventDefault();
						try {
							const response = await fetch(
								`${chimpmaticLite.restUrl}api-key/${formId}`,
								{
									method: 'GET',
									headers: {
										'X-WP-Nonce': chimpmaticLite.restNonce,
										'Content-Type': 'application/json'
									}
								}
							);

							if (response.ok) {
								const data = await response.json();
								cachedRealKey = data.api_key || '';
								input.value = cachedRealKey;
							}
						} catch (err) {
							console.error('ChimpMatic: Error fetching API key for submit', err);
						}
						form.submit();
					}
				}
			}
		});
	}

	(function initContactLookup() {
		const lookupBtn = document.getElementById('cmatic-lookup-btn');
		const emailInput = document.getElementById('cmatic-lookup-email');
		const resultsContainer = document.getElementById('cmatic-lookup-results');
		const formContainer = emailInput ? emailInput.closest('div') : null;

		if (!lookupBtn || !emailInput || !resultsContainer || !formContainer) {
			return;
		}

		function showForm() {
			formContainer.classList.remove('cmatic-hidden');
			resultsContainer.classList.add('cmatic-hidden');
			resultsContainer.innerHTML = '';
			emailInput.value = '';
			emailInput.focus();
		}

		function formatDate(dateStr) {
			if (!dateStr) return null;
			const date = new Date(dateStr);
			return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
		}

		function getStatusInfo(status) {
			const statusMap = {
				'subscribed': { class: 'cmatic-status-subscribed', badge: 'cmatic-badge-success', label: 'Subscribed' },
				'unsubscribed': { class: 'cmatic-status-unsubscribed', badge: 'cmatic-badge-neutral', label: 'Unsubscribed' },
				'pending': { class: 'cmatic-status-pending', badge: 'cmatic-badge-warning', label: 'Pending' },
				'cleaned': { class: 'cmatic-status-unsubscribed', badge: 'cmatic-badge-neutral', label: 'Cleaned' },
				'archived': { class: 'cmatic-status-not-found', badge: 'cmatic-badge-neutral', label: 'Archived' },
				'not_subscribed': { class: 'cmatic-status-not-found', badge: 'cmatic-badge-neutral', label: 'Not Found' }
			};
			return statusMap[status] || { class: 'cmatic-status-not-found', badge: 'cmatic-badge-neutral', label: status };
		}

		function renderMergeFields(mergeFields) {
			if (!mergeFields || Object.keys(mergeFields).length === 0) {
				return '<span class="cmatic-empty">No fields</span>';
			}

			const fieldLabels = {
				'EMAIL': 'Email', 'FNAME': 'First Name', 'LNAME': 'Last Name',
				'PHONE': 'Phone', 'ADDRESS': 'Address', 'BIRTHDAY': 'Birthday',
				'COMPANY': 'Company', 'WEBSITE': 'Website', 'AGE': 'Age',
				'GENDER': 'Gender', 'ZIPCODE': 'Zip Code', 'MMERGE3': 'Field 3',
				'MMERGE4': 'Field 4', 'MMERGE5': 'Field 5', 'MMERGE6': 'Field 6'
			};

			let rows = '';
			for (const [key, value] of Object.entries(mergeFields)) {
				const fieldKey = key.toLowerCase();
				if (typeof value === 'object' && value !== null) {
					if (value.addr1 || value.city || value.state || value.zip || value.country) {
						const addrParts = [value.addr1, value.addr2, value.city, value.state, value.zip, value.country].filter(Boolean);
						const label = fieldLabels[key] || key;
						rows += `<tr data-field="${fieldKey}"><th>${label}</th><td class="cmatic-val">${addrParts.length ? addrParts.join(', ') : '—'}</td></tr>`;
					}
					continue;
				}
				const label = fieldLabels[key] || key;
				const displayValue = (value && value !== '') ? value : '—';
				rows += `<tr data-field="${fieldKey}"><th>${label}</th><td class="cmatic-val">${displayValue}</td></tr>`;
			}

			if (!rows) return '<span class="cmatic-empty">No fields configured</span>';
			return `<table class="cmatic-field-table">${rows}</table>`;
		}

		function renderTags(tags) {
			if (!tags || tags.length === 0) {
				return '<div data-section="tags"><span class="cmatic-empty">No tags</span></div>';
			}
			return '<div data-section="tags" class="cmatic-tag-list">' +
				tags.map(tag => `<span class="cmatic-tag-chip cmatic-val">${tag}</span>`).join('') +
				'</div>';
		}

		function renderInterests(interests) {
			if (!interests || Object.keys(interests).length === 0) {
				return '<div data-section="groups"><span class="cmatic-empty">No groups assigned</span></div>';
			}
			let html = '<div data-section="groups">';
			for (const [category, items] of Object.entries(interests)) {
				const catKey = category.toLowerCase().replace(/\s+/g, '-');
				const itemsArray = Array.isArray(items) ? items : [items];
				html += `<div data-group="${catKey}" style="margin-bottom: 6px;"><strong style="font-size: 10px;">${category}:</strong> `;
				html += itemsArray.map(i => `<span class="cmatic-tag-chip cmatic-val">${i}</span>`).join(' ');
				html += '</div>';
			}
			html += '</div>';
			return html;
		}

		function renderMarketingPermissions(permissions) {
			if (!permissions) {
				return '<div data-section="gdpr"><span class="cmatic-empty">No GDPR permissions</span></div>';
			}

			const isArray = Array.isArray(permissions);

			if (isArray && permissions.length === 0) {
				return '<div data-section="gdpr"><span class="cmatic-empty">No GDPR permissions</span></div>';
			}
			if (!isArray && Object.keys(permissions).length === 0) {
				return '<div data-section="gdpr"><span class="cmatic-empty">No GDPR permissions</span></div>';
			}

			let rows = '';

			if (!isArray) {
				let idx = 0;
				for (const [key, value] of Object.entries(permissions)) {
					const displayVal = Array.isArray(value) ? value.join(', ') : value;
					rows += `<tr data-field="gdpr-${idx}"><th>Permission ${idx + 1}</th><td class="cmatic-val">${displayVal}</td></tr>`;
					idx++;
				}
				return `<table class="cmatic-field-table" data-section="gdpr">${rows}</table>`;
			}

			if (typeof permissions[0] === 'string') {
				permissions.forEach((hash, idx) => {
					rows += `<tr data-field="gdpr-${idx}"><th>Permission ${idx + 1}</th><td class="cmatic-val">${hash}</td></tr>`;
				});
				return `<table class="cmatic-field-table" data-section="gdpr">${rows}</table>`;
			}

			permissions.forEach((perm, idx) => {
				const permKey = perm.marketing_permission_id || `gdpr-${idx}`;
				const enabled = perm.enabled ? '✓ Yes' : '✗ No';
				rows += `<tr data-field="${permKey}"><th>${perm.text || perm.marketing_permission_id}</th><td class="cmatic-val">${enabled}</td></tr>`;
			});
			return `<table class="cmatic-field-table" data-section="gdpr">${rows}</table>`;
		}

		function getLanguageName(code) {
			if (!code) return '—';
			const languages = {
				'en': 'English', 'es': 'Spanish', 'fr': 'French', 'de': 'German',
				'pt': 'Portuguese', 'it': 'Italian', 'nl': 'Dutch', 'ru': 'Russian',
				'ja': 'Japanese', 'zh': 'Chinese', 'ko': 'Korean', 'ar': 'Arabic',
				'hi': 'Hindi', 'pl': 'Polish', 'tr': 'Turkish', 'vi': 'Vietnamese',
				'th': 'Thai', 'sv': 'Swedish', 'da': 'Danish', 'fi': 'Finnish',
				'no': 'Norwegian', 'cs': 'Czech', 'el': 'Greek', 'he': 'Hebrew',
				'hu': 'Hungarian', 'id': 'Indonesian', 'ms': 'Malay', 'ro': 'Romanian',
				'sk': 'Slovak', 'uk': 'Ukrainian', 'bg': 'Bulgarian', 'hr': 'Croatian',
				'ca': 'Catalan', 'et': 'Estonian', 'lv': 'Latvian', 'lt': 'Lithuanian',
				'sl': 'Slovenian', 'sr': 'Serbian', 'tl': 'Tagalog', 'fa': 'Persian'
			};
			return languages[code.toLowerCase()] || code.toUpperCase();
		}

		function renderPreferences(result) {
			let rows = '';

			const emailType = result.email_type ? result.email_type.toUpperCase() : '—';
			rows += `<tr data-field="email_type"><th>Email format</th><td class="cmatic-val">${emailType}</td></tr>`;

			const language = getLanguageName(result.language);
			rows += `<tr data-field="language"><th>Language</th><td class="cmatic-val">${language}</td></tr>`;

			const vip = result.vip ? 'Yes' : 'No';
			rows += `<tr data-field="vip"><th>VIP</th><td class="cmatic-val">${vip}</td></tr>`;

			if (result.member_rating !== null && result.member_rating !== undefined) {
				const stars = '★'.repeat(result.member_rating) + '☆'.repeat(5 - result.member_rating);
				rows += `<tr data-field="member_rating"><th>Contact rating</th><td class="cmatic-val">${stars}</td></tr>`;
			} else {
				rows += `<tr data-field="member_rating"><th>Contact rating</th><td class="cmatic-val">—</td></tr>`;
			}

			rows += `<tr data-field="email_client"><th>Email client</th><td class="cmatic-val">${result.email_client || '—'}</td></tr>`;

			if (result.location && (result.location.country_code || result.location.timezone)) {
				const locParts = [];
				if (result.location.country_code) locParts.push(result.location.country_code);
				if (result.location.region) locParts.push(result.location.region);
				if (result.location.timezone) locParts.push(`(${result.location.timezone})`);
				rows += `<tr data-field="location"><th>Location</th><td class="cmatic-val">${locParts.join(' ') || '—'}</td></tr>`;
			} else {
				rows += `<tr data-field="location"><th>Location</th><td class="cmatic-val">—</td></tr>`;
			}

			const smsConsent = result.consents_to_one_to_one_messaging === true ? 'Yes' :
				(result.consents_to_one_to_one_messaging === false ? 'No' : '—');
			rows += `<tr data-field="sms_consent"><th>SMS consent</th><td class="cmatic-val">${smsConsent}</td></tr>`;

			return `<table class="cmatic-field-table" data-section="preferences">${rows}</table>`;
		}

		function renderResultCard(result, isFirst) {
			const statusInfo = getStatusInfo(result.status);
			const isFound = result.found;

			let html = '<div class="cmatic-result-card">';

			if (isFound) {
				const expandedClass = isFirst ? ' cmatic-expanded' : '';
				html += `<div class="cmatic-card-header cmatic-expandable${expandedClass}" onclick="this.classList.toggle('cmatic-expanded'); this.nextElementSibling.classList.toggle('cmatic-visible');">`;
			} else {
				html += '<div class="cmatic-card-header">';
			}

			html += `
				<div class="cmatic-header-left">
					<span class="cmatic-status-dot ${statusInfo.class}"></span>
					<strong>${result.list_name}</strong>
				</div>
				<div class="cmatic-header-right">
					<span class="cmatic-badge ${statusInfo.badge}">${statusInfo.label}</span>
					${isFound ? '<span class="cmatic-chevron"></span>' : ''}
				</div>
			</div>`;

			if (isFound) {
				const visibleClass = isFirst ? ' cmatic-visible' : '';
				html += `<div class="cmatic-card-body${visibleClass}">`;

				html += '<div class="cmatic-section-header">Contact Info</div>';
				html += `<div data-section="contact-info">`;
				html += `<table class="cmatic-field-table"><tr data-field="source"><th>Source</th><td class="cmatic-val">${result.source || '—'}</td></tr></table>`;
				html += renderMergeFields(result.merge_fields);
				html += `</div>`;

				html += '<div class="cmatic-section-header">Tags</div>';
				html += renderTags(result.tags);

				html += '<div class="cmatic-section-header">Groups</div>';
				html += renderInterests(result.interests);

				html += '<div class="cmatic-section-header">GDPR / Marketing Permissions</div>';
				html += renderMarketingPermissions(result.marketing_permissions);

				html += '<div class="cmatic-section-header">Details</div>';
				html += '<table class="cmatic-field-table" data-section="details">';
				html += `<tr data-field="subscribed"><th>Subscribed</th><td class="cmatic-val">${result.subscribed ? formatDate(result.subscribed) : '—'}</td></tr>`;
				html += `<tr data-field="timestamp_signup"><th>Signup date</th><td class="cmatic-val">${result.timestamp_signup ? formatDate(result.timestamp_signup) : '—'}</td></tr>`;
				html += `<tr data-field="ip_signup"><th>IP signup</th><td class="cmatic-val">${result.ip_signup || '—'}</td></tr>`;
				html += `<tr data-field="last_changed"><th>Last changed</th><td class="cmatic-val">${result.last_changed ? formatDate(result.last_changed) : '—'}</td></tr>`;
				html += `<tr data-field="unsubscribe_reason"><th>Unsubscribe reason</th><td class="cmatic-val">${result.unsubscribe_reason || '—'}</td></tr>`;
				html += '</table>';

				html += '<div class="cmatic-section-header">Preferences</div>';
				html += renderPreferences(result);

				html += '</div>';
			}

			html += '</div>';
			return html;
		}

		function renderResults(data) {
			let html = '';

			const summaryClass = data.found ? 'cmatic-found' : 'cmatic-not-found';
			html += `<div class="cmatic-lookup-summary ${summaryClass}"><strong>${data.message}</strong></div>`;

			const sortedResults = [...data.results].sort((a, b) => {
				if (a.found && !b.found) return -1;
				if (!a.found && b.found) return 1;
				return 0;
			});

			let firstFound = true;
			sortedResults.forEach(result => {
				html += renderResultCard(result, result.found && firstFound);
				if (result.found) firstFound = false;
			});

			html += '<div style="text-align: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee;">';
			html += '<a href="#" id="cmatic-new-lookup" style="font-size: 12px; color: #2271b1; text-decoration: none;">New Lookup</a>';
			html += '</div>';

			return html;
		}

		function applyLiteBlur() {
			const freeMergeFieldCount = 6;

			const proOnlyFields = [
				'source', 'ip_signup', 'subscribed', 'timestamp_signup',
				'member_rating', 'location', 'email_client', 'vip',
				'language', 'email_type', 'sms_consent'
			];

			const proOnlySections = ['tags', 'groups', 'gdpr', 'preferences'];

			const contactInfoSection = resultsContainer.querySelector('[data-section="contact-info"]');
			if (contactInfoSection) {
				const mergeFieldRows = contactInfoSection.querySelectorAll('tr[data-field]');
				let mergeFieldIndex = 0;
				mergeFieldRows.forEach(row => {
					if (mergeFieldIndex >= freeMergeFieldCount) {
						const val = row.querySelector('.cmatic-val');
						if (val) val.classList.add('cmatic-not-pro');
					}
					mergeFieldIndex++;
				});
			}

			proOnlyFields.forEach(field => {
				const rows = resultsContainer.querySelectorAll(`tr[data-field="${field}"]`);
				rows.forEach(row => {
					const val = row.querySelector('.cmatic-val');
					if (val) val.classList.add('cmatic-not-pro');
				});
			});

			proOnlySections.forEach(section => {
				const elements = resultsContainer.querySelectorAll(`[data-section="${section}"]`);
				elements.forEach(el => {
					el.querySelectorAll('.cmatic-val, .cmatic-empty, .cmatic-tag-chip').forEach(child => {
						child.classList.add('cmatic-not-pro');
					});
				});
			});

			const blurredElements = resultsContainer.querySelectorAll('.cmatic-not-pro');
			blurredElements.forEach(el => {
				el.addEventListener('click', showUpsellTooltip);
			});
		}

		function showUpsellTooltip(e) {
			e.preventDefault();
			e.stopPropagation();

			const existing = document.querySelector('.cmatic-upsell-tooltip');
			if (existing) existing.remove();

			const tooltip = document.createElement('div');
			tooltip.className = 'cmatic-upsell-tooltip';
			tooltip.innerHTML = `
				<strong>Pro Feature</strong><br>
				Unlock full contact insights with ChimpMatic Pro.<br><br>
				<a href="https://chimpmatic.com/pro/?utm_source=plugin&utm_medium=contact-lookup&utm_campaign=upsell" target="_blank">Upgrade to Pro →</a>
			`;

			const rect = e.currentTarget.getBoundingClientRect();
			const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			tooltip.style.position = 'absolute';
			tooltip.style.top = (rect.bottom + scrollTop + 8) + 'px';
			tooltip.style.left = rect.left + 'px';

			document.body.appendChild(tooltip);

			setTimeout(() => {
				document.addEventListener('click', function closeTooltip(evt) {
					if (!tooltip.contains(evt.target)) {
						tooltip.remove();
						document.removeEventListener('click', closeTooltip);
					}
				});
			}, 10);
		}

		lookupBtn.addEventListener('click', async function() {
			const email = emailInput.value.trim();
			const formId = emailInput.dataset.formId;

			if (!email) {
				resultsContainer.innerHTML = '<div class="cmatic-lookup-summary cmatic-not-found">Please enter an email address.</div>';
				resultsContainer.classList.remove('cmatic-hidden');
				return;
			}

			lookupBtn.disabled = true;
			lookupBtn.textContent = 'Looking up...';
			resultsContainer.innerHTML = '<div class="cmatic-lookup-summary" style="border-color: #72aee6; background: #f0f6fc;">Checking all audiences...</div>';
			resultsContainer.classList.remove('cmatic-hidden');

			try {
				const response = await fetch(chimpmaticLite.restUrl + 'contact/lookup', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': chimpmaticLite.restNonce
					},
					body: JSON.stringify({
						email: email,
						form_id: parseInt(formId, 10)
					})
				});

				const data = await response.json();

				if (!response.ok) {
					throw new Error(data.message || 'Search failed');
				}

				formContainer.classList.add('cmatic-hidden');
				resultsContainer.innerHTML = renderResults(data);

				const newLookupLink = document.getElementById('cmatic-new-lookup');
				if (newLookupLink) {
					newLookupLink.addEventListener('click', function(e) {
						e.preventDefault();
						showForm();
					});
				}

				if (!data.is_pro) {
					applyLiteBlur();
				}

			} catch (error) {
				resultsContainer.innerHTML = `<div class="cmatic-lookup-summary cmatic-not-found">Error: ${error.message}</div>`;
			} finally {
				lookupBtn.disabled = false;
				lookupBtn.textContent = 'Lookup';
			}
		});

		emailInput.addEventListener('keypress', function(e) {
			if (e.key === 'Enter') {
				lookupBtn.click();
			}
		});
	})();
});
