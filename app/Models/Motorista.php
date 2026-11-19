<?php

namespace App\Models;

use App\Models\Concerns\PertenceAFilial;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'filial_id', 'nome', 'cpf', 'cnh_numero', 'cnh_categoria', 'cnh_validade', 'telefone',
    'comissao_percentual', 'data_admissao', 'ativo', 'observacoes',
])]
class Motorista extends Model
{
    use HasFactory, LogsActivity, PertenceAFilial;

    protected $table = 'motoristas';

    protected function casts(): array
    {
        return [
            'comissao_percentual' => 'decimal:2',
            'cnh_validade' => 'date',
            'data_admissao' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('motoristas');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function cnhVencida(): bool
    {
        return $this->cnh_validade !== null && $this->cnh_validade->isPast();
    }

    public function setCpfAttribute(string $value): void
    {
        $this->attributes['cpf'] = Texto::digitos($value);
    }

    public function setCnhCategoriaAttribute(?string $value): void
    {
        $this->attributes['cnh_categoria'] = $value === null || $value === '' ? null : mb_strtoupper($value);
    }
}
