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
	 * Storage service instance for backup/import/export/reset logic.
	 *
	 * @var RL_Options_Storage_Service|null
	 */
	private ?RL_Options_Storage_Service $storage_service = null;

	/**
	 * Field processor service instance for validation/sanitization/processing logic.
	 *
	 * @var RL_Options_Field_Processor|null
	 */
	private ?RL_Options_Field_Processor $field_processor = null;

	/**
	 * Assets service instance for asset enqueueing and CDN/local asset management.
	 *
	 * @var RL_Options_Assets_Service|null
	 */
	private ?RL_Options_Assets_Service $assets_service = null;

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

		// Initialize schema manager service before consuming default tabs.
		$this->schema_manager = new RL_Options_Schema_Manager($this);

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

		// Initialize REST API service for geo data and REST route registration.
		$this->rest_api = new RL_Options_Rest_Api($this);

		// Initialize storage service for backup/import/export/reset operations.
		$this->storage_service = new RL_Options_Storage_Service($this);

		// Initialize field processor service for validation/sanitization logic.
		$this->field_processor = new RL_Options_Field_Processor($this);

		// Initialize assets service for admin asset enqueueing.
		$this->assets_service = new RL_Options_Assets_Service($this);

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
	 * Get validation context for field processing.
	 */
	public function get_validation_context(): array
	{
		return $this->validation_context;
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
	 * Resolve framework assets URL (delegated to assets service).
	 *
	 * @param mixed $plugin Legacy parameter (ignored).
	 * @return string Trailing-slash-terminated assets URL.
	 */
	private function resolve_assets_url($plugin = null): string
	{
		return $this->assets_service ? $this->assets_service->resolve_assets_url() : '';
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
	 * Enqueue admin assets (delegated to assets service).
	 */
	public function enqueue_assets(string $hook): void
	{
		if ($this->assets_service) {
			$this->assets_service->enqueue_assets($hook);
		}
	}

	/**
	 * Resolve the effective JS debug level (delegated to assets service).
	 *
	 * @return string Debug level: 'error', 'warn', 'info', or 'debug'.
	 */
	private function resolve_debug_level(): string
	{
		return $this->assets_service ? $this->assets_service->resolve_debug_level() : 'error';
	}

	/**
	 * Check whether vendor assets should be served locally (delegated to assets service).
	 *
	 * @return bool True to use local assets; false to use CDN.
	 */
	private function should_use_local_assets(): bool
	{
		return $this->assets_service ? $this->assets_service->should_use_local_assets() : false;
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
	 * Resolve allowed option keys (delegated to field processor).
	 *
	 * @return string[]
	 */
	private function get_allowed_option_keys(array $field, array $state = []): array
	{
		return $this->field_processor->get_allowed_option_keys($field, $state);
	}

	/**
	 * Evaluate required_if rule set (delegated to field processor).
	 */
	private function is_required_by_rules(array $rules, array $state): bool
	{
		return $this->field_processor->is_required_by_rules($rules, $state);
	}

	/**
	 * Compare one dependency condition (delegated to field processor).
	 */
	private function match_dependency_rule($current, string $operator, $expected): bool
	{
		return $this->field_processor->match_dependency_rule($current, $operator, $expected);
	}

	/**
	 * Resolve async provider options (delegated to field processor).
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public function resolve_field_provider_options(array $field, array $state = [], bool $fallback_static = true): array
	{
		return $this->field_processor->resolve_field_provider_options($field, $state, $fallback_static);
	}

	/**
	 * Resolve provider parameter (delegated to field processor).
	 */
	private function resolve_provider_param(string $param, array $provider, array $state): string
	{
		return $this->field_processor->resolve_provider_param($param, $provider, $state);
	}

	/**
	 * Build options map for geo field types (delegated to field processor).
	 *
	 * @return array<string,string>
	 */
	public function get_geo_field_options(array $field, string $type, array $state = []): array
	{
		return $this->field_processor->get_geo_field_options($field, $type, $state);
	}

	/**
	 * Resolve country code (delegated to field processor).
	 */
	private function resolve_geo_country_code(array $field, array $state = []): string
	{
		return $this->field_processor->resolve_geo_country_code($field, $state);
	}

	/**
	 * Normalize options into [{value,label}] transport format.
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
	 * Normalize options array for transport (delegated to field processor).
	 *
	 * @param array $options Source options (associative or indexed).
	 * @param array $mapping Optional key mapping ['value' => '...', 'label' => '...'].
	 * @return array<int,array{value:string,label:string}>
	 */
	public function normalize_options_for_transport(array $options, array $mapping = []): array
	{
		return $this->field_processor->normalize_options_for_transport($options, $mapping);
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
	 * Validate field schema (delegated to field processor).
	 */
	private function validate_field_schema(array $field): void
	{
		$this->field_processor->validate_field_schema($field);
	}

	public function get_field_label(array $field): string
	{
		return $this->field_processor->get_field_label($field);
	}

	public function normalize_field_label(string $label): string
	{
		return $this->field_processor->normalize_field_label($label);
	}

	/**
	 * Sanitize field value (delegated to field processor).
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public function sanitize_field_value(array $field, $value)
	{
		return $this->field_processor->sanitize_field_value($field, $value);
	}

	/**
	 * Prepare value for validation (delegated to field processor).
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
		return $this->field_processor->prepare_value_for_validation($field, $value);
	}

	/**
	 * Validate field value (delegated to field processor).
	 *
	 * @param array  $field Field definition.
	 * @param mixed  $value Value to validate.
	 * @param string &$error Error message (passed by reference).
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_field_value(array $field, $value, string &$error = ''): bool
	{
		return $this->field_processor->validate_field_value($field, $value, $error);
	}

	/**
	 * Create a backup of current settings.
	 *
	 * @return array|false Backup data or false on failure.
	 */
	public function create_backup()
	{
		return $this->storage_service->create_backup();
	}

	/**
	 * Restore settings from backup.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function restore_backup(): bool
	{
		return $this->storage_service->restore_backup();
	}

	/**
	 * Export settings as JSON.
	 *
	 * @return string JSON-encoded settings.
	 */
	public function export_settings(): string
	{
		return $this->storage_service->export_settings();
	}

	/**
	 * Import settings from JSON.
	 *
	 * @param string $json JSON-encoded settings.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function import_settings(string $json)
	{
		return $this->storage_service->import_settings($json);
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function reset_to_defaults(): bool
	{
		return $this->storage_service->reset_to_defaults();
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
	 * Render the main options page (WordPress admin page callback).
	 *
	 * Delegates to render service.
	 */
	public function render_page(): void
	{
		if ($this->render_service) {
			$this->render_service->render_page();
		}
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