<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Date implements RL_Field_Interface
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
			$attrs
		);
	}
}
