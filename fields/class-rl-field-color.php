<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Color implements RL_Field_Interface
{
	public function type(): string
	{
		return 'color';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		printf(
			'<input type="text" id="%1$s" class="rl-color-field" name="%2$s" value="%3$s" data-default-color="%4$s" />',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr((string) $value),
			esc_attr((string) ($field['default'] ?? ''))
		);
	}
}
