<?php

declare(strict_types=1);

namespace AIArmada\References\Models;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property string $id
 * @property ReferenceType $type
 * @property ReferenceStatus $status
 * @property string $title
 * @property string $slug
 * @property string|null $author
 * @property string|null $publisher
 * @property int|null $year
 * @property string|null $isbn
 * @property string|null $description
 * @property string|null $url
 * @property string|null $language
 * @property string|null $parent_id
 * @property array|null $reference_parts
 * @property array|null $metadata
 * @property bool $is_canonical
 * @property CarbonImmutable|null $published_at
 * @property-read Reference|null $parent
 * @property-read Collection<int, Reference> $children
 */
class Reference extends Model implements HasMedia
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSlug;
    use HasUuids;
    use InteractsWithMedia;

    protected static string $ownerScopeConfigKey = 'references.owner';

    protected $fillable = [
        'type',
        'status',
        'title',
        'slug',
        'author',
        'publisher',
        'year',
        'isbn',
        'description',
        'url',
        'language',
        'parent_id',
        'reference_parts',
        'metadata',
        'is_canonical',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (Reference $reference): void {
            $parentId = $reference->getAttribute('parent_id');

            if ($parentId === null || ! (bool) config('references.owner.enabled', false)) {
                return;
            }

            OwnerWriteGuard::findOrFailForOwner(
                self::class,
                (string) $parentId,
                includeGlobal: (bool) config('references.owner.include_global', false),
            );
        });
    }

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return null;
        }

        return $this->getConnection()->transaction(function (): ?bool {
            if ($this->fireModelEvent('deleting') === false) {
                return false;
            }

            $referenceIds = $this->collectSubtreeIds();

            Media::query()
                ->where('model_type', $this->getMorphClass())
                ->whereIn('model_id', $referenceIds)
                ->get()
                ->each(static fn (Media $media): ?bool => $media->delete());

            $deleted = static::query()
                ->whereKey($referenceIds)
                ->delete();

            $this->fireModelEvent('deleted', false);

            return $deleted > 0;
        });
    }

    public function getTable(): string
    {
        return config('references.database.tables.references', 'ref_references');
    }

    public function getSlugOptions(): SlugOptions
    {
        $source = config('references.slug.source');

        if (! is_string($source) || $source === '' || ! in_array($source, $this->getFillable(), true)) {
            throw new InvalidArgumentException('references.slug.source must name a fillable reference attribute.');
        }

        $cast = $this->getCasts()[$source] ?? null;
        $nonStringCasts = [
            'array',
            'bool',
            'boolean',
            'collection',
            'date',
            'datetime',
            'decimal',
            'double',
            'float',
            'immutable_date',
            'immutable_datetime',
            'int',
            'integer',
            'json',
            'real',
        ];

        if (is_string($cast) && (in_array($cast, $nonStringCasts, true) || enum_exists($cast))) {
            throw new InvalidArgumentException('references.slug.source must name a string reference attribute.');
        }

        $maxLength = config('references.slug.max_length');

        if (! is_int($maxLength) && ! is_numeric($maxLength)) {
            throw new InvalidArgumentException('references.slug.max_length must be a positive integer.');
        }

        $maxLength = (int) $maxLength;

        if ($maxLength < 1) {
            throw new InvalidArgumentException('references.slug.max_length must be a positive integer.');
        }

        return SlugOptions::create()
            ->generateSlugsFrom($source)
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan($maxLength);
    }

    protected function casts(): array
    {
        return [
            'type' => ReferenceType::class,
            'status' => ReferenceStatus::class,
            'published_at' => 'immutable_datetime',
            'reference_parts' => 'array',
            'metadata' => 'array',
            'year' => 'integer',
            'is_canonical' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReferenceStatus::Published);
    }

    public function scopeByType(Builder $query, ReferenceType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('front_cover')
            ->useDisk(config('references.media.disk'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('back_cover')
            ->useDisk(config('references.media.disk'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk(config('references.media.disk'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages();
    }

    /**
     * @return list<string>
     */
    private function collectSubtreeIds(): array
    {
        $ids = [(string) $this->getKey()];
        $frontier = $ids;

        while ($frontier !== []) {
            $childIds = static::query()
                ->whereIn('parent_id', $frontier)
                ->pluck($this->getKeyName())
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

            $frontier = [];

            foreach ($childIds as $childId) {
                if (in_array($childId, $ids, true)) {
                    continue;
                }

                $ids[] = $childId;
                $frontier[] = $childId;
            }
        }

        return $ids;
    }
}
