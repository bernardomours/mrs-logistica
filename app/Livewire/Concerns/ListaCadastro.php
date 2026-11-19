<?php

namespace App\Livewire\Concerns;

use App\Actions\Cadastros\AlternarAtivo;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Comportamento comum das telas `Index` de cadastro (CLAUDE.md §8.1): autorização,
 * busca textual, filtro de situação, ordenação, paginação e ativar/desativar.
 */
trait ListaCadastro
{
    use EmiteToast, WithFiltros, WithOrdenacao;

    /** @return class-string<Model> */
    abstract protected function modelo(): string;

    /** Consulta base (já com eager loading). O escopo de filial vem do próprio model (RN-18). */
    abstract protected function consulta(): Builder;

    /** Nome exibido nos avisos, ex.: "Motorista João". */
    abstract protected function rotulo(object $registro): string;

    public function mount(): void
    {
        Gate::authorize('viewAny', $this->modelo());
    }

    public function alternarAtivo(int $id): void
    {
        $registro = $this->consulta()->findOrFail($id);

        Gate::authorize('desativar', $registro);

        try {
            $registro = app(AlternarAtivo::class)->handle($registro);
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'erro');

            return;
        }

        $this->toast($this->rotulo($registro).($registro->ativo ? ' ativado.' : ' desativado.'));
    }

    /**
     * @param  array<int, string>  $colunasDeBusca  Texto livre.
     * @param  array<int, string>  $colunasDeDocumento  Gravadas só com dígitos (cpf/cnpj): a busca ignora a pontuação.
     * @return LengthAwarePaginator<int, Model>
     */
    protected function paginar(Builder $query, array $colunasDeBusca, array $colunasDeDocumento = [], int $porPagina = 15): LengthAwarePaginator
    {
        return $query
            ->when($this->busca !== '', function (Builder $q) use ($colunasDeBusca, $colunasDeDocumento) {
                $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($this->busca)).'%';
                $digitos = Texto::digitos($this->busca);

                $q->where(function (Builder $busca) use ($colunasDeBusca, $colunasDeDocumento, $termo, $digitos) {
                    foreach ($colunasDeBusca as $coluna) {
                        $busca->orWhere($coluna, 'like', $termo);
                    }

                    if ($digitos !== '') {
                        foreach ($colunasDeDocumento as $coluna) {
                            $busca->orWhere($coluna, 'like', "%{$digitos}%");
                        }
                    }
                });
            })
            ->when($this->status === 'ativos', fn (Builder $q) => $q->where('ativo', true))
            ->when($this->status === 'inativos', fn (Builder $q) => $q->where('ativo', false))
            ->orderBy($this->colunaAtual(), $this->direcaoAtual())
            ->orderBy('id')
            ->paginate($porPagina);
    }
}
