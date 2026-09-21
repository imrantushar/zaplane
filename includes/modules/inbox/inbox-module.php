<?php

namespace Zaplane\Modules\Inbox;

use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Modules\Inbox\Api\AdminController;
use Zaplane\Modules\Inbox\Api\WidgetController;
use Zaplane\Modules\Inbox\Services\AiResponder;
use Zaplane\Modules\Inbox\Services\Router;
use Zaplane\Settings as ZaplaneSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One inbox for every conversation: the website chat widget today, social
 * channels next, with the assistant and workflows answering alongside the
 * team.
 *
 * Nothing here runs until the module is switched on — no tables, no routes,
 * no script on the front end.
 */
class InboxModule implements ModuleInterface {

	protected static ?self $instance = null;
	protected Container $container;

	public static function init( Container $container ): self {
		if ( ! self::$instance ) {
			self::$instance = new self( $container );
		}
		return self::$instance;
	}

	private function __construct( Container $container ) {
		$this->container = $container;
	}

	public static function enabled(): bool {
		return ZaplaneSettings::feature_enabled( 'inbox' );
	}

	public function register_hooks(): void {
		// The module switch reads translated module titles, which must not load
		// before init.
		add_action( 'init', [ $this, 'boot' ], 1 );
	}

	public function boot(): void {
		if ( ! self::enabled() ) {
			return;
		}

		Schema::ensure();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( Router::AI_HOOK, [ AiResponder::class, 'handle' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_widget' ] );
	}

	public function register_routes(): void {
		( new AdminController() )->register_routes();
		( new WidgetController() )->register_routes();
	}

	public function enqueue_widget(): void {
		$settings = Settings::get();
		$widget   = $settings['widget'];

		/**
		 * Whether to show the chat widget on this page.
		 *
		 * @param bool $show
		 */
		if ( empty( $widget['enabled'] ) || ! apply_filters( 'zaplane/inbox/show_widget', true ) ) {
			return;
		}

		$version = defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : '1';
		$base    = ZAPLANE_ASSETS_URI . 'inbox/';

		wp_enqueue_style( 'zaplane-inbox-widget', $base . 'widget.css', [], $version );
		wp_enqueue_script( 'zaplane-inbox-widget', $base . 'widget.js', [], $version, [
			'in_footer' => true,
			'strategy'  => 'defer',
		] );

		$agent = trim( (string) $settings['ai']['agent_name'] );
		$agent = '' !== $agent ? $agent : 'Ava';
		$ai_on = ! empty( $settings['ai']['enabled'] ) && ! empty( $settings['ai']['connection_id'] );

		// An explicit list: nothing else from the settings reaches the page.
		wp_localize_script( 'zaplane-inbox-widget', 'ZaplaneInbox', [
			'rest'     => esc_url_raw( rest_url( 'zaplane/v1/inbox/widget/' ) ),
			'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'title'    => (string) $widget['title'],
			'greeting' => (string) $widget['greeting'],
			'color'    => (string) $widget['color'],
			'position' => (string) $widget['position'],
			'askEmail' => (bool) $widget['ask_email'],
			'aiName'   => $ai_on ? $agent : '',
			/* translators: %s: assistant name. */
			'aiLabel'  => $ai_on ? sprintf( __( '%s · AI assistant', 'zaplane' ), $agent ) : '',
			'i18n'     => [
				'placeholder' => __( 'Type your message…', 'zaplane' ),
				'send'        => __( 'Send', 'zaplane' ),
				'name'        => __( 'Your name', 'zaplane' ),
				'email'       => __( 'Your email (so we can reply if you leave)', 'zaplane' ),
				'start'       => __( 'Start chat', 'zaplane' ),
				'open'        => __( 'Open chat', 'zaplane' ),
				'close'       => __( 'Close chat', 'zaplane' ),
				'typing'      => __( 'Typing…', 'zaplane' ),
				'error'       => __( 'Something went wrong. Please try again.', 'zaplane' ),
				'you'         => __( 'You', 'zaplane' ),
			],
		] );
	}
}
