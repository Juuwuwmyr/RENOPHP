<?php

namespace App\Controllers;

use App\Models\User;
use Reno\Http\Request;
use Reno\Http\Response;

/**
 * UserController
 * 
 * Handles user CRUD operations
 */
class UserController
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        $users = User::all();
        
        return Response::json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Display the specified user
     */
    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return Response::json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        return Response::json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8'
        ]);

        // Hash the password
        $validated['password'] = password_hash($validated['password'], PASSWORD_BCRYPT);

        $user = User::create($validated);

        return Response::json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return Response::json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8'
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = password_hash($validated['password'], PASSWORD_BCRYPT);
        }

        $user->update($validated);

        return Response::json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return Response::json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();

        return Response::json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }
}
