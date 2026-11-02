<?php

namespace App\Http\Middleware;

use App\Models\DutyTask;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Uwajibikaji: deadlines that passed become warnings as soon as anyone uses the portal (no scheduler needed). */
class SweepDutyDeadlines
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            DutyTask::sweep();
        } catch (Throwable $e) {
            report($e);   // never stops the portal (e.g. in the moment before the tables are made)
        }

        return $next($request);
    }
}
