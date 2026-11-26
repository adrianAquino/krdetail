<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use App\Support\AdminTenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Lê um campo de entrada da requisição garantindo retorno string seguro
     * (evita ErrorException "Array to string conversion" do Request::string()).
     */
    protected function inputString(Request $request, string $key, string $default = ''): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Resolve o filtro de empresa específico por módulo para o Administrador Geral,
     * mantendo bloqueio e retorno nulo para Dono, Funcionário e Cliente.
     *
     * @return array{
     *     isAdmin: bool,
     *     empresasFiltro: \Illuminate\Support\Collection<int, Empresa>,
     *     empresaFiltroId: int|null,
     *     empresaFiltroAtual: Empresa|null
     * }
     */
    protected function resolverFiltroEmpresaAdmin(Request $request): array
    {
        $user = Auth::user();
        $isAdmin = $user instanceof User && $user->isAdministrador();

        if (! $isAdmin) {
            AdminTenantContext::clear();

            return [
                'isAdmin' => false,
                'empresasFiltro' => collect(),
                'empresaFiltroId' => null,
                'empresaFiltroAtual' => null,
            ];
        }

        $empresaFiltroId = AdminTenantContext::resolveFromRequest($request);
        $empresasFiltro = Empresa::orderBy('nome_fantasia')->get();
        $empresaFiltroAtual = $empresaFiltroId ? $empresasFiltro->firstWhere('id', $empresaFiltroId) : null;

        return [
            'isAdmin' => true,
            'empresasFiltro' => $empresasFiltro,
            'empresaFiltroId' => $empresaFiltroId,
            'empresaFiltroAtual' => $empresaFiltroAtual,
        ];
    }
}
