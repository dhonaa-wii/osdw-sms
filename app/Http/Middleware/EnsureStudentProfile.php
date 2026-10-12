<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student must enter their student ID and personal details once before
 * they can apply or see their applications.
 */
class EnsureStudentProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isStudent() && ! $user->applicant) {
            // Remember where they were headed (e.g. an Apply page) for after the profile is saved.
            return redirect()->guest(route('student.profile.complete'));
        }

        return $next($request);
    }
}
