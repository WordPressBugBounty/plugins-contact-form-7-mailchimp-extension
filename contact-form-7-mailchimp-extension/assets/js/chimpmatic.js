/**
 * Admin JavaScript.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

/**
 * Fix CF7's beforeunload detection for dynamically populated selects.
 *
 * CF7 compares defaultValue vs value to detect changes. PHP-generated selects
 * with selected="selected" have undefined defaultValue causing false positives.
 * This syncs defaultValue to current value on page load.
 */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('wpcf7-admin-form-element');
    if (!form) return;

    // Sync select defaultValue to prevent false "unsaved changes" warnings
    form.querySelectorAll('select').forEach(function(select) {
        if (select.value && select.defaultValue !== select.value) {
            // Set defaultValue for select (affects selectedIndex tracking)
            Array.from(select.options).forEach(function(opt) {
                opt.defaultSelected = opt.selected;
            });
        }
    });

    // Sync checkbox values (browsers set value="on" but defaultValue="")
    form.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
        if (cb.value && !cb.defaultValue) {
            cb.defaultValue = cb.value;
        }
    });
});

function getFormId() {
    const dataContainer = document.getElementById('cmatic_data');
    if (dataContainer && dataContainer.dataset.formId) {
        return parseInt(dataContainer.dataset.formId, 10) || 0;
    }
    return 0;
}

function getApiValid() {
    const dataContainer = document.getElementById('cmatic_data');
    return dataContainer?.dataset?.apiValid || '0';
}

/**
 * Show inline status message near an element (non-invasive alternative to alert).
 *
 * @param {HTMLElement} targetElement Element to show message near.
 * @param {string}      message       Message text.
 * @param {string}      type          'error', 'warning', or 'success'.
 * @param {number}      duration      Auto-hide after ms (0 = manual close).
 */
function showInlineMessage(targetElement, message, type = 'warning', duration = 5000) {
    // Remove any existing message
    const existingMsg = targetElement.parentNode.querySelector('.cmatic-inline-msg');
    if (existingMsg) existingMsg.remove();

    const msg = document.createElement('span');
    msg.className = `cmatic-inline-msg cmatic-msg-${type}`;
    msg.textContent = message;
    msg.style.cssText = `
        display: inline-block;
        margin-left: 10px;
        padding: 4px 10px;
        border-radius: 3px;
        font-size: 13px;
        animation: cmatic-fade-in 0.3s ease;
    `;

    if (type === 'error') {
        msg.style.background = '#f8d7da';
        msg.style.color = '#721c24';
        msg.style.border = '1px solid #f5c6cb';
    } else if (type === 'warning') {
        msg.style.background = '#fff3cd';
        msg.style.color = '#856404';
        msg.style.border = '1px solid #ffeeba';
    } else {
        msg.style.background = '#d4edda';
        msg.style.color = '#155724';
        msg.style.border = '1px solid #c3e6cb';
    }

    targetElement.parentNode.insertBefore(msg, targetElement.nextSibling);

    if (duration > 0) {
        setTimeout(() => {
            msg.style.opacity = '0';
            msg.style.transition = 'opacity 0.3s ease';
            setTimeout(() => msg.remove(), 300);
        }, duration);
    }
}

/**
 * Securely get the API key - fetches from REST endpoint if masked.
 * CVE-2025-68989 fix: API key no longer stored in data-real-key attribute.
 * @returns {Promise<string>} The API key.
 */
