<?php

namespace Reno\Auth\Concerns;

/**
 * RegistersUsers
 * 
 * Trait for handling user registration in controllers.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear registration flow
 * - Validation before creation
 * - Automatic login after registration
 * - Event support
 * 
 * Usage:
 *   class RegisterController
 *   {
 *       use RegistersUsers;
 *   }
 */
trait RegistersUsers
{
    /**
     * Handle a registration request
     *
     * @param mixed $request
     * @return mixed
     */
    public function register($request)
    {
        // Validate the registration request
        $this->validator($request->all())->validate();

        // Create the user
        $user = $this->create($request->all());

        // Fire registered event
        event('user.registered', [$user]);

        // Log the user in
        $this->guard()->login($user);

        return $this->registered($request, $user)
            ?: redirect($this->redirectPath());
    }

    /**
     * Get a validator for an incoming registration request
     *
     * @param array $data
     * @return \Reno\Validation\Validator
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
     * @return \Reno\Auth\Contracts\Authenticatable
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
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
        //
    }

    /**
     * Get the guard to be used during registration
     *
     * @return \Reno\Auth\Contracts\StatefulGuard
     */
    protected function guard()
    {
        return auth()->guard();
    }

    /**
     * Get the post-registration redirect path
     *
     * @return string
     */
    public function redirectPath(): string
    {
        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/home';
    }
}
