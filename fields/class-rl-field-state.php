<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_State implements RL_Field_Interface
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
}
