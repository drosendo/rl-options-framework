<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Registry
{
	/**
	 * @var array<string,RL_Field_Interface>
	 */
	private array $renderers = [];

	public function register(RL_Field_Interface $renderer): void
	{
		$this->renderers[$renderer->type()] = $renderer;
	}

	public function get(string $type): ?RL_Field_Interface
	{
		return $this->renderers[$type] ?? null;
	}

	/**
	 * @return string[]
	 */
	public function types(): array
	{
		return array_keys($this->renderers);
	}
}
