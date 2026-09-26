<?php

namespace Horizon\Auth\Concerns;

use Horizon\Auth\Passwords\PasswordBroker;

/**
 * SendsPasswordResetEmails
 * 
 * Trait for handling password reset email requests.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear password reset request flow
 * - Validation before sending
 * - Success/failure feedback
 * 
 * Usage:
 *   class ForgotPasswordController
 *   {
 *       use SendsPasswordResetEmails;
 *   }
 */
trait SendsPasswordResetEmails
{
    /**
     * Send a reset link to the given user
     *
     * @param mixed $request
     * @return mixed
     */
    public function sendResetLinkEmail($request)
    {
        $this->validateEmail($request);

        // Send the password reset link
        $response = $this->broker()->sendResetLink(
            $this->credentials($request)
        );

        return $response === PasswordBroker::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($request, $response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }

    /**
     * Validate the email for the given request
     *
     * @param mixed $request
     * @return void
     */
    protected function validateEmail($request): void
    {
        $request->validate(['email' => 'required|email']);
    }

    /**
     * Get the needed credentials from the request
     *
     * @param mixed $request
     * @return array
     */
    protected function credentials($request): array
    {
        return $request->only('email');
    }

    /**
     * Get the response for a successful password reset link
     *
     * @param mixed $request
     * @param string $response
     * @return mixed
     */
    protected function sendResetLinkResponse($request, string $response)
    {
        return back()->with('status', trans($response));
    }

    /**
     * Get the response for a failed password reset link
     *
     * @param mixed $request
     * @param string $response
     * @return mixed
     */
    protected function sendResetLinkFailedResponse($request, string $response)
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
}
