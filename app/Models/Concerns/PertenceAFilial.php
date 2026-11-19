<?php

namespace App\Models\Concerns;

use App\Models\Filial;
use App\Models\Scopes\FilialScope;
use App\Services\FilialAtual;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para models com `filial_id` (motoristas, veículos, viagens, abastecimentos, manutenções, fechamentos).
 */
trait PertenceAFilial
{
    public static function bootPertenceAFilial(): void
    {
        static::addGlobalScope(new FilialScope);

        static::creating(function ($model) {
            if ($model->filial_id === null) {
                $model->filial_id = app(FilialAtual::class)->paraNovoLancamento();
            }
        });
    }

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }
}
