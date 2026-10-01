<?php

declare(strict_types=1);

namespace AIArmada\References\Enums;

enum ReferenceRecordKind: string
{
    case Work = 'work';
    case Edition = 'edition';
    case Part = 'part';
}
