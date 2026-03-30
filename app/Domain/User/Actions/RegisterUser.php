<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\DTOs\RegisterUserData;
use App\Domain\User\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class RegisterUser
{
    /**
     * Execute the action to register a new user.
     */
    public function execute(RegisterUserData $data): User
    {
        $user = DB::transaction(fn () => User::query()->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'role_id' => $data->roleId,
        ]));

        $user->notify(new WelcomeNotification);

        return $user;
    }
}
