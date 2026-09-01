<?php

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
        // Portal: sem sessão vai-se para /entrar; com sessão, a página de escolha.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('portal'));

        // Atrás de um proxy (o nginx que serve o site em /multifuturo, e amanhã o
        // balanceador em produção). Confiamos apenas em endereços de rede privada,
        // para que ninguém de fora possa forjar estes cabeçalhos.
        //
        // X_FORWARDED_PREFIX é o que faz o Laravel saber que está numa subpasta: sem
        // ele, $request->url() vinha sem o prefixo e os URLs assinados (o upload de
        // ficheiros do Livewire, por exemplo) falhavam a validação da assinatura.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
