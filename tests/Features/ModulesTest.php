<?php

namespace Zaplane\Tests\Features;

use Zaplane\Settings;
use Zaplane\Tests\TestCase;

class ModulesTest extends TestCase {

	/**
	 * @test
	 */
	public function every_module_declares_the_fields_the_ui_renders(): void {
		$modules = Settings::modules();

		$this->assertNotEmpty( $modules );

		foreach ( $modules as $key => $module ) {
			$this->assertSame( $key, $module['key'], 'Registry key and module key must agree.' );
			$this->assertNotEmpty( $module['title'], $key . ' has no title.' );
			$this->assertNotEmpty( $module['description'], $key . ' has no description.' );
			$this->assertIsBool( $module['default'], $key . ' has a non-boolean default.' );
			$this->assertArrayHasKey( 'menu', $module );
			$this->assertArrayHasKey( 'panel', $module );
			$this->assertArrayHasKey( 'since', $module );
		}
	}

	/**
	 * @test
	 */
	public function mcp_is_a_module_and_is_off_by_default(): void {
		$modules = Settings::modules();

		$this->assertArrayHasKey( 'mcp_server', $modules );
		$this->assertFalse( $modules['mcp_server']['default'] );
		$this->assertSame( 'mcp', $modules['mcp_server']['panel'] );
		$this->assertFalse( Settings::feature_enabled( 'mcp_server' ) );
	}

	/**
	 * @test
	 */
	public function a_module_with_a_settings_panel_carries_a_short_label_for_it(): void {
		foreach ( Settings::modules() as $key => $module ) {
			if ( '' === $module['panel'] ) {
				continue;
			}

			// The panel becomes a nav item, so the long module title will not do.
			$this->assertNotEmpty( $module['panel_label'] ?? '', $key . ' has a panel but no panel_label.' );
		}
	}

	/**
	 * @test
	 */
	public function nothing_offers_to_send_a_user_to_a_panel_that_is_hidden(): void {
		// A module's settings panel only exists while the module is on, so a panel
		// belonging to an off-by-default module is not a place to send anyone.
		$hidden = [];
		foreach ( Settings::modules() as $module ) {
			if ( '' !== $module['panel'] && ! $module['default'] ) {
				$hidden[ $module['panel'] ] = $module['key'];
			}
		}

		$this->assertNotEmpty(
			$hidden,
			'No module ships with a hidden panel, so this guard would pass vacuously.'
		);

		// The builder prompt switches the module on where the user stands rather
		// than linking anywhere, which is what keeps it out of this trap.
		$prompt = \Zaplane\Features\Teasers::for_app( 'knowledge' );
		$this->assertNotEmpty( $prompt['activates'] );
		$this->assertArrayNotHasKey( 'cta_panel', $prompt );

		// The spotlight lists modules but never links at one of their panels.
		foreach ( \Zaplane\Features\Teasers::spotlight( 'workflows' )['modules'] as $module ) {
			if ( isset( $hidden[ $module['panel'] ] ) ) {
				$this->assertFalse(
					Settings::feature_enabled( $module['key'] ),
					$module['key'] . ' advertises a panel that is hidden while it is off.'
				);
			}
		}
	}

	/**
	 * @test
	 */
	public function the_settings_defaults_are_derived_from_the_registry(): void {
		$features = Settings::defaults()['features'];

		$this->assertSame(
			array_keys( Settings::modules() ),
			array_keys( $features ),
			'Every module, and only modules, should appear in the feature defaults.'
		);

		foreach ( Settings::modules() as $key => $module ) {
			$this->assertSame( $module['default'], $features[ $key ] );
		}

		$this->assertSame( [], array_filter( $features ), 'Nothing should be on out of the box.' );
	}

	/**
	 * @test
	 */
	public function a_module_can_be_toggled_and_the_change_sticks(): void {
		$this->assertFalse( Settings::feature_enabled( 'mcp_server' ) );

		Settings::save( [ 'features' => [ 'mcp_server' => true ] ] );

		$this->assertTrue( Settings::feature_enabled( 'mcp_server' ) );
		// Untouched modules are left as they were rather than being wiped.
		$this->assertFalse( Settings::feature_enabled( 'knowledge' ) );
	}

	/**
	 * @test
	 */
	public function every_module_is_opt_in(): void {
		foreach ( Settings::modules() as $key => $module ) {
			$this->assertFalse( $module['default'], $key . ' should ship switched off.' );
			$this->assertFalse( Settings::feature_enabled( $key ) );
		}
	}

	/**
	 * @test
	 */
	public function activating_one_module_does_not_switch_off_the_others(): void {
		Settings::save( [ 'features' => [ 'knowledge' => true ] ] );
		Settings::save( [ 'features' => [ 'custom_apps' => true ] ] );

		// A partial save must merge over what is in effect, not reset to defaults.
		$this->assertTrue( Settings::feature_enabled( 'knowledge' ) );
		$this->assertTrue( Settings::feature_enabled( 'custom_apps' ) );
		$this->assertFalse( Settings::feature_enabled( 'mcp_server' ) );

		Settings::save( [ 'features' => [ 'knowledge' => false ] ] );

		$this->assertFalse( Settings::feature_enabled( 'knowledge' ) );
		$this->assertTrue( Settings::feature_enabled( 'custom_apps' ) );
	}

	/**
	 * @test
	 */
	public function an_integration_slug_resolves_to_the_module_that_owns_it(): void {
		$this->assertSame( 'knowledge', Settings::module_for_app( 'knowledge' ) );
		$this->assertNull( Settings::module_for_app( 'woocommerce' ) );
		$this->assertNull( Settings::module_for_app( '' ) );
	}

	/**
	 * @test
	 */
	public function a_disabled_module_hides_its_menu_entry(): void {
		$menu = [
			'zaplane'              => [ 'title' => 'Dashboard' ],
			'zaplane-custom-apps'  => [ 'title' => 'Custom Apps' ],
			'zaplane-knowledge'    => [ 'title' => 'Business Knowledge' ],
		];

		Settings::save( [ 'features' => [ 'custom_apps' => true, 'knowledge' => true ] ] );
		$this->assertArrayHasKey( 'zaplane-custom-apps', Settings::filter_admin_menu( $menu ) );

		Settings::save( [ 'features' => [ 'custom_apps' => false ] ] );

		$filtered = Settings::filter_admin_menu( $menu );

		$this->assertArrayNotHasKey( 'zaplane-custom-apps', $filtered );
		$this->assertArrayHasKey( 'zaplane-knowledge', $filtered, 'Only the disabled module should be hidden.' );
		$this->assertArrayHasKey( 'zaplane', $filtered );
	}

	/**
	 * @test
	 */
	public function a_module_with_no_menu_never_removes_one(): void {
		$menu = [
			'zaplane'           => [ 'title' => 'Dashboard' ],
			'zaplane-workflows' => [ 'title' => 'Workflows' ],
		];

		// mcp_server is off by default and declares no menu.
		$this->assertSame( $menu, Settings::filter_admin_menu( $menu ) );
	}
}
