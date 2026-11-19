<?php

namespace App\Models;

use App\Enums\LocalTipo;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nome', 'tipo', 'cidade', 'uf', 'cliente_id', 'ativo'])]
class Local extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'locais';

    protected function casts(): array
    {
        return [
            'tipo' => LocalTipo::class,
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Local $local) {
            $local->nome_chave = Texto::chave($local->nome);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('locais');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function setUfAttribute(?string $value): void
    {
        $this->attributes['uf'] = $value === null || $value === '' ? null : mb_strtoupper($value);
    }
}
