<?php
/**
 * RL Options Framework – Generic WordPress Options Framework
 *
 * A robust, flexible, plugin-agnostic options framework for WordPress plugins with validation,
 * sanitization, backup/restore, and extensive field type support.
 *
 * Features:
 * - 15 field types (text, textarea, select, multiselect, radio, checkbox, toggle, color, number, date, datetime, html, etc.)
 * - Tabbed interface with accordion sections
 * - Conditional field display based on other field values
 * - Field validation with error messages
 * - Field sanitization per type
 * - Backup and restore functionality
 * - Import/export settings as JSON
 * - Priority-based field sorting
 * - Filter hooks for extensibility
 * - Responsive admin interface
 * - AJAX save with SweetAlert2
 * - Tab persistence with localStorage
 *
 * Usage Example:
 * ```php
 * $config = [
 *     'option_name'       => 'my_plugin_settings',
 *     'form_field_prefix' => 'my_plugin',
 *     'page_slug'         => 'my-plugin-settings',
 *     'menu_title'        => 'My Plugin Settings',
 *     'page_title'        => 'My Plugin Settings',
 *     'capability'        => 'manage_options',
 *     'parent_menu'       => 'options-general.php',  // or 'woocommerce' for submenu
 *     'assets_url'        => plugin_dir_url( __FILE__ ) . 'assets/',
 * ];
 * 
 * $framework = new RL_Options_Framework( $config );
 * $framework->init();
 * 
 * // Add a tab
 * $framework->add_tab([
 *     'id'    => 'general',
 *     'title' => 'General Settings',
 * ]);
 * 
 * // Add a section
 * $framework->add_section([
 *     'tab_id'   => 'general',
 *     'id'       => 'basic_settings',
 *     'title'    => 'Basic Configuration',
 *     'accordion' => true,
 * ]);
 * 
 * // Add field with validation
 * $framework->add_field([
 *     'tab_id'      => 'general',
 *     'section_id'  => 'basic_settings',
 *     'id'          => 'max_items',
 *     'type'        => 'number',
 *     'label'       => 'Maximum Items',
 *     'desc'        => 'Maximum number of items',
 *     'default'     => 10,
 *     'required'    => true,
 *     'min'         => 1,
 *     'max'         => 100,
 * ]);
 * 
 * // Get option value
 * $max_items = $framework->get_option('max_items', 10);
 * ```
 *
 * @package RL_Options_Framework
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
	return;
}

if (!function_exists('rl_options_framework_get_countries')) {
	/**
	 * Get normalized country metadata.
	 *
	 * @return array<int,array{code:string,name:string,region:string,capital:string}>
	 */
	function rl_options_framework_get_countries(): array
	{
		$framework = RL_Options_Framework::instance();
		return $framework->get_country_reference_countries();
	}
}

