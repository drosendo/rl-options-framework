<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Select implements RL_Field_Interface
{
	public function type(): string
	{
		return 'select';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$options = $field['options'] ?? [];
		printf('<select id="%1$s" name="%2$s">', esc_attr($input_id), esc_attr($field_name));
		foreach ($options as $option_value => $option_label) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr((string) $option_value),
				selected($value, $option_value, false),
				esc_html((string) $option_label)
			);
		}
		echo '</select>';
	}
}
