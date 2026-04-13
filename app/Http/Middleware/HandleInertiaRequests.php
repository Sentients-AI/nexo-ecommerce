<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'unread_notifications_count' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'currency' => fn () => Context::get('tenant')?->getSetting('currency', config('tenancy.default_settings.currency', 'MYR')) ?? 'MYR',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'impersonation' => fn () => $this->resolveImpersonation($request),
            'locale' => $locale,
            'supportedLocales' => ['en', 'ar', 'ms'],
            'isRtl' => $locale === 'ar',
            'translations' => fn () => $this->getTranslations($locale),
        ];
    }

    /**
     * Resolve impersonation state for the current request.
     *
     * @return array{active: bool, original_admin_name: string|null}
     */
    private function resolveImpersonation(Request $request): array
    {
        $originalAdminId = $request->session()->get('original_admin_id');

        if (! $originalAdminId) {
            return ['active' => false, 'original_admin_name' => null];
        }

        $admin = User::query()->find($originalAdminId);

        return [
            'active' => true,
            'original_admin_name' => $admin?->name,
        ];
    }

    /**
     * Load translations for the given locale.
     *
     * @return array<string, string>
     */
    private function getTranslations(string $locale): array
    {
        $path = lang_path("{$locale}/ui.php");

        if (file_exists($path)) {
            return require $path;
        }

        return require lang_path('en/ui.php');
    }
}
