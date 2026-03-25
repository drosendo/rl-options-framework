<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Datetime implements RL_Field_Interface, RL_Field_Processing_Interface
{
	public function type(): string
	{
		return 'datetime';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$text_domain = (string) ($context['text_domain'] ?? 'default');

		$current_value = trim((string) ($value !== null && $value !== '' ? $value : ($field['default'] ?? '')));
		$date_value = '';
		$time_value = '';
		if ($current_value !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})\s(\d{2}:\d{2})$/', $current_value, $matches)) {
			$date_value = $matches[1];
			$time_value = $matches[2];
		}

		$time_step = isset($field['time_step']) ? max(60, absint((int) $field['time_step'])) : 60;
		$placeholder = $field['placeholder'] ?? __('Select date', $text_domain);

		printf(
			'<div class="rl-datetime-field"><input type="hidden" id="%1$s" name="%2$s" value="%3$s" class="rl-datetime-value" /><input type="text" id="%1$s_date" value="%4$s" class="rl-datetime-date" data-target-id="%1$s" placeholder="%5$s" autocomplete="off" /><input type="time" id="%1$s_time" value="%6$s" class="rl-datetime-time" data-target-id="%1$s" step="%7$d" /></div>',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr($current_value),
			esc_attr($date_value),
			esc_attr((string) $placeholder),
			esc_attr($time_value),
			$time_step
		);
	}

	public function sanitize(array $field, $value, array $context = [])
	{
		if ($value === null || $value === '') {
			return '';
		}

		$raw = trim(sanitize_text_field((string) $value));
		if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $raw) && false !== strtotime($raw)) {
			return date('Y-m-d H:i', strtotime($raw));
		}

		$fallback = trim((string) ($field['default'] ?? ''));
		if ($fallback !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $fallback) && false !== strtotime($fallback)) {
			return date('Y-m-d H:i', strtotime($fallback));
		}

		return '';
	}

	public function validate(array $field, $value, string &$error, array $context = []): bool
	{
		$field_label = $context['field_label'] ?? 'Field';
		$text_domain = $context['text_domain'] ?? 'default';

		if ($value === '' || $value === null) {
			return true;
		}

		$raw = trim((string) $value);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $raw) || false === strtotime($raw)) {
			$error = sprintf(
				__('%s must be a valid date and time.', $text_domain),
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
