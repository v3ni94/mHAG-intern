<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyFact extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'specific_date' => 'date',
            'recurring' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
