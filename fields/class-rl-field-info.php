<?php

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Info implements RL_Field_Interface
{
	public function type(): string
	{
		return 'info';
	}

	public function render(array $field, $value, array $context = []): void
	{
		printf('<div class="rl-info-field">%s</div>', wp_kses_post((string) ($field['description'] ?? '')));
	}
}
