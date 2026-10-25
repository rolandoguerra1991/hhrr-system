<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class EnsureUserHasActiveEmployment
{
    public function __construct(private StatefulGuard $guard) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user instanceof User && $user->employee !== null && ! $user->hasActiveEmployment()) {
            $this->guard->logout();

            throw ValidationException::withMessages([
                Fortify::username() => [__('Your employment is not active. Please contact an administrator.')],
            ]);
        }

        return $next($request);
    }
}
