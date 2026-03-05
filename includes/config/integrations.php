<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'registry' => [
        // tools
        'condition'            => [ 'file' => 'condition.php',            'class' => \Zaplane\Integrations\Condition::class ],
        'filter'               => [ 'file' => 'filter.php',               'class' => \Zaplane\Integrations\Filter::class ],
        'delay'                => [ 'file' => 'delay.php',                'class' => \Zaplane\Integrations\Delay::class ],
        'iterator'             => [ 'file' => 'iterator.php',             'class' => \Zaplane\Integrations\Iterator::class ],
        'variable'             => [ 'file' => 'variable.php',             'class' => \Zaplane\Integrations\Variable::class ],
        // apps
        'wordpress'            => [ 'file' => 'wordpress.php',            'class' => \Zaplane\Integrations\Wordpress::class ],
        'slack'                => [ 'file' => 'slack.php',                'class' => \Zaplane\Integrations\Slack::class ],
        'trello'               => [ 'file' => 'trello.php',               'class' => \Zaplane\Integrations\Trello::class ],
        'stripe'               => [ 'file' => 'stripe.php',               'class' => \Zaplane\Integrations\Stripe::class ],
        'mailchimp'            => [ 'file' => 'mailchimp.php',            'class' => \Zaplane\Integrations\Mailchimp::class ],
        'surecart'             => [ 'file' => 'surecart.php',             'class' => \Zaplane\Integrations\Surecart::class ],
        'storeengine'          => [ 'file' => 'storeengine.php',          'class' => \Zaplane\Integrations\Storeengine::class ],
        'Http'                 => [ 'file' => 'http.php',                 'class' => \Zaplane\Integrations\Http::class ],
        'webhook'              => [ 'file' => 'webhook.php',              'class' => \Zaplane\Integrations\Webhook::class ],
        'essentialblocks'      => [ 'file' => 'essentialblocks.php',      'class' => \Zaplane\Integrations\Essentialblocks::class ],
        'coblocks'             => [ 'file' => 'coblocks.php',             'class' => \Zaplane\Integrations\Coblocks::class ],
        'spectra'              => [ 'file' => 'spectra.php',              'class' => \Zaplane\Integrations\Spectra::class ],
        'beaverbuilder'        => [ 'file' => 'beaverbuilder.php',        'class' => \Zaplane\Integrations\Beaverbuilder::class ],
        'metform'              => [ 'file' => 'metform.php',              'class' => \Zaplane\Integrations\Metform::class ],
        'easydigitaldownload'  => [ 'file' => 'easy-digital-download.php','class' => \Zaplane\Integrations\EasyDigitalDownload::class ],
        'tutor'                => ['file' => 'tutor.php',                  'class' => \Zaplane\Integrations\Tutor::class],
        'groundhogg'           => ['file' => 'groundhogg.php',             'class' => \Zaplane\Integrations\Groundhogg::class],
        'academy'              => ['file' => 'academy.php',                'class' => \Zaplane\Integrations\Academy::class],
        'bricks'               => ['file' => 'bricks.php',                 'class' => \Zaplane\Integrations\Bricks::class],
        'bricks'               => ['file' => 'bricks.php',                 'class' => \Zaplane\Integrations\Bricks::class],
        'elementor'            => ['file' => 'elementor.php',              'class' => \Zaplane\Integrations\Elementor::class],
        'masterstudy'            => ['file' => 'masterstudy.php',              'class' => \Zaplane\Integrations\Masterstudy::class],
    ],
];
