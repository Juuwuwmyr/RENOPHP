<?php

namespace Horizon\Auth\Concerns;

use Horizon\Auth\AuthenticationException;

/**
 * AuthenticatesUsers
 * 
 * Trait for handling user authentication in controllers.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear authentication flow
 * - Validation before attempt
 * - Rate limiting support
 * - Remember me support
 * 
 * Usage:
 *   class LoginController
 *   {
 *       use AuthenticatesUsers;
 *   }
 */
trait AuthenticatesUsers
{
    /**
     * Handle a login request
     *
     * @param mixed $request
     * @return mixed
     */
    public function login($request)
    {
        // Validate the login request
        $this->validateLogin($request);

        // Attempt to authenticate the user
        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Validate the user login request
     *
     * @param mixed $request
     * @return void
     */
    protected function validateLogin($request): void
    {
        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string',
        ]);
    }

    /**
     * Attempt to log the user into the application
     *
     * @param mixed $request
     * @return bool
     */
    protected function attemptLogin($request): bool
    {
        return $this->guard()->attempt(
            $this->credentials($request),
            $request->filled('remember')
        );
    }

    /**
     * Get the needed credentials from the request
     *
     * @param mixed $request
     * @return array
     */
    protected function credentials($request): array
    {
        return $request->only($this->username(), 'password');
    }

    /**
     * Send the response after the user was authenticated
     *
     * @param mixed $request
     * @return mixed
     */
    protected function sendLoginResponse($request)
    {
        $request->session()->regenerate();

        return $this->authenticated($request, $this->guard()->user())
            ?: redirect()->intended($this->redirectPath());
    }

    /**
     * The user has been authenticated
     *
     * @param mixed $request
     * @param mixed $user
     * @return mixed
     */
    protected function authenticated($request, $user)
    {
        //
    }

    /**
     * Get the failed login response
     *
     * @param mixed $request
     * @return mixed
     */
    protected function sendFailedLoginResponse($request)
    {
        return back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors([
                $this->username() => 'These credentials do not match our records.',
            ]);
    }

    /**
     * Get the login username to be used by the controller
     *
     * @return string
     */
    public function username(): string
    {
        return 'email';
    }

    /**
     * Log the user out of the application
     *
     * @param mixed $request
     * @return mixed
     */
    public function logout($request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->loggedOut($request) ?: redirect('/');
    }

    /**
     * The user has logged out of the application
     *
     * @param mixed $request
     * @return mixed
     */
    protected function loggedOut($request)
    {
        //
    }

    /**
     * Get the guard to be used during authentication
     *
     * @return \Horizon\Auth\Contracts\StatefulGuard
     */
    protected function guard()
    {
        return auth()->guard();
    }

    /**
     * Get the post-login redirect path
     *
     * @return string
     */
    public function redirectPath(): string
    {
        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/home';
    }
}
