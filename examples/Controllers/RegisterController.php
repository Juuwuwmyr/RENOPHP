<?php

namespace App\Controllers;

use Horizon\Auth\Concerns\RegistersUsers;
use App\Models\User;

/**
 * RegisterController
 * 
 * Handles user registration.
 * 
 * Example implementation showing how to use the RegistersUsers trait.
 */
class RegisterController
{
    use RegistersUsers;

    /**
     * Where to redirect users after registration
     *
     * @var string
     */
    protected string $redirectTo = '/dashboard';

    /**
     * Show the application's registration form
     *
     * @return mixed
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle a registration request for the application
     *
     * @param mixed $request
     * @return mixed
     */
    public function register($request)
    {
        // The trait handles validation and user creation
        return parent::register($request);
    }

    /**
     * Get a validator for an incoming registration request
     *
     * @param array $data
     * @return \Horizon\Validation\Validator
     */
    protected function validator(array $data)
    {
        return validator($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);
    }

    /**
     * Create a new user instance after a valid registration
     *
     * @param array $data
     * @return \Horizon\Auth\Contracts\Authenticatable
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'email_verified_at' => null, // Will be set after email verification
        ]);
    }

    /**
     * The user has been registered
     *
     * @param mixed $request
     * @param mixed $user
     * @return mixed
     */
    protected function registered($request, $user)
    {
        // Send verification email
        // $user->sendEmailVerificationNotification();

        // Flash success message
        flash('success', 'Registration successful! Please verify your email.');
    }
}
