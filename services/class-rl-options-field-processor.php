<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Options Field Processor Service.
 *
 * Encapsulates field-level processing:
 * - Validation (schema checks, value validation, required rules)
 * - Sanitization
 * - Value preparation for validation
 * - Field labeling and normalization
 * - Dynamic option resolution (providers, geo fields)
 * - Dependency rule matching
 *
 * @package RL_Options_Framework
 * @since 2.1.0
 */

/**
 * Field processor service for RL Options Framework.
 */
class RL_Options_Field_Processor {

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
	 * Validate expected field schema and log warnings for invalid contracts.
	 */
	public function validate_field_schema( array $field ): void {
		$type = (string) ( $field['type'] ?? '' );
		$id   = (string) ( $field['id'] ?? 'unknown' );

		if ( 'image_select' === $type ) {
			$options = $field['options'] ?? [];
			if ( ! is_array( $options ) || empty( $options ) ) {
				RL_Logger::warn( 'image_select field is missing options.', [ 'field_id' => $id ] );
				return;
			}

			foreach ( $options as $key => $option ) {
				if ( ! is_array( $option ) || empty( $option['src'] ) || empty( $option['label'] ) ) {
					RL_Logger::warn( 'image_select option should define src and label.', [
						'field_id'   => $id,
						'option_key' => $key,
					] );
				}
			}
		}

		if ( 'image' === $type ) {
			if ( ! empty( $field['options'] ) ) {
				RL_Logger::warn( 'image field should not define options; use image_select instead.', [ 'field_id' => $id ] );
			}
		}
	}

	/**
	 * Get field label from definition.
	 */
	public function get_field_label( array $field ): string {
		$label = $field['label'] ?? $field['title'] ?? $field['id'] ?? 'Field';
		$label = wp_strip_all_tags( (string) $label );
		$label = $this->normalize_field_label( $label );
		return trim( $label ) !== '' ? $label : ( (string) ( $field['id'] ?? 'Field' ) );
	}

	/**
	 * Remove carets/arrows from label prefixes (nested field indicators).
	 */
	public function normalize_field_label( string $label ): string {
		$normalized = preg_replace( '/^\s*(?:[↳➜→\-–—]+\s*)+\s*/u', '', $label );
		if ( null === $normalized ) {
			$normalized = $label;
		}

		return trim( $normalized );
	}

