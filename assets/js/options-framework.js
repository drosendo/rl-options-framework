(function ($, window, document) {
	'use strict';

	const framework = window.rlFramework || {};

	const DEBUG_LEVELS = { error: 0, warn: 1, info: 2, debug: 3 };
	const configuredLevel = String(framework.debug_level || 'error').toLowerCase();
	const currentLevel = Object.prototype.hasOwnProperty.call(DEBUG_LEVELS, configuredLevel)
		? configuredLevel
		: 'error';
	const shouldSyncHistory = framework.sync_history === true;
	const useSwalFallback = framework.swal_fallback !== false;

	if (typeof window.rlFrameworkDebug === 'undefined') {
		window.rlFrameworkDebug = false;
	}

	const shouldLog = function(level) {
		const target = Object.prototype.hasOwnProperty.call(DEBUG_LEVELS, level) ? level : 'debug';
		return DEBUG_LEVELS[target] <= DEBUG_LEVELS[currentLevel] || window.rlFrameworkDebug === true;
	};

	const rlLog = function(...args) {
		if (shouldLog('debug')) {
			console.log('%c[RL Framework]', 'color: #4CAF50; font-weight: bold;', ...args);
		}
	};

	const rlInfo = function(...args) {
		if (shouldLog('info')) {
			console.info('%c[RL Framework INFO]', 'color: #2196F3; font-weight: bold;', ...args);
		}
	};

	const rlWarn = function(...args) {
		if (shouldLog('warn')) {
			console.warn('%c[RL Framework WARN]', 'color: #ff9800; font-weight: bold;', ...args);
		}
	};

	const rlError = function(...args) {
		if (shouldLog('error')) {
			console.error('%c[RL Framework ERROR]', 'color: #f44336; font-weight: bold;', ...args);
		}
	};

	function blurActiveElementForModal() {
		const active = document.activeElement;
		if (!active) {
			return;
		}

		const isFocusableInput = /^(INPUT|SELECT|TEXTAREA|BUTTON|A)$/i.test(active.tagName);
		if (isFocusableInput && typeof active.blur === 'function') {
			active.blur();
		}
	}

	function safeAlert(message) {
		if (useSwalFallback) {
			window.alert(message);
		}
	}

	function safeSwal(config, fallbackMessage) {
		try {
			if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
				blurActiveElementForModal();
				return Swal.fire(Object.assign({ returnFocus: false }, config));
			}
		} catch (err) {
			rlWarn('Swal.fire failed, using alert fallback.', err);
		}

		safeAlert(fallbackMessage || config.text || config.title || 'Action completed.');
		return Promise.resolve();
	}

	function closeSwalIfOpen() {
		if (typeof Swal !== 'undefined' && Swal && typeof Swal.close === 'function') {
			Swal.close();
		}
	}

	rlInfo('RL Options Framework Script Loaded', {
		debugLevel: currentLevel,
		syncHistory: shouldSyncHistory,
		swalFallback: useSwalFallback,
	});

	function initTooltips() {
		if (typeof tippy === 'undefined') {
			rlError('Tippy.js not loaded');
			return;
		}

		rlLog('Initializing Tippy.js tooltips...');
		
		tippy('[data-tippy-content]', {
			allowHTML: true,
			theme: 'light-border',
			placement: 'right',
			maxWidth: 350,
			arrow: true,
			interactive: false,
			zIndex: 99999
		});
		
		rlLog('✓ Tippy.js tooltips initialized');
	}

	function initColorPickers() {
		rlLog('Initializing color pickers...');
		$('.rl-color-field').wpColorPicker();
	}

	function syncDateTimeValue(targetId) {
		const hiddenInput = document.getElementById(targetId);
		if (!hiddenInput) {
			return;
		}

		const wrapper = hiddenInput.closest('.rl-datetime-field');
		if (!wrapper) {
			return;
		}

		const dateInput = wrapper.querySelector('.rl-datetime-date');
		const timeInput = wrapper.querySelector('.rl-datetime-time');
		if (!dateInput || !timeInput) {
			return;
		}

		const dateValue = String(dateInput.value || '').trim();
		const timeValue = String(timeInput.value || '').trim() || '00:00';
		hiddenInput.value = dateValue ? (dateValue + ' ' + timeValue) : '';
	}

	function initDateTimePickers() {
		const wrappers = Array.from(document.querySelectorAll('.rl-datetime-field'));
		if (!wrappers.length) {
			return;
		}

		rlLog('Initializing datetime pickers...', wrappers.length, 'fields found');

		wrappers.forEach((wrapper) => {
			const dateInput = wrapper.querySelector('.rl-datetime-date');
			const timeInput = wrapper.querySelector('.rl-datetime-time');
			const targetId = dateInput && dateInput.dataset ? dateInput.dataset.targetId : null;

			if (!dateInput || !timeInput || !targetId) {
				return;
			}

			if ($.fn && typeof $.fn.datepicker === 'function') {
				$(dateInput).datepicker({
					dateFormat: 'yy-mm-dd',
					changeMonth: true,
					changeYear: true,
					beforeShow: function () {
						setTimeout(function () {
							$('#ui-datepicker-div').css('z-index', 99999);
						}, 0);
					}
				});
			}

			const sync = function () {
				syncDateTimeValue(targetId);
			};

			dateInput.addEventListener('change', sync);
			dateInput.addEventListener('input', sync);
			timeInput.addEventListener('change', sync);
			timeInput.addEventListener('input', sync);

			sync();
		});
	}

	function initTabs() {
		const navWrapper = document.querySelector('.rl-options-page .nav-tab-wrapper');
		if (!navWrapper) {
			rlLog('No nav wrapper found, skipping tabs init');
			return;
		}

		const links = Array.from(navWrapper.querySelectorAll('a[data-rl-tab]'));
		const panels = Array.from(document.querySelectorAll('.rl-options-page .rl-tab-panel'));
		
		rlLog('Tabs initialized:', links.length, 'tabs found');
		rlLog('Tab slugs:', links.map(l => l.dataset.rlTab));

		function activateTab(slug, saveToHistory = true) {
			rlLog('Activating tab:', slug);
			
			links.forEach((link) => {
				const isActive = link.dataset.rlTab === slug;
				link.classList.toggle('nav-tab-active', isActive);
			});

			panels.forEach((panel) => {
				const isActive = panel.dataset.rlPanel === slug;
				panel.classList.toggle('is-active', isActive);
				
				// Open first accordion section when tab becomes active
				if (isActive) {
					openFirstAccordionInPanel(panel);
					openFirstSidebarInPanel(panel);
				}
			});

			if (saveToHistory) {
				// Save to localStorage for persistence after save
				try {
					localStorage.setItem('rl_framework_active_tab', slug);
					rlLog('Saved active tab to localStorage:', slug);
				} catch (err) {
					rlLog('LocalStorage not available:', err);
				}
				
				if (shouldSyncHistory) {
					const activeLink = links.find((link) => link.dataset.rlTab === slug);
					if (activeLink) {
						try {
							window.history.replaceState({}, document.title, activeLink.getAttribute('href'));
						} catch (err) {
							rlWarn('History sync blocked for tab navigation.', err);
						}
					}
				}
			}
		}

		links.forEach((link) => {
			link.addEventListener('click', (event) => {
				event.preventDefault();
				activateTab(link.dataset.rlTab);
			});
		});
		
		// Restore last active tab from localStorage or URL hash
		let initialTab = null;
		
		// Priority 1: URL hash
		if (window.location.hash) {
			const hash = window.location.hash.substring(1);
			if (links.some(l => l.dataset.rlTab === hash)) {
				initialTab = hash;
				rlLog('Restoring tab from URL hash:', initialTab);
			}
		}
		
		// Priority 2: localStorage (for persistence after save)
		if (!initialTab) {
			try {
				const savedTab = localStorage.getItem('rl_framework_active_tab');
				if (savedTab && links.some(l => l.dataset.rlTab === savedTab)) {
					initialTab = savedTab;
					rlLog('Restoring tab from localStorage:', initialTab);
				}
			} catch (err) {
				// Ignore
			}
		}
		
		// Activate the initial tab
		if (initialTab) {
			activateTab(initialTab, false);
		}
	}

	function initSidebarNavigation() {
		const sidebarLinks = document.querySelectorAll('.rl-sidebar-link');
		const sectionContents = document.querySelectorAll('.rl-section-content');

		if (!sidebarLinks.length || !sectionContents.length) {
			return;
		}

		sidebarLinks.forEach((link) => {
			link.addEventListener('click', (event) => {
				event.preventDefault();
				const sectionId = link.dataset.section;

				// Update sidebar active state
				sidebarLinks.forEach((l) => l.classList.remove('rl-sidebar-active'));
				link.classList.add('rl-sidebar-active');

				// Update content visibility
				sectionContents.forEach((content) => {
					const isActive = content.dataset.sectionContent === sectionId;
					content.classList.toggle('is-active', isActive);
				});

				// Update URL hash only when explicitly enabled.
				if (shouldSyncHistory && window.history && window.history.replaceState) {
					try {
						window.history.replaceState({}, document.title, link.getAttribute('href'));
					} catch (err) {
						rlWarn('History sync blocked for sidebar navigation.', err);
					}
				}
			});
		});

		// Handle direct hash navigation
		if (window.location.hash) {
			const hash = window.location.hash.substring(1);
			const targetLink = Array.from(sidebarLinks).find((l) => l.dataset.section === hash);
			if (targetLink) {
				targetLink.click();
			}
		}
	}
	
	function openFirstAccordionInPanel(panel) {
		const firstSection = panel.querySelector('.rl-section.is-accordion');
		if (!firstSection) {
			return;
		}
		
		const toggle = firstSection.querySelector('.rl-accordion-toggle');
		const content = firstSection.querySelector('.rl-accordion-content');
		
		if (!toggle || !content) {
			return;
		}
		
		// Only open if not already open
		if (!firstSection.classList.contains('is-open')) {
			firstSection.classList.add('is-open');
			toggle.setAttribute('aria-expanded', 'true');
			content.style.maxHeight = content.scrollHeight + 'px';
			rlLog('Opened first accordion section in panel:', panel.dataset.rlPanel);
		}
	}

	function openFirstSidebarInPanel(panel) {
		const sidebarLinks = panel.querySelectorAll('.rl-sidebar-link');
		const sectionContents = panel.querySelectorAll('.rl-section-content');

		if (!sidebarLinks.length || !sectionContents.length) {
			return;
		}

		const firstLink = sidebarLinks[0];
		const target = firstLink.dataset.section;

		sidebarLinks.forEach((link) => link.classList.remove('rl-sidebar-active'));
		firstLink.classList.add('rl-sidebar-active');

		sectionContents.forEach((content) => {
			const isActive = content.dataset.sectionContent === target;
			content.classList.toggle('is-active', isActive);
		});
	}

	function updateSidebarSectionsVisibility(panel) {
		if (!panel) {
			return;
		}

		const sidebarLinks = Array.from(panel.querySelectorAll('.rl-sidebar-link'));
		const sectionContents = Array.from(panel.querySelectorAll('.rl-section-content'));

		if (!sidebarLinks.length || !sectionContents.length) {
			return;
		}

		const isFieldVisible = (field) => {
			if (!field) {
				return false;
			}
			if (field.classList.contains('is-hidden')) {
				return false;
			}
			if (field.offsetParent === null && window.getComputedStyle(field).display === 'none') {
				return false;
			}
			return true;
		};

		sectionContents.forEach((sectionContent) => {
			const sectionId = sectionContent.dataset.sectionContent;
			const link = sidebarLinks.find((item) => item.dataset.section === sectionId);
			if (!link) {
				return;
			}

			const sectionElement = sectionContent.querySelector('.rl-section');
			const isSectionHidden = sectionElement && sectionElement.classList.contains('is-hidden');

			const fields = Array.from(sectionContent.querySelectorAll('.rl-field'));
			// A section has visible fields ONLY if it's not explicitly hidden itself
			const hasVisibleFields = !isSectionHidden && fields.some(isFieldVisible);

			sectionContent.style.display = hasVisibleFields ? '' : 'none';
			link.style.display = hasVisibleFields ? '' : 'none';
			if (link.parentElement) {
				link.parentElement.style.display = hasVisibleFields ? '' : 'none';
			}
		});

		const visibleLinks = sidebarLinks.filter((link) => link.style.display !== 'none');
		const currentActive = panel.querySelector('.rl-sidebar-link.rl-sidebar-active');

		if (!visibleLinks.length) {
			return;
		}

		if (!currentActive || currentActive.style.display === 'none') {
			sidebarLinks.forEach((link) => link.classList.remove('rl-sidebar-active'));
			const firstVisible = visibleLinks[0];
			firstVisible.classList.add('rl-sidebar-active');

			sectionContents.forEach((content) => {
				const isActive = content.dataset.sectionContent === firstVisible.dataset.section;
				content.classList.toggle('is-active', isActive);
			});
		}
	}

	function updateAllSidebarSectionsVisibility() {
		document.querySelectorAll('.rl-options-page .rl-tab-panel').forEach((panel) => {
			updateSidebarSectionsVisibility(panel);
		});
	}

	function escapeSelectorValue(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(value);
		}
		return String(value).replace(/([ #;?%&,.+*~\':"!^$\[\]()=>|\/])/g, '\\$1');
	}

	function clearFieldErrorFocus() {
		document.querySelectorAll('.rl-options-page .rl-field.rl-field-error-focus').forEach((field) => {
			field.classList.remove('rl-field-error-focus');
		});
	}

	function findFieldWrapper(fieldId) {
		if (!fieldId) {
			return null;
		}

		const escapedFieldId = escapeSelectorValue(fieldId);
		let wrapper = document.querySelector('.rl-options-page .rl-field[data-field-id="' + escapedFieldId + '"]');
		if (wrapper) {
			return wrapper;
		}

		const fieldName = framework.optionField + '[' + fieldId + ']';
		const input = document.querySelector('.rl-options-page [name="' + fieldName + '"]')
			|| document.querySelector('.rl-options-page [name="' + fieldName + '[]"]');

		return input ? input.closest('.rl-field') : null;
	}

	function focusInvalidField(fieldId, errorMeta) {
		if (!fieldId) {
			return;
		}

		const targetTab = errorMeta && errorMeta.tab_id ? errorMeta.tab_id : null;
		if (targetTab) {
			const tabLink = document.querySelector('.rl-options-page .nav-tab-wrapper a[data-rl-tab="' + escapeSelectorValue(targetTab) + '"]');
			if (tabLink && !tabLink.classList.contains('nav-tab-active')) {
				tabLink.click();
			}
		}

		let wrapper = findFieldWrapper(fieldId);
		if (!wrapper) {
			return;
		}

		const sectionContent = wrapper.closest('.rl-section-content');
		if (sectionContent && sectionContent.dataset.sectionContent) {
			const sectionId = sectionContent.dataset.sectionContent;
			const sidebarLink = document.querySelector('.rl-options-page .rl-sidebar-link[data-section="' + escapeSelectorValue(sectionId) + '"]');
			if (sidebarLink) {
				sidebarLink.click();
				wrapper = findFieldWrapper(fieldId) || wrapper;
			}
		}

		clearFieldErrorFocus();
		wrapper.classList.add('rl-field-error-focus');

		wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });

		const firstInput = wrapper.querySelector('input, select, textarea, button');
		if (firstInput && typeof firstInput.focus === 'function') {
			firstInput.focus({ preventScroll: true });
		}

		window.setTimeout(() => {
			wrapper.classList.remove('rl-field-error-focus');
		}, 2500);
	}

	function initAccordions() {
		const sections = document.querySelectorAll('.rl-section.is-accordion');
		sections.forEach((section) => {
			const toggle = section.querySelector('.rl-accordion-toggle');
			const content = section.querySelector('.rl-accordion-content');

			if (!toggle || !content) {
				return;
			}

			toggle.addEventListener('click', () => {
				const isOpen = section.classList.toggle('is-open');
				toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				if (isOpen) {
					content.style.maxHeight = content.scrollHeight + 'px';
				} else {
					content.style.maxHeight = null;
				}
			});

			// Open by default if a child field has validation errors in future.
			if (section.classList.contains('is-open')) {
				content.style.maxHeight = content.scrollHeight + 'px';
				toggle.setAttribute('aria-expanded', 'true');
			}
		});
	}
	
	function initFieldChangeListeners() {
		rlLog('Initializing field change listeners...');
		
		const form = document.querySelector('.rl-options-form');
		if (!form) {
			rlLog('No form found');
			return;
		}
		
		// Listen to all input changes
		form.addEventListener('change', function(e) {
			const target = e.target;
			const name = target.name;
			
			// Only log option fields
			if (name && name.includes(framework.optionField)) {
				const fieldId = name.match(/\[([^\]]+)\]/);
				const value = target.type === 'checkbox' ? target.checked : target.value;
				
				rlLog('📝 Field Changed:', {
					fieldId: fieldId ? fieldId[1] : name,
					type: target.type || target.tagName.toLowerCase(),
					newValue: value,
					name: name
				});
			}
		});
		
		rlLog('Field change listeners initialized');
	}

	function getFieldValue(form, fieldKey) {
		const fieldName = framework.optionField + '[' + fieldKey + ']';
		const inputs = form.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');

		if (!inputs.length) {
			return null;
		}

		const input = inputs[0];

		switch (input.type) {
			case 'checkbox':
				return input.checked;

			case 'radio': {
				const checked = form.querySelector('[name="' + fieldName + '"]:checked');
				return checked ? checked.value : null;
			}

			case 'select-multiple': {
				return Array.from(input.selectedOptions).map((option) => option.value);
			}

			case 'number':
				if (input.value === '') {
					return null;
				}
				return input.step && parseFloat(input.step) !== parseInt(input.step, 10)
					? parseFloat(input.value)
					: parseInt(input.value, 10);

			default:
				return input.value;
		}
	}

	function parseJsonAttribute(raw, fallback = null) {
		if (!raw) {
			return fallback;
		}
		try {
			return JSON.parse(raw);
		} catch (err) {
			return fallback;
		}
	}

	function getCurrentFormState(form) {
		const state = {};
		if (!form) {
			return state;
		}

		const fields = Array.from(form.querySelectorAll('[name^="' + framework.optionField + '["]'));
		fields.forEach((input) => {
			const match = String(input.name || '').match(/\[([^\]]+)\]/);
			if (!match) {
				return;
			}
			const key = match[1];
			state[key] = getFieldValue(form, key);
		});

		return state;
	}

	function clearInlineError(wrapper) {
		if (!wrapper) {
			return;
		}
		wrapper.classList.remove('rl-field-inline-error-active');
		const node = wrapper.querySelector('.rl-field-inline-error');
		if (node) {
			node.textContent = '';
			node.style.display = 'none';
		}
	}

	function setInlineError(wrapper, message) {
		if (!wrapper) {
			return;
		}
		const node = wrapper.querySelector('.rl-field-inline-error');
		if (!node) {
			return;
		}
		wrapper.classList.add('rl-field-inline-error-active');
		node.textContent = String(message || '').trim();
		node.style.display = node.textContent ? '' : 'none';
	}

	function collectDependencyFields(wrapper, providerConfig) {
		const watched = new Set();
		const dependsOn = parseJsonAttribute(wrapper.dataset.dependsOn, []);
		if (Array.isArray(dependsOn)) {
			dependsOn.forEach((key) => {
				if (typeof key === 'string' && key) {
					watched.add(key);
				}
			});
		}

		const params = providerConfig && providerConfig.params && typeof providerConfig.params === 'object'
			? providerConfig.params
			: {};
		Object.keys(params).forEach((paramName) => {
			const fieldRef = params[paramName];
			if (typeof fieldRef === 'string' && fieldRef) {
				watched.add(fieldRef);
			}
		});

		return Array.from(watched);
	}

	function applyProviderOptions(wrapper, options) {
		if (!wrapper || !Array.isArray(options)) {
			return;
		}

		const select = wrapper.querySelector('select');
		if (!select) {
			return;
		}

		const previousValue = select.value;
		const hasEmptyOption = Array.from(select.options).some((o) => String(o.value) === '');
		select.innerHTML = '';

		if (hasEmptyOption) {
			const emptyOption = document.createElement('option');
			emptyOption.value = '';
			emptyOption.textContent = '';
			select.appendChild(emptyOption);
		}

		options.forEach((item) => {
			if (!item || typeof item !== 'object') {
				return;
			}
			const value = String(item.value || '');
			if (!value) {
				return;
			}
			const label = String(item.label || value);
			const option = document.createElement('option');
			option.value = value;
			option.textContent = label;
			select.appendChild(option);
		});

		if (previousValue && Array.from(select.options).some((o) => o.value === previousValue)) {
			select.value = previousValue;
		} else if (hasEmptyOption) {
			select.value = '';
		}
	}

	function requestFieldOptions(form, wrapper) {
		const provider = parseJsonAttribute(wrapper.dataset.optionsProvider, null);
		if (!provider || typeof provider !== 'object') {
			return Promise.resolve([]);
		}

		const fieldId = wrapper.dataset.fieldId || '';
		if (!fieldId) {
			return Promise.resolve([]);
		}

		const payload = {
			action: provider.action || framework.provider_action,
			nonce: framework.nonce,
			field_id: fieldId,
			current_state: getCurrentFormState(form),
			provider: provider,
		};

		return $.post(framework.ajax_url, payload)
			.then((response) => {
				const data = (response && response.data) ? response.data : {};
				if (response && response.success && Array.isArray(data.options)) {
					return data.options;
				}
				return [];
			})
			.catch(() => []);
	}

	function validateFieldInline(form, wrapper) {
		if (!form || !wrapper) {
			return Promise.resolve(true);
		}

		const fieldId = wrapper.dataset.fieldId || '';
		if (!fieldId) {
			return Promise.resolve(true);
		}

		const payload = {};
		Array.from(new FormData(form).entries()).forEach(([key, value]) => {
			if (key !== 'action' && key !== 'nonce') {
				payload[key] = value;
			}
		});

		payload.action = framework.validate_action;
		payload.nonce = framework.nonce;
		payload.field_id = fieldId;

		return $.post(framework.ajax_url, payload).then((response) => {
			if (response && response.success) {
				clearInlineError(wrapper);
				return true;
			}

			const message = response && response.data && response.data.message
				? response.data.message
				: 'Invalid value.';
			setInlineError(wrapper, message);
			return false;
		}).catch(() => true);
	}

	function initDependencyProviders() {
		const form = document.querySelector('.rl-options-page form');
		if (!form) {
			return;
		}

		const providerFields = Array.from(form.querySelectorAll('.rl-field.has-options-provider'));
		if (!providerFields.length) {
			return;
		}

		providerFields.forEach((wrapper) => {
			const provider = parseJsonAttribute(wrapper.dataset.optionsProvider, {});
			const watched = collectDependencyFields(wrapper, provider);

			const refresh = () => {
				requestFieldOptions(form, wrapper).then((options) => {
					if (Array.isArray(options) && options.length) {
						applyProviderOptions(wrapper, options);
					}
					validateFieldInline(form, wrapper);
				});
			};

			watched.forEach((fieldKey) => {
				const fieldName = framework.optionField + '[' + fieldKey + ']';
				const deps = form.querySelectorAll('[name="' + fieldName + '"] , [name="' + fieldName + '[]"]');
				deps.forEach((input) => {
					input.addEventListener('change', refresh);
					input.addEventListener('input', refresh);
				});
			});

			const ownInputs = wrapper.querySelectorAll('input,select,textarea');
			ownInputs.forEach((input) => {
				input.addEventListener('change', () => validateFieldInline(form, wrapper));
				input.addEventListener('input', () => validateFieldInline(form, wrapper));
			});

			refresh();
		});
	}

	function fillSelectOptions(select, options, preserveValue = true) {
		if (!select) {
			return;
		}
		const previous = preserveValue ? select.value : '';
		select.innerHTML = '';
		const empty = document.createElement('option');
		empty.value = '';
		empty.textContent = '';
		select.appendChild(empty);

		(options || []).forEach((item) => {
			if (!item || typeof item !== 'object' || !item.value) {
				return;
			}
			const opt = document.createElement('option');
			opt.value = String(item.value);
			opt.textContent = String(item.label || item.value);
			select.appendChild(opt);
		});

		if (previous && Array.from(select.options).some((o) => o.value === previous)) {
			select.value = previous;
		}
	}

	function loadGeoOptions(endpointPath) {
		const base = String(framework.rest_base || '');
		if (!base) {
			return Promise.resolve([]);
		}
		const url = base.replace(/\/$/, '') + endpointPath;
		return $.getJSON(url).then((data) => (Array.isArray(data) ? data : [])).catch(() => []);
	}

	function initGeoFieldTypes() {
		const form = document.querySelector('.rl-options-page form');
		if (!form) {
			return;
		}

		const stateFields = Array.from(form.querySelectorAll('select.rl-geo-state[data-country-field]'));
		stateFields.forEach((stateSelect) => {
			const fieldKey = stateSelect.getAttribute('data-country-field') || '';
			if (!fieldKey) {
				return;
			}
			const countryName = framework.optionField + '[' + fieldKey + ']';
			const countryInput = form.querySelector('[name="' + countryName + '"]');
			if (!countryInput) {
				return;
			}

			const refresh = () => {
				const code = String(countryInput.value || '').toUpperCase();
				if (!code) {
					fillSelectOptions(stateSelect, []);
					return;
				}
				loadGeoOptions('/countries/' + encodeURIComponent(code) + '/subdivisions').then((options) => {
					fillSelectOptions(stateSelect, options);
				});
			};

			countryInput.addEventListener('change', refresh);
			countryInput.addEventListener('input', refresh);
			refresh();
		});

		const cityFields = Array.from(form.querySelectorAll('select.rl-geo-city'));
		cityFields.forEach((citySelect) => {
			const countryField = citySelect.getAttribute('data-country-field') || '';
			const subdivisionField = citySelect.getAttribute('data-subdivision-field') || '';
			const staticCountry = String(citySelect.getAttribute('data-country') || '').toUpperCase();

			const refresh = () => {
				let country = staticCountry;
				if (countryField) {
					const cInput = form.querySelector('[name="' + framework.optionField + '[' + countryField + ']"]');
					country = cInput ? String(cInput.value || '').toUpperCase() : country;
				}
				if (!country) {
					fillSelectOptions(citySelect, []);
					return;
				}

				let subdivision = '';
				if (subdivisionField) {
					const sInput = form.querySelector('[name="' + framework.optionField + '[' + subdivisionField + ']"]');
					subdivision = sInput ? String(sInput.value || '') : '';
				}

				const query = subdivision ? ('?subdivision=' + encodeURIComponent(subdivision)) : '';
				loadGeoOptions('/countries/' + encodeURIComponent(country) + '/municipalities' + query).then((options) => {
					fillSelectOptions(citySelect, options);
				});
			};

			if (countryField) {
				const cInput = form.querySelector('[name="' + framework.optionField + '[' + countryField + ']"]');
				if (cInput) {
					cInput.addEventListener('change', refresh);
					cInput.addEventListener('input', refresh);
				}
			}
			if (subdivisionField) {
				const sInput = form.querySelector('[name="' + framework.optionField + '[' + subdivisionField + ']"]');
				if (sInput) {
					sInput.addEventListener('change', refresh);
					sInput.addEventListener('input', refresh);
				}
			}

			refresh();
		});

		const groupFields = Array.from(form.querySelectorAll('.rl-country-state-city-field'));
		groupFields.forEach((group) => {
			const countrySelect = group.querySelector('.rl-csc-country');
			const stateSelect = group.querySelector('.rl-csc-state');
			const citySelect = group.querySelector('.rl-csc-city');
			if (!countrySelect || !stateSelect || !citySelect) {
				return;
			}

			const refreshStates = () => {
				const country = String(countrySelect.value || '').toUpperCase();
				if (!country) {
					fillSelectOptions(stateSelect, []);
					fillSelectOptions(citySelect, []);
					return;
				}
				loadGeoOptions('/countries/' + encodeURIComponent(country) + '/subdivisions').then((options) => {
					fillSelectOptions(stateSelect, options);
					refreshCities();
				});
			};

			const refreshCities = () => {
				const country = String(countrySelect.value || '').toUpperCase();
				if (!country) {
					fillSelectOptions(citySelect, []);
					return;
				}
				const subdivision = String(stateSelect.value || '');
				const query = subdivision ? ('?subdivision=' + encodeURIComponent(subdivision)) : '';
				loadGeoOptions('/countries/' + encodeURIComponent(country) + '/municipalities' + query).then((options) => {
					fillSelectOptions(citySelect, options);
				});
			};

			countrySelect.addEventListener('change', refreshStates);
			stateSelect.addEventListener('change', refreshCities);
			refreshStates();
		});
	}

	function evaluateSingleCondition(form, condition) {
		const fieldValue = getFieldValue(form, condition.field);
		const compareValue = condition.value;
		const operator = (condition.operator || 'equals').toLowerCase();

		switch (operator) {
			case 'equals':
			case '==':
				if (fieldValue === true && compareValue === '1') return true;
				if (fieldValue === false && compareValue === '0') return true;
				return String(fieldValue) === String(compareValue);
			case 'not_equals':
			case '!=':
				if (fieldValue === true && compareValue === '1') return false;
				if (fieldValue === false && compareValue === '0') return false;
				return String(fieldValue) !== String(compareValue);
			case '>':
			case 'greater_than':
				return Number(fieldValue) > Number(compareValue);
			case '>=':
			case 'greater_than_or_equal':
				return Number(fieldValue) >= Number(compareValue);
			case '<':
			case 'less_than':
				return Number(fieldValue) < Number(compareValue);
			case '<=':
			case 'less_than_or_equal':
				return Number(fieldValue) <= Number(compareValue);
			case 'in':
				return Array.isArray(compareValue)
					? compareValue.includes(fieldValue)
					: fieldValue === compareValue;
			case 'not_in':
				return Array.isArray(compareValue)
					? !compareValue.includes(fieldValue)
					: fieldValue !== compareValue;
			case 'truthy':
				return Boolean(fieldValue);
			case 'falsy':
				return !fieldValue;
			default:
				return true;
		}
	}

	/**
	 * Normalize a raw conditions value into a group object {relation, rules}.
	 * Handles:
	 *   - Already-normalized groups: {relation, rules}
	 *   - Flat arrays of rules:       [{field, operator, value}, ...]
	 *   - Single rule objects:         {field, operator, value}
	 */
	function normalizeConditions(raw) {
		if (!raw) return null;

		// Already a group object
		if (raw.rules && Array.isArray(raw.rules)) {
			return raw;
		}

		// Flat array — treat each element as a rule (AND logic)
		if (Array.isArray(raw)) {
			if (!raw.length) return null;
			return { relation: 'AND', rules: raw };
		}

		// Single rule object
		if (raw.field) {
			return { relation: 'AND', rules: [raw] };
		}

		return null;
	}

	function evaluateConditionGroup(form, group) {
		if (!group || !group.rules || !group.rules.length) {
			return true;
		}

		const relation = group.relation || 'AND';

		for (let i = 0; i < group.rules.length; i++) {
			const rule = group.rules[i];
			let result = false;

			if (rule.relation) {
				result = evaluateConditionGroup(form, rule);
			} else if (rule.field) {
				result = evaluateSingleCondition(form, rule);
			}

			if (relation === 'AND' && !result) {
				return false;
			}
			if (relation === 'OR' && result) {
				return true;
			}
		}

		return relation === 'AND';
	}

	function evaluateConditions(form, fieldWrapper) {
		if (!fieldWrapper.dataset.conditions) {
			return;
		}

		let raw;
		try {
			raw = JSON.parse(fieldWrapper.dataset.conditions);
		} catch (err) {
			return;
		}

		const conditions = normalizeConditions(raw);
		if (!conditions) {
			return;
		}

		const result = evaluateConditionGroup(form, conditions);
		fieldWrapper.classList.toggle('is-hidden', !result);
	}

	function initConditions() {
		const form = document.querySelector('.rl-options-page form');
		if (!form) {
			return;
		}

		const conditionalFields = Array.from(form.querySelectorAll('.rl-field.has-conditions, .rl-section.has-conditions'));
		if (!conditionalFields.length) {
			return;
		}

		const dependenciesMap = new Map();

		function extractFieldsFromGroup(group, set) {
			if (!group || !group.rules) return;
			group.rules.forEach((rule) => {
				if (rule.relation) {
					extractFieldsFromGroup(rule, set);
				} else if (rule.field) {
					set.add(rule.field);
				}
			});
		}

		conditionalFields.forEach((fieldWrapper) => {
			let raw;
			try {
				raw = JSON.parse(fieldWrapper.dataset.conditions);
			} catch (err) {
				return;
			}

			const conditions = normalizeConditions(raw);
			if (!conditions) return;

			const fieldKeys = new Set();
			extractFieldsFromGroup(conditions, fieldKeys);

			fieldKeys.forEach((key) => {
				if (!dependenciesMap.has(key)) {
					dependenciesMap.set(key, []);
				}
				dependenciesMap.get(key).push(fieldWrapper);
			});
		});

		function runEvaluation() {
			conditionalFields.forEach((fieldWrapper) => evaluateConditions(form, fieldWrapper));
			updateAllSidebarSectionsVisibility();
		}

		dependenciesMap.forEach((fields, fieldKey) => {
			const fieldName = framework.optionField + '[' + fieldKey + ']';
			const inputs = form.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');

			inputs.forEach((input) => {
				input.addEventListener('change', runEvaluation);
				input.addEventListener('input', runEvaluation);
				if (input.type === 'checkbox' || input.type === 'radio') {
					input.addEventListener('click', runEvaluation);
				}
			});
		});

		runEvaluation();
	}

	function initTabConditions() {
		const form = document.querySelector('.rl-options-page form');
		if (!form) {
			rlLog('No form found for tab conditions');
			return;
		}

		const navWrapper = document.querySelector('.rl-options-page .nav-tab-wrapper');
		if (!navWrapper) {
			rlLog('No nav wrapper found for tab conditions');
			return;
		}

		const tabLinks = Array.from(navWrapper.querySelectorAll('a[data-rl-tab]'));
		const tabPanels = Array.from(document.querySelectorAll('.rl-options-page .rl-tab-panel'));
		
		// Parse tab conditions from data attributes
		const tabConditions = {};
		tabLinks.forEach((link) => {
			const conditionsAttr = link.getAttribute('data-tab-conditions');
			if (conditionsAttr) {
				try {
					const raw = JSON.parse(conditionsAttr);
					const normalized = normalizeConditions(raw);
					if (normalized) {
						tabConditions[link.dataset.rlTab] = normalized;
					}
				} catch (err) {
					rlError('Failed to parse tab conditions for', link.dataset.rlTab, err);
				}
			}
		});

		if (Object.keys(tabConditions).length === 0) {
			rlLog('No tab conditions found');
			return;
		}

		rlLog('Tab conditions initialized:', Object.keys(tabConditions));

		// Extract dependencies for tabs to listen to changes
		const tabDependenciesMap = new Map();
		function extractFieldsFromGroup(group, set) {
			if (!group || !group.rules) return;
			group.rules.forEach((rule) => {
				if (rule.relation) {
					extractFieldsFromGroup(rule, set);
				} else if (rule.field) {
					set.add(rule.field);
				}
			});
		}

		Object.values(tabConditions).forEach((conditions) => {
			const fieldKeys = new Set();
			extractFieldsFromGroup(conditions, fieldKeys);
			
			fieldKeys.forEach((key) => {
				if (!tabDependenciesMap.has(key)) {
					tabDependenciesMap.set(key, true);
				}
			});
		});

		function evaluateTabConditions() {
			tabLinks.forEach((link) => {
				const tabSlug = link.dataset.rlTab;
				const conditions = tabConditions[tabSlug];
				
				if (!conditions) {
					return;
				}

				// Check all conditions recursively
				const allConditionsMet = evaluateConditionGroup(form, conditions);

				// Show/hide tab link
				if (allConditionsMet) {
					link.style.display = '';
					link.classList.remove('rl-tab-hidden');
				} else {
					link.style.display = 'none';
					link.classList.add('rl-tab-hidden');
					
					// If this tab is currently active, switch to the first visible tab
					if (link.classList.contains('nav-tab-active')) {
						const firstVisibleTab = tabLinks.find(l => l.style.display !== 'none');
						if (firstVisibleTab) {
							firstVisibleTab.click();
						}
					}
				}

				// Also hide/show the corresponding panel
				const panel = tabPanels.find(p => p.dataset.rlPanel === tabSlug);
				if (panel) {
					if (allConditionsMet) {
						panel.style.display = '';
					} else {
						panel.style.display = 'none';
					}
				}
			});

			updateAllSidebarSectionsVisibility();
		}


		// Attach listeners to tab dependencies

		tabDependenciesMap.forEach((_, fieldKey) => {
			const fieldName = framework.optionField + '[' + fieldKey + ']';
			const inputs = form.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');

			inputs.forEach((input) => {
				input.addEventListener('change', evaluateTabConditions);
				input.addEventListener('input', evaluateTabConditions);
				if (input.type === 'checkbox' || input.type === 'radio') {
					input.addEventListener('click', evaluateTabConditions);
				}
			});
		});

		evaluateTabConditions();
	}

	$(document).ready(function () {
		rlLog('='.repeat(70));
		rlLog('🚀 RL Options Framework Initializing...');
		rlLog('Debug mode:', window.rlFrameworkDebug === true ? 'ENABLED ✅' : 'DISABLED ❌');
		rlLog('To enable debug: window.rlFrameworkDebug = true');
		
		initTooltips();
		initColorPickers();
		initDateTimePickers();
		initTabs();
		initSidebarNavigation();
		initAccordions();
		initConditions();
		initGeoFieldTypes();
		initDependencyProviders();
		initTabConditions();
		updateAllSidebarSectionsVisibility();
		
		// Open first accordion section in the active panel on page load
		const activePanel = document.querySelector('.rl-tab-panel.is-active');
		if (activePanel) {
			openFirstAccordionInPanel(activePanel);
		}
		
		// Add AJAX form submission
		const $form = $('.rl-options-page form');
		if ($form.length) {
			rlLog('✓ Form found, setting up AJAX submission');
			rlLog('Form ID:', $form.attr('id'));
			rlLog('Form fields count:', $form.find('input, select, textarea').length);
			
			// Remove default submit handler
			$form.off('submit').on('submit', function(e) {
				e.preventDefault();
				$(this).trigger('rl:validated-submit');
			}).off('rl:validated-submit').on('rl:validated-submit', function() {
				
				rlLog('='.repeat(70));
				rlLog('📝 FORM SUBMISSION TRIGGERED');
				rlLog('='.repeat(70));
				
				const formData = $(this).serialize();
				const $submitBtn = $(this).find('button[type="submit"], input[type="submit"]');
				
				// Log form data details
				rlLog('Form Details:');
				rlLog('  - AJAX URL:', rlFramework.ajax_url);
				rlLog('  - Form data length:', formData.length, 'bytes');
				rlLog('  - Option field prefix:', rlFramework.optionField);
				
				// Parse and log specific fields
				const formParams = new URLSearchParams(formData);
				const optionField = rlFramework.optionField;
				
				rlLog('Fields Being Saved:');
				let fieldCount = 0;
				for (const [key, value] of formParams.entries()) {
					// Only log option fields, not WordPress nonces etc
					if (key.includes(optionField)) {
						const cleanKey = key.replace(optionField + '[', '').replace(']', '');
						rlLog(`  ✓ ${cleanKey} = "${value}"`);
						fieldCount++;
					}
				}
				rlLog(`Total option fields: ${fieldCount}`);
				
				// Disable submit button
				$submitBtn.prop('disabled', true);
				rlLog('Submit button disabled');
				
				// Show loading state if SweetAlert is available.
				if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
					safeSwal({
						title: 'Saving...',
						text: 'Please wait while your settings are being saved.',
						allowOutsideClick: false,
						allowEscapeKey: false,
						showConfirmButton: false,
						didOpen: () => {
							Swal.showLoading();
						}
					});
				}
				rlLog('Loading dialog shown');
				rlLog('Sending AJAX request...');
				
				$.ajax({
					url: rlFramework.ajax_url,
					type: 'POST',
					data: formData + '&action=' + rlFramework.ajax_action + '&nonce=' + rlFramework.nonce,
					success: function(response) {
						rlLog('='.repeat(70));
						rlLog('✅ AJAX REQUEST SUCCESSFUL');
						rlLog('Response:', response);
						rlLog('Response type:', typeof response);
						
						$submitBtn.prop('disabled', false);
						
						// Handle response - it might be a string or object
						let data = response;
						if (typeof response === 'string') {
							try {
								data = JSON.parse(response);
							} catch (e) {
								rlError('Failed to parse response as JSON:', e);
								rlError('Raw response:', response);
								
								closeSwalIfOpen();
								safeSwal({
									icon: 'error',
									title: 'Error',
									text: 'Invalid server response. Check console for details.',
									confirmButtonText: 'OK'
								}, 'Invalid server response. Check browser console for details.');
								rlLog('='.repeat(70));
								return;
							}
						}
						
						rlLog('Parsed data:', data);
						rlLog('data.success:', data.success);
						rlLog('data.data:', data.data);
						
						if (data.success) {
							const message = (data.data && data.data.message) || 'Settings saved successfully.';
							const saved = (data.data && data.data.saved) || {};
							
							rlLog('✓ Settings saved successfully');
							rlLog('  - Message:', message);
							rlLog('  - Saved fields:', saved);
							
							closeSwalIfOpen();
							safeSwal({
								icon: 'success',
								title: 'Success',
								text: message,
								confirmButtonText: 'OK'
							}, message);
						} else {
							const message = (data.data && data.data.message) || (data.message) || 'Failed to save settings.';
							const fieldErrors = (data.data && data.data.field_errors) || {};
							const errorFieldIds = Object.keys(fieldErrors);
							const firstErrorFieldId = errorFieldIds.length ? errorFieldIds[0] : null;
							const firstErrorMeta = firstErrorFieldId ? fieldErrors[firstErrorFieldId] : null;
							
							rlError('❌ Save failed');
							rlError('  - Message:', message);
							rlError('  - Full response:', data);
							
							closeSwalIfOpen();
							safeSwal({
								icon: 'error',
								title: 'Error',
								text: message,
								confirmButtonText: 'OK'
							}, message).then(() => {
								if (firstErrorFieldId) {
									focusInvalidField(firstErrorFieldId, firstErrorMeta || {});
								}
							});
						}
						rlLog('='.repeat(70));
					},
					error: function(xhr, status, error) {
						rlLog('='.repeat(70));
						rlError('❌ AJAX REQUEST FAILED');
						rlError('Status:', status);
						rlError('Error:', error);
						rlError('XHR Status:', xhr.status);
						rlError('XHR Response:', xhr.responseText);
						rlError('Full XHR:', xhr);
						rlLog('='.repeat(70));
						
						$submitBtn.prop('disabled', false);
						
						const responseText = (xhr && typeof xhr.responseText === 'string') ? xhr.responseText.trim() : '';
						const isSecurityError = xhr && xhr.status === 403;
						const isNonceFailure = responseText === '-1' || (responseText && responseText.toLowerCase().includes('security check failed'));

						closeSwalIfOpen();
						safeSwal({
							icon: 'error',
							title: 'Error',
							text: (isSecurityError || isNonceFailure)
								? 'Security check failed or session expired. Please refresh the page and try again.'
								: 'An error occurred while saving settings. Check console for details.',
							confirmButtonText: 'OK'
						}, 'An error occurred while saving settings.');
					},
					complete: function() {
						rlLog('AJAX request completed');
					}
				});
				
				return false;
			});
			
			// Add debugging for form field changes
			$form.find('select, input, textarea').on('change', function() {
				const $field = $(this);
				const fieldName = $field.attr('name');
				const fieldValue = $field.val();
				const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();
				
				rlLog('📝 Field Changed:');
				rlLog('  - Name:', fieldName);
				rlLog('  - Type:', fieldType);
				rlLog('  - New Value:', fieldValue);
				rlLog('  - Element:', $field[0]);
			});
			
			rlLog('✓ Field change listeners attached');
		} else {
			rlError('❌ WARNING: Form not found (.rl-options-page form)');
			rlLog('Available forms:', $('form').length);
		}
		
		// Initialize WordPress Media Uploader for image fields
		let mediaUploader;
		
		$(document).on('click', '.rl-upload-image-button', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const inputId = $button.data('input-id');
			const $input = $('#' + inputId);
			const $preview = $button.siblings('.rl-image-preview');
			const $removeBtn = $button.siblings('.rl-remove-image-button');
			
			// If the uploader object has already been created, reopen it
			if (mediaUploader) {
				mediaUploader.open();
				return;
			}
			
			// Create the media uploader
			mediaUploader = wp.media({
				title: 'Choose Image',
				button: {
					text: 'Use this image'
				},
				multiple: false
			});
			
			// When an image is selected, update the input and preview
			mediaUploader.on('select', function() {
				const attachment = mediaUploader.state().get('selection').first().toJSON();
				
				$input.val(attachment.url).trigger('change');
				$preview.html('<img src="' + attachment.url + '" style="max-width:200px;height:auto;display:block;" />');
				$removeBtn.show();
				
				rlLog('Image selected:', attachment.url);
			});
			
			// Open the uploader
			mediaUploader.open();
		});
		
		// Handle remove image button
		$(document).on('click', '.rl-remove-image-button', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const inputId = $button.data('input-id');
			const $input = $('#' + inputId);
			const $preview = $button.siblings('.rl-image-preview');
			
			$input.val('').trigger('change');
			$preview.html('');
			$button.hide();
			
			rlLog('Image removed');
		});
		
		rlLog('✓ Media uploader initialized');
		
		rlLog('✅ RL Options Framework Initialized Successfully');
		rlLog('='.repeat(70));
	});
})(jQuery, window, document);
