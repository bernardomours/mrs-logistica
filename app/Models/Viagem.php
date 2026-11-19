<?php

namespace App\Models;

use App\Enums\ViagemStatus;
use App\Models\Concerns\PertenceAFilial;
use App\Support\SemanaIso;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'filial_id', 'motorista_id', 'veiculo_id', 'origem_local_id', 'destino_local_id', 'tarifa_rota_id',
    'data_viagem', 'valor_frete', 'comissao_percentual', 'comissao_valor', 'status',
    'observacoes',
])]
class Viagem extends Model
{
    use HasFactory, LogsActivity, PertenceAFilial, SoftDeletes;

    protected $table = 'viagens';

    protected function casts(): array
    {
        return [
            'data_viagem' => 'date',
            'valor_frete' => 'decimal:2',
            'comissao_percentual' => 'decimal:2',
            'comissao_valor' => 'decimal:2',
            'status' => ViagemStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('viagens');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'origem_local_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'destino_local_id');
    }

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(TarifaRota::class, 'tarifa_rota_id')->withTrashed();
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Só viagens concluídas entram nos totais financeiros (RN-03). */
    public function scopeConcluidas(Builder $query): Builder
    {
        return $query->where('status', ViagemStatus::Concluida->value);
    }

    public function scopeDoMotorista(Builder $query, int $motoristaId): Builder
    {
        return $query->where('motorista_id', $motoristaId);
    }

    public function scopeDaSemana(Builder $query, SemanaIso $semana): Builder
    {
        return $query->whereBetween('data_viagem', [$semana->inicio->toDateString(), $semana->fim->toDateString()]);
    }

    public function semana(): SemanaIso
    {
        return SemanaIso::deData($this->data_viagem);
    }

    /** O valor difere da tarifa vigente que foi usada como sugestão? (RN-13) */
    public function divergeDaTarifa(): bool
    {
        return $this->tarifa !== null && bccomp((string) $this->valor_frete, (string) $this->tarifa->valor_frete, 2) !== 0;
    }

    public function estaCancelada(): bool
    {
        return $this->status === ViagemStatus::Cancelada;
    }
}
