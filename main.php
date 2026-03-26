<?php
/**
 * Entry point for the RL Options Framework library.
 *
 * Usage:
 *   require_once __DIR__ . '/main.php';
 *
 * The class_exists guard prevents fatal errors if the framework is
 * accidentally loaded more than once (e.g., by two plugins sharing
 * the same library copy, or via Composer autoloading + manual require).
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
