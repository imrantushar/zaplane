<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central store for admin-configurable Zaplane settings: feature toggles and the
 * light/dark theme palettes. Backed by the single `zaplane_settings` option (JSON).
 *
 * Defaults here are the source of truth; saved values are deep-merged on top so a
 * partial save (or a newly-added key) always resolves to a complete settings tree.
 */
class Settings {

	const OPTION = 'zaplane_settings';

	/**
	 * The palette variable keys, in display order. These map 1:1 to the
	 * `--zaplane-*` CSS custom properties consumed across the app.
	 *
	 * @return array<int,array{key:string,label:string}>
	 */
	public static function palette_keys(): array {
		return [
			[ 'key' => '--zaplane-primary', 'label' => 'Primary' ],
			[ 'key' => '--zaplane-second-primary', 'label' => 'Primary (soft)' ],
			[ 'key' => '--zaplane-secondary', 'label' => 'Secondary' ],
			[ 'key' => '--zaplane-secondary-color', 'label' => 'Surface' ],
			[ 'key' => '--zaplane-background', 'label' => 'Background' ],
			[ 'key' => '--zaplane-body-background', 'label' => 'Body background' ],
			[ 'key' => '--zaplane-border-color', 'label' => 'Border' ],
			[ 'key' => '--zaplane-font-color', 'label' => 'Text' ],
			[ 'key' => '--zaplane-font-secondary-color', 'label' => 'Text (secondary)' ],
			[ 'key' => '--zaplane-text-muted', 'label' => 'Text (muted)' ],
			[ 'key' => '--zaplane-placeholder', 'label' => 'Placeholder' ],
			[ 'key' => '--zaplane-success', 'label' => 'Success' ],
			[ 'key' => '--zaplane-warning', 'label' => 'Warning' ],
			[ 'key' => '--zaplane-danger', 'label' => 'Danger' ],
			[ 'key' => '--zaplane-gray', 'label' => 'Gray' ],
		];
	}

	/**
	 * @return array<string,string>
	 */
	public static function default_light_palette(): array {
		return [
			'--zaplane-primary'              => '#006BFF',
			'--zaplane-second-primary'       => '#DAEAFF',
			'--zaplane-secondary'            => '#F5F5F5',
			'--zaplane-secondary-color'      => '#F6F7F8',
			'--zaplane-background'           => '#FFFFFF',
			'--zaplane-body-background'      => '#F6F7F8',
			'--zaplane-border-color'         => '#CBD1D7',
			'--zaplane-font-color'           => '#141A24',
			'--zaplane-font-secondary-color' => '#737373',
			'--zaplane-text-muted'           => '#738496',
			'--zaplane-placeholder'          => '#A2ADB9',
			'--zaplane-success'              => '#16A34A',
			'--zaplane-warning'              => '#FDB022',
			'--zaplane-danger'               => '#E44A3F',
			'--zaplane-gray'                 => '#F6F7F8',
		];
	}

	/**
	 * @return array<string,string>
	 */
	public static function default_dark_palette(): array {
		return [
			'--zaplane-primary'              => '#4C8DFF',
			'--zaplane-second-primary'       => '#172A45',
			'--zaplane-secondary'            => '#1F2630',
			'--zaplane-secondary-color'      => '#1E242C',
			'--zaplane-background'           => '#171C24',
			'--zaplane-body-background'      => '#0F141A',
			'--zaplane-border-color'         => '#2C333F',
			'--zaplane-font-color'           => '#E6E9EF',
			'--zaplane-font-secondary-color' => '#9AA4B2',
			'--zaplane-text-muted'           => '#6B7684',
			'--zaplane-placeholder'          => '#6B7280',
			'--zaplane-success'              => '#34D399',
			'--zaplane-warning'              => '#FBBF24',
			'--zaplane-danger'               => '#F87171',
			'--zaplane-gray'                 => '#1E242C',
		];
	}

