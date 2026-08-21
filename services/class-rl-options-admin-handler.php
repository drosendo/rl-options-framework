<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Options Admin Handler Service.
 *
 * Encapsulates admin request handlers:
 * - Form save submissions (POST with nonce verification)
 * - AJAX settings saves (async JSON responses)
 * - AJAX field options resolution (async option providers)
 * - AJAX field validation (inline validation checks)
 *
 * @package RL_Options_Framework
 * @since 2.1.0
 */

/**
 * Admin handler service for RL Options Framework.
 */
class RL_Options_Admin_Handler {

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
	 * Handle form save submissions (POST).
	 *
	 * Validates all fields, sanitizes, saves to options,
	 * fires hooks, and redirects with status.
	 */
	public function handle_save(): void {
		RL_Logger::debug( '========== SAVE HANDLER START ==========' );

		if ( ! current_user_can( $this->framework->get_config( 'capability' ) ) ) {
			RL_Logger::error( 'User does not have required capability.', [ 'capability' => $this->framework->get_config( 'capability' ) ] );
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'smart-variations-images-premium' ) );
		}

		$nonce_action = $this->framework->get_config( 'page_slug' ) . '_save_options';
		$nonce_field  = $this->framework->get_config( 'form_field_prefix' ) . '_nonce';
		check_admin_referer( $nonce_action, $nonce_field );
		RL_Logger::debug( 'Nonce verified successfully.' );

		$fields_map = $this->framework->get_fields_index();
		RL_Logger::debug( 'Total fields registered: ' . count( $fields_map ) );

		$input = isset( $_POST[ $this->framework->get_config( 'form_field_prefix' ) ] ) ? wp_unslash( $_POST[ $this->framework->get_config( 'form_field_prefix' ) ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		RL_Logger::debug( 'Form field prefix: ' . $this->framework->get_config( 'form_field_prefix' ) );

		if ( ! is_array( $input ) ) {
			RL_Logger::warn( 'Input is not an array; defaulting to empty array.' );
			$input = [];
		}
		$this->framework->set_validation_context( $input );

		RL_Logger::debug( 'Submitted field count: ' . count( $input ) );

		$saved = get_option( $this->framework->get_config( 'option_name' ), [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		RL_Logger::debug( 'Existing saved fields: ' . count( $saved ) );

		$validation_errors = [];
		$field_errors      = [];

		foreach ( $fields_map as $field_id => $field ) {
			$value = $input[ $field_id ] ?? null;
			$value = $this->framework->prepare_value_for_validation( $field, $value );

			if ( in_array( $field_id, [ 'slider_position', 'gallery_type' ], true ) ) {
				RL_Logger::debug(
					sprintf(
						'Processing %s: value="%s", type=%s',
						$field_id,
						$value,
						$field['type'] ?? 'unknown'
					)
				);
			}

			$error = '';

			// Validate field.
			if ( ! $this->framework->validate_field_value( $field, $value, $error ) ) {
				$validation_errors[ $field_id ] = $error;
				$field_errors[ $field_id ]      = [
					'field_id'    => $field_id,
					'field_label' => $this->framework->get_field_label( $field ),
					'tab_id'      => $field['__tab_id'] ?? '',
					'section_id'  => $field['__section_id'] ?? '',
					'message'     => $error,
				];
				RL_Logger::warn( 'Validation failed for field.', [ 'field_id' => $field_id, 'error' => $error ] );
				continue;
			}

			// Sanitize and save.
			$sanitized_value       = $this->framework->sanitize_field_value( $field, $value );
			$saved[ $field_id ]    = $sanitized_value;

			if ( in_array( $field_id, [ 'slider_position', 'gallery_type' ], true ) ) {
				RL_Logger::debug(
					sprintf(
						'Sanitized %s: "%s" -> "%s"',
						$field_id,
						$value,
						$sanitized_value
					)
				);
			}
		}

		// If validation errors, redirect with error message.
		if ( ! empty( $validation_errors ) ) {
			$error_message = implode( ' ', array_values( $validation_errors ) );
			RL_Logger::warn( 'Validation errors found.', [ 'message' => $error_message ] );
			$message_param = $this->framework->get_config( 'form_field_prefix' ) . '_message';
			$error_param   = $this->framework->get_config( 'form_field_prefix' ) . '_error';
			wp_safe_redirect(
				add_query_arg(
					[
						'page'        => $this->framework->get_config( 'page_slug' ),
						$message_param => 'error',
						$error_param   => rawurlencode( $error_message ),
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$update_result = update_option( $this->framework->get_config( 'option_name' ), $saved );
		RL_Logger::info( 'update_option result: ' . ( $update_result ? 'SUCCESS' : 'FAILED (or unchanged)' ) );
		RL_Logger::info( 'Total fields saved: ' . count( $saved ) );

		// Fire generic post-save hooks for host integrations.
		do_action( $this->framework->get_config( 'option_name' ) . '_settings_saved', $saved ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		do_action( 'rl_options_framework_settings_saved', $saved, $this->framework->get_config(), $this->framework ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		RL_Logger::debug( '========== SAVE HANDLER END ==========' );

		$message_param = $this->framework->get_config( 'form_field_prefix' ) . '_message';
		wp_safe_redirect(
			add_query_arg(
				[
					'page'         => $this->framework->get_config( 'page_slug' ),
					$message_param => 'saved',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle AJAX options save submissions.
	 *
	 * Validates all fields, sanitizes, saves to options,
	 * fires hooks, and returns JSON response.
	 */
	public function handle_ajax_save(): void {
		RL_Logger::debug( '========== AJAX SAVE HANDLER CALLED ==========' );
		RL_Logger::debug( 'Expected nonce action: ' . $this->framework->get_config( 'ajax_action' ) . '_nonce' );

		// Verify nonce (accept AJAX nonce and fallback to form nonce).
		$ajax_nonce_action = $this->framework->get_config( 'ajax_action' ) . '_nonce';
		$form_nonce_action = $this->framework->get_config( 'page_slug' ) . '_save_options';
		$form_nonce_field  = $this->framework->get_config( 'form_field_prefix' ) . '_nonce';

		$ajax_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$form_nonce = isset( $_POST[ $form_nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $form_nonce_field ] ) ) : '';

		$ajax_nonce_valid = ! empty( $ajax_nonce ) && wp_verify_nonce( $ajax_nonce, $ajax_nonce_action );
		$form_nonce_valid = ! empty( $form_nonce ) && wp_verify_nonce( $form_nonce, $form_nonce_action );

		RL_Logger::debug( 'AJAX nonce valid: ' . ( $ajax_nonce_valid ? 'yes' : 'no' ) );
		RL_Logger::debug( 'Form nonce valid: ' . ( $form_nonce_valid ? 'yes' : 'no' ) );

		if ( ! $ajax_nonce_valid && ! $form_nonce_valid ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'smart-variations-images-premium' ),
				],
				403
			);
		}

		// Check permissions.
		if ( ! current_user_can( $this->framework->get_config( 'capability' ) ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You are not allowed to manage these settings.', 'smart-variations-images-premium' ),
				]
			);
		}

		RL_Logger::debug( '========== AJAX SAVE START ==========' );

		$fields_map = $this->framework->get_fields_index();
		RL_Logger::debug( 'Total fields registered: ' . count( $fields_map ) );

		// Check for import payload first
		$import_input_name = $this->framework->get_config( 'form_field_prefix' ) . '_import_json';
		if ( ! empty( $_POST[ $import_input_name ] ) ) {
			$import_json = wp_unslash( $_POST[ $import_input_name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			
			$import_result = $this->framework->get_storage_service()->import_settings( $import_json );
			
			if ( is_wp_error( $import_result ) ) {
				wp_send_json_error(
					[
						'message' => $import_result->get_error_message(),
					]
				);
			}

			// Fire generic post-save hooks for host integrations to react to the import
			$saved = get_option( $this->framework->get_config( 'option_name' ), [] );
			do_action( $this->framework->get_config( 'option_name' ) . '_settings_saved', $saved ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			do_action( 'rl_options_framework_settings_saved', $saved, $this->framework->get_config(), $this->framework ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			wp_send_json_success(
				[
					'message' => __( 'Settings imported successfully. The page will reload.', 'smart-variations-images-premium' ),
					'imported' => true,
				]
			);
		}

		// Check for reset payload
		$reset_input_name = $this->framework->get_config( 'form_field_prefix' ) . '_reset_settings';
		if ( isset( $_POST[ $reset_input_name ] ) && $_POST[ $reset_input_name ] === '1' ) {
			$this->framework->reset_to_defaults();

			// Fire generic post-reset hooks for host integrations to react to the reset
			do_action( $this->framework->get_config( 'option_name' ) . '_settings_reset' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			do_action( 'rl_options_framework_settings_reset', $this->framework->get_config(), $this->framework ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			wp_send_json_success(
				[
					'message' => __( 'Settings reset successfully. The page will reload.', 'smart-variations-images-premium' ),
					'imported' => true, // We can reuse the `imported` flag to trigger the page reload in JS
				]
			);
		}

		$input = isset( $_POST[ $this->framework->get_config( 'form_field_prefix' ) ] ) ? wp_unslash( $_POST[ $this->framework->get_config( 'form_field_prefix' ) ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		RL_Logger::debug( 'Submitted field count: ' . count( $input ) );

		if ( ! is_array( $input ) ) {
			RL_Logger::warn( 'Input is not an array.' );
			$input = [];
		}
		$this->framework->set_validation_context( $input );

		$saved = get_option( $this->framework->get_config( 'option_name' ), [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		$validation_errors = [];
		$field_errors      = [];

		foreach ( $fields_map as $field_id => $field ) {
			$value = $input[ $field_id ] ?? null;
			$value = $this->framework->prepare_value_for_validation( $field, $value );

			// Debug specific fields.
			if ( in_array( $field_id, [ 'slider_position', 'gallery_type' ], true ) ) {
				RL_Logger::debug(
					sprintf(
						'AJAX Processing %s: value="%s", type=%s',
						$field_id,
						$value,
						$field['type'] ?? 'unknown'
					)
				);
			}

			$error = '';

			// Validate field.
			if ( ! $this->framework->validate_field_value( $field, $value, $error ) ) {
				$validation_errors[ $field_id ] = $error;
				$field_errors[ $field_id ]      = [
					'field_id'    => $field_id,
					'field_label' => $this->framework->get_field_label( $field ),
					'tab_id'      => $field['__tab_id'] ?? '',
					'section_id'  => $field['__section_id'] ?? '',
					'message'     => $error,
				];
				RL_Logger::warn( 'AJAX validation failed for field.', [ 'field_id' => $field_id, 'error' => $error ] );
				continue;
			}

			// Sanitize and save.
			$sanitized_value    = $this->framework->sanitize_field_value( $field, $value );
			$saved[ $field_id ] = $sanitized_value;

			if ( in_array( $field_id, [ 'slider_position', 'gallery_type' ], true ) ) {
				RL_Logger::debug(
					sprintf(
						'AJAX Sanitized %s: "%s" -> "%s"',
						$field_id,
						$value,
						$sanitized_value
					)
				);
			}
		}

		// If validation errors, return error.
		if ( ! empty( $validation_errors ) ) {
			RL_Logger::warn( 'AJAX validation errors.', $validation_errors );
			wp_send_json_error(
				[
					'message'       => implode( ' ', array_values( $validation_errors ) ),
					'errors'        => $validation_errors,
					'field_errors'  => $field_errors,
				]
			);
		}

		$update_result = update_option( $this->framework->get_config( 'option_name' ), $saved );
		RL_Logger::info( 'AJAX update_option result: ' . ( $update_result ? 'SUCCESS' : 'FAILED (or unchanged)' ) );
		RL_Logger::info( 'Total fields saved: ' . count( $saved ) );

		// Fire generic post-save hooks for host integrations.
		do_action( $this->framework->get_config( 'option_name' ) . '_settings_saved', $saved ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		do_action( 'rl_options_framework_settings_saved', $saved, $this->framework->get_config(), $this->framework ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		RL_Logger::debug( '========== AJAX SAVE END ==========' );

		wp_send_json_success(
			[
				'message' => __( 'Settings saved successfully.', 'smart-variations-images-premium' ),
				'saved'   => count( $saved ),
			]
		);
	}

	/**
	 * Resolve async options for a single field.
	 *
	 * Handles dynamic option loading for fields
	 * that provide options via callbacks.
	 */
	public function handle_ajax_field_options(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $this->framework->get_config( 'ajax_action' ) . '_nonce' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Security check failed.', 'smart-variations-images-premium' ) ],
				403
			);
		}

		if ( ! current_user_can( $this->framework->get_config( 'capability' ) ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You are not allowed to perform this action.', 'smart-variations-images-premium' ) ],
				403
			);
		}

		$field_id       = isset( $_POST['field_id'] ) ? sanitize_key( wp_unslash( $_POST['field_id'] ) ) : '';
		$current_state  = isset( $_POST['current_state'] ) ? wp_unslash( $_POST['current_state'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $current_state ) ) {
			$current_state = [];
		}

		$fields_map = $this->framework->get_fields_index();
		if ( $field_id === '' || empty( $fields_map[ $field_id ] ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Unknown field.', 'smart-variations-images-premium' ) ],
				400
			);
		}

		$field   = $fields_map[ $field_id ];
		$options = $this->framework->resolve_field_provider_options( $field, $current_state, true );

		wp_send_json_success(
			[
				'field_id' => $field_id,
				'options'  => $options,
			]
		);
	}

	/**
	 * Validate one field on change (inline validation endpoint).
	 *
	 * Provides real-time field validation feedback
	 * without saving the entire form.
	 */
	public function handle_ajax_field_validate(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $this->framework->get_config( 'ajax_action' ) . '_nonce' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Security check failed.', 'smart-variations-images-premium' ) ],
				403
			);
		}

		if ( ! current_user_can( $this->framework->get_config( 'capability' ) ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You are not allowed to perform this action.', 'smart-variations-images-premium' ) ],
				403
			);
		}

		$field_id = isset( $_POST['field_id'] ) ? sanitize_key( wp_unslash( $_POST['field_id'] ) ) : '';
		$input    = isset( $_POST[ $this->framework->get_config( 'form_field_prefix' ) ] ) ? wp_unslash( $_POST[ $this->framework->get_config( 'form_field_prefix' ) ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $input ) ) {
			$input = [];
		}

		$fields_map = $this->framework->get_fields_index();
		if ( $field_id === '' || empty( $fields_map[ $field_id ] ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Unknown field.', 'smart-variations-images-premium' ) ],
				400
			);
		}

		$field = $fields_map[ $field_id ];
		$value = $input[ $field_id ] ?? null;
		$value = $this->framework->prepare_value_for_validation( $field, $value );
		$this->framework->set_validation_context( $input );

		$error    = '';
		$is_valid = $this->framework->validate_field_value( $field, $value, $error );

		if ( $is_valid ) {
			wp_send_json_success(
				[
					'field_id' => $field_id,
					'valid'    => true,
				]
			);
		}

		wp_send_json_error(
			[
				'field_id' => $field_id,
				'valid'    => false,
				'message'  => $error,
			]
		);
	}
}
