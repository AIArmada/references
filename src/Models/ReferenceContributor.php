<?php

declare(strict_types=1);

namespace AIArmada\References\Models;

use AIArmada\References\Enums\ReferenceContributorRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $reference_id
 * @property string $contributor_type
 * @property string $contributor_id
 * @property ReferenceContributorRole $role
 * @property-read Reference $reference
 * @property-read Model $contributor
 */
class ReferenceContributor extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference_id',
        'contributor_type',
        'contributor_id',
        'role',
    ];

    public function getTable(): string
    {
        return config('references.database.tables.reference_contributors', 'reference_contributors');
    }

    protected function casts(): array
    {
        return [
            'role' => ReferenceContributorRole::class,
        ];
    }

    /**
     * @return BelongsTo<Reference, $this>
     */
    public function reference(): BelongsTo
    {
        return $this->belongsTo(Reference::class, 'reference_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function contributor(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'contributor_type', 'contributor_id');
    }

    /**
     * @param  Builder<ReferenceContributor>  $query
     * @return Builder<ReferenceContributor>
     */
    public function scopeForRole(Builder $query, ReferenceContributorRole | string $role): Builder
    {
        return $query->where($query->qualifyColumn('role'), $role instanceof ReferenceContributorRole ? $role->value : $role);
    }
}
