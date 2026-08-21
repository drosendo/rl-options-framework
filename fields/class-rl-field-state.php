<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_State implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'state';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$geo = $context['geo_options_callback'] ?? null;
		$options_state = $context['options_state'] ?? [];
		if (!is_callable($geo)) {
			return;
		}
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$country_source = isset($field['country_field']) ? (string) $field['country_field'] : '';
		$options = call_user_func($geo, $field, 'state', is_array($options_state) ? $options_state : []);

		printf(
			'<select id="%1$s" name="%2$s" class="rl-geo-state" data-country="%4$s" data-country-field="%5$s">',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr((string) $value),
			esc_attr((string) ($field['country'] ?? '')),
			esc_attr($country_source)
		);
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
		$allowed = array_keys(call_user_func($geo_callback, $field, 'state', $context['validation_context'] ?? []));
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

		$allowed = array_keys(call_user_func($geo_callback, $field, 'state', $context['validation_context'] ?? []));
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
