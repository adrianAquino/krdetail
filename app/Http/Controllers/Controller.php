<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
