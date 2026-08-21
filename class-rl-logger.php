<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Logger - Centralized logging utility for RL Options Framework
 *
 * @package RL_Options_Framework
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Generic logging utility class with enable/disable capability.
 */
final class RL_Logger {
	/**
	 * Current minimum log level.
	 * Supported: error, warn, info, debug
	 *
	 * @var string
	 */
	private static string $level = 'error';

	/**
	 * Log prefix for identifying framework logs.
	 *
	 * @var string
	 */
	private static string $prefix = '[RL Framework]';

	private const LEVEL_MAP = [
		'error' => 0,
		'warn'  => 1,
		'info'  => 2,
		'debug' => 3,
	];

	/**
	 * Set minimum log level.
	 */
	public static function set_level( string $level ): void {
		$level = strtolower( trim( $level ) );
		if ( ! isset( self::LEVEL_MAP[ $level ] ) ) {
			$level = 'error';
		}

		self::$level = $level;
	}

	public static function get_level(): string {
		return self::$level;
	}

	private static function should_log( string $level ): bool {
		$current = self::LEVEL_MAP[ self::$level ] ?? self::LEVEL_MAP['error'];
		$target  = self::LEVEL_MAP[ $level ] ?? self::LEVEL_MAP['error'];

		return $target <= $current;
	}

	private static function build_message( string $level, string $message, array $context ): string {
		$formatted_message = self::$prefix . ' [' . strtoupper( $level ) . '] ' . $message;

		if ( ! empty( $context ) ) {
			foreach ( $context as $item ) {
				$item = self::sanitize_context_for_log( $item );
				if ( is_array( $item ) || is_object( $item ) ) {
					$formatted_message .= ' ' . wp_json_encode( $item );
				} else {
					$formatted_message .= ' ' . $item;
				}
			}
		}

		return $formatted_message;
	}

	/**
	 * Remove sensitive values from context before writing logs.
	 *
	 * @param mixed $value Context item.
	 * @return mixed
	 */
	private static function sanitize_context_for_log( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = [];
			foreach ( $value as $key => $item ) {
				$key_string = is_string( $key ) ? strtolower( $key ) : '';
				if ( $key_string !== '' && preg_match( '/(nonce|token|password|secret|api[_-]?key|authorization|cookie|set-cookie)/i', $key_string ) ) {
					$sanitized[ $key ] = '[REDACTED]';
					continue;
				}

				$sanitized[ $key ] = self::sanitize_context_for_log( $item );
			}

			return $sanitized;
		}

		if ( is_object( $value ) ) {
			return self::sanitize_context_for_log( (array) $value );
		}

		return $value;
	}

	private static function write( string $level, string $message, ...$context ): void {
		if ( ! self::should_log( $level ) ) {
			return;
		}

		$logger = 'error_log';
		$logger( self::build_message( $level, $message, $context ) );
	}

	/**
	 * Backward-compatible alias for debug logs.
	 */
	public static function log( string $message, ...$context ): void {
		self::debug( $message, ...$context );
	}

	public static function debug( string $message, ...$context ): void {
		self::write( 'debug', $message, ...$context );
	}

	public static function info( string $message, ...$context ): void {
		self::write( 'info', $message, ...$context );
	}

	public static function warn( string $message, ...$context ): void {
		self::write( 'warn', $message, ...$context );
	}

	/**
	 * Log an error message (always logged, even if debug is disabled).
	 *
	 * @param string $message Message to log.
	 * @param mixed  ...$context Additional context variables.
	 */
	public static function error( string $message, ...$context ): void {
		self::write( 'error', $message, ...$context );
	}

	/**
	 * Legacy helpers kept for compatibility.
	 */
	public static function enable(): void {
		self::set_level( 'debug' );
	}

	public static function disable(): void {
		self::set_level( 'error' );
	}

	public static function is_enabled(): bool {
		return self::$level !== 'error';
	}
}
