<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminTenantContext
{
    private static ?int $empresaId = null;

    public static function setEmpresaId(?int $empresaId): void
    {
        self::$empresaId = $empresaId !== null && $empresaId > 0 ? $empresaId : null;
    }

    public static function getEmpresaId(): ?int
    {
        return self::$empresaId;
    }

    public static function clear(): void
    {
        self::$empresaId = null;
    }

    public static function resolveFromRequest(Request $request): ?int
    {
        self::$empresaId = null;

        $user = Auth::user();
        if (! ($user instanceof User) || ! $user->isAdministrador()) {
            return null;
        }

        $raw = $request->query('empresa_id');
        if ($raw === null || $raw === '' || ! is_scalar($raw)) {
            return null;
        }

        $id = filter_var((string) $raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            return null;
        }

        $empresaId = (int) $id;
        if (! Empresa::whereKey($empresaId)->exists()) {
            return null;
        }

        self::$empresaId = $empresaId;

        return self::$empresaId;
    }
}
