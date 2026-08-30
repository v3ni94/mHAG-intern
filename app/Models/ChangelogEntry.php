<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangelogEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['released_on' => 'date'];
    }
}
