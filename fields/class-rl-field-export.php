<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Export implements RL_Field_Interface
{
	public function type(): string
	{
		return 'export';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$framework = RL_Options_Framework::instance();
		$json = $framework->export_settings();

		$label = $field['button_label'] ?? __('Download Export File', 'smart-variations-images-premium');
		$desc = $field['description'] ?? __('Download a complete backup of your current settings as a JSON file.', 'smart-variations-images-premium');

		if (!empty($desc)) {
			echo '<p>' . wp_kses_post($desc) . '</p>';
		}

		printf(
			'<button type="button" class="button button-primary rl-field-export-btn" data-export-json="%s" style="margin-top:10px;">%s</button>',
			esc_attr($json),
			esc_html($label)
		);
	}
}
