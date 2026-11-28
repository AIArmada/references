<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Models\Reference;
use InvalidArgumentException;
use RuntimeException;

final readonly class TransitionReferenceStatus
{
    public function handle(Reference $reference, ReferenceStatus $status): Reference
    {
        if (! $reference->exists) {
            throw new InvalidArgumentException('Status transitions require a persisted reference.');
        }

        $transition = clone $reference;
        $transition->setRawAttributes($reference->getRawOriginal(), true);
        $transition->status = $status;

        if (! $transition->save()) {
            throw new RuntimeException('Reference status transition was cancelled.');
        }

        $reference->setRawAttributes($transition->getAttributes());
        $reference->syncChanges();
        $reference->syncOriginal();

        return $reference;
    }
}
