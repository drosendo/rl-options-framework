<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Radio implements RL_Field_Interface
{
	public function type(): string
	{
		return 'radio';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$options = $field['options'] ?? [];
		foreach ($options as $option_value => $option_label) {
			$radio_id = $input_id . '_' . sanitize_key((string) $option_value);
			printf(
				'<label class="rl-radio"><input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s> <span>%5$s</span></label>',
				esc_attr($radio_id),
				esc_attr($field_name),
				esc_attr((string) $option_value),
				checked($value, $option_value, false),
				esc_html((string) $option_label)
			);
		}
	}
}
