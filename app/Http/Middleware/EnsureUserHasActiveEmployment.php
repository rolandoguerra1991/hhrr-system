<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasActiveEmployment
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->employee !== null && ! $user->hasActiveEmployment()) {
            Auth::guard(config('fortify.guard'))->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                Fortify::username() => [__('Your employment is not active. Please contact an administrator.')],
            ]);
        }

        return $next($request);
    }
}
