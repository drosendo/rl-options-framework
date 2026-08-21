<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Field Presets – framework-level tools for preset and bundle registration.
 *
 * This class intentionally ships with zero business/domain presets.
 * Theme/plugin developers register their own presets and bundles.
 *
 * @package RL_Options_Framework
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Presets
{
	/**
	 * Registered preset definitions.
	 *
	 * @var array<string,array>
	 */
	private array $presets = [];

	/**
	 * Registered bundle resolvers.
	 *
	 * @var array<string,callable>
	 */
	private array $bundles = [];

	/**
	 * Register or replace a preset definition.
	 *
	 * @param string $preset_id Preset identifier.
	 * @param array  $definition Field definition template.
	 */
	public function register_preset(string $preset_id, array $definition): void
	{
		if ('' === $preset_id || empty($definition)) {
			return;
		}

		$this->presets[$preset_id] = $definition;
	}

	/**
	 * Get preset with optional overrides.
	 *
	 * @param string $preset_id Preset identifier.
	 * @param array  $overrides Values to override in preset.
	 * @return array
	 */
	public function get_preset(string $preset_id, array $overrides = []): array
	{
		if (!isset($this->presets[$preset_id])) {
			return [];
		}

		return wp_parse_args($overrides, $this->presets[$preset_id]);
	}

	/**
	 * Register or replace a bundle resolver.
	 *
	 * Resolver signature: function(array $config, RL_Field_Presets $registry): array
	 *
	 * @param string   $bundle_id Bundle identifier.
	 * @param callable $resolver  Bundle callback.
	 */
	public function register_bundle(string $bundle_id, callable $resolver): void
	{
		if ('' === $bundle_id) {
			return;
		}

		$this->bundles[$bundle_id] = $resolver;
	}

	/**
	 * Expand a bundle to field definitions.
	 *
	 * @param string $bundle_id Bundle identifier.
	 * @param array  $config Bundle configuration.
	 * @return array<int,array>
	 */
	public function expand_bundle(string $bundle_id, array $config = []): array
	{
		if (!isset($this->bundles[$bundle_id])) {
			return [];
		}

		$result = call_user_func($this->bundles[$bundle_id], $config, $this);
		return is_array($result) ? $result : [];
	}

	/**
	 * @return array<int,string>
	 */
	public function get_available_presets(): array
	{
		return array_keys($this->presets);
	}

	/**
	 * @return array<int,string>
	 */
	public function get_available_bundles(): array
	{
		return array_keys($this->bundles);
	}
}
