<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Image_Select implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'image_select';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$options = $field['options'] ?? [];
		echo '<div class="rl-image-select-options">';
		foreach ($options as $option_value => $option_data) {
			if (is_array($option_data)) {
				$label = $option_data['label'] ?? '';
				$image = $option_data['src'] ?? '';
			} else {
				$label = $option_data;
				$image = '';
			}

			$radio_id = $input_id . '_' . sanitize_key((string) $option_value);
			printf(
				'<label class="rl-image-select-option" for="%1$s"><input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s><img src="%5$s" alt="%6$s" style="max-width: 100px; height: auto;"><span class="rl-image-select-label">%6$s</span></label>',
				esc_attr($radio_id),
				esc_attr($field_name),
				esc_attr((string) $option_value),
				checked($value, $option_value, false),
				esc_url((string) $image),
				esc_html((string) $label)
			);
		}
		echo '</div>';
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		$allowed_option_keys_callback = $context['allowed_option_keys_callback'] ?? null;
		$allowed = is_callable($allowed_option_keys_callback) ? $allowed_option_keys_callback($field, $context['validation_context'] ?? []) : array_keys($field['options'] ?? []);
		$allowed = array_map('strval', $allowed);
		return in_array((string) $value, $allowed, true) ? (string) $value : ($field['default'] ?? null);
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

		if (!in_array((string) $value, $allowed, true)) {
			$error = sprintf(
				/* translators: %s: field label */
				__('%s has an invalid option selected.', 'smart-variations-images-premium'),
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
