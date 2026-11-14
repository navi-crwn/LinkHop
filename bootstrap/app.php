<?php

use App\Http\AdminMiddleware;
use App\Http\Middleware\AutoLogout;
use App\Http\Middleware\CheckIpBan;
use App\Http\Middleware\CheckLinkQuota;
use App\Http\Middleware\CheckSuspended;
use App\Http\Middleware\ContentSecurityPolicyMiddleware;
use App\Http\Middleware\NoStoreWhenSignedIn;
use App\Http\Middleware\RemoveWwwPrefix;
use App\Http\Middleware\RequireHopAdminClient;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyHopSignature;
use App\Http\Middleware\VerifyHubRequest;
use App\Support\EncryptedNoteLink;
use App\Support\ServerErrorAlert;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware to remove www prefix
        $middleware->prepend(RemoveWwwPrefix::class);
        $middleware->appendToGroup('web', [
            SetLocale::class,
            AutoLogout::class,
            CheckSuspended::class,
            ContentSecurityPolicyMiddleware::class,
            NoStoreWhenSignedIn::class,
        ]);
        // Plain text: the family's hop_lang is read by scripts and the Go apps too; hop_pref marks its one-time move.
        $middleware->encryptCookies(except: [SetLocale::COOKIE, SetLocale::MIGRATED]);
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
        // An address that matches no route never meets the web middleware, so its 404 page would be English.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->route() === null) {
                SetLocale::apply($request);
            }

            return null;
        });
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

            // The token is checked before the locale middleware runs: pick the visitor's language here.
            SetLocale::apply($request);

            return redirect()->back()
                ->withInput(EncryptedNoteLink::scrubInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'token', 'code', 'recovery_code'])))
                ->with('session_expired', __('Your session expired. Please try again.'));
        });
        $exceptions->dontFlash(['payload']);
        // A refused form comes back with what was typed, kept in the session: without the key of an encrypted note.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                $request->merge(EncryptedNoteLink::scrubInput($request->input()));
            }
            // What a saved QR code carries (a Wi-Fi password, a contact) and its label never go back into the session as old input.
            if ($request->is('qr-codes', 'qr-codes/*')) {
                $request->replace($request->except(['payload', 'label']));
            }

            return null;
        });
        // With APP_DEBUG=true the error page prints the SQL of a failed query, values included. If one of the values
        // is an encrypted-note address with its key, the page is replaced by the plain error response.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            ServerErrorAlert::afterResponse($e, $request, $response->getStatusCode());
            if (! config('app.debug') || $response->getStatusCode() < 500 || ! EncryptedNoteLink::textHoldsSecret((string) $response->getContent())) {
                return $response;
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Server Error'], $response->getStatusCode())
                : response()->view('errors.500', [], $response->getStatusCode());
        });
    })->create();
