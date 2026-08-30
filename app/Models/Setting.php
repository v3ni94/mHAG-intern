<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * Wert lesen. value wird als JSON gespeichert; skalare Werte liegen unter ['v'].
     */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('group', $group)->where('key', $key)->first();
        if (! $row) {
            return $default;
        }
        $value = $row->value;

        return is_array($value) && array_key_exists('v', $value) && count($value) === 1 ? $value['v'] : $value;
    }

    public static function set(string $group, string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => is_array($value) ? $value : ['v' => $value]],
        );
    }
}
