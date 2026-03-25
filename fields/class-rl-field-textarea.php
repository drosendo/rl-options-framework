<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Textarea implements RL_Field_Interface, RL_Field_Processing_Interface
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

	public function sanitize(array $field, $value, array $context = [])
	{
		return isset($value) ? wp_kses_post($value) : '';
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		if (isset($field['maxlength']) && mb_strlen($value) > $field['maxlength']) {
			$error = sprintf(
				__('%1$s must be no more than %2$s characters.', $text_domain),
				$field_label,
				$field['maxlength']
			);
			return false;
		}

		if (isset($field['pattern']) && !preg_match($field['pattern'], $value)) {
			$error = sprintf(
				__('%s has an invalid format.', $text_domain),
				$field_label
			);
			return false;
		}

		return true;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		return $value;
	}
}
