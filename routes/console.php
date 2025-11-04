<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('user:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("User with email {$email} not found.");
        return;
    }

    $user->forceFill([
        'role'               => User::ROLE_ADMIN,
        'email_verified_at'  => now(),
    ])->save();

    $this->info("✅ {$user->email} is now an admin.");
})->describe('Promote user to admin by email');
