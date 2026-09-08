<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Color implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'color';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$extra_attrs = '';

		$palette = $field['palette'] ?? ($field['palettes'] ?? null);
		if ($palette !== null) {
			if (is_array($palette)) {
				$extra_attrs .= sprintf(' data-palettes="%s"', esc_attr((string) wp_json_encode(array_values($palette))));
			} elseif (is_bool($palette)) {
				$extra_attrs .= sprintf(' data-palettes="%s"', $palette ? 'true' : 'false');
			} elseif (is_string($palette) && '' !== trim($palette)) {
				$extra_attrs .= sprintf(' data-palettes="%s"', esc_attr(trim($palette)));
			}
		}

		if (isset($field['alpha'])) {
			$extra_attrs .= sprintf(' data-alpha-enabled="%s"', !empty($field['alpha']) ? 'true' : 'false');
		}

		printf(
			'<input type="text" id="%1$s" class="rl-color-field" name="%2$s" value="%3$s" data-default-color="%4$s"%5$s />',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr((string) $value),
			esc_attr((string) ($field['default'] ?? '')),
			$extra_attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		if ($value === '' || $value === null) {
			return '';
		}

		$raw = trim((string) $value);
		$standard_hex = sanitize_hex_color($raw);
		if ($standard_hex) {
			return $standard_hex;
		}

		if (preg_match('/^#([0-9a-fA-F]{8})$/', $raw)) {
			return $raw;
		}

		if (preg_match('/^rgba?\([^\)]+\)$/i', $raw)) {
			return $raw;
		}

		return $field['default'] ?? '';
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		if ($value === '' || $value === null) {
			return true;
		}

		$raw = (string) $value;
		if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $raw)) {
			return true;
		}

		if (preg_match('/^rgba?\([^\)]+\)$/i', $raw)) {
			return true;
		}

		$error = sprintf(
			/* translators: %s: field label */
			__('%s must be a valid hex color.', 'smart-variations-images-premium'),
			$field_label
		);
		return false;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		return $value;
	}
}
