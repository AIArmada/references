<?php

declare(strict_types=1);

namespace AIArmada\References\Traits;

use AIArmada\References\Enums\ReferencePartType;

trait HasReferenceParts
{
    public function getPart(string $type): ?array
    {
        $parts = $this->reference_parts ?? [];

        foreach ($parts as $part) {
            if (($part['type'] ?? null) === $type) {
                return $part;
            }
        }

        return null;
    }

    public function setPart(ReferencePartType $type, string $value): void
    {
        $parts = $this->reference_parts ?? [];
        $found = false;

        foreach ($parts as $index => $part) {
            if (($part['type'] ?? null) === $type->value) {
                $parts[$index]['value'] = $value;
                $found = true;

                break;
            }
        }

        if (! $found) {
            $parts[] = [
                'type' => $type->value,
                'value' => $value,
            ];
        }

        $this->reference_parts = $parts;
    }

    public function removePart(ReferencePartType $type): void
    {
        $parts = $this->reference_parts ?? [];

        $this->reference_parts = array_values(
            array_filter($parts, fn (array $part): bool => ($part['type'] ?? null) !== $type->value),
        );
    }

    public function hasPart(ReferencePartType $type): bool
    {
        $parts = $this->reference_parts ?? [];

        foreach ($parts as $part) {
            if (($part['type'] ?? null) === $type->value) {
                return true;
            }
        }

        return false;
    }

    public function getPartsGrouped(): array
    {
        $parts = $this->reference_parts ?? [];

        $grouped = [];

        foreach ($parts as $part) {
            $typeValue = $part['type'] ?? null;

            if ($typeValue === null) {
                continue;
            }

            $enum = ReferencePartType::tryFrom($typeValue);

            $grouped[] = [
                'type' => $typeValue,
                'label' => $enum?->label() ?? $typeValue,
                'value' => $part['value'] ?? '',
            ];
        }

        return $grouped;
    }
}
