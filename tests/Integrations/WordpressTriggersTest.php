<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wordpress;

/**
 * WordPress Integration - Trigger Tests
 */
class WordpressTriggersTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Wordpress::class;
    }

    protected function getTriggers(): array
    {
        return [
            'publish_post',
            'post_updated',
            'save_post',
            'wp_trash_post',
            'delete_post',
            'user_register',
            'profile_update',
            'comment_post',
            'add_attachment',
        ];
    }
}
