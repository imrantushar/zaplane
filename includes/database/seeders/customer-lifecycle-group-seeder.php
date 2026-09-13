<?php

namespace Zaplane\Database\Seeders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Customer Lifecycle: a group recipe that sets up a store's customer
 * emails in one go.
 *
 * Each automation becomes its own workflow, in a new folder. The setup switches
 * them on or off, along with a few of their steps, and asks for the coupon
 * discount and how long a customer can go without ordering. Steps read those as
 * {{setup.coupon_percent}} and {{setup.inactive_days}}, which are filled in when
 * the workflows are created. See RecipeGroupBuilder.
 */
class CustomerLifecycleGroupSeeder {

	const SLUG = 'woocommerce-customer-lifecycle';

	private const APPS = [
		'woocommerce' => [ 'WooCommerce', 'woo.svg' ],
		'gemcrm'      => [ 'GemCRM', 'crm.svg' ],
		'delay'       => [ 'Delay', 'delay' ],
	];

	public function run(): void {
		RecipeSeeding::save(
			self::SLUG,
			[
				'type'              => 'group',
				'title'             => 'WooCommerce Customer Lifecycle',
				'description'       => 'Set up your store\'s customer emails in one go: recover abandoned carts, thank buyers with a coupon, ask for feedback, win back customers who stopped ordering, and send birthday coupons. Pick the ones you want; each becomes its own workflow in a new folder.',
				'blueprint'         => wp_json_encode( self::definition() ),
				'integration_icons' => wp_json_encode( [ 'woo.svg', 'crm.svg', 'delay' ] ),
			]
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function definition(): array {
		return [
			'folder'    => 'WooCommerce Customer Lifecycle',
			'values'    => [
				[
					'key'         => 'coupon_percent',
					'type'        => 'number',
					'label'       => 'Coupon discount',
					'description' => 'The discount on every coupon these emails send.',
					'default'     => 15,
					'min'         => 1,
					'max'         => 100,
					'suffix'      => '%',
				],
				[
					'key'         => 'inactive_days',
					'type'        => 'number',
					'label'       => 'Days without an order',
					'description' => 'How long a customer goes without ordering before the win-back email is sent.',
					'default'     => 60,
					'min'         => 7,
					'max'         => 365,
					'suffix'      => 'days',
				],
			],
			'workflows' => [
				self::abandoned_cart(),
				self::thank_you_coupon(),
				self::feedback_request(),
				self::win_back(),
				self::birthday_coupon(),
			],
		];
	}

	private static function abandoned_cart(): array {
		return [
			'key'         => 'abandoned_cart',
			'title'       => 'Recover abandoned carts',
			'description' => 'Emails shoppers who leave items in their cart, with a link back to it.',
			'graph'       => self::chain(
				[
					self::trigger( 'woocommerce', 'cart_abandoned', 'zaplane/abandoned_cart/started', 'Abandoned Cart' ),
					self::email(
						'Send Cart Reminder',
						self::to_email(),
						'You left something in your cart',
						'<p>Hi {{1.full_name}},</p><p>It looks like you left some items in your cart. We\'ve saved them for you.</p><p><a href="{{1.recovery_link}}">Complete your purchase</a></p><p>If you have any questions, just reply to this email.</p>'
					),
					self::wait( 3 ),
					self::email(
						'Send Last-chance Email',
						self::to_email(),
						'Your cart is still saved',
						'<p>Hi {{1.full_name}},</p><p>Your cart is still saved, but items can sell out.</p><p><a href="{{1.recovery_link}}">Go back to your cart</a></p><p>If you\'ve already completed your purchase, please ignore this email.</p>'
					),
				]
			),
			'options'     => [
				[
					'key'   => 'last_chance',
					'label' => 'Send a last-chance email 3 days later',
					'nodes' => [ '3', '4' ],
				],
			],
		];
	}

	private static function thank_you_coupon(): array {
		return [
			'key'         => 'thank_you_coupon',
			'title'       => 'Thank buyers with a coupon',
			'description' => 'Sends a one-time coupon for the next order as soon as an order is placed.',
			// A coupon on every order is a business decision, so it starts off.
			'default'     => false,
			'graph'       => self::chain(
				[
					self::trigger( 'woocommerce', 'order_status_processing', 'woocommerce_order_status_processing', 'Order Placed' ),
					self::coupon( 'Create Thank-you Coupon', 'THANKS-{{1.order_id}}' ),
					self::email(
						'Send Thank-you Coupon',
						self::to_email(),
						'Thank you for your order, {{1.first_name}}',
						'<p>Hi {{1.first_name}},</p><p>Thank you for your order #{{1.order_number}}. As a thank-you, here is {{setup.coupon_percent}}% off your next order:</p><p><strong>{{2.coupon.code}}</strong></p><p>The code works once, at checkout.</p>'
					),
				]
			),
		];
	}

	private static function feedback_request(): array {
		return [
			'key'         => 'feedback_request',
			'title'       => 'Ask for feedback',
			'description' => 'Two days after an order is completed, asks the customer how it went.',
			'graph'       => self::chain(
				[
					self::trigger( 'woocommerce', 'order_status_completed', 'woocommerce_order_status_completed', 'Order Completed' ),
					self::wait( 2 ),
					self::email(
						'Send Feedback Request',
						self::to_email(),
						'How was your order, {{1.first_name}}?',
						'<p>Hi {{1.first_name}},</p><p>Thank you for your order #{{1.order_number}}. We hope you\'re enjoying it.</p><p>Would you tell us how it went? It only takes a minute.</p><p><a href="{{1.feedback_page_url}}">Leave your feedback</a></p>'
					),
					self::action(
						'woocommerce',
						'add_order_note',
						'Note the Request on the Order',
						[
							'order_id'         => '{{1.order_id}}',
							'note'             => 'Feedback request sent to {{1.email}}.',
							'is_customer_note' => false,
						]
					),
				]
			),
			'options'     => [
				[
					'key'   => 'order_note',
					'label' => 'Add a private note to the order when the request is sent',
					'nodes' => [ '4' ],
				],
			],
		];
	}

	private static function win_back(): array {
		return [
			'key'         => 'win_back',
			'title'       => 'Win back inactive customers',
			'description' => 'Reaches out to customers who haven\'t ordered for a while.',
			'graph'       => self::chain(
				[
					self::trigger(
						'woocommerce',
						'inactive_customer',
						'zaplane_woo_inactive_customer',
						'Inactive Customer',
						[
							'days'     => '{{setup.inactive_days}}',
							'tag_ids'  => [],
							'list_ids' => [],
						]
					),
					self::email(
						'Send We-miss-you Email',
						self::to_email(),
						'We miss you, {{1.first_name}}',
						'<p>Hi {{1.first_name}},</p><p>It\'s been a while since your last order, and we\'d love to see you again.</p><p>Come and see what\'s new in the store.</p>'
					),
					self::wait( 7 ),
					self::coupon( 'Create Win-back Coupon', 'WINBACK-{{1.id}}' ),
					self::email(
						'Send Win-back Coupon',
						self::to_email(),
						'A little something to welcome you back',
						'<p>Hi {{1.first_name}},</p><p>Here is {{setup.coupon_percent}}% off your next order, just for you:</p><p><strong>{{4.coupon.code}}</strong></p><p>The code works once, at checkout.</p>'
					),
				]
			),
			'options'     => [
				[
					'key'   => 'coupon',
					'label' => 'Follow up with a personal coupon a week later',
					'nodes' => [ '3', '4', '5' ],
				],
			],
		];
	}

	private static function birthday_coupon(): array {
		$to_contact = [
			'recipient_type' => 'contact',
			'contact_id'     => '{{1.id}}',
		];

		return [
			'key'         => 'birthday_coupon',
			'title'       => 'Send birthday coupons',
			'description' => 'Emails a coupon to GemCRM contacts on their birthday.',
			'graph'       => self::chain(
				[
					self::trigger( 'gemcrm', 'contact_birthday', 'zaplane_gemcrm_contact_birthday', 'Contact Birthday', [ 'purchase_tag_id' => '' ] ),
					self::coupon( 'Create Birthday Coupon', 'BDAY-{{1.id}}' ),
					self::email(
						'Send Birthday Coupon',
						$to_contact,
						'Happy birthday, {{1.first_name}}!',
						'<p>Hi {{1.first_name}},</p><p>Happy birthday! Here is {{setup.coupon_percent}}% off, as our gift to you:</p><p><strong>{{2.coupon.code}}</strong></p><p>The code works once, at checkout.</p>'
					),
					self::wait( 7 ),
					self::email(
						'Send Birthday Reminder',
						$to_contact,
						'Your birthday coupon is still yours',
						'<p>Hi {{1.first_name}},</p><p>A reminder that your birthday coupon is still yours to use:</p><p><strong>{{2.coupon.code}}</strong></p>'
					),
				]
			),
			'options'     => [
				[
					'key'   => 'reminder',
					'label' => 'Send a reminder a week later',
					'nodes' => [ '4', '5' ],
				],
			],
		];
	}

	/**
	 * Numbers the steps in order, lays them out left to right, and joins each one to the next.
	 *
	 * @param array<int,array<string,mixed>> $steps
	 * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array<string,string>>}
	 */
	private static function chain( array $steps ): array {
		$nodes = [];
		$edges = [];

		foreach ( array_values( $steps ) as $index => $step ) {
			$id = (string) ( $index + 1 );

			$nodes[] = array_merge(
				[
					'id'       => $id,
					'position' => [
						'x' => 80 + $index * 340,
						'y' => 200,
					],
				],
				$step
			);

			if ( $index > 0 ) {
				$edges[] = [
					'id'     => 'e' . $index . '-' . $id,
					'source' => (string) $index,
					'target' => $id,
				];
			}
		}

		return [
			'nodes' => $nodes,
			'edges' => $edges,
		];
	}

	private static function trigger( string $app, string $event, string $hook, string $name, array $config = [] ): array {
		return [
			'type' => 'trigger',
			'data' => [
				'app'    => $app,
				'event'  => $event,
				'hook'   => $hook,
				'label'  => self::APPS[ $app ][0],
				'icon'   => self::APPS[ $app ][1],
				'name'   => $name,
				'config' => $config,
			],
		];
	}

	private static function action( string $app, string $event, string $name, array $config ): array {
		return [
			'type' => 'action',
			'data' => [
				'app'    => $app,
				'event'  => $event,
				'label'  => self::APPS[ $app ][0],
				'icon'   => self::APPS[ $app ][1],
				'name'   => $name,
				'config' => $config,
			],
		];
	}

	private static function email( string $name, array $recipient, string $subject, string $body ): array {
		return self::action(
			'gemcrm',
			'send_email',
			$name,
			array_merge(
				$recipient,
				[
					'subject'        => $subject,
					'content_source' => 'custom',
					'body'           => $body,
				]
			)
		);
	}

	/**
	 * The email address the trigger reports, for triggers that aren't a GemCRM contact.
	 */
	private static function to_email(): array {
		return [
			'recipient_type' => 'custom',
			'custom_email'   => '{{1.email}}',
		];
	}

	private static function wait( int $days ): array {
		return self::action(
			'delay',
			'wait',
			'Wait ' . $days . ' Days',
			[
				'unit'   => 'days',
				'amount' => $days,
			]
		);
	}

	private static function coupon( string $name, string $code ): array {
		return self::action(
			'woocommerce',
			'create_coupon',
			$name,
			[
				'code'               => $code,
				'discount_type'      => 'percent',
				'amount'             => '{{setup.coupon_percent}}',
				'usage_limit'        => '1',
				'expiry_date'        => '',
				'email_restrictions' => '{{1.email}}',
			]
		);
	}
}
