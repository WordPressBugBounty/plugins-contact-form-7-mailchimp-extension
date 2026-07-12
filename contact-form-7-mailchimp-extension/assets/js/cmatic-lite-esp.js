'use strict';

(function() {
	function init() {
		const root = document.getElementById('cmatic-provider-data');
		const onboarding = document.getElementById('cmatic-provider-onboarding');
		const selector = document.getElementById('cmatic-provider');
		const headerContext = document.getElementById('cmatic-header-provider-context');
		const mailchimpView = document.getElementById('cmatic-mailchimp-settings');

		if (
			!root
			|| !onboarding
			|| !selector
			|| !headerContext
			|| !mailchimpView
			|| typeof chimpmaticLiteEsp === 'undefined'
			|| typeof chimpmaticLiteEspState === 'undefined'
		) {
			return;
		}

		const state = {
			activeProvider: chimpmaticLiteEspState.active_provider || '',
			savedProvider: chimpmaticLiteEspState.active_provider || '',
			providers: chimpmaticLiteEspState.providers || {}
		};
		const initialProviders = JSON.parse(JSON.stringify(state.providers));
		const controllers = new Map();
		let generation = 0;

		const parentForm = selector.closest('form');
		const message = document.getElementById('cmatic-provider-message');
		const progress = document.getElementById('cmatic-provider-progress');
		const progressDestination = document.getElementById('cmatic-provider-progress-destination');
		const completion = document.getElementById('cmatic-provider-completion');
		const completionOutcome = document.getElementById('cmatic-provider-completion-outcome');
		const completionMeta = document.getElementById('cmatic-provider-completion-meta');
		const credentialsIntro = document.getElementById('cmatic-provider-credentials-intro');
		const credentialsHeading = document.getElementById('cmatic-provider-credentials-heading');
		const credentialsDescription = document.getElementById('cmatic-provider-credentials-description');
		const authRow = document.getElementById('cmatic-provider-auth-row');
		const authFields = document.getElementById('cmatic-provider-auth-fields');
		const connectButton = document.getElementById('cmatic-provider-connect');
		const cancelCredentialButton = document.getElementById('cmatic-provider-cancel-credential');
		const connectedSummary = document.getElementById('cmatic-provider-connected-summary');
		const connectedTitle = document.getElementById('cmatic-provider-connected-title');
		const refreshButton = document.getElementById('cmatic-provider-refresh');
		const replaceCredentialButton = document.getElementById('cmatic-provider-replace-credential');
		const disconnectButton = document.getElementById('cmatic-provider-disconnect');
		const recoverySummary = document.getElementById('cmatic-provider-recovery-summary');
		const recoveryTitle = document.getElementById('cmatic-provider-recovery-title');
		const retryButton = document.getElementById('cmatic-provider-retry');
		const recoveryReplaceButton = document.getElementById('cmatic-provider-recovery-replace');
		const recoveryDisconnectButton = document.getElementById('cmatic-provider-recovery-disconnect');
		const destinationLocked = document.getElementById('cmatic-provider-destination-locked');
		const destinationRow = document.getElementById('cmatic-provider-destination-row');
		const destinationHeading = document.getElementById('cmatic-provider-destination-heading');
		const destinationDescription = document.getElementById('cmatic-provider-destination-description');
		const destinationLabel = document.getElementById('cmatic-provider-destination-label');
		const destinationSelect = document.getElementById('cmatic-provider-list');
		const mappingsLocked = document.getElementById('cmatic-provider-mappings-locked');
		const mappingsGrid = document.getElementById('cmatic-provider-mappings');
		const mappingsHeading = document.getElementById('cmatic-provider-mappings-heading');
		const mappingDescription = document.getElementById('cmatic-provider-mapping-description');
		const fieldLimit = document.getElementById('cmatic-provider-field-limit');
		const saveRow = document.getElementById('cmatic-provider-save-row');
		const saveStatus = document.getElementById('cmatic-provider-save-status');
		const saveButton = document.getElementById('cmatic-provider-save');
		const fieldState = document.getElementById('cmatic-provider-field-state');
		const consentDescription = document.getElementById('cmatic-provider-consent-description');
		const consentControls = document.getElementById('cmatic-provider-consent-controls');
		const consentGate = document.getElementById('cmatic-provider-consent-gate');
		const consentFieldRow = document.getElementById('cmatic-provider-consent-field-row');
		const consentField = document.getElementById('cmatic-provider-consent-field');
		const brevoOptin = document.getElementById('cmatic-provider-brevo-optin');
		const subscriptionMode = document.getElementById('cmatic-provider-subscription-mode');
		const brevoDoi = document.getElementById('cmatic-provider-brevo-doi');
		const doiTemplate = document.getElementById('cmatic-provider-doi-template');
		const doiRedirect = document.getElementById('cmatic-provider-doi-redirect');
		const doiToken = document.getElementById('cmatic-provider-doi-token');
		const doiVerify = document.getElementById('cmatic-provider-doi-verify');
		const doiStatus = document.getElementById('cmatic-provider-doi-status');
		const managedOptin = document.getElementById('cmatic-provider-managed-optin');
		const managedOptinTitle = document.getElementById('cmatic-provider-managed-optin-title');
		const managedOptinCopy = document.getElementById('cmatic-provider-managed-optin-copy');
		const consentDocs = document.getElementById('cmatic-provider-consent-docs');

		function i18n(key, fallback) {
			return String((chimpmaticLiteEsp.i18n || {})[key] || fallback || '');
		}

		function format(template, ...values) {
			let next = 0;
			return String(template || '').replace(/%(?:(\d+)\$)?[sd]/g, function(match, position) {
				const index = position ? Number(position) - 1 : next++;
				return String(values[index] === undefined ? '' : values[index]);
			});
		}

		function definition(slug) {
			return chimpmaticLiteEsp.manifest[slug] || chimpmaticLiteEsp.manifest.brevo;
		}

		function providerState(slug) {
			if (!state.providers[slug]) {
				state.providers[slug] = {
					connected: false,
					credential_present: false,
					configured: false,
					lists: [],
					selected_list: '',
					fields: [],
					total_fields: 0,
					mappings: {}
					,advanced_consent: false
					,consent_gate: 'none'
					,consent_field: ''
					,subscription_mode: slug === 'brevo' ? 'single' : 'provider_managed'
					,doi_template_id: 0
					,doi_redirect_url: ''
				};
			}
			const current = state.providers[slug];
			if (typeof current.dirty === 'undefined') current.dirty = false;
			if (typeof current.busy === 'undefined') current.busy = false;
			if (typeof current.refreshing === 'undefined') current.refreshing = false;
			if (typeof current.replacing === 'undefined') current.replacing = false;
			if (typeof current.lists_loading === 'undefined') current.lists_loading = false;
			if (typeof current.fields_loading === 'undefined') current.fields_loading = false;
			return current;
		}

		function showMessage(value, tone) {
			message.textContent = String(value || '');
			message.classList.toggle('is-error', tone === 'error');
			message.classList.toggle('is-success', tone === 'success');
		}

		function setBusy(button, busy, label) {
			button.disabled = busy;
			button.classList.toggle('is-busy', busy);
			button.setAttribute('aria-busy', busy ? 'true' : 'false');
			if (label) button.textContent = label;
		}

		function setHeaderStatus(slug, current) {
			const dot = headerContext.querySelector('.cmatic-header__status-dot');
			const text = headerContext.querySelector('.cmatic-header__status-text');
			if (!dot || !text) return;

			const label = definition(slug).label;
			dot.classList.remove(
				'cmatic-header__status-dot--connected',
				'cmatic-header__status-dot--neutral',
				'cmatic-header__status-dot--disconnected'
			);
			if (current.connected) {
				dot.classList.add('cmatic-header__status-dot--connected');
				text.textContent = format(i18n('connected', '%s connected'), label);
			} else if (current.credential_present) {
				dot.classList.add('cmatic-header__status-dot--disconnected');
				text.textContent = format(i18n('inactive', 'Reconnect %s'), label);
			} else {
				dot.classList.add('cmatic-header__status-dot--neutral');
				text.textContent = format(i18n('notConnected', '%s not connected'), label);
			}
		}

		function authField() {
			return authFields.querySelector('[data-auth-field="api_key"]');
		}

		function credentialName(meta) {
			const field = (meta.auth_fields || [])[0] || {};
			return String(field.label || i18n('credential', 'credential')).replace(String(meta.label) + ' ', '');
		}

		function renderAuthFields(meta) {
			const fragment = document.createDocumentFragment();
			(meta.auth_fields || []).forEach(function(field) {
				const wrapper = document.createElement('div');
				const label = document.createElement('label');
				const input = document.createElement('input');
				const description = document.createElement('small');
				const readiness = document.createElement('small');
				const id = 'cmatic-provider-auth-' + field.id;

				wrapper.className = 'cmatic-provider-auth-field';
				label.htmlFor = id;
				label.textContent = field.label;
				input.type = field.type || 'password';
				input.id = id;
				input.value = '';
				input.placeholder = field.placeholder || '';
				input.autocomplete = field.autocomplete || 'new-password';
				input.spellcheck = false;
				input.dataset.authField = field.id;
				description.className = 'description';
				description.textContent = field.description || '';
				readiness.className = 'cmatic-provider-credential-readiness';
				readiness.dataset.credentialReadiness = '';
				readiness.setAttribute('role', 'status');
				readiness.setAttribute('aria-live', 'polite');

				wrapper.append(label, input, description, readiness);
				fragment.append(wrapper);
			});
			authFields.replaceChildren(fragment);
		}

		function sortedLists(current) {
			return (current.lists || []).slice().sort(function(first, second) {
				return String(first.name || '').localeCompare(String(second.name || ''), undefined, { sensitivity: 'base' });
			});
		}

		function renderDestinations(meta, current) {
			const fragment = document.createDocumentFragment();
			const empty = document.createElement('option');
			empty.value = '';
			empty.textContent = format(
				i18n('selectDestination', 'Select a %s...'),
				String(meta.destination_singular || '').toLowerCase()
			);
			fragment.append(empty);

			sortedLists(current).forEach(function(list) {
				const option = document.createElement('option');
				option.value = String(list.id);
				option.textContent = String(list.name);
				fragment.append(option);
			});
			destinationSelect.replaceChildren(fragment);
			destinationSelect.value = String(current.selected_list || '');
		}

		function firstEmailOption(select) {
			return Array.from(select.options).find(function(option) {
				return option.dataset.basetype === 'email';
			});
		}

		function compatibleType(providerType, formType) {
			const types = {
				email: ['email'],
				phone: ['tel'],
				text: ['text', 'textarea'],
				boolean: []
			};
			return (types[String(providerType || '').toLowerCase()] || []).includes(formType);
		}

		function mappingWarning(field, select) {
			const previous = select.parentElement.querySelector('.cmatic-provider-mapping-warning');
			if (previous) previous.remove();
			if (!select.value) return;
			const option = select.options[select.selectedIndex];
			const formType = option ? String(option.dataset.basetype || '') : '';
			if (!formType || compatibleType(field.type, formType)) return;
			const warning = document.createElement('p');
			warning.className = 'cmatic-provider-mapping-warning';
			warning.id = select.id + '-warning';
			warning.setAttribute('role', 'status');
			warning.textContent = format(
				i18n('mappingTypeWarning', '%1$s is a %2$s field, but %3$s expects %4$s. Review this mapping.'),
				select.value,
				formType,
				field.name || field.tag,
				field.type || 'text'
			);
			select.setAttribute('aria-describedby', warning.id);
			select.insertAdjacentElement('afterend', warning);
		}

		function renderFieldState(current) {
			const fragment = document.createDocumentFragment();
			const total = document.createElement('input');
			total.type = 'hidden';
			total.name = 'wpcf7-cmatic-provider[total_merge_fields]';
			total.value = String(current.total_fields || 0);
			fragment.append(total);

			(current.fields || []).slice(0, Number(chimpmaticLiteEsp.fieldLimit)).forEach(function(field, index) {
				['tag', 'name', 'type', 'display_order'].forEach(function(property) {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = 'wpcf7-cmatic-provider[merge_fields][' + index + '][' + property + ']';
					input.value = String(field[property] === undefined ? '' : field[property]);
					fragment.append(input);
				});
			});
			fieldState.replaceChildren(fragment);
		}

		function renderMappings(current) {
			const rows = Array.from(root.querySelectorAll('[data-mapping-slot]'));
			rows.forEach(function(select, offset) {
				const row = select.closest('.cmatic-provider-mapping-row');
				const field = (current.fields || [])[offset];
				const slot = select.dataset.mappingSlot;

				if (!row || !field) {
					if (row) row.hidden = true;
					select.disabled = true;
					select.value = '';
					return;
				}

				const isEmail = String(field.tag || '').toUpperCase() === 'EMAIL';
				const currentValue = String((current.mappings || {})[slot] || '');
				const placeholder = select.options[0];
				const options = Array.from(select.options).slice(1).sort(function(first, second) {
					return Number(compatibleType(field.type, second.dataset.basetype))
						- Number(compatibleType(field.type, first.dataset.basetype));
				});
				select.replaceChildren(placeholder, ...options);
				row.hidden = false;
				row.dataset.remoteTag = String(field.tag || '');
				row.dataset.required = isEmail ? '1' : '0';
				row.querySelector('[data-field-label]').textContent = String(field.name || field.tag || slot);
				row.querySelector('[data-field-type]').textContent = String(field.tag || '') + ' · ' + String(field.type || 'text');
				row.querySelector('[data-required-label]').hidden = !isEmail;
				select.disabled = false;
				select.setAttribute('aria-required', isEmail ? 'true' : 'false');

				Array.from(select.options).forEach(function(option) {
					if (!option.value) {
						option.disabled = false;
						return;
					}
					option.disabled = isEmail && option.dataset.basetype !== 'email';
				});

				let mapping = currentValue;
				if (!mapping && isEmail) {
					const match = firstEmailOption(select);
					mapping = match ? match.value : '';
					current.mappings[slot] = mapping;
				}
				select.value = mapping;
				mappingWarning(field, select);
			});

			const total = Number(current.total_fields || 0);
			fieldLimit.hidden = total <= Number(chimpmaticLiteEsp.fieldLimit);
			renderFieldState(current);
		}

		function requiredEmailMapped() {
			const row = root.querySelector('[data-required="1"]:not([hidden])');
			const select = row ? row.querySelector('[data-mapping-slot]') : null;
			return Boolean(select && select.value);
		}

		function mappedCount(current) {
			return Object.values(current.mappings || {}).filter(Boolean).length;
		}

		function selectedList(current) {
			return (current.lists || []).find(function(list) {
				return String(list.id) === String(current.selected_list || '');
			}) || null;
		}

		function consentReady(slug, current) {
			if (!current.advanced_consent) return true;
			if (current.consent_gate === 'required' && !current.consent_field) return false;
			if (slug !== 'brevo' || current.subscription_mode !== 'double') return true;
			return Boolean(current.doi_verification_token);
		}

		function renderConsent(meta, current) {
			const slug = state.activeProvider;
			const consentMeta = meta.consent || {};
			const advanced = Boolean(current.advanced_consent);
			consentDescription.textContent = advanced
				? String(consentMeta.description || '')
				: i18n('consentRequiresPro', 'Advanced consent controls are available with an active Chimpmatic Pro license.');
			Array.from(consentControls.querySelectorAll('input, select, button')).forEach(function(control) {
				control.disabled = !advanced;
			});
			consentGate.value = current.consent_gate === 'required' ? 'required' : 'none';
			consentFieldRow.hidden = consentGate.value !== 'required';
			consentField.value = String(current.consent_field || '');
			brevoOptin.hidden = slug !== 'brevo';
			managedOptin.hidden = slug === 'brevo';
			if (slug === 'brevo') {
				subscriptionMode.value = current.subscription_mode === 'double' ? 'double' : 'single';
				brevoDoi.hidden = subscriptionMode.value !== 'double';
				doiTemplate.value = current.doi_template_id ? String(current.doi_template_id) : '';
				doiRedirect.value = String(current.doi_redirect_url || '');
				doiToken.value = String(current.doi_verification_token || '');
				doiStatus.textContent = current.doi_verification_token
					? i18n('doiVerified', 'DOI settings verified.')
					: '';
				doiVerify.disabled = !advanced || !doiTemplate.value || !doiRedirect.value || current.doi_verifying;
			} else if (slug === 'mailerlite') {
				managedOptinTitle.textContent = i18n('managedByMailerLite', 'Managed by MailerLite');
				managedOptinCopy.textContent = i18n('mailerLiteOptin', 'MailerLite uses the Double opt-in for API and integrations setting in your account.');
			} else {
				const list = selectedList(current);
				const process = String(list && list.opt_in_process || '');
				managedOptinTitle.textContent = process === 'double_opt_in'
					? i18n('doubleOptin', 'Double opt-in')
					: process === 'single_opt_in'
						? i18n('singleOptin', 'Single opt-in')
						: i18n('optinUnavailable', 'Opt-in setting unavailable');
				managedOptinCopy.textContent = i18n('klaviyoOptin', 'The selected Klaviyo list controls whether confirmation is required.');
			}
			consentDocs.href = String(consentMeta.docs_url || '#');
		}

		function renderProgress(meta, current) {
			const complete = state.activeProvider === state.savedProvider && current.configured && !current.dirty;
			completion.hidden = !complete;
			progress.hidden = complete;
			if (complete) {
				completionOutcome.textContent = format(
					i18n('setupOutcome', 'New submissions from this form will be added to %1$s in %2$s.'),
					current.selected_list_name || current.selected_list,
					meta.label
				);
				completionMeta.textContent = format(
					i18n('mappedCount', '%1$d fields mapped · Saved'),
					mappedCount(current)
				);
				return;
			}

			progressDestination.textContent = format(
				i18n('chooseDestination', 'Choose %s'),
				String(meta.destination_singular || '').toLowerCase()
			);
			const items = Array.from(progress.children);
			const destinationDone = Boolean(current.selected_list && current.fields && current.fields.length);
			const statuses = [current.connected, destinationDone, false];
			let currentIndex = current.connected ? (destinationDone ? 2 : 1) : 0;
			items.forEach(function(item, index) {
				item.classList.toggle('is-done', statuses[index]);
				item.classList.toggle('is-current', index === currentIndex);
				if (index === currentIndex) item.setAttribute('aria-current', 'step');
				else item.removeAttribute('aria-current');
				item.querySelector('span').textContent = statuses[index] ? '✓' : String(index + 1);
			});
		}

		function renderConnection(meta, current) {
			const fresh = !current.connected && !current.credential_present;
			const recovering = !current.connected && current.credential_present && !current.replacing;
			const editing = fresh || current.replacing;
			credentialsIntro.hidden = current.connected && !current.replacing || recovering;
			authRow.hidden = !editing || current.busy;
			connectedSummary.hidden = !current.connected || current.replacing;
			recoverySummary.hidden = !recovering;

			if (editing) {
				renderAuthFields(meta);
				credentialsHeading.textContent = current.replacing
					? format(i18n('updateProvider', 'Update %s connection'), meta.label)
					: format(i18n('connectProvider', 'Connect %s'), meta.label);
				credentialsDescription.textContent = format(
					i18n('connectDescription', 'Connect %1$s to choose a %2$s and map its fields.'),
					meta.label,
					String(meta.destination_singular || '').toLowerCase()
				);
				connectButton.textContent = current.replacing
					? i18n('updateConnection', 'Update connection')
					: format(i18n('connectProvider', 'Connect %s'), meta.label);
				connectButton.disabled = true;
				cancelCredentialButton.hidden = !current.replacing;
			}

			if (current.busy) {
				credentialsIntro.hidden = false;
				credentialsHeading.textContent = format(i18n('checkingConnection', 'Checking your %s connection...'), meta.label);
				credentialsDescription.textContent = '';
			}

			connectedTitle.textContent = format(i18n('connected', '%s connected'), meta.label);
			refreshButton.textContent = current.lists_loading || current.refreshing
				? format(i18n('loadingDestinations', 'Loading %s...'), String(meta.destination_plural || '').toLowerCase())
				: format(i18n('refreshDestinations', 'Refresh %s'), String(meta.destination_plural || '').toLowerCase());
			refreshButton.disabled = current.lists_loading || current.refreshing;
			replaceCredentialButton.textContent = format(i18n('replaceCredential', 'Replace %s'), credentialName(meta));
			recoveryTitle.textContent = format(i18n('reconnectProvider', 'Reconnect %s'), meta.label);
			recoveryReplaceButton.textContent = format(i18n('replaceCredential', 'Replace %s'), credentialName(meta));
		}

		function renderDestination(meta, current) {
			destinationHeading.textContent = format(
				i18n('chooseDestination', 'Choose a %s'),
				String(meta.destination_singular || '').toLowerCase()
			);
			destinationLabel.textContent = meta.label + ' ' + String(meta.destination_singular || '').toLowerCase();
			if (!current.connected || current.lists_loading) {
				destinationLocked.hidden = false;
				destinationRow.hidden = true;
				destinationDescription.textContent = '';
				destinationLocked.querySelector('p').textContent = current.lists_loading
					? format(i18n('loadingDestinationsFrom', 'Loading %1$s from %2$s...'), String(meta.destination_plural || '').toLowerCase(), meta.label)
					: format(i18n('connectToContinue', 'Connect %s to continue.'), meta.label);
				return;
			}

			destinationLocked.hidden = true;
			destinationRow.hidden = false;
			renderDestinations(meta, current);
			const listCount = (current.lists || []).length;
			if (1 === listCount && current.selected_list) {
				destinationDescription.textContent = format(
					i18n('onlyDestination', '%1$s was selected because it is your only %2$s %3$s.'),
					current.selected_list_name || current.selected_list,
					meta.label,
					String(meta.destination_singular || '').toLowerCase()
				);
			} else {
				destinationDescription.textContent = 1 === listCount
					? format(
						i18n('oneDestinationFound', '1 %s found. Choose where new submissions from this form should go.'),
						String(meta.destination_singular || '').toLowerCase()
					)
					: format(
						i18n('destinationsFound', '%1$d %2$s found. Choose where new submissions from this form should go.'),
						listCount,
						String(meta.destination_plural || '').toLowerCase()
					);
			}
		}

		function renderMappingStage(meta, current) {
			const visible = Boolean(current.connected && current.selected_list && current.fields && current.fields.length);
			mappingsHeading.textContent = format(i18n('mapProviderFields', 'Map %s fields'), meta.label);
			mappingDescription.textContent = format(
				i18n('mappedFields', 'Match each %s field to a Contact Form 7 field. Subscriber Email is required.'),
				meta.label
			);
			mappingsGrid.hidden = !visible;
			mappingsLocked.hidden = visible;
			saveRow.hidden = !visible;
			if (!visible) {
				mappingsLocked.querySelector('p').textContent = current.lists_loading
					? format(i18n('waitForDestinations', 'Wait while %s load.'), String(meta.destination_plural || '').toLowerCase())
					: format(i18n('chooseToLoadFields', 'Choose a %s to load its fields.'), String(meta.destination_singular || '').toLowerCase());
				return;
			}

			renderMappings(current);
			const complete = state.activeProvider === state.savedProvider && current.configured && !current.dirty;
			if (current.dirty) {
				saveStatus.textContent = i18n('unsavedChanges', 'Unsaved changes');
				saveButton.textContent = i18n('saveChanges', 'Save changes');
				saveButton.disabled = !requiredEmailMapped() || !consentReady(state.activeProvider, current);
			} else if (complete) {
				saveStatus.textContent = i18n('savedJustNow', 'Saved');
				saveButton.textContent = i18n('saveChanges', 'Save changes');
				saveButton.disabled = true;
			} else {
				saveStatus.textContent = i18n('saveToActivate', 'Save to activate this configuration.');
				saveButton.textContent = i18n('saveConfiguration', 'Save configuration');
				saveButton.disabled = !requiredEmailMapped() || !consentReady(state.activeProvider, current);
			}
		}

		function render() {
			const slug = state.activeProvider;
			selector.value = slug;
			headerContext.hidden = !slug;
			onboarding.hidden = Boolean(slug);
			showMessage('', '');

			if (!slug) {
				root.hidden = true;
				mailchimpView.hidden = true;
				return;
			}

			const current = providerState(slug);
			setHeaderStatus(slug, current);
			if ('mailchimp' === slug) {
				root.hidden = true;
				mailchimpView.hidden = false;
				return;
			}

			const meta = definition(slug);
			root.hidden = false;
			root.dataset.provider = slug;
			mailchimpView.hidden = true;
			renderProgress(meta, current);
			renderConnection(meta, current);
			renderDestination(meta, current);
			renderMappingStage(meta, current);
			renderConsent(meta, current);
		}

		function abortRequests() {
			controllers.forEach(function(controller) {
				controller.abort();
			});
			controllers.clear();
		}

		async function request(channel, action, payload) {
			if (controllers.has(channel)) controllers.get(channel).abort();
			const controller = new AbortController();
			controllers.set(channel, controller);

			try {
				const response = await fetch(chimpmaticLiteEsp.restUrl + 'providers/' + action, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': chimpmaticLiteEsp.nonce
					},
					body: JSON.stringify(payload),
					signal: controller.signal
				});
				const data = await response.json().catch(function() {
					return {};
				});
				if (!response.ok) throw new Error(data.message || i18n('requestFailed', 'Provider request failed.'));
				return data;
			} finally {
				if (controllers.get(channel) === controller) controllers.delete(channel);
			}
		}

		function discardDraft(slug) {
			const current = providerState(slug);
			const baseline = initialProviders[slug] || {};
			current.selected_list = String(baseline.selected_list || '');
			current.selected_list_name = String(baseline.selected_list_name || '');
			current.fields = Array.isArray(baseline.fields) ? JSON.parse(JSON.stringify(baseline.fields)) : [];
			current.total_fields = Number(baseline.total_fields || 0);
			current.mappings = baseline.mappings ? JSON.parse(JSON.stringify(baseline.mappings)) : {};
			current.configured = Boolean(baseline.configured);
			current.dirty = false;
		}

		function switchProvider(nextSlug) {
			const previousSlug = state.activeProvider;
			if (previousSlug && 'mailchimp' !== previousSlug && providerState(previousSlug).dirty) {
				if (!window.confirm(i18n('discardChanges', 'Discard unsaved changes and switch providers?'))) {
					selector.value = previousSlug;
					return;
				}
				discardDraft(previousSlug);
			}
			abortRequests();
			generation++;
			state.activeProvider = nextSlug;
			render();
			autoLoadOnlyDestination();
		}

		function autoLoadOnlyDestination() {
			const slug = state.activeProvider;
			if (!slug || 'mailchimp' === slug) return;
			const current = providerState(slug);
			const lists = sortedLists(current);
			if (current.connected && !current.selected_list && !current.fields_loading && 1 === lists.length) {
				loadFields(String(lists[0].id), true);
			}
		}

		async function connectProvider(options) {
			const settings = options || {};
			const slug = state.activeProvider;
			const current = providerState(slug);
			const input = authField();
			const apiKey = settings.useSaved ? '' : input ? input.value.trim() : '';
			if (!settings.useSaved && !apiKey) return;

			abortRequests();
			const token = ++generation;
			current.busy = true;
			current.lists_loading = true;
			render();
			try {
				const data = await request('connect', 'connect', {
					form_id: Number(root.dataset.formId),
					provider: slug,
					api_key: apiKey
				});
				if (token !== generation || data.provider !== state.activeProvider) return;

				current.connected = true;
				current.credential_present = true;
				current.lists = Array.isArray(data.lists) ? data.lists : [];
				if (data.key_changed) {
					current.selected_list = '';
					current.selected_list_name = '';
					current.fields = [];
					current.total_fields = 0;
					current.mappings = {};
					current.configured = false;
					current.dirty = true;
				}
				current.replacing = false;
				current.busy = false;
				current.lists_loading = false;
				const lists = sortedLists(current);
				if (1 === lists.length && !current.selected_list) {
					await loadFields(String(lists[0].id), true);
					return;
				}
				render();
				showMessage(
					format(i18n('connectedDestinationsReady', '%1$s connected. %2$d destinations are ready.'), definition(slug).label, lists.length),
					'success'
				);
			} catch (error) {
				current.busy = false;
				current.lists_loading = false;
				if (error.name !== 'AbortError') {
					render();
					showMessage(error.message || i18n('requestFailed', 'Provider request failed.'), 'error');
				}
			}
		}

		async function loadFields(listId, automatic) {
			const slug = state.activeProvider;
			const current = providerState(slug);
			const meta = definition(slug);
			const previous = {
				selected_list: current.selected_list,
				selected_list_name: current.selected_list_name,
				fields: current.fields,
				total_fields: current.total_fields,
				mappings: current.mappings
			};
			current.selected_list = listId;
			const selected = (current.lists || []).find(function(list) {
				return String(list.id) === String(listId);
			});
			current.selected_list_name = selected ? String(selected.name) : '';
			current.fields_loading = Boolean(listId);
			current.fields = [];
			render();
			if (!listId) {
				current.total_fields = 0;
				current.mappings = {};
				current.dirty = true;
				return;
			}

			abortRequests();
			const token = ++generation;
			showMessage(format(i18n('loadingProviderFields', 'Loading %s fields...'), meta.label), '');
			try {
				const data = await request('fields', 'fields', {
					form_id: Number(root.dataset.formId),
					provider: slug,
					list_id: listId
				});
				if (token !== generation || data.provider !== state.activeProvider || String(data.list_id || '') !== String(listId)) return;
				current.fields = Array.isArray(data.merge_fields) ? data.merge_fields : [];
				current.total_fields = Number(data.total_merge_fields || 0);
				current.mappings = data.mappings || {};
				current.fields_loading = false;
				current.configured = false;
				current.dirty = true;
				render();
				showMessage(
					automatic
						? format(i18n('onlyDestinationSelected', '%1$s connected. %2$s was selected automatically.'), meta.label, current.selected_list_name)
						: format(i18n('providerFieldsReady', '%s fields are ready.'), meta.label),
					'success'
				);
			} catch (error) {
				current.selected_list = previous.selected_list;
				current.selected_list_name = previous.selected_list_name;
				current.fields = previous.fields;
				current.total_fields = previous.total_fields;
				current.mappings = previous.mappings;
				current.fields_loading = false;
				if (error.name !== 'AbortError') {
					render();
					showMessage(error.message || i18n('requestFailed', 'Provider request failed.'), 'error');
				}
			}
		}

		async function disconnectProvider() {
			const slug = state.activeProvider;
			const meta = definition(slug);
			if (!window.confirm(format(i18n('disconnectConfirm', 'Disconnect %s? Your field mappings will be kept.'), meta.label))) return;
			abortRequests();
			const token = ++generation;
			try {
				const data = await request('disconnect', 'disconnect', {
					form_id: Number(root.dataset.formId),
					provider: slug
				});
				if (token !== generation || data.provider !== state.activeProvider) return;
				const current = providerState(slug);
				current.connected = false;
				current.credential_present = false;
				current.configured = false;
				current.replacing = false;
				current.dirty = state.savedProvider === slug;
				render();
			} catch (error) {
				if (error.name !== 'AbortError') showMessage(error.message || i18n('requestFailed', 'Provider request failed.'), 'error');
			}
		}

		onboarding.addEventListener('click', function(event) {
			const choice = event.target.closest('[data-provider-choice]');
			if (!choice) return;
			switchProvider(String(choice.dataset.providerChoice || ''));
			const heading = document.getElementById('cmatic-provider-credentials-heading');
			if (heading && !root.hidden) {
				heading.setAttribute('tabindex', '-1');
				heading.focus({ preventScroll: true });
			}
		});

		selector.addEventListener('change', function() {
			switchProvider(selector.value);
		});

		authFields.addEventListener('input', function(event) {
			const input = event.target.closest('[data-auth-field="api_key"]');
			if (!input) return;
			const ready = Boolean(input.value.trim());
			const readiness = authFields.querySelector('[data-credential-readiness]');
			connectButton.disabled = !ready;
			if (readiness) readiness.textContent = ready ? i18n('readyToVerify', 'Ready to verify.') : '';
			showMessage('', '');
		});

		authFields.addEventListener('keydown', function(event) {
			if (event.key !== 'Enter' || connectButton.disabled) return;
			event.preventDefault();
			connectProvider({ useSaved: false });
		});

		connectButton.addEventListener('click', function() {
			connectProvider({ useSaved: false });
		});
		cancelCredentialButton.addEventListener('click', function() {
			providerState(state.activeProvider).replacing = false;
			render();
		});
		refreshButton.addEventListener('click', function() {
			const current = providerState(state.activeProvider);
			current.refreshing = true;
			render();
			connectProvider({ useSaved: true }).finally(function() {
				current.refreshing = false;
				if (state.activeProvider) render();
			});
		});
		replaceCredentialButton.addEventListener('click', function() {
			providerState(state.activeProvider).replacing = true;
			render();
			authField()?.focus();
		});
		recoveryReplaceButton.addEventListener('click', function() {
			providerState(state.activeProvider).replacing = true;
			render();
			authField()?.focus();
		});
		retryButton.addEventListener('click', function() {
			connectProvider({ useSaved: true });
		});
		disconnectButton.addEventListener('click', disconnectProvider);
		recoveryDisconnectButton.addEventListener('click', disconnectProvider);

		destinationSelect.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.doi_verification_token = '';
			loadFields(destinationSelect.value, false);
		});

		consentGate.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.consent_gate = consentGate.value === 'required' ? 'required' : 'none';
			current.consent_field = current.consent_gate === 'required' ? String(consentField.value || '') : '';
			current.dirty = true;
			render();
		});
		consentField.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.consent_field = String(consentField.value || '');
			current.dirty = true;
			render();
		});
		subscriptionMode.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.subscription_mode = subscriptionMode.value === 'double' ? 'double' : 'single';
			current.doi_verification_token = '';
			current.dirty = true;
			render();
		});
		[doiTemplate, doiRedirect].forEach(function(input) {
			input.addEventListener('input', function() {
				const current = providerState(state.activeProvider);
				current.doi_template_id = Number(doiTemplate.value || 0);
				current.doi_redirect_url = String(doiRedirect.value || '');
				current.doi_verification_token = '';
				current.dirty = true;
				render();
			});
		});
		doiVerify.addEventListener('click', async function() {
			const slug = state.activeProvider;
			const current = providerState(slug);
			current.doi_verifying = true;
			render();
			try {
				const data = await request('consent', 'consent/validate', {
					form_id: Number(root.dataset.formId),
					provider: slug,
					list_id: String(current.selected_list || ''),
					subscription_mode: 'double',
					doi_template_id: Number(current.doi_template_id || 0),
					doi_redirect_url: String(current.doi_redirect_url || '')
				});
				if (slug !== state.activeProvider) return;
				current.doi_verification_token = String(data.verification_token || '');
				showMessage(i18n('doiVerified', 'DOI settings verified.'), 'success');
			} catch (error) {
				if (error.name !== 'AbortError') showMessage(error.message || i18n('doiFailed', 'DOI settings could not be verified.'), 'error');
			} finally {
				current.doi_verifying = false;
				if (slug === state.activeProvider) render();
			}
		});

		root.addEventListener('change', function(event) {
			const select = event.target.closest('[data-mapping-slot]');
			if (!select || !root.contains(select)) return;
			const current = providerState(state.activeProvider);
			current.mappings = current.mappings || {};
			current.mappings[select.dataset.mappingSlot] = select.value;
			current.configured = false;
			current.dirty = true;
			render();
			document.getElementById(select.id)?.focus();
		});

		if (parentForm) {
			parentForm.addEventListener('submit', function(event) {
				if (!state.activeProvider || 'mailchimp' === state.activeProvider) return;
				const current = providerState(state.activeProvider);
				if (!current.connected || !current.selected_list || !current.fields.length) {
					event.preventDefault();
					showMessage(i18n('missingDestination', 'Connect a provider and choose a destination before saving.'), 'error');
					return;
				}
				if (!requiredEmailMapped()) {
					event.preventDefault();
					showMessage(i18n('missingEmailMapping', 'Select a Contact Form 7 field for Subscriber Email.'), 'error');
					const emailRow = root.querySelector('[data-required="1"]:not([hidden])');
					const emailSelect = emailRow ? emailRow.querySelector('[data-mapping-slot]') : null;
					if (emailSelect) emailSelect.focus();
					return;
				}
				if (!consentReady(state.activeProvider, current)) {
					event.preventDefault();
					showMessage(i18n('consentIncomplete', 'Complete the consent and opt-in settings before saving.'), 'error');
					return;
				}
				setBusy(saveButton, true, i18n('saving', 'Saving...'));
			});
		}

		render();
		autoLoadOnlyDestination();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
