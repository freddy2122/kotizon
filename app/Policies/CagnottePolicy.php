<?php

namespace App\Policies;

use App\Models\Cagnotte;
use App\Models\User;

class CagnottePolicy
{
    public function view(User $user = null, Cagnotte $cagnotte): bool
    {
        return true; // Public view
    }

    public function update(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }

    public function delete(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }

    public function addPhotos(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }

    public function removePhoto(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }

    public function publish(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }

    public function unpublish(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }

    public function preview(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }

    public function unpreview(User $user, Cagnotte $cagnotte): bool
    {
        return $user->id === $cagnotte->user_id;
    }
}