async function getApiKey() {
    const apiInput = document.getElementById('cmatic-api');
    if (!apiInput) return '';

    const isMasked = apiInput.dataset.isMasked === '1';
    const hasKey = apiInput.dataset.hasKey === '1';
    const inputValue = apiInput.value.trim();

    // If not masked, the input contains the real key (user just typed/pasted it).
    if (!isMasked) {
        return inputValue;
    }

    // If masked but no key exists in DB, return empty.
    if (!hasKey) {
        return '';
    }

    // Masked with existing key - fetch the real key from secure endpoint.
    const formId = getFormId();
    if (!formId) return '';

    try {
        // Use Lite endpoint (works for both Lite and PRO).
        const restUrl = typeof chimpmaticLite !== 'undefined'
            ? chimpmaticLite.restUrl
            : getRestUrl().replace('chimpmatic/v1/', 'chimpmatic-lite/v1/');
        const nonce = typeof chimpmaticLite !== 'undefined'
            ? chimpmaticLite.restNonce
            : (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '');

        const response = await fetch(
            `${restUrl}api-key/${formId}`,
            {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': nonce,
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

function setApiValid(value) {
    const dataContainer = document.getElementById('cmatic_data');
    if (dataContainer) {
        dataContainer.dataset.apiValid = value ? '1' : '0';
    }
}

function getRestUrl() {
    if (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) {
        return wpApiSettings.root + 'chimpmatic/v1/';
    }
    return '/wp-json/chimpmatic/v1/';
}

const REST_ENDPOINTS = {
    SETTINGS_SAVE: 'settings/save',
    SETTINGS_CONFIG: 'settings/config',
    AUDIENCES: 'mailchimp/audiences',
    FIELDS: 'mailchimp/fields',
    GROUPS: 'mailchimp/groups',
    INTERESTS: 'mailchimp/interests',
    EXPORT_USERS: 'export/users',
    NOTICES_DISMISS: 'notices/dismiss',
    TELEMETRY_TOGGLE: 'telemetry/toggle'
};

const actionToEndpoint = {
    'wpcf7_chm_savetool': 'settings/save',
    'wpcf7_chm_savetool_cfg': 'settings/config',
    'wpcf7_chm_loadlistas': 'mailchimp/audiences',
    'wpcf7_chm_loadcampos': 'mailchimp/fields',
    'wpcf7_chm_loadgrupos': 'mailchimp/groups',
    'wpcf7_chm_get_interest': 'mailchimp/interests',
    'wpcf7_chm_exporuser': 'export/users'
};

async function chmRequest(actionOrEndpoint, data = {}) {
    const endpoint = actionToEndpoint[actionOrEndpoint] || actionOrEndpoint;
    return await chmRestRequest(endpoint, data);
}

async function chmRestRequest(endpoint, data = {}) {
    const url = getRestUrl() + endpoint;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings?.nonce || ''
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        return result.html || result.data || '';
    } catch (error) {
        console.error('[Chimpmatic] Error:', error);
        throw error;
    }
}

// Save Tool Configuration
document.addEventListener('click', function(event) {
    if (event.target && event.target.id === 'chm_submitme') {
        event.preventDefault();

        const data = {
            tool_key: document.getElementById('wpcf7-mailchimp-tool_key')?.value || '',
            chm_idformxx: getFormId(),
            uemail: document.getElementById('wpcf7-mailchimp-uemail')?.value || '',
            prod_id: document.getElementById('wpcf7-mailchimp-prod-id')?.value || ''
        };

        chmRequest('wpcf7_chm_savetool', data)
            .then(response => {
                const panel = document.getElementById('chm_panel_principal');
                if (panel) panel.innerHTML = response;
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
    }
});

// Save Tool Config
document.addEventListener('click', function(event) {
    if (event.target && event.target.id === 'chm_submitme_cfg') {
        event.preventDefault();

        const data = {
            tool_key: document.getElementById('wpcf7-mailchimp-tool_key')?.value || '',
            uemail: document.getElementById('wpcf7-mailchimp-uemail')?.value || '',
            prod_id: document.getElementById('wpcf7-mailchimp-prod-id')?.value || ''
        };

        chmRequest('wpcf7_chm_savetool_cfg', data)
            .then(response => {
                const liveElements = document.querySelectorAll('.chimp-live');
                liveElements.forEach(el => el.innerHTML = response);
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
    }
});

// Connect and Fetch Your Audiences (Load Lists)
document.addEventListener('click', function(event) {
    if (event.target && event.target.id === 'chm_activalist') {
        event.preventDefault();

        const button = event.target;
        if (button.disabled) return;

        const panel = button.closest('.cmatic-field-group') || button.closest('.mce-custom-fields');
        let apiKeyElement = panel ? panel.querySelector('#cmatic-api') : null;
        if (!apiKeyElement) {
            apiKeyElement = document.getElementById('cmatic-api');
        }

        const apiKey = apiKeyElement?.value || '';
        if (!apiKey || apiKey.trim() === '') {
            showInlineMessage(button, 'Enter API key first', 'warning');
            return;
        }

        const formId = getFormId();
        if (!formId || formId <= 0) {
            showInlineMessage(button, 'Save form first', 'warning');
            return;
        }

        const data = {
            tool_key: document.getElementById('wpcf7-mailchimp-tool_key')?.value || '',
            chm_idformxx: formId,
            chimpapi: apiKey
        };

        const originalText = button.value;
        button.value = 'Syncing...';
        button.disabled = true;

        chmRequest('wpcf7_chm_loadlistas', data)
            .then(response => {
                const listPanel = document.getElementById('chm_panel_listamail');
                if (listPanel) {
                    listPanel.innerHTML = response;
                }

                const valor = getApiValid();
                let attrclass = '';
                let chm_valid = '';

                if (valor === '1') {
                    attrclass = 'spt-response-out spt-valid';
                    chm_valid = '<h3 class="title">Chimpmatic<span><span class="chmm valid">API Connected</span></span></h3>';

                    button.value = 'Synced';
                    button.disabled = false;
                    setTimeout(() => {
                        button.value = 'Connected';
                        const fieldsBtn = document.getElementById('chm_selgetcampos');
                        if (fieldsBtn && !fieldsBtn.disabled) {
                            fieldsBtn.click();
                        }
                    }, 800);
                } else {
                    attrclass = 'spt-response-out';
                    chm_valid = '<h3 class="title">Chimpmatic<span><span class="chmm invalid">API Inactive</span></span></h3>';

                    button.value = originalText;
                    button.disabled = false;

                    const configPanel = document.getElementById('chm_panel_configcampos');
                    if (configPanel) {
                        configPanel.innerHTML = '<span> </span>';
                    }
                }

                if (listPanel) {
                    listPanel.className = attrclass;
                }

                const validPanel = document.getElementById('chm_apivalid');
                if (validPanel) {
                    validPanel.innerHTML = chm_valid;
                }
            })
            .catch(error => {
                button.value = originalText;
                button.disabled = false;
                alert('Error loading audiences: ' + error.message);
            });
    }
});

// Auto-connect on paste
document.addEventListener('paste', function(event) {
    const apiInput = document.getElementById('cmatic-api');
    if (event.target !== apiInput) return;

    setTimeout(function() {
        const apiKey = apiInput.value.trim();
        if (apiKey && apiKey.length >= 30 && apiKey.includes('-')) {
            const connectBtn = document.getElementById('chm_activalist');
            if (connectBtn && !connectBtn.disabled) {
                connectBtn.click();
            }
        }
    }, 100);
});

// Auto-refresh fields when audience dropdown changes
document.addEventListener('change', function(event) {
    if (event.target && event.target.id === 'wpcf7-mailchimp-list') {
        const fieldsBtn = document.getElementById('chm_selgetcampos') || document.getElementById('mce_fetch_fields');
        if (fieldsBtn && !fieldsBtn.disabled) {
            fieldsBtn.click();
        }
    }
});

// Fetch Your Fields and Groups
document.addEventListener('click', async function(event) {
    if (event.target && (event.target.id === 'chm_selgetcampos' || event.target.id === 'mce_fetch_fields')) {
        event.preventDefault();

        const button = event.target;
        if (button.disabled) return;

        const data = {
            tool_key: document.getElementById('wpcf7-mailchimp-tool_key')?.value || '',
            chm_idformxx: getFormId(),
            chm_listid: document.getElementById('wpcf7-mailchimp-list')?.value || '',
            chimpapi: await getApiKey()
        };

        const isInputButton = button.tagName === 'INPUT';
        const originalText = isInputButton ? button.value : button.textContent;
        if (isInputButton) {
            button.value = 'Syncing...';
        } else {
            button.textContent = 'Syncing...';
        }
        button.disabled = true;

        chmRequest('wpcf7_chm_loadcampos', data)
            .then(response => {
                const genPanel = document.getElementById('chm_panel_gencamposygrupos');

                if (genPanel) {
                    genPanel.innerHTML = response;
                    genPanel.className = 'spt-response-out';

                    const listPanel = document.getElementById('chm_panel_listamail');
                    const attrclass = listPanel ? listPanel.className : '';

                    if (attrclass === 'spt-response-out') {
                        genPanel.className = 'spt-response-out';
                    } else {
                        genPanel.className = 'spt-response-out spt-valid';
                    }

                    setTimeout(function() {
                        if (typeof applyFuzzyMatchingPro === 'function') {
                            applyFuzzyMatchingPro();
                        }
                    }, 100);
                }

                if (isInputButton) {
                    button.value = 'Synced!';
                } else {
                    button.textContent = 'Synced!';
                }
                setTimeout(() => {
                    if (isInputButton) {
                        button.value = originalText;
                    } else {
                        button.textContent = originalText;
                    }
                    button.disabled = false;
                }, 800);
            })
            .catch(error => {
                if (isInputButton) {
                    button.value = originalText;
                } else {
                    button.textContent = originalText;
                }
                button.disabled = false;
                alert('Error: ' + error.message);
            });
    }
});

// Load Groups
document.addEventListener('click', async function(event) {
    if (event.target && event.target.id === 'chm_activagroups') {
        event.preventDefault();

        const data = {
            tool_key: document.getElementById('wpcf7-mailchimp-tool_key')?.value || '',
            chm_idformxx: getFormId(),
            chm_listid: document.getElementById('wpcf7-mailchimp-list')?.value || '',
            chimpapi: await getApiKey()
        };

        chmRequest('wpcf7_chm_loadgrupos', data)
            .then(response => {
                const groupPanel = document.getElementById('chm_panel_listgroup');
                if (groupPanel) {
                    groupPanel.innerHTML = response;
                    groupPanel.className = 'spt-response-out';

                    const listPanel = document.getElementById('chm_panel_listamail');
                    const attrclass = listPanel ? listPanel.className : '';

                    if (attrclass === 'spt-response-out') {
                        groupPanel.className = 'spt-response-out';
                    } else {
                        groupPanel.className = 'spt-response-out spt-valid';
                    }
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
    }
});

// Export Users
document.addEventListener('click', async function(event) {
    if (event.target && event.target.id === 'chm_userexport') {
        event.preventDefault();

        const valuesChecked = [];
        let icont = 1;

        document.querySelectorAll("input[type='checkbox'][name='usercheck']:checked").forEach(checkbox => {
            const idListInput = document.getElementById(`wpcf7-mailchimp-idlistexport${icont}`);
            valuesChecked.push([
                checkbox.value,
                idListInput ? idListInput.value : ''
            ]);
            icont++;
        });

        const data = {
            tool_key: document.getElementById('wpcf7-mailchimp-tool_key')?.value || '',
            chm_idformxx: getFormId(),
            chimpapi: await getApiKey(),
            cadseluser: valuesChecked
        };

        chmRequest('wpcf7_chm_exporuser', data)
            .then(response => {
                const exportPanel = document.getElementById('chm_panel_exporuser');
                if (exportPanel) {
                    exportPanel.innerHTML = response;
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
    }
});

// Get Interest (Groups - Arbitrary)
document.addEventListener('change', async function(event) {
    if (event.target && event.target.classList.contains('chimp-gg-arbirary')) {
        event.preventDefault();

        const checkbox = event.target;
        const itag = checkbox.getAttribute('data-tag');
        const xchk = checkbox.checked ? 1 : 0;
        const ggKeyInput = document.getElementById(`wpcf7-mailchimp-ggCustomKey${itag}`);

        const data = {
            valcheck: xchk,
            chm_idformxx: getFormId(),
            chm_listid: document.getElementById('wpcf7-mailchimp-list')?.value || '',
            chimpapi: await getApiKey(),
            indtag: itag,
            ggid: ggKeyInput ? ggKeyInput.value : ''
        };

        chmRequest('wpcf7_chm_get_interest', data)
            .then(response => {
                // Find the select element and replace it with the response
                const selectElement = document.getElementById(`wpcf7-mailchimp-ggCustomValue${itag}`);
                if (selectElement) {
                    selectElement.outerHTML = response;

                    // Auto-select first interest option (backend already saved it)
                    const newSelect = document.getElementById(`wpcf7-mailchimp-ggCustomValue${itag}`);
                    if (newSelect) {
                        if (xchk === 1 && newSelect.options.length > 1) {
                            newSelect.selectedIndex = 1;
                        }
                        // Sync defaultSelected to prevent false "unsaved changes" warning
                        Array.from(newSelect.options).forEach(opt => {
                            opt.defaultSelected = opt.selected;
                        });
                    }
                }

                // Sync checkbox defaultChecked after successful save
                checkbox.defaultChecked = checkbox.checked;
            })
            .catch(error => {
                alert('Error loading interests: ' + error.message);
            });
    }
});

function togglePanel(panelSelector, buttonElement, showText, hideText) {
    const panel = typeof panelSelector === 'string' ?
        (panelSelector.startsWith('.') ? document.querySelector(panelSelector) : document.getElementById(panelSelector)) :
        panelSelector;

    if (panel) {
        const isHidden = panel.style.display === 'none' || !panel.style.display || window.getComputedStyle(panel).display === 'none';
        panel.style.display = isHidden ? 'block' : 'none';

        if (buttonElement && showText && hideText) {
            buttonElement.textContent = isHidden ? hideText : showText;
        }
    }
}

// On page load, set Connected button state
document.addEventListener('DOMContentLoaded', function() {
    const connectBtn = document.getElementById('chm_activalist');
    const isApiValid = getApiValid() === '1';

    if (connectBtn && isApiValid && connectBtn.value !== 'Connected') {
        connectBtn.value = 'Connected';
    }
});

function findBestMatchPro(mergeTag, fieldName, cf7Tags) {
    if (!mergeTag || !cf7Tags || cf7Tags.length === 0) return null;

    const normalize = (str) => String(str).toLowerCase().replace(/[^a-z0-9]/g, '');
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
                const match = cf7Tags.find(tag => {
                    const tagName = normalize(tag.name || tag);
                    return tagName.includes(cf7Keyword);
                });
                if (match) {
                    return match.name || match;
                }
            }
        }
    }

    for (const tag of cf7Tags) {
        const tagName = normalize(tag.name || tag);

        if (normalizedTag.includes(tagName) || tagName.includes(normalizedTag)) {
            return tag.name || tag;
        }

        if (normalizedName.includes(tagName) || tagName.includes(normalizedName)) {
            return tag.name || tag;
        }
    }

    return null;
}

function applyFuzzyMatchingPro() {
    const genPanel = document.getElementById('chm_panel_gencamposygrupos');
    const camposPanel = document.getElementById('chm_panel_camposforma');

    let fieldRows = document.querySelectorAll('#chm_panel_camposforma .mcee-container');

    if (fieldRows.length === 0) {
        fieldRows = document.querySelectorAll('#chm_panel_gencamposygrupos .mcee-container');
    }

    if (fieldRows.length === 0) {
        fieldRows = document.querySelectorAll('.mcee-container');
    }

    if (!fieldRows || fieldRows.length === 0) {
        return;
    }

    const cf7TagsSet = new Set();
    const allDropdowns = document.querySelectorAll('[id^="wpcf7-mailchimp-CustomValue"]');

    allDropdowns.forEach(dropdown => {
        Array.from(dropdown.options).forEach(option => {
            if (option.value && option.value.trim() !== '' && option.value !== ' ') {
                cf7TagsSet.add(option.value);
            }
        });
    });

    const cf7Tags = Array.from(cf7TagsSet).map(name => ({ name }));

    if (cf7Tags.length === 0) {
        return;
    }

    const changedFields = [];

    fieldRows.forEach((row, index) => {
        const keyInput = row.querySelector('[id^="wpcf7-mailchimp-CustomKey"]');
        const dropdown = row.querySelector('[id^="wpcf7-mailchimp-CustomValue"]');

        if (!keyInput || !dropdown) return;

        if (dropdown.value && dropdown.value.trim() !== '' && dropdown.value !== ' ') {
            return;
        }

        const dropdownOptions = [];
        Array.from(dropdown.options).forEach(option => {
            if (option.value && option.value.trim() !== '' && option.value !== ' ') {
                dropdownOptions.push({ name: option.value });
            }
        });

        if (dropdownOptions.length === 0) {
            return;
        }

        const mergeTag = keyInput.value;
        const label = row.querySelector('label');
        const fieldName = label ? label.textContent.split('*|')[0].trim() : mergeTag;

        const bestMatch = findBestMatchPro(mergeTag, fieldName, dropdownOptions);
        if (bestMatch) {
            dropdown.value = bestMatch;
            const fieldMatch = dropdown.id.match(/wpcf7-mailchimp-CustomValue(\d+)/);
            if (fieldMatch) {
                changedFields.push({ field: 'CustomValue' + fieldMatch[1], value: bestMatch });
            }
            Array.from(dropdown.options).forEach(opt => {
                opt.defaultSelected = (opt.value === bestMatch);
            });
        }
    });

    if (changedFields.length > 0) {
        saveFieldMappingsPro(changedFields);
    }
}

async function saveFieldMappingsPro(fields) {
    const formId = getFormId();
    if (!formId || fields.length === 0) return;

    const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root)
        ? wpApiSettings.root + 'chimpmatic-lite/v1/form/field'
        : '/wp-json/chimpmatic-lite/v1/form/field';

    for (const { field, value } of fields) {
        try {
            await fetch(restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings?.nonce || ''
                },
                body: JSON.stringify({ form_id: formId, field, value })
            });
        } catch (error) {
            console.error('Failed to save field mapping:', field, error);
        }
    }
}

// Tags Chip UI with auto-save
(function() {
    document.addEventListener('change', function(e) {
        if (e.target.type !== 'checkbox') return;
        const chip = e.target.closest('.cmatic-tag-chip');
        if (!chip) return;

        const checkbox = e.target;
        chip.classList.toggle('selected', checkbox.checked);

        // Extract tag name from checkbox name: wpcf7-mailchimp[labeltags][TAGNAME]
        const nameMatch = checkbox.name.match(/\[labeltags\]\[([^\]]+)\]/);
        if (!nameMatch) return;

        const tagName = nameMatch[1];
        const formId = getFormId();

        if (!formId) return;

        // Auto-save via unified REST API endpoint
        const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root)
            ? wpApiSettings.root + 'chimpmatic-lite/v1/form/field'
            : '/wp-json/chimpmatic-lite/v1/form/field';

        fetch(restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings?.nonce || ''
            },
            body: JSON.stringify({
                form_id: formId,
                field: 'labeltags.' + tagName,
                value: checkbox.checked
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Sync defaultChecked to prevent beforeunload warning
                checkbox.defaultChecked = checkbox.checked;
            }
        })
        .catch(error => {
            console.error('Tag auto-save failed:', error);
        });
    });
})();

// GDPR Dropdown Auto-Save (PRO feature)
(function() {
    document.addEventListener('change', function(e) {
        if (e.target.tagName !== 'SELECT') return;

        // Match GDPR dropdown: wpcf7-mailchimp[GDPRCustomValue1]
        const nameMatch = e.target.name.match(/wpcf7-mailchimp\[GDPRCustomValue(\d+)\]/);
        if (!nameMatch) return;

        const fieldIndex = nameMatch[1];
        const formId = getFormId();
        if (!formId) return;

        const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root)
            ? wpApiSettings.root + 'chimpmatic-lite/v1/form/field'
            : '/wp-json/chimpmatic-lite/v1/form/field';

        fetch(restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings?.nonce || ''
            },
            body: JSON.stringify({
                form_id: formId,
                field: 'GDPRCustomValue' + fieldIndex,
                value: e.target.value.trim()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Sync defaultValue to prevent beforeunload warning
                e.target.dataset.savedValue = e.target.value;
            }
        })
        .catch(error => {
            console.error('GDPR auto-save failed:', error);
        });
    });
})();

// Groups/Interests Dropdown Auto-Save (PRO feature)
(function() {
    document.addEventListener('change', function(e) {
        if (e.target.tagName !== 'SELECT') return;

        // Match Groups dropdown: wpcf7-mailchimp[ggCustomValue1]
        const nameMatch = e.target.name.match(/wpcf7-mailchimp\[ggCustomValue(\d+)\]/);
        if (!nameMatch) return;

        const fieldIndex = nameMatch[1];
        const formId = getFormId();
        if (!formId) return;

        const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root)
            ? wpApiSettings.root + 'chimpmatic-lite/v1/form/field'
            : '/wp-json/chimpmatic-lite/v1/form/field';

        fetch(restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings?.nonce || ''
            },
            body: JSON.stringify({
                form_id: formId,
                field: 'ggCustomValue' + fieldIndex,
                value: e.target.value.trim()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Sync defaultValue to prevent beforeunload warning
                e.target.dataset.savedValue = e.target.value;
            }
        })
        .catch(error => {
            console.error('Groups auto-save failed:', error);
        });
    });
})();

