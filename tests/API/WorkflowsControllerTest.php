<?php

namespace Zaplane\Tests\API;

use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPDBMock;
use Zaplane\Modules\API\WorkflowsController;

class WorkflowsControllerTest extends TestCase
{
    private WorkflowsController $controller;
    private WPDBMock $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new WPDBMock();
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->controller = new WorkflowsController();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function testPermissionsCheckReturnsFalseForUnauthorizedUser(): void
    {
        $result = $this->controller->permissions_check();
        $this->assertFalse($result);
    }

    public function testGetWorkflowItemsReturnsEmptyArrayWhenNoWorkflows(): void
    {
        $this->wpdb->tables['results'] = [];

        $response = $this->controller->get_workflow_items();
        $data = $response->get_data();

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testGetWorkflowItemsReturnsWorkflows(): void
    {
        $this->wpdb->tables['results'] = [
            (object) ['id' => 1, 'title' => 'Workflow 1', 'status' => 'active'],
            (object) ['id' => 2, 'title' => 'Workflow 2', 'status' => 'draft'],
        ];

        $response = $this->controller->get_workflow_items();
        $data = $response->get_data();

        $this->assertCount(2, $data);
        $this->assertEquals('Workflow 1', $data[0]->title);
    }

    public function testGetWorkflowItemReturnsNull(): void
    {
        $this->wpdb->tables['row'] = null;

        $request = new MockRequest(['id' => 999]);
        $response = $this->controller->get_workflow_item($request);
        $data = $response->get_data();

        $this->assertNull($data);
    }

    public function testGetWorkflowItemReturnsWorkflow(): void
    {
        $this->wpdb->tables['row'] = (object) [
            'id' => 1,
            'title' => 'Test Workflow',
            'status' => 'active',
        ];

        $request = new MockRequest(['id' => 1]);
        $response = $this->controller->get_workflow_item($request);
        $data = $response->get_data();

        $this->assertEquals(1, $data->id);
        $this->assertEquals('Test Workflow', $data->title);
    }

    public function testCreateItemReturnsInsertId(): void
    {
        $this->wpdb->tables['next_insert_id'] = 42;

        $request = new MockRequest([
            'title' => 'New Workflow',
            'name' => 'new-workflow',
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(42, $data['id']);
    }

    public function testDeleteItemReturnsSuccess(): void
    {
        $this->wpdb->tables['delete_result'] = 1;

        $request = new MockRequest(['id' => 1]);
        $response = $this->controller->delete_item($request);
        $data = $response->get_data();

        $this->assertTrue($data['deleted']);
    }

    public function testListVersionsReturnsVersions(): void
    {
        $this->wpdb->tables['results'] = [
            (object) ['id' => 1, 'graph_hash' => 'abc123', 'is_active' => 1],
            (object) ['id' => 2, 'graph_hash' => 'def456', 'is_active' => 0],
        ];

        $request = new MockRequest(['id' => 1]);
        $response = $this->controller->list_versions($request);
        $data = $response->get_data();

        $this->assertCount(2, $data);
    }

    public function testGetVersionReturnsNotFoundForMissingVersion(): void
    {
        $this->wpdb->tables['row'] = null;

        $request = new MockRequest(['id' => 1, 'version_id' => 999]);
        $response = $this->controller->get_version($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    public function testGetVersionReturnsVersionData(): void
    {
        $this->wpdb->tables['row'] = (object) [
            'id' => 5,
            'graph_json' => '{"nodes":[],"edges":[]}',
            'graph_hash' => 'hash123',
            'is_active' => 1,
        ];

        $request = new MockRequest(['id' => 1, 'version_id' => 5]);
        $response = $this->controller->get_version($request);
        $data = $response->get_data();

        $this->assertEquals(5, $data['id']);
        $this->assertTrue($data['is_active']);
        $this->assertIsArray($data['graph']);
    }

    public function testGetGraphReturnsNotFoundForMissingWorkflow(): void
    {
        $this->wpdb->tables['row'] = null;

        $request = new MockRequest(['id' => 999]);
        $response = $this->controller->get_graph($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    public function testGetGraphReturnsWorkflowWithGraph(): void
    {
        $this->wpdb->tables['row'] = [
            'workflow_id' => 1,
            'title' => 'Test',
            'name' => 'test',
            'status' => 'active',
            'user_id' => 1,
            'version_id' => 10,
            'graph_hash' => 'hash',
            'graph_json' => '{"nodes":[{"id":"1"}],"edges":[]}',
            'version_created_at' => '2024-01-01',
        ];

        $request = new MockRequest(['id' => 1]);
        $response = $this->controller->get_graph($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('workflow', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('graph', $data);
        $this->assertCount(1, $data['graph']['nodes']);
    }

    public function testGetGraphReturnsEmptyGraphWhenNoVersion(): void
    {
        $this->wpdb->tables['row'] = [
            'workflow_id' => 1,
            'title' => 'Test',
            'name' => 'test',
            'status' => 'draft',
            'user_id' => 1,
            'version_id' => null,
            'graph_hash' => null,
            'graph_json' => null,
            'version_created_at' => null,
        ];

        $request = new MockRequest(['id' => 1]);
        $response = $this->controller->get_graph($request);
        $data = $response->get_data();

        $this->assertEmpty($data['graph']['nodes']);
        $this->assertEmpty($data['graph']['edges']);
    }

    public function testActivateVersionReturnsNotFoundForInvalidVersion(): void
    {
        $this->wpdb->tables['var'] = null;

        $request = new MockRequest(['id' => 1, 'version_id' => 999]);
        $response = $this->controller->activate_version($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
    }

    public function testActivateVersionSucceeds(): void
    {
        $this->wpdb->tables['var'] = 5;
        $this->wpdb->tables['update_result'] = 1;

        $request = new MockRequest(['id' => 1, 'version_id' => 5]);
        $response = $this->controller->activate_version($request);
        $data = $response->get_data();

        $this->assertEquals(1, $data['workflow_id']);
        $this->assertEquals(5, $data['active_version']);
    }

    public function testGetRunsReturnsRuns(): void
    {
        $this->wpdb->tables['var'] = 'hash123';
        $this->wpdb->tables['results'] = [
            ['id' => 1, 'status' => 'completed', 'started_at' => '2024-01-01'],
        ];

        $request = new MockRequest(['id' => 1]);
        $response = $this->controller->get_runs($request);
        $data = $response->get_data();

        $this->assertCount(1, $data);
        $this->assertEquals('completed', $data[0]['status']);
    }
}

class MockRequest implements \ArrayAccess
{
    private array $params;
    private array $jsonParams;

    public function __construct(array $params = [], array $jsonParams = [])
    {
        $this->params = $params;
        $this->jsonParams = $jsonParams;
    }

    public function get_json_params(): array
    {
        return $this->jsonParams;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->params[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->params[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->params[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->params[$offset]);
    }
}
