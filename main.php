<?php
/**
 * Entry point for the RL Options Framework library.
 *
 * This is a generic, reusable options framework that can be used by any WordPress plugin.
 * It should not contain any plugin-specific code.
 *
 * @package RL_Options_Framework
 * @version 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/class-rl-logger.php';
require_once __DIR__ . '/class-rl-options-framework.php';
require_once __DIR__ . '/services/class-rl-options-render-service.php';
require_once __DIR__ . '/services/class-rl-options-admin-handler.php';
require_once __DIR__ . '/services/class-rl-options-schema-manager.php';
require_once __DIR__ . '/services/class-rl-options-rest-api.php';
require_once __DIR__ . '/services/class-rl-options-storage-service.php';
require_once __DIR__ . '/services/class-rl-options-field-processor.php';
