<?php

declare(strict_types=1);

namespace AIArmada\References\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Models\Reference;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ReferencePolicy
{
    use HandlesAuthorization;

    private function canAccessReference(Reference $reference): bool
    {
        if (! (bool) config('references.owner.enabled', false)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $reference->isGlobal();
        }

        if ($reference->belongsToOwner($owner)) {
            return true;
        }

        return (bool) config('references.owner.include_global', false)
            && $reference->isGlobal();
    }

    /**
     * Determine whether the user can view any references.
     */
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the reference.
     */
    public function view(mixed $user, Reference $reference): bool
    {
        return $this->canAccessReference($reference);
    }

    /**
     * Determine whether the user can create references.
     */
    public function create(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the reference.
     */
    public function update(mixed $user, Reference $reference): bool
    {
        return $this->canAccessReference($reference);
    }

    /**
     * Determine whether the user can update any references.
     */
    public function updateAny(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the reference.
     */
    public function delete(mixed $user, Reference $reference): bool
    {
        return $this->canAccessReference($reference);
    }

    /**
     * Determine whether the user can duplicate the reference.
     */
    public function duplicate(mixed $user, Reference $reference): bool
    {
        return $this->view($user, $reference);
    }
}
