<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Field Types – Typed field aliases with built-in validation and sanitization
 *
 * Provides semantic field types that map to base types with pre-configured validation:
 * - email: text with email validation
 * - phone: text with phone validation
 * - postal_code: text with postal code validation
 * - url: text with URL validation
 * - nif: text with Portuguese NIF validation
 *
 * @package RL_Options_Framework
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Types
{
	/**
	 * Get field type definition for a typed alias
	 *
	 * @param string $type_alias Alias identifier (email, phone, nif, url, postal_code)
	 * @return array|null Field type configuration or null if not recognized
	 */
	public static function get_type_definition(string $type_alias): ?array
	{
		$definitions = [
			'email' => [
				'base_type'  => 'text',
				'sanitize'   => 'sanitize_email',
				'validate'   => 'is_email',
				'attributes' => [
					'type'        => 'email',
					'placeholder' => 'user@example.com',
				],
			],

			'phone' => [
				'base_type'  => 'text',
				'sanitize'   => 'sanitize_text_field',
				'validate'   => [__CLASS__, 'validate_phone'],
				'attributes' => [
					'type'        => 'tel',
					'placeholder' => '+351 ...',
					'pattern'     => '\\+?[0-9\\s\\-\\(\\)]+',
				],
			],

			'postal_code' => [
				'base_type'  => 'text',
				'sanitize'   => 'sanitize_text_field',
				'validate'   => [__CLASS__, 'validate_postal_code'],
				'attributes' => [
					'placeholder' => '1000-001',
					'pattern'     => '[0-9]{4}-[0-9]{3}',
					'maxlength'   => 8,
				],
			],

			'url' => [
				'base_type'  => 'text',
				'sanitize'   => 'esc_url_raw',
				'validate'   => 'wp_http_validate_url',
				'attributes' => [
					'type'        => 'url',
					'placeholder' => 'https://example.com',
				],
			],

			'nif' => [
				'base_type'  => 'text',
				'sanitize'   => [__CLASS__, 'sanitize_nif'],
				'validate'   => [__CLASS__, 'validate_nif'],
				'attributes' => [
					'placeholder' => '123456789',
					'pattern'     => '[0-9]{9}',
					'maxlength'   => 9,
				],
			],
		];

		return $definitions[$type_alias] ?? null;
	}

	/**
	 * Check if a field type is a recognized typed alias
	 *
	 * @param string $type Field type to check
	 * @return bool True if alias exists
	 */
	public static function is_typed_alias(string $type): bool
	{
		return in_array($type, ['email', 'phone', 'postal_code', 'url', 'nif'], true);
	}

	/**
	 * Expand a typed alias into a full field definition
	 *
	 * @param array $field Field definition with type alias
	 * @return array Expanded field definition with base_type and merged attributes
	 */
	public static function expand_typed_field(array $field): array
	{
		if (empty($field['type']) || !self::is_typed_alias($field['type'])) {
			return $field;
		}

		$definition = self::get_type_definition($field['type']);
		if (!$definition) {
			return $field;
		}

		// Store original type alias for reference
		$field['type_alias'] = $field['type'];

		// Replace type with base type
		$field['type'] = $definition['base_type'];

		// Merge sanitize/validate if not already set
		if (empty($field['sanitize']) && !empty($definition['sanitize'])) {
			$field['sanitize'] = $definition['sanitize'];
		}
		if (empty($field['validate']) && !empty($definition['validate'])) {
			$field['validate'] = $definition['validate'];
		}

		// Merge attributes
		if (!empty($definition['attributes'])) {
			$field = array_merge($definition['attributes'], $field);
		}

		return $field;
	}

	/**
	 * Validate phone number (basic international format)
	 *
	 * @param mixed $value Value to validate
	 * @return bool True if valid
	 */
	public static function validate_phone($value): bool
	{
		if (empty($value)) {
			return true; // Optional field
		}

		// Allow digits, spaces, hyphens, parentheses, and + prefix
		return (bool) preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $value);
	}

	/**
	 * Validate Portuguese postal code format (XXXX-XXX)
	 *
	 * @param mixed $value Value to validate
	 * @return bool True if valid
	 */
	public static function validate_postal_code($value): bool
	{
		if (empty($value)) {
			return true; // Optional field
		}

		return (bool) preg_match('/^[0-9]{4}-[0-9]{3}$/', $value);
	}

	/**
	 * Sanitize Portuguese NIF (remove non-digits)
	 *
	 * @param mixed $value Value to sanitize
	 * @return string Sanitized NIF (digits only)
	 */
	public static function sanitize_nif($value): string
	{
		return preg_replace('/[^0-9]/', '', (string) $value);
	}

	/**
	 * Validate Portuguese NIF (9 digits with checksum)
	 *
	 * @param mixed $value Value to validate
	 * @return bool True if valid
	 */
	public static function validate_nif($value): bool
	{
		if (empty($value)) {
			return true; // Optional field
		}

		$nif = preg_replace('/[^0-9]/', '', (string) $value);

		// Must be exactly 9 digits
		if (strlen($nif) !== 9) {
			return false;
		}

		// Calculate checksum
		$check = 0;
		for ($i = 0; $i < 8; $i++) {
			$check += (int) $nif[$i] * (9 - $i);
		}

		$checksum = 11 - ($check % 11);
		if ($checksum >= 10) {
			$checksum = 0;
		}

		return (int) $nif[8] === $checksum;
	}
}
