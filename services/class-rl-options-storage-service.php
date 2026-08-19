<?php
/**
 * RL Options Storage Service.
 *
 * Encapsulates settings persistence helpers:
 * - Backup creation and restore
 * - Export and import
 * - Reset to schema defaults
 *
 * @package RL_Options_Framework
 * @since 2.1.0
 */

/**
 * Storage service for RL Options Framework.
 */
class RL_Options_Storage_Service {

	/**
	 * Framework instance.
	 *
	 * @var RL_Options_Framework
	 */
	private $framework;

	/**
	 * Constructor.
	 *
	 * @param RL_Options_Framework $framework Framework instance.
	 */
	public function __construct( RL_Options_Framework $framework ) {
		$this->framework = $framework;
	}

	/**
	 * Create a backup of current settings.
	 *
	 * @return array|false Backup data or false on failure.
	 */
	public function create_backup() {
		$config   = $this->framework->config;
		$settings = get_option( $config['option_name'], [] );

		if ( empty( $settings ) ) {
			return false;
		}

		$backup = [
			'created_at' => current_time( 'mysql' ),
			'version'    => $config['version'],
			'settings'   => $settings,
		];

		$backup_key = $config['option_name'] . '_backup';

		return update_option( $backup_key, $backup ) ? $backup : false;
	}

	/**
	 * Restore settings from backup.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function restore_backup(): bool {
		$config     = $this->framework->config;
		$backup_key = $config['option_name'] . '_backup';
		$backup     = get_option( $backup_key, false );

		if ( empty( $backup ) || ! isset( $backup['settings'] ) ) {
			return false;
		}

		return update_option( $config['option_name'], $backup['settings'] );
	}

	/**
	 * Export settings as JSON.
	 *
	 * @return string JSON-encoded settings.
	 */
	public function export_settings(): string {
		$config   = $this->framework->config;
		$settings = get_option( $config['option_name'], [] );

		$export = [
			'exported_at' => current_time( 'mysql' ),
			'version'     => $config['version'],
			'settings'    => $settings,
		];

		return wp_json_encode( $export, JSON_PRETTY_PRINT );
	}

	/**
	 * Import settings from JSON.
	 *
	 * @param string $json JSON-encoded settings.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function import_settings( string $json ) {
		$data   = json_decode( $json, true );
		$config = $this->framework->config;

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_json',
				__( 'Invalid JSON format.', $config['text_domain'] )
			);
		}

		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return new WP_Error(
				'invalid_format',
				__( 'Invalid settings format.', $config['text_domain'] )
			);
		}

		$fields_map   = $this->framework->get_fields_index();
		$raw_settings = $data['settings'];
		$input        = [];
		foreach ( $fields_map as $field_id => $field ) {
			if ( array_key_exists( $field_id, $raw_settings ) ) {
				$input[ $field_id ] = $raw_settings[ $field_id ];
			}
		}

		if ( empty( $input ) && ! empty( $raw_settings ) ) {
			return new WP_Error(
				'invalid_settings_payload',
				__( 'Imported settings do not contain any recognized framework fields.', $config['text_domain'] )
			);
		}

		$this->framework->set_validation_context( $input );
		$sanitized         = [];
		$validation_errors = [];

		foreach ( $fields_map as $field_id => $field ) {
			if ( ! array_key_exists( $field_id, $input ) ) {
				continue;
			}

			$value = $this->framework->prepare_value_for_validation( $field, $input[ $field_id ] );
			$error = '';

			if ( ! $this->framework->validate_field_value( $field, $value, $error ) ) {
				$validation_errors[ $field_id ] = $error !== ''
					? $error
					: sprintf(
						__( 'Invalid value for %s.', $config['text_domain'] ),
						$this->framework->get_field_label( $field )
					);
				continue;
			}

			$sanitized[ $field_id ] = $this->framework->sanitize_field_value( $field, $value );
		}

		$this->framework->set_validation_context( [] );

		if ( ! empty( $validation_errors ) ) {
			return new WP_Error(
				'invalid_settings_payload',
				implode( ' ', array_values( $validation_errors ) ),
				[ 'errors' => $validation_errors ]
			);
		}

		$this->create_backup();

		return update_option( $config['option_name'], $sanitized );
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function reset_to_defaults(): bool {
		$config     = $this->framework->config;
		$option_name = $config['option_name'];
		$fields_map = $this->framework->get_fields_index();
		$defaults   = [];

		do_action( "rl_options_before_reset_{$option_name}" );
		do_action( 'rl_options_before_reset', $option_name );

		$this->create_backup();

		foreach ( $fields_map as $field_id => $field ) {
			if ( isset( $field['default'] ) ) {
				$defaults[ $field_id ] = $field['default'];
			}
		}

		$result = update_option( $option_name, $defaults );

		do_action( "rl_options_after_reset_{$option_name}", $defaults );
		do_action( 'rl_options_after_reset', $option_name, $defaults );

		return $result;
	}
}