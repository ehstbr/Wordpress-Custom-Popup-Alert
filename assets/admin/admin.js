(function ($) {
	'use strict';

	var config = window.wccpaAdmin || {};
	var definitions = config.conditions || {};
	var i18n = config.i18n || {};
	var hidden = document.getElementById('wccpa-rules-json');
	var host = document.getElementById('wccpa-rule-builder');
	var state = null;
	var requestSequence = 0;

	function parseState() {
		try {
			var parsed = JSON.parse(hidden.value || '{}');
			if (parsed && parsed.kind === 'group' && Array.isArray(parsed.children)) {
				return parsed;
			}
		} catch (error) {
			// Start with a clean tree when legacy data cannot be decoded.
		}
		return { kind: 'group', relation: 'AND', children: [] };
	}

	function sync() {
		if (hidden) {
			hidden.value = JSON.stringify(state);
		}
	}

	function element(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (typeof text !== 'undefined') {
			node.textContent = text;
		}
		return node;
	}

	function button(text, className) {
		var node = element('button', 'button ' + (className || ''), text);
		node.type = 'button';
		return node;
	}

	function defaultCondition() {
		var keys = Object.keys(definitions);
		if (!keys.length) {
			return null;
		}
		var type = keys[0];
		var definition = definitions[type];
		var operators = Object.keys(definition.operators || {});
		var extra = {};
		(definition.extra || []).forEach(function (field) {
			extra[field.key] = typeof field.default === 'undefined' ? '' : field.default;
		});
		return {
			kind: 'condition',
			type: type,
			operator: operators[0] || 'equals',
			value: definition.multiple ? [] : (definition.value_type === 'boolean' ? true : ''),
			labels: {},
			extra: extra
		};
	}

	function resetCondition(node, type) {
		var definition = definitions[type];
		node.type = type;
		if (!definition) {
			return;
		}
		var operators = Object.keys(definition.operators || {});
		node.operator = operators[0] || 'equals';
		node.value = definition.multiple ? [] : (definition.value_type === 'boolean' ? true : '');
		node.labels = {};
		node.extra = {};
		(definition.extra || []).forEach(function (field) {
			node.extra[field.key] = typeof field.default === 'undefined' ? '' : field.default;
		});
	}

	function groupedTypeSelect(node) {
		var select = element('select', 'wccpa-condition-type');
		select.setAttribute('aria-label', i18n.selectCondition || 'Condition');
		var groups = {};
		Object.keys(definitions).forEach(function (key) {
			var definition = definitions[key];
			groups[definition.group] = groups[definition.group] || [];
			groups[definition.group].push(definition);
		});
		Object.keys(groups).forEach(function (groupName) {
			var optgroup = document.createElement('optgroup');
			optgroup.label = groupName;
			groups[groupName].forEach(function (definition) {
				var option = document.createElement('option');
				option.value = definition.key;
				option.textContent = definition.label;
				option.selected = node.type === definition.key;
				optgroup.appendChild(option);
			});
			select.appendChild(optgroup);
		});
		if (!definitions[node.type]) {
			var unavailable = document.createElement('option');
			unavailable.value = node.type || '';
			unavailable.textContent = (node.type || i18n.selectCondition || 'Condition') + ' — ' + (i18n.unavailableSuffix || 'unavailable');
			unavailable.selected = true;
			select.insertBefore(unavailable, select.firstChild);
		}
		select.addEventListener('change', function () {
			resetCondition(node, select.value);
			sync();
			render();
		});
		return select;
	}

	function operatorSelect(node, definition) {
		var select = element('select', 'wccpa-condition-operator');
		Object.keys((definition && definition.operators) || {}).forEach(function (key) {
			var option = document.createElement('option');
			option.value = key;
			option.textContent = definition.operators[key];
			option.selected = node.operator === key;
			select.appendChild(option);
		});
		if (!definition) {
			var option = document.createElement('option');
			option.value = node.operator || '';
			option.textContent = node.operator || '—';
			select.appendChild(option);
			select.disabled = true;
		}
		select.addEventListener('change', function () {
			node.operator = select.value;
			sync();
		});
		return select;
	}

	function selectValue(node, definition) {
		var select = element('select', definition.multiple ? 'wccpa-select-multiple' : '');
		select.multiple = !!definition.multiple;
		if (select.multiple) {
			select.size = Math.min(5, Math.max(3, Object.keys(definition.options || {}).length));
		}
		var values = Array.isArray(node.value) ? node.value.map(String) : [String(node.value || '')];
		Object.keys(definition.options || {}).forEach(function (key) {
			var option = document.createElement('option');
			option.value = key;
			option.textContent = definition.options[key];
			option.selected = values.indexOf(String(key)) !== -1;
			select.appendChild(option);
		});
		select.addEventListener('change', function () {
			node.value = definition.multiple
				? Array.prototype.slice.call(select.selectedOptions).map(function (option) { return option.value; })
				: select.value;
			sync();
		});
		return select;
	}

	function booleanValue(node) {
		var select = element('select');
		[[true, i18n.yes || 'Yes'], [false, i18n.no || 'No']].forEach(function (item) {
			var option = document.createElement('option');
			option.value = item[0] ? '1' : '0';
			option.textContent = item[1];
			option.selected = Boolean(node.value) === item[0];
			select.appendChild(option);
		});
		select.addEventListener('change', function () {
			node.value = select.value === '1';
			sync();
		});
		return select;
	}

	function textValue(node, definition) {
		var input = element('input');
		input.type = 'text';
		input.className = 'regular-text';
		input.placeholder = definition.placeholder || '';
		input.value = typeof node.value === 'string' ? node.value : '';
		input.addEventListener('input', function () {
			node.value = input.value;
			sync();
		});
		return input;
	}

	function ajaxValue(node, definition) {
		var control = element('div', 'wccpa-ajax-control');
		var tokens = element('div', 'wccpa-tokens');
		var input = element('input', 'wccpa-ajax-input');
		var results = element('div', 'wccpa-ajax-results');
		var values = Array.isArray(node.value) ? node.value : (node.value ? [node.value] : []);
		node.labels = node.labels || {};
		input.type = 'search';
		input.autocomplete = 'off';
		input.placeholder = definition.placeholder || '';
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-expanded', 'false');
		results.hidden = true;

		values.forEach(function (value) {
			var key = String(value);
			var token = element('span', 'wccpa-token');
			token.appendChild(document.createTextNode(node.labels[key] || ('#' + key)));
			var remove = element('button', '', '×');
			remove.type = 'button';
			remove.setAttribute('aria-label', i18n.remove || 'Remove');
			remove.addEventListener('click', function () {
				node.value = values.filter(function (existing) { return String(existing) !== key; });
				delete node.labels[key];
				sync();
				render();
			});
			token.appendChild(remove);
			tokens.appendChild(token);
		});

		var debounce = null;
		input.addEventListener('input', function () {
			window.clearTimeout(debounce);
			if (!input.value.trim()) {
				results.hidden = true;
				input.setAttribute('aria-expanded', 'false');
				return;
			}
			debounce = window.setTimeout(function () {
				searchAjax(input.value.trim(), definition, results, function (item) {
					var current = Array.isArray(node.value) ? node.value.slice() : [];
					if (current.map(String).indexOf(String(item.id)) === -1) {
						current.push(item.id);
					}
					node.value = current;
					node.labels[String(item.id)] = item.text;
					sync();
					render();
				});
				input.setAttribute('aria-expanded', 'true');
			}, 250);
		});
		input.addEventListener('blur', function () {
			window.setTimeout(function () {
				results.hidden = true;
				input.setAttribute('aria-expanded', 'false');
			}, 150);
		});
		control.appendChild(tokens);
		control.appendChild(input);
		control.appendChild(results);
		return control;
	}

	function searchAjax(query, definition, results, onSelect) {
		var sequence = ++requestSequence;
		results.hidden = false;
		results.innerHTML = '';
		results.appendChild(element('span', 'wccpa-ajax-message', i18n.searching || 'Searching…'));
		var params = new URLSearchParams({ nonce: config.nonce || '', q: query });
		if (definition.ajax_source === 'term') {
			params.set('action', 'wccpa_search_terms');
			params.set('taxonomy', definition.taxonomy || '');
		} else if (definition.ajax_source === 'user') {
			params.set('action', 'wccpa_search_users');
		} else {
			params.set('action', 'wccpa_search_content');
			params.set('source', definition.ajax_source || 'content');
		}
		window.fetch(config.ajaxUrl + '?' + params.toString(), { credentials: 'same-origin' })
			.then(function (response) { return response.json(); })
			.then(function (response) {
				if (sequence !== requestSequence) {
					return;
				}
				results.innerHTML = '';
				var items = response && response.success && Array.isArray(response.data) ? response.data : [];
				if (!items.length) {
					results.appendChild(element('span', 'wccpa-ajax-message', i18n.noResults || 'No results found.'));
					return;
				}
				items.forEach(function (item) {
					var result = element('button', 'wccpa-ajax-result', item.text);
					result.type = 'button';
					result.addEventListener('mousedown', function (event) { event.preventDefault(); });
					result.addEventListener('click', function () { onSelect(item); });
					results.appendChild(result);
				});
			})
			.catch(function () {
				results.innerHTML = '';
				results.appendChild(element('span', 'wccpa-ajax-message', i18n.noResults || 'No results found.'));
			});
	}

	function extraFields(node, definition) {
		var fragment = document.createDocumentFragment();
		node.extra = node.extra || {};
		(definition.extra || []).forEach(function (field) {
			var label = element('label', 'wccpa-extra-field');
			if (field.type === 'checkbox') {
				var checkbox = element('input');
				checkbox.type = 'checkbox';
				checkbox.checked = typeof node.extra[field.key] === 'undefined' ? !!field.default : !!node.extra[field.key];
				checkbox.addEventListener('change', function () {
					node.extra[field.key] = checkbox.checked;
					sync();
				});
				label.appendChild(checkbox);
				label.appendChild(document.createTextNode(field.label));
			} else if (field.type === 'select') {
				label.appendChild(document.createTextNode(field.label + ' '));
				var select = element('select');
				Object.keys(field.options || {}).forEach(function (key) {
					var option = document.createElement('option');
					option.value = key;
					option.textContent = field.options[key];
					option.selected = (node.extra[field.key] || field.default) === key;
					select.appendChild(option);
				});
				select.addEventListener('change', function () {
					node.extra[field.key] = select.value;
					sync();
				});
				label.appendChild(select);
			}
			fragment.appendChild(label);
		});
		return fragment;
	}

	function renderCondition(node, parent) {
		var row = element('div', 'wccpa-rule-row');
		var definition = definitions[node.type];
		row.appendChild(groupedTypeSelect(node));
		row.appendChild(operatorSelect(node, definition));
		var valueHost = element('div', 'wccpa-condition-value');
		if (!definition) {
			valueHost.appendChild(element('em', '', i18n.unavailableRule || 'Condition unavailable.'));
		} else if (definition.value_type === 'select') {
			valueHost.appendChild(selectValue(node, definition));
		} else if (definition.value_type === 'boolean') {
			valueHost.appendChild(booleanValue(node));
		} else if (definition.value_type === 'ajax') {
			valueHost.appendChild(ajaxValue(node, definition));
		} else {
			valueHost.appendChild(textValue(node, definition));
		}
		if (definition) {
			valueHost.appendChild(extraFields(node, definition));
		}
		row.appendChild(valueHost);
		var remove = button(i18n.remove || 'Remove', 'button-link-delete wccpa-condition-remove');
		remove.addEventListener('click', function () {
			parent.children = parent.children.filter(function (child) { return child !== node; });
			sync();
			render();
		});
		row.appendChild(remove);
		return row;
	}

	function renderGroup(group, parent, depth) {
		var wrapper = element('div', 'wccpa-rule-group');
		var header = element('div', 'wccpa-group-header');
		var headerText = element('span', 'wccpa-group-header__text', depth === 0 ? (i18n.showWhen || 'Show when') : (i18n.group || 'Group'));
		var relation = element('select');
		[['AND', i18n.all || 'ALL'], ['OR', i18n.any || 'ANY']].forEach(function (item) {
			var option = document.createElement('option');
			option.value = item[0];
			option.textContent = item[1];
			option.selected = group.relation === item[0];
			relation.appendChild(option);
		});
		relation.addEventListener('change', function () {
			group.relation = relation.value;
			sync();
		});
		header.appendChild(headerText);
		header.appendChild(relation);
		header.appendChild(element('span', '', i18n.areTrue || 'are true'));
		if (parent) {
			var removeGroup = button(i18n.remove || 'Remove', 'button-link-delete wccpa-group-header__remove');
			removeGroup.addEventListener('click', function () {
				parent.children = parent.children.filter(function (child) { return child !== group; });
				sync();
				render();
			});
			header.appendChild(removeGroup);
		}
		wrapper.appendChild(header);
		var children = element('div', 'wccpa-group-children');
		if (!group.children.length) {
			children.appendChild(element('p', 'wccpa-rule-empty', host.dataset.emptyMessage || 'Add at least one condition.'));
		}
		group.children.forEach(function (child) {
			children.appendChild(child.kind === 'group' ? renderGroup(child, group, depth + 1) : renderCondition(child, group));
		});
		wrapper.appendChild(children);
		var actions = element('div', 'wccpa-rule-actions');
		var addCondition = button('+ ' + (i18n.addCondition || 'Add condition'), 'button-secondary');
		addCondition.disabled = !Object.keys(definitions).length;
		addCondition.addEventListener('click', function () {
			var condition = defaultCondition();
			if (condition) {
				group.children.push(condition);
				sync();
				render();
			}
		});
		actions.appendChild(addCondition);
		var addGroup = button('+ ' + (i18n.addGroup || 'Add group'), 'button-secondary');
		addGroup.disabled = depth >= Number(config.maxDepth || 5) - 1;
		addGroup.title = addGroup.disabled ? (i18n.maxDepthReached || '') : '';
		addGroup.addEventListener('click', function () {
			if (!addGroup.disabled) {
				group.children.push({ kind: 'group', relation: 'AND', children: [] });
				sync();
				render();
			}
		});
		actions.appendChild(addGroup);
		wrapper.appendChild(actions);
		return wrapper;
	}

	function render() {
		if (!host || !state) {
			return;
		}
		host.innerHTML = '';
		host.appendChild(renderGroup(state, null, 0));
	}

	function namedValue(name, fallback) {
		var fields = document.getElementsByName(name);
		for (var index = 0; index < fields.length; index += 1) {
			if (fields[index].type !== 'hidden') {
				return fields[index].value;
			}
		}
		return fallback;
	}

	function namedBool(name) {
		var fields = document.getElementsByName(name);
		for (var index = 0; index < fields.length; index += 1) {
			if (fields[index].type === 'checkbox') {
				return fields[index].checked;
			}
		}
		return false;
	}

	function buttonConfig(key) {
		var base = 'wccpa_behavior[' + key + ']';
		return {
			enabled: namedBool(base + '[enabled]'),
			label: namedValue(base + '[label]', ''),
			action: namedValue(base + '[action]', 'close'),
			url: namedValue(base + '[url]', ''),
			new_tab: namedBool(base + '[new_tab]'),
			style: namedValue(base + '[style]', 'primary')
		};
	}

	function editorContent() {
		if (window.tinyMCE && window.tinyMCE.get('content') && !window.tinyMCE.get('content').isHidden()) {
			return window.tinyMCE.get('content').getContent();
		}
		var textarea = document.getElementById('content');
		if (textarea) {
			return textarea.value;
		}
		return '';
	}

	function preview() {
		if (!window.WCCPA || typeof window.WCCPA.preview !== 'function') {
			window.alert(i18n.previewUnavailable || 'Could not start the preview.');
			return;
		}
		window.WCCPA.preview({
			id: 'preview',
			title: namedValue('wccpa_display[public_title]', '') || i18n.defaultTitle || 'Alert',
			content: editorContent(),
			priority: Number(namedValue('wccpa_priority', 50)),
			exclusive: false,
			frequency: { mode: 'always', days: 0 },
			behavior: {
				close_x: namedBool('wccpa_behavior[close_x]'),
				close_overlay: namedBool('wccpa_behavior[close_overlay]'),
				close_esc: namedBool('wccpa_behavior[close_esc]'),
				auto_close: namedBool('wccpa_behavior[auto_close]'),
				auto_close_seconds: Number(namedValue('wccpa_behavior[auto_close_seconds]', 10)),
				countdown: namedValue('wccpa_behavior[countdown_style]', 'none') !== 'none',
				countdown_style: namedValue('wccpa_behavior[countdown_style]', 'none'),
				pause_on_hover: namedBool('wccpa_behavior[pause_on_hover]'),
				delay: 0,
				animation: namedValue('wccpa_behavior[animation]', 'fade-scale'),
				primary_button: buttonConfig('primary_button'),
				secondary_button: buttonConfig('secondary_button')
			},
			appearance: {
				width: Number(namedValue('wccpa_appearance[width]', 600)),
				width_unit: namedValue('wccpa_appearance[width_unit]', 'px'),
				max_height: Number(namedValue('wccpa_appearance[max_height]', 80)),
				max_height_unit: namedValue('wccpa_appearance[max_height_unit]', 'vh'),
				padding: Number(namedValue('wccpa_appearance[padding]', 24)),
				background: namedValue('wccpa_appearance[background]', '#ffffff'),
				text_color: namedValue('wccpa_appearance[text_color]', '#1d2327'),
				radius: Number(namedValue('wccpa_appearance[radius]', 8)),
				border_enabled: namedBool('wccpa_appearance[border_enabled]'),
				border_width: Number(namedValue('wccpa_appearance[border_width]', 1)),
				border_style: namedValue('wccpa_appearance[border_style]', 'solid'),
				border_color: namedValue('wccpa_appearance[border_color]', '#dcdcde'),
				shadow: namedValue('wccpa_appearance[shadow]', 'medium'),
				overlay_enabled: namedBool('wccpa_appearance[overlay_enabled]'),
				overlay_color: namedValue('wccpa_appearance[overlay_color]', '#000000'),
				overlay_opacity: Number(namedValue('wccpa_appearance[overlay_opacity]', 58)),
				overlay_blur: Number(namedValue('wccpa_appearance[overlay_blur]', 0)),
				topbar: namedBool('wccpa_appearance[topbar]'),
				topbar_bg: namedValue('wccpa_appearance[topbar_bg]', '#d63638'),
				topbar_text: namedValue('wccpa_appearance[topbar_text]', '#ffffff'),
				icon: namedValue('wccpa_appearance[icon]', 'warning')
			}
		});
	}

	function setupDimensionFields() {
		document.querySelectorAll('[data-wccpa-unit-field]').forEach(function (field) {
			var input = field.querySelector('[data-wccpa-dimension-value]');
			var select = field.querySelector('[data-wccpa-dimension-unit]');
			var bounds = {};
			try {
				bounds = JSON.parse(field.getAttribute('data-wccpa-unit-bounds') || '{}');
			} catch (error) {
				bounds = {};
			}
			if (!input || !select) {
				return;
			}

			function updateBounds(resetInvalid) {
				var range = bounds[select.value];
				if (!range) {
					return;
				}
				input.min = String(range.min);
				input.max = String(range.max);
				var value = Number(input.value);
				if (resetInvalid && (!Number.isFinite(value) || value < Number(range.min) || value > Number(range.max))) {
					input.value = String(range.default);
				}
			}

			select.addEventListener('change', function () { updateBounds(true); });
			updateBounds(true);
		});

		var editor = document.querySelector('[data-wccpa-dimension-editor]');
		if (!editor) {
			return;
		}
		var popup = editor.querySelector('.wccpa-dimension-popup');
		var padding = document.getElementById('wccpa-padding');
		var radius = document.getElementById('wccpa-radius');
		function updateDiagram() {
			if (!popup) {
				return;
			}
			var paddingValue = padding ? Math.max(0, Math.min(80, Number(padding.value) || 0)) : 24;
			var radiusValue = radius ? Math.max(0, Math.min(80, Number(radius.value) || 0)) : 8;
			popup.style.setProperty('--wccpa-diagram-padding', Math.min(54, 18 + (paddingValue * 0.45)) + 'px');
			popup.style.setProperty('--wccpa-diagram-radius', Math.min(42, radiusValue) + 'px');
		}
		[padding, radius].forEach(function (input) {
			if (input) {
				input.addEventListener('input', updateDiagram);
			}
		});
		updateDiagram();
	}

	function setupAppearancePreviews() {
		var colorsPreview = document.querySelector('[data-wccpa-colors-preview]');
		var borderPreview = document.querySelector('[data-wccpa-border-preview]');
		var overlayPreview = document.querySelector('[data-wccpa-overlay-preview]');
		var headerPreview = document.querySelector('[data-wccpa-header-preview]');
		var dimensionPopup = document.querySelector('.wccpa-dimension-popup');
		if (!colorsPreview && !borderPreview && !overlayPreview && !headerPreview && !dimensionPopup) {
			return;
		}

		function numberValue(name, fallback, minimum, maximum) {
			var value = Number(namedValue(name, fallback));
			if (!Number.isFinite(value)) {
				value = fallback;
			}
			return Math.min(maximum, Math.max(minimum, value));
		}

		function colorValue(name, fallback) {
			var value = String(namedValue(name, fallback));
			return /^#[0-9a-f]{6}$/i.test(value) ? value : fallback;
		}

		function rgba(hex, opacity) {
			var value = parseInt(hex.slice(1), 16);
			return 'rgba(' + ((value >> 16) & 255) + ',' + ((value >> 8) & 255) + ',' + (value & 255) + ',' + opacity + ')';
		}

		function updateColors() {
			var background = colorValue('wccpa_appearance[background]', '#ffffff');
			var textColor = colorValue('wccpa_appearance[text_color]', '#1d2327');
			var radius = numberValue('wccpa_appearance[radius]', 8, 0, 80);
			var shadow = namedValue('wccpa_appearance[shadow]', 'medium');
			var shadows = {
				none: 'none',
				soft: '0 6px 18px rgba(0, 0, 0, .14)',
				medium: '0 10px 28px rgba(0, 0, 0, .22)',
				strong: '0 14px 38px rgba(0, 0, 0, .34)'
			};
			if (colorsPreview) {
				colorsPreview.style.backgroundColor = background;
				colorsPreview.style.color = textColor;
				colorsPreview.style.borderRadius = Math.min(32, radius) + 'px';
				colorsPreview.style.boxShadow = shadows[shadow] || shadows.medium;
			}
			if (borderPreview) {
				borderPreview.style.backgroundColor = background;
				borderPreview.style.color = textColor;
				borderPreview.style.borderRadius = Math.min(32, radius) + 'px';
			}
			if (dimensionPopup) {
				dimensionPopup.style.backgroundColor = background;
				dimensionPopup.style.color = textColor;
			}
			if (overlayPreview) {
				var dialog = overlayPreview.querySelector('.wccpa-preview-overlay-dialog');
				if (dialog) {
					dialog.style.backgroundColor = background;
					dialog.style.color = textColor;
					dialog.style.borderRadius = Math.min(24, radius) + 'px';
				}
			}
			if (headerPreview) {
				headerPreview.style.backgroundColor = background;
				headerPreview.style.borderRadius = Math.min(24, radius) + 'px';
				var body = headerPreview.querySelector('.wccpa-preview-header-body');
				if (body) {
					body.style.backgroundColor = background;
					body.style.color = textColor;
				}
			}
		}

		function updateBorder() {
			if (!borderPreview) {
				return;
			}
			var enabled = namedBool('wccpa_appearance[border_enabled]');
			borderPreview.style.borderWidth = enabled ? numberValue('wccpa_appearance[border_width]', 1, 0, 10) + 'px' : '0';
			borderPreview.style.borderStyle = namedValue('wccpa_appearance[border_style]', 'solid');
			borderPreview.style.borderColor = colorValue('wccpa_appearance[border_color]', '#dcdcde');
		}

		function updateOverlay() {
			if (!overlayPreview) {
				return;
			}
			var enabled = namedBool('wccpa_appearance[overlay_enabled]');
			var color = colorValue('wccpa_appearance[overlay_color]', '#000000');
			var opacity = numberValue('wccpa_appearance[overlay_opacity]', 58, 0, 100) / 100;
			var blur = numberValue('wccpa_appearance[overlay_blur]', 0, 0, 20);
			var layer = overlayPreview.querySelector('.wccpa-preview-overlay-layer');
			var page = overlayPreview.querySelector('.wccpa-preview-page-content');
			if (layer) {
				layer.style.backgroundColor = enabled ? rgba(color, opacity) : 'transparent';
				layer.style.backdropFilter = enabled ? 'blur(' + blur + 'px)' : 'none';
				layer.style.setProperty('-webkit-backdrop-filter', enabled ? 'blur(' + blur + 'px)' : 'none');
			}
			if (page) {
				page.style.filter = enabled && blur ? 'blur(' + Math.min(6, blur * .45) + 'px)' : 'none';
			}
		}

		function updateHeader() {
			if (!headerPreview) {
				return;
			}
			var enabled = namedBool('wccpa_appearance[topbar]');
			var background = colorValue('wccpa_appearance[background]', '#ffffff');
			var textColor = colorValue('wccpa_appearance[text_color]', '#1d2327');
			var bar = headerPreview.querySelector('.wccpa-preview-header-bar');
			var icon = headerPreview.querySelector('.wccpa-preview-header-icon');
			if (bar) {
				bar.style.backgroundColor = enabled ? colorValue('wccpa_appearance[topbar_bg]', '#d63638') : background;
				bar.style.color = enabled ? colorValue('wccpa_appearance[topbar_text]', '#ffffff') : textColor;
			}
			if (icon) {
				var iconName = namedValue('wccpa_appearance[icon]', 'warning');
				var iconClasses = {
					info: 'dashicons-info-outline',
					warning: 'dashicons-warning',
					alert: 'dashicons-marker',
					exclamation: 'dashicons-info',
					error: 'dashicons-dismiss',
					success: 'dashicons-yes-alt',
					delivery: 'dashicons-car',
					location: 'dashicons-location',
					compatibility: 'dashicons-admin-tools',
					help: 'dashicons-editor-help'
				};
				icon.hidden = iconName === 'none';
				icon.className = 'dashicons wccpa-preview-header-icon ' + (iconClasses[iconName] || 'dashicons-warning');
			}
		}

		function updateAll() {
			updateColors();
			updateBorder();
			updateOverlay();
			updateHeader();
		}

		document.querySelectorAll('[name]').forEach(function (field) {
			if (field.name.indexOf('wccpa_appearance[') !== 0) {
				return;
			}
			field.addEventListener('input', updateAll);
			field.addEventListener('change', updateAll);
		});
		updateAll();
	}

	function setupBehaviorPreview() {
		var preview = document.querySelector('[data-wccpa-behavior-preview]');
		if (!preview) {
			return;
		}
		var dialog = preview.querySelector('[data-wccpa-preview-dialog]');
		var closeButton = preview.querySelector('[data-wccpa-preview-close]');
		var overlayClick = preview.querySelector('[data-wccpa-preview-overlay-click]');
		var escKey = preview.querySelector('[data-wccpa-preview-esc]');
		var delay = preview.querySelector('[data-wccpa-preview-delay]');
		var autoClose = preview.querySelector('[data-wccpa-preview-auto-close]');
		var pauseIndicator = preview.querySelector('[data-wccpa-preview-pause]');
		var footer = preview.querySelector('[data-wccpa-preview-footer]');
		var countdownText = preview.querySelector('[data-wccpa-preview-countdown-text]');
		var progress = preview.querySelector('[data-wccpa-preview-progress]');
		var progressBar = progress ? progress.querySelector('span') : null;
		var primary = preview.querySelector('[data-wccpa-preview-primary]');
		var secondary = preview.querySelector('[data-wccpa-preview-secondary]');
		var replayTimer = null;
		var countdownTimer = null;
		var countdownTotal = 10000;
		var countdownRemaining = 10000;
		var countdownStartedAt = 0;
		var countdownPaused = false;

		function format(template, value, fallback) {
			return String(template || fallback).replace('%s', String(value));
		}

		function countdownStyle() {
			var style = namedValue('wccpa_behavior[countdown_style]', 'none');
			return ['none', 'text', 'progress', 'close_x', 'close_button'].indexOf(style) === -1 ? 'none' : style;
		}

		function updateButton(element, key, seconds, style) {
			if (!element) {
				return true;
			}
			var base = 'wccpa_behavior[' + key + ']';
			var enabled = namedBool(base + '[enabled]');
			var action = namedValue(base + '[action]', 'close');
			var label = namedValue(base + '[label]', '');
			element.hidden = !enabled;
			var visibleLabel = style === 'close_button' && action === 'close' && enabled ? label + ' (' + seconds + ')' : label;
			if (element.textContent !== visibleLabel) {
				element.textContent = visibleLabel;
			}
			element.className = 'wccpa-behavior-preview__button is-' + (namedValue(base + '[style]', 'primary') === 'secondary' ? 'secondary' : 'primary');
			element.classList.toggle('is-url', action === 'url');
			return !enabled;
		}

		function renderCountdown() {
			var enabled = namedBool('wccpa_behavior[auto_close]');
			var style = enabled ? countdownStyle() : 'none';
			var seconds = Math.max(0, Math.ceil(countdownRemaining / 1000));
			var amount = countdownTotal > 0 ? Math.max(0, Math.min(100, (1 - (countdownRemaining / countdownTotal)) * 100)) : 0;

			if (autoClose) {
				autoClose.hidden = !enabled || style !== 'none';
				autoClose.textContent = format(i18n.autoClosePreview, Math.round(countdownTotal / 1000), 'Auto-close: %s s');
			}
			if (pauseIndicator) {
				pauseIndicator.hidden = !enabled || !namedBool('wccpa_behavior[pause_on_hover]');
				pauseIndicator.classList.toggle('is-active', countdownPaused);
				pauseIndicator.textContent = countdownPaused ? (i18n.paused || 'Paused') : (i18n.pauseOnHover || 'Pause on hover');
			}
			if (countdownText) {
				countdownText.hidden = style !== 'text';
				var message = String(i18n.countdownMessage || 'This message will close in %d seconds.').replace('%d', String(seconds));
				if (countdownText.textContent !== message) {
					countdownText.textContent = message;
				}
			}
			if (progress) {
				progress.hidden = style !== 'progress';
			}
			if (progressBar) {
				progressBar.style.width = amount + '%';
			}
			if (closeButton) {
				closeButton.classList.toggle('is-countdown', style === 'close_x' && !closeButton.hidden);
				closeButton.style.setProperty('--wccpa-preview-countdown', amount + '%');
			}

			var primaryHidden = updateButton(primary, 'primary_button', seconds, style);
			var secondaryHidden = updateButton(secondary, 'secondary_button', seconds, style);
			if (footer) {
				footer.hidden = primaryHidden && secondaryHidden && (!countdownText || countdownText.hidden);
			}
		}

		function restartCountdown() {
			window.clearInterval(countdownTimer);
			countdownTimer = null;
			countdownTotal = Math.max(1000, Number(namedValue('wccpa_behavior[auto_close_seconds]', 10)) * 1000 || 10000);
			countdownRemaining = countdownTotal;
			countdownStartedAt = Date.now();
			countdownPaused = false;
			renderCountdown();
			if (!namedBool('wccpa_behavior[auto_close]')) {
				return;
			}
			countdownTimer = window.setInterval(function () {
				if (countdownPaused) {
					return;
				}
				var elapsed = (Date.now() - countdownStartedAt) % countdownTotal;
				countdownRemaining = countdownTotal - elapsed;
				renderCountdown();
			}, 100);
		}

		function replayAnimation() {
			if (!dialog) {
				return;
			}
			['animation-none', 'animation-fade', 'animation-fade-scale', 'animation-slide', 'is-replaying'].forEach(function (className) {
				dialog.classList.remove(className);
			});
			var animation = namedValue('wccpa_behavior[animation]', 'fade-scale');
			if (['none', 'fade', 'fade-scale', 'slide'].indexOf(animation) === -1) {
				animation = 'fade-scale';
			}
			dialog.classList.add('animation-' + animation);
			if (animation !== 'none') {
				void dialog.offsetWidth;
				dialog.classList.add('is-replaying');
				window.clearTimeout(replayTimer);
				replayTimer = window.setTimeout(function () { dialog.classList.remove('is-replaying'); }, 450);
			}
		}

		function update(replay, restart) {
			if (closeButton) {
				closeButton.hidden = !namedBool('wccpa_behavior[close_x]');
			}
			if (overlayClick) {
				overlayClick.hidden = !namedBool('wccpa_behavior[close_overlay]');
			}
			if (escKey) {
				escKey.hidden = !namedBool('wccpa_behavior[close_esc]');
			}
			if (delay) {
				delay.textContent = format(i18n.delayPreview, namedValue('wccpa_behavior[delay]', 0), 'Delay: %s s');
			}
			if (restart) {
				restartCountdown();
			} else {
				renderCountdown();
			}
			if (replay) {
				replayAnimation();
			}
		}

		if (dialog) {
			dialog.addEventListener('mouseenter', function () {
				if (!namedBool('wccpa_behavior[auto_close]') || !namedBool('wccpa_behavior[pause_on_hover]') || countdownPaused) {
					return;
				}
				countdownRemaining = Math.max(0, countdownTotal - ((Date.now() - countdownStartedAt) % countdownTotal));
				countdownPaused = true;
				renderCountdown();
			});
			dialog.addEventListener('mouseleave', function () {
				if (!countdownPaused) {
					return;
				}
				countdownStartedAt = Date.now() - (countdownTotal - countdownRemaining);
				countdownPaused = false;
				renderCountdown();
			});
		}

		document.querySelectorAll('[name]').forEach(function (field) {
			if (field.name.indexOf('wccpa_behavior[') !== 0) {
				return;
			}
			field.addEventListener('input', function () { update(false, true); });
			field.addEventListener('change', function () {
				update(field.name === 'wccpa_behavior[animation]', true);
			});
		});
		update(true, true);
	}

	function setupConditionalFields() {
		var frequencyDays = document.getElementById('wccpa-frequency-days');
		var frequencyRadios = document.querySelectorAll('input[name="wccpa_frequency[mode]"]');
		function updateFrequency() {
			var selected = document.querySelector('input[name="wccpa_frequency[mode]"]:checked');
			if (frequencyDays) {
				frequencyDays.readOnly = !selected || selected.value !== 'days';
				frequencyDays.setAttribute('aria-disabled', frequencyDays.readOnly ? 'true' : 'false');
				frequencyDays.classList.toggle('is-disabled', frequencyDays.readOnly);
			}
		}
		frequencyRadios.forEach(function (radio) {
			radio.addEventListener('change', updateFrequency);
		});
		updateFrequency();

		document.querySelectorAll('[data-wccpa-toggle-group]').forEach(function (group) {
			var toggle = group.querySelector('input[type="checkbox"]');
			var details = group.querySelector('.wccpa-toggle-details, .wccpa-auto-close__details');
			if (!toggle || !details) {
				return;
			}
			function updateDetails() {
				details.hidden = !toggle.checked;
				if (group.getAttribute('data-wccpa-toggle-group') === 'schedule') {
					details.querySelectorAll('input, select, textarea, button').forEach(function (field) {
						field.disabled = !toggle.checked;
					});
					details.setAttribute('aria-disabled', toggle.checked ? 'false' : 'true');
				}
			}
			toggle.addEventListener('change', updateDetails);
			updateDetails();
		});

		document.querySelectorAll('[data-wccpa-button-settings]').forEach(function (settings) {
			var action = settings.querySelector('[data-wccpa-button-action]');
			var urlFields = settings.querySelector('.wccpa-button-url-fields');
			if (!action || !urlFields) {
				return;
			}
			function updateButtonAction() {
				urlFields.hidden = action.value !== 'url';
			}
			action.addEventListener('change', updateButtonAction);
			updateButtonAction();
		});
	}

	$(function () {
		$('.wccpa-color-field').wpColorPicker();
		setupConditionalFields();
		setupDimensionFields();
		setupAppearancePreviews();
		setupBehaviorPreview();
	});

	if (hidden && host) {
		state = parseState();
		render();
		var form = hidden.closest('form');
		if (form) {
			form.addEventListener('submit', sync);
		}
	}

	var previewButton = document.getElementById('wccpa-preview-button');
	if (previewButton) {
		previewButton.addEventListener('click', preview);
	}
}(jQuery));
