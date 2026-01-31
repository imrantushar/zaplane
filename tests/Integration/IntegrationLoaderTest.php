<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Core\IntegrationLoader;

class IntegrationLoaderTest extends TestCase
{
    /**
     * Test that IntegrationLoader initializes correctly
     */
    public function testLoaderInitializes(): void
    {
        $this->assertIsArray(IntegrationLoader::getAllSlugs());
    }

    /**
     * Test that getAllSlugs returns registered integrations
     */
    public function testGetAllSlugsReturnsIntegrations(): void
    {
        $slugs = IntegrationLoader::getAllSlugs();

        $this->assertIsArray($slugs);
        // Should have at least some integrations registered
        $this->assertNotEmpty($slugs);
    }

    /**
     * Test that getRegistry returns metadata
     */
    public function testGetRegistryReturnsMetadata(): void
    {
        $registry = IntegrationLoader::getRegistry();

        $this->assertIsArray($registry);

        // Each entry should have 'file' and 'class' keys
        foreach ($registry as $slug => $meta) {
            $this->assertIsString($slug);
            $this->assertArrayHasKey('class', $meta);
        }
    }

    /**
     * Test that has() correctly identifies registered integrations
     */
    public function testHasCorrectlyIdentifiesIntegrations(): void
    {
        $slugs = IntegrationLoader::getAllSlugs();

        if (!empty($slugs)) {
            $firstSlug = $slugs[0];
            $this->assertTrue(IntegrationLoader::has($firstSlug));
        }

        $this->assertFalse(IntegrationLoader::has('nonexistent_integration_xyz'));
    }

    /**
     * Test that get() returns integration instance
     */
    public function testGetReturnsIntegrationInstance(): void
    {
        $slugs = IntegrationLoader::getAllSlugs();

        if (!empty($slugs)) {
            $firstSlug   = $slugs[0];
            $integration = IntegrationLoader::get($firstSlug);

            $this->assertNotNull($integration);
            $this->assertIsObject($integration);
        }
    }

    /**
     * Test that get() returns null for non-existent integration
     */
    public function testGetReturnsNullForNonExistent(): void
    {
        $integration = IntegrationLoader::get('nonexistent_integration_xyz');
        $this->assertNull($integration);
    }

    /**
     * Test that getTriggers returns triggers for an integration
     */
    public function testGetTriggersReturnsArray(): void
    {
        $slugs = IntegrationLoader::getAllSlugs();

        if (!empty($slugs)) {
            $triggers = IntegrationLoader::getTriggers($slugs[0]);
            $this->assertIsArray($triggers);
        }
    }

    /**
     * Test that getActions returns actions for an integration
     */
    public function testGetActionsReturnsArray(): void
    {
        $slugs = IntegrationLoader::getAllSlugs();

        if (!empty($slugs)) {
            $actions = IntegrationLoader::getActions($slugs[0]);
            $this->assertIsArray($actions);
        }
    }

    /**
     * Test that isModular correctly identifies modular integrations
     */
    public function testIsModularIdentifiesModularIntegrations(): void
    {
        // Check if wordpress is modular (it should be after migration)
        if (IntegrationLoader::has('wordpress')) {
            $isModular = IntegrationLoader::isModular('wordpress');
            $this->assertTrue($isModular);
        }
    }

    /**
     * Test that getTriggerClass returns class for modular triggers
     */
    public function testGetTriggerClassReturnsClassForModularTriggers(): void
    {
        if (IntegrationLoader::has('wordpress') && IntegrationLoader::isModular('wordpress')) {
            $triggers = IntegrationLoader::getTriggers('wordpress');

            if (!empty($triggers)) {
                $firstKey    = array_key_first($triggers);
                $triggerClass = IntegrationLoader::getTriggerClass('wordpress', $firstKey);

                $this->assertNotNull($triggerClass);
                $this->assertIsString($triggerClass);
            }
        }
    }

    /**
     * Test that getActionClass returns class for modular actions
     */
    public function testGetActionClassReturnsClassForModularActions(): void
    {
        if (IntegrationLoader::has('wordpress') && IntegrationLoader::isModular('wordpress')) {
            $actions = IntegrationLoader::getActions('wordpress');

            if (!empty($actions)) {
                $firstKey    = array_key_first($actions);
                $actionClass = IntegrationLoader::getActionClass('wordpress', $firstKey);

                $this->assertNotNull($actionClass);
                $this->assertIsString($actionClass);
            }
        }
    }

    /**
     * Test that getTriggerConfigSchema returns schema
     */
    public function testGetTriggerConfigSchemaReturnsSchema(): void
    {
        if (IntegrationLoader::has('wordpress')) {
            $triggers = IntegrationLoader::getTriggers('wordpress');

            if (!empty($triggers)) {
                $firstKey = array_key_first($triggers);
                $schema   = IntegrationLoader::getTriggerConfigSchema('wordpress', $firstKey);

                $this->assertIsArray($schema);
            }
        }
    }

