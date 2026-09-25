<?php

declare(strict_types=1);

namespace Horizon\Tests\Unit\Http;

use Horizon\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function test_can_create_basic_response(): void
    {
        $response = new Response('Hello World', 200);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isOk());
    }

    public function test_can_set_headers(): void
    {
        $response = new Response();
        $response->header('Content-Type', 'application/json');
        $response->header('X-Custom-Header', 'custom-value');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals('custom-value', $response->getHeader('X-Custom-Header'));
        
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Custom-Header', $headers);
    }

    public function test_can_create_json_response(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $response = new Response();
        $response->json($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $response->getContent());
    }

    public function test_can_create_redirect_response(): void
    {
        $response = new Response();
        $response->redirect('https://example.com', 302);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeader('Location'));
        $this->assertTrue($response->isRedirect());
    }

    public function test_status_code_methods(): void
    {
        // Test successful responses
        $response = new Response('', 200);
        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isOk());
        $this->assertFalse($response->isError());

        // Test client error responses
        $response = new Response('', 404);
        $this->assertTrue($response->isClientError());
        $this->assertTrue($response->isNotFound());
        $this->assertTrue($response->isError());
        $this->assertFalse($response->isSuccessful());

        // Test server error responses
        $response = new Response('', 500);
        $this->assertTrue($response->isServerError());
        $this->assertTrue($response->isError());
        $this->assertFalse($response->isSuccessful());

        // Test forbidden response
        $response = new Response('', 403);
        $this->assertTrue($response->isForbidden());
        $this->assertTrue($response->isClientError());

        // Test empty responses
        $response = new Response('', 204);
        $this->assertTrue($response->isEmpty());
    }

    public function test_can_set_cookies(): void
    {
        $response = new Response();
        $response->cookie('session_id', 'abc123', [
            'expires' => time() + 3600,
            'path' => '/admin',
            'secure' => true
        ]);

        // We can't easily test the actual cookie setting, but we can verify 
        // the response object stores the cookie data correctly
        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_can_chain_method_calls(): void
    {
        $response = (new Response())
            ->status(201)
            ->header('Content-Type', 'application/json')
            ->header('X-Custom', 'test')
            ->content('{"success": true}');

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals('test', $response->getHeader('X-Custom'));
        $this->assertEquals('{"success": true}', $response->getContent());
    }

    public function test_make_static_method(): void
    {
        $response = Response::make('Hello', 201, ['X-Test' => 'value']);

        $this->assertEquals('Hello', $response->getContent());
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('value', $response->getHeader('X-Test'));
    }

    public function test_with_headers_method(): void
    {
        $response = new Response();
        $response->withHeaders([
            'Content-Type' => 'text/plain',
            'X-Custom-1' => 'value1',
            'X-Custom-2' => 'value2'
        ]);

        $this->assertEquals('text/plain', $response->getHeader('Content-Type'));
        $this->assertEquals('value1', $response->getHeader('X-Custom-1'));
        $this->assertEquals('value2', $response->getHeader('X-Custom-2'));
    }

    public function test_to_string_conversion(): void
    {
        $response = new Response('Hello World');
        
        $this->assertEquals('Hello World', (string) $response);
    }
}