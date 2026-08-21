<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Country implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'country';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$geo = $context['geo_options_callback'] ?? null;
		if (!is_callable($geo)) {
			return;
		}
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$options = call_user_func($geo, $field, 'country', []);

		printf('<select id="%1$s" name="%2$s" class="rl-geo-country">', esc_attr($input_id), esc_attr($field_name));
		if (!empty($field['placeholder'])) {
			printf('<option value="">%s</option>', esc_html((string) $field['placeholder']));
		}
		foreach ((array) $options as $option_value => $option_label) {
			printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr((string) $option_value), selected($value, $option_value, false), esc_html((string) $option_label));
		}
		echo '</select>';
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		$raw = sanitize_text_field((string) $value);
		$geo_callback = $context['geo_options_callback'] ?? null;
		if (!is_callable($geo_callback)) {
			return '';
		}
		$allowed = array_keys(call_user_func($geo_callback, $field, 'country', $context['validation_context'] ?? []));
		return in_array($raw, $allowed, true) ? $raw : '';
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		if ($value === '' || $value === null) {
			return true;
		}

		$geo_callback = $context['geo_options_callback'] ?? null;
		if (!is_callable($geo_callback)) {
			/* translators: %s: field label */
			$error = sprintf(__('%s has an invalid geographic value.', 'smart-variations-images-premium'), $field_label);
			return false;
		}

		$allowed = array_keys(call_user_func($geo_callback, $field, 'country', $context['validation_context'] ?? []));
		if (!in_array((string) $value, $allowed, true)) {
			/* translators: %s: field label */
			$error = sprintf(__('%s has an invalid geographic value.', 'smart-variations-images-premium'), $field_label);
			return false;
		}

		return true;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		return $value;
	}
}
