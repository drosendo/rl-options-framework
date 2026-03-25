<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Number implements RL_Field_Interface
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
}
