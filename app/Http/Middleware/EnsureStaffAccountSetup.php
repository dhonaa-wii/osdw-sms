<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A staff account made by the administrator starts with a generated
 * password. Before anything else the staff member replaces it and connects
 * a Google account.
 */
class EnsureStaffAccountSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsAccountSetup()) {
            return redirect()->route('staff.account.setup');
        }

        return $next($request);
    }
}
