<?php
/**
 * Entry point for the RL Options Framework library.
 *
 * Two supported installation methods:
 *
 * 1. Manual (drop-in):
 *    Copy the library folder into your plugin/theme and call:
 *      require_once __DIR__ . '/path/to/rloptionsFramework/main.php';
 *
 * 2. Composer:
 *    composer require rosendolabs/rl-options-framework
 *    Then include vendor/autoload.php — this file runs automatically
 *    via the "files" autoload directive.
 *
 * Both methods are safe to use simultaneously. The class_exists guard
 * below prevents fatal errors if the framework ends up required more
 * than once (e.g., two plugins bundling the same library, or Composer
 * autoload + a manual require coexisting in the same project).
 *
 * @package RL_Options_Framework
 * @version 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Guard against duplicate class declarations on multiple requires.
if ( ! class_exists( 'RL_Options_Framework', false ) ) {
	require_once __DIR__ . '/class-rl-logger.php';
	require_once __DIR__ . '/class-rl-options-framework.php';
	require_once __DIR__ . '/services/class-rl-options-render-service.php';
	require_once __DIR__ . '/services/class-rl-options-admin-handler.php';
	require_once __DIR__ . '/services/class-rl-options-schema-manager.php';
	require_once __DIR__ . '/services/class-rl-options-rest-api.php';
	require_once __DIR__ . '/services/class-rl-options-storage-service.php';
	require_once __DIR__ . '/services/class-rl-options-field-processor.php';
	require_once __DIR__ . '/services/class-rl-options-assets-service.php';
}
