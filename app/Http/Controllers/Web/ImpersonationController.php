<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class ImpersonationController
{
    /**
     * Start impersonating the given user.
     * Only super admins (tenant_id = null) may impersonate.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        abort_unless($admin->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 403, 'Cannot impersonate another super admin.');

        $request->session()->put('impersonating_as', $user->id);
        $request->session()->put('original_admin_id', $admin->id);

        Auth::loginUsingId($user->id);
        $request->session()->regenerate();

        Log::info('impersonation.started', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return redirect('/en');
    }

    /**
     * Stop impersonating and restore the original super admin session.
     */
    public function stop(Request $request): RedirectResponse
    {
        $originalAdminId = $request->session()->pull('original_admin_id');
        $request->session()->forget('impersonating_as');

        if ($originalAdminId) {
            Auth::loginUsingId($originalAdminId);
            $request->session()->regenerate();

            Log::info('impersonation.stopped', [
                'admin_id' => $originalAdminId,
                'ip' => $request->ip(),
            ]);
        }

        return redirect('/control-plane');
    }
}
