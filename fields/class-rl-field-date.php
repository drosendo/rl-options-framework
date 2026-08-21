<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Date implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'date';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$current_value = trim((string) ($value !== null && $value !== '' ? $value : ($field['default'] ?? '')));
		if ($current_value !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $current_value, $matches)) {
			if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
				$current_value = '';
			}
		} elseif ($current_value !== '') {
			$current_value = '';
		}

		$attrs = '';
		if (isset($field['min'])) {
			$attrs .= ' min="' . esc_attr((string) $field['min']) . '"';
		}
		if (isset($field['max'])) {
			$attrs .= ' max="' . esc_attr((string) $field['max']) . '"';
		}

		printf(
			'<input type="date" id="%1$s" name="%2$s" value="%3$s"%4$s />',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr($current_value),
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		if ($value === null || $value === '') {
			return '';
		}

		$raw = trim(sanitize_text_field((string) $value));
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches) && checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
			return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
		}

		$fallback = trim((string) ($field['default'] ?? ''));
		if ($fallback !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fallback, $matches) && checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
			return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
		}

		return '';
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		if ($value === '' || $value === null) {
			return true;
		}

		$raw = trim((string) $value);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches) || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
			$error = sprintf(
				/* translators: %s: field label */
				__('%s must be a valid date (YYYY-MM-DD).', 'smart-variations-images-premium'),
				$field_label
			);
			return false;
		}

		if (isset($field['min']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $field['min'], $min_match) && checkdate((int) $min_match[2], (int) $min_match[3], (int) $min_match[1]) && strcmp($raw, (string) $field['min']) < 0) {
			$error = sprintf(
				/* translators: 1: field label, 2: min date */
				__('%1$s must be on or after %2$s.', 'smart-variations-images-premium'),
				$field_label,
				$field['min']
			);
			return false;
		}

		if (isset($field['max']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $field['max'], $max_match) && checkdate((int) $max_match[2], (int) $max_match[3], (int) $max_match[1]) && strcmp($raw, (string) $field['max']) > 0) {
			$error = sprintf(
				/* translators: 1: field label, 2: max date */
				__('%1$s must be on or before %2$s.', 'smart-variations-images-premium'),
				$field_label,
				$field['max']
			);
			return false;
		}

		return true;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		return $value;
	}
}
