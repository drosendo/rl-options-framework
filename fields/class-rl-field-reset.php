<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Reset implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'reset';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$label = $field['button_label'] ?? __('Reset All Settings', 'smart-variations-images-premium');
		$desc = $field['description'] ?? __('This will permanently delete all settings and restore the default configuration. This cannot be undone.', 'smart-variations-images-premium');
		$confirm_msg = $field['confirm_message'] ?? __('Are you sure you want to reset ALL settings to their defaults? This cannot be undone.', 'smart-variations-images-premium');

		if (!empty($desc)) {
			echo '<p>' . wp_kses_post($desc) . '</p>';
		}

		$framework = RL_Options_Framework::instance();
		$reset_input_name = $framework->get_config('form_field_prefix') . '_reset_settings';

		// Hidden input to capture the reset intent
		printf(
			'<input type="hidden" name="%s" class="rl-field-reset-input" value="0">',
			esc_attr($reset_input_name)
		);

		printf(
			'<button type="button" class="button button-secondary rl-field-reset-btn" data-confirm-msg="%s" style="margin-top:10px; color:#a00; border-color:#a00;">%s</button>',
			esc_attr($confirm_msg),
			esc_html($label)
		);
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		// Intercepted by admin handler.
		return null;
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