    /**
     * Test that getActionConfigSchema returns schema
     */
    public function testGetActionConfigSchemaReturnsSchema(): void
    {
        if (IntegrationLoader::has('wordpress')) {
            $actions = IntegrationLoader::getActions('wordpress');

            if (!empty($actions)) {
                $firstKey = array_key_first($actions);
                $schema   = IntegrationLoader::getActionConfigSchema('wordpress', $firstKey);

                $this->assertIsArray($schema);
            }
        }
    }

    /**
     * Test WordPress triggers are loaded correctly
     */
    public function testWordpressTriggersAreLoaded(): void
    {
        if (!IntegrationLoader::has('wordpress')) {
            $this->markTestSkipped('WordPress integration not available');
        }

        $triggers = IntegrationLoader::getTriggers('wordpress');

        $this->assertNotEmpty($triggers);

        // Check for some common triggers
        $expectedTriggers = [
            'publish_post',
            'user_register',
            'wp_insert_comment',
            'save_post',
        ];

        foreach ($expectedTriggers as $expectedTrigger) {
            $this->assertArrayHasKey($expectedTrigger, $triggers, "Missing trigger: {$expectedTrigger}");
        }
    }

    /**
     * Test WordPress actions are loaded correctly
     */
    public function testWordpressActionsAreLoaded(): void
    {
        if (!IntegrationLoader::has('wordpress')) {
            $this->markTestSkipped('WordPress integration not available');
        }

        $actions = IntegrationLoader::getActions('wordpress');

        $this->assertNotEmpty($actions);

        // Check for some common actions
        $expectedActions = [
            'create_post',
            'update_post',
            'create_user',
            'create_comment',
        ];

        foreach ($expectedActions as $expectedAction) {
            $this->assertArrayHasKey($expectedAction, $actions, "Missing action: {$expectedAction}");
        }
    }

    /**
     * Test trigger definitions have required fields
     */
    public function testTriggerDefinitionsHaveRequiredFields(): void
    {
        if (!IntegrationLoader::has('wordpress')) {
            $this->markTestSkipped('WordPress integration not available');
        }

        $triggers = IntegrationLoader::getTriggers('wordpress');

        foreach ($triggers as $key => $trigger) {
            $this->assertArrayHasKey('label', $trigger, "Trigger {$key} missing 'label'");
            $this->assertArrayHasKey('hook', $trigger, "Trigger {$key} missing 'hook'");
        }
    }

    /**
     * Test action definitions have required fields
     */
    public function testActionDefinitionsHaveRequiredFields(): void
    {
        if (!IntegrationLoader::has('wordpress')) {
            $this->markTestSkipped('WordPress integration not available');
        }

        $actions = IntegrationLoader::getActions('wordpress');

        foreach ($actions as $key => $action) {
            $this->assertArrayHasKey('label', $action, "Action {$key} missing 'label'");
        }
    }

    /**
     * Test modular triggers have _class field
     */
    public function testModularTriggersHaveClassField(): void
    {
        if (!IntegrationLoader::has('wordpress') || !IntegrationLoader::isModular('wordpress')) {
            $this->markTestSkipped('Modular WordPress integration not available');
        }

        $triggers = IntegrationLoader::getTriggers('wordpress');

        foreach ($triggers as $key => $trigger) {
            if (isset($trigger['_class'])) {
                $this->assertTrue(class_exists($trigger['_class']), "Class {$trigger['_class']} does not exist");
            }
        }
    }

    /**
     * Test modular actions have _class field
     */
    public function testModularActionsHaveClassField(): void
    {
        if (!IntegrationLoader::has('wordpress') || !IntegrationLoader::isModular('wordpress')) {
            $this->markTestSkipped('Modular WordPress integration not available');
        }

        $actions = IntegrationLoader::getActions('wordpress');

        foreach ($actions as $key => $action) {
            if (isset($action['_class'])) {
                $this->assertTrue(class_exists($action['_class']), "Class {$action['_class']} does not exist");
            }
        }
    }

    /**
     * Test all() loads all integrations
     */
    public function testAllLoadsAllIntegrations(): void
    {
        $all = IntegrationLoader::all();

        $this->assertIsArray($all);
        $this->assertNotEmpty($all);

        foreach ($all as $slug => $instance) {
            $this->assertIsString($slug);
            $this->assertIsObject($instance);
        }
    }

    /**
     * Test instances are cached
     */
    public function testInstancesAreCached(): void
    {
        $slugs = IntegrationLoader::getAllSlugs();

        if (!empty($slugs)) {
            $firstSlug = $slugs[0];

            $instance1 = IntegrationLoader::get($firstSlug);
            $instance2 = IntegrationLoader::get($firstSlug);

            $this->assertSame($instance1, $instance2);
        }
    }

    /**
     * Test Slack integration is modular
     */
    public function testSlackIntegrationIsModular(): void
    {
        if (!IntegrationLoader::has('slack')) {
            $this->markTestSkipped('Slack integration not available');
        }

        $this->assertTrue(IntegrationLoader::isModular('slack'));
    }

    /**
     * Test StoreEngine integration is modular
     */
    public function testStoreengineIntegrationIsModular(): void
    {
        if (!IntegrationLoader::has('storeengine')) {
            $this->markTestSkipped('StoreEngine integration not available');
        }

        $this->assertTrue(IntegrationLoader::isModular('storeengine'));
    }
}