// Per-form Boolean Setting Toggle Auto-Save (sync_tags, checknotaddgroups, etc.)
(function() {
    // Global fields are handled by chimpmatic-lite.js via /settings/toggle endpoint
    const globalFields = ['debug', 'backlink', 'auto_update', 'telemetry'];

    document.addEventListener('change', function(e) {
        if (e.target.type !== 'checkbox') return;

        // Only handle checkboxes with data-field attribute
        const fieldName = e.target.dataset.field;
        if (!fieldName) return;

        // Skip global fields - handled by chimpmatic-lite.js
        if (globalFields.includes(fieldName)) return;

        // Handle toggle-target visibility (use class, not inline style - CF7 strips inline styles)
        const toggleTarget = e.target.dataset.toggleTarget;
        if (toggleTarget) {
            const target = document.querySelector(toggleTarget);
            if (target) {
                target.classList.toggle('cmatic-hidden', !e.target.checked);
            }
        }

        const formId = getFormId();
        if (!formId) return;

        const toggle = e.target.closest('.cmatic-toggle');
        if (toggle) {
            toggle.classList.add('is-saving');
        }

        const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root)
            ? wpApiSettings.root + 'cmatic/form/setting'
            : '/wp-json/cmatic/form/setting';

        fetch(restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings?.nonce || ''
            },
            body: JSON.stringify({
                form_id: formId,
                field: fieldName,
                value: e.target.checked
            })
        })
        .then(response => response.json())
        .then(data => {
            if (toggle) {
                toggle.classList.remove('is-saving');
            }
            if (data.success) {
                // Sync defaultChecked to prevent beforeunload warning
                e.target.defaultChecked = e.target.checked;
            } else {
                // Revert on failure
                e.target.checked = !e.target.checked;
                // Revert toggle-target visibility
                if (toggleTarget) {
                    const target = document.querySelector(toggleTarget);
                    if (target) {
                        target.classList.toggle('cmatic-hidden', !e.target.checked);
                    }
                }
                console.error('Setting save failed:', data.message);
            }
        })
        .catch(error => {
            if (toggle) {
                toggle.classList.remove('is-saving');
            }
            // Revert on error
            e.target.checked = !e.target.checked;
            // Revert toggle-target visibility
            if (toggleTarget) {
                const target = document.querySelector(toggleTarget);
                if (target) {
                    target.classList.toggle('cmatic-hidden', !e.target.checked);
                }
            }
            console.error('Setting auto-save failed:', error);
        });
    });
})();

