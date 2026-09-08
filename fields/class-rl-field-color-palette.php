<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Color_Palette implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'color_palette';
	}

	/**
	 * Normalize options into a consistent [ [ 'value' => '#hex', 'label' => 'Name' ], ... ] structure.
	 *
	 * @param array $options Raw options definition.
	 * @return array Normalized list of swatches.
	 */
	private function normalize_options(array $options): array
	{
		$normalized = [];

		foreach ($options as $key => $data) {
			if (is_array($data)) {
				$val = (string) ($data['value'] ?? ($data['color'] ?? $key));
				$lbl = (string) ($data['label'] ?? $data['name'] ?? $val);
			} elseif (is_numeric($key)) {
				// Flat indexed array of hex values: ['#10b981', '#3b82f6']
				$val = (string) $data;
				$lbl = (string) $data;
			} else {
				// Associative array: ['#10b981' => 'Emerald']
				$val = (string) $key;
				$lbl = (string) $data;
			}

			$val = trim($val);
			if ('' !== $val) {
				$normalized[] = [
					'value' => $val,
					'label' => $lbl,
				];
			}
		}

		return $normalized;
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id    = (string) ($context['input_id'] ?? '');
		$field_name  = (string) ($context['field_name'] ?? '');
		$raw_options = $field['options'] ?? [];

		// If no options explicitly provided on field, fallback to global framework palette
		if (empty($raw_options) && !empty($context['config']['color_palette'])) {
			$raw_options = (array) $context['config']['color_palette'];
		}

		$options    = $this->normalize_options((array) $raw_options);
		$size       = in_array($field['size'] ?? 'medium', ['small', 'medium', 'large'], true) ? $field['size'] : 'medium';
		$shape      = in_array($field['shape'] ?? 'round', ['round', 'square'], true) ? $field['shape'] : 'round';
		$show_label = !empty($field['show_label']);
		$current    = (string) ($value ?? ($field['default'] ?? ''));

		if (empty($options)) {
			echo '<p class="description">' . esc_html__('No color palette options configured.', 'smart-variations-images-premium') . '</p>';
			return;
		}

		printf(
			'<div class="rl-color-palette-field rl-color-palette--size-%1$s rl-color-palette--shape-%2$s" id="%3$s">',
			esc_attr($size),
			esc_attr($shape),
			esc_attr($input_id . '_wrapper')
		);

		echo '<div class="rl-color-palette-items" role="radiogroup" aria-label="' . esc_attr($field['label'] ?? 'Color Palette') . '">';

		foreach ($options as $idx => $opt) {
			$opt_val   = $opt['value'];
			$opt_lbl   = $opt['label'];
			$item_id   = $input_id . '_' . sanitize_key(str_replace('#', '', $opt_val)) . '_' . $idx;
			$is_active = (strcasecmp($current, $opt_val) === 0);

			$tooltip = ($opt_lbl !== $opt_val) ? sprintf('%s (%s)', $opt_lbl, $opt_val) : $opt_val;

			printf(
				'<label class="rl-color-palette-item%1$s" for="%2$s" title="%3$s" data-tippy-content="%3$s">',
				$is_active ? ' is-selected' : '',
				esc_attr($item_id),
				esc_attr($tooltip)
			);

			printf(
				'<input type="radio" id="%1$s" name="%2$s" value="%3$s" class="rl-color-palette-radio"%4$s />',
				esc_attr($item_id),
				esc_attr($field_name),
				esc_attr($opt_val),
				checked($is_active, true, false)
			);

			printf(
				'<span class="rl-color-palette-swatch" style="background-color: %1$s;">' .
					'<svg class="rl-color-palette-check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
						'<polyline points="20 6 9 17 4 12"></polyline>' .
					'</svg>' .
				'</span>',
				esc_attr($opt_val)
			);

			if ($show_label) {
				printf(
					'<span class="rl-color-palette-text">%s</span>',
					esc_html($opt_lbl)
				);
			}

			echo '</label>';
		}

		echo '</div>'; // .rl-color-palette-items
		echo '</div>'; // .rl-color-palette-field
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		if ($value === '' || $value === null) {
			return $field['default'] ?? '';
		}

		$raw = trim((string) $value);

		// If options are specified, check if the value exists in allowed options
		$raw_options = $field['options'] ?? [];
		if (empty($raw_options) && !empty($context['config']['color_palette'])) {
			$raw_options = (array) $context['config']['color_palette'];
		}

		if (!empty($raw_options)) {
			$options = $this->normalize_options((array) $raw_options);
			$allowed = array_column($options, 'value');
			foreach ($allowed as $allowed_color) {
				if (strcasecmp($raw, $allowed_color) === 0) {
					return $allowed_color;
				}
			}
		}

		// Fallback to hex/rgba validation if option list empty or dynamic
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

		if ($value === '' || $value === null) {
			return true;
		}

		$raw = trim((string) $value);

		$raw_options = $field['options'] ?? [];
		if (empty($raw_options) && !empty($context['config']['color_palette'])) {
			$raw_options = (array) $context['config']['color_palette'];
		}

		if (!empty($raw_options)) {
			$options = $this->normalize_options((array) $raw_options);
			$allowed = array_column($options, 'value');
			$matched = false;
			foreach ($allowed as $allowed_color) {
				if (strcasecmp($raw, $allowed_color) === 0) {
					$matched = true;
					break;
				}
			}

			if (!$matched) {
				$error = sprintf(
					/* translators: %s: field label */
					__('%s has an invalid color selected.', 'smart-variations-images-premium'),
					$field_label
				);
				return false;
			}

			return true;
		}

		// General color format validation
		if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $raw) || preg_match('/^rgba?\([^\)]+\)$/i', $raw)) {
			return true;
		}

		$error = sprintf(
			/* translators: %s: field label */
			__('%s must be a valid color.', 'smart-variations-images-premium'),
			$field_label
		);
		return false;
	}

	public function prepare_for_validation(array $field, $value, array $context = [])
	{
		return $value;
	}
}