	/**
	 * Sanitize raw value based on field definition.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public function sanitize_field_value( array $field, $value ) {
		$config = $this->framework->config;

		// Allow custom sanitize callback to take precedence
		if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			return call_user_func( $field['sanitize_callback'], $value, $field );
		}

		// Delegate to field type's processing interface if available
		$field_type = (string) ( $field['type'] ?? 'text' );
		$field_registry = $this->framework->field_registry();
		$renderer       = $field_registry ? $field_registry->get( $field_type ) : null;
		if ( $renderer instanceof RL_Field_Processing_Interface ) {
			return $renderer->sanitize(
				$field,
				$value,
				[
					'text_domain'                  => (string) $config['text_domain'],
					'validation_context'           => $this->framework->get_validation_context(),
					'allowed_option_keys_callback' => [ $this, 'get_allowed_option_keys' ],
					'geo_options_callback'         => [ $this, 'get_geo_field_options' ],
				]
			);
		}

		// Fallback for fields without processing interface
		return isset( $value ) ? sanitize_text_field( $value ) : '';
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
	public function prepare_value_for_validation( array $field, $value ) {
		$config     = $this->framework->config;
		$field_type = $field['type'] ?? '';

		$field_registry = $this->framework->field_registry();
		$renderer       = $field_registry ? $field_registry->get( (string) $field_type ) : null;
		if ( $renderer instanceof RL_Field_Processing_Interface ) {
			return $renderer->prepare_for_validation(
				$field,
				$value,
				[
					'text_domain'            => (string) $config['text_domain'],
					'validation_context'     => $this->framework->get_validation_context(),
				]
			);
		}

		if ( 'number' !== $field_type ) {
			return $value;
		}

		$fallback = isset( $field['default'] ) && is_numeric( $field['default'] ) ? (float) $field['default'] : 0.0;
		if ( !  isset( $value ) || $value === '' ) {
			return $fallback;
		}

		if ( isset( $field['min'] ) && is_numeric( $field['min'] ) && (float) $fallback < (float) $field['min'] ) {
			$fallback = $field['min'];
		}

		if ( isset( $field['max'] ) && is_numeric( $field['max'] ) && (float) $fallback > (float) $field['max'] ) {
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
	public function validate_field_value( array $field, $value, string &$error = '' ): bool {
		$config      = $this->framework->config;
		$field_label = $this->get_field_label( $field );

		// Custom validation callback takes precedence
		if ( isset( $field['validate_callback'] ) && is_callable( $field['validate_callback'] ) ) {
			$result = call_user_func( $field['validate_callback'], $value, $field );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
				return false;
			}
			if ( false === $result ) {
				$error = sprintf(
					/* translators: %s: field title */
					__( '%s is invalid.', 'smart-variations-images-premium' ),
					$field_label
				);
				return false;
			}
			return true;
		}

		// Required field validation
		if ( ! empty( $field['required'] ) && ( $value === null || $value === '' ) ) {
			$error = sprintf(
				/* translators: %s: field title */
				__( '%s is required.', 'smart-variations-images-premium' ),
				$field_label
			);
			return false;
		}

		$validation_context = $this->framework->get_validation_context();
		if ( ! empty( $field['required_if'] ) && is_array( $field['required_if'] ) && $this->is_required_by_rules( $field['required_if'], $validation_context ) && ( $value === null || $value === '' ) ) {
			$error = sprintf(
				/* translators: %s: field title */
				__( '%s is required for the selected dependency values.', 'smart-variations-images-premium' ),
				$field_label
			);
			return false;
		}

		// Delegate to field type's processing interface if available
		$field_type     = (string) ( $field['type'] ?? 'text' );
		$field_registry = $this->framework->field_registry();
		$renderer       = $field_registry ? $field_registry->get( $field_type ) : null;
		if ( $renderer instanceof RL_Field_Processing_Interface ) {
			return $renderer->validate(
				$field,
				$value,
				$error,
				[
					'text_domain'                  => (string) $config['text_domain'],
					'field_label'                  => $field_label,
					'validation_context'           => $validation_context,
					'required_checked'             => true,
					'allowed_option_keys_callback' => [ $this, 'get_allowed_option_keys' ],
					'geo_options_callback'         => [ $this, 'get_geo_field_options' ],
				]
			);
		}

		// Fallback validation for fields without processing interface
		return true;
	}

	/**
	 * Resolve allowed option keys for static and provider-backed fields.
	 *
	 * @return string[]
	 */
	public function get_allowed_option_keys( array $field, array $state = [] ): array {
		$allowed            = array_map( 'strval', array_keys( $field['options'] ?? [] ) );
		$provider_options   = $this->resolve_field_provider_options( $field, $state, false );

		foreach ( $provider_options as $item ) {
			if ( is_array( $item ) && isset( $item['value'] ) ) {
				$allowed[] = (string) $item['value'];
			}
		}

		$allowed = array_values( array_unique( array_filter( $allowed, static function ( $v ) {
			return $v !== '';
		} ) ) );

		return $allowed;
	}

	/**
	 * Evaluate required_if rule set.
	 */
	public function is_required_by_rules( array $rules, array $state ): bool {
		if ( isset( $rules['field'] ) ) {
			$rules = [ $rules ];
		}

		if ( empty( $rules ) ) {
			return false;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$field = isset( $rule['field'] ) ? (string) $rule['field'] : '';
			if ( $field === '' ) {
				continue;
			}

			$current   = $state[ $field ] ?? null;
			$operator  = strtolower( (string) ( $rule['operator'] ?? 'truthy' ) );
			$expected  = $rule['value'] ?? null;

			if ( ! $this->match_dependency_rule( $current, $operator, $expected ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Compare one dependency condition.
	 */
	public function match_dependency_rule( $current, string $operator, $expected ): bool {
		switch ( $operator ) {
			case 'equals':
			case '==':
				return $current == $expected;
			case 'not_equals':
			case '!=':
				return $current != $expected;
			case 'in':
				return is_array( $expected ) ? in_array( $current, $expected, true ) : $current == $expected;
			case 'not_in':
				return is_array( $expected ) ? ! in_array( $current, $expected, true ) : $current != $expected;
			case 'empty':
				return $current === null || $current === '' || $current === [];
			case 'not_empty':
				return ! ( $current === null || $current === '' || $current === [] );
			case 'falsy':
				return ! $current;
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
	public function resolve_field_provider_options( array $field, array $state = [], bool $fallback_static = true ): array {
		$provider = $field['options_provider'] ?? null;
		if ( ! is_array( $provider ) ) {
			return $fallback_static ? $this->normalize_options_for_transport( $field['options'] ?? [] ) : [];
		}

		$endpoint = strtolower( (string) ( $provider['endpoint'] ?? '' ) );
		if ( $endpoint === '' ) {
			$endpoint = strtolower( (string) ( $provider['action'] ?? '' ) );
		}

		$options     = [];
		$country     = strtoupper( (string) $this->resolve_provider_param( 'country', $provider, $state ) );
		$subdivision = (string) $this->resolve_provider_param( 'subdivision', $provider, $state );

		$rest_api = $this->framework->rest_api();
		if ( $rest_api ) {
			switch ( $endpoint ) {
				case 'countries':
					$countries = $rest_api->get_country_reference_data();
					foreach ( $countries as $code => $item ) {
						$options[] = [ 'value' => (string) $code, 'label' => (string) ( $item['name'] ?? $code ) ];
					}
					break;

				case 'subdivisions':
				case 'country_subdivisions':
					$options = $rest_api->get_country_subdivisions_data( $country );
					break;

				case 'municipalities':
				case 'country_municipalities':
					$options = $rest_api->get_country_municipalities_data( $country, $subdivision );
					break;
			}
		}

		$options = apply_filters( 'rl_options_framework_resolved_provider_options', $options, $provider, $field, $state, $this->framework );
		if ( ! is_array( $options ) || empty( $options ) ) {
			$options = $fallback_static ? $this->normalize_options_for_transport( $field['options'] ?? [] ) : [];
		}

		do_action( 'rl_options_framework_field_dependency_resolved', (string) ( $field['id'] ?? '' ), $provider, $state, $options, $this->framework );

		return $this->normalize_options_for_transport( $options, $provider['mapping'] ?? [] );
	}

	/**
	 * Resolve provider parameter from explicit param mapping or state.
	 */
	public function resolve_provider_param( string $param, array $provider, array $state ): string {
		$field_ref = $provider['params'][ $param ] ?? $provider[ $param ] ?? $param;
		if ( ! is_string( $field_ref ) || $field_ref === '' ) {
			return '';
		}

		$value = $state[ $field_ref ] ?? ( $state[ $param ] ?? '' );
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Build options map for geo field types.
	 *
	 * @return array<string,string>
	 */
	public function get_geo_field_options( array $field, string $type, array $state = [] ): array {
		$out  = [];
		$type = strtolower( $type );

		$rest_api = $this->framework->rest_api();
		if ( ! $rest_api ) {
			return $out;
		}

		if ( $type === 'country' ) {
			foreach ( $rest_api->get_country_reference_data() as $code => $item ) {
				$out[ (string) $code ] = (string) ( $item['name'] ?? $code );
			}
			return $out;
		}

		$country = $this->resolve_geo_country_code( $field, $state );
		if ( $country === '' ) {
			return $out;
		}

		if ( $type === 'state' ) {
			foreach ( $rest_api->get_country_subdivisions_data( $country ) as $item ) {
				if ( is_array( $item ) && isset( $item['value'] ) ) {
					$out[ (string) $item['value'] ] = (string) ( $item['label'] ?? $item['value'] );
				}
			}
			return $out;
		}

		if ( $type === 'city' ) {
			$subdivision = '';
			if ( ! empty( $field['subdivision'] ) ) {
				$subdivision = sanitize_key( (string) $field['subdivision'] );
			} elseif ( ! empty( $field['subdivision_field'] ) && isset( $state[ (string) $field['subdivision_field'] ] ) ) {
				$subdivision = sanitize_key( (string) $state[ (string) $field['subdivision_field'] ] );
			}

			foreach ( $rest_api->get_country_municipalities_data( $country, $subdivision ) as $item ) {
				if ( is_array( $item ) && isset( $item['value'] ) ) {
					$out[ (string) $item['value'] ] = (string) ( $item['label'] ?? $item['value'] );
				}
			}
		}

		return $out;
	}

	/**
	 * Resolve country code from fixed field config or linked country field.
	 */
	public function resolve_geo_country_code( array $field, array $state = [] ): string {
		$country = '';
		if ( ! empty( $field['country'] ) ) {
			$country = strtoupper( sanitize_key( (string) $field['country'] ) );
		}

		if ( $country === '' && ! empty( $field['country_field'] ) ) {
			$key = (string) $field['country_field'];
			if ( isset( $state[ $key ] ) ) {
				$country = strtoupper( sanitize_key( (string) $state[ $key ] ) );
			}
		}

		return $country;
	}

	/**
	 * Normalize options (dict or list) to [{value,label},...] format.
	 *
	 * @param array $options    Raw options (dict or list of dicts).
	 * @param array $mapping    Optional mapping {obj_key => label_key, ...}.
	 * @return array<int,array{value:string,label:string}>
	 */
	public function normalize_options_for_transport( array $options, array $mapping = [] ): array {
		$out = [];

		$value_key = $mapping['value'] ?? 'value';
		$label_key = $mapping['label'] ?? 'label';

		$is_assoc = array_keys( $options ) !== range( 0, count( $options ) - 1 );
		if ( $is_assoc ) {
			foreach ( $options as $value => $label ) {
				if ( is_array( $label ) ) {
					$v = $label[ $value_key ] ?? $value;
					$l = $label[ $label_key ] ?? ( $label['name'] ?? $v );
				} else {
					$v = $value;
					$l = $label;
				}
				$out[] = [ 'value' => (string) $v, 'label' => (string) $l ];
			}
		} else {
			foreach ( $options as $item ) {
				if ( is_array( $item ) ) {
					$v = $item[ $value_key ] ?? ( $item['value'] ?? '' );
					$l = $item[ $label_key ] ?? ( $item['label'] ?? $v );
					if ( $v === '' ) {
						continue;
					}
					$out[] = [ 'value' => (string) $v, 'label' => (string) $l ];
				}
			}
		}

		return $out;
	}
}
