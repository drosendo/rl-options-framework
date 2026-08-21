<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Multiselect implements RL_Field_Interface, RL_Field_Processing_Interface
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
			(int) max(3, min(6, count($options)))
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

	public function sanitize(array $field, $value, array $context = [])
	{
		$allowed_option_keys_callback = $context['allowed_option_keys_callback'] ?? null;
		$allowed = is_callable($allowed_option_keys_callback) ? $allowed_option_keys_callback($field, $context['validation_context'] ?? []) : array_keys($field['options'] ?? []);
		$values = is_array($value) ? array_values($value) : [];
		return array_values(array_intersect($allowed, array_map('sanitize_text_field', $values)));
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		if ($value === '' || $value === null) {
			return true;
		}

		$allowed_option_keys_callback = $context['allowed_option_keys_callback'] ?? null;
		$allowed = is_callable($allowed_option_keys_callback) ? $allowed_option_keys_callback($field, $context['validation_context'] ?? []) : array_keys($field['options'] ?? []);
		$values = is_array($value) ? $value : [$value];

		foreach ($values as $item) {
			if (!in_array((string) $item, $allowed, true)) {
				$error = sprintf(
					/* translators: %s: field label */
					__('%s includes an invalid option.', 'smart-variations-images-premium'),
					$field_label
				);
				return false;
			}
		}

		return true;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		return $value;
	}
}
