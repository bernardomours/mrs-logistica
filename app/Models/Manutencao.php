<?php

namespace App\Models;

use App\Enums\ManutencaoTipo;
use App\Models\Concerns\PertenceAFilial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'filial_id', 'veiculo_id', 'motorista_id', 'data_manutencao', 'tipo', 'descricao', 'oficina', 'odometro_km',
    'valor_pecas', 'valor_mao_obra', 'valor_total', 'nota_fiscal', 'proxima_km', 'proxima_data', 'observacoes',
])]
class Manutencao extends Model
{
    use HasFactory, LogsActivity, PertenceAFilial, SoftDeletes;

    protected $table = 'manutencoes';

    protected function casts(): array
    {
        return [
            'data_manutencao' => 'date',
            'proxima_data' => 'date',
            'tipo' => ManutencaoTipo::class,
            'odometro_km' => 'decimal:1',
            'valor_pecas' => 'decimal:2',
            'valor_mao_obra' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'proxima_km' => 'decimal:1',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('manutencoes');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDoVeiculo(Builder $query, int $veiculoId): Builder
    {
        return $query->where('veiculo_id', $veiculoId);
    }

    /** Manutenções entre as datas (inclusive) — usado na linha informativa do fechamento (RN-20). */
    public function scopeNoPeriodo(Builder $query, string $inicio, string $fim): Builder
    {
        return $query->whereBetween('data_manutencao', [$inicio, $fim]);
    }
}
