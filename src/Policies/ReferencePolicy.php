<?php

declare(strict_types=1);

namespace AIArmada\References\Policies;

use AIArmada\CommerceSupport\Policies\EnforcesOwnerScope;
use AIArmada\References\Models\Reference;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ReferencePolicy
{
    use EnforcesOwnerScope;
    use HandlesAuthorization;

    public function viewAny(mixed $user): bool
    {
        return $this->ownerContextAllowsAccess(Reference::class);
    }

    public function view(mixed $user, Reference $reference): bool
    {
        return $this->isViewableInOwnerScope($reference);
    }

    public function create(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(mixed $user, Reference $reference): bool
    {
        return $this->isMutableInOwnerScope($reference);
    }

    public function updateAny(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(mixed $user, Reference $reference): bool
    {
        return $this->isMutableInOwnerScope($reference);
    }

    public function duplicate(mixed $user, Reference $reference): bool
    {
        return $this->view($user, $reference);
    }
}
