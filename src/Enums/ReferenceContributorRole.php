<?php

declare(strict_types=1);

namespace AIArmada\References\Enums;

enum ReferenceContributorRole: string
{
    case Author = 'author';
    case Editor = 'editor';
    case Translator = 'translator';

    public function label(): string
    {
        return match ($this) {
            self::Author => 'Author',
            self::Editor => 'Editor',
            self::Translator => 'Translator',
        };
    }
}
