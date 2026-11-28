<?php

declare(strict_types=1);

namespace AIArmada\References\Models;

use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\References\Actions\DeleteReference;
use AIArmada\References\Actions\SaveReference;
use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferencePartType;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Persistence\ReferencePersistence;
use AIArmada\References\Rules\Isbn;
use AIArmada\References\Traits\HasReferenceParts;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Events\NullDispatcher;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlugWithUuids;
use Spatie\Sluggable\SlugOptions;

/**
 * @property string $id
 * @property ReferenceType $type
 * @property ReferenceStatus $status
 * @property string $title
 * @property string $slug
 * @property string|null $publisher
 * @property int|null $year
 * @property string|null $isbn
 * @property string|null $description
 * @property string|null $url
 * @property string|null $language
 * @property string|null $parent_id
 * @property array|null $reference_parts
 * @property array|null $metadata
 * @property ReferenceRecordKind $record_kind
 * @property int|null $edition_number
 * @property string|null $edition_label
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $last_state_change_at
 * @property string|null $verified_by
 * @property-read Reference|null $parent
 * @property-read Collection<int, Reference> $children
 * @property-read Collection<int, ReferenceContributor> $contributors
 */
class Reference extends ReferencePersistence implements HasMedia
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasReferenceParts;
    use HasSlugWithUuids;
    use InteractsWithMedia;

    protected static string $ownerScopeConfigKey = 'references.owner';

    protected $fillable = [
        'type',
        'status',
        'title',
        'slug',
        'publisher',
        'year',
        'isbn',
        'description',
        'url',
        'language',
        'parent_id',
        'reference_parts',
        'metadata',
        'record_kind',
        'edition_number',
        'edition_label',
        'published_at',
        'verified_at',
        'rejected_at',
        'last_state_change_at',
        'verified_by',
    ];

    protected $attributes = ['record_kind' => 'work', 'status' => 'draft'];

    protected function saveReference(Closure $save): bool
    {
        return (new SaveReference)->handle($this, $save);
    }

    public function delete(): ?bool
    {
        $dispatcher = static::getEventDispatcher();
        $eventsEnabled = $dispatcher !== null && ! $dispatcher instanceof NullDispatcher;

        return (new DeleteReference)->handle(
            $this,
            $eventsEnabled
                ? fn (Reference $record): bool => $record->fireModelEvent('deleting') !== false
                : static fn (): bool => true,
            $eventsEnabled ? fn (Reference $record): mixed => $record->fireModelEvent('deleted', false) : null,
        );
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public function withoutMediaCascadeDeletion(Closure $callback): mixed
    {
        $preserveMedia = $this->deletePreservingMedia;
        $this->deletePreservingMedia = true;

        try {
            return $callback();
        } finally {
            $this->deletePreservingMedia = $preserveMedia;
        }
    }

    public function getTable(): string
    {
        if (is_string($this->table) && $this->table !== '') {
            return $this->table;
        }

        $table = config('references.database.tables.references');

        return is_string($table) && $table !== '' ? $table : 'references';
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
            'verified_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'last_state_change_at' => 'immutable_datetime',
            'reference_parts' => 'array',
            'metadata' => 'array',
            'year' => 'integer',
            'record_kind' => ReferenceRecordKind::class,
            'edition_number' => 'integer',
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

    /**
     * @return HasMany<ReferenceContributor, $this>
     */
    public function contributors(): HasMany
    {
        $table = (new ReferenceContributor)->getTable();

        return $this->hasMany(ReferenceContributor::class, 'reference_id')
            ->orderBy($table . '.contributor_id')
            ->orderBy($table . '.id');
    }

    /**
     * @return HasMany<ReferenceContributor, $this>
     */
    public function contributorsForRole(ReferenceContributorRole | string $role): HasMany
    {
        $table = (new ReferenceContributor)->getTable();

        return $this->hasMany(ReferenceContributor::class, 'reference_id')->forRole($role)
            ->orderBy($table . '.contributor_id')
            ->orderBy($table . '.id');
    }

    /**
     * Resolve the work that owns this record's inherited contributors.
     */
    public function effectiveContributorReference(): static
    {
        $key = $this->getKey();

        if (! $this->exists || ! is_string($key) || ! Str::isUuid($key)) {
            throw new InvalidArgumentException('A persisted reference UUID is required.');
        }

        $record = $this->newQuery()->findOrFail($key);

        if ($this->isWorkKind($record)) {
            return $record;
        }

        $seen = [(string) $record->getKey()];
        $cursor = $record;

        for ($depth = 0; $depth < 10; $depth++) {
            $parentId = $cursor->parent_id;

            if (! is_string($parentId) || $parentId === '' || in_array($parentId, $seen, true)) {
                break;
            }

            $seen[] = $parentId;
            $parent = $this->resolveRelatedRecord($parentId, $cursor);

            if (! $parent instanceof static) {
                break;
            }

            $cursor = $parent;

            if ($this->isWorkKind($parent)) {
                return $parent;
            }
        }

        return $record;
    }

    /**
     * Contributors inherited from the owning work, stable by contributor ID.
     *
     * @return Collection<int, ReferenceContributor>
     */
    public function effectiveContributors(ReferenceContributorRole | string $role): Collection
    {
        $reference = $this->effectiveContributorReference();

        /** @var Collection<int, ReferenceContributor> $contributors */
        $contributors = $reference->contributorsForRole($role)
            ->with('contributor')
            ->get();

        return $contributors;
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

    public function prepareForPersistence(): void
    {
        $config = static::ownerScopeConfig();

        if ($config->enabled) {
            if ($this->exists) {
                static::guardOwnedOwnerWrite($this, $config, 'save');
                static::guardGlobalOwnerWrite($this, $config, 'save');
            } else {
                static::assignOwnerOnCreate($this, $config);
            }
        }

        $this->validateFields();

        if (! $this->exists && ($this->getAttribute('slug') === null || $this->getAttribute('slug') === '')) {
            $this->generateSlugOnCreate();
        }

        $this->validateHierarchyState();
        $this->syncPublishedAt();
    }

    private function validateFields(): void
    {
        $raw = $this->getAttributes();

        $type = $raw['type'] ?? null;

        if (! is_string($type) || ReferenceType::tryFrom($type) === null) {
            throw ValidationException::withMessages(['type' => 'Invalid reference type.']);
        }

        $status = $raw['status'] ?? null;

        if (! is_string($status) || ReferenceStatus::tryFrom($status) === null) {
            throw ValidationException::withMessages(['status' => 'Invalid reference status.']);
        }

        $title = $raw['title'] ?? null;

        if (! is_string($title) || mb_trim($title) === '' || mb_strlen($title) > 255) {
            throw ValidationException::withMessages(['title' => 'The title is required and must be at most 255 characters.']);
        }

        $kind = $raw['record_kind'] ?? null;

        if (! is_string($kind) || ReferenceRecordKind::tryFrom($kind) === null) {
            throw ValidationException::withMessages(['record_kind' => 'Invalid reference record kind.']);
        }

        $editionNumber = $raw['edition_number'] ?? null;

        if ($editionNumber !== null && (! (is_int($editionNumber) || (is_string($editionNumber) && ctype_digit($editionNumber))) || (int) $editionNumber < 1 || (int) $editionNumber > 2147483647)) {
            throw ValidationException::withMessages(['edition_number' => 'The edition number must be a positive integer.']);
        }

        $editionLabel = $raw['edition_label'] ?? null;

        if ($editionLabel !== null && (! is_string($editionLabel) || mb_strlen($editionLabel) > 255)) {
            throw ValidationException::withMessages(['edition_label' => 'The edition label must be at most 255 characters.']);
        }

        if ($kind !== ReferenceRecordKind::Edition->value && ($editionNumber !== null || $editionLabel !== null)) {
            throw ValidationException::withMessages(['record_kind' => 'Only editions can contain edition fields.']);
        }

        $year = $raw['year'] ?? null;

        if ($year !== null && ! (is_int($year) || (is_string($year) && preg_match('/^-?\d+$/', $year) === 1))) {
            throw ValidationException::withMessages(['year' => 'Invalid year: must be an integer.']);
        }

        if ($year !== null) {
            $yearInt = (int) $year;
            $maxYear = (int) CarbonImmutable::now()->format('Y') + 5;

            if ($yearInt < -3000 || $yearInt > $maxYear) {
                throw ValidationException::withMessages(['year' => sprintf('Invalid year: must be between -3000 and %d.', $maxYear)]);
            }
        }

        $isbn = $this->getAttribute('isbn');

        if (is_string($isbn)) {
            $isbn = Isbn::normalize($isbn);
            $this->setAttribute('isbn', $isbn);
        }

        if ($isbn !== null && (! is_string($isbn) || ! Isbn::isValid($isbn))) {
            throw ValidationException::withMessages(['isbn' => 'Invalid isbn: must be a valid ISBN-10 or ISBN-13.']);
        }

        $url = $this->getAttribute('url');

        if ($url !== null) {
            if (! is_string($url) || mb_strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages(['url' => 'Invalid url: must be a URL of at most 255 characters.']);
            }

            $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages(['url' => 'Invalid url: only http and https URLs are accepted.']);
            }
        }

        $language = $this->getAttribute('language');

        if ($language !== null && (! is_string($language) || mb_strlen($language) > 10)) {
            throw ValidationException::withMessages(['language' => 'Invalid language: must be a string of at most 10 characters.']);
        }

        foreach (['reference_parts', 'metadata'] as $jsonAttribute) {
            $decoded = $this->getAttribute($jsonAttribute);

            if ($decoded === null) {
                continue;
            }

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([$jsonAttribute => sprintf('Invalid %s: must be a JSON object.', $jsonAttribute)]);
            }

            if ($jsonAttribute === 'reference_parts') {
                $seen = [];

                foreach ($decoded as $part) {
                    $type = is_array($part) ? ($part['type'] ?? null) : null;

                    if (! is_string($type) || ReferencePartType::tryFrom($type) === null
                        || ! is_string($part['value'] ?? null) || isset($seen[$type])) {
                        throw ValidationException::withMessages(['reference_parts' => 'Invalid reference_parts: expected unique typed string entries.']);
                    }

                    $seen[$type] = true;
                }

                if (! array_is_list($decoded)) {
                    throw ValidationException::withMessages(['reference_parts' => 'Invalid reference_parts: expected a list.']);
                }
            }

            $encoded = json_encode($decoded);

            if ($encoded === false || mb_strlen($encoded) > 65535) {
                throw ValidationException::withMessages([$jsonAttribute => sprintf('Invalid %s: payload exceeds 64KB.', $jsonAttribute)]);
            }
        }
    }

    private function validateHierarchyState(): void
    {
        if (! $this->exists) {
            $this->validateParentHierarchy();

            return;
        }

        if ($this->isDirty('parent_id') || $this->isDirty('record_kind')) {
            $this->validateParentHierarchy();
        }
    }

    private function validateParentHierarchy(): void
    {
        $parentId = $this->getAttribute('parent_id');
        $kind = (string) ($this->getAttributes()['record_kind'] ?? '');

        if ($this->exists && $this->isDirty('record_kind')) {
            $allowedChildren = match ($kind) {
                'work' => ['edition', 'part'],
                'edition' => ['part'],
                default => [],
            };

            if ($this->newQuery()->withoutOwnerScope()->where('parent_id', $this->getKey())->whereNotIn('record_kind', $allowedChildren)->exists()) {
                throw ValidationException::withMessages(['record_kind' => 'The record kind would invalidate existing children.']);
            }

            if ($kind !== ReferenceRecordKind::Work->value && $this->contributorsForRole(ReferenceContributorRole::Author)->exists()) {
                throw ValidationException::withMessages(['record_kind' => 'Remove authorship before changing a work to another record kind.']);
            }
        }

        if ($parentId === null) {
            if ($kind !== 'work') {
                throw ValidationException::withMessages(['parent_id' => 'Editions and parts must have a parent reference.']);
            }

            return;
        }

        $this->assertParentUuid($parentId);

        /** @var Reference|null $parent */
        $parent = $this->newQuery()->withoutOwnerScope()->whereKey($parentId)->first();

        if ($parent === null) {
            throw ValidationException::withMessages(['parent_id' => 'Invalid parent_id: reference not found or inaccessible.']);
        }

        if ($this->exists && (string) $parent->getKey() === (string) $this->getKey()) {
            throw ValidationException::withMessages(['parent_id' => 'Invalid parent_id: a reference cannot be its own parent.']);
        }

        $this->rejectHierarchyCycle($parent);

        $parentKind = (string) $parent->getAttributes()['record_kind'];
        $validParent = match ($kind) {
            'edition' => $parentKind === 'work',
            'part' => in_array($parentKind, ['work', 'edition'], true),
            default => false,
        };

        if (! $validParent) {
            throw ValidationException::withMessages(['parent_id' => 'Editions must belong to a work; parts must belong to a work or edition.']);
        }

        $config = static::ownerScopeConfig();

        if (! $config->enabled) {
            return;
        }

        [$selfType, $selfId] = $this->ownerTuple($this);
        [$parentType, $parentIdValue] = $this->ownerTuple($parent);

        if (($selfType === null) !== ($selfId === null) || ($parentType === null) !== ($parentIdValue === null)) {
            throw ValidationException::withMessages(['parent_id' => 'Reference owner tuple is malformed.']);
        }

        $parentGlobal = $parentType === null;
        $sameOwner = $selfType === $parentType
            && ($selfId === null || $parentIdValue === null
                ? $selfId === $parentIdValue
                : (string) $selfId === (string) $parentIdValue);

        if (! $sameOwner && ! ($parentGlobal && $config->includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal())) {
            throw ValidationException::withMessages(['parent_id' => 'Cross-tenant write blocked: reference parent does not belong to the same owner.']);
        }
    }

    private function assertParentUuid(mixed $parentId): void
    {
        if (! is_string($parentId) || ! Str::isUuid($parentId)) {
            throw ValidationException::withMessages(['parent_id' => 'Invalid parent_id: must be a UUID.']);
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
                throw ValidationException::withMessages(['parent_id' => 'Invalid parent_id: a reference cannot be moved below its own descendant.']);
            }

            if (in_array($cursorParentId, $seen, true)) {
                throw ValidationException::withMessages(['parent_id' => 'Invalid parent_id: existing hierarchy contains a cycle.']);
            }

            $seen[] = $cursorParentId;

            $cursor = $this->resolveRelatedRecord($cursorParentId, $cursor);

            if ($cursor === null) {
                break;
            }
        }
    }

    private function syncPublishedAt(): void
    {
        if ($this->status === ReferenceStatus::Draft) {
            $this->published_at = null;

            return;
        }

        if ($this->status === ReferenceStatus::Published && $this->published_at === null) {
            $this->published_at = CarbonImmutable::now();
        }
    }

    private function resolveRelatedRecord(string $parentId, Reference $child): ?static
    {
        if (! Str::isUuid($parentId)) {
            throw new InvalidArgumentException('Reference parent_id must be a UUID.');
        }

        /** @var static|null $parent */
        $parent = $this->newQuery()->find($parentId);

        if (! $parent instanceof static) {
            return null;
        }

        $config = static::ownerScopeConfig();

        if (! $config->enabled) {
            return $parent;
        }

        [$childType, $childId] = $this->ownerTuple($child);
        [$parentType, $parentIdValue] = $this->ownerTuple($parent);

        if (($childType === null) !== ($childId === null) || ($parentType === null) !== ($parentIdValue === null)) {
            throw new InvalidArgumentException('Reference owner tuple is malformed: owner type and owner id must both be present or both be null.');
        }

        if ($parentType === null) {
            if ($childType === null) {
                return $parent;
            }

            return $config->includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal() ? $parent : null;
        }

        if ($childType === null) {
            return null;
        }

        return $childType === $parentType && (string) $childId === (string) $parentIdValue ? $parent : null;
    }

    private function isWorkKind(Reference $reference): bool
    {
        return (string) ($reference->getAttributes()['record_kind'] ?? '') === ReferenceRecordKind::Work->value;
    }

    /**
     * @return array{0: string|null, 1: string|int|null}
     */
    private function ownerTuple(Reference $reference): array
    {
        $config = static::ownerScopeConfig();
        $ownerType = $reference->getAttribute($config->ownerTypeColumn);
        $ownerId = $reference->getAttribute($config->ownerIdColumn);

        return [
            $ownerType === null ? null : (string) $ownerType,
            $ownerId,
        ];
    }
}
