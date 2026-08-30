<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Person extends Model
{
    protected $table = 'persons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->title, $this->first_name, $this->last_name])));
    }
}
