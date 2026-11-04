<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CpfCnpj implements ValidationRule
{
    /**
     * Executa a regra de validação de CPF ou CNPJ (tradicional e alfanumérico).
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Remove pontuações e converte letras para maiúsculo
        $limpo = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));

        if (strlen($limpo) === 11 && ctype_digit($limpo)) {
            if (!$this->validarCpf($limpo)) {
                $fail('O CPF informado é inválido.');
            }
            return;
        }

        if (strlen($limpo) === 14) {
            if (!$this->validarCnpj($limpo)) {
                $fail('O CNPJ informado é inválido.');
            }
            return;
        }

        $fail('O campo deve conter um CPF (11 dígitos) ou CNPJ (14 caracteres) válido.');
    }

    private function validarCpf(string $cpf): bool
    {
        // Rejeita sequências de dígitos iguais (ex: 00000000000, 11111111111)
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
     * Valida um CNPJ (Suporta o formato tradicional e o Alfanumérico)
     */
    private function validarCnpj(string $cnpj): bool
    {
        // O CNPJ precisa ter exatamente 14 caracteres e não pode ser sequência repetida
        if (strlen($cnpj) !== 14 || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        // Os dois últimos caracteres (Dígitos Verificadores) devem ser estritamente numéricos
        if (!ctype_digit(substr($cnpj, -2))) {
            return false;
        }

        // Separa a base (12 caracteres) dos dígitos verificadores (2 caracteres)
        $base = substr($cnpj, 0, 12);
        $dvOriginal = substr($cnpj, 12, 2);

        // 1º Dígito Verificador
        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $dv1 = $this->calcularDigitoCnpj($base, $pesos1);

        // 2º Dígito Verificador
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $dv2 = $this->calcularDigitoCnpj($base . $dv1, $pesos2);

        return ($dvOriginal === "{$dv1}{$dv2}");
    }

    /**
     * Executa o cálculo do Módulo 11 do CNPJ adaptado para alfanumérico
     */
    private function calcularDigitoCnpj(string $bloco, array $pesos): int
    {
        $soma = 0;
        $tamanho = strlen($bloco);

        for ($i = 0; $i < $tamanho; $i++) {
            $char = $bloco[$i];

            // Regra da Receita Federal: Valor ASCII do caractere menos 48
            $valorItem = ord($char) - 48;

            $soma += $valorItem * $pesos[$i];
        }

        $resto = $soma % 11;

        // Se o resto for menor que 2, o dígito é 0. Caso contrário, é 11 - resto.
        return ($resto < 2) ? 0 : (11 - $resto);
    }
}
