<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'registry' => [

		'condition'            => [
			'file' => 'condition.php',
			'class' => \Zaplane\Integrations\Condition::class
		],
		'filter'               => [
			'file' => 'filter.php',
			'class' => \Zaplane\Integrations\Filter::class
		],
		'delay'                => [
			'file' => 'delay.php',
			'class' => \Zaplane\Integrations\Delay::class
		],
		'pause'                => [
			'file' => 'pause.php',
			'class' => \Zaplane\Integrations\Pause::class
		],
		'iterator'             => [
			'file' => 'iterator.php',
			'class' => \Zaplane\Integrations\Iterator::class
		],
		'variable'             => [
			'file' => 'variable.php',
			'class' => \Zaplane\Integrations\Variable::class
		],

		'wordpress'            => [
			'file' => 'wordpress.php',
			'class' => \Zaplane\Integrations\Wordpress::class
		],
		'woocommerce'          => [
			'file' => 'woocommerce.php',
			'class' => \Zaplane\Integrations\Woocommerce::class
		],
		'slack'                => [
			'file' => 'Slack.php',
			'class' => \Zaplane\Integrations\Slack::class
		],
		'trello'               => [
			'file' => 'Trello.php',
			'class' => \Zaplane\Integrations\Trello::class
		],
		'stripe'               => [
			'file' => 'Stripe.php',
			'class' => \Zaplane\Integrations\Stripe::class
		],
		'mailchimp'            => [
			'file' => 'mailchimp.php',
			'class' => \Zaplane\Integrations\Mailchimp::class
		],
		'surecart'             => [
			'file' => 'surecart.php',
			'class' => \Zaplane\Integrations\Surecart::class
		],
		'storeengine'          => [
			'file' => 'storeengine.php',
			'class' => \Zaplane\Integrations\Storeengine::class
		],
		'http'                 => [
			'file' => 'http.php',
			'class' => \Zaplane\Integrations\Http::class
		],
		'webhook'              => [
			'file' => 'webhook.php',
			'class' => \Zaplane\Integrations\Webhook::class
		],
		'learndash'            => [
			'file' => 'learndash.php',
			'class' => \Zaplane\Integrations\Learndash::class
		],
		'memberpress'          => [
			'file' => 'memberpress.php',
			'class' => \Zaplane\Integrations\Memberpress::class
		],
		'fluentform'           => [
			'file' => 'fluent-form.php',
			'class' => \Zaplane\Integrations\FluentForm::class
		],
		'fluentcrm'            => [
			'file' => 'fluent-crm.php',
			'class' => \Zaplane\Integrations\FluentCrm::class
		],
		'gravityforms'         => [
			'file' => 'gravityforms.php',
			'class' => \Zaplane\Integrations\Gravityforms::class
		],
		'ninjaform'            => [
			'file' => 'ninjaform.php',
			'class' => \Zaplane\Integrations\Ninjaform::class
		],
		'formidable'           => [
			'file' => 'formidable.php',
			'class' => \Zaplane\Integrations\Formidable::class
		],
		'wpforms'              => [
			'file' => 'wpforms.php',
			'class' => \Zaplane\Integrations\Wpforms::class
		],
		'contact-form-7'       => [
			'file' => 'contact-form.php',
			'class' => \Zaplane\Integrations\ContactForm::class
		],
		'divi'                 => [
			'file' => 'divi.php',
			'class' => \Zaplane\Integrations\Divi::class
		],
		'essentialblocks'      => [
			'file' => 'essentialblocks.php',
			'class' => \Zaplane\Integrations\Essentialblocks::class
		],
		'coblocks'             => [
			'file' => 'coblocks.php',
			'class' => \Zaplane\Integrations\Coblocks::class
		],
		'spectra'              => [
			'file' => 'spectra.php',
			'class' => \Zaplane\Integrations\Spectra::class
		],
		'beaverbuilder'        => [
			'file' => 'beaverbuilder.php',
			'class' => \Zaplane\Integrations\Beaverbuilder::class
		],
		'metform'              => [
			'file' => 'metform.php',
			'class' => \Zaplane\Integrations\Metform::class
		],
		'easydigitaldownload'  => [
			'file' => 'easy-digital-download.php',
			'class' => \Zaplane\Integrations\EasyDigitalDownload::class
		],
		'tutor'                => [
			'file' => 'tutor.php',
			'class' => \Zaplane\Integrations\Tutor::class
		],
		'groundhogg'           => [
			'file' => 'groundhogg.php',
			'class' => \Zaplane\Integrations\Groundhogg::class
		],
		'bricks'               => [
			'file' => 'bricks.php',
			'class' => \Zaplane\Integrations\Bricks::class
		],
		'elementor'            => [
			'file' => 'elementor.php',
			'class' => \Zaplane\Integrations\Elementor::class
		],
		'academy'            => [
			'file' => 'academy.php',
			'class' => \Zaplane\Integrations\Academy::class
		],
		'profilebuilder'            => [
			'file' => 'profilebuilder.php',
			'class' => \Zaplane\Integrations\Profilebuilder::class
		],
	],
];
