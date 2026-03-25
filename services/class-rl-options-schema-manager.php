<?php
/**
 * RL Options Schema Manager Service.
 *
 * Encapsulates schema management:
 * - Tab registration and management
 * - Section registration and organization
 * - Field registration and normalization
 * - Field type expansion and validation
 * - Tab/section/field visibility conditions
 *
 * @package RL_Options_Framework
 * @since 2.1.0
 */

/**
 * Schema manager service for RL Options Framework.
 */
class RL_Options_Schema_Manager {

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
	 * Add a tab to the framework.
	 *
	 * @param string $slug Tab slug (unique identifier).
	 * @param array  $args Tab configuration (label, priority, sections, etc.).
	 */
	public function add_tab( string $slug, array $args ): void {
		$args = $this->normalize_tab( $slug, $args );
		$tabs = $this->framework->get_tabs();
		$tabs[ $slug ] = $args;
		$this->framework->set_tabs( $tabs );
	}

	/**
	 * Append a section to a tab.
	 *
	 * @param string $tab_slug    Tab slug.
	 * @param string $section_id  Section ID.
	 * @param array  $section     Section configuration.
	 */
	public function add_section( string $tab_slug, string $section_id, array $section ): void {
		$tabs = $this->framework->get_tabs();
		if ( ! isset( $tabs[ $tab_slug ] ) ) {
			$tabs[ $tab_slug ] = [
				'label'    => ucwords( str_replace( '_', ' ', $tab_slug ) ),
				'priority' => 10,
				'sections' => [],
			];
		}

		$section                                = $this->normalize_section( $section_id, $section );
		$tabs[ $tab_slug ]['sections'][ $section_id ] = $section;
		$this->framework->set_tabs( $tabs );
	}

	/**
	 * Add a field to a section.
	 *
	 * @param string $tab_slug    Tab slug.
	 * @param string $section_id  Section ID.
	 * @param array  $field       Field configuration.
	 */
	public function add_field( string $tab_slug, string $section_id, array $field ): void {
		$tabs = $this->framework->get_tabs();
		if ( ! isset( $tabs[ $tab_slug ]['sections'][ $section_id ] ) ) {
			$this->add_section( $tab_slug, $section_id, [ 'title' => ucwords( str_replace( '_', ' ', $section_id ) ) ] );
			$tabs = $this->framework->get_tabs();
		}

		$field = $this->normalize_field( $field );
		if ( ! isset( $field['id'] ) || $field['id'] === '' ) {
			$field['id'] = uniqid( 'field_', true );
		}

		$tabs[ $tab_slug ]['sections'][ $section_id ]['fields'][ $field['id'] ] = $field;
		$this->framework->set_tabs( $tabs );
	}

	/**
	 * Add multiple fields to a section.
	 *
	 * @param string $tab_slug    Tab slug.
	 * @param string $section_id  Section ID.
	 * @param array  $fields      Array of field configurations.
	 */
	public function add_fields( string $tab_slug, string $section_id, array $fields ): void {
		foreach ( $fields as $field ) {
			$this->add_field( $tab_slug, $section_id, $field );
		}
	}

