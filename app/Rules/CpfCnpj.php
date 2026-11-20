<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CpfCnpj implements ValidationRule
{
    public function __construct(public readonly bool $apenasCnpj = false)
    {
    }

    /**
     * Normaliza um CPF (11 dígitos) ou CNPJ (14 caracteres) para o padrão formatado único,
     * prevenindo duplicidades por diferença de pontuação/espaços/caixa.
     */
    public static function normalizar(mixed $valor): ?string
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }

        $trimmed = trim((string) $valor);
        if ($trimmed === '') {
            return null;
        }

        $limpo = preg_replace('/[^A-Z0-9]/', '', strtoupper($trimmed));
        if ($limpo === null || $limpo === '') {
            return $trimmed;
        }

        if (strlen($limpo) === 11 && ctype_digit($limpo)) {
            return substr($limpo, 0, 3) . '.' . substr($limpo, 3, 3) . '.' . substr($limpo, 6, 3) . '-' . substr($limpo, 9, 2);
        }

        if (strlen($limpo) === 14) {
            return substr($limpo, 0, 2) . '.' . substr($limpo, 2, 3) . '.' . substr($limpo, 5, 3) . '/' . substr($limpo, 8, 4) . '-' . substr($limpo, 12, 2);
        }

        return $trimmed;
    }

    /**
     * Executa a regra de validação de CPF ou CNPJ (tradicional e alfanumérico).
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('O campo deve conter um CPF ou CNPJ em formato de texto válido.');
            return;
        }

        $texto = (string) $value;
        if (! preg_match('/^[0-9A-Za-z.\-\/\s]+$/', $texto)) {
            $fail($this->apenasCnpj
                ? 'O CNPJ informado contém caracteres inválidos.'
                : 'O campo contém caracteres inválidos para CPF ou CNPJ.');
            return;
        }

        $limpo = preg_replace('/[^A-Z0-9]/', '', strtoupper($texto)) ?? '';

        if (! $this->apenasCnpj && strlen($limpo) === 11 && ctype_digit($limpo)) {
            if (! $this->validarCpf($limpo)) {
                $fail('O CPF informado é inválido.');
            }
            return;
        }

        if (strlen($limpo) === 14) {
            if (! $this->validarCnpj($limpo)) {
                $fail('O CNPJ informado é inválido.');
            }
            return;
        }

        $fail($this->apenasCnpj
            ? 'O CNPJ informado deve conter 14 caracteres válidos.'
            : 'O campo deve conter um CPF (11 dígitos) ou CNPJ (14 caracteres) válido.');
    }

    private function validarCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida um CNPJ (Suporta o formato tradicional e o Alfanumérico).
     */
    private function validarCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        if (! ctype_digit(substr($cnpj, -2))) {
            return false;
        }

        $base = substr($cnpj, 0, 12);
        $dvOriginal = substr($cnpj, 12, 2);

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $dv1 = $this->calcularDigitoCnpj($base, $pesos1);

        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $dv2 = $this->calcularDigitoCnpj($base . $dv1, $pesos2);

        return ($dvOriginal === "{$dv1}{$dv2}");
    }

    /**
     * Executa o cálculo do Módulo 11 do CNPJ adaptado para alfanumérico.
     */
    private function calcularDigitoCnpj(string $bloco, array $pesos): int
    {
        $soma = 0;
        $tamanho = strlen($bloco);

        for ($i = 0; $i < $tamanho; $i++) {
            $valorItem = ord($bloco[$i]) - 48;
            $soma += $valorItem * $pesos[$i];
        }

        $resto = $soma % 11;

        return ($resto < 2) ? 0 : (11 - $resto);
    }
}
