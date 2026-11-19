<?php

namespace App\Models;

use App\Enums\FechamentoStatus;
use App\Models\Concerns\PertenceAFilial;
use App\Services\TravaDeSemana;
use App\Support\SemanaIso;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Snapshot do acerto semanal de um motorista (RN-11). Existir = semana travada para viagens e abastecimentos.
 */
#[Fillable([
    'filial_id', 'motorista_id', 'ano_iso', 'semana_iso', 'data_inicio', 'data_fim', 'status',
    'qtd_viagens', 'total_faturamento', 'total_comissao', 'valor_a_pagar', 'total_abastecimento', 'total_liquido',
    'total_litros', 'total_km', 'media_km_l_simples', 'media_km_l_ponderada', 'observacoes',
])]
class FechamentoSemanal extends Model
{
    use HasFactory, LogsActivity, PertenceAFilial;

    protected $table = 'fechamentos_semanais';

    protected static function booted(): void
    {
        // O cache da trava (por requisição) nunca pode ficar velho depois de fechar/reabrir uma semana.
        static::saved(fn () => app(TravaDeSemana::class)->esquecer());
        static::deleted(fn () => app(TravaDeSemana::class)->esquecer());
    }

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'status' => FechamentoStatus::class,
            'total_faturamento' => 'decimal:2',
            'total_comissao' => 'decimal:2',
            'valor_a_pagar' => 'decimal:2',
            'total_abastecimento' => 'decimal:2',
            'total_liquido' => 'decimal:2',
            'total_litros' => 'decimal:3',
            'total_km' => 'decimal:1',
            'media_km_l_simples' => 'decimal:4',
            'media_km_l_ponderada' => 'decimal:4',
            'fechado_em' => 'datetime',
            'pago_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['motorista_id', 'ano_iso', 'semana_iso', 'status', 'total_faturamento', 'total_comissao', 'valor_a_pagar', 'total_abastecimento', 'total_liquido', 'pago_em', 'observacoes'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('fechamentos');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    public function pagoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_por');
    }

    public function scopeDaSemana(Builder $query, SemanaIso $semana): Builder
    {
        return $query->where('ano_iso', $semana->ano)->where('semana_iso', $semana->semana);
    }

    public function semana(): SemanaIso
    {
        return SemanaIso::de($this->ano_iso, $this->semana_iso);
    }

    public function estaPago(): bool
    {
        return $this->status === FechamentoStatus::Pago;
    }
}
