<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\ContactForm;

class ContactFormTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return ContactForm::class;
    }

    /**
     * form_submitted trigger
     */
    public function test_form_submitted(): void
    {
        $node = $this->makeTriggerNode('form_submitted', [
            'form_id' => '20',
        ]);

        $form = new \WPCF7_ContactForm(20);

        $submission = new \WPCF7_Submission($form, [
            'posted_data' => [
                'your-name' => 'John Doe',
                'your-email' => 'john@example.com',
                'your-message' => 'Test message',
            ],
        ]);

        $result = ContactForm::resolve_trigger(
            $node,
            [ $form, false, $submission ]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(20, $result['form']['form_id']);
    }

    /**
     * form_created trigger
     */
    public function test_form_created(): void
    {
        $node = $this->makeTriggerNode('form_created');

        $form = new \WPCF7_ContactForm(20);

        $result = ContactForm::resolve_trigger(
            $node,
            [ $form ]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('created', $result['form']['action']);
        $this->assertEquals(20, $result['form']['form_id']);
    }

    /**
     * form_updated trigger
     */
    public function test_form_updated(): void
    {
        $node = $this->makeTriggerNode('form_updated');

        $form = new \WPCF7_ContactForm(30);

        $result = ContactForm::resolve_trigger(
            $node,
            [ $form ]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('updated', $result['form']['action']);
        $this->assertEquals(30, $result['form']['form_id']);
    }
}