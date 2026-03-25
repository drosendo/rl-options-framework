<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Country implements RL_Field_Interface
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
}
