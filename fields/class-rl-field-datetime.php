<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Datetime implements RL_Field_Interface
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
}
