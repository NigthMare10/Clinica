<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuid;

    protected $fillable = ['key', 'value', 'is_public'];

    protected function casts(): array
    {
        return ['value' => 'array', 'is_public' => 'boolean'];
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }
}
