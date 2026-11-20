<?php

namespace App\Livewire\Usuarios;

use App\Actions\Usuarios\AlternarAtivoUsuario;
use App\Actions\Usuarios\ExcluirUsuario;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Concerns\WithFiltros;
use App\Livewire\Concerns\WithOrdenacao;
use App\Models\User;
use App\Services\GestaoDeUsuarios;
use App\Support\Texto;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Usuários')]
class Index extends Component
{
    use EmiteToast, WithFiltros, WithOrdenacao;

    #[Url(history: true)]
    public string $papel = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['name', 'email', 'ativo', 'ultimo_login_at'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'name';
    }

    protected function filtrosPadrao(): array
    {
        return ['busca' => '', 'status' => 'todos', 'papel' => ''];
    }

    public function updatedPapel(): void
    {
        $this->resetPage();
    }

    private function consulta(): Builder
    {
        return app(GestaoDeUsuarios::class)->consultaVisivelPara(auth()->user());
    }

    public function alternarAtivo(int $id): void
    {
        $alvo = $this->consulta()->findOrFail($id);

        Gate::authorize('desativar', $alvo);

        try {
            $alvo = app(AlternarAtivoUsuario::class)->handle(auth()->user(), $alvo);
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'erro');

            return;
        }

        $this->toast("Usuário {$alvo->name}".($alvo->ativo ? ' ativado.' : ' desativado.'));
    }

    public function excluir(int $id): void
    {
        $alvo = $this->consulta()->findOrFail($id);

        Gate::authorize('delete', $alvo);

        try {
            app(ExcluirUsuario::class)->handle(auth()->user(), $alvo);
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'erro');

            return;
        }

        $this->toast("Usuário {$alvo->name} excluído.");
    }

    public function render(): View
    {
        $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($this->busca)).'%';
        $digitos = Texto::digitos($this->busca);

        $usuarios = $this->consulta()
            ->with(['roles:id,name', 'filiais:id,nome,codigo'])
            ->when($this->busca !== '', fn (Builder $q) => $q->where(function (Builder $busca) use ($termo, $digitos) {
                $busca->where('name', 'like', $termo)->orWhere('email', 'like', $termo);

                if ($digitos !== '') {
                    $busca->orWhere('cpf', 'like', "%{$digitos}%");
                }
            }))
            ->when($this->status === 'ativos', fn (Builder $q) => $q->where('ativo', true))
            ->when($this->status === 'inativos', fn (Builder $q) => $q->where('ativo', false))
            ->when($this->papel !== '', fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $this->papel)))
            ->orderBy($this->colunaAtual(), $this->direcaoAtual())
            ->orderBy('id')
            ->paginate(15);

        return view('livewire.usuarios.index', [
            'usuarios' => $usuarios,
            'papeis' => app(GestaoDeUsuarios::class)->papeisAtribuiveis(auth()->user()),
        ]);
    }
}
