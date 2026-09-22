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
			[ 'key' => '--zaplane-primary-strong', 'label' => 'Primary (solid fill)' ],
			[ 'key' => '--zaplane-second-primary', 'label' => 'Primary (soft)' ],
			[ 'key' => '--zaplane-secondary', 'label' => 'Secondary' ],
			[ 'key' => '--zaplane-secondary-color', 'label' => 'Surface' ],
			[ 'key' => '--zaplane-background', 'label' => 'Background' ],
			[ 'key' => '--zaplane-body-background', 'label' => 'Body background' ],
			[ 'key' => '--zaplane-canvas', 'label' => 'Canvas' ],
			[ 'key' => '--zaplane-border-color', 'label' => 'Border' ],
			[ 'key' => '--zaplane-font-color', 'label' => 'Text' ],
			[ 'key' => '--zaplane-font-secondary-color', 'label' => 'Text (secondary)' ],
			[ 'key' => '--zaplane-text-muted', 'label' => 'Text (muted)' ],
			[ 'key' => '--zaplane-placeholder', 'label' => 'Placeholder' ],
			[ 'key' => '--zaplane-success', 'label' => 'Success' ],
			[ 'key' => '--zaplane-warning', 'label' => 'Warning' ],
			[ 'key' => '--zaplane-danger', 'label' => 'Danger' ],
			[ 'key' => '--zaplane-gray', 'label' => 'Gray' ],
			[ 'key' => '--zaplane-cat-trigger', 'label' => 'Node · trigger' ],
			[ 'key' => '--zaplane-cat-action', 'label' => 'Node · action' ],
			[ 'key' => '--zaplane-cat-tool', 'label' => 'Node · tool' ],
			[ 'key' => '--zaplane-cat-ai', 'label' => 'Node · AI' ],
		];
	}

	/**
	 * @return array<string,string>
	 */
	public static function default_light_palette(): array {
		return [
			'--zaplane-primary'              => '#006BFF',
			// The fill under a white button label. In light this is the brand
			// itself, which already clears 4.5:1; dark has to go deeper.
			'--zaplane-primary-strong'       => '#006BFF',
			'--zaplane-second-primary'       => '#DAEAFF',
			'--zaplane-secondary'            => '#F5F5F5',
			'--zaplane-secondary-color'      => '#F6F7F8',
			'--zaplane-background'           => '#FFFFFF',
			'--zaplane-body-background'      => '#F6F7F8',
			// The canvas must not equal the node fill, or a node is only visible
			// because of its border — which is what forced the border to be heavy.
			'--zaplane-canvas'               => '#EDF0F4',
			'--zaplane-border-color'         => '#CBD1D7',
			'--zaplane-font-color'           => '#141A24',
			'--zaplane-font-secondary-color' => '#737373',
			'--zaplane-text-muted'           => '#738496',
			'--zaplane-placeholder'          => '#A2ADB9',
			'--zaplane-success'              => '#16A34A',
			'--zaplane-warning'              => '#FDB022',
			'--zaplane-danger'               => '#E44A3F',
			'--zaplane-gray'                 => '#F6F7F8',
			// Node categories. Actions take the brand hue because they are the
			// default case; the rest are spaced far enough apart to be told apart
			// at a glance when the canvas is zoomed out and labels are unreadable.
			'--zaplane-cat-trigger'          => '#0E9F6E',
			'--zaplane-cat-action'           => '#006BFF',
			'--zaplane-cat-tool'             => '#D97706',
			'--zaplane-cat-ai'               => '#7C3AED',
		];
	}

	/**
	 * @return array<string,string>
	 */
	public static function default_dark_palette(): array {
		return [
			'--zaplane-primary'              => '#4C8DFF',
			// A blue bright enough to read as an accent on the dark ground is too
			// light to sit under a white label, and one dark enough for the label
			// is too dim as an accent. No single value does both, so the solid
			// fill is its own token: 5.17:1 under white, where the accent gave 3.2.
			'--zaplane-primary-strong'       => '#2563EB',
			'--zaplane-second-primary'       => '#16263C',
			'--zaplane-secondary'            => '#212732',
			'--zaplane-secondary-color'      => '#1A1F27',
			'--zaplane-background'           => '#161A21',
			'--zaplane-body-background'      => '#0E1116',
			'--zaplane-canvas'               => '#0A0C10',
			// Raised from #2C333F. A border doing separation work has to be seen.
			'--zaplane-border-color'         => '#303845',
			// Pulled back off near-white. 14:1 on a ground this dark halates;
			// this holds 13.5:1 without the glare.
			'--zaplane-font-color'           => '#DCE3EC',
			'--zaplane-font-secondary-color' => '#A7B3C2',
			// Both of these used to fail AA outright — 3.70:1 and 3.54:1 — which is
			// why secondary text read as washed out.
			'--zaplane-text-muted'           => '#8794A6',
			'--zaplane-placeholder'          => '#7C8899',
			'--zaplane-success'              => '#3DD68C',
			'--zaplane-warning'              => '#F5B544',
			'--zaplane-danger'               => '#FB7185',
			'--zaplane-gray'                 => '#1A1F27',
			// Lightened so each stays legible against #0B0E13 rather than being a
			// straight reuse of the light values.
			'--zaplane-cat-trigger'          => '#3DD68C',
			'--zaplane-cat-action'           => '#4C8DFF',
			'--zaplane-cat-tool'             => '#F5A524',
			'--zaplane-cat-ai'               => '#A78BFA',
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
	 * - `settings` — admin URL (relative to wp-admin) of the module's own
	 *   settings, for the gear button on the Modules screen. Unused with `panel`.
	 * - `since` — the version that introduced it, used to badge it as new.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function modules(): array {
		$modules = [
			'custom_apps' => [
				'key'         => 'custom_apps',
				'title'       => __( 'Custom Apps', 'zaplane' ),
				'description' => __( 'Build your own integrations from the UI — any external REST API, or hooks on this site. When off, the Custom Apps menu is hidden.', 'zaplane' ),
				'default'     => false,
				'menu'        => 'custom-apps',
				'panel'       => '',
				'settings'    => 'admin.php?page=zaplane-custom-apps',
				// Custom apps are user-defined, so the slugs are resolved at runtime
				// rather than listed here.
				'apps'        => [],
				'owns_custom_apps' => true,
				'since'       => '1.1.0',
			],
			'knowledge'   => [
				'key'         => 'knowledge',
				'title'       => __( 'Business Knowledge', 'zaplane' ),
				'description' => __( 'A searchable knowledge base the AI Agent can answer from. Sync any post type — products, docs, policies. When off, the Business Knowledge menu is hidden.', 'zaplane' ),
				'default'     => false,
				'menu'        => 'knowledge',
				'panel'       => '',
				'settings'    => 'admin.php?page=zaplane-knowledge&settings=1',
				'apps'        => [ 'knowledge' ],
				'since'       => '1.1.0',
			],
			'inbox'       => [
				'key'         => 'inbox',
				'title'       => __( 'Inbox', 'zaplane' ),
				'description' => __( 'One inbox for every customer conversation, starting with a chat widget for your website. An AI assistant answers from your Business Knowledge and hands over to your team when needed. When off, nothing is added to your site.', 'zaplane' ),
				'default'     => false,
				'menu'        => 'inbox',
				'panel'       => '',
				'settings'    => 'admin.php?page=zaplane-inbox&view=settings',
				'apps'        => [ 'inbox' ],
				'since'       => '1.4.0',
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
				// Nothing on the canvas corresponds to the MCP server; the MCP Client
				// tool is the other direction and is not gated by this.
				'apps'        => [],
				'since'       => '1.2.0',
			],
		];

		/**
		 * Filter the registered modules.
		 *
		 * Adding one here makes it appear on the Modules screen, saveable, and
		 * eligible for the discovery card, with no other change. A callback must
		 * not call feature_enabled() — the settings defaults are derived from this
		 * list, so that would recurse.
		 *
		 * @param array<string,array<string,mixed>> $modules
		 */
		return (array) apply_filters( 'zaplane/modules', $modules );
	}

	/**
	 * The module that owns an integration slug, or null when nothing does.
	 *
	 * No integration is hidden when its module is off — that would break
	 * workflows already using it — so this is what lets the builder notice it is
	 * offering a node from a module the site has not switched on.
	 */
	public static function module_for_app( string $slug ): ?string {
		if ( '' === $slug ) {
			return null;
		}

		foreach ( self::modules() as $key => $module ) {
			if ( in_array( $slug, (array) ( $module['apps'] ?? [] ), true ) ) {
				return $key;
			}
		}

		// User-defined apps belong to whichever module claims them, and their slugs
		// are only known at runtime.
		foreach ( self::modules() as $key => $module ) {
			if ( empty( $module['owns_custom_apps'] ) ) {
				continue;
			}
			if ( class_exists( '\Zaplane\CustomApps\ManifestStore' )
				&& array_key_exists( $slug, \Zaplane\CustomApps\ManifestStore::all() ) ) {
				return $key;
			}
		}

		return null;
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

		// Base a save on what is currently in effect, not on the defaults. Settings
		// are stored as a whole tree, so starting from defaults means a partial
		// save — activating one module from a teaser, say — silently resets every
		// key the caller did not mention back to its default.
		$out = self::get();

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
