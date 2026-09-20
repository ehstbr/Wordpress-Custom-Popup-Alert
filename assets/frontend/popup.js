(function () {
	'use strict';

	var ICONS = {
		info: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 10h2v8h-2zm0-4h2v2h-2zm1-4a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/></svg>',
		warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
		alert: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 14h-2V6h2v8zm0 4h-2v-2h2v2zm-1-16a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/></svg>',
		error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm5 13.6L15.6 17 12 13.4 8.4 17 7 15.6l3.6-3.6L7 8.4 8.4 7l3.6 3.6L15.6 7 17 8.4 13.4 12l3.6 3.6z"/></svg>',
		success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-2 15-5-5 1.4-1.4 3.6 3.6 7.6-7.6L19 8l-9 9z"/></svg>',
		delivery: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 8h-3V4H3v13h2a3 3 0 0 0 6 0h3a3 3 0 0 0 6 0h1v-5l-1-4zM8 18.5A1.5 1.5 0 1 1 8 15a1.5 1.5 0 0 1 0 3.5zM15 15h-4.4A3 3 0 0 0 5 15V6h10v9zm2-5h2l.7 2H17v-2zm0 8.5a1.5 1.5 0 1 1 0-3.5 1.5 1.5 0 0 1 0 3.5z"/></svg>',
		location: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>',
		compatibility: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11h-3.1A6 6 0 0 0 13 6.1V3h-2v3.1A6 6 0 0 0 6.1 11H3v2h3.1a6 6 0 0 0 4.9 4.9V21h2v-3.1a6 6 0 0 0 4.9-4.9H21v-2zm-9 5a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/></svg>',
		help: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 18h2v-2h-2v2zm1-16a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm0-14a4 4 0 0 0-4 4h2a2 2 0 1 1 2.8 1.8c-1.1.5-1.8 1.4-1.8 2.7V15h2v-.5c0-.5.2-.8.8-1.1A4 4 0 0 0 12 6z"/></svg>'
	};
	ICONS.exclamation = ICONS.alert;

	function safeStorage(storage, method, key, value) {
		try {
			if (method === 'get') {
				return storage.getItem(key);
			}
			storage.setItem(key, value);
			return true;
		} catch (error) {
			return method === 'get' ? null : false;
		}
	}

	function hexToRgba(hex, opacity) {
		var value = String(hex || '#000000').replace('#', '');
		if (value.length === 3) {
			value = value.split('').map(function (part) { return part + part; }).join('');
		}
		var number = parseInt(value, 16);
		if (isNaN(number)) {
			number = 0;
		}
		return 'rgba(' + ((number >> 16) & 255) + ',' + ((number >> 8) & 255) + ',' + (number & 255) + ',' + Math.max(0, Math.min(1, Number(opacity) / 100)) + ')';
	}

	function dimensionValue(value, unit, bounds, fallbackValue, fallbackUnit) {
		var safeUnit = Object.prototype.hasOwnProperty.call(bounds, unit) ? unit : fallbackUnit;
		var range = bounds[safeUnit];
		var number = Number(value);
		if (!Number.isFinite(number)) {
			number = fallbackValue;
		}
		number = Math.min(range[1], Math.max(range[0], number));
		return number + safeUnit;
	}

	function createShell(id) {
		var root = document.createElement('div');
		var progressLabel = (window.wccpaFrontend && window.wccpaFrontend.progressLabel) || 'Countdown progress';
		root.id = id;
		root.className = 'wccpa-root';
		root.hidden = true;
		root.setAttribute('aria-hidden', 'true');
		root.innerHTML = '<div class="wccpa-overlay" data-wccpa-overlay><div class="wccpa-dialog" role="dialog" aria-modal="true" aria-labelledby="' + id + '-title" aria-describedby="' + id + '-content" tabindex="-1"><div class="wccpa-topbar" data-wccpa-topbar><span class="wccpa-icon" data-wccpa-icon aria-hidden="true"></span><h2 class="wccpa-title" id="' + id + '-title"></h2><button type="button" class="wccpa-close" data-wccpa-close><svg class="wccpa-close-progress" data-wccpa-close-progress viewBox="0 0 36 36" aria-hidden="true" hidden><circle class="wccpa-close-progress__track" cx="18" cy="18" r="15.5" pathLength="100"></circle><circle class="wccpa-close-progress__value" data-wccpa-close-progress-value cx="18" cy="18" r="15.5" pathLength="100"></circle></svg><span aria-hidden="true">&times;</span></button></div><div class="wccpa-body" id="' + id + '-content"></div><div class="wccpa-footer" data-wccpa-footer hidden><p class="wccpa-countdown" data-wccpa-countdown aria-live="polite" hidden></p><a class="wccpa-button wccpa-button--secondary" data-wccpa-secondary href="#"></a><a class="wccpa-button wccpa-button--primary" data-wccpa-primary href="#"></a></div><div class="wccpa-progress" data-wccpa-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><span data-wccpa-progress-bar></span></div></div></div>';
		root.querySelector('[data-wccpa-close]').setAttribute('aria-label', (window.wccpaFrontend && window.wccpaFrontend.closeLabel) || 'Close alert');
		root.querySelector('[data-wccpa-progress]').setAttribute('aria-label', progressLabel);
		document.body.appendChild(root);
		return root;
	}

	function PopupController(root, alerts, options) {
		this.root = root;
		this.options = options || {};
		this.overlay = root.querySelector('[data-wccpa-overlay]');
		this.dialog = root.querySelector('.wccpa-dialog');
		this.topbar = root.querySelector('[data-wccpa-topbar]');
		this.icon = root.querySelector('[data-wccpa-icon]');
		this.title = root.querySelector('.wccpa-title');
		this.content = root.querySelector('.wccpa-body');
		this.closeButton = root.querySelector('[data-wccpa-close]');
		this.closeProgress = root.querySelector('[data-wccpa-close-progress]');
		this.closeProgressValue = root.querySelector('[data-wccpa-close-progress-value]');
		this.countdown = root.querySelector('[data-wccpa-countdown]');
		this.footer = root.querySelector('[data-wccpa-footer]');
		this.progress = root.querySelector('[data-wccpa-progress]');
		this.progressBar = root.querySelector('[data-wccpa-progress-bar]');
		this.primary = root.querySelector('[data-wccpa-primary]');
		this.secondary = root.querySelector('[data-wccpa-secondary]');
		this.current = null;
		this.countdownTimer = null;
		this.autoCloseTotal = 0;
		this.autoCloseRemaining = 0;
		this.autoCloseStartedAt = 0;
		this.autoClosePaused = false;
		this.autoCloseStyle = 'none';
		this.pauseOnHover = false;
		this.delayTimer = null;
		this.previousFocus = null;
		this.previousOverflow = '';
		this.queue = this.prepareQueue(alerts || []);
		this.onKeydown = this.handleKeydown.bind(this);
		this.onOverlayClick = this.handleOverlayClick.bind(this);
		this.onMouseEnter = this.handleMouseEnter.bind(this);
		this.onMouseLeave = this.handleMouseLeave.bind(this);
		this.close = this.close.bind(this);
		this.closeButton.addEventListener('click', this.close);
		this.overlay.addEventListener('click', this.onOverlayClick);
		this.dialog.addEventListener('mouseenter', this.onMouseEnter);
		this.dialog.addEventListener('mouseleave', this.onMouseLeave);
	}

	PopupController.prototype.prepareQueue = function (alerts) {
		var eligible = this.options.preview ? alerts.slice() : alerts.filter(this.canShow.bind(this));
		eligible.sort(function (left, right) {
			return Number(right.priority || 0) - Number(left.priority || 0) || Number(left.id || 0) - Number(right.id || 0);
		});

		var exclusivePriorities = eligible.filter(function (alert) { return !!alert.exclusive; }).map(function (alert) { return Number(alert.priority || 0); });
		if (exclusivePriorities.length) {
			var threshold = Math.max.apply(Math, exclusivePriorities);
			eligible = eligible.filter(function (alert) { return Number(alert.priority || 0) >= threshold; });
		}

		return eligible;
	};

	PopupController.prototype.canShow = function (alert) {
		var frequency = alert.frequency || {};
		var mode = frequency.mode || 'always';
		var key = 'wccpa_seen_' + String(alert.id);
		if (mode === 'always') {
			return true;
		}
		if (mode === 'session') {
			return !safeStorage(window.sessionStorage, 'get', key);
		}
		if (mode === 'days') {
			var raw = safeStorage(window.localStorage, 'get', key);
			if (!raw) {
				return true;
			}
			if (Number(frequency.days) === 0) {
				return false;
			}
			try {
				var seen = JSON.parse(raw);
				var elapsed = Date.now() - Number(seen.shown_at || 0);
				return elapsed >= Number(frequency.days || 0) * 86400000;
			} catch (error) {
				return true;
			}
		}
		return true;
	};

	PopupController.prototype.recordShown = function (alert) {
		if (this.options.preview) {
			return;
		}
		var frequency = alert.frequency || {};
		var key = 'wccpa_seen_' + String(alert.id);
		var payload = JSON.stringify({ shown_at: Date.now() });
		if (frequency.mode === 'session') {
			safeStorage(window.sessionStorage, 'set', key, payload);
		} else if (frequency.mode === 'days') {
			safeStorage(window.localStorage, 'set', key, payload);
		}
	};

	PopupController.prototype.start = function () {
		this.showNext();
	};

	PopupController.prototype.showNext = function () {
		var controller = this;
		if (!this.queue.length) {
			if (this.options.removeOnFinish && this.root.parentNode) {
				this.root.parentNode.removeChild(this.root);
			}
			return;
		}
		var alert = this.queue[0];
		var delay = Math.max(0, Number((alert.behavior || {}).delay || 0) * 1000);
		window.clearTimeout(this.delayTimer);
		this.delayTimer = window.setTimeout(function () { controller.open(alert); }, delay);
	};

	PopupController.prototype.open = function (alert) {
		this.current = alert;
		this.applyAlert(alert);
		this.recordShown(alert);
		this.previousFocus = document.activeElement;
		this.previousOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		document.addEventListener('keydown', this.onKeydown, true);
		this.root.hidden = false;
		this.root.setAttribute('aria-hidden', 'false');
		this.root.classList.remove('is-closing');
		var root = this.root;
		window.requestAnimationFrame(function () { root.classList.add('is-open'); });

		var focusTarget = this.focusableElements()[0] || this.dialog;
		window.setTimeout(function () { focusTarget.focus(); }, 0);
		this.startAutoClose(alert);
	};

	PopupController.prototype.applyAlert = function (alert) {
		var appearance = alert.appearance || {};
		var behavior = alert.behavior || {};
		var style = this.root.style;
		style.setProperty('--wccpa-width', dimensionValue(appearance.width, appearance.width_unit, { px: [280, 1600], '%': [20, 100], vw: [20, 100] }, 600, 'px'));
		style.setProperty('--wccpa-max-height', dimensionValue(appearance.max_height, appearance.max_height_unit, { px: [160, 2000], '%': [20, 100], vh: [20, 100] }, 80, 'vh'));
		style.setProperty('--wccpa-padding', Math.max(0, Number(appearance.padding || 24)) + 'px');
		style.setProperty('--wccpa-background', appearance.background || '#ffffff');
		style.setProperty('--wccpa-text', appearance.text_color || '#1d2327');
		style.setProperty('--wccpa-radius', Math.max(0, Number(appearance.radius || 0)) + 'px');
		style.setProperty('--wccpa-border-width', appearance.border_enabled ? Math.max(0, Number(appearance.border_width || 1)) + 'px' : '0');
		style.setProperty('--wccpa-border-style', appearance.border_style || 'solid');
		style.setProperty('--wccpa-border-color', appearance.border_color || '#dcdcde');
		style.setProperty('--wccpa-overlay', hexToRgba(appearance.overlay_color, appearance.overlay_opacity));
		style.setProperty('--wccpa-overlay-blur', Math.max(0, Number(appearance.overlay_blur || 0)) + 'px');
		style.setProperty('--wccpa-topbar-bg', appearance.topbar_bg || '#d63638');
		style.setProperty('--wccpa-topbar-text', appearance.topbar_text || '#ffffff');

		this.root.className = 'wccpa-root wccpa-root--shadow-' + (appearance.shadow || 'medium') + ' wccpa-root--animation-' + (behavior.animation || 'fade-scale');
		this.root.classList.toggle('wccpa-root--overlay-off', appearance.overlay_enabled === false || appearance.overlay_enabled === 0 || appearance.overlay_enabled === '0');
		this.topbar.classList.toggle('wccpa-topbar--plain', appearance.topbar === false || appearance.topbar === 0 || appearance.topbar === '0');
		this.title.textContent = alert.title || (window.wccpaFrontend && window.wccpaFrontend.defaultTitle) || 'Alert';
		this.content.innerHTML = alert.content || '';
		this.closeButton.hidden = !(behavior.close_x === true || behavior.close_x === 1 || behavior.close_x === '1');

		var iconName = appearance.icon || 'none';
		this.icon.hidden = !ICONS[iconName];
		this.icon.innerHTML = ICONS[iconName] || '';
		this.configureButton(this.primary, behavior.primary_button || {});
		this.configureButton(this.secondary, behavior.secondary_button || {});
		this.resetCountdownPresentation();
	};

	PopupController.prototype.configureButton = function (element, config) {
		var controller = this;
		var replacement = element.cloneNode(false);
		element.parentNode.replaceChild(replacement, element);
		if (element === this.primary) {
			this.primary = replacement;
		} else {
			this.secondary = replacement;
		}
		element = replacement;
		element.hidden = !config.enabled;
		element.dataset.wccpaBaseLabel = config.label || '';
		element.dataset.wccpaAction = config.action === 'url' && config.url ? 'url' : 'close';
		if (!config.enabled) {
			return;
		}
		element.textContent = config.label || '';
		element.className = 'wccpa-button wccpa-button--' + (config.style === 'secondary' ? 'secondary' : 'primary');
		if (config.action === 'url' && config.url) {
			element.href = config.url;
			if (config.new_tab) {
				element.target = '_blank';
				element.rel = 'noopener noreferrer';
			}
		} else {
			element.href = '#';
			element.addEventListener('click', function (event) {
				event.preventDefault();
				controller.close();
			});
		}
	};

	PopupController.prototype.startAutoClose = function (alert) {
		var controller = this;
		var behavior = alert.behavior || {};
		var styles = ['none', 'text', 'progress', 'close_x', 'close_button'];
		window.clearInterval(this.countdownTimer);
		this.countdownTimer = null;
		this.resetCountdownPresentation();
		if (!behavior.auto_close) {
			return;
		}

		var totalSeconds = Number(behavior.auto_close_seconds || 10);
		if (!Number.isFinite(totalSeconds)) {
			totalSeconds = 10;
		}
		this.autoCloseTotal = Math.max(1000, totalSeconds * 1000);
		this.autoCloseRemaining = this.autoCloseTotal;
		this.autoCloseStartedAt = Date.now();
		this.autoClosePaused = false;
		this.pauseOnHover = behavior.pause_on_hover === true || behavior.pause_on_hover === 1 || behavior.pause_on_hover === '1';
		this.autoCloseStyle = behavior.countdown_style || (behavior.countdown ? 'text' : 'none');
		if (styles.indexOf(this.autoCloseStyle) === -1) {
			this.autoCloseStyle = 'none';
		}

		this.updateAutoClosePresentation(this.autoCloseRemaining);
		this.countdownTimer = window.setInterval(function () {
			controller.tickAutoClose();
		}, 100);
	};

	PopupController.prototype.tickAutoClose = function () {
		if (!this.current || this.autoClosePaused) {
			return;
		}
		var remaining = Math.max(0, this.autoCloseRemaining - (Date.now() - this.autoCloseStartedAt));
		this.updateAutoClosePresentation(remaining);
		if (remaining <= 0) {
			window.clearInterval(this.countdownTimer);
			this.countdownTimer = null;
			this.close();
		}
	};

	PopupController.prototype.updateAutoClosePresentation = function (remaining) {
		var seconds = Math.max(0, Math.ceil(remaining / 1000));
		var elapsed = this.autoCloseTotal > 0 ? 1 - (remaining / this.autoCloseTotal) : 0;
		var progress = Math.max(0, Math.min(100, elapsed * 100));
		var template = window.wccpaFrontend && window.wccpaFrontend.countdown ? window.wccpaFrontend.countdown : 'This message will close in %d seconds.';

		this.countdown.hidden = this.autoCloseStyle !== 'text';
		var countdownMessage = this.autoCloseStyle === 'text' ? template.replace('%d', String(seconds)) : '';
		if (this.countdown.textContent !== countdownMessage) {
			this.countdown.textContent = countdownMessage;
		}
		this.progress.hidden = this.autoCloseStyle !== 'progress';
		this.progress.setAttribute('aria-valuenow', String(Math.round(progress)));
		this.progressBar.style.width = progress + '%';
		this.closeProgress.hidden = this.autoCloseStyle !== 'close_x' || this.closeButton.hidden;
		this.closeProgressValue.style.strokeDashoffset = String(100 - progress);
		this.updateCountdownButton(this.primary, seconds);
		this.updateCountdownButton(this.secondary, seconds);
		this.footer.hidden = this.primary.hidden && this.secondary.hidden && this.countdown.hidden;
	};

	PopupController.prototype.updateCountdownButton = function (element, seconds) {
		var label = element.dataset.wccpaBaseLabel || '';
		var visibleLabel = this.autoCloseStyle === 'close_button' && element.dataset.wccpaAction === 'close' && !element.hidden ? label + ' (' + seconds + ')' : label;
		if (element.textContent !== visibleLabel) {
			element.textContent = visibleLabel;
		}
	};

	PopupController.prototype.resetCountdownPresentation = function () {
		this.autoCloseStyle = 'none';
		this.pauseOnHover = false;
		this.countdown.hidden = true;
		this.countdown.textContent = '';
		this.progress.hidden = true;
		this.progress.setAttribute('aria-valuenow', '0');
		this.progressBar.style.width = '0%';
		this.closeProgress.hidden = true;
		this.closeProgressValue.style.strokeDashoffset = '100';
		this.updateCountdownButton(this.primary, 0);
		this.updateCountdownButton(this.secondary, 0);
		this.footer.hidden = this.primary.hidden && this.secondary.hidden;
	};

	PopupController.prototype.handleMouseEnter = function () {
		if (!this.current || !this.pauseOnHover || !this.countdownTimer || this.autoClosePaused) {
			return;
		}
		this.autoCloseRemaining = Math.max(0, this.autoCloseRemaining - (Date.now() - this.autoCloseStartedAt));
		this.autoClosePaused = true;
		this.updateAutoClosePresentation(this.autoCloseRemaining);
	};

	PopupController.prototype.handleMouseLeave = function () {
		if (!this.current || !this.autoClosePaused) {
			return;
		}
		this.autoCloseStartedAt = Date.now();
		this.autoClosePaused = false;
	};

	PopupController.prototype.handleOverlayClick = function (event) {
		if (this.current && event.target === this.overlay && (this.current.behavior || {}).close_overlay) {
			this.close();
		}
	};

	PopupController.prototype.handleKeydown = function (event) {
		if (!this.current) {
			return;
		}
		if (event.key === 'Escape' && (this.current.behavior || {}).close_esc) {
			event.preventDefault();
			this.close();
			return;
		}
		if (event.key !== 'Tab') {
			return;
		}
		var elements = this.focusableElements();
		if (!elements.length) {
			event.preventDefault();
			this.dialog.focus();
			return;
		}
		var first = elements[0];
		var last = elements[elements.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	};

	PopupController.prototype.focusableElements = function () {
		return Array.prototype.slice.call(this.dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(function (element) {
			return !element.hidden && element.offsetParent !== null;
		});
	};

	PopupController.prototype.close = function () {
		var controller = this;
		if (!this.current) {
			return;
		}
		window.clearInterval(this.countdownTimer);
		this.countdownTimer = null;
		this.autoClosePaused = false;
		this.root.classList.remove('is-open');
		this.root.classList.add('is-closing');
		this.current = null;
		document.removeEventListener('keydown', this.onKeydown, true);
		window.setTimeout(function () {
			controller.root.hidden = true;
			controller.root.setAttribute('aria-hidden', 'true');
			controller.root.classList.remove('is-closing');
			document.body.style.overflow = controller.previousOverflow;
			if (controller.previousFocus && typeof controller.previousFocus.focus === 'function') {
				controller.previousFocus.focus();
			}
			controller.queue.shift();
			controller.showNext();
		}, 190);
	};

	function initFrontend() {
		var data = document.getElementById('wccpa-data');
		var root = document.getElementById('wccpa-root');
		if (!data || !root) {
			return;
		}
		try {
			var alerts = JSON.parse(data.textContent || '[]');
			new PopupController(root, alerts).start();
		} catch (error) {
			// A malformed payload must never break the host page.
		}
	}

	window.WCCPA = {
		preview: function (alert) {
			var previous = document.getElementById('wccpa-preview-root');
			if (previous && previous.parentNode) {
				previous.parentNode.removeChild(previous);
			}
			var root = createShell('wccpa-preview-root');
			new PopupController(root, [alert], { preview: true, removeOnFinish: true }).start();
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFrontend);
	} else {
		initFrontend();
	}
}());
