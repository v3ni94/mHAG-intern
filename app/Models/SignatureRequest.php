<?php

namespace App\Models;

use App\Enums\SignatureRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SignatureRequest extends Model
{
    protected $guarded = ['id'];

    /** Vorgangsarten, die unterschrieben werden koennen. */
    public const SUBJECT_CLASSES = [
        Resolution::class,
        Contract::class,
        ShareTransaction::class,
        ShareholderListSnapshot::class,
    ];

    /**
     * Datenscope (Abschnitt 13, Nachtrag vom 05.09.2026).
     *
     * Eine Signaturanfrage ist so sichtbar wie der Vorgang, zu dem sie
     * gehoert. Eine Anfrage ohne auffindbaren Vorgang bleibt verborgen: im
     * Zweifel nichts zeigen.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }

        return $query->whereHasMorph(
            'subject',
            self::SUBJECT_CLASSES,
            fn (Builder $q) => $q->visibleTo($user),
        );
    }

    protected function casts(): array
    {
        return ['status' => SignatureRequestStatus::class];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SignatureParticipant::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
