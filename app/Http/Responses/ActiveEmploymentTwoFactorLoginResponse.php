<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class ActiveEmploymentTwoFactorLoginResponse implements TwoFactorLoginResponse
{
    public function __construct(private StatefulGuard $guard) {}

    /**
     * Create a response after a successful two-factor login.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->employee !== null && ! $user->hasActiveEmployment()) {
            $this->guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                Fortify::username() => [__('Your employment is not active. Please contact an administrator.')],
            ]);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Fortify::redirects('login'));
    }
}
