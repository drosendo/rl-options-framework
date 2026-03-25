<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Checkbox implements RL_Field_Interface
{
	public function type(): string
	{
		return 'checkbox';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		printf(
			'<label class="rl-checkbox"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> <span>%4$s</span></label>',
			esc_attr($input_id),
			esc_attr($field_name),
			checked(!empty($value), true, false),
			esc_html((string) ($field['text'] ?? ''))
		);
	}
}
