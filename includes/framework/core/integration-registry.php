<?php

return [
    // tool
    'condition' => ['file' => 'condition.php', 'class' => \Zaplane\Integrations\Condition::class],
    'filter' => ['file' => 'filter.php', 'class' => \Zaplane\Integrations\Filter::class],
    'delay' => ['file' => 'delay.php', 'class' => \Zaplane\Integrations\Delay::class],
    'iterator' => ['file' => 'iterator.php', 'class' => \Zaplane\Integrations\Iterator::class],
    // apps
    'wordpress' => ['file' => 'wordpress.php', 'class' => \Zaplane\Integrations\Wordpress::class],
    'woo'       => ['file' => 'woo.php', 'class' => \Zaplane\Integrations\Woo::class],
    'slack'     => ['file' => 'slack.php', 'class' => \Zaplane\Integrations\Slack::class],
    'trello'    => ['file' => 'trello.php', 'class' => \Zaplane\Integrations\Trello::class],
    'stripe'    => ['file' => 'stripe.php', 'class' => \Zaplane\Integrations\Stripe::class],
    'Http' => ['file' => 'http.php', 'class' => \Zaplane\Integrations\Http::class],
    'variable' => ['file' => 'variable.php', 'class' => \Zaplane\Integrations\Variable::class],
    'webhook' => ['file' => 'webhook.php', 'class' => \Zaplane\Integrations\Webhook::class],
    'storeengine' => ['file' => 'storeengine.php', 'class' => \Zaplane\Integrations\Storeengine::class],
    'metabox' => ['file' => 'metabox.php', 'class' => \Zaplane\Integrations\Metabox::class],
];
