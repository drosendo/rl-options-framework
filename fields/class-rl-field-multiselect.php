<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Multiselect implements RL_Field_Interface
{
	public function type(): string
	{
		return 'multiselect';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$options = $field['options'] ?? [];
		$values = is_array($value) ? $value : [];

		printf(
			'<select id="%1$s" name="%2$s[]" multiple size="%3$d">',
			esc_attr($input_id),
			esc_attr($field_name),
			max(3, min(6, count($options)))
		);
		foreach ($options as $option_value => $option_label) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr((string) $option_value),
				selected(in_array($option_value, $values, true), true, false),
				esc_html((string) $option_label)
			);
		}
		echo '</select>';
	}
}
