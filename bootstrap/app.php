<?php

use App\Http\Middleware\EnsureStaffAccountSetup;
use App\Http\Middleware\EnsureStudentProfile;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'student.profile' => EnsureStudentProfile::class,
            'staff.setup' => EnsureStaffAccountSetup::class,
        ]);

        // Hosts terminate HTTPS at a proxy; trust it so URLs and secure
        // cookies use https.
        $middleware->trustProxies(at: '*');

        // A signed-in user who opens /login or /register goes to their own home.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeRoute());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
