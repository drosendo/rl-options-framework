<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Country_State_City implements RL_Field_Interface, RL_Field_Processing_Interface
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
		$country_label = (string) ($field['country_label'] ?? __('Country', 'smart-variations-images-premium'));
		$state_label = (string) ($field['state_label'] ?? __('State', 'smart-variations-images-premium'));
		$city_label = (string) ($field['city_label'] ?? __('City', 'smart-variations-images-premium'));
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

	public function sanitize(array $field, $value, array $context = [])
	{
		$raw_group = is_array($value) ? $value : [];
		$country = sanitize_text_field((string) ($raw_group['country'] ?? ''));
		$state = sanitize_text_field((string) ($raw_group['state'] ?? ''));
		$city = sanitize_text_field((string) ($raw_group['city'] ?? ''));

		$geo_callback = $context['geo_options_callback'] ?? null;
		if (!is_callable($geo_callback)) {
			return ['country' => '', 'state' => '', 'city' => ''];
		}

		$allowed_countries = array_keys(call_user_func($geo_callback, $field, 'country', $context['validation_context'] ?? []));
		if (!in_array($country, $allowed_countries, true)) {
			$country = '';
		}

		$allowed_states = array_keys(call_user_func($geo_callback, array_merge($field, ['country' => $country]), 'state', $context['validation_context'] ?? []));
		if (!in_array($state, $allowed_states, true)) {
			$state = '';
		}

		$allowed_cities = array_keys(call_user_func($geo_callback, array_merge($field, ['country' => $country, 'subdivision' => $state]), 'city', $context['validation_context'] ?? []));
		if (!in_array($city, $allowed_cities, true)) {
			$city = '';
		}

		return [
			'country' => $country,
			'state' => $state,
			'city' => $city,
		];
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		$group = is_array($value) ? $value : [];
		$country = sanitize_text_field((string) ($group['country'] ?? ''));
		$state = sanitize_text_field((string) ($group['state'] ?? ''));
		$city = sanitize_text_field((string) ($group['city'] ?? ''));
		$required = !empty($field['required']);

		$geo_callback = $context['geo_options_callback'] ?? null;
		if (!is_callable($geo_callback)) {
			/* translators: %s: field label */
			$error = sprintf(__('%s has an invalid geographic value.', 'smart-variations-images-premium'), $field_label);
			return false;
		}

		if ($required && $country === '') {
			/* translators: %s: field label */
			$error = sprintf(__('%s requires a country selection.', 'smart-variations-images-premium'), $field_label);
			return false;
		}

		if ($country !== '') {
			$allowed_countries = array_keys(call_user_func($geo_callback, $field, 'country', $context['validation_context'] ?? []));
			if (!in_array($country, $allowed_countries, true)) {
				/* translators: %s: field label */
				$error = sprintf(__('%s has an invalid country.', 'smart-variations-images-premium'), $field_label);
				return false;
			}
		}

		if ($state !== '') {
			$allowed_states = array_keys(call_user_func($geo_callback, array_merge($field, ['country' => $country]), 'state', $context['validation_context'] ?? []));
			if (!in_array($state, $allowed_states, true)) {
				/* translators: %s: field label */
				$error = sprintf(__('%s has an invalid state/district.', 'smart-variations-images-premium'), $field_label);
				return false;
			}
		}

		if ($city !== '') {
			$allowed_cities = array_keys(call_user_func($geo_callback, array_merge($field, ['country' => $country, 'subdivision' => $state]), 'city', $context['validation_context'] ?? []));
			if (!in_array($city, $allowed_cities, true)) {
				/* translators: %s: field label */
				$error = sprintf(__('%s has an invalid city/municipality.', 'smart-variations-images-premium'), $field_label);
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

