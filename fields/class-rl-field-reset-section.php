<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Options Field - Reset Section
 *
 * @package RL_Options_Framework
 */

if (!defined('ABSPATH')) {
	return;
}

/**
 * Reset Section field type.
 */
class RL_Field_Reset_Section implements RL_Field_Interface, RL_Field_Processing_Interface
{
	/**
	 * Get field type identifier.
	 */
	public function type(): string
	{
		return 'reset_section';
	}

	/**
	 * Render the field.
	 */
	public function render(array $field, $value, array $context = []): void
	{
		$label = $field['button_label'] ?? __('Reset Section Settings', 'smart-variations-images-premium');
		$confirm_msg = $field['confirm_message'] ?? __('Are you sure you want to reset all settings in this section to their defaults? This cannot be undone.', 'smart-variations-images-premium');

		$framework = RL_Options_Framework::instance();
		$reset_input_name = $framework->get_config('form_field_prefix') . '_reset_section';

		$tab_id = $field['__tab_id'] ?? '';
		$section_id = $field['__section_id'] ?? '';
		$payload = $tab_id . ':' . $section_id;

		// Hidden input to capture the reset intent (value will be set via JS)
		$html = sprintf(
			'<input type="hidden" name="%s" class="rl-field-reset-section-input" value="">',
			esc_attr($reset_input_name)
		);

		$html .= sprintf(
			'<button type="button" class="button button-secondary rl-field-reset-section-btn" data-confirm-msg="%s" data-payload="%s" style="margin-top:10px; color:#a00; border-color:#a00;">%s</button>',
			esc_attr($confirm_msg),
			esc_attr($payload),
			esc_html($label)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
