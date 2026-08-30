<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEntityAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'context' => \App\Enums\AssignmentContext::class,
        ];
    }

    /**
     * Bezeichnung fuer den Ansichtsumschalter: die erfasste Bezeichnung, sonst
     * Name der Gesellschaft mit der Eigenschaft, in der gehandelt wird.
     */
    public function viewLabel(): string
    {
        if (trim((string) $this->label) !== '') {
            return (string) $this->label;
        }

        // Kein Nachladen erzwingen: ist die Beziehung nicht geladen, wird sie
        // hier bewusst geladen, damit die Beschriftung nie leer bleibt.
        $this->loadMissing('entity');
        $name = $this->entity?->display_name ?: 'Ohne Zuordnung';
        $kontext = $this->context instanceof \App\Enums\AssignmentContext ? $this->context : null;

        return $kontext && $kontext !== \App\Enums\AssignmentContext::Self
            ? $name.' ('.$kontext->shortLabel().')'
            : $name;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
