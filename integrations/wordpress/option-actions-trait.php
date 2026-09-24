<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait OptionActionsTrait {

	protected static function action_activate_plugin( array $config ): array {
		$plugin = $config['plugin'] ?? '';
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			return static::error( "Failed to activate plugin {$plugin}: " . $result->get_error_message() );
		}

		return static::success([
			'plugin' => $plugin,
			'status' => 'activated',
		]);
	}

	protected static function action_deactivate_plugin( array $config ): array {
		$plugin = $config['plugin'] ?? '';
		$result = deactivate_plugins( $plugin );
		if ( ! $result ) {
			return static::error( "Failed to deactivate plugin {$plugin}" );
		}

		return static::success([
			'plugin' => $plugin,
			'status' => 'deactivated',
		]);
	}

	/*
	 * There is deliberately no action that switches the active theme. Changing
	 * which theme a site runs is the site owner's decision, taken in the
	 * Appearance screens, not something an automation should do on their behalf.
	 * The Theme Switch *trigger* remains: it listens for the switch the owner
	 * makes and lets a workflow react to it.
	 */

	protected static function action_add_plugin_theme_option( array $config ): array {
		add_option( $config['option_name'] ?? '', $config['value'] ?? '' );
		return static::success( [ 'option_name' => $config['option_name'] ] );
	}

	protected static function action_update_option_advanced( array $config ): array {
		update_option( $config['option_name'] ?? '', $config['value'] ?? '' );
		return static::success();
	}

	protected static function action_delete_option( array $config ): array {
		$result = delete_option( $config['option_name'] ?? '' );
		if ( ! $result ) {
			return static::error( 'Failed to delete option' );
		}
		return static::success( [ 'option_name' => $config['option_name'] ] );
	}
}