// License Activation Handler
document.addEventListener('DOMContentLoaded', function() {
    const activationForm = document.getElementById('chimpmatic-activation-form');
    const deactivateBtn = document.getElementById('chimpmatic-deactivate-btn');

    function showFeedback(message, type = 'info') {
        const feedbackDiv = document.getElementById('chimpmatic-license-feedback');
        const messageP = document.getElementById('chimpmatic-license-feedback-message');

        if (!feedbackDiv || !messageP) return;

        messageP.textContent = message;

        if (type === 'success') {
            feedbackDiv.style.backgroundColor = '#d4edda';
            feedbackDiv.style.borderColor = '#c3e6cb';
            feedbackDiv.style.color = '#155724';
            feedbackDiv.style.border = '1px solid #c3e6cb';
        } else if (type === 'error') {
            feedbackDiv.style.backgroundColor = '#f8d7da';
            feedbackDiv.style.borderColor = '#f5c6cb';
            feedbackDiv.style.color = '#721c24';
            feedbackDiv.style.border = '1px solid #f5c6cb';
        } else {
            feedbackDiv.style.backgroundColor = '#d1ecf1';
            feedbackDiv.style.borderColor = '#bee5eb';
            feedbackDiv.style.color = '#0c5460';
            feedbackDiv.style.border = '1px solid #bee5eb';
        }

        feedbackDiv.style.display = 'block';
    }

    function hideFeedback() {
        const feedbackDiv = document.getElementById('chimpmatic-license-feedback');
        if (feedbackDiv) {
            feedbackDiv.style.display = 'none';
        }
    }

    if (activationForm) {
        activationForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const licenseKey = document.getElementById('license_key').value.trim();
            const productId = document.getElementById('product_id').value.trim();
            const activateBtn = document.getElementById('chimpmatic-activate-btn');

            if (!licenseKey || !productId) {
                showFeedback('Please enter both license key and product ID', 'error');
                return;
            }

            activateBtn.disabled = true;
            activateBtn.textContent = 'Activating...';
            hideFeedback();

            try {
                showFeedback('Checking activation status...', 'info');
                await new Promise(resolve => setTimeout(resolve, 500));

                const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) ?
                    wpApiSettings.root + 'chimpmatic/v1/license/activate' :
                    '/wp-json/chimpmatic/v1/license/activate';

                const response = await fetch(restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings?.nonce || ''
                    },
                    body: JSON.stringify({
                        license_key: licenseKey,
                        product_id: productId
                    }),
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success) {
                    if (result.was_deactivated) {
                        showFeedback('Site already activated... deactivating previous activation...', 'info');
                        await new Promise(resolve => setTimeout(resolve, 800));
                    }

                    showFeedback('Activating license...', 'info');
                    await new Promise(resolve => setTimeout(resolve, 500));

                    showFeedback('Success! License activated.', 'success');
                    await new Promise(resolve => setTimeout(resolve, 1000));

                    window.location.reload();
                } else {
                    showFeedback(result.message || 'Activation failed', 'error');
                    activateBtn.disabled = false;
                    activateBtn.textContent = 'Activate License';
                }
            } catch (error) {
                showFeedback('Error: ' + error.message, 'error');
                activateBtn.disabled = false;
                activateBtn.textContent = 'Activate License';
            }
        });
    }

    if (deactivateBtn) {
        deactivateBtn.addEventListener('click', async function(e) {
            if (!e.target.onclick || e.target.onclick.call(e.target) === false) {
                return;
            }

            const btn = e.target;
            btn.disabled = true;
            btn.textContent = 'Deactivating...';

            try {
                const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) ?
                    wpApiSettings.root + 'chimpmatic/v1/license/deactivate' :
                    '/wp-json/chimpmatic/v1/license/deactivate';

                const response = await fetch(restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings?.nonce || ''
                    },
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Deactivation failed: ' + (result.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.textContent = 'Deactivate License For This Site';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.textContent = 'Deactivate License For This Site';
            }
        });
    }
});

