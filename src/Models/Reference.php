<?php

declare(strict_types=1);

namespace AIArmada\References\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Traits\HasReferenceParts;
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
    use HasReferenceParts;
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
            $reference->validateFields();
            $reference->validateParentHierarchy();
            $reference->guardSingleCanonical();
            $reference->stampPublishedAt();
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

            // Descendants go one row at a time, children first, so every row
            // fires its own model events and carries its media with it. The
            // cascade is hierarchy-complete by design: this row was obtained
            // through owner scoping, and no descendant is left orphaned. Each
            // row is removed inside its own owner context so the write guards
            // see the scope the row actually belongs to.
            foreach ($this->childrenFirst($this->collectSubtreeIds()) as $id) {
                if ((string) $id === (string) $this->getKey()) {
                    continue;
                }

                $child = static::query()->withoutOwnerScope()->whereKey($id)->first();

                if ($child instanceof self) {
                    /** @var Model|null $childOwner */
                    $childOwner = $child->getAttribute('owner');

                    OwnerContext::withOwner($childOwner, function () use ($child): void {
                        $child->deleteOwnRow();
                    });
                }
            }

            $this->deleteOwnMedia();

            $deleted = $this->newQueryWithoutScopes()->whereKey($this->getKey())->delete();

            $this->fireModelEvent('deleted', false);

            return $deleted > 0;
        });
    }

    public function getTable(): string
    {
        return config('references.database.tables.references', 'references');
    }

    /**
     * Move through the status lifecycle, keeping published_at in sync.
     */
    public function transitionStatus(ReferenceStatus $status): static
    {
        $this->status = $status;

        if ($status === ReferenceStatus::Draft) {
            $this->published_at = null;
        }

        $this->save();

        return $this;
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

    private function validateFields(): void
    {
        $raw = $this->getAttributes();

        $year = $raw['year'] ?? null;

        if ($year !== null && ! (is_int($year) || (is_string($year) && preg_match('/^-?\d+$/', $year) === 1))) {
            throw new InvalidArgumentException('Invalid year: must be an integer.');
        }

        if ($year !== null) {
            $yearInt = (int) $year;
            $maxYear = (int) CarbonImmutable::now()->format('Y') + 5;

            if ($yearInt < -3000 || $yearInt > $maxYear) {
                throw new InvalidArgumentException(sprintf('Invalid year: must be between -3000 and %d.', $maxYear));
            }
        }

        $isbn = $this->getAttribute('isbn');

        if ($isbn !== null && (! is_string($isbn) || mb_strlen($isbn) > 20)) {
            throw new InvalidArgumentException('Invalid isbn: must be a string of at most 20 characters.');
        }

        $url = $this->getAttribute('url');

        if ($url !== null) {
            if (! is_string($url) || mb_strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException('Invalid url: must be a URL of at most 255 characters.');
            }

            $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (! in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('Invalid url: only http and https URLs are accepted.');
            }
        }

        $language = $this->getAttribute('language');

        if ($language !== null && (! is_string($language) || mb_strlen($language) > 10)) {
            throw new InvalidArgumentException('Invalid language: must be a string of at most 10 characters.');
        }

        foreach (['reference_parts', 'metadata'] as $jsonAttribute) {
            $decoded = $this->getAttribute($jsonAttribute);

            if ($decoded === null) {
                continue;
            }

            if (! is_array($decoded)) {
                throw new InvalidArgumentException(sprintf('Invalid %s: must be a JSON object.', $jsonAttribute));
            }

            $encoded = json_encode($decoded);

            if ($encoded === false || mb_strlen($encoded) > 65535) {
                throw new InvalidArgumentException(sprintf('Invalid %s: payload exceeds 64KB.', $jsonAttribute));
            }
        }
    }

    private function validateParentHierarchy(): void
    {
        $parentId = $this->getAttribute('parent_id');

        if ($parentId === null) {
            return;
        }

        if ($this->exists && ! $this->isDirty('parent_id')) {
            return;
        }

        /** @var Reference|null $parent */
        $parent = static::query()->withoutOwnerScope()->whereKey($parentId)->first();

        if ($parent === null) {
            throw new InvalidArgumentException('Invalid parent_id: reference not found.');
        }

        if ($this->exists && (string) $parent->getKey() === (string) $this->getKey()) {
            throw new InvalidArgumentException('Invalid parent_id: a reference cannot be its own parent.');
        }

        $this->rejectHierarchyCycle($parent);

        if (! (bool) config('references.owner.enabled', false)) {
            return;
        }

        OwnerWriteGuard::findOrFailForOwner(
            self::class,
            (string) $parentId,
            includeGlobal: (bool) config('references.owner.include_global', false),
        );

        $parentGlobal = $parent->owner_type === null && $parent->owner_id === null;
        $includeGlobal = (bool) config('references.owner.include_global', false);

        [$selfType, $selfId] = $this->effectiveOwnerTuple();

        $sameOwner = $selfType === $parent->owner_type
            && ($selfId === null || $parent->owner_id === null
                ? $selfId === $parent->owner_id
                : (string) $selfId === (string) $parent->owner_id);

        if (! $sameOwner && ! ($parentGlobal && $includeGlobal)) {
            throw new InvalidArgumentException('Cross-tenant write blocked: reference parent does not belong to the same owner.');
        }
    }

    private function rejectHierarchyCycle(Reference $parent): void
    {
        if (! $this->exists) {
            return;
        }

        $selfKey = (string) $this->getKey();
        $seen = [(string) $parent->getKey()];
        $cursor = $parent;

        while ($cursor->parent_id !== null) {
            $cursorParentId = (string) $cursor->parent_id;

            if ($cursorParentId === $selfKey) {
                throw new InvalidArgumentException('Invalid parent_id: a reference cannot be moved below its own descendant.');
            }

            if (in_array($cursorParentId, $seen, true)) {
                break;
            }

            $seen[] = $cursorParentId;

            /** @var Reference|null $cursor */
            $cursor = static::query()->withoutOwnerScope()->whereKey($cursorParentId)->first();

            if ($cursor === null) {
                break;
            }
        }
    }

    private function guardSingleCanonical(): void
    {
        if (! $this->is_canonical) {
            return;
        }

        if ($this->exists && ! $this->isDirty('is_canonical')) {
            return;
        }

        [$selfType, $selfId] = $this->effectiveOwnerTuple();

        $query = static::query()->withoutOwnerScope()->where('is_canonical', true);

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        if ($selfType === null || $selfId === null) {
            $query->whereNull('owner_type')->whereNull('owner_id');
        } else {
            $query->where('owner_type', $selfType)->where('owner_id', $selfId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Only one canonical reference is allowed per owner.');
        }
    }

    private function stampPublishedAt(): void
    {
        if ($this->status === ReferenceStatus::Published && $this->published_at === null) {
            $this->published_at = CarbonImmutable::now();
        }
    }

    /**
     * @return array{0: string|null, 1: mixed}
     */
    private function effectiveOwnerTuple(): array
    {
        if ($this->owner_type !== null && $this->owner_id !== null) {
            return [$this->owner_type, $this->owner_id];
        }

        if ($this->exists) {
            return [$this->owner_type, $this->owner_id];
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return [null, null];
        }

        return [$owner->getMorphClass(), $owner->getKey()];
    }

    /**
     * Delete one row with its media and events (no subtree walk).
     */
    private function deleteOwnRow(): void
    {
        if ($this->fireModelEvent('deleting') === false) {
            return;
        }

        $this->deleteOwnMedia();
        $this->newQueryWithoutScopes()->whereKey($this->getKey())->delete();
        $this->fireModelEvent('deleted', false);
    }

    private function deleteOwnMedia(): void
    {
        Media::query()
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey())
            ->chunkById(100, static function ($media): void {
                $media->each(static fn (Media $item): ?bool => $item->delete());
            });
    }

    /**
     * Order subtree ids children-first via one parent map (cycle-safe).
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function childrenFirst(array $ids): array
    {
        /** @var array<string, string|null> $parents */
        $parents = static::query()->withoutOwnerScope()->whereKey($ids)
            ->pluck('parent_id', $this->getKeyName())
            ->map(static fn (mixed $parentId): ?string => $parentId === null ? null : (string) $parentId)
            ->all();

        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $id) use (&$visit, &$ordered, &$visiting, &$visited, $parents): void {
            if (isset($visited[$id]) || isset($visiting[$id])) {
                return;
            }

            $visiting[$id] = true;

            foreach ($parents as $childId => $parentId) {
                if ($parentId === $id) {
                    $visit((string) $childId);
                }
            }

            unset($visiting[$id]);
            $visited[$id] = true;
            $ordered[] = $id;
        };

        $visit((string) $this->getKey());

        foreach ($ids as $id) {
            $visit((string) $id);
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private function collectSubtreeIds(): array
    {
        $ids = [(string) $this->getKey()];
        $frontier = $ids;

        while ($frontier !== []) {
            $childIds = static::query()->withoutOwnerScope()
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