	/**
	 * The optional modules a site owner can switch on and off.
	 *
	 * One registry, read by four things that used to each keep their own copy:
	 * the settings defaults, the sanitizer, the admin-menu filter, and the
	 * Modules screen in the dashboard (which is rendered from this over REST
	 * rather than from a hardcoded list in JavaScript).
	 *
	 * - `menu`  — submenu slug suffix hidden while the module is off.
	 * - `panel` — a settings tab that configures this module, shown once it is on.
	 * - `since` — the version that introduced it, used to badge it as new.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function modules(): array {
		return [
			'custom_apps' => [
				'key'         => 'custom_apps',
				'title'       => __( 'Custom Apps', 'zaplane' ),
				'description' => __( 'Build your own integrations from the UI — any external REST API, or hooks on this site. When off, the Custom Apps menu is hidden.', 'zaplane' ),
				'default'     => true,
				'menu'        => 'custom-apps',
				'panel'       => '',
				'since'       => '1.1.0',
			],
			'knowledge'   => [
				'key'         => 'knowledge',
				'title'       => __( 'Business Knowledge', 'zaplane' ),
				'description' => __( 'A searchable knowledge base the AI Agent can answer from. Sync any post type — products, docs, policies. When off, the Business Knowledge menu is hidden.', 'zaplane' ),
				'default'     => true,
				'menu'        => 'knowledge',
				'panel'       => '',
				'since'       => '1.1.0',
			],
			'mcp_server'  => [
				'key'         => 'mcp_server',
				'title'       => __( 'AI access (MCP)', 'zaplane' ),
				'description' => __( 'Let Claude, Cursor or any Model Context Protocol client read your automations and build new ones from a plain-language description. Configure it under AI access.', 'zaplane' ),
				// This one hands an outside AI client real power over the site, so it
				// stays off until a site owner turns it on and issues a token.
				'default'     => false,
				'menu'        => '',
				'panel'       => 'mcp',
				'panel_label' => __( 'AI access', 'zaplane' ),
				'since'       => '1.2.0',
			],
		];
	}

	/**
	 * Default on/off state for every module, derived from the registry.
	 *
	 * @return array<string,bool>
	 */
	private static function module_defaults(): array {
		$out = [];
		foreach ( self::modules() as $key => $module ) {
			$out[ $key ] = (bool) $module['default'];
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			'features' => self::module_defaults(),
			'theme'    => [
				'default_mode' => 'light',
				'light'        => self::default_light_palette(),
				'dark'         => self::default_dark_palette(),
			],
		];
	}

	/**
	 * The resolved settings tree (defaults deep-merged with saved values).
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$raw   = get_option( self::OPTION, '' );
		$saved = is_array( $raw ) ? $raw : ( is_string( $raw ) ? json_decode( $raw, true ) : null );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return self::deep_merge( self::defaults(), $saved );
	}

	/**
	 * Sanitize an incoming settings payload and persist it.
	 *
	 * @param array<string,mixed> $input
	 */
	public static function save( array $input ): bool {
		$clean = self::sanitize( $input );
		return update_option( self::OPTION, wp_json_encode( $clean ) );
	}

	public static function feature_enabled( string $key ): bool {
		$settings = self::get();
		return ! empty( $settings['features'][ $key ] );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function theme(): array {
		$settings = self::get();
		return $settings['theme'];
	}

	/**
	 * Register runtime hooks (feature gating). Called once during init.
	 */
	public static function boot(): void {
		add_filter( 'zaplane/admin_menu_list', [ self::class, 'filter_admin_menu' ] );
	}

	/**
	 * Hide disabled feature pages from both the WP submenu and the SPA sidebar
	 * (both are driven by this same list).
	 *
	 * @param array<string,mixed> $menu
	 * @return array<string,mixed>
	 */
	public static function filter_admin_menu( $menu ) {
		if ( ! is_array( $menu ) ) {
			return $menu;
		}
		foreach ( self::modules() as $key => $module ) {
			if ( '' === $module['menu'] || self::feature_enabled( $key ) ) {
				continue;
			}
			unset( $menu[ ZAPLANE_PLUGIN_SLUG . '-' . $module['menu'] ] );
		}

		return $menu;
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	private static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$out      = $defaults;

		// Features: booleans only.
		if ( isset( $input['features'] ) && is_array( $input['features'] ) ) {
			foreach ( array_keys( $defaults['features'] ) as $fkey ) {
				if ( array_key_exists( $fkey, $input['features'] ) ) {
					$out['features'][ $fkey ] = (bool) $input['features'][ $fkey ];
				}
			}
		}

		// Theme mode.
		if ( isset( $input['theme']['default_mode'] ) ) {
			$mode                          = $input['theme']['default_mode'];
			$out['theme']['default_mode']  = in_array( $mode, [ 'light', 'dark' ], true ) ? $mode : 'light';
		}

		// Palettes: keep only known keys, only valid hex colors.
		foreach ( [ 'light', 'dark' ] as $variant ) {
			if ( ! isset( $input['theme'][ $variant ] ) || ! is_array( $input['theme'][ $variant ] ) ) {
				continue;
			}
			foreach ( $defaults['theme'][ $variant ] as $var => $default_value ) {
				if ( ! isset( $input['theme'][ $variant ][ $var ] ) ) {
					continue;
				}
				$hex = sanitize_hex_color( (string) $input['theme'][ $variant ][ $var ] );
				if ( $hex ) {
					$out['theme'][ $variant ][ $var ] = $hex;
				}
			}
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $defaults
	 * @param array<string,mixed> $saved
	 * @return array<string,mixed>
	 */
	private static function deep_merge( array $defaults, array $saved ): array {
		$out = $defaults;
		foreach ( $saved as $key => $value ) {
			if ( is_array( $value ) && isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) ) {
				$out[ $key ] = self::deep_merge( $defaults[ $key ], $value );
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}
}
