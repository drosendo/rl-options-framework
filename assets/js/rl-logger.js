(function (window) {
	'use strict';

	const framework = window.rlFramework || {};

	const DEBUG_LEVELS = { error: 0, warn: 1, info: 2, debug: 3 };
	
	const configuredLevel = String(framework.debug_level || window.rlFrameworkDebugLevel || 'error').toLowerCase();
	const currentLevel = Object.prototype.hasOwnProperty.call(DEBUG_LEVELS, configuredLevel)
		? configuredLevel
		: 'error';

	if (typeof window.rlFrameworkDebug === 'undefined') {
		window.rlFrameworkDebug = false;
	}

	const shouldLog = function(level) {
		const target = Object.prototype.hasOwnProperty.call(DEBUG_LEVELS, level) ? level : 'debug';
		return DEBUG_LEVELS[target] <= DEBUG_LEVELS[currentLevel] || window.rlFrameworkDebug === true;
	};

	const getLogPrefix = () => framework.logPrefix || 'RL Framework';

	const rlLog = function(...args) {
		if (shouldLog('debug')) {
			console.log('%c[' + getLogPrefix() + ']', 'color: #4CAF50; font-weight: bold;', ...args);
		}
	};

	const rlInfo = function(...args) {
		if (shouldLog('info')) {
			console.info('%c[' + getLogPrefix() + ' INFO]', 'color: #2196F3; font-weight: bold;', ...args);
		}
	};

	const rlWarn = function(...args) {
		if (shouldLog('warn')) {
			console.warn('%c[' + getLogPrefix() + ' WARN]', 'color: #ff9800; font-weight: bold;', ...args);
		}
	};

	const rlError = function(...args) {
		if (shouldLog('error')) {
			console.error('%c[' + getLogPrefix() + ' ERROR]', 'color: #f44336; font-weight: bold;', ...args);
		}
	};

	framework.log = rlLog;
	framework.info = rlInfo;
	framework.warn = rlWarn;
	framework.error = rlError;
	framework.setLogPrefix = function(prefix) { framework.logPrefix = prefix; };
	window.rlFramework = framework;
})(window);
