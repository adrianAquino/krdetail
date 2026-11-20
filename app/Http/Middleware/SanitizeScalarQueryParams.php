<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeScalarQueryParams
{
    /**
     * Parâmetros de query string que devem sempre ser escalares nas telas de listagem e filtro.
     */
    private const SCALAR_QUERY_KEYS = [
        'busca',
        'status',
        'acesso',
        'alerta',
        'tab',
        'periodo',
        'cliente_id',
        'veiculo_id',
        'page',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $queryAll = $request->query->all();

        foreach (self::SCALAR_QUERY_KEYS as $key) {
            if (array_key_exists($key, $queryAll) && $queryAll[$key] !== null && !is_scalar($queryAll[$key])) {
                $request->query->remove($key);
            }
        }

        return $next($request);
    }
}
