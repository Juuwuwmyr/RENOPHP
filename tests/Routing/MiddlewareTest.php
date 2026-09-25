<?php

declare(strict_types=1);

namespace Tests\Routing;

use Horizon\Http\Middleware\Pipeline;
use Horizon\Http\Middleware\TrimStrings;
use Horizon\Http\Middleware\ConvertEmptyStringsToNull;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    /** @test */
    public function it_can_execute_middleware_pipeline(): void
    {
        $request = $this->createRequest('POST', '/test', [
            'name' => '  John Doe  ',
            'email' => '',
            'description' => '  Some text  '
        ]);

        $pipeline = new Pipeline($this->app);
        
        $response = $pipeline
            ->send($request)
            ->through([
                TrimStrings::class,
                ConvertEmptyStringsToNull::class,
            ])
            ->then(function ($request) {
                // Check that strings were trimmed and empty strings converted to null
                $this->assertEquals('John Doe', $request->input('name'));
                $this->assertNull($request->input('email'));
                $this->assertEquals('Some text', $request->input('description'));
                
                return 'middleware processed';
            });

        $this->assertEquals('middleware processed', $response);
    }

    /** @test */
    public function it_can_execute_middleware_with_parameters(): void
    {
        // Create a test middleware that accepts parameters
        $middleware = new class implements \Horizon\Contracts\Http\MiddlewareInterface {
            public function handle(\Horizon\Contracts\Http\RequestInterface $request, \Closure $next, string $param = 'default'): mixed
            {
                $request->attributes->set('middleware_param', $param);
                return $next($request);
            }
        };

        $request = $this->createRequest('GET', '/test');
        
        $pipeline = new Pipeline($this->app);
        
        $response = $pipeline
            ->send($request)
            ->through([get_class($middleware) . ':custom_value'])
            ->then(function ($request) {
                $this->assertEquals('custom_value', $request->attributes->get('middleware_param'));
                return 'success';
            });

        $this->assertEquals('success', $response);
    }

    /** @test */
    public function it_executes_middleware_in_correct_order(): void
    {
        $executionOrder = [];

        $middleware1 = new class($executionOrder) implements \Horizon\Contracts\Http\MiddlewareInterface {
            private array &$order;
            
            public function __construct(array &$order) {
                $this->order = &$order;
            }
            
            public function handle(\Horizon\Contracts\Http\RequestInterface $request, \Closure $next): mixed
            {
                $this->order[] = 'middleware1_before';
                $response = $next($request);
                $this->order[] = 'middleware1_after';
                return $response;
            }
        };

        $middleware2 = new class($executionOrder) implements \Horizon\Contracts\Http\MiddlewareInterface {
            private array &$order;
            
            public function __construct(array &$order) {
                $this->order = &$order;
            }
            
            public function handle(\Horizon\Contracts\Http\RequestInterface $request, \Closure $next): mixed
            {
                $this->order[] = 'middleware2_before';
                $response = $next($request);
                $this->order[] = 'middleware2_after';
                return $response;
            }
        };

        $request = $this->createRequest('GET', '/test');
        
        $pipeline = new Pipeline($this->app);
        
        $pipeline
            ->send($request)
            ->through([$middleware1, $middleware2])
            ->then(function ($request) use (&$executionOrder) {
                $executionOrder[] = 'destination';
                return 'success';
            });

        $expectedOrder = [
            'middleware1_before',
            'middleware2_before',
            'destination',
            'middleware2_after',
            'middleware1_after'
        ];

        $this->assertEquals($expectedOrder, $executionOrder);
    }

    /** @test */
    public function trim_strings_middleware_trims_input(): void
    {
        $request = $this->createRequest('POST', '/test', [
            'name' => '  John Doe  ',
            'nested' => [
                'field' => '  nested value  '
            ]
        ]);

        $middleware = new TrimStrings();
        
        $processedRequest = null;
        $middleware->handle($request, function ($req) use (&$processedRequest) {
            $processedRequest = $req;
            return 'success';
        });

        $this->assertEquals('John Doe', $processedRequest->input('name'));
        $this->assertEquals('nested value', $processedRequest->input('nested.field'));
    }

    /** @test */
    public function convert_empty_strings_middleware_converts_to_null(): void
    {
        $request = $this->createRequest('POST', '/test', [
            'name' => 'John Doe',
            'email' => '',
            'description' => null,
            'nested' => [
                'empty' => '',
                'value' => 'test'
            ]
        ]);

        $middleware = new ConvertEmptyStringsToNull();
        
        $processedRequest = null;
        $middleware->handle($request, function ($req) use (&$processedRequest) {
            $processedRequest = $req;
            return 'success';
        });

        $this->assertEquals('John Doe', $processedRequest->input('name'));
        $this->assertNull($processedRequest->input('email'));
        $this->assertNull($processedRequest->input('description'));
        $this->assertNull($processedRequest->input('nested.empty'));
        $this->assertEquals('test', $processedRequest->input('nested.value'));
    }
}