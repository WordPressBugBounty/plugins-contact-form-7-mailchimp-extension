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
		const progressMappings = document.getElementById('cmatic-provider-progress-mappings');
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
		const credentialStorage = document.getElementById('cmatic-provider-credential-storage');
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
		const fieldLimitCopy = document.getElementById('cmatic-provider-field-limit-copy');
		const fieldLimitLinkCopy = document.getElementById('cmatic-provider-field-limit-link-copy');
		const saveRow = document.getElementById('cmatic-provider-save-row');
		const saveStatus = document.getElementById('cmatic-provider-save-status');
		const saveButton = document.getElementById('cmatic-provider-save');
		const fieldState = document.getElementById('cmatic-provider-field-state');
		const consentDescription = document.getElementById('cmatic-provider-consent-description');
		const consentControls = document.getElementById('cmatic-provider-consent-controls');
		const consentGate = document.getElementById('cmatic-provider-consent-gate');
		const consentGateTitle = document.getElementById('cmatic-provider-consent-gate-title');
		const consentGateExplanation = document.getElementById('cmatic-provider-consent-gate-explanation');
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
		const providerOptinTitle = document.getElementById('cmatic-provider-optin-title');
		const providerOptinExplanation = document.getElementById('cmatic-provider-optin-explanation');
		const mailerliteRouting = document.getElementById('cmatic-mailerlite-routing');
		const mailerliteGroups = document.getElementById('cmatic-provider-mailerlite-groups');
		const routingRuleList = document.getElementById('cmatic-routing-rule-list');
		const routingAddRule = document.getElementById('cmatic-routing-add-rule');
		const routingNotice = document.getElementById('cmatic-routing-notice');
		const mailerliteOptions = document.getElementById('cmatic-provider-mailerlite-options');
		const mailerliteStatus = document.getElementById('cmatic-provider-mailerlite-status');
		const mailerliteResubscribe = document.getElementById('cmatic-provider-mailerlite-resubscribe');
		const mailerliteResubscribeForce = document.getElementById('cmatic-provider-mailerlite-resubscribe-force');
		const mailerliteConsentMetadata = document.getElementById('cmatic-provider-mailerlite-consent-metadata-enabled');
		const mailerliteStatusNotice = document.getElementById('cmatic-provider-mailerlite-status-notice');
		const fieldCreator = document.getElementById('cmatic-provider-mailerlite-create-field');
		const fieldCreatorName = document.getElementById('cmatic-provider-mailerlite-field-name');
		const fieldCreatorType = document.getElementById('cmatic-provider-mailerlite-field-type');
		const fieldCreatorButton = document.getElementById('cmatic-provider-mailerlite-field-create');
		const fieldCreatorNotice = document.getElementById('cmatic-provider-mailerlite-field-notice');
		const lookup = document.getElementById('cmatic-provider-mailerlite-lookup');
		const lookupEmail = document.getElementById('cmatic-provider-mailerlite-lookup-email');
		const lookupButton = document.getElementById('cmatic-provider-mailerlite-lookup-submit');
		const lookupResults = document.getElementById('cmatic-provider-mailerlite-lookup-results');
		const testingNotice = document.getElementById('cmatic-provider-testing-notice');
		let routingSequence = 0;
		let routingValidationVisible = false;
		let fieldCreating = false;

		function i18n(key, fallback) {
			return String((chimpmaticLiteEsp.i18n || {})[key] || fallback || '');
		}

		function term(meta, key, fallback) {
			return String((meta || {})[key] || fallback || '');
		}

		function requestFailed() {
			const meta = definition(state.activeProvider);
			return format(i18n('requestFailed', '%s could not complete the request. Try again.'), meta.label);
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
					,base_groups: []
					,additional_groups: []
					,routing_rules: []
					,routing_supported: false
					,routing_entitled: false
					,status_mode: 'legacy_provider_managed'
					,status_supported: false
					,status_entitled: false
					,resubscribe_force: false
					,resubscribe_entitled: false
					,consent_metadata_enabled: false
					,consent_metadata_supported: false
					,consent_metadata_entitled: false
					,create_field_supported: false
					,create_field_entitled: false
					,lookup_supported: false
				};
			}
			const current = state.providers[slug];
			if (typeof current.dirty === 'undefined') current.dirty = false;
			if (typeof current.busy === 'undefined') current.busy = false;
			if (typeof current.refreshing === 'undefined') current.refreshing = false;
			if (typeof current.replacing === 'undefined') current.replacing = false;
			if (typeof current.lists_loading === 'undefined') current.lists_loading = false;
			if (typeof current.fields_loading === 'undefined') current.fields_loading = false;
			if (!Array.isArray(current.base_groups)) current.base_groups = current.selected_list ? [String(current.selected_list)] : [];
			if (!Array.isArray(current.additional_groups)) current.additional_groups = [];
			if (!Array.isArray(current.routing_rules)) current.routing_rules = [];
			return current;
		}

		function configurationSnapshot(source) {
			const current = source || {};
			const mappings = {};
			Object.keys(current.mappings || {}).sort().forEach(function(key) {
				mappings[key] = String(current.mappings[key] || '');
			});
			return {
				selected_list: String(current.selected_list || ''),
				mappings: mappings,
				consent_gate: current.consent_gate === 'required' ? 'required' : 'none',
				consent_field: String(current.consent_field || ''),
				subscription_mode: String(current.subscription_mode || ''),
				doi_template_id: Number(current.doi_template_id || 0),
				doi_redirect_url: String(current.doi_redirect_url || ''),
				doi_verification_token: String(current.doi_verification_token || ''),
				additional_groups: (current.additional_groups || []).map(String),
				routing_rules: (current.routing_rules || []).map(function(rule) {
					return {
						id: String(rule.id || ''),
						field: String(rule.field || ''),
						value: String(rule.value || ''),
						group_id: String(rule.group_id || '')
					};
				}),
				status_mode: String(current.status_mode || 'legacy_provider_managed'),
				resubscribe_force: Boolean(current.resubscribe_force),
				consent_metadata_enabled: Boolean(current.consent_metadata_enabled)
			};
		}

		function updateDirty(slug) {
			const current = providerState(slug);
			const baseline = initialProviders[slug] || {};
			current.dirty = JSON.stringify(configurationSnapshot(current)) !== JSON.stringify(configurationSnapshot(baseline));
			if (!current.dirty) current.configured = Boolean(baseline.configured);
			return current.dirty;
		}

		function createRoutingRuleId() {
			if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
			routingSequence++;
			const seed = (Date.now().toString(16) + routingSequence.toString(16) + Math.random().toString(16).slice(2)).padEnd(32, '0').slice(0, 32);
			return seed.slice(0, 8) + '-' + seed.slice(8, 12) + '-4' + seed.slice(13, 16) + '-8' + seed.slice(17, 20) + '-' + seed.slice(20, 32);
		}

		function routingTags() {
			return (chimpmaticLiteEspState.form_tags || []).filter(function(tag) {
				return Boolean(tag && tag.routing_eligible && Array.isArray(tag.choices) && tag.choices.length);
			});
		}

		function appendOption(select, value, label, selected) {
			const option = document.createElement('option');
			option.value = String(value || '');
			option.textContent = String(label || value || '');
			option.selected = Boolean(selected);
			select.append(option);
		}

		function routingValidation(current) {
			const errors = new Map();
			const seen = new Map();
			const validFields = new Map(routingTags().map(function(tag) {
				return [String(tag.name), new Set((tag.choices || []).map(function(choice) { return String(choice.value); }))];
			}));
			const validGroups = new Set(sortedLists(current).map(function(list) { return String(list.id); }));

			(current.routing_rules || []).forEach(function(rule) {
				const id = String(rule.id || '');
				const field = String(rule.field || '');
				const value = String(rule.value || '');
				const group = String(rule.group_id || '');
				if (!field || !value || !group) {
					errors.set(id, i18n('routingIncomplete', 'Choose a field, an answer, and a destination group.'));
					return;
				}
				if (!validFields.has(field) || !validFields.get(field).has(value) || !validGroups.has(group)) {
					errors.set(id, i18n('routingInvalid', 'This rule contains an unavailable field, answer, or group.'));
					return;
				}
				const key = JSON.stringify([field, value, group]);
				if (seen.has(key)) {
					errors.set(id, i18n('routingDuplicate', 'This rule duplicates another rule.'));
					errors.set(seen.get(key), i18n('routingDuplicate', 'This rule duplicates another rule.'));
					return;
				}
				seen.set(key, id);
			});

			return errors;
		}

		function renderRouting(current) {
			const active = state.activeProvider === 'mailerlite' && current.routing_supported;
			mailerliteRouting.hidden = !active;
			if (!active) return;
			const premiumSaved = (current.additional_groups || []).length > 0 || (current.routing_rules || []).length > 0;
			const locked = !current.routing_entitled;
			routingAddRule.disabled = locked || !routingTags().length;
			routingNotice.hidden = !locked;
			routingNotice.textContent = premiumSaved
				? i18n('routingSavedInactive', 'MailerLite group rules are saved but inactive. Subscribers are added only to the group marked “Use when Pro is inactive.” Renew Pro to restore the saved rules.')
				: i18n('routingRequiresPro', 'Additional MailerLite groups and answer-based rules require Chimpmatic Pro.');

			const validation = routingValidation(current);
			const fragment = document.createDocumentFragment();
			const routingRules = current.routing_rules || [];
			const tableHeader = document.createElement('div');
			tableHeader.className = 'cmatic-routing-table-header';
			tableHeader.setAttribute('aria-hidden', 'true');
			[
				'#',
				i18n('whenThisField', 'When this field'),
				i18n('isThisValue', 'is this value'),
				i18n('addSubscriberTo', 'add subscriber to'),
				''
			].forEach(function(label) {
				const heading = document.createElement('span');
				heading.textContent = label;
				tableHeader.append(heading);
			});
			if (routingRules.length) {
				fragment.append(tableHeader);
			}
			routingRules.forEach(function(rule, index) {
				const row = document.createElement('div');
				const ruleLabel = document.createElement('strong');
				const fieldCell = document.createElement('div');
				const fieldLabel = document.createElement('label');
				const field = document.createElement('select');
				const valueCell = document.createElement('div');
				const valueLabel = document.createElement('label');
				const value = document.createElement('select');
				const groupCell = document.createElement('div');
				const groupLabel = document.createElement('label');
				const group = document.createElement('select');
				const remove = document.createElement('button');
				const removeIcon = document.createElement('span');
				const removeText = document.createElement('span');
				const hiddenId = document.createElement('input');
				const error = document.createElement('p');
				const prefix = 'cmatic-routing-' + index;
				row.className = 'cmatic-routing-table-row';
				row.dataset.routingRule = String(rule.id || '');
				ruleLabel.className = 'cmatic-routing-rule-label';
				ruleLabel.textContent = String(index + 1).padStart(2, '0');
				fieldCell.className = 'cmatic-routing-table-cell';
				fieldCell.dataset.routingColumnLabel = i18n('whenThisField', 'When this field');
				field.id = prefix + '-field';
				field.name = 'wpcf7-cmatic-provider[routing_rules][' + index + '][field]';
				field.dataset.routingField = '';
				fieldLabel.htmlFor = field.id;
				fieldLabel.className = 'screen-reader-text';
				fieldLabel.textContent = i18n('whenThisField', 'When this field');
				appendOption(field, '', i18n('chooseField', 'Choose a field'), false);
				routingTags().forEach(function(tag) { appendOption(field, tag.name, '[' + tag.name + ']', String(tag.name) === String(rule.field)); });
				valueCell.className = 'cmatic-routing-table-cell';
				valueCell.dataset.routingColumnLabel = i18n('isThisValue', 'is this value');
				value.id = prefix + '-value';
				value.name = 'wpcf7-cmatic-provider[routing_rules][' + index + '][value]';
				value.dataset.routingValue = '';
				valueLabel.htmlFor = value.id;
				valueLabel.className = 'screen-reader-text';
				valueLabel.textContent = i18n('isThisValue', 'is this value');
				appendOption(value, '', i18n('chooseValue', 'Choose a value'), false);
				const tag = routingTags().find(function(item) { return String(item.name) === String(rule.field); });
				(tag ? tag.choices : []).forEach(function(choice) { appendOption(value, choice.value, choice.label, String(choice.value) === String(rule.value)); });
				groupCell.className = 'cmatic-routing-table-cell';
				groupCell.dataset.routingColumnLabel = i18n('addSubscriberTo', 'add subscriber to');
				group.id = prefix + '-group';
				group.name = 'wpcf7-cmatic-provider[routing_rules][' + index + '][group_id]';
				group.dataset.routingGroup = '';
				groupLabel.htmlFor = group.id;
				groupLabel.className = 'screen-reader-text';
				groupLabel.textContent = i18n('addSubscriberTo', 'add subscriber to');
				appendOption(group, '', i18n('chooseGroup', 'Choose a group'), false);
				sortedLists(current).forEach(function(list) { appendOption(group, list.id, list.name, String(list.id) === String(rule.group_id)); });
				[field, value, group].forEach(function(control) { control.disabled = locked; });
				hiddenId.type = 'hidden';
				hiddenId.name = 'wpcf7-cmatic-provider[routing_rules][' + index + '][id]';
				hiddenId.value = String(rule.id || '');
				remove.type = 'button';
				remove.className = 'button-link cmatic-routing-remove';
				remove.dataset.routingRemove = '';
				remove.disabled = locked;
				remove.title = i18n('removeRule', 'Remove rule');
				removeIcon.className = 'dashicons dashicons-no-alt';
				removeIcon.setAttribute('aria-hidden', 'true');
				removeText.className = 'screen-reader-text';
				removeText.textContent = format(i18n('removeRuleNumber', 'Remove rule %d'), index + 1);
				remove.append(removeIcon, removeText);
				fieldCell.append(fieldLabel, field);
				valueCell.append(valueLabel, value);
				groupCell.append(groupLabel, group);
				error.className = 'cmatic-routing-rule-error';
				error.id = prefix + '-error';
				error.setAttribute('role', 'alert');
				error.hidden = !routingValidationVisible || !validation.has(String(rule.id || ''));
				error.textContent = validation.get(String(rule.id || '')) || '';
				[field, value, group].forEach(function(control) {
					if (!error.hidden) {
						control.setAttribute('aria-invalid', 'true');
						control.setAttribute('aria-describedby', error.id);
					} else {
						control.removeAttribute('aria-invalid');
						control.removeAttribute('aria-describedby');
					}
				});
				row.append(hiddenId, ruleLabel, fieldCell, valueCell, groupCell, remove, error);
				fragment.append(row);
			});
			routingRuleList.replaceChildren(fragment);
		}

		function renderMailerLiteGroups(current) {
			const selected = new Set((current.base_groups || [current.selected_list]).map(String));
			const premiumSaved = selected.size > 1 || (current.routing_rules || []).length > 0;
			const locked = !current.routing_entitled;
			const fragment = document.createDocumentFragment();
			sortedLists(current).forEach(function(list, index) {
				const id = String(list.id || '');
				const row = document.createElement('div');
				const groupLabel = document.createElement('label');
				const group = document.createElement('input');
				const primaryLabel = document.createElement('label');
				const primary = document.createElement('input');
				row.className = 'cmatic-provider-mapping-row';
				row.dataset.mailerliteGroup = id;
				group.type = 'checkbox';
				group.id = 'cmatic-mailerlite-group-' + index;
				group.name = 'wpcf7-cmatic-provider[base_groups][]';
				group.value = id;
				group.checked = selected.has(id);
				group.disabled = locked;
				group.dataset.mailerliteGroupSelected = '';
				groupLabel.htmlFor = group.id;
				groupLabel.append(group, document.createTextNode(' ' + String(list.name || id)));
				primary.type = 'radio';
				primary.id = 'cmatic-mailerlite-primary-' + index;
				primary.name = 'wpcf7-cmatic-provider[primary_group]';
				primary.value = id;
				primary.checked = id === String(current.selected_list || '');
				primary.disabled = locked && premiumSaved;
				primary.dataset.mailerliteGroupPrimary = '';
				primary.setAttribute('aria-label', format(
					i18n('useGroupWithoutPro', 'Use %s when Chimpmatic Pro is inactive'),
					String(list.name || id)
				));
				primaryLabel.htmlFor = primary.id;
				primaryLabel.append(primary, document.createTextNode(' ' + i18n('useWhenProInactive', 'Use when Pro is inactive')));
				row.append(groupLabel, primaryLabel);
				fragment.append(row);
			});
			mailerliteGroups.replaceChildren(fragment);
		}

		function renderMailerLiteOptions(current) {
			const active = state.activeProvider === 'mailerlite';
			mailerliteOptions.hidden = !active;
			fieldCreator.hidden = !active || !current.create_field_supported || !current.connected;
			lookup.hidden = !active || !current.lookup_supported || !current.connected;
			if (!active) return;
			mailerliteStatus.value = String(current.status_mode || 'legacy_provider_managed');
			mailerliteStatus.disabled = !current.status_entitled;
			mailerliteResubscribe.hidden = mailerliteStatus.value !== 'active';
			mailerliteResubscribeForce.checked = Boolean(current.resubscribe_force);
			mailerliteResubscribeForce.disabled = !current.status_entitled || !current.resubscribe_entitled;
			mailerliteConsentMetadata.checked = Boolean(current.consent_metadata_enabled);
			mailerliteConsentMetadata.disabled = !current.consent_metadata_entitled;
			const degraded = (!current.status_entitled && current.status_mode !== 'legacy_provider_managed') || (!current.resubscribe_entitled && current.resubscribe_force) || (!current.consent_metadata_entitled && current.consent_metadata_enabled);
			mailerliteStatusNotice.hidden = !degraded;
			mailerliteStatusNotice.textContent = i18n('proOptionsInactive', 'Saved MailerLite Pro settings are inactive. Subscribers continue with the current Active behavior; renew Pro to restore the saved settings.');
			fieldCreatorName.disabled = !current.create_field_entitled;
			fieldCreatorType.querySelectorAll('input[type="radio"]').forEach(function(input) {
				input.disabled = !current.create_field_entitled;
			});
			fieldCreatorButton.disabled = !current.create_field_entitled || fieldCreating;
			fieldCreatorNotice.hidden = Boolean(current.create_field_entitled);
			fieldCreatorNotice.textContent = i18n('fieldCreationRequiresPro', 'Creating MailerLite subscriber fields requires Chimpmatic Pro.');
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
				boolean: ['acceptance'],
				'multiple-choice': ['select', 'radio', 'checkbox']
			};
			return (types[String(providerType || '').toLowerCase()] || []).includes(formType);
		}

		function mappingWarning(field, select) {
			const previous = select.parentElement.querySelector('.cmatic-provider-mapping-warning');
			if (previous) previous.remove();
			select.removeAttribute('aria-describedby');
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
					option.disabled = (isEmail && option.dataset.basetype !== 'email')
						|| (String(field.type || '').toLowerCase() === 'boolean' && option.dataset.basetype !== 'acceptance');
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
			consentGateTitle.textContent = format(i18n('sendToProvider', 'Send to %s'), meta.label);
			consentGateExplanation.textContent = format(
				i18n('consentGateExplanation', 'Choose whether every valid form submission is sent to %s or only submissions with affirmative consent.'),
				meta.label
			);
			providerOptinTitle.textContent = format(i18n('confirmationInProvider', 'Confirmation in %s'), meta.label);
			providerOptinExplanation.textContent = format(i18n('confirmationExplanation', 'Controls whether %s requires confirmation after the form is submitted.'), meta.label);
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
			consentDocs.textContent = format(i18n('openConfirmationSettings', 'Open %s confirmation settings'), meta.label);
		}

		function renderProgress(meta, current) {
			const complete = state.activeProvider === state.savedProvider && current.configured && !current.dirty;
			completion.hidden = !complete;
			progress.hidden = complete;
			if (complete) {
				const baseGroupNames = (current.base_groups || [current.selected_list]).map(function(groupId) {
					const group = (current.lists || []).find(function(list) { return String(list.id) === String(groupId); });
					return group ? String(group.name) : String(groupId);
				}).filter(Boolean);
				completionOutcome.textContent = format(
					i18n('setupOutcome', 'New %1$s from this form will be added to %2$s in %3$s.'),
					term(meta, 'person_plural', 'contacts').toLowerCase(),
					baseGroupNames.join(', ') || current.selected_list_name || current.selected_list,
					meta.label
				);
				completionMeta.textContent = state.activeProvider === 'mailerlite'
					? format(i18n('mailerLiteSummary', 'Always-used groups: %1$d · Answer-based rules: %2$d · Subscriber fields mapped: %3$d · Saved'), baseGroupNames.length, (current.routing_rules || []).length, mappedCount(current))
					: format(i18n('mappedCount', '%1$d fields mapped · Saved'), mappedCount(current));
				return;
			}

			progressDestination.textContent = format(
				i18n('chooseDestination', 'Choose %s'),
				String(meta.destination_singular || '').toLowerCase()
			);
			progressMappings.textContent = format(i18n('mapData', 'Map %s'), term(meta, 'data_plural', 'fields').toLowerCase());
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
					i18n('connectDescription', 'Connect %1$s to choose a %2$s and map its %3$s.'),
					meta.label,
					String(meta.destination_singular || '').toLowerCase(),
					term(meta, 'data_plural', 'fields').toLowerCase()
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
			credentialStorage.textContent = format(
				i18n('credentialStored', '%s stored securely'),
				String(((meta.auth_fields || [])[0] || {}).label || i18n('credential', 'Credential'))
			);
			refreshButton.textContent = current.lists_loading || current.refreshing
				? format(i18n('loadingDestinations', 'Loading %s...'), String(meta.destination_plural || '').toLowerCase())
				: format(i18n('refreshDestinations', 'Refresh %s'), String(meta.destination_plural || '').toLowerCase());
			refreshButton.disabled = current.lists_loading || current.refreshing;
			replaceCredentialButton.textContent = format(i18n('replaceCredential', 'Replace %s'), credentialName(meta));
			recoveryTitle.textContent = format(i18n('reconnectProvider', 'Reconnect %s'), meta.label);
			recoveryReplaceButton.textContent = format(i18n('replaceCredential', 'Replace %s'), credentialName(meta));
		}

		function renderDestination(meta, current) {
			destinationHeading.textContent = state.activeProvider === 'mailerlite'
				? i18n('groupsForEverySubscriber', 'Groups for every subscriber')
				: format(i18n('chooseDestination', 'Choose a %s'), String(meta.destination_singular || '').toLowerCase());
			destinationLabel.textContent = meta.label + ' ' + String(meta.destination_singular || '').toLowerCase();
			if (!current.connected || current.lists_loading) {
				destinationLocked.hidden = false;
				destinationRow.hidden = true;
				mailerliteGroups.hidden = true;
				destinationDescription.textContent = '';
				destinationLocked.querySelector('p').textContent = current.lists_loading
					? format(i18n('loadingDestinationsFrom', 'Loading %1$s from %2$s...'), String(meta.destination_plural || '').toLowerCase(), meta.label)
					: format(i18n('connectToContinue', 'Connect %s to continue.'), meta.label);
				return;
			}

			destinationLocked.hidden = true;
			renderDestinations(meta, current);
			const isMailerLite = state.activeProvider === 'mailerlite';
			destinationRow.hidden = isMailerLite;
			destinationSelect.disabled = isMailerLite;
			mailerliteGroups.hidden = !isMailerLite;
			if (isMailerLite) renderMailerLiteGroups(current);
			renderRouting(current);
			const listCount = (current.lists || []).length;
			if (isMailerLite) {
				destinationDescription.textContent = i18n('mailerLiteGroupsHelp', 'Every subscriber successfully sent to MailerLite is added to each selected group. Mark one selected group “Use when Pro is inactive.”');
				return;
			}
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
			mappingsHeading.textContent = format(
				i18n('mapProviderFields', 'Map %1$s %2$s'),
				meta.label,
				term(meta, 'data_plural', 'fields').toLowerCase()
			);
			mappingDescription.textContent = format(
				i18n('mappedFields', 'Match each %1$s %2$s to a Contact Form 7 field. Email address mapping is required.'),
				meta.label,
				term(meta, 'data_singular', 'field').toLowerCase()
			);
			const dataPlural = term(meta, 'data_plural', 'fields').toLowerCase();
			const dataSingular = term(meta, 'data_singular', 'field').toLowerCase();
			fieldLimitCopy.textContent = fieldLimit.dataset.proActive === '1'
				? format(i18n('proDataLimit', 'Up to %1$d %2$s are available with your active Chimpmatic Pro license.'), Number(fieldLimit.dataset.currentLimit || 0), dataPlural)
				: format(i18n('liteDataLimit', 'Your Lite setup includes %1$d %2$s. Email address is always included.'), Number(fieldLimit.dataset.liteLimit || 0), dataPlural);
			if (fieldLimitLinkCopy) {
				fieldLimitLinkCopy.textContent = format(i18n('unlockData', 'Unlock every available %s and advanced features with Chimpmatic Pro'), dataSingular);
			}
			testingNotice.textContent = format(
				i18n('testingWarning', 'Real submission: may create or update a %1$s in %2$s and trigger confirmation emails or automations.'),
				term(meta, 'person_singular', 'contact').toLowerCase(),
				meta.label
			);
			mappingsGrid.hidden = !visible;
			mappingsLocked.hidden = visible;
			saveRow.hidden = !visible;
			if (!visible) {
				mappingsLocked.querySelector('p').textContent = current.lists_loading
					? format(i18n('waitForDestinations', 'Wait while %s load.'), String(meta.destination_plural || '').toLowerCase())
					: format(
						i18n('chooseToLoadFields', 'Choose a %1$s to load its %2$s.'),
						String(meta.destination_singular || '').toLowerCase(),
						term(meta, 'data_plural', 'fields').toLowerCase()
					);
				return;
			}

			renderMappings(current);
			renderMailerLiteOptions(current);
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
			renderRouting(current);
			renderMailerLiteOptions(current);
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
				if (!response.ok) {
					throw new Error(data.message || requestFailed());
				}
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
			current.base_groups = Array.isArray(baseline.base_groups) ? JSON.parse(JSON.stringify(baseline.base_groups)) : [];
			current.additional_groups = Array.isArray(baseline.additional_groups) ? JSON.parse(JSON.stringify(baseline.additional_groups)) : [];
			current.routing_rules = Array.isArray(baseline.routing_rules) ? JSON.parse(JSON.stringify(baseline.routing_rules)) : [];
			current.status_mode = String(baseline.status_mode || 'legacy_provider_managed');
			current.resubscribe_force = Boolean(baseline.resubscribe_force);
			current.consent_metadata_enabled = Boolean(baseline.consent_metadata_enabled);
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
			lookupEmail.value = '';
			lookupResults.replaceChildren();
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
					current.base_groups = [];
					current.additional_groups = [];
					current.routing_rules = [];
					current.configured = false;
					current.dirty = false;
					initialProviders[slug] = JSON.parse(JSON.stringify(current));
				}
				current.replacing = false;
				current.busy = false;
				current.lists_loading = false;
				const lists = sortedLists(current);
				if (1 === lists.length && !current.selected_list) {
					await loadFields(String(lists[0].id), true);
					return;
				}
				if (current.selected_list && lists.some(function(list) { return String(list.id) === String(current.selected_list); })) {
					await loadFields(String(current.selected_list), true);
					return;
				}
				render();
				showMessage(
					format(
						i18n('connectedDestinationsReady', '%1$s connected. %2$d %3$s ready.'),
						definition(slug).label,
						lists.length,
						term(definition(slug), 'destination_plural', 'destinations').toLowerCase()
					),
					'success'
				);
			} catch (error) {
				current.busy = false;
				current.lists_loading = false;
				if (error.name !== 'AbortError') {
					render();
					showMessage(error.message || requestFailed(), 'error');
				}
			}
		}

		async function loadFields(listId, automatic, focusFieldKey) {
			const slug = state.activeProvider;
			const current = providerState(slug);
			const meta = definition(slug);
			const previous = {
				selected_list: current.selected_list,
				selected_list_name: current.selected_list_name,
				fields: current.fields,
				total_fields: current.total_fields,
				mappings: current.mappings
				,base_groups: current.base_groups
				,additional_groups: current.additional_groups
			};
			current.selected_list = listId;
			const selected = (current.lists || []).find(function(list) {
				return String(list.id) === String(listId);
			});
			current.selected_list_name = selected ? String(selected.name) : '';
			current.additional_groups = (current.additional_groups || []).filter(function(groupId) { return String(groupId) !== String(listId); });
			current.base_groups = listId ? [String(listId), ...current.additional_groups.map(String)] : [];
			current.fields_loading = Boolean(listId);
			current.fields = [];
			render();
			if (!listId) {
				current.total_fields = 0;
				current.mappings = {};
				updateDirty(slug);
				return;
			}

			abortRequests();
			const token = ++generation;
			showMessage(format(
				i18n('loadingProviderFields', 'Loading %1$s %2$s...'),
				meta.label,
				term(meta, 'data_plural', 'fields').toLowerCase()
			), '');
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
				updateDirty(slug);
				render();
				if (focusFieldKey) {
					const row = Array.from(root.querySelectorAll('.cmatic-provider-mapping-row')).find(function(item) { return String(item.dataset.remoteTag || '') === String(focusFieldKey); });
					const select = row ? row.querySelector('[data-mapping-slot]') : null;
					if (select) {
						row.scrollIntoView({ block: 'nearest' });
						select.focus();
					} else {
						showMessage(i18n('fieldRefreshRequired', 'Subscriber field created; refresh MailerLite subscriber fields before mapping it.'), 'error');
					}
				}
				showMessage(
					automatic
						? format(i18n('onlyDestinationSelected', '%1$s connected. %2$s was selected automatically.'), meta.label, current.selected_list_name)
						: format(
							i18n('providerFieldsReady', '%1$s %2$s are ready.'),
							meta.label,
							term(meta, 'data_plural', 'fields').toLowerCase()
						),
					'success'
				);
			} catch (error) {
				current.selected_list = previous.selected_list;
				current.selected_list_name = previous.selected_list_name;
				current.fields = previous.fields;
				current.total_fields = previous.total_fields;
				current.mappings = previous.mappings;
				current.base_groups = previous.base_groups;
				current.additional_groups = previous.additional_groups;
				current.fields_loading = false;
				if (error.name !== 'AbortError') {
					render();
					showMessage(error.message || requestFailed(), 'error');
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
				if (error.name !== 'AbortError') showMessage(error.message || requestFailed(), 'error');
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

		routingAddRule.addEventListener('click', function() {
			const current = providerState(state.activeProvider);
			if (!routingTags().length || !sortedLists(current).length) return;
			current.routing_rules.push({
				id: createRoutingRuleId(),
				field: '',
				value: '',
				group_id: ''
			});
			routingValidationVisible = false;
			current.configured = false;
			updateDirty(state.activeProvider);
			render();
			const rows = routingRuleList.querySelectorAll('[data-routing-rule]');
			const last = rows[rows.length - 1];
			if (last) last.querySelector('[data-routing-field]')?.focus();
		});

		mailerliteStatus.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.status_mode = mailerliteStatus.value;
			if (current.status_mode !== 'active') current.resubscribe_force = false;
			updateDirty(state.activeProvider);
			render();
			mailerliteStatus.focus();
		});
		mailerliteResubscribeForce.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.resubscribe_force = mailerliteResubscribeForce.checked;
			updateDirty(state.activeProvider);
			render();
		});
		mailerliteConsentMetadata.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.consent_metadata_enabled = mailerliteConsentMetadata.checked;
			updateDirty(state.activeProvider);
			render();
		});

		consentGate.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.consent_gate = consentGate.value === 'required' ? 'required' : 'none';
			current.consent_field = current.consent_gate === 'required' ? String(consentField.value || '') : '';
			updateDirty(state.activeProvider);
			render();
		});
		consentField.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.consent_field = String(consentField.value || '');
			updateDirty(state.activeProvider);
			render();
		});
		subscriptionMode.addEventListener('change', function() {
			const current = providerState(state.activeProvider);
			current.subscription_mode = subscriptionMode.value === 'double' ? 'double' : 'single';
			current.doi_verification_token = '';
			updateDirty(state.activeProvider);
			render();
		});
		[doiTemplate, doiRedirect].forEach(function(input) {
			input.addEventListener('input', function() {
				const current = providerState(state.activeProvider);
				current.doi_template_id = Number(doiTemplate.value || 0);
				current.doi_redirect_url = String(doiRedirect.value || '');
				current.doi_verification_token = '';
				updateDirty(state.activeProvider);
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
			const groupControl = event.target.closest('[data-mailerlite-group-selected], [data-mailerlite-group-primary]');
			if (groupControl) {
				const current = providerState(state.activeProvider);
				const row = groupControl.closest('[data-mailerlite-group]');
				const groupId = String(row?.dataset.mailerliteGroup || '');
				const checkedGroups = Array.from(mailerliteGroups.querySelectorAll('[data-mailerlite-group-selected]:checked')).map(function(input) { return input.value; });
				let primary = String(current.selected_list || '');
				if (groupControl.matches('[data-mailerlite-group-primary]')) {
					primary = groupId;
					if (!checkedGroups.includes(primary)) checkedGroups.push(primary);
				} else if (!checkedGroups.includes(primary)) {
					primary = String(checkedGroups[0] || '');
				}
				if (!primary) {
					groupControl.checked = true;
					return;
				}
				current.additional_groups = checkedGroups.filter(function(id) { return String(id) !== primary; });
				current.base_groups = [primary, ...current.additional_groups];
				current.configured = false;
				if (primary !== String(current.selected_list || '')) {
					loadFields(primary, false);
					return;
				}
				updateDirty(state.activeProvider);
				render();
				const replacement = mailerliteGroups.querySelector('[data-mailerlite-group="' + CSS.escape(groupId) + '"] ' + (groupControl.matches('[data-mailerlite-group-primary]') ? '[data-mailerlite-group-primary]' : '[data-mailerlite-group-selected]'));
				if (replacement) replacement.focus();
				return;
			}
			const routingControl = event.target.closest('[data-routing-field], [data-routing-value], [data-routing-group]');
			if (routingControl) {
				const row = routingControl.closest('[data-routing-rule]');
				const current = providerState(state.activeProvider);
				const rule = (current.routing_rules || []).find(function(item) { return String(item.id) === String(row.dataset.routingRule); });
				if (!rule) return;
				if (routingControl.matches('[data-routing-field]')) {
					rule.field = routingControl.value;
					rule.value = '';
				} else if (routingControl.matches('[data-routing-value]')) {
					rule.value = routingControl.value;
				} else {
					rule.group_id = routingControl.value;
				}
				current.configured = false;
				updateDirty(state.activeProvider);
				render();
				const replacement = routingRuleList.querySelector('[data-routing-rule="' + CSS.escape(String(rule.id)) + '"] ' + (routingControl.matches('[data-routing-field]') ? '[data-routing-field]' : routingControl.matches('[data-routing-value]') ? '[data-routing-value]' : '[data-routing-group]'));
				if (replacement) replacement.focus();
				return;
			}
			const select = event.target.closest('[data-mapping-slot]');
			if (!select || !root.contains(select)) return;
			const current = providerState(state.activeProvider);
			current.mappings = current.mappings || {};
			current.mappings[select.dataset.mappingSlot] = select.value;
			current.configured = false;
			updateDirty(state.activeProvider);
			render();
			document.getElementById(select.id)?.focus();
		});

		root.addEventListener('click', function(event) {
			const remove = event.target.closest('[data-routing-remove]');
			if (!remove) return;
			const row = remove.closest('[data-routing-rule]');
			const current = providerState(state.activeProvider);
			current.routing_rules = (current.routing_rules || []).filter(function(rule) { return String(rule.id) !== String(row.dataset.routingRule); });
			current.configured = false;
			updateDirty(state.activeProvider);
			render();
			routingAddRule.focus();
		});

		fieldCreatorButton.addEventListener('click', async function() {
			if (fieldCreating || !fieldCreatorName.value.trim()) return;
			fieldCreating = true;
			let finalMessage = '';
			let finalTone = '';
			const current = providerState(state.activeProvider);
			setBusy(fieldCreatorButton, true, i18n('creating', 'Creating...'));
			try {
				const data = await request('field-create', 'fields/create', {
					form_id: Number(root.dataset.formId),
					provider: 'mailerlite',
					name: fieldCreatorName.value.trim(),
					type: fieldCreatorType.querySelector('input[type="radio"]:checked')?.value || 'text'
				});
				const key = String((data.field || {}).key || '');
				fieldCreatorName.value = '';
				await loadFields(String(current.selected_list || ''), false, key);
				finalMessage = i18n('fieldCreated', 'MailerLite subscriber field created. Choose its Contact Form 7 source and save.');
				finalTone = 'success';
			} catch (error) {
				if (error.name !== 'AbortError') {
					finalMessage = error.message || i18n('fieldCreateUnconfirmed', 'Field creation was not confirmed. Refresh MailerLite subscriber fields before retrying.');
					finalTone = 'error';
				}
			} finally {
				fieldCreating = false;
				setBusy(fieldCreatorButton, false, i18n('createField', 'Create field'));
				render();
				if (finalMessage) showMessage(finalMessage, finalTone);
			}
		});

		lookupButton.addEventListener('click', async function() {
			const email = lookupEmail.value.trim();
			if (!email) return;
			setBusy(lookupButton, true, i18n('findingSubscriber', 'Finding subscriber...'));
			lookupResults.replaceChildren();
			try {
				const data = await request('lookup', 'lookup', { form_id: Number(root.dataset.formId), provider: 'mailerlite', email: email });
				const summary = document.createElement('p');
				summary.className = 'cmatic-lookup-summary';
				summary.textContent = data.found
					? format(i18n('subscriberFound', 'Subscriber found · %s'), String(data.status || i18n('statusUnavailable', 'status unavailable')))
					: i18n('noSubscriberFound', 'No subscriber found.');
				lookupResults.append(summary);
				if (data.found) {
					const card = document.createElement('div');
					card.className = 'cmatic-result-card';
					const groups = document.createElement('p');
					groups.textContent = format(
						i18n('groupsLabel', 'Groups: %s'),
						(data.groups || []).map(function(group) { return String(group.name || group.id || ''); }).filter(Boolean).join(', ')
					);
					card.append(groups);
					Object.entries(data.fields || {}).forEach(function(entry) {
						const item = document.createElement('p');
						item.textContent = String(entry[0]) + ': ' + String(entry[1]);
						card.append(item);
					});
					lookupResults.append(card);
				}
			} catch (error) {
				if (error.name !== 'AbortError') lookupResults.textContent = error.message || i18n('lookupFailed', 'MailerLite could not find the subscriber. Try again.');
			} finally {
				setBusy(lookupButton, false, i18n('findSubscriber', 'Find subscriber'));
			}
		});

		if (parentForm) {
			parentForm.addEventListener('submit', function(event) {
				if (!state.activeProvider || 'mailchimp' === state.activeProvider) return;
				const current = providerState(state.activeProvider);
				if (!current.connected || !current.selected_list || !current.fields.length) {
					event.preventDefault();
					const meta = definition(state.activeProvider);
					showMessage(format(
						i18n('missingDestination', 'Connect %1$s and choose a %2$s before saving.'),
						meta.label,
						String(meta.destination_singular || '').toLowerCase()
					), 'error');
					return;
				}
				if (!requiredEmailMapped()) {
					event.preventDefault();
					showMessage(i18n('missingEmailMapping', 'Select a Contact Form 7 field for the required email address.'), 'error');
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
				if ('mailerlite' === state.activeProvider && current.routing_entitled) {
					const validation = routingValidation(current);
					if (validation.size) {
						event.preventDefault();
						routingValidationVisible = true;
						render();
						showMessage(i18n('routingFixBeforeSave', 'Complete or remove the highlighted routing rules before saving.'), 'error');
						const firstInvalid = routingRuleList.querySelector('[aria-invalid="true"]');
						if (firstInvalid) firstInvalid.focus();
						return;
					}
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