// CF7 Integration Page License Activation/Deactivation
(function() {
    'use strict';

    const activateBtn = document.getElementById('cmatic-cf7-activate-btn');
    const deactivateBtn = document.getElementById('cmatic-cf7-deactivate-btn');
    const feedbackDiv = document.getElementById('cmatic-cf7-license-feedback');

    let form = activateBtn ? activateBtn.closest('form') : (deactivateBtn ? deactivateBtn.closest('form') : null);

    if (!form && activateBtn) {
        let element = activateBtn.parentElement;
        while (element && element.tagName !== 'FORM') {
            element = element.parentElement;
        }
        form = element;
    }

    function showFeedback(message, type = 'info') {
        if (!feedbackDiv) return;

        feedbackDiv.textContent = message;
        feedbackDiv.style.display = 'block';

        if (type === 'success') {
            feedbackDiv.style.backgroundColor = '#d4edda';
            feedbackDiv.style.color = '#155724';
            feedbackDiv.style.border = '1px solid #c3e6cb';
        } else if (type === 'error') {
            feedbackDiv.style.backgroundColor = '#f8d7da';
            feedbackDiv.style.color = '#721c24';
            feedbackDiv.style.border = '1px solid #f5c6cb';
        } else {
            feedbackDiv.style.backgroundColor = '#d1ecf1';
            feedbackDiv.style.color = '#0c5460';
            feedbackDiv.style.border = '1px solid #bee5eb';
        }
    }

    function hideFeedback() {
        if (feedbackDiv) {
            feedbackDiv.style.display = 'none';
        }
    }

    if (activateBtn) {
        activateBtn.addEventListener('click', async function(e) {
            e.preventDefault();

            const licenseKey = document.getElementById('license_key').value.trim();
            const productId = document.getElementById('product_id').value.trim();

            if (!licenseKey || !productId) {
                showFeedback('Please enter both License Key and Product ID', 'error');
                return;
            }

            activateBtn.disabled = true;
            activateBtn.textContent = 'Activating...';
            hideFeedback();

            try {
                const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) ?
                    wpApiSettings.root + 'chimpmatic/v1/license/activate' :
                    '/wp-json/chimpmatic/v1/license/activate';

                const response = await fetch(restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings?.nonce || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        license_key: licenseKey,
                        product_id: productId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showFeedback('License activated successfully!', 'success');

                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showFeedback(result.message || 'Activation failed', 'error');
                    activateBtn.disabled = false;
                    activateBtn.textContent = 'Activate License';
                }
            } catch (error) {
                showFeedback('Network error: ' + error.message, 'error');
                activateBtn.disabled = false;
                activateBtn.textContent = 'Activate License';
            }
        });
    }

    if (deactivateBtn) {
        deactivateBtn.addEventListener('click', async function(e) {
            e.preventDefault();

            deactivateBtn.disabled = true;
            deactivateBtn.textContent = 'Deactivating...';
            hideFeedback();

            try {
                const restUrl = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) ?
                    wpApiSettings.root + 'chimpmatic/v1/license/deactivate' :
                    '/wp-json/chimpmatic/v1/license/deactivate';

                const response = await fetch(restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings?.nonce || ''
                    },
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success) {
                    showFeedback('License deactivated successfully!', 'success');

                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showFeedback(result.message || 'Deactivation failed', 'error');
                    deactivateBtn.disabled = false;
                    deactivateBtn.textContent = 'Deactivate License';
                }
            } catch (error) {
                showFeedback('Network error: ' + error.message, 'error');
                deactivateBtn.disabled = false;
                deactivateBtn.textContent = 'Deactivate License';
            }
        });
    }
})();

