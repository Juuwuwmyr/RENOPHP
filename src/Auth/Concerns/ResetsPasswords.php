<?php

namespace Reno\Auth\Concerns;

use Reno\Auth\Passwords\PasswordBroker;

/**
 * ResetsPasswords
 * 
 * Trait for handling password reset logic.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear password reset flow
 * - Token validation
 * - Password update
 * - Automatic login after reset
 * 
 * Usage:
 *   class ResetPasswordController
 *   {
 *       use ResetsPasswords;
 *   }
 */
trait ResetsPasswords
{
    /**
     * Reset the given user's password
     *
     * @param mixed $request
     * @return mixed
     */
    public function reset($request)
    {
        $request->validate($this->rules(), $this->validationErrorMessages());

        // Reset the password
        $response = $this->broker()->reset(
            $this->credentials($request),
            function ($user, $password) {
                $this->resetPassword($user, $password);
            }
        );

        return $response === PasswordBroker::PASSWORD_RESET
            ? $this->sendResetResponse($request, $response)
            : $this->sendResetFailedResponse($request, $response);
    }

    /**
     * Get the password reset validation rules
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ];
    }

    /**
     * Get the password reset validation error messages
     *
     * @return array
     */
    protected function validationErrorMessages(): array
    {
        return [];
    }

    /**
     * Get the password reset credentials from the request
     *
     * @param mixed $request
     * @return array
     */
    protected function credentials($request): array
    {
        return $request->only(
            'email',
            'password',
            'password_confirmation',
            'token'
        );
    }

    /**
     * Reset the given user's password
     *
     * @param \Reno\Auth\Contracts\Authenticatable $user
     * @param string $password
     * @return void
     */
    protected function resetPassword($user, string $password): void
    {
        $user->password = bcrypt($password);
        $user->save();

        // Log the user in
        $this->guard()->login($user);
    }

    /**
     * Get the response for a successful password reset
     *
     * @param mixed $request
     * @param string $response
     * @return mixed
     */
    protected function sendResetResponse($request, string $response)
    {
        return redirect($this->redirectPath())
            ->with('status', trans($response));
    }

    /**
     * Get the response for a failed password reset
     *
     * @param mixed $request
     * @param string $response
     * @return mixed
     */
    protected function sendResetFailedResponse($request, string $response)
    {
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($response)]);
    }

    /**
     * Get the broker to be used during password reset
     *
     * @return PasswordBroker
     */
    public function broker(): PasswordBroker
    {
        return app('auth.password');
    }

    /**
     * Get the guard to be used during password reset
     *
     * @return \Reno\Auth\Contracts\StatefulGuard
     */
    protected function guard()
    {
        return auth()->guard();
    }

    /**
     * Get the password reset redirect path
     *
     * @return string
     */
    public function redirectPath(): string
    {
        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/home';
    }
}
