<?php

namespace App\Models;

use App\Support\Texto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nome', 'cnpj', 'cidade', 'uf', 'ativo'])]
class Posto extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'postos';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Posto $posto) {
            $posto->nome_chave = Texto::chave($posto->nome);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('postos');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function setCnpjAttribute(?string $value): void
    {
        $digitos = Texto::digitos($value);
        $this->attributes['cnpj'] = $digitos === '' ? null : $digitos;
    }

    public function setUfAttribute(?string $value): void
    {
        $this->attributes['uf'] = $value === null || $value === '' ? null : mb_strtoupper($value);
    }
}
