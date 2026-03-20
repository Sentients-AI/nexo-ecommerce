<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Referral\Models\ReferralCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ReferralWebController extends Controller
{
    /**
     * Show the referral landing page for a given code.
     */
    public function show(string $code): Response|RedirectResponse
    {
        $referralCode = ReferralCode::query()
            ->withoutTenancy()
            ->where('code', $code)
            ->first();

        if ($referralCode === null || ! $referralCode->isValid()) {
            return redirect('/')->with('flash', [
                'type' => 'error',
                'message' => 'This referral link is no longer valid.',
            ]);
        }

        return Inertia::render('Referral/Show', [
            'referralCode' => [
                'code' => $referralCode->code,
                'referee_discount_percent' => $referralCode->referee_discount_percent,
                'expires_at' => $referralCode->expires_at,
            ],
        ]);
    }
}
