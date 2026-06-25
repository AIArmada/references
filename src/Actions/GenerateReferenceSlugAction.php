<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Models\Reference;
use Illuminate\Support\Str;

final class GenerateReferenceSlugAction
{
    public function execute(Reference $reference, ?string $source = null): string
    {
        $field = $source ?? (string) config('references.slug.source', 'title');
        $maxLength = max(1, (int) config('references.slug.max_length', 200));

        $baseSlug = Str::limit(
            Str::slug((string) $reference->getAttribute($field)),
            $maxLength,
            '',
        );

        if ($baseSlug === '') {
            $baseSlug = Str::limit('reference', $maxLength, '');
        }

        $slug = $baseSlug;
        $suffix = 1;

        while ($this->slugExists($slug, $reference)) {
            $suffixValue = '-' . $suffix;
            $slug = Str::limit(
                $baseSlug,
                max(1, $maxLength - mb_strlen($suffixValue)),
                '',
            ) . $suffixValue;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, Reference $reference): bool
    {
        $query = Reference::where('slug', $slug);

        if ($reference->exists) {
            $query->where('id', '!=', $reference->id);
        }

        return $query->exists();
    }
}
