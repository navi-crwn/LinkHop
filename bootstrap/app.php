<?php

use App\Http\AdminMiddleware;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\AutoLogout;
use App\Http\Middleware\CheckIpBan;
use App\Http\Middleware\CheckLinkQuota;
use App\Http\Middleware\CheckSuspended;
use App\Http\Middleware\ContentSecurityPolicyMiddleware;
use App\Http\Middleware\EnsureGoogleUserHasPassword;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\RemoveWwwPrefix;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware to remove www prefix
        $middleware->prepend(RemoveWwwPrefix::class);
        $middleware->appendToGroup('web', [
            AutoLogout::class,
            CheckSuspended::class,
            EnsureGoogleUserHasPassword::class,
            ContentSecurityPolicyMiddleware::class,
        ]);
        // Browsers POST CSP reports without a CSRF token.
        $middleware->validateCsrfTokens(except: ['csp-report']);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'ip.ban' => CheckIpBan::class,
            'quota' => CheckLinkQuota::class,
            '2fa' => EnsureTwoFactorAuthenticated::class,
            'api.auth' => ApiAuthenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
