<?php

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

		$allowed_html = $field['allowed_html'] ?? wp_kses_allowed_html('post');
		if (!is_array($allowed_html)) {
			$allowed_html = wp_kses_allowed_html('post');
		}

		$allowed_html = apply_filters('rl_options_framework_html_allowed_html', $allowed_html, $field, $context);
		echo wp_kses($html, is_array($allowed_html) ? $allowed_html : wp_kses_allowed_html('post'));
	}
}
