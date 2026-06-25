<?php

declare(strict_types=1);

namespace AIArmada\References\Enums;

enum ReferenceType: string
{
    case Book = 'book';
    case Article = 'article';
    case Thesis = 'thesis';
    case Fatwa = 'fatwa';
    case Video = 'video';
    case Audio = 'audio';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Book => 'Book',
            self::Article => 'Article',
            self::Thesis => 'Thesis',
            self::Fatwa => 'Fatwa',
            self::Video => 'Video',
            self::Audio => 'Audio',
            self::Website => 'Website',
            self::Other => 'Other',
        };
    }
}
