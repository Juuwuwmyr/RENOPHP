<?php

declare(strict_types=1);

namespace Horizon\Http\Controllers;

use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Http\Response;

abstract class Controller
{
    /**
     * The middleware registered on the controller.
     */
    protected array $middleware = [];

    /**
     * Execute an action on the controller.
     */
    public function callAction(string $method, array $parameters): mixed
    {
        return $this->{$method}(...array_values($parameters));
    }

    /**
     * Get the middleware assigned to the controller.
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Register middleware on the controller.
     */
    protected function middleware(string|array $middleware, array $options = []): MiddlewareDefinition
    {
        $definition = new MiddlewareDefinition($middleware, $options);
        
        $this->middleware[] = $definition;

        return $definition;
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = app('validator')->make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new \Horizon\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Create a JSON response.
     */
    protected function json(mixed $data = [], int $status = 200, array $headers = []): ResponseInterface
    {
        return response()->json($data, $status, $headers);
    }

    /**
     * Create a successful JSON response.
     */
    protected function success(mixed $data = [], string $message = 'Success', int $status = 200): ResponseInterface
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Create an error JSON response.
     */
    protected function error(string $message = 'Error', int $status = 400, mixed $errors = null): ResponseInterface
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->json($response, $status);
    }

    /**
     * Create a redirect response.
     */
    protected function redirect(string $to, int $status = 302, array $headers = []): ResponseInterface
    {
        return response()->redirect($to, $status, $headers);
    }

    /**
     * Create a redirect response to a route.
     */
    protected function redirectToRoute(string $name, array $parameters = [], int $status = 302, array $headers = []): ResponseInterface
    {
        return $this->redirect(route($name, $parameters), $status, $headers);
    }

    /**
     * Create a view response.
     */
    protected function view(string $view, array $data = [], int $status = 200, array $headers = []): ResponseInterface
    {
        return response()->view($view, $data, $status, $headers);
    }

    /**
     * Create a download response.
     */
    protected function download(string $file, ?string $name = null, array $headers = []): ResponseInterface
    {
        return response()->download($file, $name, $headers);
    }

    /**
     * Create a file response.
     */
    protected function file(string $file, array $headers = []): ResponseInterface
    {
        return response()->file($file, $headers);
    }

    /**
     * Create a streamed response.
     */
    protected function stream(\Closure $callback, int $status = 200, array $headers = []): ResponseInterface
    {
        return response()->stream($callback, $status, $headers);
    }

    /**
     * Get the authenticated user.
     */
    protected function user(?string $guard = null): mixed
    {
        return auth($guard)->user();
    }

    /**
     * Authorize a given action for the current user.
     */
    protected function authorize(string $ability, mixed $arguments = []): void
    {
        if (!$this->can($ability, $arguments)) {
            $this->deny();
        }
    }

    /**
     * Determine if the current user can perform the given ability.
     */
    protected function can(string $ability, mixed $arguments = []): bool
    {
        return app('gate')->allows($ability, $arguments);
    }

    /**
     * Deny access with a 403 response.
     */
    protected function deny(string $message = 'This action is unauthorized.'): never
    {
        throw new \Horizon\Http\Exceptions\HttpException(403, $message);
    }
}