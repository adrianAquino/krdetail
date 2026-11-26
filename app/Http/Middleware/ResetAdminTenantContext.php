<?php

namespace App\Http\Middleware;

use App\Support\AdminTenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResetAdminTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        AdminTenantContext::clear();

        try {
            return $next($request);
        } finally {
            AdminTenantContext::clear();
        }
    }
}
