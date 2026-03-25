<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Number implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'number';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$attrs = '';
		if (isset($field['min'])) {
			$attrs .= ' min="' . esc_attr((string) $field['min']) . '"';
		}
		if (isset($field['max'])) {
			$attrs .= ' max="' . esc_attr((string) $field['max']) . '"';
		}
		if (isset($field['step'])) {
			$attrs .= ' step="' . esc_attr((string) $field['step']) . '"';
		}

		printf(
			'<input type="number" id="%1$s" name="%2$s" value="%3$s"%4$s />',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr((string) (is_numeric($value) ? $value : ($field['default'] ?? ''))),
			$attrs
		);
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		if (isset($field['step']) && floatval($field['step']) !== intval($field['step'])) {
			return isset($value) ? floatval($value) : null;
		}
		return isset($value) ? intval($value) : null;
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		if (!is_numeric($value)) {
			$error = sprintf(
				__('%s must be a valid number.', $text_domain),
				$field_label
			);
			return false;
		}

		if (isset($field['min']) && $value < $field['min']) {
			$error = sprintf(
				__('%1$s must be at least %2$s.', $text_domain),
				$field_label,
				$field['min']
			);
			return false;
		}

		if (isset($field['max']) && $value > $field['max']) {
			$error = sprintf(
				__('%1$s must be no more than %2$s.', $text_domain),
				$field_label,
				$field['max']
			);
			return false;
		}

		return true;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		if ($value !== null && $value !== '' && is_numeric($value)) {
			$numeric = $value;
			if (isset($field['min']) && is_numeric($field['min']) && (float) $numeric < (float) $field['min']) {
				$numeric = $field['min'];
			}
			if (isset($field['max']) && is_numeric($field['max']) && (float) $numeric > (float) $field['max']) {
				$numeric = $field['max'];
			}
			return $numeric;
		}

		if ($value !== null && $value !== '') {
			return $value;
		}

		$fallback = $field['default'] ?? null;
		if (!is_numeric($fallback)) {
			$fallback = isset($field['min']) && is_numeric($field['min']) ? $field['min'] : 0;
		}
		if (isset($field['min']) && is_numeric($field['min']) && (float) $fallback < (float) $field['min']) {
			$fallback = $field['min'];
		}
		if (isset($field['max']) && is_numeric($field['max']) && (float) $fallback > (float) $field['max']) {
			$fallback = $field['max'];
		}
		return $fallback;
	}
}
