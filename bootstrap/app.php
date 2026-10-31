<?php

use App\Http\AdminMiddleware;
use App\Http\Middleware\AutoLogout;
use App\Http\Middleware\CheckIpBan;
use App\Http\Middleware\CheckLinkQuota;
use App\Http\Middleware\CheckSuspended;
use App\Http\Middleware\ContentSecurityPolicyMiddleware;
use App\Http\Middleware\RemoveWwwPrefix;
use App\Http\Middleware\RequireHopAdminClient;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyHopSignature;
use App\Http\Middleware\VerifyHubRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            SetLocale::class,
            AutoLogout::class,
            CheckSuspended::class,
            ContentSecurityPolicyMiddleware::class,
        ]);
        $middleware->encryptCookies(except: [SetLocale::COOKIE]);
        // Browsers POST CSP reports without a CSRF token; hub server-to-server calls are HMAC-signed.
        $middleware->validateCsrfTokens(except: ['csp-report', 'internal/*', 'hop/events']);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'ip.ban' => CheckIpBan::class,
            'quota' => CheckLinkQuota::class,
            'hop.signed' => VerifyHopSignature::class,
            'hop.admin_client' => RequireHopAdminClient::class,
            'hub.signed' => VerifyHubRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired CSRF token (session timed out while the page stayed open) is not an
        // attack worth a bare "419 Page Expired" page. CSRF stays on; only the reply changes.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException || $request->expectsJson()) {
                return null; // JSON/AJAX callers keep the 419 JSON response
            }
            if ($request->routeIs('logout')) {
                // The user wanted to leave anyway: finish the logout here and at the hub.
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->away(hub_url('logout'));
            }

            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'token', 'code', 'recovery_code']))
                ->with('session_expired', 'Sesi habis, silakan coba lagi.');
        });
    })->create();
