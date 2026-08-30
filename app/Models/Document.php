<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'document_date' => 'date',
            'expires_on' => 'date',
            'status' => \App\Enums\DocumentStatus::class,
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Datenscope: Externe sehen nur Dokumente, die mit einer ihrer
     * Entities oder deren Darlehen verknüpft sind.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }
        $ids = $user->accessibleEntityIds()->all();

        return $query->whereHas('links', function (Builder $q) use ($ids) {
            $q->where(function (Builder $inner) use ($ids) {
                $inner->where('linkable_type', Entity::class)->whereIn('linkable_id', $ids);
            })->orWhere(function (Builder $inner) use ($ids) {
                $inner->where('linkable_type', Loan::class)->whereIn(
                    'linkable_id',
                    Loan::query()->whereIn('lender_entity_id', $ids)
                        ->orWhereIn('borrower_entity_id', $ids)->select('id'),
                );
            });
        });
    }
}
