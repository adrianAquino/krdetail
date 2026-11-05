<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUser
{
    /**
     * Permite acesso apenas a usuários internos (administrador ou funcionario).
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! ($user instanceof User) || $user->isCliente()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Você não possui permissão para acessar esta área do sistema.');
        }

        return $next($request);
    }
}
