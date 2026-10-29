<?php

use App\Http\Middleware\EnsureCompanyAdmin;
use App\Http\Middleware\EnsurePasswordIsNotTemporary;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ResolveBranchContext;
use App\Http\Middleware\ResolveMenuCardContext;
use App\Http\Middleware\SecurityHeaders;
use App\Models\PlatformPaymentSetting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->preventRequestForgery(except: ['webhooks/mercado-pago/test', 'webhooks/mercado-pago/production']);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'branch.context' => ResolveBranchContext::class,
            'company.admin' => EnsureCompanyAdmin::class,
            'menu.card' => ResolveMenuCardContext::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'password.changed' => EnsurePasswordIsNotTemporary::class,
            'role' => EnsureUserHasRole::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveBranchContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveMenuCardContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(PlatformPaymentSetting::CREDENTIAL_FIELDS);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'webhooks/mercado-pago/*') || $request->expectsJson(),
        );
    })->create();
