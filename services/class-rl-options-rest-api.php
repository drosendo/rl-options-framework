<?php
/**
 * RL Options REST API Service.
 *
 * Encapsulates REST route registration and geo reference data:
 * - REST endpoint registration for countries, subdivisions, municipalities
 * - Country reference data sourcing with transient caching
 * - Subdivision and municipality data with transient caching
 * - Remote REST Countries API integration with configurable sources
 *
 * @package RL_Options_Framework
 * @since 2.1.0
 */

/**
 * REST API and geo data service for RL Options Framework.
 */
class RL_Options_Rest_Api {

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
	 * Set up REST API hooks and handle late-boot scenario.
	 *
	 * Called from framework init(). Registers the rest_api_init action and,
	 * if rest_api_init has already fired (e.g. framework boots late), registers
	 * routes immediately.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		if ( did_action( 'rest_api_init' ) > 0 ) {
			$this->register_routes();
		}
	}

	/**
	 * Register REST routes for country geo data endpoints.
	 *
	 * Routes:
	 * - GET /wp-json/rl-options/v1/countries
	 * - GET /wp-json/rl-options/v1/countries/{code}/subdivisions
	 * - GET /wp-json/rl-options/v1/countries/{code}/municipalities
	 */
	public function register_routes(): void {
		register_rest_route(
			'rl-options/v1',
			'/countries',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ $this, 'rest_get_countries' ],
			]
		);

		register_rest_route(
			'rl-options/v1',
			'/countries/(?P<code>[A-Za-z]{2})/subdivisions',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ $this, 'rest_get_subdivisions' ],
			]
		);

		register_rest_route(
			'rl-options/v1',
			'/countries/(?P<code>[A-Za-z]{2})/municipalities',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ $this, 'rest_get_municipalities' ],
			]
		);
	}

	/**
	 * REST: return normalized countries list.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function rest_get_countries( WP_REST_Request $request ) {
		$data = $this->get_country_reference_data();
		$out  = [];
		foreach ( $data as $code => $item ) {
			$out[] = [
				'code'    => (string) $code,
				'name'    => (string) ( $item['name'] ?? $code ),
				'region'  => (string) ( $item['region'] ?? '' ),
				'capital' => (string) ( $item['capital'] ?? '' ),
			];
		}
		return rest_ensure_response( $out );
	}

	/**
	 * REST: return subdivisions for a country code.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function rest_get_subdivisions( WP_REST_Request $request ) {
		$country = strtoupper( sanitize_key( (string) $request->get_param( 'code' ) ) );
		return rest_ensure_response( $this->get_country_subdivisions_data( $country ) );
	}

	/**
	 * REST: return municipalities for a country/subdivision.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function rest_get_municipalities( WP_REST_Request $request ) {
		$country     = strtoupper( sanitize_key( (string) $request->get_param( 'code' ) ) );
		$subdivision = sanitize_text_field( (string) $request->get_param( 'subdivision' ) );
		return rest_ensure_response( $this->get_country_municipalities_data( $country, $subdivision ) );
	}

	/**
	 * Public helper: return countries as [{code,name,region,capital}].
	 *
	 * @return array<int,array{code:string,name:string,region:string,capital:string}>
	 */
	public function get_country_reference_countries(): array {
		$data = $this->get_country_reference_data();
		$out  = [];
		foreach ( $data as $code => $item ) {
			$out[] = [
				'code'    => (string) $code,
				'name'    => (string) ( $item['name'] ?? $code ),
				'region'  => (string) ( $item['region'] ?? '' ),
				'capital' => (string) ( $item['capital'] ?? '' ),
			];
		}
		return $out;
	}

	/**
	 * Retrieve normalized country reference dataset with transient cache.
	 *
	 * Sources are filterable via `rl_options_framework_country_reference_sources`.
	 * A TTL of 0 means permanent (refresh only when transient is cleared).
	 *
	 * @return array<string,array>
	 */
	public function get_country_reference_data(): array {
		$cache_key = 'rl_of_country_reference_v1';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$data    = $this->get_default_country_reference_data();
		$sources = apply_filters(
			'rl_options_framework_country_reference_sources',
			[
				[
					'id'      => 'restcountries',
					'type'    => 'restcountries',
					'url'     => 'https://restcountries.com/v3.1/all?fields=cca2,name,region,capital',
					'timeout' => 6,
				],
			],
			$this->framework
		);

		if ( is_array( $sources ) ) {
			foreach ( $sources as $source ) {
				if ( ! is_array( $source ) ) {
					continue;
				}
				$type = strtolower( (string) ( $source['type'] ?? '' ) );
				if ( $type === 'restcountries' ) {
					$remote = $this->fetch_restcountries_reference_data( $source );
					if ( ! empty( $remote ) ) {
						$data = $remote + $data;
					}
				}
			}
		}

		$data = apply_filters( 'rl_options_framework_country_reference_data', $data, $sources, $this->framework );
		if ( ! is_array( $data ) || empty( $data ) ) {
			$data = $this->get_default_country_reference_data();
		}

		$ttl = max( 0, (int) apply_filters( 'rl_options_framework_country_reference_ttl', 0, $this->framework ) );
		if ( ! empty( $data ) ) {
			set_transient( $cache_key, $data, $ttl );
		} else {
			delete_transient( $cache_key );
		}
		do_action( 'rl_options_framework_country_reference_warmed', $data, $ttl, $this->framework );

		return $data;
	}

	/**
	 * Return normalized subdivisions list for a country.
	 *
	 * Results are filterable via `rl_options_framework_country_subdivisions`.
	 *
	 * @param  string $country_code ISO 3166-1 alpha-2 country code.
	 * @return array<int,array{value:string,label:string}>
	 */
	public function get_country_subdivisions_data( string $country_code ): array {
		$country_code = strtoupper( sanitize_key( $country_code ) );
		if ( $country_code === '' ) {
			return [];
		}

		$cache_key = 'rl_of_subdivisions_' . strtolower( $country_code );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$defaults = [
			'PT' => [
				[ 'value' => 'aveiro',  'label' => 'Aveiro' ],
				[ 'value' => 'braga',   'label' => 'Braga' ],
				[ 'value' => 'coimbra', 'label' => 'Coimbra' ],
				[ 'value' => 'lisboa',  'label' => 'Lisboa' ],
				[ 'value' => 'porto',   'label' => 'Porto' ],
			],
		];

		$out = $defaults[ $country_code ] ?? [];
		$out = apply_filters( 'rl_options_framework_country_subdivisions', $out, $country_code, $this->framework );
		$out = $this->framework->normalize_options_for_transport( is_array( $out ) ? $out : [] );

		$ttl = max( 0, (int) apply_filters( 'rl_options_framework_country_reference_ttl', 0, $this->framework ) );
		if ( ! empty( $out ) ) {
			set_transient( $cache_key, $out, $ttl );
		} else {
			delete_transient( $cache_key );
		}

		return $out;
	}

	/**
	 * Return normalized municipalities list for a country/subdivision.
	 *
	 * Results are filterable via `rl_options_framework_country_municipalities`.
	 *
	 * @param  string $country_code ISO 3166-1 alpha-2 country code.
	 * @param  string $subdivision  Subdivision slug.
	 * @return array<int,array{value:string,label:string}>
	 */
	public function get_country_municipalities_data( string $country_code, string $subdivision ): array {
		$country_code = strtoupper( sanitize_key( $country_code ) );
		$subdivision  = sanitize_key( $subdivision );
		if ( $country_code === '' ) {
			return [];
		}

		$cache_key = 'rl_of_municipalities_' . strtolower( $country_code ) . '_' . $subdivision;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$defaults = [
			'PT' => [
				'lisboa' => [
					[ 'value' => 'lisboa',  'label' => 'Lisboa' ],
					[ 'value' => 'sintra',  'label' => 'Sintra' ],
					[ 'value' => 'cascais', 'label' => 'Cascais' ],
				],
				'porto'  => [
					[ 'value' => 'porto', 'label' => 'Porto' ],
					[ 'value' => 'gaia',  'label' => 'Vila Nova de Gaia' ],
					[ 'value' => 'maia',  'label' => 'Maia' ],
				],
			],
		];

		$out = [];
		if ( isset( $defaults[ $country_code ] ) && is_array( $defaults[ $country_code ] ) ) {
			if ( $subdivision !== '' && isset( $defaults[ $country_code ][ $subdivision ] ) ) {
				$out = $defaults[ $country_code ][ $subdivision ];
			} elseif ( $subdivision === '' ) {
				foreach ( $defaults[ $country_code ] as $items ) {
					if ( is_array( $items ) ) {
						$out = array_merge( $out, $items );
					}
				}
			}
		}

		$out = apply_filters( 'rl_options_framework_country_municipalities', $out, $country_code, $subdivision, $this->framework );
		$out = $this->framework->normalize_options_for_transport( is_array( $out ) ? $out : [] );

		$ttl = max( 0, (int) apply_filters( 'rl_options_framework_country_reference_ttl', 0, $this->framework ) );
		if ( ! empty( $out ) ) {
			set_transient( $cache_key, $out, $ttl );
		} else {
			delete_transient( $cache_key );
		}

		return $out;
	}

	/**
	 * Load reference countries from REST Countries API.
	 *
	 * @param  array $source Source configuration array (url, timeout).
	 * @return array<string,array>
	 */
	private function fetch_restcountries_reference_data( array $source ): array {
		$url = isset( $source['url'] ) ? esc_url_raw( (string) $source['url'] ) : '';
		if ( $url === '' ) {
			return [];
		}

		$timeout  = max( 2, min( 12, (int) ( $source['timeout'] ?? 6 ) ) );
		$response = wp_remote_get( $url, [ 'timeout' => $timeout ] );
		if ( is_wp_error( $response ) ) {
			return [];
		}

		if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return [];
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) ) {
			return [];
		}

		$out = [];
		foreach ( $payload as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$code = strtoupper( sanitize_key( (string) ( $item['cca2'] ?? '' ) ) );
			if ( $code === '' ) {
				continue;
			}
			$name    = (string) ( $item['name']['common'] ?? $code );
			$region  = (string) ( $item['region'] ?? '' );
			$capital = '';
			if ( ! empty( $item['capital'] ) && is_array( $item['capital'] ) ) {
				$capital = (string) reset( $item['capital'] );
			}
			$out[ $code ] = [
				'code'    => $code,
				'name'    => $name,
				'region'  => $region,
				'capital' => $capital,
			];
		}

		return $out;
	}

	/**
	 * Fallback country dataset used when no external source is available.
	 *
	 * @return array<string,array>
	 */
	private function get_default_country_reference_data(): array {
		return [
			'PT' => [ 'code' => 'PT', 'name' => 'Portugal',        'region' => 'Europe',   'capital' => 'Lisbon' ],
			'ES' => [ 'code' => 'ES', 'name' => 'Spain',           'region' => 'Europe',   'capital' => 'Madrid' ],
			'FR' => [ 'code' => 'FR', 'name' => 'France',          'region' => 'Europe',   'capital' => 'Paris' ],
			'DE' => [ 'code' => 'DE', 'name' => 'Germany',         'region' => 'Europe',   'capital' => 'Berlin' ],
			'IT' => [ 'code' => 'IT', 'name' => 'Italy',           'region' => 'Europe',   'capital' => 'Rome' ],
			'GB' => [ 'code' => 'GB', 'name' => 'United Kingdom',  'region' => 'Europe',   'capital' => 'London' ],
			'US' => [ 'code' => 'US', 'name' => 'United States',   'region' => 'Americas', 'capital' => 'Washington, D.C.' ],
			'BR' => [ 'code' => 'BR', 'name' => 'Brazil',          'region' => 'Americas', 'capital' => 'Brasilia' ],
			'CA' => [ 'code' => 'CA', 'name' => 'Canada',          'region' => 'Americas', 'capital' => 'Ottawa' ],
			'AU' => [ 'code' => 'AU', 'name' => 'Australia',       'region' => 'Oceania',  'capital' => 'Canberra' ],
		];
	}
}
