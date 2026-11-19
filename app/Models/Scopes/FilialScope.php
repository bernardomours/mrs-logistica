<?php

namespace App\Models\Scopes;

use App\Services\FilialAtual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restringe consultas às filiais que o usuário pode ver (RN-18).
 * Console/jobs sem usuário autenticado não sofrem restrição; requisições web
 * sem usuário não enxergam nada.
 */
class FilialScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $filiais = app(FilialAtual::class);

        if ($filiais->usuario() === null) {
            if (! app()->runningInConsole()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $ids = $filiais->idsParaConsulta();

        if ($ids !== null) {
            $builder->whereIn($model->qualifyColumn('filial_id'), $ids);
        }
    }
}
