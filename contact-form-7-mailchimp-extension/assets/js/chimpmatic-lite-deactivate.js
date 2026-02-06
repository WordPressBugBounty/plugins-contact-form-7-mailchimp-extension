/**
 * Deactivation survey modal.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

(function () {
	'use strict';

	const config = window.cmaticDeactivate || {};
	let deactivateUrl = '';
	let modalElement = null;
	let pluginsCache = null;
	const MAX_RETRIES = 3;

	document.addEventListener('DOMContentLoaded', () => {
		init();
	});

	function init() {
		const deactivateLink = findDeactivateLink();
		if (!deactivateLink) return;

		deactivateUrl = deactivateLink.href;
		buildModal();
		attachEventListeners(deactivateLink);
	}

	function findDeactivateLink() {
		const row = document.querySelector(`tr[data-slug="${config.pluginSlug}"]`);
		return row ? row.querySelector('.deactivate a') : null;
	}

	function buildModal() {
		modalElement = document.getElementById('cmatic-deactivate-modal');
		if (!modalElement) return;

		modalElement.innerHTML = `
			<div class="cmatic-modal__overlay"></div>
			<div class="cmatic-modal__dialog">
				<div class="cmatic-modal__header">
					<h2 id="cmatic-modal-title">${config.strings.title}</h2>
					<button type="button" class="cmatic-modal__close" aria-label="${config.strings.closeLabel}">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="cmatic-modal__body">
					<h3 id="cmatic-modal-description">${config.strings.description}</h3>
					<form id="cmatic-deactivate-form">
						<div class="cmatic-reasons" role="radiogroup" aria-labelledby="cmatic-modal-description">
							${buildReasonsList()}
						</div>
						<div class="cmatic-error-message" role="alert" aria-live="assertive"></div>
					</form>
				</div>
				<div class="cmatic-modal__footer">
					<a href="#" class="cmatic-skip-link" style="display: none;">${config.strings.skipButton}</a>
					<button type="submit" form="cmatic-deactivate-form" class="button button-primary cmatic-submit-button">
						${config.strings.submitButton}
					</button>
				</div>
			</div>
		`;
	}

	function buildReasonsList() {
		return config.reasons.map(reason => {
			return `
				<button type="button" class="cmatic-reason-btn" data-reason-id="${reason.id}" data-input-type="${reason.input_type}" aria-pressed="false">
					${reason.text}
				</button>
			`;
		}).join('');
	}

	function buildInputField(reasonId, inputType) {
		const reason = config.reasons.find(r => r.id === reasonId);
		if (!reason) return '';

		let inputHtml = '';
		if (inputType === 'plugin-dropdown') {
			inputHtml = `<select class="cmatic-input-field" aria-label="${reason.placeholder || 'Select a plugin'}" disabled><option value="">Loading plugins...</option></select>`;
		} else if (inputType === 'textfield') {
			inputHtml = `<input type="text" class="cmatic-input-field" placeholder="${reason.placeholder}" maxlength="${reason.max_length || 200}" aria-label="${reason.placeholder}" />`;
		}

		if (inputHtml) {
			return `<div class="cmatic-input-wrapper">${inputHtml}</div>`;
		}
		return '';
	}

	async function fetchPluginsList() {
		if (pluginsCache) return pluginsCache;

		try {
			const response = await fetch(config.pluginsUrl, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': config.restNonce,
				},
			});

			if (response.ok) {
				pluginsCache = await response.json();
				return pluginsCache;
			}
		} catch (error) {
			console.error('ChimpMatic: Failed to fetch plugins list', error);
		}
		return [];
	}

	function populatePluginDropdown(selectElement, plugins) {
		let optionsHtml = '<option value="">-- Select Plugin --</option>';
		if (plugins && plugins.length > 0) {
			plugins.forEach(plugin => {
				optionsHtml += `<option value="${plugin.value}">${plugin.label}</option>`;
			});
		}
		selectElement.innerHTML = optionsHtml;
		selectElement.disabled = false;
	}

	function attachEventListeners(deactivateLink) {
		deactivateLink.addEventListener('click', handleDeactivateClick);
		modalElement.querySelector('.cmatic-modal__close').addEventListener('click', closeModal);
		modalElement.querySelector('.cmatic-skip-link').addEventListener('click', handleSkip);
		modalElement.querySelector('#cmatic-deactivate-form').addEventListener('submit', handleSubmit);
		modalElement.querySelectorAll('.cmatic-reason-btn').forEach(btn => btn.addEventListener('click', handleReasonClick));
		document.addEventListener('keydown', handleKeydown);
		modalElement.querySelector('.cmatic-modal__overlay').addEventListener('click', handleOverlayClick);
	}

	function handleDeactivateClick(evt) {
		evt.preventDefault();
		openModal();
	}

	function openModal() {
		modalElement.classList.add('cmatic-modal--active');
		document.body.classList.add('cmatic-modal-open');
		const firstBtn = modalElement.querySelector('.cmatic-reason-btn');
		if (firstBtn) firstBtn.focus();
		trapFocus();

		setTimeout(() => {
			const skipLink = modalElement.querySelector('.cmatic-skip-link');
			if (skipLink) {
				skipLink.style.display = 'inline';
				skipLink.style.animation = 'fadeIn 0.3s ease';
			}
		}, 10000);
	}

	function closeModal() {
		modalElement.classList.add('cmatic-modal--closing');

		setTimeout(() => {
			modalElement.classList.remove('cmatic-modal--active', 'cmatic-modal--closing');
			document.body.classList.remove('cmatic-modal-open');
			resetForm();
		}, 300);
	}

	function handleSkip(evt) {
		evt.preventDefault();

		const skipData = {
			reason_id: 0,
			reason_text: '',
		};

		const submitBtn = modalElement.querySelector('.cmatic-submit-btn');
		const skipLink = modalElement.querySelector('.cmatic-skip-link');
		submitBtn.disabled = true;
		submitBtn.textContent = cmaticData.i18n.submitting;
		skipLink.style.display = 'none';

		fetch(cmaticData.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cmaticData.nonce,
			},
			body: JSON.stringify(skipData),
		}).finally(() => {
			window.location.href = deactivateUrl;
		});
	}

	async function handleReasonClick(evt) {
		const clickedBtn = evt.currentTarget;
		const reasonId = parseInt(clickedBtn.dataset.reasonId, 10);
		const inputType = clickedBtn.dataset.inputType;

		modalElement.querySelectorAll('.cmatic-reason-btn').forEach(btn => {
			btn.classList.remove('selected');
			btn.setAttribute('aria-pressed', 'false');
			const nextEl = btn.nextElementSibling;
			if (nextEl && nextEl.classList.contains('cmatic-input-wrapper')) {
				nextEl.remove();
			}
		});

		clickedBtn.classList.add('selected');
		clickedBtn.setAttribute('aria-pressed', 'true');

		if (inputType && inputType !== '') {
			const inputHtml = buildInputField(reasonId, inputType);
			if (inputHtml) {
				clickedBtn.insertAdjacentHTML('afterend', inputHtml);
				const input = clickedBtn.nextElementSibling.querySelector('.cmatic-input-field');

				if (inputType === 'plugin-dropdown' && input) {
					const plugins = await fetchPluginsList();
					populatePluginDropdown(input, plugins);
					input.focus();
				} else if (input) {
					setTimeout(() => input.focus(), 100);
				}
			}
		}

		hideValidationError();
	}

	async function handleSubmit(evt) {
		evt.preventDefault();
		if (!validateForm()) return;

		const selectedBtn = modalElement.querySelector('.cmatic-reason-btn.selected');
		const reasonId = parseInt(selectedBtn.dataset.reasonId, 10);
		const inputWrapper = selectedBtn.nextElementSibling;
		const inputField = inputWrapper && inputWrapper.classList.contains('cmatic-input-wrapper')
			? inputWrapper.querySelector('.cmatic-input-field')
			: null;
		const reasonText = inputField ? inputField.value.trim() : '';

		setButtonsDisabled(true);

		try {
			await submitFeedback(reasonId, reasonText);
		} catch (error) {
			console.error('ChimpMatic: Failed to submit feedback', error);
		}
		window.location.href = deactivateUrl;
	}

	function validateForm() {
		const selectedBtn = modalElement.querySelector('.cmatic-reason-btn.selected');
		if (!selectedBtn) {
			showValidationError(config.strings.errorRequired);
			return false;
		}

		const inputWrapper = selectedBtn.nextElementSibling;
		if (!inputWrapper || !inputWrapper.classList.contains('cmatic-input-wrapper')) {
			hideValidationError();
			return true;
		}

		const inputField = inputWrapper.querySelector('.cmatic-input-field');
		if (!inputField) {
			hideValidationError();
			return true;
		}

		if (inputField.type === 'text' && inputField.value.trim() === '') {
			showValidationError(config.strings.errorDetails);
			inputField.focus();
			return false;
		}

		if (inputField.tagName === 'SELECT' && inputField.value === '') {
			showValidationError(config.strings.errorDropdown);
			inputField.focus();
			return false;
		}

		hideValidationError();
		return true;
	}

	async function submitFeedback(reasonId, reasonText, retry = 0) {
		const response = await fetch(config.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.restNonce,
			},
			body: JSON.stringify({ reason_id: reasonId, reason_text: reasonText }),
		});

		if (!response.ok) {
			if (retry < MAX_RETRIES) {
				const delay = Math.pow(2, retry) * 1000;
				await new Promise(resolve => setTimeout(resolve, delay));
				console.warn(`ChimpMatic: Retry ${retry + 1}/${MAX_RETRIES}`);
				return submitFeedback(reasonId, reasonText, retry + 1);
			}
			throw new Error('Failed to submit feedback');
		}

		return response.json();
	}

	function showValidationError(message) {
		const errorEl = modalElement.querySelector('.cmatic-error-message');
		errorEl.textContent = message;
		errorEl.classList.add('cmatic-error-message--visible');
	}

	function hideValidationError() {
		const errorEl = modalElement.querySelector('.cmatic-error-message');
		errorEl.textContent = '';
		errorEl.classList.remove('cmatic-error-message--visible');
	}

	function handleKeydown(evt) {
		if (!modalElement.classList.contains('cmatic-modal--active')) return;
		if (evt.key === 'Escape') {
			evt.preventDefault();
			closeModal();
		}
	}

	function handleOverlayClick() {
		const textInputs = modalElement.querySelectorAll('input[type="text"]');
		const selects = modalElement.querySelectorAll('select');
		const hasContent = Array.from(textInputs).some(input => input.value.trim() !== '') ||
		                   Array.from(selects).some(select => select.value !== '');
		if (hasContent) {
			const confirmed = confirm('You have unsaved feedback. Close anyway?');
			if (confirmed) closeModal();
		} else {
			closeModal();
		}
	}

	function trapFocus() {
		const focusableElements = modalElement.querySelectorAll('button, input, select, [tabindex]:not([tabindex="-1"])');
		if (focusableElements.length === 0) return;

		const firstFocusable = focusableElements[0];
		const lastFocusable = focusableElements[focusableElements.length - 1];

		function handleTabKey(evt) {
			if (evt.key !== 'Tab') return;
			if (evt.shiftKey && document.activeElement === firstFocusable) {
				evt.preventDefault();
				lastFocusable.focus();
			} else if (!evt.shiftKey && document.activeElement === lastFocusable) {
				evt.preventDefault();
				firstFocusable.focus();
			}
		}

		modalElement.addEventListener('keydown', handleTabKey);
	}

	function resetForm() {
		modalElement.querySelectorAll('.cmatic-reason-btn').forEach(btn => {
			btn.classList.remove('selected');
			btn.setAttribute('aria-pressed', 'false');
			const nextEl = btn.nextElementSibling;
			if (nextEl && nextEl.classList.contains('cmatic-input-wrapper')) {
				nextEl.remove();
			}
		});
		hideValidationError();
		setButtonsDisabled(false);
		const skipLink = modalElement.querySelector('.cmatic-skip-link');
		if (skipLink) skipLink.style.display = 'none';
	}

	function setButtonsDisabled(disabled) {
		modalElement.querySelectorAll('.button').forEach(button => button.disabled = disabled);
	}
})();