	/**
	 * Normalize tab structure.
	 *
	 * @param string $slug Tab slug.
	 * @param array  $tab  Tab definition.
	 */
	private function normalize_tab( string $slug, array $tab ): array {
		$tab = wp_parse_args(
			$tab,
			[
				'label'    => ucwords( str_replace( '_', ' ', $slug ) ),
				'priority' => 10,
				'sections' => [],
			]
		);

		if ( ! empty( $tab['sections'] ) ) {
			foreach ( $tab['sections'] as $section_id => $section ) {
				$section_id            = is_string( $section_id ) ? $section_id : ( $section['id'] ?? uniqid( 'section_', true ) );
				$tab['sections'][ $section_id ] = $this->normalize_section( $section_id, $section );
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
	private function normalize_section( string $section_id, array $section ): array {
		$section = wp_parse_args(
			$section,
			[
				'id'          => $section_id,
				'title'       => ucwords( str_replace( '_', ' ', $section_id ) ),
				'description' => '',
				'priority'    => 10,
				'accordion'   => false,
				'fields'      => [],
			]
		);

		if ( ! empty( $section['fields'] ) ) {
			foreach ( $section['fields'] as $field_id => $field ) {
				$field = is_array( $field ) ? $field : [];
				if ( ! isset( $field['id'] ) ) {
					$field['id'] = is_string( $field_id ) ? $field_id : uniqid( 'field_', true );
				}
				$section['fields'][ $field['id'] ] = $this->normalize_field( $field );
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
	private function normalize_field( array $field ): array {
		if ( ! class_exists( 'RL_Field_Types' ) ) {
			require_once __DIR__ . '/../class-rl-field-types.php';
		}

		// Expand typed aliases (email, phone, postal_code, url, nif) into framework-native field definitions.
		if ( class_exists( 'RL_Field_Types' ) ) {
			$field = RL_Field_Types::expand_typed_field( $field );
		}

		// Provider shorthand: provider => 'countries' (or array) maps to options_provider.
		if ( ! isset( $field['options_provider'] ) && isset( $field['provider'] ) ) {
			if ( is_string( $field['provider'] ) ) {
				$field['options_provider'] = [
					'endpoint' => sanitize_key( $field['provider'] ),
				];
			} elseif ( is_array( $field['provider'] ) ) {
				$field['options_provider'] = $field['provider'];
			}
		}

		// Support sanitize/validate aliases for developer ergonomics.
		if ( ! isset( $field['sanitize_callback'] ) && isset( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
			$field['sanitize_callback'] = $field['sanitize'];
		}
		if ( ! isset( $field['validate_callback'] ) && isset( $field['validate'] ) && is_callable( $field['validate'] ) ) {
			$field['validate_callback'] = $field['validate'];
		}

		// If a text-like field uses non-delimited pattern syntax, normalize to a full anchored regex.
		if ( ! empty( $field['pattern'] ) && is_string( $field['pattern'] ) ) {
			$pattern = trim( $field['pattern'] );
			if ( $pattern !== '' ) {
				$first = substr( $pattern, 0, 1 );
				$last  = substr( $pattern, -1 );
				$is_delimited = in_array( $first, [ '/', '#', '~' ], true ) && $last === $first;
				if ( ! $is_delimited ) {
					$field['pattern'] = '/^' . str_replace( '/', '\\/', $pattern ) . '$/';
				}
			}
		}

		$field = wp_parse_args(
			$field,
			[
				'id'          => '',
				'label'       => '',
				'type'        => 'text',
				'default'     => '',
				'description' => '',
				'priority'    => 10,
				'conditions'  => [],
				'options'     => [],
				'fields'      => [],
			]
		);

		if ( ! empty( $field['conditions'] ) && is_array( $field['conditions'] ) ) {
			$field['conditions'] = array_values(
				array_map(
					static function ( $condition ) {
						$condition = wp_parse_args(
							(array) $condition,
							[
								'field'    => '',
								'operator' => 'equals',
								'value'    => true,
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
		if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$normalized_children = [];
			foreach ( $field['fields'] as $child_id => $child ) {
				$child = is_array( $child ) ? $child : [];
				if ( ! isset( $child['id'] ) ) {
					$child['id'] = is_string( $child_id ) ? $child_id : uniqid( 'field_', true );
				}
				$normalized_children[ $child['id'] ] = $this->normalize_field( $child );
			}
			// Sort children by priority to ensure consistent ordering
			uasort(
				$normalized_children,
				static function ( array $a, array $b ): int {
					return ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 );
				}
			);
			$field['fields'] = $normalized_children;
		} else {
			$field['fields'] = [];
		}

		return $field;
	}

	/**
	 * Get default Support tab with debug settings.
	 *
	 * @return array Default tabs with debug and asset settings.
	 */
	public function get_default_tabs(): array {
		$td = $this->framework->get_config( 'text_domain' );
		$debug_field = $this->framework->get_config( 'debug_field_id' );
		$local_assets_field = $this->framework->get_config( 'local_assets_field_id' );
		$show_local_assets = ! empty( $this->framework->get_config( 'use_local_assets_toggle' ) );

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

		if ( $show_local_assets ) {
			$debug_fields[ $local_assets_field ] = [
				'id'       => $local_assets_field,
				'type'     => 'toggle',
				'label'    => __( 'Use Local Assets', $td ),
				'text'     => __( 'Load options framework libraries locally (GDPR compliant)', $td ),
				'desc'     => __( 'Controls how the RL Options Framework loads its own UI libraries (SweetAlert2, Tippy.js, jQuery UI theme). When enabled, these are served from your server. When disabled, they are loaded from public CDNs. This setting does not affect any other plugin assets.', $td ),
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
	 * Filter tabs based on show_if conditions.
	 *
	 * @param array $tabs    Tabs array.
	 * @param array $options Current options values.
	 * @return array Tabs with visibility flags.
	 */
	public function filter_tabs_by_conditions( array $tabs, array $options ): array {
		foreach ( $tabs as $slug => &$tab ) {
			// Check if tab has show_if condition
			if ( ! empty( $tab['show_if'] ) && is_array( $tab['show_if'] ) ) {
				$show_if = $tab['show_if'];

				// Support both single condition and array of conditions
				// Single: ['field' => 'enable_feature', 'value' => true]
				// Multiple: [['field' => 'enable_feature', 'value' => true], ['field' => 'gallery_type', 'value' => 'static']]
				$is_multi = isset( $show_if[0] ) && is_array( $show_if[0] );
				$conditions = $is_multi ? $show_if : [ $show_if ];

				// Check all conditions (AND logic - all must be true)
				$all_conditions_met = true;
				foreach ( $conditions as $condition ) {
					$field_id = $condition['field'] ?? '';
					$expected_value = $condition['value'] ?? '';
					$current_value = $options[ $field_id ] ?? '';

					// Normalize boolean comparisons
					// Toggle/checkbox fields save as "1" or "" (empty string)
					if ( is_bool( $expected_value ) ) {
						$current_value = ! empty( $current_value );
					}

					// Check if condition is met
					if ( $current_value !== $expected_value ) {
						$all_conditions_met = false;
						break;
					}
				}

				// Mark tab as hidden if any condition is not met
				$tab['_hidden'] = ! $all_conditions_met;
			}
		}

		return $tabs;
	}
}
