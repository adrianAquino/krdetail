<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ConfiguracaoController extends Controller
{
    public function index()
    {
        $usuarios = User::orderBy('name', 'asc')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'telefone' => $u->telefone ?? '(11) 99999-0000',
                'tipo' => $u->tipo_usuario ?? 'funcionario',
                'ativo' => (bool) $u->ativo,
            ];
        });

        return view('configuracoes.index', compact('usuarios'));
    }
}
