<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['cliente_id', 'origem_local_id', 'destino_local_id', 'valor_frete', 'vigencia_inicio', 'vigencia_fim', 'observacoes'])]
class TarifaRota extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'tarifas_rota';

    protected function casts(): array
    {
        return [
            'valor_frete' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('tarifas');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'origem_local_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'destino_local_id');
    }

    /** Tarifas vigentes na data informada (RN-10). */
    public function scopeVigentesEm(Builder $query, Carbon|string $data): Builder
    {
        $data = Carbon::parse($data)->toDateString();

        return $query->whereDate('vigencia_inicio', '<=', $data)
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>=', $data));
    }

    public function estaVigente(): bool
    {
        return $this->vigencia_inicio->isPast() && ($this->vigencia_fim === null || $this->vigencia_fim->endOfDay()->isFuture());
    }
}
