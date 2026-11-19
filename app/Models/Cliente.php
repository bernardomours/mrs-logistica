<?php

namespace App\Models;

use App\Support\Texto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['razao_social', 'nome_fantasia', 'cnpj', 'telefone', 'email', 'ativo'])]
class Cliente extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'clientes';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('clientes');
    }

    public function locais(): HasMany
    {
        return $this->hasMany(Local::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function getNomeExibicaoAttribute(): string
    {
        return $this->nome_fantasia ?: $this->razao_social;
    }

    public function setCnpjAttribute(?string $value): void
    {
        $digitos = Texto::digitos($value);
        $this->attributes['cnpj'] = $digitos === '' ? null : $digitos;
    }

    public function setTelefoneAttribute(?string $value): void
    {
        $this->attributes['telefone'] = $value === null || trim($value) === '' ? null : trim($value);
    }
}
