<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Textarea implements RL_Field_Interface
{
	public function type(): string
	{
		return 'textarea';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		printf(
			'<textarea id="%1$s" name="%2$s" rows="%4$d">%3$s</textarea>',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_textarea((string) $value),
			isset($field['rows']) ? absint($field['rows']) : 5
		);
	}
}
