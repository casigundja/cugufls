<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->active, 403, 'Conta desativada.');
        if (app()->isProduction() && $request->user()->hasAnyRole(['admin', 'super-admin']) && ! $request->user()->two_factor_confirmed_at && ! $request->is('admin/seguranca')) {
            return redirect('/admin/seguranca')->with('success', 'Configure a autenticação de dois fatores para continuar.');
        }

        return $next($request);
    }
}
