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
		echo $field['html'] ?? '';
	}
}
