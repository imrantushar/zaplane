<?php

return [
    'wordpress' => ['file' => 'wordpress.php', 'class' => \Zaplane\Integration\Wordpress::class],
    'woo'       => ['file' => 'woo.php', 'class' => \Zaplane\Integration\Woo::class],
    'slack'     => ['file' => 'slack.php', 'class' => \Zaplane\Integration\Slack::class],
    'gmail'     => ['file' => 'gmail.php', 'class' => \Zaplane\Integration\Gmail::class],
    'trello'    => ['file' => 'trello.php', 'class' => \Zaplane\Integration\Trello::class],
    'stripe'    => ['file' => 'stripe.php', 'class' => \Zaplane\Integration\Stripe::class],
    'mailerlite'=> ['file' => 'mailerlite.php', 'class' => \Zaplane\Integration\Mailerlite::class],
];
