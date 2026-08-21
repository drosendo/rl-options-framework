<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Options Assets Service
 *
 * Handles admin asset enqueueing: CSS, JavaScript, and inline styles.
 * Manages CDN vs local asset strategy based on config.
 *
 * @package RL_Options_Framework
 */

if (!defined('ABSPATH')) {
	return;
}

class RL_Options_Assets_Service {
	/**
	 * @var RL_Options_Framework Framework instance for config/option access.
	 */
	private $framework;

	/**
	 * Constructor.
	 *
	 * @param RL_Options_Framework $framework Framework instance.
	 */
	public function __construct(RL_Options_Framework $framework) {
		$this->framework = $framework;
	}

	/**
	 * Enqueue admin assets for options page.
	 *
	 * WordPress admin_enqueue_scripts hook callback.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets(string $hook): void
	{
		if (!$this->is_target_admin_page($hook)) {
			return;
		}

		$use_local_assets = true; // Always use local assets for wp.org guidelines

		wp_enqueue_style('dashicons');

		// CSS for image_select field type
		$custom_css = "
			.rl-image-select-options {
				display: flex;
				flex-wrap: wrap;
				gap: 15px;
			}
			.rl-image-select-option {
				cursor: pointer;
				position: relative;
				display: inline-block;
			}
			.rl-image-select-option input[type='radio'] {
				position: absolute;
				opacity: 0;
				width: 0;
				height: 0;
			}
			.rl-image-select-option img {
				display: block;
				border: 2px solid #ddd;
				border-radius: 4px;
				transition: all 0.2s ease;
				padding: 2px;
				background: #fff;
			}
			.rl-image-select-option:hover img {
				border-color: #999;
			}
			.rl-image-select-option input[type='radio']:checked + img {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
			}
			.rl-image-select-label {
				display: block;
				text-align: center;
				margin-top: 5px;
				font-size: 12px;
				font-weight: 500;
				color: #646970;
			}
			.rl-image-select-option input[type='radio']:checked ~ .rl-image-select-label {
				color: #135e96;
				font-weight: 600;
			}
		";
		wp_register_style('rl-framework-custom-css', false, [], $this->framework->get_config('version'));
		wp_enqueue_style('rl-framework-custom-css');
		wp_add_inline_style('rl-framework-custom-css', $custom_css);

		wp_enqueue_style('wp-color-picker');

		$config = $this->framework->get_config();
		$assets_url = $this->resolve_assets_url();

		wp_enqueue_style(
			$config['page_slug'] . '-framework',
			$assets_url . 'css/options-framework.css',
			['dashicons'],
			$config['version']
		);

		$sweetalert_css_url = $assets_url . 'vendor/sweetalert2/sweetalert2.min.css';
		$tippy_css_url = $assets_url . 'vendor/tippy/tippy.css';

		// SweetAlert2 for better notifications
		wp_enqueue_style(
			'sweetalert2',
			$sweetalert_css_url,
			[],
			'11.0.0'
		);
		// Tippy.js CSS for tooltips
		wp_enqueue_style(
			'tippy-js',
			$tippy_css_url,
			[],
			'6.3.7'
		);

		$sweetalert_js_url = $assets_url . 'vendor/sweetalert2/sweetalert2.all.min.js';
		$popper_js_url = $assets_url . 'vendor/popper/popper.min.js';
		$tippy_js_url = $assets_url . 'vendor/tippy/tippy.umd.min.js';

		wp_enqueue_script('wp-color-picker');
		wp_enqueue_script('jquery-ui-datepicker');
		wp_localize_jquery_ui_datepicker(); // i18n: locale-aware month/day names
		wp_enqueue_script(
			'sweetalert2',
			$sweetalert_js_url,
			[],
			'11.0.0',
			true
		);

		// Popper.js (required for Tippy.js)
		wp_enqueue_script(
			'popper-js',
			$popper_js_url,
			[],
			'2.11.8',
			true
		);

		// Tippy.js for tooltips
		wp_enqueue_script(
			'tippy-js',
			$tippy_js_url,
			['popper-js'],
			'6.3.7',
			true
		);

		// Enqueue WordPress media uploader
		wp_enqueue_media();

		wp_enqueue_script(
			$config['page_slug'] . '-framework',
			$assets_url . 'js/options-framework.js',
			['jquery', 'wp-color-picker', 'jquery-ui-datepicker', 'sweetalert2', 'tippy-js'],
			$config['version'],
			true
		);

		wp_localize_script(
			$config['page_slug'] . '-framework',
			'rlFramework',
			[
				'page' => $config['page_slug'],
				'optionField' => $config['form_field_prefix'],
				'ajax_url' => admin_url('admin-ajax.php'),
				'ajax_action' => $config['ajax_action'],
				'provider_action' => 'rl_options_framework_field_options',
				'validate_action' => 'rl_options_framework_field_validate',
				'nonce' => wp_create_nonce($config['ajax_action'] . '_nonce'),
				'sync_history' => !empty($config['sync_history']),
				'swal_fallback' => !empty($config['swal_fallback']),
				'debug_level' => $this->resolve_debug_level(),
				'rest_base' => esc_url_raw(rest_url('rl-options/v1/')),
			]
		);
	}

	/**
	 * Check whether current admin hook/page matches framework options page.
	 *
	 * @param string $hook Current admin hook suffix.
	 * @return bool
	 */
	private function is_target_admin_page(string $hook): bool
	{
		$config = $this->framework->get_config();
		$page_slug = (string) ($config['page_slug'] ?? '');

		if ('' === $page_slug) {
			return false;
		}

		if ($hook === 'toplevel_page_' . $page_slug || substr($hook, -strlen('_page_' . $page_slug)) === '_page_' . $page_slug) {
			return true;
		}

		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash((string) $_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $page === $page_slug;
	}

	/**
	 * Resolve framework assets URL.
	 *
	 * Applies strategy: explicit config → plugin context → theme context → plugin fallback.
	 *
	 * @return string Trailing-slash-terminated assets URL.
	 */
	public function resolve_assets_url(): string
	{
		$config = $this->framework->get_config();

		if (!empty($config['assets_url'])) {
			return trailingslashit((string) $config['assets_url']);
		}

		$context = strtolower((string) ($config['context'] ?? 'auto'));
		if (!in_array($context, ['auto', 'plugin', 'theme'], true)) {
			$context = 'auto';
		}

		// Try plugin context (if plugin object available)
		if (('auto' === $context || 'plugin' === $context) && isset($config['_plugin_instance']) && method_exists($config['_plugin_instance'], 'get_plugin_url')) {
			return trailingslashit($config['_plugin_instance']->get_plugin_url() . 'includes/library/rloptionsFramework/assets');
		}

		$relative = 'includes/library/rloptionsFramework/assets/';
		if ('theme' === $context || 'auto' === $context) {
			$theme_dir = trailingslashit(get_template_directory());
			$theme_uri = trailingslashit(get_template_directory_uri());
			if (file_exists($theme_dir . $relative)) {
				return $theme_uri . $relative;
			}

			if (is_child_theme()) {
				$child_dir = trailingslashit(get_stylesheet_directory());
				$child_uri = trailingslashit(get_stylesheet_directory_uri());
				if (file_exists($child_dir . $relative)) {
					return $child_uri . $relative;
				}
			}
		}

		// Fallback: __FILE__ is in services/, so one level up reaches rloptionsFramework/assets/
		return trailingslashit(plugin_dir_url(__FILE__) . '../assets');
	}

	/**
	 * Resolve the effective JS debug level.
	 *
	 * If the saved options contain a truthy debug toggle, returns 'debug'.
	 * Otherwise falls back to config['debug_level'] (default 'error').
	 *
	 * @return string Debug level: 'error', 'warn', 'info', or 'debug'.
	 */
	public function resolve_debug_level(): string
	{
		$config = $this->framework->get_config();
		$options = $this->framework->get_options();
		$debug_field_id = $config['debug_field_id'] ?? '';

		if ($debug_field_id && is_array($options) && !empty($options[$debug_field_id])) {
			return 'debug';
		}

		return (string) ($config['debug_level'] ?? 'error');
	}

	/**
	 * Check whether vendor assets should be served locally.
	 *
	 * Falls back to local if toggle is enabled but field not present/false.
	 *
	 * @return bool True to use local assets; false to use CDN.
	 */
	public function should_use_local_assets(): bool
	{
		$config = $this->framework->get_config();

		if (empty($config['use_local_assets_toggle'])) {
			return false;
		}

		$field_id = $config['local_assets_field_id'] ?? 'rloptions_local_assets';
		$options = $this->framework->get_options();

		if (!is_array($options)) {
			return true;
		}

		if (!array_key_exists($field_id, $options)) {
			return true;
		}

		return (bool) $options[$field_id];
	}
}
