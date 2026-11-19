<?php

namespace App\Models;

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
    'filial_id', 'veiculo_id', 'motorista_id', 'posto_id', 'data_abastecimento', 'litros', 'preco_litro',
    'valor_total', 'odometro_km', 'km_anterior', 'km_rodados', 'media_km_l', 'tanque_cheio',
    'combustivel', 'nota_fiscal', 'observacoes',
])]
class Abastecimento extends Model
{
    use HasFactory, LogsActivity, PertenceAFilial, SoftDeletes;

    protected $table = 'abastecimentos';

    protected function casts(): array
    {
        return [
            'data_abastecimento' => 'date',
            'litros' => 'decimal:3',
            'preco_litro' => 'decimal:3',
            'valor_total' => 'decimal:2',
            'odometro_km' => 'decimal:1',
            'km_anterior' => 'decimal:1',
            'km_rodados' => 'decimal:1',
            'media_km_l' => 'decimal:4',
            'tanque_cheio' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Colunas derivadas da cadeia de hodômetro (km_anterior, km_rodados, media_km_l) não são auditadas.
        return LogOptions::defaults()
            ->logOnly(['veiculo_id', 'motorista_id', 'posto_id', 'data_abastecimento', 'litros', 'preco_litro', 'valor_total', 'odometro_km', 'tanque_cheio', 'combustivel', 'nota_fiscal', 'observacoes'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('abastecimentos');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDoMotorista(Builder $query, int $motoristaId): Builder
    {
        return $query->where('motorista_id', $motoristaId);
    }

    public function scopeDaSemana(Builder $query, SemanaIso $semana): Builder
    {
        return $query->whereBetween('data_abastecimento', [$semana->inicio->toDateString(), $semana->fim->toDateString()]);
    }

    /** Média válida para as agregações: tanque cheio e média calculada (RN-08/RN-09). */
    public function scopeComMediaValida(Builder $query): Builder
    {
        return $query->where('tanque_cheio', true)->whereNotNull('media_km_l');
    }

    public function semana(): SemanaIso
    {
        return SemanaIso::deData($this->data_abastecimento);
    }

    /**
     * Alertas que não bloqueiam (RN-13).
     *
     * @return array<int, string>
     */
    public function alertas(): array
    {
        $alertas = [];

        if ($this->media_km_l !== null) {
            $media = (float) $this->media_km_l;

            if ($media < (float) config('frota.media_km_l_min') || $media > (float) config('frota.media_km_l_max')) {
                $alertas[] = 'Média de '.number_format($media, 2, ',', '.').' km/l fora da faixa esperada.';
            }
        }

        $capacidade = $this->veiculo?->capacidade_tanque_l;

        if ($capacidade !== null && bccomp((string) $this->litros, (string) $capacidade, 3) > 0) {
            $alertas[] = 'Litros acima da capacidade do tanque.';
        }

        return $alertas;
    }
}
