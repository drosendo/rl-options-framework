<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Text implements RL_Field_Interface
{
	public function type(): string
	{
		return 'text';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr((string) $value)
		);
	}
}
