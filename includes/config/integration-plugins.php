<?php
/**
 * Central map of integration slug => the WordPress plugin basename(s) it needs
 * active to function.
 *
 * This is the ONE place to declare dependencies. `IntegrationBase::get_required_plugins()`
 * reads this map by slug, so you never have to edit individual integration files.
 * The recipe testing CLI uses it to auto-activate the right plugin before a live
 * run; the value is also available for the admin UI ("requires WooCommerce").
 *
 * Rules:
 * - Key = the integration's get_slug() (NOT the folder name).
 * - Value = array of plugin basenames, e.g. [ 'woocommerce/woocommerce.php' ].
 * - API / SaaS integrations (Slack, Mailchimp, Gmail, Telegram, Discord, HTTP,
 *   Jotform, Typeform, Fillout, Zoom, WhatsApp, ActiveCampaign, …) have NO local
 *   plugin dependency — leave them OUT of this map (they resolve to []).
 * - Flow primitives (condition, filter, delay, iterator, variable) and `wordpress`
 *   (core) also stay out.
 *
 * To override at runtime, hook `zaplane_integration_required_plugins`
 * ( $basenames, $slug ).
 *
 * @package Zaplane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	// --- WooCommerce family ---
	'woocommerce'         => [ 'woocommerce/woocommerce.php' ],
	'woobookings'         => [ 'woocommerce-bookings/woocommerce-bookings.php' ],
	'dokan'               => [ 'dokan-lite/dokan.php' ],
	'funnelkit'           => [ 'funnel-builder/funnel-builder.php' ],
	'wpfunnels'           => [ 'wpfunnels/wpfunnels.php' ],

	// --- eCommerce / memberships ---
	'storeengine'         => [ 'storeengine/storeengine.php' ],
	'surecart'            => [ 'surecart/surecart.php' ],
	'fluentcart'          => [ 'fluent-cart/fluent-cart.php' ],
	'easydigitaldownload' => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
	'memberpress'         => [ 'memberpress/memberpress.php' ],
	'paidmembershippro'   => [ 'paid-memberships-pro/paid-memberships-pro.php' ],
	'suremembers'         => [ 'suremembers/suremembers.php' ],

	// --- CRM / marketing ---
	'fluentcrm'           => [ 'fluent-crm/fluent-crm.php' ],
	'gemcrm'              => [ 'gemcrm/gemcrm.php' ],
	'groundhogg'          => [ 'groundhogg/groundhogg.php' ],

	// --- Email / payments ---
	'fluentsmtp'          => [ 'fluent-smtp/fluent-smtp.php' ],
	'suremail'            => [ 'suremails/suremails.php' ],
	'paymattic'           => [ 'wp-payment-form/wp-payment-form.php' ],

	// --- Forms ---
	'fluentform'          => [ 'fluentform/fluentform.php' ],
	'gravityforms'        => [ 'gravityforms/gravityforms.php' ],
	'ninjaform'           => [ 'ninja-forms/ninja-forms.php' ],
	'formidable'          => [ 'formidable/formidable.php' ],
	'wpforms'             => [ 'wpforms-lite/wpforms.php' ],
	'sureform'            => [ 'sureforms/sureforms.php' ],
	'metform'             => [ 'metform/metform.php' ],
	'weforms'             => [ 'weforms/weforms.php' ],

	// --- LMS ---
	'learndash'           => [ 'sfwd-lms/sfwd_lms.php' ],
	'tutor'               => [ 'tutor/tutor.php' ],
	'lifter'              => [ 'lifterlms/lifterlms.php' ],
	'masterstudy'         => [ 'masterstudy-lms-learning-management-system/masterstudy-lms-learning-management-system.php' ],
	'academy'             => [ 'academy/academy.php' ],

	// --- Page builders / blocks ---
	'elementor'           => [ 'elementor/elementor.php' ],
	'beaverbuilder'       => [ 'beaver-builder-lite-version/fl-builder.php' ],
	'essentialblocks'     => [ 'essential-blocks/essential-blocks.php' ],
	'ablocks'             => [ 'ablocks/ablocks.php' ],
	'kadenceblocks'       => [ 'kadence-blocks/kadence-blocks.php' ],
	'spectra'             => [ 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php' ],
	'coblocks'            => [ 'coblocks/class-coblocks.php' ],

	// --- Content / dev tooling ---
	'advancecustomfields' => [ 'advanced-custom-fields/acf.php' ],
	'metabox'             => [ 'meta-box/meta-box.php' ],
	'jetengine'           => [ 'jet-engine/jet-engine.php' ],
	'wpuserfrontend'      => [ 'wp-user-frontend/wp-user-frontend.php' ],
	'profilebuilder'      => [ 'profile-builder/index.php' ],

	// --- Community / events / gamification ---
	'ultimatemember'      => [ 'ultimate-member/ultimate-member.php' ],
	'buddyboss'           => [ 'buddyboss-platform/bp-loader.php' ],
	'eventscalendar'      => [ 'the-events-calendar/the-events-calendar.php' ],
	'gamipress'           => [ 'gamipress/gamipress.php' ],

	/*
	 * Not yet mapped — add the basename here when you write a recipe for one:
	 *   bitform, arform, avadaform, bookingcalendarcontactform, divi (theme).
	 * Verify the value before relying on auto-activation; a wrong basename makes
	 * the activate step fail. Installed-plugin basenames can be confirmed with
	 * `wp plugin list --fields=name,file`.
	 */
];
