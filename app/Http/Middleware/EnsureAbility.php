<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAbility
{
    /**
     * Usage: ->middleware('ability:stock.manage,logistics.manage') — any listed ability passes.
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user || ! collect($abilities)->contains(fn (string $ability) => $user->hasAbility($ability))) {
            abort(403, __('You do not have permission to do this.'));
        }

        return $next($request);
    }
}
