<?php

namespace App\Controllers;

use Horizon\Auth\Concerns\AuthenticatesUsers;

/**
 * LoginController
 * 
 * Handles user authentication (login/logout).
 * 
 * Example implementation showing how to use the AuthenticatesUsers trait.
 */
class LoginController
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login
     *
     * @var string
     */
    protected string $redirectTo = '/dashboard';

    /**
     * Show the application's login form
     *
     * @return mixed
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request to the application
     *
     * @param mixed $request
     * @return mixed
     */
    public function login($request)
    {
        // The trait handles validation and authentication
        return parent::login($request);
    }

    /**
     * Log the user out of the application
     *
     * @param mixed $request
     * @return mixed
     */
    public function logout($request)
    {
        return parent::logout($request);
    }

    /**
     * Get the login username (override if using username instead of email)
     *
     * @return string
     */
    public function username(): string
    {
        return 'email';
    }
}
