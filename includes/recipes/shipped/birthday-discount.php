<?php
/**
 * Birthday Discount: emails a GemCRM contact a coupon on their birthday, and a
 * reminder a week later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'Birthday Discount',
	'description' => 'Automatically reward GemCRM contacts on their birthday. Sends a personalized discount coupon, waits 7 days, then sends a reminder email. Optionally applies a tag when the contact makes a purchase.',
	'steps'       => [
		[
			'trigger' => 'gemcrm.contact_birthday',
			'name'    => 'Contact Birthday',
			'config'  => [
				'purchase_tag_id' => '',
			],
		],
		[
			'action' => 'woocommerce.create_coupon',
			'name'   => 'Create Birthday Coupon',
			'config' => [
				'code'               => 'BDAY-{{trigger.id}}',
				'discount_type'      => 'percent',
				'amount'             => '20',
				'usage_limit'        => '1',
				'expiry_date'        => '',
				'email_restrictions' => '{{trigger.email}}',
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Birthday Discount Email',
			'config' => [
				'recipient_type' => 'contact',
				'contact_id'     => '{{trigger.id}}',
				'subject'        => '🎂 Happy Birthday {{trigger.first_name}} — Here\'s Your Exclusive Discount!',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.first_name}},</p><p>Wishing you a wonderful birthday! As a special gift, here is your exclusive discount code:</p><p><strong>{{coupon.code}}</strong></p><p>Use it at checkout to get 20% off your next purchase. This code is valid for one use only, so treat yourself!</p><p>Happy Birthday! 🎉</p>',
			],
		],
		[
			'action' => 'delay.wait',
			'name'   => 'Wait 7 Days',
			'config' => [
				'unit'   => 'days',
				'amount' => 7,
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Birthday Reminder Email',
			'config' => [
				'recipient_type' => 'contact',
				'contact_id'     => '{{trigger.id}}',
				'subject'        => 'Don\'t forget your birthday discount, {{trigger.first_name}}!',
				'content_source' => 'custom',
				// The Send Email and Wait steps pass the coupon along, so its code still reads by name.
				'body'           => '<p>Hi {{trigger.first_name}},</p><p>Just a friendly reminder that your birthday discount is still waiting for you!</p><p><strong>{{coupon.code}}</strong></p><p>Use it before it expires. We\'d love to celebrate your special day with you.</p>',
			],
		],
	],
];
