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
     * Permite acesso apenas à equipe interna (administrador, dono da empresa ou funcionario).
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! ($user instanceof User) || ! $user->isStaff()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Você não possui permissão para acessar esta área do sistema.');
        }

        return $next($request);
    }
}
