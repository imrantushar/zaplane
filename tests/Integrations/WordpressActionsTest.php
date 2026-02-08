<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wordpress;

/**
 * WordPress Integration - Action Tests
 */
class WordpressActionsTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Wordpress::class;
    }

    protected function getActions(): array
    {
        return [
            'create_post',
            'update_post',
            'update_title',
            'trash_post',
            'delete_post',
            'create_user',
            'update_user',
            'delete_user',
            'create_comment',
            'delete_comment',
        ];
    }
}
