<?php
/**
 * RL Options Framework – Generic WordPress Options Framework
 *
 * A robust, flexible, plugin-agnostic options framework for WordPress plugins with validation,
 * sanitization, backup/restore, and extensive field type support.
 *
 * Features:
 * - 13 field types (text, textarea, select, multiselect, radio, checkbox, toggle, color, number, html, etc.)
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
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
	return;
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
	private array $config = [];

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
	 * Whether framework has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

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
			'version'           => '2.0.0',
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

		$this->tabs = $this->get_default_tabs();

		// Register admin menu unless host project opts to fully control menu wiring.
		if (!empty($this->config['register_menu'])) {
			add_action('admin_menu', [$this, 'register_menu'], 60);
		}

		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('admin_post_' . $this->config['page_slug'] . '_save', [$this, 'handle_save']);
		add_action('wp_ajax_' . $this->config['ajax_action'], [$this, 'handle_ajax_save']);
		add_action('admin_notices', [$this, 'render_notices']);

		$this->initialized = true;

		/**
		 * Give other add-ons a chance to interact with the framework instance.
		 *
		 * Hook name format: {option_name}_framework_boot
		 */
		do_action($this->config['option_name'] . '_framework_boot', $this);
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
			['jquery', 'wp-color-picker', 'sweetalert2', 'tippy-js'],
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
				'nonce' => wp_create_nonce($this->config['ajax_action'] . '_nonce'),
				'sync_history' => !empty($this->config['sync_history']),
				'swal_fallback' => !empty($this->config['swal_fallback']),
				'debug_level' => (string) ($this->config['debug_level'] ?? 'error'),
			]
		);
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
	 */
	public function handle_save(): void
	{
		RL_Logger::debug('========== SAVE HANDLER START ==========');

		if (!current_user_can($this->config['capability'])) {
			RL_Logger::error('User does not have required capability.', ['capability' => $this->config['capability']]);
			wp_die(esc_html__('You are not allowed to manage these settings.', $this->config['text_domain']));
		}

		$nonce_action = $this->config['page_slug'] . '_save_options';
		$nonce_field = $this->config['form_field_prefix'] . '_nonce';
		check_admin_referer($nonce_action, $nonce_field);
		RL_Logger::debug('Nonce verified successfully.');

		$fields_map = $this->get_fields_index();
		RL_Logger::debug('Total fields registered: ' . count($fields_map));

		$input = wp_unslash($_POST[$this->config['form_field_prefix']] ?? []);
		RL_Logger::debug('Raw POST data keys: ' . implode(', ', array_keys($_POST)));
		RL_Logger::debug('Form field prefix: ' . $this->config['form_field_prefix']);

		if (!is_array($input)) {
			RL_Logger::warn('Input is not an array; defaulting to empty array.');
			$input = [];
		}

		RL_Logger::debug('Submitted field count: ' . count($input));
		RL_Logger::debug('Submitted fields: ' . implode(', ', array_keys($input)));

		$saved = get_option($this->config['option_name'], []);
		if (!is_array($saved)) {
			$saved = [];
		}
		RL_Logger::debug('Existing saved fields: ' . count($saved));

		$validation_errors = [];
		$field_errors = [];

		foreach ($fields_map as $field_id => $field) {
			$value = $input[$field_id] ?? null;
			$value = $this->prepare_value_for_validation($field, $value);

			if (in_array($field_id, ['slider_position', 'gallery_type'], true)) {
				RL_Logger::debug(sprintf(
					'Processing %s: value="%s", type=%s',
					$field_id,
					$value,
					$field['type'] ?? 'unknown'
				));
			}

			$error = '';

			// Validate field
			if (!$this->validate_field_value($field, $value, $error)) {
				$validation_errors[$field_id] = $error;
				$field_errors[$field_id] = [
					'field_id' => $field_id,
					'field_label' => $this->get_field_label($field),
					'tab_id' => $field['__tab_id'] ?? '',
					'section_id' => $field['__section_id'] ?? '',
					'message' => $error,
				];
				RL_Logger::warn('Validation failed for field.', ['field_id' => $field_id, 'error' => $error]);
				continue;
			}

			// Sanitize and save
			$sanitized_value = $this->sanitize_field_value($field, $value);
			$saved[$field_id] = $sanitized_value;

			if (in_array($field_id, ['slider_position', 'gallery_type'], true)) {
				RL_Logger::debug(sprintf(
					'Sanitized %s: "%s" -> "%s"',
					$field_id,
					$value,
					$sanitized_value
				));
			}
		}

		// If validation errors, redirect with error message
		if (!empty($validation_errors)) {
			$error_message = implode(' ', array_values($validation_errors));
			RL_Logger::warn('Validation errors found.', ['message' => $error_message]);
			$message_param = $this->config['form_field_prefix'] . '_message';
			$error_param = $this->config['form_field_prefix'] . '_error';
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => $this->config['page_slug'],
						$message_param => 'error',
						$error_param => rawurlencode($error_message),
					],
					admin_url('admin.php')
				)
			);
			exit;
		}

		$update_result = update_option($this->config['option_name'], $saved);
		RL_Logger::info('update_option result: ' . ($update_result ? 'SUCCESS' : 'FAILED (or unchanged)'));
		RL_Logger::info('Total fields saved: ' . count($saved));

		// Fire action hook for SVI to regenerate CSS files
		do_action('svi_settings_saved', $saved);

		// Verify what was actually saved
		$verification = get_option($this->config['option_name']);
		if (isset($verification['slider_position'])) {
			RL_Logger::debug('Verified slider_position in DB: "' . $verification['slider_position'] . '"');
		}
		RL_Logger::debug('========== SAVE HANDLER END ==========');

		$message_param = $this->config['form_field_prefix'] . '_message';
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => $this->config['page_slug'],
					$message_param => 'saved',
				],
				admin_url('admin.php')
			)
		);
		exit;
	}

	/**
	 * Handle AJAX options save submissions.
	 */
	public function handle_ajax_save(): void
	{
		RL_Logger::debug('========== AJAX SAVE HANDLER CALLED ==========');
		RL_Logger::debug('POST data keys: ' . implode(', ', array_keys($_POST)));
		RL_Logger::debug('Expected nonce action: ' . $this->config['ajax_action'] . '_nonce');
		RL_Logger::debug('Nonce value from POST: ' . ($_POST['nonce'] ?? 'NOT SET'));
		RL_Logger::debug('Action from POST: ' . ($_POST['action'] ?? 'NOT SET'));

		// Verify nonce (accept AJAX nonce and fallback to form nonce)
		$ajax_nonce_action = $this->config['ajax_action'] . '_nonce';
		$form_nonce_action = $this->config['page_slug'] . '_save_options';
		$form_nonce_field = $this->config['form_field_prefix'] . '_nonce';

		$ajax_nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		$form_nonce = isset($_POST[$form_nonce_field]) ? sanitize_text_field(wp_unslash($_POST[$form_nonce_field])) : '';

		$ajax_nonce_valid = !empty($ajax_nonce) && wp_verify_nonce($ajax_nonce, $ajax_nonce_action);
		$form_nonce_valid = !empty($form_nonce) && wp_verify_nonce($form_nonce, $form_nonce_action);

		RL_Logger::debug('AJAX nonce valid: ' . ($ajax_nonce_valid ? 'yes' : 'no'));
		RL_Logger::debug('Form nonce valid: ' . ($form_nonce_valid ? 'yes' : 'no'));

		if (!$ajax_nonce_valid && !$form_nonce_valid) {
			wp_send_json_error([
				'message' => __('Security check failed. Please refresh the page and try again.', $this->config['text_domain']),
			], 403);
		}

		// Check permissions
		if (!current_user_can($this->config['capability'])) {
			wp_send_json_error([
				'message' => __('You are not allowed to manage these settings.', $this->config['text_domain']),
			]);
		}

		RL_Logger::debug('========== AJAX SAVE START ==========');

		$fields_map = $this->get_fields_index();
		RL_Logger::debug('Total fields registered: ' . count($fields_map));

		$input = wp_unslash($_POST[$this->config['form_field_prefix']] ?? []);
		RL_Logger::debug('Submitted field count: ' . count($input));

		if (!is_array($input)) {
			RL_Logger::warn('Input is not an array.');
			$input = [];
		}

		$saved = get_option($this->config['option_name'], []);
		if (!is_array($saved)) {
			$saved = [];
		}

		$validation_errors = [];
		$field_errors = [];

		foreach ($fields_map as $field_id => $field) {
			$value = $input[$field_id] ?? null;
			$value = $this->prepare_value_for_validation($field, $value);

			// Debug specific fields
			if (in_array($field_id, ['slider_position', 'gallery_type'], true)) {
				RL_Logger::debug(sprintf(
					'AJAX Processing %s: value="%s", type=%s',
					$field_id,
					$value,
					$field['type'] ?? 'unknown'
				));
			}

			$error = '';

			// Validate field
			if (!$this->validate_field_value($field, $value, $error)) {
				$validation_errors[$field_id] = $error;
				$field_errors[$field_id] = [
					'field_id' => $field_id,
					'field_label' => $this->get_field_label($field),
					'tab_id' => $field['__tab_id'] ?? '',
					'section_id' => $field['__section_id'] ?? '',
					'message' => $error,
				];
				RL_Logger::warn('AJAX validation failed for field.', ['field_id' => $field_id, 'error' => $error]);
				continue;
			}

			// Sanitize and save
			$sanitized_value = $this->sanitize_field_value($field, $value);
			$saved[$field_id] = $sanitized_value;

			if (in_array($field_id, ['slider_position', 'gallery_type'], true)) {
				RL_Logger::debug(sprintf(
					'AJAX Sanitized %s: "%s" -> "%s"',
					$field_id,
					$value,
					$sanitized_value
				));
			}
		}

		// If validation errors, return error
		if (!empty($validation_errors)) {
			RL_Logger::warn('AJAX validation errors.', $validation_errors);
			wp_send_json_error([
				'message' => implode(' ', array_values($validation_errors)),
				'errors' => $validation_errors,
				'field_errors' => $field_errors,
			]);
		}

		$update_result = update_option($this->config['option_name'], $saved);
		RL_Logger::info('AJAX update_option result: ' . ($update_result ? 'SUCCESS' : 'FAILED (or unchanged)'));
		RL_Logger::info('Total fields saved: ' . count($saved));

		// Fire action hook for SVI to regenerate CSS files
		do_action('svi_settings_saved', $saved);

		// Verify what was actually saved
		$verification = get_option($this->config['option_name']);
		if (isset($verification['slider_position'])) {
			RL_Logger::debug('AJAX verified slider_position in DB: "' . $verification['slider_position'] . '"');
		}
		RL_Logger::debug('========== AJAX SAVE END ==========');

		wp_send_json_success([
			'message' => __('Settings saved successfully.', $this->config['text_domain']),
			'saved' => count($saved),
		]);
	}

	/**
	 * Render the full settings page.
	 */
	public function render_page(): void
	{
		$tabs = $this->get_tabs();

		// Filter tabs based on show_if conditions
		$options = get_option($this->config['option_name'], []);
		if (!is_array($options)) {
			$options = [];
		}
		$tabs = $this->filter_tabs_by_conditions($tabs, $options);

		$current_tab = $this->get_current_tab_slug($tabs);
		$header_meta_html = apply_filters('rl_options_framework_header_meta_html', '', $this->config, $this);
		?>
		<div class="wrap rl-options-page">
			<div class="rl-page-header">
				<h1><?php echo esc_html($this->config['page_title']); ?></h1>
				<?php if (!empty($header_meta_html)): ?>
					<div class="rl-page-header-meta">
						<?php echo wp_kses_post($header_meta_html); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if (empty($tabs)): ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('No settings tabs have been registered yet.', $this->config['text_domain']); ?></p>
					<p><?php printf(esc_html__('Use the %s filter to add settings tabs.', $this->config['text_domain']), '<code>' . esc_html($this->config['option_name'] . '_framework_tabs') . '</code>'); ?>
					</p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if (!empty($tabs)): ?>
				<nav class="nav-tab-wrapper" role="tablist">
					<?php foreach ($tabs as $slug => $tab): ?>
						<?php
						$active = $slug === $current_tab ? ' nav-tab-active' : '';
						$hidden = !empty($tab['_hidden']) ? ' style="display:none;"' : '';
						$tab_conditions = !empty($tab['show_if']) ? ' data-tab-conditions=\'' . esc_attr(wp_json_encode($tab['show_if'])) . '\'' : '';
						?>
						<a class="nav-tab<?php echo esc_attr($active); ?>" href="<?php echo esc_url($this->get_tab_url($slug)); ?>"
							data-rl-tab="<?php echo esc_attr($slug); ?>" <?php echo $tab_conditions; ?> 				<?php echo $hidden; ?>>
							<?php echo esc_html($tab['label']); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
				<input type="hidden" name="action" value="<?php echo esc_attr($this->config['page_slug'] . '_save'); ?>" />
				<?php
				$nonce_action = $this->config['page_slug'] . '_save_options';
				$nonce_field = $this->config['form_field_prefix'] . '_nonce';
				wp_nonce_field($nonce_action, $nonce_field);
				?>
				<div class="rl-tab-panels">
					<?php foreach ($tabs as $slug => $tab): ?>
						<?php
						$panel_active = $slug === $current_tab ? ' is-active' : '';
						$hidden = !empty($tab['_hidden']) ? ' style="display:none;"' : '';
						?>
						<section class="rl-tab-panel<?php echo esc_attr($panel_active); ?>"
							data-rl-panel="<?php echo esc_attr($slug); ?>" <?php echo $hidden; ?>>
							<?php
							if (!empty($tab['description'])) {
								echo '<p class="rl-tab-description">' . wp_kses_post($tab['description']) . '</p>';
							}

							if (!empty($tab['sections']) && count($tab['sections']) > 1):
								// Multiple sections - render sidebar navigation
								$this->render_panel_with_sidebar($tab, $options);
							elseif (!empty($tab['sections'])):
								// Single section - render directly
								foreach ($tab['sections'] as $section):
									$this->render_section($section, $options, false);
								endforeach;
							endif;
							?>
						</section>
					<?php endforeach; ?>
				</div>

				<div class="rl-submit-bar">
					<button type="submit" class="button button-primary">
						<?php esc_html_e('Save changes', $this->config['text_domain']); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render panel with sidebar navigation.
	 *
	 * @param array $tab     Tab configuration.
	 * @param array $options Current options.
	 */
	private function render_panel_with_sidebar(array $tab, array $options): void
	{
		if (empty($tab['sections'])) {
			return;
		}

		$current_section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : key($tab['sections']);

		?>
		<div class="rl-sidebar-layout">
			<div class="rl-sidebar">
				<ul class="rl-sidebar-menu">
					<?php foreach ($tab['sections'] as $section_id => $section): ?>
						<?php $active_class = $section_id === $current_section ? ' rl-sidebar-active' : ''; ?>
						<li>
							<a href="#<?php echo esc_attr($section_id); ?>"
								class="rl-sidebar-link<?php echo esc_attr($active_class); ?>"
								data-section="<?php echo esc_attr($section_id); ?>">
								<?php echo esc_html($section['title'] ?? ucwords(str_replace('_', ' ', $section_id))); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="rl-content">
				<?php foreach ($tab['sections'] as $section_id => $section): ?>
					<?php $content_active = $section_id === $current_section ? ' is-active' : ''; ?>
					<div class="rl-section-content<?php echo esc_attr($content_active); ?>"
						data-section-content="<?php echo esc_attr($section_id); ?>">
						<?php $this->render_section($section, $options, true); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a section / accordion element.
	 *
	 * @param array<string,mixed> $section    Section definition.
	 * @param array               $options    Current option values.
	 * @param bool                $in_sidebar Whether this section is in a sidebar layout.
	 */
	private function render_section(array $section, array $options, bool $in_sidebar = false): void
	{
		$is_accordion = !empty($section['accordion']) && !$in_sidebar;
		$section_id = $section['id'];

		$section_classes = ['rl-section'];
		if ($is_accordion) {
			$section_classes[] = 'is-accordion';
		}

		?>
		<div class="<?php echo esc_attr(implode(' ', $section_classes)); ?>"
			data-rl-section="<?php echo esc_attr($section_id); ?>">
			<?php if ($is_accordion): ?>
				<button type="button" class="rl-accordion-toggle" aria-expanded="false">
					<span><?php echo esc_html($section['title']); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<div class="rl-accordion-content">
					<?php $this->render_section_inner($section, $options); ?>
				</div>
			<?php else: ?>
				<?php if (!$in_sidebar || !empty($section['title'])): ?>
					<header class="rl-section-header">
						<h2><?php echo esc_html($section['title']); ?></h2>
						<?php if (!empty($section['description'])): ?>
							<p class="description"><?php echo wp_kses_post($section['description']); ?></p>
						<?php endif; ?>
					</header>
				<?php endif; ?>
				<div class="rl-section-body">
					<?php $this->render_section_inner($section, $options); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the fields for a section.
	 *
	 * @param array $section Section definition.
	 * @param array $options Stored options.
	 */
	private function render_section_inner(array $section, array $options): void
	{
		if (empty($section['fields'])) {
			echo '<p class="description">' . esc_html__('No settings available for this section yet.', $this->config['text_domain']) . '</p>';
			return;
		}

		foreach ($section['fields'] as $field_key => $field) {
			// Ensure field has an ID (use key as fallback)
			if (empty($field['id'])) {
				$field['id'] = $field_key;
			}
			$this->render_field($field, $options, 0);
		}
	}

	/**
	 * Render a single field row.
	 *
	 * @param array $field   Field configuration.
	 * @param array $options Stored options.
	 */
	private function render_field(array $field, array $options, int $level = 0): void
	{
		// Ensure field has required properties
		if (empty($field['id']) || empty($field['type'])) {
			return; // Skip invalid fields
		}

		$field_id = $field['id'];
		$field_type = $field['type'];
		$field_label = $field['label'] ?? '';
		$field_label = $this->normalize_field_label((string) $field_label);
		$field_desc = $field['description'] ?? $field['desc'] ?? '';
		$field_classes = ['rl-field', 'rl-field-' . $field_type];

		$field_value = $options[$field_id] ?? ($field['default'] ?? null);

		$data_attrs = '';
		if (!empty($field['conditions']) && is_array($field['conditions'])) {
			$data_attrs = sprintf(
				' data-conditions="%s"',
				esc_attr(wp_json_encode($field['conditions']))
			);
			$field_classes[] = 'has-conditions';
		}

		if (!empty($field['width'])) {
			$field_classes[] = 'rl-field-width-' . preg_replace('/[^a-z0-9_\-]/', '', $field['width']);
		}

		// Add indent classes if specified or inherited from nesting
		$indent_level = null;
		if (isset($field['indent_level'])) {
			$indent_level = max(0, (int) $field['indent_level']);
		} elseif (!empty($field['indent'])) {
			$indent_level = max(1, $level);
		} else {
			$indent_level = max(0, $level);
		}

		if ($indent_level > 0) {
			$field_classes[] = 'rl-field-indented';
			$field_classes[] = 'rl-field-indent-level-' . $indent_level;
		}

		printf(
			'<div class="%1$s" data-field-id="%2$s"%3$s>',
			esc_attr(implode(' ', $field_classes)),
			esc_attr((string) $field_id),
			$data_attrs
		);

		if (!empty($field_label)) {
			printf(
				'<label class="rl-field-label" for="%1$s">%2$s',
				esc_attr($this->get_input_id($field_id)),
				esc_html($field_label)
			);
			if (!empty($field_desc)) {
				$tooltip_desc = $this->format_tooltip_content((string) $field_desc);

				printf(
					' <span class="rl-field-tooltip" data-tippy-content="%s"><span class="dashicons dashicons-info"></span></span>',
					esc_attr($tooltip_desc)
				);
			}
			echo '</label>';
		}

		echo '<div class="rl-field-control">';
		$this->render_field_control($field, $field_value);
		echo '</div>';

		echo '</div>'; // Close .rl-field

		// Render nested subfields (if provided) with automatic indentation
		// These render as siblings, not children of the parent field wrapper
		if (!empty($field['fields']) && is_array($field['fields'])) {
			// Sort children by priority if associative
			$children = $field['fields'];
			uasort(
				$children,
				static function (array $a, array $b): int {
					return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
				}
			);

			foreach ($children as $child_key => $child_field) {
				if (empty($child_field['id'])) {
					$child_field['id'] = is_string($child_key) ? $child_key : uniqid('field_', true);
				}
				$this->render_field($child_field, $options, $level + 1);
			}
		}
	}

	/**
	 * Format tooltip text with safe lightweight HTML.
	 *
	 * Rules:
	 * - If explicit HTML is provided, keep it (sanitized).
	 * - If plain text is provided, auto-wrap into paragraphs.
	 * - Label-like fragments (e.g. "Grid:") are bolded for readability.
	 */
	private function format_tooltip_content(string $raw): string
	{
		$allowed_html = [
			'p' => [],
			'br' => [],
			'b' => [],
			'strong' => [],
			'em' => [],
			'code' => [],
			'ul' => [],
			'ol' => [],
			'li' => [],
			'a' => [
				'href' => true,
				'target' => true,
				'rel' => true,
			],
		];

		$raw = trim($raw);
		if ('' === $raw) {
			return '';
		}

		$sanitized = wp_kses($raw, $allowed_html);

		// If the source is already intentionally structured, preserve it as-is.
		if ((bool) preg_match('/<(p|ul|ol|li|br)\b/i', $sanitized)) {
			return $sanitized;
		}

		// Normalize any lightweight HTML into plain text for global auto-formatting.
		// This lets existing strings like "<strong>Grid:</strong> ..." become proper list rows.
		$text_source = preg_replace('/<br\s*\/?>/i', "\n", $sanitized);
		$text_source = wp_strip_all_tags((string) $text_source, true);

		// Normalize plain text for formatter heuristics.
		$normalized = preg_replace('/\r\n?|\n/', ' ', (string) $text_source);
		$normalized = preg_replace('/\s+/', ' ', (string) $normalized);
		$normalized = trim((string) $normalized);

		if ('' === $normalized) {
			return '';
		}

		$label_pattern = '/([A-Z][A-Za-z0-9\/\-\s]{1,40}):\s*/';
		$label_matches = [];
		preg_match_all($label_pattern, $normalized, $label_matches, PREG_OFFSET_CAPTURE);

		$html_parts = [];

		// If there are label segments, render intro + list structure.
		if (!empty($label_matches[0]) && count($label_matches[0]) >= 1) {
			$first_offset = (int) $label_matches[0][0][1];
			$intro = trim(substr($normalized, 0, $first_offset));

			if ('' !== $intro) {
				$intro = rtrim($intro, '. ');
				$html_parts[] = sprintf('<p>%s.</p>', esc_html($intro));
			}

			$list_items = [];
			$total = count($label_matches[0]);
			for ($index = 0; $index < $total; $index++) {
				$full_match = $label_matches[0][$index][0];
				$current_offset = (int) $label_matches[0][$index][1];
				$next_offset = ($index + 1 < $total)
					? (int) $label_matches[0][$index + 1][1]
					: strlen($normalized);

				$label = trim(rtrim($full_match, ':'));
				$body_start = $current_offset + strlen($full_match);
				$body = trim(substr($normalized, $body_start, $next_offset - $body_start));
				$body = rtrim($body, '. ');

				if ('' === $label && '' === $body) {
					continue;
				}

				if ('' !== $body) {
					$list_items[] = sprintf(
						'<li><strong>%s:</strong> %s.</li>',
						esc_html($label),
						esc_html($body)
					);
				} else {
					$list_items[] = sprintf('<li><strong>%s:</strong></li>', esc_html($label));
				}
			}

			if (!empty($list_items)) {
				$html_parts[] = '<ul>' . implode('', $list_items) . '</ul>';
			}
		} else {
			$chunks = preg_split('/\s(?=[A-Z][A-Za-z0-9\/\-\s]{2,40}:\s)/', $normalized) ?: [$normalized];
			foreach ($chunks as $chunk) {
				$chunk = trim((string) $chunk);
				if ('' === $chunk) {
					continue;
				}

				if (preg_match('/^([A-Z][A-Za-z0-9\/\-\s]{2,40}:)\s*(.*)$/', $chunk, $matches)) {
					$label = esc_html(trim($matches[1]));
					$text = esc_html(trim($matches[2]));
					$html_parts[] = sprintf('<p><strong>%s</strong> %s</p>', $label, $text);
					continue;
				}

				$html_parts[] = sprintf('<p>%s</p>', esc_html($chunk));
			}
		}

		if (empty($html_parts)) {
			return wp_kses(sprintf('<p>%s</p>', esc_html($normalized)), $allowed_html);
		}

		return wp_kses(implode('', $html_parts), $allowed_html);
	}

	/**
	 * Render field control input based on field type.
	 *
	 * @param array       $field Field definition.
	 * @param string|bool $value Current value.
	 */
	private function render_field_control(array $field, $value): void
	{
		$field_id = $field['id'];
		$field_name = $this->get_input_name($field_id);
		$input_id = $this->get_input_id($field_id);

		switch ($field['type']) {
			case 'html':
				echo $field['html'] ?? '';
				break;
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="%4$d">%3$s</textarea>',
					esc_attr($input_id),
					esc_attr($field_name),
					esc_textarea((string) $value),
					isset($field['rows']) ? absint($field['rows']) : 5
				);
				break;

			case 'select':
				$options = $field['options'] ?? [];
				printf(
					'<select id="%1$s" name="%2$s">',
					esc_attr($input_id),
					esc_attr($field_name)
				);
				foreach ($options as $option_value => $option_label) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr($option_value),
						selected($value, $option_value, false),
						esc_html($option_label)
					);
				}
				echo '</select>';
				break;

			case 'multiselect':
				$options = $field['options'] ?? [];
				$values = is_array($value) ? $value : [];
				printf(
					'<select id="%1$s" name="%2$s[]" multiple size="%3$d">',
					esc_attr($input_id),
					esc_attr($field_name),
					max(3, min(6, count($options)))
				);
				foreach ($options as $option_value => $option_label) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr($option_value),
						selected(in_array($option_value, $values, true), true, false),
						esc_html($option_label)
					);
				}
				echo '</select>';
				break;

			case 'radio':
				$options = $field['options'] ?? [];
				foreach ($options as $option_value => $option_label) {
					$radio_id = $input_id . '_' . sanitize_key($option_value);
					printf(
						'<label class="rl-radio"><input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s> <span>%5$s</span></label>',
						esc_attr($radio_id),
						esc_attr($field_name),
						esc_attr($option_value),
						checked($value, $option_value, false),
						esc_html($option_label)
					);
				}
				break;

			case 'checkbox':
				printf(
					'<label class="rl-checkbox"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> <span>%4$s</span></label>',
					esc_attr($input_id),
					esc_attr($field_name),
					checked(!empty($value), true, false),
					esc_html($field['text'] ?? '')
				);
				break;

			case 'toggle':
				printf(
					'<label class="rl-toggle"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s><span class="rl-toggle-track"><span class="rl-toggle-handle"></span></span></label>',
					esc_attr($input_id),
					esc_attr($field_name),
					checked(!empty($value), true, false)
				);
				break;

			case 'image_select':
				$options = $field['options'] ?? [];
				echo '<div class="rl-image-select-options">';
				foreach ($options as $option_value => $option_data) {
					// Handle simple array (label only) or complex array (image + label)
					if (is_array($option_data)) {
						$label = $option_data['label'] ?? '';
						$image = $option_data['src'] ?? '';
					} else {
						$label = $option_data;
						$image = ''; // Should not happen for image_select but fallback
					}

					$radio_id = $input_id . '_' . sanitize_key($option_value);

					printf(
						'<label class="rl-image-select-option" for="%1$s">
							<input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s>
							<img src="%5$s" alt="%6$s" style="max-width: 100px; height: auto;">
							<span class="rl-image-select-label">%6$s</span>
						</label>',
						esc_attr($radio_id),
						esc_attr($field_name),
						esc_attr($option_value),
						checked($value, $option_value, false),
						esc_url($image),
						esc_html($label)
					);
				}
				echo '</div>';
				break;

			case 'color':
				printf(
					'<input type="text" id="%1$s" class="rl-color-field" name="%2$s" value="%3$s" data-default-color="%4$s" />',
					esc_attr($input_id),
					esc_attr($field_name),
					esc_attr((string) $value),
					esc_attr($field['default'] ?? '')
				);
				break;

			case 'number':
				$attrs = '';
				if (isset($field['min'])) {
					$attrs .= ' min="' . esc_attr($field['min']) . '"';
				}
				if (isset($field['max'])) {
					$attrs .= ' max="' . esc_attr($field['max']) . '"';
				}
				if (isset($field['step'])) {
					$attrs .= ' step="' . esc_attr($field['step']) . '"';
				}

				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s"%4$s />',
					esc_attr($input_id),
					esc_attr($field_name),
					esc_attr(is_numeric($value) ? $value : ($field['default'] ?? '')),
					$attrs
				);
				break;

			case 'info':
				// Info field - just displays content, no input
				printf(
					'<div class="rl-info-field">%s</div>',
					wp_kses_post($field['description'] ?? '')
				);
				break;

			case 'image':
				// Image upload field using WordPress media uploader
				$button_text = __('Choose Image', 'smart-variations-images');
				$remove_text = __('Remove', 'smart-variations-images');
				printf(
					'<div class="rl-image-field">
						<input type="hidden" id="%1$s" name="%2$s" value="%3$s" class="rl-image-input" />
						<button type="button" class="button rl-upload-image-button" data-input-id="%1$s">%4$s</button>
						<button type="button" class="button rl-remove-image-button" data-input-id="%1$s" style="%5$s">%6$s</button>
						<div class="rl-image-preview" style="margin-top:10px;">%7$s</div>
					</div>',
					esc_attr($input_id),
					esc_attr($field_name),
					esc_attr((string) $value),
					esc_html($button_text),
					empty($value) ? 'display:none;' : '',
					esc_html($remove_text),
					!empty($value) ? sprintf('<img src="%s" style="max-width:200px;height:auto;display:block;" />', esc_url($value)) : ''
				);
				break;

			case 'multiselect':
				// Multiselect field - checkboxes for multiple selections
				$selected_values = is_array($value) ? $value : (!empty($value) ? [$value] : []);
				if (!empty($field['options']) && is_array($field['options'])) {
					echo '<div class="rl-multiselect-field">';
					foreach ($field['options'] as $option_value => $option_label) {
						printf(
							'<label class="rl-checkbox"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s> <span>%4$s</span></label><br>',
							esc_attr($field_name),
							esc_attr($option_value),
							checked(in_array($option_value, $selected_values, true), true, false),
							esc_html($option_label)
						);
					}
					echo '</div>';
				}
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr($input_id),
					esc_attr($field_name),
					esc_attr((string) $value)
				);
				break;
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
	 * @param string $slug Tab slug.
	 * @param array  $args Tab parameters.
	 */
	public function add_tab(string $slug, array $args): void
	{
		$args = $this->normalize_tab($slug, $args);
		$this->tabs[$slug] = $args;
	}

	/**
	 * Append a section to a tab.
	 *
	 * @param string $tab_slug    Tab slug.
	 * @param string $section_id  Section ID.
	 * @param array  $section     Section definition.
	 */
	public function add_section(string $tab_slug, string $section_id, array $section): void
	{
		$tabs = $this->get_tabs();
		if (!isset($tabs[$tab_slug])) {
			return;
		}

		$section['id'] = $section_id;
		$tabs[$tab_slug]['sections'][$section_id] = $this->normalize_section($section_id, $section);

		$this->tabs[$tab_slug] = $tabs[$tab_slug];
	}

	/**
	 * Append fields to a tab section.
	 *
	 * @param string $tab_slug    Tab slug.
	 * @param string $section_id  Section ID within the tab.
	 * @param array  $fields      List of field definitions.
	 */
	public function add_fields(string $tab_slug, string $section_id, array $fields): void
	{
		foreach ($fields as $field) {
			$this->add_field($tab_slug, $section_id, $field);
		}
	}

	/**
	 * Append a single field to a section.
	 *
	 * @param string $tab_slug   Tab slug.
	 * @param string $section_id Section ID.
	 * @param array  $field      Field definition.
	 */
	public function add_field(string $tab_slug, string $section_id, array $field): void
	{
		$field = $this->normalize_field($field);

		if (empty($field['id'])) {
			return;
		}

		if (!isset($this->tabs[$tab_slug])) {
			return;
		}

		if (!isset($this->tabs[$tab_slug]['sections'][$section_id])) {
			$this->tabs[$tab_slug]['sections'][$section_id] = $this->normalize_section(
				$section_id,
				[
					'id' => $section_id,
					'title' => ucwords(str_replace('_', ' ', $section_id)),
					'fields' => [],
				]
			);
		}

		$this->tabs[$tab_slug]['sections'][$section_id]['fields'][$field['id']] = $field;
	}

	/**
	 * Return tabs including external modifications.
	 *
	 * Filter hook format: {option_name}_framework_tabs
	 *
	 * @return array
	 */
	private function get_tabs(): array
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
	private function get_default_tabs(): array
	{
		$td                  = $this->config['text_domain'];
		$debug_field         = $this->config['debug_field_id'];
		$local_assets_field  = $this->config['local_assets_field_id'];
		$show_local_assets   = !empty($this->config['use_local_assets_toggle']);

		$debug_fields = [
			$debug_field => [
				'id'       => $debug_field,
				'type'     => 'toggle',
				'label'    => __( 'Enable Debug Mode', $td ),
				'text'     => __( 'Enable debug logging', $td ),
				'desc'     => __( 'Enable verbose debug logging for troubleshooting. Disable on production sites.', $td ),
				'default'  => false,
				'priority' => 10,
			],
		];

		if ($show_local_assets) {
			$debug_fields[$local_assets_field] = [
				'id'       => $local_assets_field,
				'type'     => 'toggle',
				'label'    => __( 'Use Local Assets', $td ),
				'text'     => __( 'Load libraries locally (GDPR compliant)', $td ),
				'desc'     => __( 'When enabled, third-party frontend libraries are served from your server instead of CDN providers, improving privacy and GDPR compliance.', $td ),
				'default'  => true,
				'priority' => 20,
			];
		}

		return [
			'support' => [
				'label'    => __( 'Support', $td ),
				'priority' => 900,
				'sections' => [
					'debug' => [
						'id'     => 'debug',
						'title'  => __( 'Debug Settings', $td ),
						'fields' => $debug_fields,
					],
				],
			],
		];
	}

	/**
	 * Map field definitions by ID.
	 *
	 * @return array<string,array>
	 */
	private function get_fields_index(): array
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

	private function get_field_label(array $field): string
	{
		$label = $field['label'] ?? $field['title'] ?? $field['id'] ?? 'Field';
		$label = wp_strip_all_tags((string) $label);
		$label = $this->normalize_field_label($label);
		return trim($label) !== '' ? $label : ((string) ($field['id'] ?? 'Field'));
	}

	private function normalize_field_label(string $label): string
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
	private function sanitize_field_value(array $field, $value)
	{
		if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
			return call_user_func($field['sanitize_callback'], $value, $field);
		}

		switch ($field['type']) {
			case 'toggle':
			case 'checkbox':
				return !empty($value);

			case 'number':
				if (isset($field['step']) && floatval($field['step']) !== intval($field['step'])) {
					return isset($value) ? floatval($value) : null;
				}
				return isset($value) ? intval($value) : null;

			case 'color':
				// Allow empty values to pass through without forcing a default.
				if ($value === '' || $value === null) {
					return '';
				}

				$raw = trim((string) $value);

				// Accept standard 3/6-digit hex via WP helper.
				$standard_hex = sanitize_hex_color($raw);
				if ($standard_hex) {
					return $standard_hex;
				}

				// Accept 8-digit hex (with alpha) as-is if it matches the pattern.
				if (preg_match('/^#([0-9a-fA-F]{8})$/', $raw)) {
					return $raw;
				}

				// Accept rgba()/rgb() values when provided.
				if (preg_match('/^rgba?\([^\)]+\)$/i', $raw)) {
					return $raw;
				}

				return $field['default'] ?? '';

			case 'textarea':
				return isset($value) ? wp_kses_post($value) : '';

			case 'select':
			case 'radio':
			case 'image_select':
				$allowed = array_keys($field['options'] ?? []);

				// Convert allowed keys to strings to match form submission values
				// Form values are always strings, but array_keys() may return integers
				$allowed = array_map('strval', $allowed);

				// Debug for specific fields
				if (in_array($field['id'] ?? '', ['slider_position', 'gallery_type'], true)) {
					error_log(sprintf(
						'[RL Framework] Sanitize select - Field: %s, Value: "%s" (type: %s), Allowed: %s, Match: %s',
						$field['id'],
						$value,
						gettype($value),
						json_encode($allowed),
						in_array($value, $allowed, true) ? 'YES' : 'NO'
					));
				}

				return in_array($value, $allowed, true) ? $value : ($field['default'] ?? null);
			case 'multiselect':
				$allowed = array_keys($field['options'] ?? []);
				$values = is_array($value) ? array_values($value) : [];

				return array_values(
					array_intersect(
						$allowed,
						array_map('sanitize_text_field', $values)
					)
				);

			default:
				return isset($value) ? sanitize_text_field($value) : '';
		}
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
	private function prepare_value_for_validation(array $field, $value)
	{
		$field_type = $field['type'] ?? '';

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
	private function validate_field_value(array $field, $value, string &$error = ''): bool
	{
		$field_label = $this->get_field_label($field);

		// Custom validation callback
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

		// Type-specific validation
		switch ($field['type']) {
			case 'number':
				if (!is_numeric($value)) {
					$error = sprintf(
						/* translators: %s: field label */
						__('%s must be a valid number.', $this->config['text_domain']),
						$field_label
					);
					return false;
				}

				if (isset($field['min']) && $value < $field['min']) {
					$error = sprintf(
						/* translators: 1: field title, 2: minimum value */
						__('%1$s must be at least %2$s.', $this->config['text_domain']),
						$field_label,
						$field['min']
					);
					return false;
				}

				if (isset($field['max']) && $value > $field['max']) {
					$error = sprintf(
						/* translators: 1: field title, 2: maximum value */
						__('%1$s must be no more than %2$s.', $this->config['text_domain']),
						$field_label,
						$field['max']
					);
					return false;
				}
				break;

			case 'text':
			case 'textarea':
				if (isset($field['maxlength']) && mb_strlen($value) > $field['maxlength']) {
					$error = sprintf(
						/* translators: 1: field title, 2: maximum length */
						__('%1$s must be no more than %2$s characters.', $this->config['text_domain']),
						$field_label,
						$field['maxlength']
					);
					return false;
				}

				if (isset($field['pattern']) && !preg_match($field['pattern'], $value)) {
					$error = sprintf(
						/* translators: %s: field title */
						__('%s has an invalid format.', $this->config['text_domain']),
						$field_label
					);
					return false;
				}
				break;

			case 'color':
				// Allow empty, 3/6/8-digit hex, and rgb/rgba values.
				if ($value === '' || $value === null) {
					break;
				}

				$raw = (string) $value;
				if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $raw)) {
					break;
				}

				if (preg_match('/^rgba?\([^\)]+\)$/i', $raw)) {
					break;
				}

				$error = sprintf(
					/* translators: %s: field title */
					__('%s must be a valid hex color.', $this->config['text_domain']),
					$field_label
				);
				return false;
				break;
		}

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
	private function get_input_name(string $field_id): string
	{
		return $this->config['form_field_prefix'] . '[' . $field_id . ']';
	}

	/**
	 * Utility – produce field input ID.
	 */
	private function get_input_id(string $field_id): string
	{
		return $this->config['form_field_prefix'] . '_' . $field_id;
	}

	/**
	 * Determine current tab slug from request.
	 *
	 * @param array $tabs Tabs list.
	 * @return string Tab slug, or empty string if no tabs exist.
	 */
	private function get_current_tab_slug(array $tabs): string
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
	private function get_tab_url(string $slug): string
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
	 * Normalize tab structure.
	 *
	 * @param string $slug Tab slug.
	 * @param array  $tab  Tab args.
	 */
	private function normalize_tab(string $slug, array $tab): array
	{
		$tab = wp_parse_args(
			$tab,
			[
				'label' => ucwords(str_replace('_', ' ', $slug)),
				'priority' => 10,
				'sections' => [],
			]
		);

		if (!empty($tab['sections'])) {
			foreach ($tab['sections'] as $section_id => $section) {
				$section_id = is_string($section_id) ? $section_id : ($section['id'] ?? uniqid('section_', true));
				$tab['sections'][$section_id] = $this->normalize_section($section_id, $section);
			}
		} else {
			$tab['sections'] = [];
		}

		return $tab;
	}

	/**
	 * Normalize section structure.
	 *
	 * @param string $section_id Section ID.
	 * @param array  $section    Section definition.
	 */
	private function normalize_section(string $section_id, array $section): array
	{
		$section = wp_parse_args(
			$section,
			[
				'id' => $section_id,
				'title' => ucwords(str_replace('_', ' ', $section_id)),
				'description' => '',
				'priority' => 10,
				'accordion' => false,
				'fields' => [],
			]
		);

		if (!empty($section['fields'])) {
			foreach ($section['fields'] as $field_id => $field) {
				$field = is_array($field) ? $field : [];
				if (!isset($field['id'])) {
					$field['id'] = is_string($field_id) ? $field_id : uniqid('field_', true);
				}
				$section['fields'][$field['id']] = $this->normalize_field($field);
			}
		} else {
			$section['fields'] = [];
		}

		return $section;
	}

	/**
	 * Normalize field structure.
	 *
	 * @param array $field Field configuration.
	 */
	private function normalize_field(array $field): array
	{
		$field = wp_parse_args(
			$field,
			[
				'id' => '',
				'label' => '',
				'type' => 'text',
				'default' => '',
				'description' => '',
				'priority' => 10,
				'conditions' => [],
				'options' => [],
				'fields' => [],
			]
		);

		if (!empty($field['conditions']) && is_array($field['conditions'])) {
			$field['conditions'] = array_values(
				array_map(
					static function ($condition) {
						$condition = wp_parse_args(
							(array) $condition,
							[
								'field' => '',
								'operator' => 'equals',
								'value' => true,
							]
						);
						return $condition;
					},
					$field['conditions']
				)
			);
		} else {
			$field['conditions'] = [];
		}

		// Normalize nested subfields (if any)
		if (!empty($field['fields']) && is_array($field['fields'])) {
			$normalized_children = [];
			foreach ($field['fields'] as $child_id => $child) {
				$child = is_array($child) ? $child : [];
				if (!isset($child['id'])) {
					$child['id'] = is_string($child_id) ? $child_id : uniqid('field_', true);
				}
				$normalized_children[$child['id']] = $this->normalize_field($child);
			}
			// Sort children by priority to ensure consistent ordering
			uasort(
				$normalized_children,
				static function (array $a, array $b): int {
					return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
				}
			);
			$field['fields'] = $normalized_children;
		} else {
			$field['fields'] = [];
		}

		return $field;
	}

	/**
	 * Filter tabs based on show_if conditions.
	 *
	 * @param array $tabs    Tabs array.
	 * @param array $options Current options values.
	 * @return array Tabs with visibility flags.
	 */
	private function filter_tabs_by_conditions(array $tabs, array $options): array
	{
		foreach ($tabs as $slug => &$tab) {
			// Check if tab has show_if condition
			if (!empty($tab['show_if']) && is_array($tab['show_if'])) {
				$show_if = $tab['show_if'];

				// Support both single condition and array of conditions
				// Single: ['field' => 'enable_svi', 'value' => true]
				// Multiple: [['field' => 'enable_svi', 'value' => true], ['field' => 'gallery_type', 'value' => 'static']]
				$is_multi = isset($show_if[0]) && is_array($show_if[0]);
				$conditions = $is_multi ? $show_if : [$show_if];

				// Check all conditions (AND logic - all must be true)
				$all_conditions_met = true;
				foreach ($conditions as $condition) {
					$field_id = $condition['field'] ?? '';
					$expected_value = $condition['value'] ?? '';
					$current_value = $options[$field_id] ?? '';

					// Normalize boolean comparisons
					// Toggle/checkbox fields save as "1" or "" (empty string)
					if (is_bool($expected_value)) {
						$current_value = !empty($current_value);
					}

					// Check if condition is met
					if ($current_value !== $expected_value) {
						$all_conditions_met = false;
						break;
					}
				}

				// Mark tab as hidden if any condition is not met
				$tab['_hidden'] = !$all_conditions_met;
			}
		}

		return $tabs;
	}
}