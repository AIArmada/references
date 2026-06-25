<?php

declare(strict_types=1);

namespace AIArmada\References\Enums;

enum ReferencePartType: string
{
    case Jilid = 'jilid';
    case Juz = 'juz';
    case Surah = 'surah';
    case Chapter = 'chapter';
    case Section = 'section';
    case Page = 'page';

    public function label(): string
    {
        return match ($this) {
            self::Jilid => 'Jilid',
            self::Juz => 'Juz',
            self::Surah => 'Surah',
            self::Chapter => 'Bab',
            self::Section => 'Bahagian',
            self::Page => 'Halaman',
        };
    }
}
