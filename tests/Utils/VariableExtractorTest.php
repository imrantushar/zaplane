<?php

namespace Zaplane\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Zaplane\Utils\VariableExtractor;

class VariableExtractorTest extends TestCase
{
    /**
     * @test
     */
    public function it_extracts_simple_types(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30,
            'active' => true,
            'score' => 95.5,
        ];

        $variables = VariableExtractor::extract($data);

        $this->assertCount(4, $variables);
        $this->assertEquals('name', $variables[0]['key']);
        $this->assertEquals('string', $variables[0]['type']);
        $this->assertEquals('age', $variables[1]['key']);
        $this->assertEquals('integer', $variables[1]['type']);
        $this->assertEquals('active', $variables[2]['key']);
        $this->assertEquals('boolean', $variables[2]['type']);
        $this->assertEquals('score', $variables[3]['key']);
        $this->assertEquals('float', $variables[3]['type']);
    }

    /**
     * @test
     */
    public function it_extracts_nested_objects(): void
    {
        $data = [
            'user' => [
                'id' => 1,
                'email' => 'john@example.com',
            ],
        ];

        $variables = VariableExtractor::extract($data);

        $this->assertCount(2, $variables);
        $this->assertEquals('user.id', $variables[0]['key']);
        $this->assertEquals('integer', $variables[0]['type']);
        $this->assertEquals('user.email', $variables[1]['key']);
        $this->assertEquals('email', $variables[1]['type']);
    }

    /**
     * @test
     */
    public function it_extracts_arrays(): void
    {
        $data = [
            'tags' => ['php', 'mysql', 'wordpress'],
        ];

        $variables = VariableExtractor::extract($data);

        $this->assertCount(1, $variables);
        $this->assertEquals('tags', $variables[0]['key']);
        $this->assertEquals('array', $variables[0]['type']);
    }

    /**
     * @test
     */
    public function it_extracts_array_of_objects(): void
    {
        $data = [
            'posts' => [
                ['id' => 1, 'title' => 'Post 1'],
                ['id' => 2, 'title' => 'Post 2'],
            ],
        ];

        $variables = VariableExtractor::extract($data);

        $this->assertCount(3, $variables);
        $this->assertEquals('posts', $variables[0]['key']);
        $this->assertEquals('array', $variables[0]['type']);
        $this->assertEquals('posts[].id', $variables[1]['key']);
        $this->assertEquals('integer', $variables[1]['type']);
        $this->assertEquals('posts[].title', $variables[2]['key']);
        $this->assertEquals('string', $variables[2]['type']);
    }

    /**
     * @test
     */
    public function it_detects_special_string_types(): void
    {
        $data = [
            'email' => 'test@example.com',
            'website' => 'https://example.com',
            'date' => '2024-01-15',
            'datetime' => '2024-01-15T10:30:00',
            'html' => '<p>Hello</p>',
        ];

        $variables = VariableExtractor::extract($data);

        $this->assertEquals('email', $variables[0]['type']);
        $this->assertEquals('url', $variables[1]['type']);
        $this->assertEquals('datetime', $variables[2]['type']);
        $this->assertEquals('datetime', $variables[3]['type']);
        $this->assertEquals('html', $variables[4]['type']);
    }

    /**
     * @test
     */
    public function it_flattens_nested_data(): void
    {
        $data = [
            'user' => [
                'id' => 1,
                'profile' => [
                    'name' => 'John',
                    'age' => 30,
                ],
            ],
            'status' => 'active',
        ];

        $flat = VariableExtractor::flatten($data);

        $this->assertEquals([
            'user.id' => 1,
            'user.profile.name' => 'John',
            'user.profile.age' => 30,
            'status' => 'active',
        ], $flat);
    }

    /**
     * @test
     */
    public function it_handles_null_values(): void
    {
        $data = [
            'value' => null,
        ];

        $variables = VariableExtractor::extract($data);

        $this->assertCount(1, $variables);
        $this->assertEquals('value', $variables[0]['key']);
        $this->assertEquals('null', $variables[0]['type']);
    }

    /**
     * @test
     */
    public function it_truncates_long_strings_in_sample(): void
    {
        $data = [
            'content' => str_repeat('a', 200),
        ];

        $variables = VariableExtractor::extract($data);

        $this->assertStringEndsWith('...', $variables[0]['sample']);
        $this->assertEquals(103, strlen($variables[0]['sample']));
    }

    /**
     * @test
     */
    public function it_handles_non_array_scalar_input(): void
    {
        $variables = VariableExtractor::extract('hello world');

        $this->assertCount(1, $variables);
        $this->assertEquals('value', $variables[0]['key']);
        $this->assertEquals('string', $variables[0]['type']);
        $this->assertEquals('hello world', $variables[0]['sample']);
    }

    /**
     * @test
     */
    public function it_handles_non_array_integer_input(): void
    {
        $variables = VariableExtractor::extract(42);

        $this->assertCount(1, $variables);
        $this->assertEquals('value', $variables[0]['key']);
        $this->assertEquals('integer', $variables[0]['type']);
        $this->assertEquals(42, $variables[0]['sample']);
    }

    /**
     * @test
     */
    public function it_handles_null_input(): void
    {
        $variables = VariableExtractor::extract(null);

        $this->assertCount(0, $variables);
    }

    /**
     * @test
     */
    public function it_handles_empty_array_input(): void
    {
        $variables = VariableExtractor::extract([]);

        $this->assertCount(0, $variables);
    }
}
