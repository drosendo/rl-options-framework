<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Html implements RL_Field_Interface
{
	public function type(): string
	{
		return 'html';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$html = (string) ($field['html'] ?? '');
		if ($html === '') {
			return;
		}

		// HTML fields are framework-defined trusted markup and may include inline scripts.
		// Keep legacy behavior by default; opt into sanitization per field when needed.
		$sanitize = isset($field['sanitize_html']) ? (bool) $field['sanitize_html'] : false;
		if (!$sanitize) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$allowed_html = $field['allowed_html'] ?? wp_kses_allowed_html('post');
		if (!is_array($allowed_html)) {
			$allowed_html = wp_kses_allowed_html('post');
		}

		$allowed_html = apply_filters('rl_options_framework_html_allowed_html', $allowed_html, $field, $context);
		echo wp_kses($html, is_array($allowed_html) ? $allowed_html : wp_kses_allowed_html('post'));
	}
}
