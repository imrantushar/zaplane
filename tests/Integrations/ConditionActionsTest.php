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

    protected function getActionTests(): array
    {
        return [
            'if' => ['expression' => '{{value}}'],
        ];
    }

    protected function setupMockData(): void
    {
        // No mock data needed for condition
    }

    /**
     * @test
     */
    public function condition_true_returns_true_port(): void
    {
        $node = $this->makeActionNode('if', ['expression' => '{{value}}']);
        $result = Condition::execute_node($node, ['value' => true]);

        $this->assertEquals('true', $result['port']);
    }

    /**
     * @test
     */
    public function condition_false_returns_false_port(): void
    {
        $node = $this->makeActionNode('if', ['expression' => '{{value}}']);
        $result = Condition::execute_node($node, ['value' => false]);

        $this->assertEquals('false', $result['port']);
    }

    /**
     * @test
     */
    public function condition_with_comparison(): void
    {
        $node = $this->makeActionNode('if', ['expression' => '{{count > 5}}']);

        $resultTrue = Condition::execute_node($node, ['count' => 10]);
        $this->assertEquals('true', $resultTrue['port']);

        $resultFalse = Condition::execute_node($node, ['count' => 3]);
        $this->assertEquals('false', $resultFalse['port']);
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
