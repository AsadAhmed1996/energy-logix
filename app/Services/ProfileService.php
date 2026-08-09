<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    /**
     * Update the user's profile information.
     */
    public function updateProfile(User $user, array $data): void
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            $user->sendEmailVerificationNotification();
        }

        $user->save();
    }
}