if (!function_exists('rl_options_framework_get_country_subdivisions')) {
	/**
	 * Get country subdivisions as normalized options.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	function rl_options_framework_get_country_subdivisions(string $country_code): array
	{
		$framework = RL_Options_Framework::instance();
		return $framework->get_country_subdivisions($country_code);
	}
}

if (!function_exists('rl_options_framework_get_country_municipalities')) {
	/**
	 * Get municipalities as normalized options.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	function rl_options_framework_get_country_municipalities(string $country_code, string $subdivision = ''): array
	{
		$framework = RL_Options_Framework::instance();
		return $framework->get_country_municipalities($country_code, $subdivision);
	}
}

/**
 * Generic options framework for WordPress plugins.
 */
final class RL_Options_Framework
{
	/**
	 * Singleton instance (for backward compatibility).
	 *
	 * @var RL_Options_Framework
	 */
	private static $instance = null;

	/**
	 * Plugin instance.
	 *
	 * @var object
	 */
	private $plugin;

	/**
	 * Framework configuration
	 *
	 * @var array
	 */
	public array $config = [];

	/**
	 * Base URL to the framework assets.
	 *
	 * @var string
	 */
	private string $assets_url = '';

	/**
	 * Cached tabs definition.
	 *
	 * @var array<string,array>
	 */
	private array $tabs = [];

	/**
	 * Current request option values used by contextual validation/sanitization.
	 *
	 * @var array<string,mixed>
	 */
	private array $validation_context = [];

	/**
	 * Whether framework has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Presets manager instance for developer-registered presets and bundles.
	 *
	 * @var RL_Field_Presets|null
	 */
	private ?RL_Field_Presets $presets = null;

	/**
	 * Field renderer registry.
	 *
	 * @var RL_Field_Registry|null
	 */
	private ?RL_Field_Registry $field_registry = null;

	/**
	 * Render service instance for page rendering logic.
	 *
	 * @var RL_Options_Render_Service|null
	 */
	private ?RL_Options_Render_Service $render_service = null;

	/**
	 * Admin handler service instance for request handlers.
	 *
	 * @var RL_Options_Admin_Handler|null
	 */
	private ?RL_Options_Admin_Handler $admin_handler = null;

	/**
	 * Schema manager service instance for schema building and management.
	 *
	 * @var RL_Options_Schema_Manager|null
	 */
	private ?RL_Options_Schema_Manager $schema_manager = null;

	/**
	 * REST API service instance for geo data and REST route management.
	 *
	 * @var RL_Options_Rest_Api|null
	 */
	private ?RL_Options_Rest_Api $rest_api = null;

	/**
	 * Constructor.
	 *
	 * @param array $config Framework configuration.
	 */
	public function __construct(array $config = [])
	{
		$defaults = [
			'option_name'       => 'rl_framework_settings',
			'form_field_prefix' => 'rl_options',
			'page_slug'         => 'rl-options-settings',
			'menu_title'        => 'Plugin Settings',
			'page_title'        => 'Plugin Settings',
			'capability'        => 'manage_options',
			'parent_menu'       => 'options-general.php',
			'text_domain'       => 'rl-options-framework',
			'ajax_action'       => 'rl_save_options_ajax',
			'assets_url'        => '',
			'plugin_url'        => '',
			'version'           => '2.1.0',
			'context'           => 'auto', // auto|plugin|theme
			'register_menu'     => true,
			'sync_history'      => false,
			'debug_level'       => 'error', // error|warn|info|debug
			'swal_fallback'     => true,
			// ID of the debug toggle field saved in the options row.
			// Override per-plugin so the key matches existing saved data.
			'debug_field_id'    => 'enable_debug',
			// Local assets toggle (GDPR/privacy). Keep enabled and configurable by default.
			'use_local_assets_toggle' => true,
			'local_assets_field_id'   => 'rloptions_local_assets',
		];

		$this->config = wp_parse_args($config, $defaults);
	}

	/**
	 * Retrieve singleton instance (for backward compatibility).
	 *
	 * @deprecated Use new RL_Options_Framework($config) instead.
	 * @param array $config Optional configuration array.
	 */
	public static function instance(array $config = []): RL_Options_Framework
	{
		if (null === self::$instance) {
			// Create with provided config or defaults
			self::$instance = new self($config);
		} elseif (!empty($config)) {
			// Update config if provided on subsequent calls
			self::$instance->config = wp_parse_args($config, self::$instance->config);
		}

		return self::$instance;
	}

	/**
	 * Initialize the framework.
	 *
	 * @param object $plugin Plugin instance (optional, for legacy compatibility).
	 */
	public function init($plugin = null): void
	{
		if ($this->initialized) {
			return;
		}

		if ($plugin) {
			$this->plugin = $plugin;
		}

		RL_Logger::set_level((string) ($this->config['debug_level'] ?? 'error'));

		$this->assets_url = $this->resolve_assets_url($plugin);

		$this->tabs = $this->schema_manager->get_default_tabs();

		// Initialize presets manager
		require_once __DIR__ . '/class-rl-field-presets.php';
		require_once __DIR__ . '/class-rl-field-types.php';
		$this->presets = new RL_Field_Presets();

		// Initialize field renderer registry (ACF-style single class per field type).
		require_once __DIR__ . '/fields/load.php';
		$this->field_registry = new RL_Field_Registry();
		RL_Field_Bootstrap::register_defaults($this->field_registry);

		// Initialize render service for page rendering logic.
		$this->render_service = new RL_Options_Render_Service($this);

		// Initialize admin handler service for request handlers.
		$this->admin_handler = new RL_Options_Admin_Handler($this);

		// Initialize schema manager service for schema building and management.
		$this->schema_manager = new RL_Options_Schema_Manager($this);

		// Initialize REST API service for geo data and REST route registration.
		$this->rest_api = new RL_Options_Rest_Api($this);

		// Register admin menu unless host project opts to fully control menu wiring.
		if (!empty($this->config['register_menu'])) {
			add_action('admin_menu', [$this, 'register_menu'], 60);
		}

		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('admin_post_' . $this->config['page_slug'] . '_save', [$this, 'handle_save']);
		add_action('wp_ajax_' . $this->config['ajax_action'], [$this, 'handle_ajax_save']);
		add_action('wp_ajax_rl_options_framework_field_options', [$this, 'handle_ajax_field_options']);
		add_action('wp_ajax_rl_options_framework_field_validate', [$this, 'handle_ajax_field_validate']);
		add_action('admin_notices', [$this, 'render_notices']);

		// Register REST routes and handle late-boot scenario via service.
		$this->rest_api->register();

		$this->initialized = true;

		/**
		 * Give other add-ons a chance to interact with the framework instance.
		 *
		 * Hook name format: {option_name}_framework_boot
		 */
		do_action($this->config['option_name'] . '_framework_boot', $this);
	}

	/**
	 * Access REST API service (geo data and route registry).
	 */
	public function rest_api(): ?RL_Options_Rest_Api
	{
		return $this->rest_api;
	}

	/**
	 * Access preset/bundle registry.
	 */
	public function presets(): ?RL_Field_Presets
	{
		return $this->presets;
	}

	/**
	 * Get configuration value by key.
	 *
	 * @param string $key     Config key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Configuration value.
	 */
	public function get_config(string $key = '', $default = null)
	{
		if ($key === '') {
			return $this->config;
		}
		return $this->config[$key] ?? $default;
	}

	/**
	 * Set the validation context (current form input state).
	 *
	 * Used by field validators to access related field values.
	 *
	 * @param array $context Input context array.
	 */
	public function set_validation_context(array $context): void
	{
		$this->validation_context = $context;
	}

	/**
	 * Set tabs structure.
	 *
	 * @param array $tabs Tabs array.
	 */
	public function set_tabs(array $tabs): void
	{
		$this->tabs = $tabs;
	}

	/**
	 * Register a reusable field preset.
	 */
	public function register_field_preset(string $preset_id, array $definition): void
	{
		if (!$this->presets) {
			return;
		}

		$this->presets->register_preset($preset_id, $definition);
	}

	/**
	 * Register a reusable field bundle resolver.
	 */
	public function register_field_bundle(string $bundle_id, callable $resolver): void
	{
		if (!$this->presets) {
			return;
		}

		$this->presets->register_bundle($bundle_id, $resolver);
	}

	/**
	 * Add one preset field into target section.
	 */
	public function add_preset_field(string $tab_slug, string $section_id, string $preset_id, array $overrides = []): void
	{
		if (!$this->presets) {
			return;
		}

		$field = $this->presets->get_preset($preset_id, $overrides);
		if (!empty($field)) {
			$this->add_field($tab_slug, $section_id, $field);
		}
	}

	/**
	 * Expand and add a bundle into target section.
	 */
	public function add_bundle_fields(string $tab_slug, string $section_id, string $bundle_id, array $config = []): void
	{
		if (!$this->presets) {
			return;
		}

		$fields = $this->presets->expand_bundle($bundle_id, $config);
		if (!empty($fields)) {
			$this->add_fields($tab_slug, $section_id, $fields);
		}
	}

	/**
	 * Access field registry.
	 *
	 * @return RL_Field_Registry|null
	 */
	public function field_registry(): ?RL_Field_Registry
	{
		return $this->field_registry;
	}

	/**
	 * Register a custom field renderer.
	 */
	public function register_field_renderer(RL_Field_Interface $renderer): void
	{
		if (!$this->field_registry) {
			return;
		}

		$this->field_registry->register($renderer);
	}

	/**
	 * Resolve framework assets URL for plugin or theme integrations.
	 */
	private function resolve_assets_url($plugin = null): string
	{
		if (!empty($this->config['assets_url'])) {
			return trailingslashit((string) $this->config['assets_url']);
		}

		$context = strtolower((string) ($this->config['context'] ?? 'auto'));
		if (!in_array($context, ['auto', 'plugin', 'theme'], true)) {
			$context = 'auto';
		}

		if (('auto' === $context || 'plugin' === $context) && $plugin && method_exists($plugin, 'get_plugin_url')) {
			return trailingslashit($plugin->get_plugin_url() . 'includes/library/rloptionsFramework/assets');
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

		return trailingslashit(plugin_dir_url(__FILE__) . 'assets');
	}

	/**
	 * Activate framework hooks (legacy method, calls init).
	 *
	 * @deprecated Use init() instead.
	 */
	public function boot($plugin): void
	{
		$this->init($plugin);
	}

	/**
	 * Register submenu entry under configured parent menu.
	 */
	public function register_menu(): void
	{
		RL_Logger::log('register_menu() called');
		RL_Logger::log('Parent menu: ' . $this->config['parent_menu']);
		RL_Logger::log('Page slug: ' . $this->config['page_slug']);
		RL_Logger::log('Menu title: ' . $this->config['menu_title']);
		RL_Logger::log('Capability: ' . $this->config['capability']);

		$hook = add_submenu_page(
			$this->config['parent_menu'],
			$this->config['page_title'],
			$this->config['menu_title'],
			$this->config['capability'],
			$this->config['page_slug'],
			[$this, 'render_page']
		);

		RL_Logger::log('Menu registered, hook suffix: ' . ($hook ?: 'FAILED'));
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_assets(string $hook): void
	{
		if (!$this->is_options_page()) {
			return;
		}

		$use_local_assets = $this->should_use_local_assets();

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
		wp_register_style('rl-framework-custom-css', false);
		wp_enqueue_style('rl-framework-custom-css');
		wp_add_inline_style('rl-framework-custom-css', $custom_css);

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_style(
			$this->config['page_slug'] . '-framework',
			$this->assets_url . 'css/options-framework.css',
			['dashicons'],
			$this->config['version']
		);

		$sweetalert_css_url = $use_local_assets
			? $this->assets_url . 'vendor/sweetalert2/sweetalert2.min.css'
			: 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
		$tippy_css_url = $use_local_assets
			? $this->assets_url . 'vendor/tippy/tippy.css'
			: 'https://unpkg.com/tippy.js@6/dist/tippy.css';

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

		$sweetalert_js_url = $use_local_assets
			? $this->assets_url . 'vendor/sweetalert2/sweetalert2.all.min.js'
			: 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js';
		$popper_js_url = $use_local_assets
			? $this->assets_url . 'vendor/popper/popper.min.js'
			: 'https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js';
		$tippy_js_url = $use_local_assets
			? $this->assets_url . 'vendor/tippy/tippy.umd.min.js'
			: 'https://unpkg.com/tippy.js@6/dist/tippy.umd.min.js';


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
			$this->config['page_slug'] . '-framework',
			$this->assets_url . 'js/options-framework.js',
			['jquery', 'wp-color-picker', 'jquery-ui-datepicker', 'sweetalert2', 'tippy-js'],
			$this->config['version'],
			true
		);

		wp_localize_script(
			$this->config['page_slug'] . '-framework',
			'rlFramework',
			[
				'page' => $this->config['page_slug'],
				'optionField' => $this->config['form_field_prefix'],
				'ajax_url' => admin_url('admin-ajax.php'),
				'ajax_action' => $this->config['ajax_action'],
				'provider_action' => 'rl_options_framework_field_options',
				'validate_action' => 'rl_options_framework_field_validate',
				'nonce' => wp_create_nonce($this->config['ajax_action'] . '_nonce'),
				'sync_history' => !empty($this->config['sync_history']),
				'swal_fallback' => !empty($this->config['swal_fallback']),
				'debug_level' => $this->resolve_debug_level(),
				'rest_base' => esc_url_raw(rest_url('rl-options/v1/')),
			]
		);
	}

	/**
	 * Resolve the effective JS debug level.
	 * If the saved options contain a truthy debug toggle, returns 'debug'.
	 * Otherwise falls back to config['debug_level'] (default 'error').
	 */
	private function resolve_debug_level(): string
	{
		$options = get_option($this->config['option_name'], []);
		if (is_array($options) && !empty($options[$this->config['debug_field_id']])) {
			return 'debug';
		}
		return (string) ($this->config['debug_level'] ?? 'error');
	}

	/**
	 * Check whether vendor assets should be served locally.
	 */
	private function should_use_local_assets(): bool
	{
		if (empty($this->config['use_local_assets_toggle'])) {
			return false;
		}

		$field_id = $this->config['local_assets_field_id'] ?? 'rloptions_local_assets';
		$options = get_option($this->config['option_name'], []);

		if (!is_array($options)) {
			return true;
		}

		if (!array_key_exists($field_id, $options)) {
			return true;
		}

		return (bool) $options[$field_id];
	}

	/**
	 * Render framework notices after redirects.
	 */
	public function render_notices(): void
	{
		if (!$this->is_options_page()) {
			return;
		}

		$message_param = $this->config['form_field_prefix'] . '_message';
		$error_param = $this->config['form_field_prefix'] . '_error';

		if (isset($_GET[$message_param]) && 'saved' === $_GET[$message_param]) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__('Settings saved.', $this->config['text_domain'])
			);
		}

		if (isset($_GET[$message_param]) && 'error' === $_GET[$message_param] && !empty($_GET[$error_param])) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html(wp_unslash($_GET[$error_param]))
			);
		}
	}

	/**
	 * Handle options save submissions with validation.
	 *
	 * Delegates to admin handler service.
	 */
	public function handle_save(): void
	{
		$this->admin_handler->handle_save();
	}

	/**
	 * Handle AJAX options save submissions.
	 *
	 * Delegates to admin handler service.
	 */
	public function handle_ajax_save(): void
	{
		$this->admin_handler->handle_ajax_save();
	}

	/**
	 * Resolve async options for a single field.
	 *
	 * Delegates to admin handler service.
	 */
	public function handle_ajax_field_options(): void
	{
		$this->admin_handler->handle_ajax_field_options();
	}

	/**
	 * Validate one field on change (inline validation endpoint).
	 *
	 * Delegates to admin handler service.
	 */
	public function handle_ajax_field_validate(): void
	{
		$this->admin_handler->handle_ajax_field_validate();
	}

	/**
	 * Resolve allowed option keys for static and provider-backed fields.
	 *
	 * @return string[]
	 */
	private function get_allowed_option_keys(array $field, array $state = []): array
	{
		$allowed = array_map('strval', array_keys($field['options'] ?? []));
		$provider_options = $this->resolve_field_provider_options($field, $state, false);

		foreach ($provider_options as $item) {
			if (is_array($item) && isset($item['value'])) {
				$allowed[] = (string) $item['value'];
			}
		}

		$allowed = array_values(array_unique(array_filter($allowed, static function ($v) {
			return $v !== '';
		})));

		return $allowed;
	}

	/**
	 * Evaluate required_if rule set.
	 */
	private function is_required_by_rules(array $rules, array $state): bool
	{
		if (isset($rules['field'])) {
			$rules = [$rules];
		}

		if (empty($rules)) {
			return false;
		}

		foreach ($rules as $rule) {
			if (!is_array($rule)) {
				continue;
			}

			$field = isset($rule['field']) ? (string) $rule['field'] : '';
			if ($field === '') {
				continue;
			}

			$current = $state[$field] ?? null;
			$operator = strtolower((string) ($rule['operator'] ?? 'truthy'));
			$expected = $rule['value'] ?? null;

			if (!$this->match_dependency_rule($current, $operator, $expected)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Compare one dependency condition.
	 */
	private function match_dependency_rule($current, string $operator, $expected): bool
	{
		switch ($operator) {
			case 'equals':
			case '==':
				return $current == $expected;
			case 'not_equals':
			case '!=':
				return $current != $expected;
			case 'in':
				return is_array($expected) ? in_array($current, $expected, true) : $current == $expected;
			case 'not_in':
				return is_array($expected) ? !in_array($current, $expected, true) : $current != $expected;
			case 'empty':
				return $current === null || $current === '' || $current === [];
			case 'not_empty':
				return !($current === null || $current === '' || $current === []);
			case 'falsy':
				return !$current;
			case 'truthy':
			default:
				return (bool) $current;
		}
	}

	/**
	 * Resolve async provider options for a field.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public function resolve_field_provider_options(array $field, array $state = [], bool $fallback_static = true): array
	{
		$provider = $field['options_provider'] ?? null;
		if (!is_array($provider)) {
			return $fallback_static ? $this->normalize_options_for_transport($field['options'] ?? []) : [];
		}

		$endpoint = strtolower((string) ($provider['endpoint'] ?? ''));
		if ($endpoint === '') {
			$endpoint = strtolower((string) ($provider['action'] ?? ''));
		}

		$options = [];
		$country = strtoupper((string) $this->resolve_provider_param('country', $provider, $state));
		$subdivision = (string) $this->resolve_provider_param('subdivision', $provider, $state);

		switch ($endpoint) {
			case 'countries':
				$countries = $this->rest_api->get_country_reference_data();
				foreach ($countries as $code => $item) {
					$options[] = ['value' => (string) $code, 'label' => (string) ($item['name'] ?? $code)];
				}
				break;

			case 'subdivisions':
			case 'country_subdivisions':
				$options = $this->rest_api->get_country_subdivisions_data($country);
				break;

			case 'municipalities':
			case 'country_municipalities':
				$options = $this->rest_api->get_country_municipalities_data($country, $subdivision);
				break;
		}

		$options = apply_filters('rl_options_framework_resolved_provider_options', $options, $provider, $field, $state, $this);
		if (!is_array($options) || empty($options)) {
			$options = $fallback_static ? $this->normalize_options_for_transport($field['options'] ?? []) : [];
		}

		do_action('rl_options_framework_field_dependency_resolved', (string) ($field['id'] ?? ''), $provider, $state, $options, $this);

		return $this->normalize_options_for_transport($options, $provider['mapping'] ?? []);
	}

	/**
	 * Resolve provider parameter from explicit param mapping or state.
	 */
	private function resolve_provider_param(string $param, array $provider, array $state): string
	{
		$field_ref = $provider['params'][$param] ?? $provider[$param] ?? $param;
		if (!is_string($field_ref) || $field_ref === '') {
			return '';
		}

		$value = $state[$field_ref] ?? ($state[$param] ?? '');
		if (is_array($value)) {
			$value = reset($value);
		}

		return sanitize_text_field((string) $value);
	}

	/**
	 * Build options map for geo field types.
	 *
	 * @return array<string,string>
	 */
	public function get_geo_field_options(array $field, string $type, array $state = []): array
	{
		$out = [];
		$type = strtolower($type);

		if ($type === 'country') {
			foreach ($this->rest_api->get_country_reference_data() as $code => $item) {
				$out[(string) $code] = (string) ($item['name'] ?? $code);
			}
			return $out;
		}

		$country = $this->resolve_geo_country_code($field, $state);
		if ($country === '') {
			return $out;
		}

		if ($type === 'state') {
			foreach ($this->rest_api->get_country_subdivisions_data($country) as $item) {
				if (is_array($item) && isset($item['value'])) {
					$out[(string) $item['value']] = (string) ($item['label'] ?? $item['value']);
				}
			}
			return $out;
		}

		if ($type === 'city') {
			$subdivision = '';
			if (!empty($field['subdivision'])) {
				$subdivision = sanitize_key((string) $field['subdivision']);
			} elseif (!empty($field['subdivision_field']) && isset($state[(string) $field['subdivision_field']])) {
				$subdivision = sanitize_key((string) $state[(string) $field['subdivision_field']]);
			}

			foreach ($this->rest_api->get_country_municipalities_data($country, $subdivision) as $item) {
				if (is_array($item) && isset($item['value'])) {
					$out[(string) $item['value']] = (string) ($item['label'] ?? $item['value']);
				}
			}
		}

		return $out;
	}

	/**
	 * Resolve country code from fixed field config or linked country field.
	 */
	private function resolve_geo_country_code(array $field, array $state = []): string
	{
		$country = '';
		if (!empty($field['country'])) {
			$country = strtoupper(sanitize_key((string) $field['country']));
		}

		if ($country === '' && !empty($field['country_field'])) {
			$key = (string) $field['country_field'];
			if (isset($state[$key])) {
				$country = strtoupper(sanitize_key((string) $state[$key]));
			}
		}

		return $country;
	}

	/**
	 * Normalize options into [{value,label}] transport format.
	 *
	 * Public so services (e.g. REST API service) can use it for geo data normalization.
	 *
	 * @param array $options Source options (associative or indexed).
	 * @param array $mapping Optional key mapping ['value' => '...', 'label' => '...'].
	 * @return array<int,array{value:string,label:string}>
	 */
	public function normalize_options_for_transport(array $options, array $mapping = []): array
	{
		$out = [];
		$value_key = isset($mapping['value']) ? (string) $mapping['value'] : 'value';
		$label_key = isset($mapping['label']) ? (string) $mapping['label'] : 'label';

		$is_assoc = array_keys($options) !== range(0, count($options) - 1);
		if ($is_assoc) {
			foreach ($options as $value => $label) {
				if (is_array($label)) {
					$v = $label[$value_key] ?? $value;
					$l = $label[$label_key] ?? ($label['name'] ?? $v);
				} else {
					$v = $value;
					$l = $label;
				}
				$out[] = ['value' => (string) $v, 'label' => (string) $l];
			}
		} else {
			foreach ($options as $item) {
				if (is_array($item)) {
					$v = $item[$value_key] ?? ($item['value'] ?? '');
					$l = $item[$label_key] ?? ($item['label'] ?? $v);
					if ($v === '') {
						continue;
					}
					$out[] = ['value' => (string) $v, 'label' => (string) $l];
				}
			}
		}

		return $out;
	}

	/**
	 * Public helper: return countries as [{code,name,region,capital}].
	 *
	 * Delegates to REST API service.
	 */
	public function get_country_reference_countries(): array
	{
		return $this->rest_api->get_country_reference_countries();
	}

	/**
	 * Public helper: return normalized subdivisions options for a country.
	 *
	 * Delegates to REST API service.
	 */
	public function get_country_subdivisions(string $country_code): array
	{
		return $this->rest_api->get_country_subdivisions_data($country_code);
	}

	/**
	 * Public helper: return normalized municipalities options.
	 *
	 * Delegates to REST API service.
	 */
	public function get_country_municipalities(string $country_code, string $subdivision = ''): array
	{
		return $this->rest_api->get_country_municipalities_data($country_code, $subdivision);
	}

	/**
	 * Render the full settings page.
	 */
	public function render_page(): void
	{
		if ($this->render_service) {
			$this->render_service->render_page();
		}
	}

	/**
	 * Render panel with sidebar navigation.
	 *
	 * @param array $tab     Tab configuration.
	 * @param array $options Current options.
	 * @deprecated Use render service instead.
	 */
	private function render_panel_with_sidebar(array $tab, array $options): void
	{
		// Delegated to render service - kept for internal compatibility
		if ($this->render_service) {
			$this->render_service->render_panel_with_sidebar($tab, $options);
		}
	}

	/**
	 * Render a section / accordion element.
	 *
	 * @param array<string,mixed> $section    Section definition.
	 * @param array               $options    Current option values.
	 * @param bool                $in_sidebar Whether this section is in a sidebar layout.
	 * @deprecated Use render service instead.
	 */
	private function render_section(array $section, array $options, bool $in_sidebar = false): void
	{
		// Delegated to render service - kept for internal compatibility
		if ($this->render_service) {
			$this->render_service->render_section($section, $options, $in_sidebar);
		}
	}

	/**
	 * Render the fields for a section.
	 *
	 * @param array $section Section definition.
	 * @param array $options Stored options.
	 * @deprecated Use render service instead.
	 */
	private function render_section_inner(array $section, array $options): void
	{
		// Delegated to render service - kept for internal compatibility
		if ($this->render_service) {
			$this->render_service->render_section_inner($section, $options);
		}
	}

	/**
	 * Render a single field row.
	 *
	 * @param array $field   Field configuration.
	 * @param array $options Stored options.
	 * @deprecated Use render service instead.
	 */
	private function render_field(array $field, array $options, int $level = 0): void
	{
		// Delegated to render service - kept for internal compatibility
		if ($this->render_service) {
			$this->render_service->render_field($field, $options, $level);
		}
	}

	/**
	 * Format tooltip text with safe lightweight HTML.
	 *
	 * @param string $raw Raw tooltip text.
	 * @return string Formatted HTML content.
	 * @deprecated Use render service instead.
	 */
	private function format_tooltip_content(string $raw): string
	{
		// Delegated to render service - kept for internal compatibility
		if ($this->render_service) {
			return $this->render_service->format_tooltip_content($raw);
		}
		return $raw;
	}

	/**
	 * Render field control input based on field type.
	 *
	 * @param array       $field Field definition.
	 * @param string|bool $value Current value.
	 * @deprecated Use render service instead.
	 */
	private function render_field_control(array $field, $value): void
	{
		// Delegated to render service - kept for internal compatibility
		if ($this->render_service) {
			$this->render_service->render_field_control($field, $value);
		}
	}

	/**
	 * Provide current options array.
	 */
	public function get_options(): array
	{
		$options = get_option($this->config['option_name'], []);
		return is_array($options) ? $options : [];
	}

	/**
	 * Retrieve a single option value.
	 *
	 * @param string $key     Field ID.
	 * @param mixed  $default Default value if missing.
	 * @return mixed
	 */
	public function get_option(string $key, $default = null)
	{
		$options = $this->get_options();
		return $options[$key] ?? $default;
	}

	/**
	 * Register (or override) a tab definition.
	 *
	 * Delegates to schema manager.
	 *
	 * @param string $slug Tab slug.
	 * @param array  $args Tab parameters.
	 */
	public function add_tab(string $slug, array $args): void
	{
		$this->schema_manager->add_tab($slug, $args);
	}

	/**
	 * Append a section to a tab.
	 *
	 * Delegates to schema manager.
	 *
	 * @param string $tab_slug    Tab slug.
	 * @param string $section_id  Section ID.
	 * @param array  $section     Section definition.
	 */
	public function add_section(string $tab_slug, string $section_id, array $section): void
	{
		$this->schema_manager->add_section($tab_slug, $section_id, $section);
	}

	/**
	 * Append fields to a tab section.
	 *
	 * Delegates to schema manager.
	 *
	 * @param string $tab_slug    Tab slug.
	 * @param string $section_id  Section ID within the tab.
	 * @param array  $fields      List of field definitions.
	 */
	public function add_fields(string $tab_slug, string $section_id, array $fields): void
	{
		$this->schema_manager->add_fields($tab_slug, $section_id, $fields);
	}

	/**
	 * Append a single field to a section.
	 *
	 * Delegates to schema manager.
	 *
	 * @param string $tab_slug   Tab slug.
	 * @param string $section_id Section ID.
	 * @param array  $field      Field definition.
	 */
	public function add_field(string $tab_slug, string $section_id, array $field): void
	{
		$this->schema_manager->add_field($tab_slug, $section_id, $field);
	}

	/**
	 * Return tabs including external modifications.
	 *
	 * Filter hook format: {option_name}_framework_tabs
	 *
	 * @return array
	 */
	public function get_tabs(): array
	{
		$filter_name = $this->config['option_name'] . '_framework_tabs';
		$tabs = apply_filters($filter_name, $this->tabs, $this);

		uasort(
			$tabs,
			static function (array $a, array $b): int {
				return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
			}
		);

		foreach ($tabs as &$tab) {
			if (empty($tab['sections']) || !is_array($tab['sections'])) {
				$tab['sections'] = [];
				continue;
			}

			uasort(
				$tab['sections'],
				static function (array $a, array $b): int {
					return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
				}
			);

			foreach ($tab['sections'] as &$section) {
				if (empty($section['fields']) || !is_array($section['fields'])) {
					$section['fields'] = [];
					continue;
				}

				uasort(
					$section['fields'],
					static function (array $a, array $b): int {
						return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
					}
				);
			}
		}

		unset($tab, $section);

		if (isset($tabs['dashboard'])) {
			$dashboard = $tabs['dashboard'];
			unset($tabs['dashboard']);

			return ['dashboard' => $dashboard] + $tabs;
		}

		return $tabs;
	}

	/**
	 * Build the built-in default tabs.
	 *
	 * Ships a "Support" tab that every plugin gets for free. Plugins add their
	 * own tabs (and can extend the support tab) via the
	 * {option_name}_framework_tabs filter.
	 *
	 * The debug field ID is taken from config['debug_field_id'] (default: 'enable_debug')
	 * so each plugin can keep its own saved-option key without migration.
	 *
	 * @return array
	 */
	public function get_fields_index(): array
	{
		$fields = [];
		$tabs = $this->get_tabs();

		$collect = function (array $items, string $tab_id, string $section_id) use (&$fields, &$collect) {
			foreach ($items as $field) {
				if (empty($field['id'])) {
					continue;
				}
				$this->validate_field_schema($field);
				$field['__tab_id'] = $tab_id;
				$field['__section_id'] = $section_id;
				$fields[$field['id']] = $field;
				if (!empty($field['fields']) && is_array($field['fields'])) {
					$collect($field['fields'], $tab_id, $section_id);
				}
			}
		};

		foreach ($tabs as $tab_id => $tab) {
			if (empty($tab['sections'])) {
				continue;
			}

			foreach ($tab['sections'] as $section_id => $section) {
				if (empty($section['fields'])) {
					continue;
				}
				$collect($section['fields'], (string) $tab_id, (string) $section_id);
			}
		}

		return $fields;
	}

	/**
	 * Validate expected field schema and log warnings for invalid contracts.
	 */
	private function validate_field_schema(array $field): void
	{
		$type = (string) ($field['type'] ?? '');
		$id = (string) ($field['id'] ?? 'unknown');

		if ('image_select' === $type) {
			$options = $field['options'] ?? [];
			if (!is_array($options) || empty($options)) {
				RL_Logger::warn('image_select field is missing options.', ['field_id' => $id]);
				return;
			}

			foreach ($options as $key => $option) {
				if (!is_array($option) || empty($option['src']) || empty($option['label'])) {
					RL_Logger::warn('image_select option should define src and label.', [
						'field_id' => $id,
						'option_key' => $key,
					]);
				}
			}
		}

		if ('image' === $type) {
			if (!empty($field['options'])) {
				RL_Logger::warn('image field should not define options; use image_select instead.', ['field_id' => $id]);
			}
		}
	}

	public function get_field_label(array $field): string
	{
		$label = $field['label'] ?? $field['title'] ?? $field['id'] ?? 'Field';
		$label = wp_strip_all_tags((string) $label);
		$label = $this->normalize_field_label($label);
		return trim($label) !== '' ? $label : ((string) ($field['id'] ?? 'Field'));
	}

	public function normalize_field_label(string $label): string
	{
		$normalized = preg_replace('/^\s*(?:[↳➜→\-–—]+\s*)+\s*/u', '', $label);
		if (null === $normalized) {
			$normalized = $label;
		}

		return trim($normalized);
	}

	/**
	 * Sanitize raw value based on field definition.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public function sanitize_field_value(array $field, $value)
	{
		// Allow custom sanitize callback to take precedence
		if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
			return call_user_func($field['sanitize_callback'], $value, $field);
		}

		// Delegate to field type's processing interface if available
		$field_type = (string) ($field['type'] ?? 'text');
		$renderer = $this->field_registry ? $this->field_registry->get($field_type) : null;
		if ($renderer instanceof RL_Field_Processing_Interface) {
			return $renderer->sanitize(
				$field,
				$value,
				[
					'text_domain' => (string) $this->config['text_domain'],
					'validation_context' => $this->validation_context,
					'allowed_option_keys_callback' => function (array $f, array $state = []): array {
						return $this->get_allowed_option_keys($f, $state);
					},
					'geo_options_callback' => function (array $f, string $type, array $state = []): array {
						return $this->get_geo_field_options($f, $type, $state);
					},
				]
			);
		}

		// Fallback for fields without processing interface
		return isset($value) ? sanitize_text_field($value) : '';
	}

	/**
	 * Prepare raw value for validation.
	 *
	 * Ensures number fields always receive a valid numeric fallback when empty
	 * (for hidden/dependent fields that may not be posted).
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw submitted value.
	 * @return mixed
	 */
	public function prepare_value_for_validation(array $field, $value)
	{
		$field_type = $field['type'] ?? '';
		$renderer = $this->field_registry ? $this->field_registry->get((string) $field_type) : null;
		if ($renderer instanceof RL_Field_Processing_Interface) {
			return $renderer->prepare_for_validation(
				$field,
				$value,
				[
					'text_domain' => (string) $this->config['text_domain'],
					'validation_context' => $this->validation_context,
				]
			);
		}

		if ('number' !== $field_type) {
			return $value;
		}

		if ($value !== null && $value !== '' && is_numeric($value)) {
			$numeric = $value;

			if (isset($field['min']) && is_numeric($field['min']) && (float) $numeric < (float) $field['min']) {
				$numeric = $field['min'];
			}

			if (isset($field['max']) && is_numeric($field['max']) && (float) $numeric > (float) $field['max']) {
				$numeric = $field['max'];
			}

			return $numeric;
		}

		if ($value !== null && $value !== '') {
			return $value;
		}

		$fallback = $field['default'] ?? null;

		if (!is_numeric($fallback)) {
			if (isset($field['min']) && is_numeric($field['min'])) {
				$fallback = $field['min'];
			} else {
				$fallback = 0;
			}
		}

		if (isset($field['min']) && is_numeric($field['min']) && (float) $fallback < (float) $field['min']) {
			$fallback = $field['min'];
		}

		if (isset($field['max']) && is_numeric($field['max']) && (float) $fallback > (float) $field['max']) {
			$fallback = $field['max'];
		}

		return $fallback;
	}

	/**
	 * Validate field value before sanitization.
	 *
	 * @param array  $field Field definition.
	 * @param mixed  $value Value to validate.
	 * @param string &$error Error message (passed by reference).
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_field_value(array $field, $value, string &$error = ''): bool
	{
		$field_label = $this->get_field_label($field);

		// Custom validation callback takes precedence
		if (isset($field['validate_callback']) && is_callable($field['validate_callback'])) {
			$result = call_user_func($field['validate_callback'], $value, $field);
			if (is_wp_error($result)) {
				$error = $result->get_error_message();
				return false;
			}
			if (false === $result) {
				$error = sprintf(
					/* translators: %s: field title */
					__('%s is invalid.', $this->config['text_domain']),
					$field_label
				);
				return false;
			}
			return true;
		}

		// Required field validation
		if (!empty($field['required']) && ($value === null || $value === '')) {
			$error = sprintf(
				/* translators: %s: field title */
				__('%s is required.', $this->config['text_domain']),
				$field_label
			);
			return false;
		}

		if (!empty($field['required_if']) && is_array($field['required_if']) && $this->is_required_by_rules($field['required_if'], $this->validation_context) && ($value === null || $value === '')) {
			$error = sprintf(
				/* translators: %s: field title */
				__('%s is required for the selected dependency values.', $this->config['text_domain']),
				$field_label
			);
			return false;
		}

		// Delegate to field type's processing interface if available
		$field_type = (string) ($field['type'] ?? 'text');
		$renderer = $this->field_registry ? $this->field_registry->get($field_type) : null;
		if ($renderer instanceof RL_Field_Processing_Interface) {
			return $renderer->validate(
				$field,
				$value,
				$error,
				[
					'text_domain' => (string) $this->config['text_domain'],
					'field_label' => $field_label,
					'validation_context' => $this->validation_context,
					'required_checked' => true,
					'allowed_option_keys_callback' => function (array $f, array $state = []): array {
						return $this->get_allowed_option_keys($f, $state);
					},
					'geo_options_callback' => function (array $f, string $type, array $state = []): array {
						return $this->get_geo_field_options($f, $type, $state);
					},
				]
			);
		}

		// Fallback validation for fields without processing interface
		return true;
	}

	/**
	 * Create a backup of current settings.
	 *
	 * @return array|false Backup data or false on failure.
	 */
	public function create_backup()
	{
		$settings = get_option($this->config['option_name'], []);

		if (empty($settings)) {
			return false;
		}

		$backup = [
			'created_at' => current_time('mysql'),
			'version' => $this->config['version'],
			'settings' => $settings,
		];

		$backup_key = $this->config['option_name'] . '_backup';
		return update_option($backup_key, $backup) ? $backup : false;
	}

	/**
	 * Restore settings from backup.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function restore_backup(): bool
	{
		$backup_key = $this->config['option_name'] . '_backup';
		$backup = get_option($backup_key, false);

		if (empty($backup) || !isset($backup['settings'])) {
			return false;
		}

		return update_option($this->config['option_name'], $backup['settings']);
	}

	/**
	 * Export settings as JSON.
	 *
	 * @return string JSON-encoded settings.
	 */
	public function export_settings(): string
	{
		$settings = get_option($this->config['option_name'], []);

		$export = [
			'exported_at' => current_time('mysql'),
			'version' => $this->config['version'],
			'settings' => $settings,
		];

		return wp_json_encode($export, JSON_PRETTY_PRINT);
	}

	/**
	 * Import settings from JSON.
	 *
	 * @param string $json JSON-encoded settings.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function import_settings(string $json)
	{
		$data = json_decode($json, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error(
				'invalid_json',
				__('Invalid JSON format.', $this->config['text_domain'])
			);
		}

		if (!isset($data['settings']) || !is_array($data['settings'])) {
			return new WP_Error(
				'invalid_format',
				__('Invalid settings format.', $this->config['text_domain'])
			);
		}

		// Create backup before import
		$this->create_backup();

		return update_option($this->config['option_name'], $data['settings']);
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function reset_to_defaults(): bool
	{
		// Create backup before reset
		$this->create_backup();

		$fields_map = $this->get_fields_index();
		$defaults = [];

		foreach ($fields_map as $field_id => $field) {
			if (isset($field['default'])) {
				$defaults[$field_id] = $field['default'];
			}
		}

		return update_option($this->config['option_name'], $defaults);
	}

	/**
	 * Utility – produce field input name.
	 */
	public function get_input_name(string $field_id): string
	{
		return $this->config['form_field_prefix'] . '[' . $field_id . ']';
	}

	/**
	 * Utility – produce field input ID.
	 */
	public function get_input_id(string $field_id): string
	{
		return $this->config['form_field_prefix'] . '_' . $field_id;
	}

	/**
	 * Determine current tab slug from request.
	 *
	 * @param array $tabs Tabs list.
	 * @return string Tab slug, or empty string if no tabs exist.
	 */
	public function get_current_tab_slug(array $tabs): string
	{
		// If no tabs, return empty string
		if (empty($tabs)) {
			return '';
		}

		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

		if ($tab && isset($tabs[$tab])) {
			return $tab;
		}

		// Return first tab key
		$first_key = key($tabs);
		return $first_key !== null ? (string) $first_key : '';
	}

	/**
	 * Build URL for tab navigation.
	 */
	public function get_tab_url(string $slug): string
	{
		return add_query_arg(
			[
				'page' => $this->config['page_slug'],
				'tab' => $slug,
			],
			admin_url('admin.php')
		);
	}

	/**
	 * Check if current admin screen is the options page.
	 */
	private function is_options_page(): bool
	{
		return isset($_GET['page']) && $this->config['page_slug'] === sanitize_key(wp_unslash($_GET['page']));
	}


	/**
	 * Filter tabs based on show_if conditions.
	 *
	 * Delegates to schema manager.
	 *
	 * @param array $tabs    Tabs array.
	 * @param array $options Current options values.
	 * @return array Tabs with visibility flags.
	 */
	public function filter_tabs_by_conditions(array $tabs, array $options): array
	{
		return $this->schema_manager->filter_tabs_by_conditions($tabs, $options);
	}
}