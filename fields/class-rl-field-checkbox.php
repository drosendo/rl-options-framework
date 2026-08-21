<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Checkbox implements RL_Field_Interface, RL_Field_Processing_Interface
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

	public function sanitize(array $field, $value, array $context = [])
	{
		return !empty($value);
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		return true;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		return $value;
	}
}
