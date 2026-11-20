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
     * Permite acesso apenas à equipe interna ativa (administrador, dono da empresa ou funcionario).
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! ($user instanceof User)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }

            return redirect()->route('login');
        }

        if (! $user->ativo) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sua conta foi desativada.'], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Sua conta foi desativada.']);
        }

        if (! $user->isStaff()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Você não possui permissão para acessar esta área do sistema.',
                ], 403);
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'Você não possui permissão para acessar esta área do sistema.');
        }

        return $next($request);
    }
}
