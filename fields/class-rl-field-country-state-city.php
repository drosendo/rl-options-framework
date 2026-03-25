<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Country_State_City implements RL_Field_Interface
{
	public function type(): string
	{
		return 'country_state_city';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$geo = $context['geo_options_callback'] ?? null;
		if (!is_callable($geo)) {
			return;
		}
		$field_id = (string) ($field['id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$text_domain = (string) ($context['text_domain'] ?? 'default');
		$group = is_array($value) ? $value : [];
		$country_val = sanitize_text_field((string) ($group['country'] ?? ($field['default']['country'] ?? '')));
		$state_val = sanitize_text_field((string) ($group['state'] ?? ($field['default']['state'] ?? '')));
		$city_val = sanitize_text_field((string) ($group['city'] ?? ($field['default']['city'] ?? '')));
		$country_label = (string) ($field['country_label'] ?? __('Country', $text_domain));
		$state_label = (string) ($field['state_label'] ?? __('State', $text_domain));
		$city_label = (string) ($field['city_label'] ?? __('City', $text_domain));
		$countries = call_user_func($geo, $field, 'country', []);
		$states = call_user_func($geo, array_merge($field, ['country' => $country_val]), 'state', []);
		$cities = call_user_func($geo, array_merge($field, ['country' => $country_val, 'subdivision' => $state_val]), 'city', []);

		echo '<div class="rl-country-state-city-field" data-field-id="' . esc_attr($field_id) . '">';
		echo '<div class="rl-csc-col"><label>' . esc_html($country_label) . '</label>';
		echo '<select class="rl-csc-country" name="' . esc_attr($field_name . '[country]') . '">';
		echo '<option value=""></option>';
		foreach ((array) $countries as $k => $v) {
			printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr((string) $k), selected($country_val, $k, false), esc_html((string) $v));
		}
		echo '</select></div>';

		echo '<div class="rl-csc-col"><label>' . esc_html($state_label) . '</label>';
		echo '<select class="rl-csc-state" name="' . esc_attr($field_name . '[state]') . '">';
		echo '<option value=""></option>';
		foreach ((array) $states as $k => $v) {
			printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr((string) $k), selected($state_val, $k, false), esc_html((string) $v));
		}
		echo '</select></div>';

		echo '<div class="rl-csc-col"><label>' . esc_html($city_label) . '</label>';
		echo '<select class="rl-csc-city" name="' . esc_attr($field_name . '[city]') . '">';
		echo '<option value=""></option>';
		foreach ((array) $cities as $k => $v) {
			printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr((string) $k), selected($city_val, $k, false), esc_html((string) $v));
		}
		echo '</select></div>';
		echo '</div>';
	}
}
