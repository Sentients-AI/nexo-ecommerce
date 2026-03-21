<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

final class SocialiteController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        // Find existing user by google_id or email (to link accounts)
        $user = User::query()->withoutTenancy()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            // Link google_id if not yet set
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        } else {
            $user = User::query()->withoutTenancy()->create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => Str::password(32),
                'tenant_id' => Context::get('tenant_id'),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }
}
