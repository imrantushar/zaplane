<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Slack;

/**
 * Slack Integration - Action Tests
 */
class SlackActionsTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Slack::class;
    }

    protected function getActions(): array
    {
        return [
            'send_message',
        ];
    }
}
