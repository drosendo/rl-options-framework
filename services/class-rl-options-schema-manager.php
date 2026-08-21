<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
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
				'label'      => ucwords( str_replace( '_', ' ', $slug ) ),
				'priority'   => 10,
				'conditions' => [],
				'sections'   => [],
			]
		);

		$tab['conditions'] = $this->normalize_conditions( $tab );

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
				'conditions'  => [],
				'accordion'   => false,
				'fields'      => [],
			]
		);

		$section['conditions'] = $this->normalize_conditions( $section );

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
	 * Normalize conditions format.
	 *
	 * @param array $item Item containing conditions.
	 * @return array Normalized conditions array.
	 */
	public function normalize_conditions( array $item ): array {
		$conditions = $item['conditions'] ?? [];

		if ( ! empty( $conditions ) && is_array( $conditions ) ) {
			// Support single condition array not wrapped in another array
			if ( isset( $conditions['field'] ) ) {
				$conditions = [ $conditions ];
			}

			return $this->normalize_condition_group( $conditions );
		}

		return [];
	}

	/**
	 * Recursively normalize a condition group.
	 *
	 * @param array $group The condition group.
	 * @return array Normalized group.
	 */
	private function normalize_condition_group( array $group ): array {
		$normalized = [
			'relation' => 'AND',
			'rules'    => [],
		];

		if ( isset( $group['relation'] ) ) {
			$normalized['relation'] = strtoupper( $group['relation'] ) === 'OR' ? 'OR' : 'AND';
			unset( $group['relation'] );
		}

		foreach ( $group as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			// If it's a nested group (has relation, or doesn't have 'field' but has numeric keys)
			if ( isset( $rule['relation'] ) || ( ! isset( $rule['field'] ) && wp_is_numeric_array( $rule ) ) ) {
				$sub_group = $this->normalize_condition_group( $rule );
				if ( ! empty( $sub_group['rules'] ) ) {
					$normalized['rules'][] = $sub_group;
				}
			} elseif ( isset( $rule['field'] ) ) {
				$normalized['rules'][] = wp_parse_args(
					$rule,
					[
						'field'    => '',
						'operator' => 'equals',
						'value'    => true,
					]
				);
			}
		}

		return empty( $normalized['rules'] ) ? [] : $normalized;
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

		$field['conditions'] = $this->normalize_conditions( $field );

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
				'label'    => __( 'Enable Debug Mode', 'smart-variations-images-premium' ),
				'text'     => __( 'Enable debug logging', 'smart-variations-images-premium' ),
				'desc'     => __( 'Enable verbose debug logging for troubleshooting. Disable on production sites.', 'smart-variations-images-premium' ),
				'default'  => false,
				'priority' => 10,
			],
		];

		if ( $show_local_assets ) {
			$debug_fields[ $local_assets_field ] = [
				'id'       => $local_assets_field,
				'type'     => 'toggle',
				'label'    => __( 'Use Local Assets', 'smart-variations-images-premium' ),
				'text'     => __( 'Load options framework libraries locally (GDPR compliant)', 'smart-variations-images-premium' ),
				'desc'     => __( 'Controls how the RL Options Framework loads its own UI libraries (SweetAlert2, Tippy.js, jQuery UI theme). When enabled, these are served from your server. When disabled, they are loaded from public CDNs. This setting does not affect any other plugin assets.', 'smart-variations-images-premium' ),
				'default'  => true,
				'priority' => 20,
			];
		}

		return [
			'support' => [
				'label'    => __( 'Support', 'smart-variations-images-premium' ),
				'priority' => 900,
				'sections' => [
					'debug' => [
						'id'     => 'debug',
						'title'  => __( 'Debug Settings', 'smart-variations-images-premium' ),
						'fields' => $debug_fields,
					],
				],
			],
		];
	}

	/**
	 * Filter tabs based on conditions.
	 *
	 * @param array $tabs    Tabs to filter.
	 * @param array $options Current options values.
	 * @return array Tabs with visibility flags.
	 */
	public function filter_tabs_by_conditions( array $tabs, array $options ): array {
		foreach ( $tabs as $slug => &$tab ) {
			if ( ! empty( $tab['conditions'] ) && is_array( $tab['conditions'] ) ) {
				$tab['_hidden'] = ! $this->evaluate_condition_group( $tab['conditions'], $options );
			}
		}

		return $tabs;
	}

	/**
	 * Recursively evaluate a condition group.
	 *
	 * @param array $group   The condition group (must have 'relation' and 'rules').
	 * @param array $options Current option values.
	 * @return bool Whether the condition group is met.
	 */
	private function evaluate_condition_group( array $group, array $options ): bool {
		$relation = $group['relation'] ?? 'AND';
		$rules    = $group['rules'] ?? [];

		if ( empty( $rules ) ) {
			return true;
		}

		foreach ( $rules as $rule ) {
			$result = false;

			if ( isset( $rule['relation'] ) ) {
				// Nested group
				$result = $this->evaluate_condition_group( $rule, $options );
			} elseif ( isset( $rule['field'] ) ) {
				// Leaf condition
				$field_id       = $rule['field'];
				$operator       = strtolower( $rule['operator'] ?? 'equals' );
				$expected_value = $rule['value'] ?? true;
				$current_value  = $options[ $field_id ] ?? '';

				// Normalize boolean comparisons for toggles
				if ( is_bool( $expected_value ) ) {
					$current_value = ! empty( $current_value );
				}
				
				// Handle truthy / falsy fallback for toggles saved as string '1' or '0'
				if ( $current_value === '1' && is_bool( $expected_value ) ) {
					$current_value = true;
				}
				if ( $current_value === '0' && is_bool( $expected_value ) ) {
					$current_value = false;
				}
				if ( $current_value === '' && is_bool( $expected_value ) ) {
					$current_value = false;
				}

				switch ( $operator ) {
					case 'equals':
					case '==':
						$result = $current_value === $expected_value;
						break;
					case 'not_equals':
					case '!=':
						$result = $current_value !== $expected_value;
						break;
					case 'in':
						$result = is_array( $expected_value ) ? in_array( $current_value, $expected_value, true ) : $current_value === $expected_value;
						break;
					case 'not_in':
						$result = is_array( $expected_value ) ? ! in_array( $current_value, $expected_value, true ) : $current_value !== $expected_value;
						break;
					case '>':
					case 'greater_than':
						$result = is_numeric( $current_value ) && is_numeric( $expected_value ) && (float) $current_value > (float) $expected_value;
						break;
					case '>=':
					case 'greater_than_or_equal':
						$result = is_numeric( $current_value ) && is_numeric( $expected_value ) && (float) $current_value >= (float) $expected_value;
						break;
					case '<':
					case 'less_than':
						$result = is_numeric( $current_value ) && is_numeric( $expected_value ) && (float) $current_value < (float) $expected_value;
						break;
					case '<=':
					case 'less_than_or_equal':
						$result = is_numeric( $current_value ) && is_numeric( $expected_value ) && (float) $current_value <= (float) $expected_value;
						break;
					case 'truthy':
						$result = (bool) $current_value;
						break;
					case 'falsy':
						$result = ! (bool) $current_value;
						break;
					default:
						$result = true;
				}
			}

			if ( $relation === 'AND' && ! $result ) {
				return false;
			}
			if ( $relation === 'OR' && $result ) {
				return true;
			}
		}

		return $relation === 'AND';
	}
}
