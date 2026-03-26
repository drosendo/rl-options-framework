<?php
/**
 * Entry point for the RL Options Framework library.
 *
 * Supports two loading strategies:
 *
 * 1. Composer autoloading (recommended):
 *    Install via `composer require rosendolabs/rl-options-framework`.
 *    Include `vendor/autoload.php` in your plugin/theme. This file is
 *    executed automatically via the `files` autoload directive and will
 *    skip the manual requires below if classes are already registered.
 *
 * 2. Legacy manual loading:
 *    Drop the library folder anywhere and call:
 *    `require_once __DIR__ . '/main.php';`
 *    All classes are loaded via explicit require_once calls.
 *
 * @package RL_Options_Framework
 * @version 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// When loaded via Composer, the classmap autoloader already registered all
// classes. Skip manual requires to avoid duplicate class declarations.
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
