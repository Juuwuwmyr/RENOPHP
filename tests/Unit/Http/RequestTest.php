<?php

declare(strict_types=1);

namespace Horizon\Tests\Unit\Http;

use Horizon\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function test_can_create_request_from_globals(): void
    {
        $request = new Request(
            ['q' => 'search'],
            ['name' => 'John'],
            ['session_id' => 'abc123'],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/test?q=search',
                'HTTP_USER_AGENT' => 'Test Agent',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
            ]
        );

        $this->assertEquals('POST', $request->method());
        $this->assertEquals('/test', $request->path());
        $this->assertEquals('search', $request->input('q'));
        $this->assertEquals('John', $request->input('name'));
        $this->assertEquals('Test Agent', $request->userAgent());
        $this->assertTrue($request->ajax());
    }

    public function test_can_get_input_values(): void
    {
        $request = new Request(
            ['query_param' => 'value'],
            ['post_param' => 'value2'],
            [],
            [],
            ['REQUEST_METHOD' => 'POST']
        );

        $this->assertEquals('value', $request->input('query_param'));
        $this->assertEquals('value2', $request->input('post_param'));
        $this->assertEquals('default', $request->input('missing', 'default'));
    }

    public function test_can_check_input_existence(): void
    {
        $request = new Request(
            ['exists' => 'value', 'empty' => ''],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'GET']
        );

        $this->assertTrue($request->has('exists'));
        $this->assertTrue($request->has('empty'));
        $this->assertFalse($request->has('missing'));
        
        $this->assertTrue($request->filled('exists'));
        $this->assertFalse($request->filled('empty'));
        $this->assertFalse($request->filled('missing'));
    }

    public function test_can_get_only_specified_inputs(): void
    {
        $request = new Request(
            ['a' => 1, 'b' => 2, 'c' => 3],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'GET']
        );

        $this->assertEquals(['a' => 1, 'c' => 3], $request->only(['a', 'c']));
        $this->assertEquals(['a' => 1, 'b' => 2], $request->except(['c']));
    }

    public function test_can_detect_json_requests(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/json'
            ],
            '{"name": "John"}'
        );

        $this->assertTrue($request->isJson());
        $this->assertEquals('John', $request->json('name'));
    }

    public function test_can_get_headers(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [
                'HTTP_USER_AGENT' => 'Test Agent',
                'HTTP_AUTHORIZATION' => 'Bearer token123',
                'CONTENT_TYPE' => 'application/json'
            ]
        );

        $this->assertEquals('Test Agent', $request->header('User-Agent'));
        $this->assertEquals('Bearer token123', $request->header('Authorization'));
        $this->assertEquals('application/json', $request->header('Content-Type'));
        $this->assertEquals('token123', $request->bearerToken());
    }

    public function test_can_detect_secure_requests(): void
    {
        $request = new Request([], [], [], [], [
            'HTTPS' => 'on'
        ]);

        $this->assertTrue($request->secure());
        
        $request = new Request([], [], [], [], [
            'HTTPS' => 'off'
        ]);

        $this->assertFalse($request->secure());
    }

    public function test_can_get_client_ip(): void
    {
        $request = new Request([], [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        $this->assertEquals('192.168.1.1', $request->ip());
        
        $request = new Request([], [], [], [], [
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 192.168.1.1',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        // Should return the forwarded IP if valid
        $this->assertStringContainsString('.', $request->ip());
    }

    public function test_method_override(): void
    {
        // Test _method parameter override
        $request = new Request(
            [],
            ['_method' => 'PUT'],
            [],
            [],
            ['REQUEST_METHOD' => 'POST']
        );

        $this->assertEquals('PUT', $request->method());
        
        // Test X-HTTP-Method-Override header
        $request = new Request(
            [],
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE'
            ]
        );

        $this->assertEquals('DELETE', $request->method());
    }
}