<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasAnyRole(['admin', 'super-admin']) && ! $request->user()->two_factor_confirmed_at && ! $request->is('admin/seguranca')) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                abort(403, 'Configure a autenticação em dois passos na área de segurança.');
            }

            return redirect('/admin/seguranca');
        }

        return $next($request);
    }
}
