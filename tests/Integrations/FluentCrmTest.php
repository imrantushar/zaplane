<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\FluentCrm;

class FluentCrmTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return FluentCrm::class;
    }

    public function test_triggers(): void
    {
        $triggers = FluentCrm::get_triggers();

        foreach ( $triggers as $slug => $trigger ) {
            switch ( $slug ) {
                case 'added_tag':
                case 'removed_tag':
                    $contact = $this->mockContact();
                    $result = FluentCrm::resolve_trigger( ['event' => $slug ], [ $contact, [1, 2] ] );
                    $this->assertIsArray( $result );
                    $this->assertTrue( $result['success'] );
                    break;

                case 'added_list':
                case 'removed_list':
                    $contact = $this->mockContact();
                    $result = FluentCrm::resolve_trigger( ['event' => $slug ], [ $contact, [5, 6] ] );
                    $this->assertIsArray( $result );
                    $this->assertTrue( $result['success'] );
                    break;

                case 'created_contact':
                    $contact = $this->mockContact();
                    $result = FluentCrm::resolve_trigger( ['event' => $slug ], [ $contact] );
                    $this->assertIsArray( $result );
                    $this->assertTrue( $result['success'] );
                    $this->assertArrayHasKey('contact', $result );
                    break;

                case 'company_created':
                case 'company_deleted':
                    $company = $this->mockCompany();
                    $result = FluentCrm::resolve_trigger( ['event' => $slug ], [ $company ] );
                    $this->assertIsArray( $result );
                    $this->assertTrue( $result['success'] );
                    $this->assertArrayHasKey( 'company', $result );
                    break;

                case 'company_updated':
                    $company = $this->mockCompany();
                    $result = FluentCrm::resolve_trigger( ['event' => $slug ], [ $company, [], [] ] );
                    $this->assertIsArray( $result );
                    $this->assertTrue( $result['success'] );
                    $this->assertArrayHasKey( 'company', $result );
                    $this->assertArrayHasKey( 'old_status', $result );
                    $this->assertArrayHasKey( 'new_status', $result );
                    break;
            }
        }
    }

    public function test_actions(): void
    {
        $actions = FluentCrm::get_actions();

        foreach ( $actions as $slug => $action ) {
            $config = [];
            $input  = [];
            $result = FluentCrm::execute_node(
                ['data' => ['event' => $slug, 'config' => $config] ],
                $input
            );
            $this->assertIsArray( $result );
            $this->assertArrayHasKey( 'port', $result );
            $this->assertArrayHasKey( 'data', $result );
        }
    }

    private function mockContact()
    {
        return (object)[
            'id' => 1,
            'user_id' => 1,
            'hash' => 'mock-hash-1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'contact_owner' => 1,
            'company_id' => 1,
            'prefix' => '',
            'timezone' => 'UTC',
            'address_line_1' => '',
            'address_line_2' => '',
            'postal_code' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'ip' => '',
            'latitude' => null,
            'longitude' => null,
            'total_points' => 0,
            'life_time_value' => 0,
            'phone' => '',
            'status' => 'subscribed',
            'contact_type' => 'individual',
            'source' => '',
            'avatar' => '',
            'date_of_birth' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'last_activity' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'photo' => '',
            'tags' => [ $this->mockTag() ],
            'lists' => [ $this->mockList() ],
        ];
    }

    private function mockCompany()
    {
        return (object)[
            'id' => 10,
            'hash' => 'mock-company-hash',
            'owner_id' => 1,
            'name' => 'Test Company',
            'industry' => 'Software',
            'email' => 'company@example.com',
            'timezone' => 'UTC',
            'address_line_1' => '',
            'address_line_2' => '',
            'postal_code' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'employees_number' => 10,
            'description' => 'Test Company Description',
            'phone' => '',
            'type' => 'private',
            'logo' => '',
            'website' => '',
            'linkedin_url' => '',
            'facebook_url' => '',
            'twitter_url' => '',
            'date_of_start' => date('Y-m-d'),
            'meta' => ['custom_values' => []],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function mockTag()
    {
        return (object)[
            'id' => 100,
            'title' => 'VIP',
            'slug' => 'vip',
            'description' => 'VIP Tag',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'pivot' => (object)[
                'subscriber_id' => 1,
                'object_id' => 100,
                'object_type' => 'tag',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    private function mockList()
    {
        return (object)[
            'id' => 200,
            'title' => 'Newsletter',
            'slug' => 'newsletter',
            'description' => 'Newsletter List',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
}