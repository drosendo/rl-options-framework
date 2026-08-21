<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Import implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'import';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$desc = $field['description'] ?? __('Select a previously exported JSON file to import settings.', 'smart-variations-images-premium');
		$status_msg = $field['status_message'] ?? __('File loaded! Click "Save Changes" below to apply the import.', 'smart-variations-images-premium');

		if (!empty($desc)) {
			echo '<p>' . wp_kses_post($desc) . '</p>';
		}

		$framework = RL_Options_Framework::instance();
		$import_input_name = $framework->get_config('form_field_prefix') . '_import_json';

		// File input for the user
		echo '<input type="file" class="button rl-field-import-file" accept=".json" style="margin-top:10px;">';
		
		// Hidden textarea that receives the file contents via JS and is submitted with the form
		printf(
			'<textarea name="%s" class="rl-field-import-textarea" style="display:none;"></textarea>',
			esc_attr($import_input_name)
		);
		
		// Status message to indicate the file is ready to save
		printf(
			'<p class="rl-field-import-status" style="display:none; color: #2271b1; font-weight: bold; margin-top: 15px;">%s</p>',
			esc_html($status_msg)
		);
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		// The import field itself doesn't save a value in the normal settings array.
		// The actual import is intercepted in the admin handler before generic fields are processed.
		return null; 
	}
}
