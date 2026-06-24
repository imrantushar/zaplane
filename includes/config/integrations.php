<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$registry = [
	'condition'           => [
		'file'  => 'condition.php',
		'class' => \Zaplane\Integrations\Condition::class,
	],
	'filter'              => [
		'file'  => 'filter.php',
		'class' => \Zaplane\Integrations\Filter::class,
	],
	'delay'               => [
		'file'  => 'delay.php',
		'class' => \Zaplane\Integrations\Delay::class,
	],
	'iterator'            => [
		'file'  => 'iterator.php',
		'class' => \Zaplane\Integrations\Iterator::class,
	],
	'variable'            => [
		'file'  => 'variable.php',
		'class' => \Zaplane\Integrations\Variable::class,
	],
	'dokan'               => [
		'file'  => 'dokan.php',
		'class' => \Zaplane\Integrations\Dokan::class,
	],
	'funnelkit'           => [
		'file'  => 'funnelkit.php',
		'class' => \Zaplane\Integrations\Funnelkit::class,
	],
	'wordpress'           => [
		'file'  => 'wordpress.php',
		'class' => \Zaplane\Integrations\Wordpress::class,
	],
	'woocommerce'         => [
		'file'  => 'woocommerce.php',
		'class' => \Zaplane\Integrations\Woocommerce::class,
	],
	'woobookings'         => [
		'file'  => 'woo-bookings.php',
		'class' => \Zaplane\Integrations\WooBookings::class,
	],
	'slack'               => [
		'file'  => 'slack.php',
		'class' => \Zaplane\Integrations\Slack::class,
	],
	'mailchimp'           => [
		'file'  => 'mailchimp.php',
		'class' => \Zaplane\Integrations\Mailchimp::class,
	],
	'activecampaign'      => [
		'file'  => 'active-campaign.php',
		'class' => \Zaplane\Integrations\ActiveCampaign::class,
	],
	'surecart'            => [
		'file'  => 'surecart.php',
		'class' => \Zaplane\Integrations\Surecart::class,
	],
	'storeengine'         => [
		'file'  => 'storeengine.php',
		'class' => \Zaplane\Integrations\Storeengine::class,
	],
	'http'                => [
		'file'  => 'http.php',
		'class' => \Zaplane\Integrations\Http::class,
	],
	'learndash'           => [
		'file'  => 'learndash.php',
		'class' => \Zaplane\Integrations\Learndash::class,
	],
	'memberpress'         => [
		'file'  => 'memberpress.php',
		'class' => \Zaplane\Integrations\Memberpress::class,
	],
	'fluentform'          => [
		'file'  => 'fluent-form.php',
		'class' => \Zaplane\Integrations\FluentForm::class,
	],
	'fluentcrm'           => [
		'file'  => 'fluent-crm.php',
		'class' => \Zaplane\Integrations\FluentCrm::class,
	],
	'fluentsmtp'          => [
		'file'  => 'fluent-smtp.php',
		'class' => \Zaplane\Integrations\FluentSmtp::class,
	],
	'fluentcart'          => [
		'file'  => 'fluent-cart.php',
		'class' => \Zaplane\Integrations\FluentCart::class,
	],
	'gravityforms'        => [
		'file'  => 'gravityforms.php',
		'class' => \Zaplane\Integrations\Gravityforms::class,
	],
	'ninjaform'           => [
		'file'  => 'ninjaform.php',
		'class' => \Zaplane\Integrations\Ninjaform::class,
	],
	'formidable'          => [
		'file'  => 'formidable.php',
		'class' => \Zaplane\Integrations\Formidable::class,
	],
	'wpforms'             => [
		'file'  => 'wpforms.php',
		'class' => \Zaplane\Integrations\Wpforms::class,
	],
	'jotform'        => [
		'file'  => 'jotform.php',
		'class' => \Zaplane\Integrations\Jotform::class
	],
	'contact-form-7'      => [
		'file'  => 'contact-form.php',
		'class' => \Zaplane\Integrations\ContactForm::class,
	],
	'divi'                => [
		'file'  => 'divi.php',
		'class' => \Zaplane\Integrations\Divi::class,
	],
	'essentialblocks'     => [
		'file'  => 'essentialblocks.php',
		'class' => \Zaplane\Integrations\Essentialblocks::class,
	],
	'coblocks'            => [
		'file'  => 'coblocks.php',
		'class' => \Zaplane\Integrations\Coblocks::class,
	],
	'spectra'             => [
		'file'  => 'spectra.php',
		'class' => \Zaplane\Integrations\Spectra::class,
	],
	'beaverbuilder'       => [
		'file'  => 'beaverbuilder.php',
		'class' => \Zaplane\Integrations\Beaverbuilder::class,
	],
	'metform'             => [
		'file'  => 'metform.php',
		'class' => \Zaplane\Integrations\Metform::class,
	],
	'easydigitaldownload' => [
		'file'  => 'easy-digital-download.php',
		'class' => \Zaplane\Integrations\EasyDigitalDownload::class,
	],
	'tutor'               => [
		'file'  => 'tutor.php',
		'class' => \Zaplane\Integrations\Tutor::class,
	],
	'groundhogg'          => [
		'file'  => 'groundhogg.php',
		'class' => \Zaplane\Integrations\Groundhogg::class,
	],
	'elementor'           => [
		'file'  => 'elementor.php',
		'class' => \Zaplane\Integrations\Elementor::class,
	],
	'masterstudy'         => [
		'file'  => 'masterstudy.php',
		'class' => \Zaplane\Integrations\Masterstudy::class,
	],
	'academy'             => [
		'file'  => 'academy.php',
		'class' => \Zaplane\Integrations\Academy::class,
	],
	'gemcrm'              => [
		'file'  => 'gemcrm.php',
		'class' => \Zaplane\Integrations\Gemcrm::class,
	],
	'lifter'              => [
		'file'  => 'lifter.php',
		'class' => \Zaplane\Integrations\Lifter::class,
	],
	'jetengine'           => [
		'file'  => 'jetengine.php',
		'class' => \Zaplane\Integrations\Jetengine::class,
	],
	'wpuserfrontend'      => [
		'file'  => 'wpuserfrontend.php',
		'class' => \Zaplane\Integrations\Wpuserfrontend::class,
	],
	'wpfunnels'           => [
		'file'  => 'wpfunnels.php',
		'class' => \Zaplane\Integrations\Wpfunnels::class,
	],
	'metabox'             => [
		'file'  => 'metabox.php',
		'class' => \Zaplane\Integrations\Metabox::class,
	],
	'advancecustomfields' => [
		'file'  => 'advance-custom-fields.php',
		'class' => \Zaplane\Integrations\AdvanceCustomFields::class,
	],
	'profilebuilder'      => [
		'file'  => 'profilebuilder.php',
		'class' => \Zaplane\Integrations\Profilebuilder::class,
	],
	'kadenceblocks'       => [
		'file'  => 'kadenceblocks.php',
		'class' => \Zaplane\Integrations\Kadenceblocks::class,
	],
	'eventscalendar'      => [
		'file'  => 'eventscalendar.php',
		'class' => \Zaplane\Integrations\Eventscalendar::class,
	],
	'buddyboss'           => [
		'file'  => 'buddyboss.php',
		'class' => \Zaplane\Integrations\Buddyboss::class,
	],
	'ultimatemember'      => [
		'file'  => 'ultimatemember.php',
		'class' => \Zaplane\Integrations\Ultimatemember::class,
	],
	'bitform'             => [
		'file'  => 'bitform.php',
		'class' => \Zaplane\Integrations\Bitform::class,
	],
	'sureform'            => [
		'file'  => 'sureform.php',
		'class' => \Zaplane\Integrations\Sureform::class,
	],
	'suremail'            => [
		'file'  => 'suremail.php',
		'class' => \Zaplane\Integrations\Suremail::class,
	],
	'paymattic'           => [
		'file'  => 'paymattic.php',
		'class' => \Zaplane\Integrations\Paymattic::class,
	],
	'weforms'             => [
		'file'  => 'weforms.php',
		'class' => \Zaplane\Integrations\Weforms::class,
	],
	'suremembers'         => [
		'file'  => 'suremembers.php',
		'class' => \Zaplane\Integrations\Suremembers::class,
	],
	'arform'              => [
		'file'  => 'arform.php',
		'class' => \Zaplane\Integrations\ARForm::class,
	],
	'paidmembershippro'   => [
		'file'  => 'paidmembershippro.php',
		'class' => \Zaplane\Integrations\Paidmembershippro::class,
	],
	'whatsapp'            => [
		'file'  => 'whatsapp.php',
		'class' => \Zaplane\Integrations\Whatsapp::class,
	],
	'telegram'            => [
		'file'  => 'telegram.php',
		'class' => \Zaplane\Integrations\Telegram::class,
	],
	'gmail'               => [
		'file'  => 'gmail.php',
		'class' => \Zaplane\Integrations\Gmail::class,
	],
	'google-meet'         => [
		'file'  => 'google-meet.php',
		'class' => \Zaplane\Integrations\GoogleMeet::class,
	],
	'zoom'                => [
		'file'  => 'zoom.php',
		'class' => \Zaplane\Integrations\Zoom::class,
	],
	'gamipress'           => [
		'file'  => 'gamipress.php',
		'class' => \Zaplane\Integrations\Gamipress::class,
	],
	'fillout'             => [
		'file'  => 'fillout.php',
		'class' => \Zaplane\Integrations\Fillout::class,
	],
	'typeform'            => [
		'file'  => 'typeform.php',
		'class' => \Zaplane\Integrations\Typeform::class,
	],
	'discord'             => [
		'file'  => 'discord.php',
		'class' => \Zaplane\Integrations\Discord::class,
	],
	'bookingcalendarcontactform'  => [
		'file'  => 'bookingcalendarcontactform.php',
		'class' => \Zaplane\Integrations\Bookingcalendarcontactform::class,
	],
	'avadaform'        => [
		'file'  => 'avadaform.php',
		'class' => \Zaplane\Integrations\Avadaform::class,
	],
	'brevo'        => [
		'file'  => 'brevo.php',
		'class' => \Zaplane\Integrations\Brevo::class
	],
];

$priority = [
	'academy',
	'gemcrm',
	'storeengine',
];

$priorityItems = [];
foreach ( $priority as $key ) {
	if ( isset( $registry[ $key ] ) ) {
		$priorityItems[ $key ] = $registry[ $key ];
	}
}

$remaining = array_diff_key( $registry, $priorityItems );

uksort( $remaining, function ( $a, $b ) {
	return strcasecmp( $a, $b );
} );

$finalRegistry = array_merge( $priorityItems, $remaining );

return [ 'registry' => $finalRegistry ];
