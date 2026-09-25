<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Horizon\Http\Controllers\Controller;
use Horizon\Contracts\Http\RequestInterface;

class ExampleController extends Controller
{
    public function __construct()
    {
        // Apply authentication middleware to all methods except index and show
        $this->middleware('auth')->except('index', 'show');
        
        // Apply rate limiting to store and update methods
        $this->middleware('throttle:10,1')->only('store', 'update');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(RequestInterface $request): array
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        
        // Simulate data retrieval
        $data = [
            'current_page' => (int) $page,
            'per_page' => (int) $limit,
            'total' => 100,
            'data' => array_map(fn($i) => [
                'id' => $i,
                'name' => "Item $i",
                'created_at' => date('Y-m-d H:i:s')
            ], range(1, $limit))
        ];

        return $this->success($data, 'Items retrieved successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id, RequestInterface $request): array
    {
        // Route parameter $id is automatically injected
        // RequestInterface is resolved from container
        
        if ($id <= 0 || $id > 1000) {
            return $this->error('Item not found', 404);
        }

        $item = [
            'id' => $id,
            'name' => "Item $id",
            'description' => "This is item number $id",
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->success($item, 'Item retrieved successfully');
    }

    /**
     * Store a newly created resource.
     */
    public function store(RequestInterface $request): array
    {
        // In a real application, this would include validation
        $name = $request->input('name');
        $description = $request->input('description');

        if (empty($name)) {
            return $this->error('Name is required', 400, ['name' => ['The name field is required.']]);
        }

        $item = [
            'id' => rand(1001, 9999),
            'name' => $name,
            'description' => $description ?: 'No description provided',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->success($item, 'Item created successfully', 201);
    }

    /**
     * Update the specified resource.
     */
    public function update(int $id, RequestInterface $request): array
    {
        if ($id <= 0 || $id > 1000) {
            return $this->error('Item not found', 404);
        }

        $name = $request->input('name');
        $description = $request->input('description');

        $item = [
            'id' => $id,
            'name' => $name ?: "Item $id",
            'description' => $description ?: "Updated item $id",
            'created_at' => date('Y-m-d H:i:s', time() - 86400), // Yesterday
            'updated_at' => date('Y-m-d H:i:s') // Now
        ];

        return $this->success($item, 'Item updated successfully');
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(int $id): array
    {
        if ($id <= 0 || $id > 1000) {
            return $this->error('Item not found', 404);
        }

        return $this->success(null, 'Item deleted successfully');
    }

    /**
     * Example method with multiple dependencies.
     */
    public function complexMethod(int $id, RequestInterface $request, ?string $optional = 'default'): array
    {
        // Demonstrates:
        // - Route parameter injection ($id)
        // - Service injection (RequestInterface $request)
        // - Optional parameter with default value ($optional)
        
        return $this->success([
            'id' => $id,
            'query_params' => $request->query(),
            'input_data' => $request->all(),
            'optional_param' => $optional,
            'method' => $request->method(),
            'path' => $request->path(),
        ], 'Complex method executed successfully');
    }
}