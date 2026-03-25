<?php
/**
 * Entry point for the RL Options Framework library.
 *
 * This is a generic, reusable options framework that can be used by any WordPress plugin.
 * It should not contain any plugin-specific code.
 *
 * @package RL_Options_Framework
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/class-rl-logger.php';
require_once __DIR__ . '/class-rl-options-framework.php';
