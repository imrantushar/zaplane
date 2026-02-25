<?php

return [
    // tool
    'condition' => ['file' => 'condition.php', 'class' => \Zaplane\Integrations\Condition::class],
    'filter' => ['file' => 'filter.php', 'class' => \Zaplane\Integrations\Filter::class],
    'delay' => ['file' => 'delay.php', 'class' => \Zaplane\Integrations\Delay::class],
    'iterator' => ['file' => 'iterator.php', 'class' => \Zaplane\Integrations\Iterator::class],
    // apps
    'wordpress' => ['file' => 'wordpress.php', 'class' => \Zaplane\Integrations\Wordpress::class],
    'surecart'       => ['file' => 'surecart.php', 'class' => \Zaplane\Integrations\Surecart::class],
    'slack'     => ['file' => 'slack.php', 'class' => \Zaplane\Integrations\Slack::class],
    'trello'    => ['file' => 'trello.php', 'class' => \Zaplane\Integrations\Trello::class],
    'stripe'    => ['file' => 'stripe.php', 'class' => \Zaplane\Integrations\Stripe::class],
    'Http' => ['file' => 'http.php', 'class' => \Zaplane\Integrations\Http::class],
    'variable' => ['file' => 'variable.php', 'class' => \Zaplane\Integrations\Variable::class],
    'webhook' => ['file' => 'webhook.php', 'class' => \Zaplane\Integrations\Webhook::class],
    'storeengine' => ['file' => 'storeengine.php', 'class' => \Zaplane\Integrations\Storeengine::class],
    'essentialblocks' => ['file' => 'essentialblocks.php', 'class' => \Zaplane\Integrations\Essentialblocks::class],    
    'coblocks' => ['file' => 'coblocks.php', 'class' => \Zaplane\Integrations\Coblocks::class],
    'spectra' => ['file' => 'spectra.php', 'class' => \Zaplane\Integrations\Spectra::class],
    'beaverbuilder' => ['file' => 'beaverbuilder.php', 'class' => \Zaplane\Integrations\Beaverbuilder::class],
    'metform' => ['file' => 'metform.php', 'class' => \Zaplane\Integrations\Metform::class],
    'easydigitaldownload' => ['file' => 'easydigitaldownload.php', 'class' => \Zaplane\Integrations\Easydigitaldownload::class],
    'lifter' => ['file' => 'lifter.php', 'class' => \Zaplane\Integrations\Lifter::class],
];
