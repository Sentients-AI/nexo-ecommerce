<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Tenant\Actions\CreateTenantWithAdminUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\OnboardingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class OnboardingController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Onboarding/Create', [
            'baseDomain' => config('tenancy.base_domain'),
            'reservedSlugs' => config('tenancy.reserved_subdomains', []),
        ]);
    }

    public function store(OnboardingRequest $request, CreateTenantWithAdminUser $action): RedirectResponse
    {
        $validated = $request->validated();

        ['tenant' => $tenant, 'user' => $user] = $action->execute(
            storeName: $validated['store_name'],
            storeSlug: $validated['store_slug'],
            storeEmail: $validated['store_email'],
            userName: $validated['name'],
            userEmail: $validated['email'],
            password: $validated['password'],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('vendor.dashboard')->with('success', "Welcome to {$tenant->name}! Your 14-day trial has started.");
    }
}
