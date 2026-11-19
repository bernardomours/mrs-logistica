<?php

namespace App\Models;

use App\Enums\VeiculoTipo;
use App\Models\Concerns\PertenceAFilial;
use App\Rules\Placa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'filial_id', 'placa', 'tipo', 'marca', 'modelo', 'ano_fabricacao', 'renavam', 'capacidade_tanque_l',
    'odometro_inicial', 'consumo_referencia_km_l', 'motorista_padrao_id', 'ativo',
])]
class Veiculo extends Model
{
    use HasFactory, LogsActivity, PertenceAFilial;

    protected $table = 'veiculos';

    protected function casts(): array
    {
        return [
            'tipo' => VeiculoTipo::class,
            'capacidade_tanque_l' => 'decimal:2',
            'odometro_inicial' => 'decimal:1',
            'consumo_referencia_km_l' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('veiculos');
    }

    public function motoristaPadrao(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_padrao_id');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** "ABC1D23" => "ABC-1D23" para exibição. */
    public function getPlacaFormatadaAttribute(): string
    {
        return substr($this->placa, 0, 3).'-'.substr($this->placa, 3);
    }

    public function setPlacaAttribute(string $value): void
    {
        $this->attributes['placa'] = Placa::normalizar($value);
    }
}
