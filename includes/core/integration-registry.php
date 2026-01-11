<?php

return [
    // tool
    'condition' => ['file' => 'condition.php', 'class' => \Zaplane\Integration\Condition::class],
    'delay' => ['file' => 'delay.php', 'class' => \Zaplane\Integration\Delay::class],
    'iterator' => ['file' => 'iterator.php', 'class' => \Zaplane\Integration\Iterator::class],
    // apps
    'wordpress' => ['file' => 'wordpress.php', 'class' => \Zaplane\Integration\Wordpress::class],
    'woo'       => ['file' => 'woo.php', 'class' => \Zaplane\Integration\Woo::class],
    'slack'     => ['file' => 'slack.php', 'class' => \Zaplane\Integration\Slack::class],
    'trello'    => ['file' => 'trello.php', 'class' => \Zaplane\Integration\Trello::class],
    'stripe'    => ['file' => 'stripe.php', 'class' => \Zaplane\Integration\Stripe::class],
    'Http' => ['file' => 'http.php', 'class' => \Zaplane\Integration\Http::class],
    'variable' => ['file' => 'variable.php', 'class' => \Zaplane\Integration\variable::class],
    'webhook' => ['file' => 'webhook.php', 'class' => \Zaplane\Integration\webhook::class],
];