// Dependency Update Handler
(function() {
    'use strict';

    const updateBtn = document.querySelector('.cmatic-update-dependencies-btn');
    if (updateBtn) {
        updateBtn.addEventListener('click', function(e) {
            updateBtn.disabled = true;
            updateBtn.textContent = 'Updating...';
            updateBtn.style.opacity = '0.6';

            const dismissBtn = document.querySelector('.cmatic-dismiss-notice-btn');
            if (dismissBtn) {
                dismissBtn.disabled = true;
                dismissBtn.style.opacity = '0.6';
            }

            const noticeDiv = updateBtn.closest('.notice');
            if (noticeDiv) {
                const dismissX = noticeDiv.querySelector('.notice-dismiss');
                if (dismissX) {
                    dismissX.style.pointerEvents = 'none';
                    dismissX.style.opacity = '0.3';
                }
            }
        });
    }

    const dismissBtn = document.querySelector('.cmatic-dismiss-notice-btn');
    if (dismissBtn) {
        dismissBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const nonce = dismissBtn.getAttribute('data-nonce');
            if (!nonce) {
                return;
            }

            dismissBtn.disabled = true;
            dismissBtn.textContent = 'Dismissing...';

            const restUrl = getRestUrl() + REST_ENDPOINTS.NOTICES_DISMISS;

            fetch(restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings?.nonce || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({})
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const noticeDiv = dismissBtn.closest('.notice');
                    if (noticeDiv) {
                        noticeDiv.style.transition = 'opacity 0.3s';
                        noticeDiv.style.opacity = '0';
                        setTimeout(function() {
                            noticeDiv.remove();
                        }, 300);
                    }
                } else {
                    dismissBtn.disabled = false;
                    dismissBtn.textContent = 'Dismiss';
                }
            })
            .catch(error => {
                dismissBtn.disabled = false;
                dismissBtn.textContent = 'Dismiss';
            });
        });
    }
})();
