<?php

namespace App\Http\Middleware;

use App\Services\CatalogRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => ['user' => $request->user()?->only('id', 'name', 'email')],
            'navigation' => fn () => $request->user() ? app(CatalogRegistry::class)->navigation($request->user()) : [],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'token' => fn () => $request->session()->get('token')],
        ];
    }
}
