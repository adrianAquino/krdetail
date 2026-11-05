<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\OrdemServicoController;
use App\Http\Controllers\TarefaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\EstoqueController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\ConfiguracaoController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Autenticação e Auto-Cadastro (Visitantes)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/cadastro', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/cadastro', [AuthController::class, 'register'])->name('register.post');
});

// Sistema (Usuários Autenticados: Equipe e Clientes)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard (Adaptado por perfil: Equipe vs Portal do Cliente)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Veículos (Clientes acessam apenas seus próprios veículos; equipe acessa todos)
    Route::get('/veiculos', [VeiculoController::class, 'index'])->name('veiculos.index');
    Route::get('/veiculos/create', [VeiculoController::class, 'create'])->name('veiculos.create');
    Route::post('/veiculos', [VeiculoController::class, 'store'])->name('veiculos.store');
    Route::get('/veiculos/{veiculo}', [VeiculoController::class, 'show'])->name('veiculos.show');
    Route::get('/veiculos/{veiculo}/edit', [VeiculoController::class, 'edit'])->name('veiculos.edit');
    Route::put('/veiculos/{veiculo}', [VeiculoController::class, 'update'])->name('veiculos.update');

    // Agendamentos (Clientes visualizam andamento/histórico e criam agendamentos próprios)
    Route::get('/agendamentos', [AgendamentoController::class, 'index'])->name('agendamentos.index');
    Route::get('/agendamentos/create', [AgendamentoController::class, 'create'])->name('agendamentos.create');
    Route::post('/agendamentos', [AgendamentoController::class, 'store'])->name('agendamentos.store');
    Route::get('/agendamentos/{agendamento}', [AgendamentoController::class, 'show'])->name('agendamentos.show');

    // Rotas Exclusivas da Equipe Interna (Administrador / Funcionário)
    Route::middleware('staff')->group(function () {
        Route::delete('/veiculos/{veiculo}', [VeiculoController::class, 'destroy'])->name('veiculos.destroy');

        Route::get('/agendamentos/{agendamento}/edit', [AgendamentoController::class, 'edit'])->name('agendamentos.edit');
        Route::put('/agendamentos/{agendamento}', [AgendamentoController::class, 'update'])->name('agendamentos.update');
        Route::delete('/agendamentos/{agendamento}', [AgendamentoController::class, 'destroy'])->name('agendamentos.destroy');

        // Clientes
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/create', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
        Route::get('/clientes/{cliente}/edit', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');

        // Serviços
        Route::get('/servicos', [ServicoController::class, 'index'])->name('servicos.index');
        Route::get('/servicos/create', [ServicoController::class, 'create'])->name('servicos.create');
        Route::post('/servicos', [ServicoController::class, 'store'])->name('servicos.store');
        Route::get('/servicos/{servico}/edit', [ServicoController::class, 'edit'])->name('servicos.edit');
        Route::put('/servicos/{servico}', [ServicoController::class, 'update'])->name('servicos.update');
        Route::delete('/servicos/{servico}', [ServicoController::class, 'destroy'])->name('servicos.destroy');

        // Insumos & Produtos (Resource)
        Route::resource('produtos', ProdutoController::class);

        // Controle de Estoque
        Route::prefix('estoque')->name('estoque.')->group(function () {
            Route::get('/', [EstoqueController::class, 'index'])->name('index');
            Route::post('/movimentar', [EstoqueController::class, 'movimentar'])->name('movimentar');
        });

        // Ordens de Serviço
        Route::prefix('ordens-servico')->name('ordens-servico.')->group(function () {
            Route::get('/', [OrdemServicoController::class, 'index'])->name('index');
            Route::post('/', [OrdemServicoController::class, 'store'])->name('store');
            Route::get('/{ordemServico}', [OrdemServicoController::class, 'show'])->name('show');
            Route::match(['post', 'patch'], '/{ordemServico}/concluir', [OrdemServicoController::class, 'concluir'])->name('concluir');
        });

        // Tarefas de Detalhamento
        Route::prefix('tarefas')->name('tarefas.')->group(function () {
            Route::get('/{tarefa}', [TarefaController::class, 'show'])->name('show');
            Route::match(['post', 'patch'], '/{tarefa}/iniciar', [TarefaController::class, 'iniciar'])->name('iniciar');
            Route::match(['post', 'patch'], '/{tarefa}/concluir', [TarefaController::class, 'concluir'])->name('concluir');
            Route::match(['post', 'patch'], '/{tarefa}/atribuir', [TarefaController::class, 'atribuir'])->name('atribuir');
            Route::post('/{tarefa}/produtos', [TarefaController::class, 'adicionarProduto'])->name('adicionar-produto');
            Route::post('/{tarefa}/adicionar-produto', [TarefaController::class, 'adicionarProduto'])->name('adicionarProduto');
            Route::delete('/{tarefa}/produtos/{tarefaProduto}', [TarefaController::class, 'removerProduto'])->name('remover-produto');
        });

        // Relatórios Financeiros & Operacionais
        Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/geral', [RelatorioController::class, 'index'])->name('relatorios');

        // Configurações do Sistema
        Route::get('/configuracoes', [ConfiguracaoController::class, 'index'])->name('configuracoes.index');
        Route::get('/configuracoes/geral', [ConfiguracaoController::class, 'index'])->name('configuracoes');
        Route::post('/configuracoes/usuarios', [ConfiguracaoController::class, 'storeUsuario'])->name('configuracoes.usuarios.store');
        Route::put('/configuracoes/usuarios/{user}', [ConfiguracaoController::class, 'updateUsuario'])->name('configuracoes.usuarios.update');
        Route::patch('/configuracoes/usuarios/{user}/toggle-status', [ConfiguracaoController::class, 'toggleStatusUsuario'])->name('configuracoes.usuarios.toggle-status');
    });
});
