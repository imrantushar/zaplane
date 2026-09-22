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
		Services\WorkflowSends::register();
		Services\VisitorContact::register();
		add_action( Services\KnowledgeAnswer::HOOK, [ Services\KnowledgeAnswer::class, 'handle' ], 10, 2 );
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
			// Report the page for the team's Visitors list.
			'visitors' => (bool) $widget['visitors'],
			// A reply from the team opens the chat, with a sound.
			'autoOpen' => (bool) $widget['auto_open'],
			'sound'    => (bool) $widget['sound'],
			// Buttons under the greeting, before the visitor has typed anything.
			'questions' => Services\AnswerMenu::top_level(),
			'menu'      => Services\AnswerMenu::for_widget(),
			// Knowledge may answer on its own, so show "typing…" after sending.
			'autoAnswers' => ! empty( Settings::get()['answers']['enabled'] ),
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
				'viewProduct' => __( 'View product', 'zaplane' ),
				'outOfStock'  => __( 'Out of stock', 'zaplane' ),
				'attachment'  => __( 'Attachment', 'zaplane' ),
				'deleted'     => __( 'Message deleted', 'zaplane' ),
				'edited'      => __( 'edited', 'zaplane' ),
				'readMore'    => __( 'Read more', 'zaplane' ),
				'commonQuestions' => __( 'Common questions', 'zaplane' ),
				'talkToPerson'    => Services\KnowledgeAnswer::person_label(),
				'allTopics'       => Services\AnswerMenu::topics_label(),
				'contactTitle'    => __( 'How can we reach you?', 'zaplane' ),
				'contactHint'     => __( "If you leave the page, we'll email you our reply.", 'zaplane' ),
				'contactSave'     => __( 'Save', 'zaplane' ),
				'contactSkip'     => __( 'Not now', 'zaplane' ),
				'contactSaved'    => __( "Thanks! We'll email you if you leave before we reply.", 'zaplane' ),
				/* translators: %s: email address. */
				'codeTitle'       => __( 'Enter the 6-digit code we sent to %s', 'zaplane' ),
				'codeVerify'      => __( 'Confirm', 'zaplane' ),
				'codeResend'      => __( 'Send a new code', 'zaplane' ),
				'codeChange'      => __( 'Change email', 'zaplane' ),
				'codeLabel'       => __( 'Verification code', 'zaplane' ),
				'newMessage'      => __( 'New message', 'zaplane' ),
			],
		] );
	}
}
