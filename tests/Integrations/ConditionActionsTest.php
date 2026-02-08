<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Condition;

/**
 * Condition Tool - Action Tests
 */
class ConditionActionsTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Condition::class;
    }

    protected function getActions(): array
    {
        return [
            'if',
        ];
    }

    /**
     * @test
     */
    public function output_ports_include_true_and_false(): void
    {
        $ports = Condition::get_output_ports();

        $this->assertContains('true', $ports);
        $this->assertContains('false', $ports);
    }
}
