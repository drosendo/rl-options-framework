<?php

if (!defined('ABSPATH')) {
	return;
}

interface RL_Field_Interface
{
	/**
	 * Render field control markup.
	 *
	 * @param array $field Field schema.
	 * @param mixed $value Current value.
	 * @param array $context Runtime render context.
	 */
	public function render(array $field, $value, array $context = []): void;

	/**
	 * Return field type slug handled by this renderer.
	 */
	public function type(): string;
}
