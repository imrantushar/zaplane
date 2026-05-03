<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Fillout;

class FilloutTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Fillout::class;
    }

    public function test_form_submitted_success(): void
    {
        $node = $this->makeTriggerNode('form_submitted', [
            'form_id' => 'form123',
        ]);

        $payload = [
            'formId' => 'form123',
            'formName' => 'Test Form',
            'submission' => [
                'submissionId' => 'sub123',
                'submissionTime' => '2024-01-01',
            ],
        ];

        $result = Fillout::resolve_trigger(
            $node,
            [ [ 'payload' => $payload ] ]
        );

        $this->assertIsArray($result);
        $this->assertEquals('form123', $result['fillout_form_id']);
        $this->assertEquals('sub123', $result['fillout_submission_id']);
    }

    public function test_form_submitted_any(): void
    {
        $node = $this->makeTriggerNode('form_submitted', [
            'form_id' => 'any',
        ]);

        $payload = [
            'formId' => 'random_form',
            'submission' => [
                'submissionId' => 'sub999',
            ],
        ];

        $result = Fillout::resolve_trigger(
            $node,
            [ [ 'payload' => $payload ] ]
        );

        $this->assertIsArray($result);
        $this->assertEquals('random_form', $result['fillout_form_id']);
    }

    public function test_form_submitted_wrong_form(): void
    {
        $node = $this->makeTriggerNode('form_submitted', [
            'form_id' => 'form999',
        ]);

        $payload = [
            'formId' => 'form123',
            'submission' => [
                'submissionId' => 'sub123',
            ],
        ];

        $result = Fillout::resolve_trigger(
            $node,
            [ [ 'payload' => $payload ] ]
        );

        $this->assertFalse($result);
    }

    public function test_form_submitted_missing_submission(): void
    {
        $node = $this->makeTriggerNode('form_submitted');

        $payload = [
            'formId' => 'form123',
            'submission' => [],
        ];

        $result = Fillout::resolve_trigger(
            $node,
            [ [ 'payload' => $payload ] ]
        );

        $this->assertFalse($result);
    }

    public function test_form_submitted_deduplication(): void
    {
        $node = $this->makeTriggerNode('form_submitted', [
            'form_id' => 'form123',
        ]);

        $payload = [
            'formId' => 'form123',
            'submission' => [
                'submissionId' => 'sub123',
            ],
        ];

        $first = Fillout::resolve_trigger(
            $node,
            [ [ 'payload' => $payload ] ]
        );

        $second = Fillout::resolve_trigger(
            $node,
            [ [ 'payload' => $payload ] ]
        );

        $this->assertNotFalse($first);
        $this->assertFalse($second);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $ref = new \ReflectionClass(\Zaplane\Integrations\Fillout::class);
        $prop = $ref->getProperty('processed_submissions');
        $prop->setAccessible(true);
        $prop->setValue([]);
    }
}