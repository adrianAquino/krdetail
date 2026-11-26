<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagerUser
{
    /**
     * Permite operações gerenciais em catálogo (serviços/produtos) e movimentações manuais de estoque
     * apenas para Administrador Geral ou Dono da Empresa ativo (regra antifraude).
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

        if (! $user->canManageCatalogoEEstoque()) {
            $mensagem = 'Apenas o Dono da Empresa ou Administrador Geral pode cadastrar/alterar serviços, produtos ou realizar movimentações manuais de estoque fora de Ordens de Serviço.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $mensagem,
                ], 403);
            }

            $destino = match (true) {
                $request->routeIs('servicos.*') => 'servicos.index',
                $request->routeIs('produtos.*') => 'produtos.index',
                $request->routeIs('estoque.*') => 'estoque.index',
                default => 'dashboard',
            };

            return redirect()
                ->route($destino)
                ->with('error', $mensagem);
        }

        return $next($request);
    }
}
