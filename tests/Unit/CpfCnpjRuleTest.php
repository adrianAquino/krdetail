<?php

namespace Tests\Unit;

use App\Models\Agendamento;
use App\Rules\CpfCnpj;
use PHPUnit\Framework\TestCase;

class CpfCnpjRuleTest extends TestCase
{
    private function validarCpfCnpj(string $valor): ?string
    {
        $erro = null;
        $rule = new CpfCnpj();
        $rule->validate('cpf_cnpj', $valor, function (string $message) use (&$erro) {
            $erro = $message;
        });

        return $erro;
    }

    public function test_aceita_cpf_valido_com_e_sem_mascara(): void
    {
        $this->assertNull($this->validarCpfCnpj('52998224725'));
        $this->assertNull($this->validarCpfCnpj('529.982.247-25'));
    }

    public function test_rejeita_cpf_com_digitos_repetidos_ou_verificador_invalido(): void
    {
        $this->assertSame('O CPF informado é inválido.', $this->validarCpfCnpj('111.111.111-11'));
        $this->assertSame('O CPF informado é inválido.', $this->validarCpfCnpj('529.982.247-00'));
    }

    public function test_aceita_cnpj_numerico_valido_com_e_sem_mascara(): void
    {
        $this->assertNull($this->validarCpfCnpj('11222333000181'));
        $this->assertNull($this->validarCpfCnpj('11.222.333/0001-81'));
    }

    public function test_rejeita_cnpj_invalido_ou_tamanho_incorreto(): void
    {
        $this->assertSame('O CNPJ informado é inválido.', $this->validarCpfCnpj('11.222.333/0001-00'));
        $this->assertSame('O campo deve conter um CPF (11 dígitos) ou CNPJ (14 caracteres) válido.', $this->validarCpfCnpj('12345'));
    }

    public function test_formata_corretamente_duracoes_do_agendamento(): void
    {
        $this->assertSame('0min', Agendamento::formatarMinutos(0));
        $this->assertSame('45min', Agendamento::formatarMinutos(45));
        $this->assertSame('2h 00min', Agendamento::formatarMinutos(120));
        $this->assertSame('2h 30min', Agendamento::formatarMinutos(150));
    }
}
